<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Sale;
use App\Services\AdminSaleOverview;
use App\Services\AdminSaleReportExport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * What our team sees first: every creator's numbers for one sale, side by side.
 *
 * Mirrors the creator dashboard deliberately -- same sale picker, same live
 * updating, same commission figures -- so the two views cannot drift apart and
 * a creator asking "what does your screen say?" gets the same answer.
 */
class OverviewController extends Controller
{
    public function __construct(
        private readonly AdminSaleOverview $overview,
        private readonly AdminSaleReportExport $export,
    ) {}

    /**
     * Land on the sale that is running, or the most recent one that ended.
     */
    public function index(): RedirectResponse|View
    {
        $sale = $this->defaultSale();

        if (! $sale) {
            return view('admin.no-sales');
        }

        return redirect()->route('admin.overview.show', $sale);
    }

    public function show(Sale $sale): View
    {
        $data = $this->overview->for($sale);

        return view('admin.overview', [
            'sale' => $sale,
            'sales' => Sale::orderByDesc('starts_at')->get(),
            'rows' => $data['rows'],
            'totals' => $data['totals'],
            'creators' => $data['creators'],
            'units' => $data['units'],
            'refunded' => $data['refunded'],
            'locked' => $data['locked'],
            'pollSeconds' => (int) config('affiliate.poll_seconds', 5),
        ]);
    }

    /**
     * Polling endpoint, so the overview moves during a live sale exactly as a
     * creator's own dashboard does.
     */
    public function live(Sale $sale): JsonResponse
    {
        if ($sale->isClosedOut()) {
            return response()->json([
                'live' => false,
                'reason' => 'This sale has been closed out and its figures are final.',
            ]);
        }

        $data = $this->overview->for($sale);

        return response()->json([
            'live' => $sale->isLive(),
            'updated_at' => Carbon::now()->toIso8601String(),
            'rows' => array_map(fn (array $row) => [
                'user_id' => $row['user_id'],
                'name' => $row['name'],
                'email' => $row['email'],
                'codes' => $row['codes'],
                'currency' => $row['currency'],
                'units' => $row['units'],
                'refunded' => $row['refunded'],
                'gross' => $row['summary']->money($row['gross']),
                'rate' => $row['summary']->commissionRate * 100,
                'payout' => $row['summary']->money($row['payout']),
                'status' => $row['status'],
            ], $data['rows']),
            'totals' => $data['totals'],
            'creators' => $data['creators'],
            'units' => $data['units'],
            'refunded' => $data['refunded'],
        ]);
    }

    /**
     * The whole sale as one spreadsheet -- a row per creator, for our records.
     */
    public function download(Sale $sale): BinaryFileResponse
    {
        $path = $this->export->write($sale);

        return response()
            ->download($path, $this->export->filename($sale), [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ])
            ->deleteFileAfterSend();
    }

    private function defaultSale(): ?Sale
    {
        return Sale::query()->live()->orderByDesc('starts_at')->first()
            ?? Sale::query()->orderByDesc('starts_at')->first();
    }
}
