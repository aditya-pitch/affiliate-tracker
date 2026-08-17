@extends('layouts.app')

@section('title', 'Payments')

@section('content')

    <div class="page-head">
        <div>
            <h1>Payments</h1>
            <p>
                Recording a payment emails the creator a confirmation and moves the sale to Paid.
            </p>
        </div>
    </div>

    {{-- Sales past their end date that the scheduler has not closed out yet. --}}
    @if ($salesAwaitingClose->isNotEmpty())
        <div class="alert alert--info">
            <strong>{{ $salesAwaitingClose->count() }}</strong>
            {{ Str::plural('sale', $salesAwaitingClose->count()) }}
            past the end date and not yet closed out. Closing finalises every creator’s report and
            emails them.
            <ul>
                @foreach ($salesAwaitingClose as $sale)
                    <li style="margin-bottom: 8px;">
                        {{ $sale->name }} — ended {{ $sale->ends_at->format('j M Y, H:i') }}
                        <form method="POST" action="{{ route('admin.sales.close', $sale) }}"
                              style="display: inline-block; margin-left: 8px;">
                            @csrf
                            <button type="submit" class="btn btn--outline btn--sm">
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
                    <table class="data">
                        <thead>
                        <tr>
                            <th>Creator</th>
                            <th>Sale</th>
                            <th class="num">Units</th>
                            <th class="num">Owed</th>
                            <th>Invoice</th>
                            <th>Status</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach ($settlements as $settlement)
                            <tr>
                                <td>
                                    <div class="who__name">{{ $settlement->user->name }}</div>
                                    <div class="who__meta">{{ $settlement->user->email }}</div>
                                </td>
                                <td>
                                    <div>{{ $settlement->sale->name }}</div>
                                    <div class="who__meta">
                                        ended {{ $settlement->sale->ends_at->format('j M Y') }}
                                        · {{ $settlement->user->profile->commissionRatePercent() }} rate
                                    </div>
                                </td>
                                <td class="num">{{ $settlement->units_sold }}</td>
                                <td class="num">
                                    <strong>{{ \App\Support\Money::format($settlement->payout_amount, $settlement->currency) }}</strong>
                                </td>
                                <td>
                                    @if ($settlement->hasInvoice())
                                        <a href="{{ route('admin.settlements.invoice', $settlement) }}">
                                            {{ Str::limit($settlement->invoice_original_name, 24) }}
                                        </a>
                                    @elseif ($settlement->invoice_original_name)
                                        <span class="muted small">{{ Str::limit($settlement->invoice_original_name, 24) }}</span>
                                    @else
                                        <span class="muted small">Not yet sent</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge badge--{{ $settlement->isPaid() ? 'paid' : ($settlement->hasInvoice() ? 'invoiced' : 'awaiting') }}">
                                        {{ $settlement->stageLabel() }}
                                    </span>
                                    @if ($settlement->isPaid())
                                        <div class="who__meta">
                                            {{ \App\Support\Money::format($settlement->paid_amount, $settlement->currency) }}
                                            on {{ $settlement->paid_on?->format('j M Y') }}
                                            @if ($settlement->payment_reference)
                                                <br>{{ $settlement->payment_reference }}
                                            @endif
                                        </div>
                                    @endif
                                </td>
                            </tr>

                            {{--
                                The payment form gets a full-width row of its own rather than being
                                squeezed into a cell. Three inputs and a button will never fit
                                inside a table column alongside seven other columns — that is what
                                made this screen overlap before.
                            --}}
                            @unless ($settlement->isPaid())
                                <tr class="form-row">
                                    <td colspan="6">
                                        <details class="payform">
                                            <summary>Record a payment for {{ $settlement->user->firstName() }}</summary>
                                            <form method="POST" action="{{ route('admin.settlements.pay', $settlement) }}">
                                                @csrf
                                                <div class="payform__body">
                                                    <div class="field">
                                                        <label for="amt-{{ $settlement->id }}">Amount paid ({{ $settlement->currency }})</label>
                                                        <input id="amt-{{ $settlement->id }}" type="number" step="0.01" min="0"
                                                               name="paid_amount" class="input" required
                                                               value="{{ number_format((float) $settlement->payout_amount, 2, '.', '') }}">
                                                    </div>
                                                    <div class="field">
                                                        <label for="on-{{ $settlement->id }}">Date paid</label>
                                                        <input id="on-{{ $settlement->id }}" type="date" name="paid_on" class="input"
                                                               required value="{{ now()->toDateString() }}"
                                                               max="{{ now()->toDateString() }}">
                                                    </div>
                                                    <div class="field">
                                                        <label for="ref-{{ $settlement->id }}">Reference <span class="muted">(optional)</span></label>
                                                        <input id="ref-{{ $settlement->id }}" type="text" name="payment_reference"
                                                               class="input" maxlength="120" placeholder="NEFT / UTR number">
                                                    </div>
                                                    <div class="payform__actions">
                                                        <button type="submit" class="btn btn--primary">Mark as paid</button>
                                                    </div>
                                                </div>
                                            </form>
                                        </details>
                                    </td>
                                </tr>
                            @endunless
                        @endforeach
                        </tbody>
                    </table>
                </div>

                @include('partials.pager', ['paginator' => $settlements])
            @endif
        </div>
    </section>

@endsection
