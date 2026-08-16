<?php

namespace App\Services;

use App\Models\Order;
use App\Support\Money;
use Illuminate\Support\Collection;

/**
 * The orders table from spec section 5.3, with "exactly these columns".
 *
 * One presenter shared by the dashboard, the polling response and the Excel
 * export, so the downloaded report always matches what was on screen -- and so
 * the masking rules cannot be applied in one place and forgotten in another.
 */
final class OrderTable
{
    /**
     * The column headings, in the order the spec lists them.
     *
     * @var list<string>
     */
    public const COLUMNS = [
        'S No',
        'Order ID',
        'Order Date/Time',
        'Name',
        'Code',
        'Country',
        'State',
        'Plugin',
        'Currency',
        'Amount',
    ];

    /**
     * @param  Collection<int, Order>  $orders
     * @param  int  $startingSerial  First serial number, so pagination
     *                               continues numbering rather than restarting.
     * @return list<array<string, mixed>>
     */
    public function rows(Collection $orders, int $startingSerial = 1): array
    {
        $serial = $startingSerial;

        return $orders->map(function (Order $order) use (&$serial) {
            return [
                'serial' => $serial++,
                'order_id' => $order->maskedOrderRef(),
                'placed_at' => $order->placed_at->format('d M Y, H:i'),
                'placed_at_iso' => $order->placed_at->toIso8601String(),
                'name' => $order->maskedCustomerName(),
                'code' => $order->couponCode->code,
                'country' => $order->country,
                'state' => $order->state ?? '—',
                'plugin' => $order->plugin,
                'currency' => $order->currency,

                // Shown in the currency the customer actually paid in
                // (spec section 5.4), not converted.
                'amount' => Money::plain($order->amount, $order->currency),

                'is_refunded' => $order->is_refunded,
            ];
        })->all();
    }

    /**
     * The same rows as flat arrays in column order, for the Excel export.
     *
     * @param  Collection<int, Order>  $orders
     * @return list<list<string|int>>
     */
    public function spreadsheetRows(Collection $orders): array
    {
        return array_map(
            fn (array $row) => [
                $row['serial'],
                $row['order_id'],
                $row['placed_at'],
                $row['name'],
                $row['code'],
                $row['country'],
                $row['state'],
                $row['plugin'],
                $row['currency'],
                $row['amount'].($row['is_refunded'] ? ' (refunded)' : ''),
            ],
            $this->rows($orders)
        );
    }
}
