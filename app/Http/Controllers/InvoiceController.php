<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use App\Models\Settlement;
use App\Models\User;
use App\Services\SettlementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Invoice upload, spec section 5.7.
 *
 * "The 'Upload invoice' option, disabled while the sale was running, becomes
 * active so the creator can upload their invoice for the commission owed."
 *
 * The button is disabled in the view, and the rule is enforced again here and
 * once more in SettlementService -- a disabled button is a courtesy, not a
 * control.
 */
class InvoiceController extends Controller
{
    public function __construct(
        private readonly SettlementService $settlements,
    ) {}

    public function store(Request $request, Sale $sale): RedirectResponse
    {
        $user = $this->user();
        $settlement = $this->settlementFor($user, $sale);

        $request->validate([
            'invoice' => [
                'required',
                'file',
                'mimes:pdf,jpg,jpeg,png',
                'max:10240', // 10 MB
            ],
        ], [
            'invoice.mimes' => 'Please upload your invoice as a PDF, or as a JPG or PNG image.',
            'invoice.max' => 'That file is larger than 10 MB. Please upload a smaller version.',
        ]);

        try {
            $this->settlements->attachInvoice($settlement, $request->file('invoice'));
        } catch (RuntimeException $e) {
            return back()->withErrors(['invoice' => $e->getMessage()]);
        }

        return back()->with('status', 'Thank you — your invoice has been received. We will be in touch once payment is on its way.');
    }

    /**
     * Let a creator download the invoice they uploaded, so they can check we
     * received the right file.
     */
    public function download(Sale $sale): StreamedResponse
    {
        $user = $this->user();
        $settlement = $this->settlementFor($user, $sale);

        abort_unless($settlement->hasInvoice(), 404);
        abort_unless(Storage::disk('invoices')->exists($settlement->invoice_path), 404);

        return Storage::disk('invoices')->download(
            $settlement->invoice_path,
            $settlement->invoice_original_name ?? 'invoice.pdf'
        );
    }

    private function user(): User
    {
        /** @var User $user */
        $user = Auth::user();

        return $user;
    }

    /**
     * The creator's own settlement for this sale, or a 404. Scoped by user_id,
     * so one creator cannot reach another's settlement by id.
     */
    private function settlementFor(User $user, Sale $sale): Settlement
    {
        $settlement = Settlement::with('sale')
            ->where('user_id', $user->id)
            ->where('sale_id', $sale->id)
            ->first();

        abort_if($settlement === null, 404);

        return $settlement;
    }
}
