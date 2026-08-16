<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Sale;
use App\Models\Settlement;
use App\Models\User;
use App\Services\EncouragementService;
use App\Services\OrderTable;
use App\Services\SaleSummaryService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * The sales dashboard (spec section 5).
 *
 * Every query in here is scoped to the signed-in creator. Spec section 9: "A
 * creator must only ever see their own data."
 */
class DashboardController extends Controller
{
    private const ORDERS_PER_PAGE = 25;

    public function __construct(
        private readonly SaleSummaryService $summaries,
        private readonly OrderTable $table,
        private readonly EncouragementService $encouragement,
    ) {}

    /**
     * Spec section 4: "after signing in an affiliate goes straight to their
     * sales dashboard -- no menus or choices to navigate. The very first
     * screen is the current ongoing sale / the latest sale that ended."
     */
    public function index(): RedirectResponse|View
    {
        $user = $this->user();
        $sales = $this->salesFor($user);

        if ($sales->isEmpty()) {
            return view('dashboard.empty');
        }

        // The live one if there is one, otherwise the most recently ended.
        $first = $sales->first(fn (Sale $sale) => $sale->isLive()) ?? $sales->first();

        return redirect()->route('sales.show', $first);
    }

    public function show(Request $request, Sale $sale): View
    {
        $user = $this->user();

        $this->assertParticipated($user, $sale);

        $sales = $this->salesFor($user);
        $summary = $this->summaries->for($user, $sale);
        $orders = $this->ordersFor($user, $sale, $request);
        $settlement = $this->settlementFor($user, $sale);

        return view('dashboard.show', [
            'sale' => $sale,
            'sales' => $sales,
            'summary' => $summary,
            'orders' => $orders,
            'rows' => $this->table->rows($orders->getCollection(), $orders->firstItem() ?? 1),
            'settlement' => $settlement,
            'encouragement' => $this->encouragement->messageFor(
                $sale,
                $summary->unitsSold,
                $this->recentOrderCount($user, $sale),
            ),
            'milestone' => $this->encouragement->milestoneFor(
                $user,
                $summary->unitsSold,
                $summary->payoutAmount,
                $summary->currency,
            ),
            'pollSeconds' => (int) config('affiliate.poll_seconds', 5),
        ]);
    }

    /**
     * The polling endpoint behind spec section 5.6.
     *
     * Returns the summary and the current page of orders as JSON, so a creator
     * with the dashboard open watches the numbers move without refreshing.
     * Deliberately excluded from refreshing the idle timer -- see
     * EnforceSessionTimeout.
     */
    public function live(Request $request, Sale $sale): JsonResponse
    {
        $user = $this->user();

        $this->assertParticipated($user, $sale);

        // A closed sale is locked, so there is nothing to poll for. Telling the
        // browser to stop saves a request every five seconds per open tab.
        if ($sale->isClosedOut()) {
            return response()->json([
                'live' => false,
                'reason' => 'This sale has ended and its report is final.',
            ]);
        }

        $summary = $this->summaries->for($user, $sale);
        $orders = $this->ordersFor($user, $sale, $request);

        return response()->json([
            'live' => $sale->isLive(),
            'updated_at' => Carbon::now()->toIso8601String(),
            'summary' => $summary->toArray(),
            'rows' => $this->table->rows($orders->getCollection(), $orders->firstItem() ?? 1),
            'total_orders' => $orders->total(),
            'encouragement' => $this->encouragement->messageFor(
                $sale,
                $summary->unitsSold,
                $this->recentOrderCount($user, $sale),
            ),
        ]);
    }

    // --- Internals -------------------------------------------------------

    private function user(): User
    {
        /** @var User $user */
        $user = Auth::user();

        // Both are read by the views (the rate in the summary header, the codes
        // in the orders heading), so they are loaded once here rather than
        // lazily from inside a Blade template.
        $user->loadMissing('profile', 'couponCodes');

        return $user;
    }

    /**
     * The sales this creator took part in, newest first, for the sale picker.
     *
     * @return \Illuminate\Support\Collection<int, Sale>
     */
    private function salesFor(User $user): \Illuminate\Support\Collection
    {
        return Sale::query()
            ->forAffiliate($user)
            ->orderByDesc('starts_at')
            ->get();
    }

    /**
     * @return LengthAwarePaginator<Order>
     */
    private function ordersFor(User $user, Sale $sale, Request $request): LengthAwarePaginator
    {
        return Order::query()
            ->with('couponCode')
            ->where('user_id', $user->id)
            ->where('sale_id', $sale->id)
            ->orderByDesc('placed_at')
            ->orderByDesc('id')
            ->paginate(self::ORDERS_PER_PAGE)
            ->withQueryString();
    }

    private function settlementFor(User $user, Sale $sale): ?Settlement
    {
        return Settlement::where('user_id', $user->id)
            ->where('sale_id', $sale->id)
            ->first();
    }

    /**
     * Orders in the last hour, which is what decides whether the creator sees
     * the "this is moving right now" nudge rather than a general one.
     */
    private function recentOrderCount(User $user, Sale $sale): int
    {
        if (! $sale->isLive()) {
            return 0;
        }

        return Order::where('user_id', $user->id)
            ->where('sale_id', $sale->id)
            ->where('is_refunded', false)
            ->where('placed_at', '>=', Carbon::now()->subHour())
            ->count();
    }

    /**
     * A creator may only open a sale their own codes were actually used in.
     *
     * 404 rather than 403 on purpose: a creator who guesses a slug should not
     * be able to learn that a campaign by that name exists.
     */
    private function assertParticipated(User $user, Sale $sale): void
    {
        $participated = Order::where('user_id', $user->id)
            ->where('sale_id', $sale->id)
            ->exists();

        abort_unless($participated, 404);
    }
}
