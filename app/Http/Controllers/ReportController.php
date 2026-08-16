<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Sale;
use App\Models\User;
use App\Services\SaleReportExport;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ReportController extends Controller
{
    public function __construct(
        private readonly SaleReportExport $export,
    ) {}

    /**
     * Spec section 5.7: once a sale has ended the creator can download the
     * report as an Excel file -- the summary plus the full orders list.
     */
    public function download(Sale $sale): BinaryFileResponse
    {
        /** @var User $user */
        $user = Auth::user();
        $user->loadMissing('profile');

        $participated = Order::where('user_id', $user->id)
            ->where('sale_id', $sale->id)
            ->exists();

        abort_unless($participated, 404);

        // The report is a settlement document, so it only exists once the
        // figures are final. While a sale runs, the dashboard is the record.
        abort_unless($sale->hasEnded(), 403, 'The report will be available to download once this sale has ended.');

        $path = $this->export->write($user, $sale);

        Log::channel('audit')->info('Report downloaded', [
            'user_id' => $user->id,
            'sale_id' => $sale->id,
        ]);

        return response()
            ->download($path, $this->export->filename($user, $sale), [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ])
            ->deleteFileAfterSend();
    }
}
