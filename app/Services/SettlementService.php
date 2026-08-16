<?php

namespace App\Services;

use App\Mail\PaymentConfirmationMail;
use App\Mail\SaleEndedMail;
use App\Models\NotificationLogEntry;
use App\Models\Order;
use App\Models\Sale;
use App\Models\Settlement;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Everything that happens between a sale ending and a creator being paid
 * (spec section 5.7).
 */
final class SettlementService
{
    public function __construct(
        private readonly CommissionCalculator $calculator,
    ) {}

    /**
     * Close a sale out: freeze each participating creator's figures into a
     * settlement row, stamp the sale as closed, and email the creators that
     * their report is ready.
     *
     * Safe to call twice -- a sale that is already closed is left alone, so a
     * scheduler that fires during a deploy cannot double-send the emails.
     */
    public function closeSale(Sale $sale, bool $notify = true): int
    {
        if ($sale->isClosedOut()) {
            return 0;
        }

        $created = 0;

        DB::transaction(function () use ($sale, &$created) {
            /*
             | "For every creator who made at least one sale during it" -- a
             | creator whose only orders were refunded has not made a sale, so
             | they get no settlement row and no email.
             */
            $userIds = Order::query()
                ->where('sale_id', $sale->id)
                ->where('is_refunded', false)
                ->distinct()
                ->pluck('user_id');

            $users = User::with('profile')->whereIn('id', $userIds)->get();

            foreach ($users as $user) {
                $summary = $this->summariseForSnapshot($user, $sale);

                Settlement::updateOrCreate(
                    ['sale_id' => $sale->id, 'user_id' => $user->id],
                    [
                        'status' => Settlement::STATUS_PENDING,
                        'currency' => $summary->currency,
                        'units_sold' => $summary->unitsSold,
                        'refunded_orders' => $summary->refundedOrders,
                        'gross_earnings' => $summary->grossEarnings,
                        'gst_amount' => $summary->gstAmount,
                        'net_sales' => $summary->netSales,
                        'commission_rate' => $summary->commissionRate,
                        'commission_amount' => $summary->commissionAmount,
                        'transaction_fee' => $summary->transactionFee,
                        'payout_amount' => $summary->payoutAmount,
                        'finalised_at' => Carbon::now(),
                    ]
                );

                $created++;
            }

            // Stamped last, inside the same transaction: until this is set the
            // dashboard still calculates live, so a half-finished close cannot
            // show a creator a locked report that is missing orders.
            $sale->forceFill(['closed_at' => Carbon::now()])->save();
        });

        if ($notify) {
            $this->sendSaleEndedEmails($sale);
        }

        Log::channel('audit')->info('Sale closed out', [
            'sale_id' => $sale->id,
            'sale' => $sale->name,
            'settlements_created' => $created,
        ]);

        return $created;
    }

    /**
     * Record a creator's invoice. Only possible once the sale has ended
     * (spec section 5.7 -- the option stays disabled while a sale is live).
     */
    public function attachInvoice(Settlement $settlement, UploadedFile $file): void
    {
        if (! $settlement->sale->hasEnded()) {
            throw new RuntimeException('Invoices can only be uploaded once the sale has ended.');
        }

        if ($settlement->isPaid()) {
            throw new RuntimeException('This commission has already been paid.');
        }

        // Replace rather than accumulate -- a creator re-uploading a corrected
        // invoice should not leave the old one lying around on disk.
        if ($settlement->invoice_path && Storage::disk('invoices')->exists($settlement->invoice_path)) {
            Storage::disk('invoices')->delete($settlement->invoice_path);
        }

        $name = sprintf(
            'invoice-%s-%s-%s.%s',
            $settlement->sale->slug,
            $settlement->user_id,
            Carbon::now()->format('YmdHis'),
            $file->getClientOriginalExtension()
        );

        $path = $file->storeAs(
            (string) $settlement->user_id,
            $name,
            ['disk' => 'invoices']
        );

        $settlement->forceFill([
            'invoice_path' => $path,
            'invoice_original_name' => $file->getClientOriginalName(),
            'invoice_size' => $file->getSize(),
            'invoice_uploaded_at' => Carbon::now(),
            'status' => Settlement::STATUS_INVOICE_UPLOADED,
        ])->save();

        Log::channel('audit')->info('Invoice uploaded', [
            'settlement_id' => $settlement->id,
            'user_id' => $settlement->user_id,
            'sale_id' => $settlement->sale_id,
        ]);
    }

    /**
     * The internal control from spec section 5.7: recording the payment is what
     * sends the confirmation email and flips the status to Paid.
     */
    public function markPaid(
        Settlement $settlement,
        float $amount,
        Carbon $paidOn,
        ?string $reference,
        User $admin,
    ): void {
        if (! $admin->isAdmin()) {
            throw new RuntimeException('Only authorised team members can record a payment.');
        }

        if ($settlement->isPaid()) {
            throw new RuntimeException('This commission is already marked as paid.');
        }

        $settlement->forceFill([
            'status' => Settlement::STATUS_PAID,
            'paid_amount' => $amount,
            'paid_on' => $paidOn,
            'payment_reference' => $reference,
            'paid_by_user_id' => $admin->id,
            'paid_at' => Carbon::now(),
        ])->save();

        $settlement->loadMissing('user.profile', 'sale');

        Mail::to($settlement->user->email)->send(new PaymentConfirmationMail($settlement));

        NotificationLogEntry::create([
            'user_id' => $settlement->user_id,
            'sale_id' => $settlement->sale_id,
            'type' => NotificationLogEntry::TYPE_PAYMENT_CONFIRMED,
            'sent_at' => Carbon::now(),
        ]);

        Log::channel('audit')->info('Commission marked paid', [
            'settlement_id' => $settlement->id,
            'user_id' => $settlement->user_id,
            'sale_id' => $settlement->sale_id,
            'amount' => $amount,
            'currency' => $settlement->currency,
            'recorded_by' => $admin->id,
        ]);
    }

    /**
     * Recompute a creator's figures for the snapshot. Deliberately reads the
     * orders table directly rather than going through SaleSummaryService,
     * which would short-circuit to the snapshot we are about to write.
     */
    private function summariseForSnapshot(User $user, Sale $sale): CommissionBreakdown
    {
        $base = Order::query()->where('user_id', $user->id)->where('sale_id', $sale->id);

        return $this->calculator->summarise(
            currency: $user->payoutCurrency(),
            unitsSold: (clone $base)->where('is_refunded', false)->count(),
            refundedOrders: (clone $base)->where('is_refunded', true)->count(),
            grossEarnings: (float) (clone $base)->where('is_refunded', false)->sum('converted_amount'),
            commissionRate: $user->commissionRate(),
        );
    }

    /**
     * Spec section 6.2: settlement emails are always sent, regardless of the
     * creator's activity notification switches.
     */
    private function sendSaleEndedEmails(Sale $sale): void
    {
        $settlements = Settlement::with('user.profile', 'sale')
            ->where('sale_id', $sale->id)
            ->get();

        foreach ($settlements as $settlement) {
            $alreadySent = NotificationLogEntry::where('user_id', $settlement->user_id)
                ->where('sale_id', $sale->id)
                ->where('type', NotificationLogEntry::TYPE_SALE_ENDED)
                ->exists();

            if ($alreadySent) {
                continue;
            }

            Mail::to($settlement->user->email)->send(new SaleEndedMail($settlement));

            NotificationLogEntry::create([
                'user_id' => $settlement->user_id,
                'sale_id' => $sale->id,
                'type' => NotificationLogEntry::TYPE_SALE_ENDED,
                'sent_at' => Carbon::now(),
            ]);
        }
    }
}
