@extends('layouts.app')

@section('title', 'Settlements')

@section('content')

    <h1 style="margin-bottom: 6px;">Settlements</h1>
    <p class="muted small" style="margin-bottom: 20px;">
        Internal. Recording a payment emails the creator a confirmation and moves the sale to Paid.
    </p>

    {{-- Sales past their end date that have not been closed out yet. Normally
         the scheduler handles this within the minute; this is the manual
         fallback for when it has not run. --}}
    @if ($salesAwaitingClose->isNotEmpty())
        <div class="alert alert--info">
            <strong>{{ $salesAwaitingClose->count() }}</strong>
            {{ Str::plural('sale', $salesAwaitingClose->count()) }}
            past the end date and not yet closed out:
            <ul>
                @foreach ($salesAwaitingClose as $sale)
                    <li style="margin-bottom: 6px;">
                        {{ $sale->name }} — ended {{ $sale->ends_at->format('j M Y, H:i') }}
                        <form method="POST" action="{{ route('admin.sales.close', $sale) }}" style="display: inline;">
                            @csrf
                            <button type="submit" class="btn btn--outline btn--sm" style="margin-left: 8px;">
                                Close out &amp; email creators
                            </button>
                        </form>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="row" style="margin-bottom: 16px;">
        @foreach (['outstanding' => 'Outstanding', 'invoiced' => 'Invoice in', 'paid' => 'Paid', 'all' => 'All'] as $key => $label)
            <a class="btn btn--sm {{ $status === $key ? 'btn--dark' : 'btn--outline' }}"
               href="{{ route('admin.settlements.index', ['status' => $key]) }}">{{ $label }}</a>
        @endforeach
    </div>

    <section class="card">
        <div class="card__body card__body--flush">
            @if ($settlements->isEmpty())
                <div class="empty">
                    <h3>Nothing to show</h3>
                    <p class="small">No settlements match this filter.</p>
                </div>
            @else
                <div class="table-scroll">
                    <table class="orders">
                        <thead>
                        <tr>
                            <th>Creator</th>
                            <th>Sale</th>
                            <th class="num">Units</th>
                            <th class="num">Payout</th>
                            <th>Rate</th>
                            <th>Invoice</th>
                            <th>Status</th>
                            <th>Record payment</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach ($settlements as $settlement)
                            <tr>
                                <td>
                                    <strong>{{ $settlement->user->name }}</strong><br>
                                    <span class="small muted">{{ $settlement->user->email }}</span>
                                </td>
                                <td>
                                    {{ $settlement->sale->name }}<br>
                                    <span class="small muted">ended {{ $settlement->sale->ends_at->format('j M Y') }}</span>
                                </td>
                                <td class="num">{{ $settlement->units_sold }}</td>
                                <td class="num">
                                    <strong>{{ \App\Support\Money::format($settlement->payout_amount, $settlement->currency) }}</strong>
                                </td>
                                <td>{{ $settlement->user->profile->commissionRatePercent() }}</td>
                                <td>
                                    @if ($settlement->hasInvoice())
                                        <a href="{{ route('admin.settlements.invoice', $settlement) }}">
                                            {{ Str::limit($settlement->invoice_original_name, 20) }}
                                        </a>
                                    @elseif ($settlement->invoice_original_name)
                                        <span class="small muted">{{ Str::limit($settlement->invoice_original_name, 20) }}</span>
                                    @else
                                        <span class="small muted">—</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge badge--{{ $settlement->isPaid() ? 'paid' : ($settlement->hasInvoice() ? 'invoiced' : 'awaiting') }}">
                                        {{ $settlement->stageLabel() }}
                                    </span>
                                </td>
                                <td>
                                    @if ($settlement->isPaid())
                                        <span class="small muted">
                                            {{ \App\Support\Money::format($settlement->paid_amount, $settlement->currency) }}
                                            on {{ $settlement->paid_on?->format('j M Y') }}
                                        </span>
                                    @else
                                        <form method="POST" action="{{ route('admin.settlements.pay', $settlement) }}"
                                              class="row" style="gap: 6px; flex-wrap: nowrap;">
                                            @csrf
                                            <input type="number" step="0.01" min="0" name="paid_amount" class="input"
                                                   style="width: 120px; padding: 6px 9px; font-size: 13px;"
                                                   value="{{ number_format((float) $settlement->payout_amount, 2, '.', '') }}"
                                                   required aria-label="Amount paid">
                                            <input type="date" name="paid_on" class="input"
                                                   style="width: 150px; padding: 6px 9px; font-size: 13px;"
                                                   value="{{ now()->toDateString() }}"
                                                   max="{{ now()->toDateString() }}"
                                                   required aria-label="Date paid">
                                            <input type="text" name="payment_reference" class="input"
                                                   style="width: 130px; padding: 6px 9px; font-size: 13px;"
                                                   placeholder="Reference" aria-label="Payment reference">
                                            <button type="submit" class="btn btn--primary btn--sm">Mark paid</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>

                @include('partials.pager', ['paginator' => $settlements])
            @endif
        </div>
    </section>

@endsection
