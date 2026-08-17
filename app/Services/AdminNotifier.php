<?php

namespace App\Services;

use App\Mail\AdminAlertMail;
use App\Models\Sale;
use App\Models\Settlement;
use App\Support\Money;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Operational updates to our own team's address.
 *
 * Two rules here, both deliberate:
 *
 *  - These are entirely separate from the creator notification switches in spec
 *    section 6. A creator turning their own emails off has no bearing on
 *    whether we hear that their invoice arrived.
 *
 *  - Nothing in here may ever break the thing that triggered it. An admin
 *    notification failing to send must not roll back a recorded payment or
 *    abort a sale close-out, so every send is wrapped and logged rather than
 *    allowed to propagate.
 */
final class AdminNotifier
{
    public function saleClosed(Sale $sale, int $creatorCount): void
    {
        if (! $this->enabled('on_sale_closed')) {
            return;
        }

        $settlements = Settlement::where('sale_id', $sale->id)->get();

        $facts = [
            'Sale' => $sale->name,
            'Ran' => $sale->starts_at->format('j M Y').' – '.$sale->ends_at->format('j M Y'),
            'Creators settled' => (string) $creatorCount,
            'Units sold' => (string) $settlements->sum('units_sold'),
        ];

        // Grouped by currency: creators are paid in INR or USD, so a single
        // total would be adding rupees to dollars.
        foreach ($settlements->groupBy('currency') as $currency => $group) {
            $facts["Payable ({$currency})"] = Money::format($group->sum('payout_amount'), $currency);
        }

        $this->send(
            headline: "{$sale->name} has been closed out",
            facts: $facts,
            actionUrl: route('admin.overview.show', $sale),
            actionLabel: 'Review the sale',
            subject: "Closed out: {$sale->name} — {$creatorCount} creators to pay",
        );
    }

    public function invoiceUploaded(Settlement $settlement): void
    {
        if (! $this->enabled('on_invoice_uploaded')) {
            return;
        }

        $settlement->loadMissing('user', 'sale');

        $this->send(
            headline: 'An invoice has arrived',
            facts: [
                'Creator' => $settlement->user->name,
                'Email' => $settlement->user->email,
                'Sale' => $settlement->sale->name,
                'Amount owed' => Money::format($settlement->payout_amount, $settlement->currency),
                'File' => $settlement->invoice_original_name ?? 'invoice',
            ],
            actionUrl: route('admin.settlements.index'),
            actionLabel: 'Record the payment',
            subject: "Invoice from {$settlement->user->name} — {$settlement->sale->name}",
        );
    }

    public function paymentRecorded(Settlement $settlement): void
    {
        if (! $this->enabled('on_payment_recorded')) {
            return;
        }

        $settlement->loadMissing('user', 'sale', 'paidBy');

        $this->send(
            headline: 'A payment was recorded',
            facts: [
                'Creator' => $settlement->user->name,
                'Sale' => $settlement->sale->name,
                'Amount paid' => Money::format($settlement->paid_amount ?? 0, $settlement->currency),
                'Date' => $settlement->paid_on?->format('j F Y') ?? '—',
                'Reference' => $settlement->payment_reference ?: '—',
                'Recorded by' => $settlement->paidBy?->name ?? '—',
            ],
            actionUrl: route('admin.creators.show', $settlement->user),
            actionLabel: 'View the creator',
            subject: "Paid: {$settlement->user->name} — {$settlement->sale->name}",
        );
    }

    /**
     * @param  array<string, string>  $facts
     */
    private function send(
        string $headline,
        array $facts,
        ?string $actionUrl = null,
        ?string $actionLabel = null,
        ?string $subject = null,
    ): void {
        $to = (string) config('affiliate.admin.email');

        if (! filter_var($to, FILTER_VALIDATE_EMAIL)) {
            Log::channel('audit')->warning('Admin alert not sent: ADMIN_EMAIL is not a valid address', [
                'configured' => $to,
                'headline' => $headline,
            ]);

            return;
        }

        try {
            Mail::to($to)->send(new AdminAlertMail(
                headline: $headline,
                facts: $facts,
                actionUrl: $actionUrl,
                actionLabel: $actionLabel,
                subjectLine: $subject,
            ));
        } catch (Throwable $e) {
            // Never allowed to take down whatever triggered it.
            Log::channel('audit')->error('Admin alert failed to send', [
                'headline' => $headline,
                'to' => $to,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function enabled(string $key): bool
    {
        return (bool) config('affiliate.admin_alerts.enabled', true)
            && (bool) config("affiliate.admin_alerts.{$key}", true);
    }
}
