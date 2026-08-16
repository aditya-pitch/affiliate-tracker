@extends('layouts.app')

@section('title', $sale->name)

@section('content')

    {{-- Spec 5.1: pick a sale. Live is marked live, finished ones ended. --}}
    <nav class="sale-picker" aria-label="Choose a sale">
        @foreach ($sales as $option)
            <a class="sale-tab" href="{{ route('sales.show', $option) }}"
               @if ($option->id === $sale->id) aria-current="page" @endif>
                <span class="sale-tab__name">{{ $option->name }}</span>
                <span class="sale-tab__meta">
                    @if ($option->isLive())
                        <span class="badge badge--live"><span class="pulse"></span> Live</span>
                    @else
                        <span class="badge badge--ended">Ended</span>
                    @endif
                    <span>{{ $option->starts_at->format('M Y') }}</span>
                </span>
            </a>
        @endforeach
    </nav>

    {{-- Spec section 1: encouragement while the sale runs. Never a telling-off. --}}
    <div class="nudge">
        <span class="nudge__icon" aria-hidden="true">{{ $sale->isLive() ? '🚀' : '🎉' }}</span>
        <div>
            <div class="nudge__text" data-nudge-text>{{ $encouragement }}</div>
            @if ($milestone)
                <span class="nudge__milestone">{{ $milestone }}</span>
            @endif
        </div>
    </div>

    <div class="stack"
         data-live-url="{{ route('dashboard.live', $sale) }}"
         data-poll-seconds="{{ $pollSeconds }}">

        {{-- ---------------------------------------------------------------
             Summary (spec 5.2)
        ---------------------------------------------------------------- --}}
        <section class="card">
            <div class="headline">
                <div>
                    <div class="headline__label">Your payout · {{ $summary->currency }}</div>
                    <div class="headline__value tabular" data-payout-value>
                        {{ $summary->money($summary->payoutAmount) }}
                    </div>
                    <div class="headline__note">
                        {{ $sale->name }} ·
                        {{ $sale->starts_at->format('j M Y') }} – {{ $sale->ends_at->format('j M Y') }}
                        @if ($sale->hasEnded())
                            · <strong>Final</strong>
                        @endif
                    </div>
                </div>

                <div class="headline__stats">
                    <div>
                        <div class="headline__stat-label">Units sold</div>
                        <div class="headline__stat-value tabular" data-units-value>{{ $summary->unitsSold }}</div>
                    </div>
                    <div>
                        <div class="headline__stat-label">Refunded</div>
                        <div class="headline__stat-value tabular" data-refunded-value>{{ $summary->refundedOrders }}</div>
                    </div>
                    <div>
                        <div class="headline__stat-label">Your rate</div>
                        <div class="headline__stat-value tabular">{{ auth()->user()->profile->commissionRatePercent() }}</div>
                    </div>
                </div>
            </div>

            <table class="summary">
                <caption class="sr-only">Summary of your sales for {{ $sale->name }}</caption>
                <tbody data-summary-body>
                @foreach ($summary->rows() as $row)
                    <tr class="{{ $row['emphasis'] ? 'is-total' : ($row['muted'] ? 'is-muted' : '') }}">
                        <td>{{ $row['label'] }}</td>
                        <td class="tabular">{{ $row['value'] }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </section>

        {{-- ---------------------------------------------------------------
             Settlement (spec 5.7) — only once the sale has ended
        ---------------------------------------------------------------- --}}
        @if ($sale->hasEnded())
            <section class="card">
                <div class="card__head">
                    <div>
                        <h2>Getting paid</h2>
                        <p class="small muted mb-0">
                            This sale has ended, so these figures are final.
                        </p>
                    </div>
                    @if ($settlement)
                        <span class="badge badge--{{ $settlement->isPaid() ? 'paid' : ($settlement->hasInvoice() ? 'invoiced' : 'awaiting') }}">
                            {{ $settlement->stageLabel() }}
                        </span>
                    @endif
                </div>

                <div class="card__body">
                    <div class="settle">

                        {{-- Download the report --}}
                        <div class="settle__box">
                            <h3>Download your report</h3>
                            <p>The summary above plus your full orders list, as an Excel file.</p>
                            <a class="btn btn--outline btn--block" href="{{ route('sales.report', $sale) }}">
                                Download .xlsx
                            </a>
                        </div>

                        {{-- Upload the invoice --}}
                        <div class="settle__box">
                            <h3>Send us your invoice</h3>

                            @if (! $settlement)
                                <p>There is nothing to invoice for this sale.</p>
                            @elseif ($settlement->isPaid())
                                <p>
                                    Paid on {{ $settlement->paid_on?->format('j F Y') }}.
                                    Nothing further needed — thank you.
                                </p>
                                @if ($settlement->hasInvoice())
                                    <a class="btn btn--outline btn--block btn--sm"
                                       href="{{ route('sales.invoice.download', $sale) }}">
                                        View the invoice you sent
                                    </a>
                                @endif
                            @else
                                <p>
                                    Make your invoice out for
                                    <strong>{{ $summary->money($summary->payoutAmount) }}</strong>
                                    to Pitch Innovations, and upload it here as a PDF or an image (up to 10 MB).
                                </p>

                                @if ($settlement->hasInvoice())
                                    <p class="small">
                                        Received {{ $settlement->invoice_uploaded_at?->format('j M Y') }} —
                                        <a href="{{ route('sales.invoice.download', $sale) }}">{{ $settlement->invoice_original_name }}</a>.
                                        You can replace it below if you need to.
                                    </p>
                                @endif

                                <form method="POST" action="{{ route('sales.invoice.store', $sale) }}"
                                      enctype="multipart/form-data">
                                    @csrf

                                    <label class="file-drop" for="invoice" data-file-drop>
                                        <span data-file-label>Choose a file…</span>
                                        <input id="invoice" name="invoice" type="file" class="sr-only"
                                               accept=".pdf,.jpg,.jpeg,.png" required>
                                    </label>

                                    <button type="submit" class="btn btn--primary btn--block">
                                        {{ $settlement->hasInvoice() ? 'Replace invoice' : 'Upload invoice' }}
                                    </button>
                                </form>
                            @endif
                        </div>

                        {{-- Payment status --}}
                        <div class="settle__box {{ $settlement?->isPaid() ? '' : 'settle__box--locked' }}">
                            <h3>Payment</h3>
                            @if ($settlement?->isPaid())
                                <p>
                                    We paid
                                    <strong>{{ \App\Support\Money::format($settlement->paid_amount ?? $settlement->payout_amount, $settlement->currency) }}</strong>
                                    on {{ $settlement->paid_on?->format('j F Y') }}.
                                    @if ($settlement->payment_reference)
                                        <br><span class="small">Reference: {{ $settlement->payment_reference }}</span>
                                    @endif
                                </p>
                            @elseif ($settlement?->hasInvoice())
                                <p>
                                    Your invoice is with our team. We will email you as soon as the
                                    payment goes out.
                                </p>
                            @else
                                <p>
                                    Once your invoice is in, our team will process the payment and
                                    email you a confirmation.
                                </p>
                            @endif
                        </div>

                    </div>
                </div>
            </section>
        @endif

        {{-- ---------------------------------------------------------------
             Orders (spec 5.3)
        ---------------------------------------------------------------- --}}
        <section class="card">
            <div class="card__head">
                <div>
                    <h2>Orders</h2>
                    <p class="small muted mb-0">
                        <span data-total-orders>{{ $orders->total() }}</span> orders placed with your
                        {{ auth()->user()->couponCodes->count() === 1 ? 'code' : 'codes' }} during this sale.
                    </p>
                </div>

                @if ($sale->isLive())
                    <div class="livebar" data-livebar>
                        <span class="livebar__dot"></span>
                        <span data-livebar-text>Updating live</span>
                    </div>
                @else
                    <span class="badge badge--ended">Final</span>
                @endif
            </div>

            <div class="card__body card__body--flush">
                @if ($orders->isEmpty())
                    <div class="empty">
                        <h3>Nothing here just yet</h3>
                        <p class="small">
                            Orders placed with your code will appear here the moment they happen.
                        </p>
                    </div>
                @else
                    <div class="table-scroll">
                        <table class="orders">
                            <thead>
                            <tr>
                                @foreach (\App\Services\OrderTable::COLUMNS as $column)
                                    <th @class(['num' => $column === 'Amount'])>{{ $column }}</th>
                                @endforeach
                            </tr>
                            </thead>
                            <tbody data-orders-body>
                            @foreach ($rows as $row)
                                <tr data-order-id="{{ $row['order_id'] }}|{{ $row['placed_at_iso'] }}"
                                    @class(['is-refunded' => $row['is_refunded']])>
                                    <td class="muted">{{ $row['serial'] }}</td>
                                    <td class="tabular">{{ $row['order_id'] }}</td>
                                    <td class="tabular">{{ $row['placed_at'] }}</td>
                                    <td>{{ $row['name'] }}</td>
                                    <td><span class="code">{{ $row['code'] }}</span></td>
                                    <td>{{ $row['country'] }}</td>
                                    <td>{{ $row['state'] }}</td>
                                    <td class="plugin">{{ $row['plugin'] }}</td>
                                    <td>{{ $row['currency'] }}</td>
                                    <td class="num">
                                        {{ $row['amount'] }}
                                        @if ($row['is_refunded'])
                                            <span class="badge badge--refunded">Refunded</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>

                    @include('partials.pager', ['paginator' => $orders])
                @endif
            </div>
        </section>

    </div>

    <p class="small muted" style="margin-top: 18px;">
        Order numbers and customer surnames are partly hidden for privacy.
        Amounts in the table are shown in the currency the customer paid;
        your summary is converted to {{ $summary->currency }} at the rate locked when each order was placed.
    </p>

@endsection

@push('scripts')
    <script src="{{ asset('assets/js/dashboard.js') }}" defer></script>
    <script>
        // Show the chosen filename on the invoice upload control.
        document.addEventListener('DOMContentLoaded', function () {
            var input = document.getElementById('invoice');
            var drop = document.querySelector('[data-file-drop]');
            var label = document.querySelector('[data-file-label]');
            if (!input || !drop || !label) return;

            input.addEventListener('change', function () {
                if (input.files && input.files.length) {
                    label.textContent = input.files[0].name;
                    drop.classList.add('has-file');
                } else {
                    label.textContent = 'Choose a file…';
                    drop.classList.remove('has-file');
                }
            });
        });
    </script>
@endpush
