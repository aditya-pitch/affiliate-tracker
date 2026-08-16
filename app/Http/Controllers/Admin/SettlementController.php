<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Sale;
use App\Models\Settlement;
use App\Models\User;
use App\Services\SettlementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The internal control from spec section 5.7.
 *
 * The admin side is out of scope for the spec "except where the dashboard
 * depends on it", so this is deliberately the minimum: see who is owed what,
 * read their invoice, record the payment. Recording the payment is what sends
 * the confirmation email and flips the status to Paid.
 *
 * Everything here is behind the `admin` middleware.
 */
class SettlementController extends Controller
{
    public function __construct(
        private readonly SettlementService $settlements,
    ) {}

    public function index(Request $request): View
    {
        $status = $request->query('status', 'outstanding');

        $query = Settlement::query()
            ->with(['user.profile', 'sale'])
            ->join('sales', 'sales.id', '=', 'settlements.sale_id')
            ->orderByDesc('sales.ends_at')
            ->orderBy('settlements.id')
            ->select('settlements.*');

        $query = match ($status) {
            'paid' => $query->where('settlements.status', Settlement::STATUS_PAID),
            'invoiced' => $query->where('settlements.status', Settlement::STATUS_INVOICE_UPLOADED),
            'all' => $query,
            default => $query->whereIn('settlements.status', [
                Settlement::STATUS_PENDING,
                Settlement::STATUS_INVOICE_UPLOADED,
            ]),
        };

        return view('admin.settlements', [
            'settlements' => $query->paginate(30)->withQueryString(),
            'status' => $status,
            'salesAwaitingClose' => Sale::query()
                ->whereNull('closed_at')
                ->where('ends_at', '<', Carbon::now())
                ->orderBy('ends_at')
                ->get(),
        ]);
    }

    /**
     * Record a payment. Spec section 5.7: "Recording that payment (amount and
     * date) is what triggers the creator's payment-confirmation email and flips
     * the status to Paid."
     */
    public function markPaid(Request $request, Settlement $settlement): RedirectResponse
    {
        $validated = $request->validate([
            'paid_amount' => ['required', 'numeric', 'min:0'],
            'paid_on' => ['required', 'date', 'before_or_equal:today'],
            'payment_reference' => ['nullable', 'string', 'max:120'],
        ]);

        /** @var User $admin */
        $admin = Auth::user();

        $settlement->loadMissing('user.profile', 'sale');

        try {
            $this->settlements->markPaid(
                settlement: $settlement,
                amount: (float) $validated['paid_amount'],
                paidOn: Carbon::parse($validated['paid_on']),
                reference: $validated['payment_reference'] ?? null,
                admin: $admin,
            );
        } catch (RuntimeException $e) {
            return back()->withErrors(['payment' => $e->getMessage()]);
        }

        return back()->with('status', "Payment recorded. {$settlement->user->firstName()} has been emailed a confirmation.");
    }

    /**
     * Close a sale out by hand, for when it should not wait for the scheduler.
     */
    public function closeSale(Sale $sale): RedirectResponse
    {
        if ($sale->isClosedOut()) {
            return back()->withErrors(['sale' => 'That sale has already been closed out.']);
        }

        if (! $sale->hasEnded()) {
            return back()->withErrors(['sale' => 'That sale is still running.']);
        }

        $count = $this->settlements->closeSale($sale);

        return back()->with('status', "{$sale->name} closed out. {$count} creator reports finalised and emailed.");
    }

    public function downloadInvoice(Settlement $settlement): StreamedResponse
    {
        abort_unless($settlement->hasInvoice(), 404);
        abort_unless(Storage::disk('invoices')->exists($settlement->invoice_path), 404);

        return Storage::disk('invoices')->download(
            $settlement->invoice_path,
            $settlement->invoice_original_name ?? 'invoice.pdf'
        );
    }
}
