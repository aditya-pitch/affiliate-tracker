<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CouponCode;
use App\Models\Order;
use App\Models\Sale;
use App\Models\Settlement;
use App\Models\User;
use App\Services\CreatorProvisioner;
use App\Services\EncouragementService;
use App\Services\OrderTable;
use App\Services\SaleSummaryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Managing creator dashboards: who exists, setting new ones up, and sending
 * them their login details.
 *
 * The spec puts the admin side out of scope "except where the dashboard depends
 * on it", and a dashboard cannot exist for a creator who has not been created,
 * so this is that dependency made real.
 */
class CreatorController extends Controller
{
    public function __construct(
        private readonly CreatorProvisioner $provisioner,
    ) {}

    /**
     * Every creator dashboard we have, with the codes that feed it.
     */
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q', ''));

        $creators = User::query()
            ->with('profile', 'couponCodes')
            ->withCount(['orders as orders_count' => fn ($q) => $q->where('is_refunded', false)])
            ->where('role', User::ROLE_AFFILIATE)
            ->when($search !== '', fn ($q) => $q->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhereHas('couponCodes', fn ($c) => $c->where('code', 'like', "%{$search}%"));
            }))
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        return view('admin.creators.index', [
            'creators' => $creators,
            'search' => $search,
        ]);
    }

    public function create(): View
    {
        return view('admin.creators.create', [
            'suggestedPassword' => $this->provisioner->generatePassword(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateCreator($request);

        ['user' => $user, 'password' => $password] = $this->provisioner->create($data);

        if ($request->boolean('send_welcome')) {
            $sent = $this->provisioner->sendWelcome($user, $password);

            // The account exists either way. If the email failed, say so
            // plainly rather than claiming it was sent — the password below is
            // then the only copy that exists.
            $message = $sent
                ? "{$user->name}'s dashboard is ready, and their login details have been emailed to {$user->email}."
                : "{$user->name}'s dashboard is ready, but the email could not be sent. "
                    .'Copy the password below and pass it on yourself, then check your mail settings '
                    .'with: php artisan mail:verify';

            return redirect()
                ->route('admin.creators.show', $user)
                ->with('status', $message)
                ->with('issued_password', $password);
        }

        return redirect()
            ->route('admin.creators.show', $user)
            ->with('status', "{$user->name}'s dashboard is ready. Their password is shown below — this is the only time it can be displayed.")
            ->with('issued_password', $password);
    }

    public function show(User $user): View
    {
        $this->assertCreator($user);

        $user->load('profile', 'couponCodes');

        return view('admin.creators.show', [
            'creator' => $user,
            'settlements' => Settlement::with('sale')
                ->where('user_id', $user->id)
                ->join('sales', 'sales.id', '=', 'settlements.sale_id')
                ->orderByDesc('sales.ends_at')
                ->select('settlements.*')
                ->get(),
            'salesTakenPart' => Sale::forAffiliate($user)->orderByDesc('starts_at')->get(),
            'lifetimeOrders' => Order::where('user_id', $user->id)->where('is_refunded', false)->count(),
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $this->assertCreator($user);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'date_of_birth' => ['required', 'date', 'before:today'],
            'commission_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'payout_currency' => ['required', Rule::in(config('affiliate.payout_currencies'))],
            'country_code' => ['nullable', 'string', 'size:2'],
            'payout_account_name' => ['nullable', 'string', 'max:160'],
            'payout_details' => ['nullable', 'string', 'max:2000'],
            'gst_number' => ['nullable', 'string', 'max:20'],
            'pan_number' => ['nullable', 'string', 'max:20'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $user->update([
            'name' => $data['name'],
            'email' => strtolower(trim($data['email'])),
            'date_of_birth' => $data['date_of_birth'],
            'is_active' => $request->boolean('is_active'),
        ]);

        $user->profile->update([
            'commission_rate' => $data['commission_rate'] / 100,
            'payout_currency' => $data['payout_currency'],
            'country_code' => strtoupper($data['country_code'] ?? 'IN'),
            'payout_account_name' => $data['payout_account_name'] ?? null,
            'payout_details' => $data['payout_details'] ?? null,
            'gst_number' => $data['gst_number'] ?? null,
            'pan_number' => $data['pan_number'] ?? null,
        ]);

        return back()->with('status', 'Creator details updated.');
    }

    public function addCode(Request $request, User $user): RedirectResponse
    {
        $this->assertCreator($user);

        $request->validate([
            'code' => ['required', 'string', 'max:64', 'unique:coupon_codes,code'],
        ], [
            'code.unique' => 'That coupon code is already assigned to someone.',
        ]);

        $code = $this->provisioner->addCode($user, $request->string('code')->toString());

        return back()->with('status', "Code {$code->code} added to {$user->name}.");
    }

    public function toggleCode(CouponCode $code): RedirectResponse
    {
        $code->update(['is_active' => ! $code->is_active]);

        return back()->with(
            'status',
            "Code {$code->code} is now ".($code->is_active ? 'active' : 'inactive').'.'
        );
    }

    /**
     * Issue a new password. Shown once, here, and never again.
     */
    public function resetPassword(Request $request, User $user): RedirectResponse
    {
        $this->assertCreator($user);

        $password = $this->provisioner->resetPassword($user);

        if ($request->boolean('send_email')) {
            $sent = $this->provisioner->sendWelcome($user, $password);

            return back()
                ->with('status', $sent
                    ? "A new password has been issued and emailed to {$user->email}."
                    : 'A new password has been issued, but the email could not be sent. Copy it below — '
                        .'it cannot be shown again.')
                ->with('issued_password', $password);
        }

        return back()
            ->with('status', 'A new password has been issued. Copy it now — it cannot be shown again.')
            ->with('issued_password', $password);
    }

    public function sendWelcome(User $user): RedirectResponse
    {
        $this->assertCreator($user);

        $sent = $this->provisioner->sendWelcome($user);

        if (! $sent) {
            return back()->withErrors([
                'email' => 'That email could not be sent. Check your mail settings with: php artisan mail:verify',
            ]);
        }

        return back()->with(
            'status',
            "Sign-in instructions emailed to {$user->email}. It does not include a password — use ".
            '“Issue a new password” if they need one.'
        );
    }

    /**
     * See exactly what a creator sees. Read-only: the settlement actions are
     * theirs, not ours.
     */
    public function dashboard(
        User $user,
        Sale $sale,
        SaleSummaryService $summaries,
        OrderTable $table,
        EncouragementService $encouragement,
    ): View {
        $this->assertCreator($user);
        $user->load('profile', 'couponCodes');

        abort_unless(
            Order::where('user_id', $user->id)->where('sale_id', $sale->id)->exists(),
            404
        );

        $summary = $summaries->for($user, $sale);

        $orders = Order::query()
            ->with('couponCode')
            ->where('user_id', $user->id)
            ->where('sale_id', $sale->id)
            ->orderByDesc('placed_at')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        return view('dashboard.show', [
            'creator' => $user,
            'viewingAsAdmin' => true,
            'sale' => $sale,
            'sales' => Sale::forAffiliate($user)->orderByDesc('starts_at')->get(),
            'summary' => $summary,
            'orders' => $orders,
            'rows' => $table->rows($orders->getCollection(), $orders->firstItem() ?? 1),
            'settlement' => Settlement::where('user_id', $user->id)->where('sale_id', $sale->id)->first(),
            'encouragement' => $encouragement->messageFor($sale, $summary->unitsSold),
            'milestone' => null,
            'pollSeconds' => (int) config('affiliate.poll_seconds', 5),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validateCreator(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'date_of_birth' => ['required', 'date', 'before:today'],
            'commission_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'payout_currency' => ['required', Rule::in(config('affiliate.payout_currencies'))],
            'country_code' => ['nullable', 'string', 'size:2'],
            'codes' => ['required', 'string', 'max:500'],
            'payout_account_name' => ['nullable', 'string', 'max:160'],
            'payout_details' => ['nullable', 'string', 'max:2000'],
            'gst_number' => ['nullable', 'string', 'max:20'],
            'pan_number' => ['nullable', 'string', 'max:20'],
            'sale_notification_frequency' => ['required', Rule::in(['immediate', 'hourly', 'daily'])],
        ], [
            'codes.required' => 'Give the creator at least one coupon code — without one there is nothing to track.',
            'email.unique' => 'There is already an account on that email address.',
        ]);

        // Entered as a percentage because that is how the rate is agreed and
        // discussed; stored as a fraction because that is how it is used.
        $data['commission_rate'] = $data['commission_rate'] / 100;

        return $data;
    }

    private function assertCreator(User $user): void
    {
        abort_unless($user->isAffiliate(), 404);
    }
}
