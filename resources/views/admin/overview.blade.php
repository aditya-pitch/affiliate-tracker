@extends('layouts.app')

@section('title', 'Overview · '.$sale->name)

@section('content')

    <div class="page-head">
        <div>
            <h1>{{ $sale->name }}</h1>
            <p>
                {{ $sale->starts_at->format('j M Y') }} – {{ $sale->ends_at->format('j M Y') }}
                @if ($sale->isLive())
                    · <span class="badge badge--live"><span class="pulse"></span> Live</span>
                @elseif ($locked)
                    · <span class="badge badge--ended">Closed out — figures final</span>
                @else
                    · <span class="badge badge--awaiting">Ended — not yet closed out</span>
                @endif
            </p>
        </div>

        <div class="page-head__actions">
            <a class="btn btn--outline btn--sm" href="{{ route('admin.overview.download', $sale) }}">
                Download .xlsx
            </a>
            @if ($sale->hasEnded() && ! $locked)
                <form method="POST" action="{{ route('admin.sales.close', $sale) }}">
                    @csrf
                    <button type="submit" class="btn btn--dark btn--sm">Close out &amp; email creators</button>
                </form>
            @endif
        </div>
    </div>

    {{-- Same sale picker the creators get. --}}
    <nav class="sale-picker" aria-label="Choose a sale">
        @foreach ($sales as $option)
            <a class="sale-tab" href="{{ route('admin.overview.show', $option) }}"
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

    <div @unless ($locked) data-live-url="{{ route('admin.overview.live', $sale) }}"
         data-poll-seconds="{{ $pollSeconds }}" @endunless>

        {{--
            Totals are grouped by payout currency on purpose. Creators are paid
            in INR or USD depending on where they are (spec 5.4), so a single
            combined figure would be adding rupees to dollars.
        --}}
        <div class="totals">
            <div class="totals__cell">
                <div class="totals__label">Creators</div>
                <div class="totals__value" data-total-creators>{{ $creators }}</div>
                <div class="totals__note">with sales on this campaign</div>
            </div>
            <div class="totals__cell">
                <div class="totals__label">Units sold</div>
                <div class="totals__value" data-total-units>{{ $units }}</div>
                <div class="totals__note">{{ $refunded }} refunded</div>
            </div>

            @forelse ($totals as $currency => $total)
                <div class="totals__cell totals__cell--accent">
                    <div class="totals__label">Payable · {{ $currency }}</div>
                    <div class="totals__value">{{ \App\Support\Money::format($total['payout'], $currency) }}</div>
                    <div class="totals__note">
                        across {{ $total['creators'] }} {{ Str::plural('creator', $total['creators']) }}
                        · gross {{ \App\Support\Money::format($total['gross'], $currency) }}
                    </div>
                </div>
            @empty
                <div class="totals__cell">
                    <div class="totals__label">Payable</div>
                    <div class="totals__value">—</div>
                    <div class="totals__note">no sales yet</div>
                </div>
            @endforelse
        </div>

        <section class="card">
            <div class="card__head">
                <div>
                    <h2>Coupon performance</h2>
                    <p class="small muted mb-0">
                        Every creator with sales on this campaign. Commission uses each creator’s own rate.
                    </p>
                </div>

                @if ($sale->isLive() && ! $locked)
                    <div class="livebar" data-livebar>
                        <span class="livebar__dot"></span>
                        <span data-livebar-text>Updating live</span>
                    </div>
                @else
                    <span class="badge badge--ended">Final</span>
                @endif
            </div>

            <div class="card__body card__body--flush">
                @if (empty($rows))
                    <div class="empty">
                        <h3>No sales on this campaign yet</h3>
                        <p class="small">
                            As soon as an order comes in on any creator’s code, it will appear here.
                        </p>
                    </div>
                @else
                    <div class="table-scroll">
                        <table class="data">
                            <thead>
                            <tr>
                                <th>Creator</th>
                                <th>Codes used</th>
                                <th class="num">Units</th>
                                <th class="num">Refunded</th>
                                <th class="num">Rate</th>
                                <th class="num">Gross</th>
                                <th class="num">Payout</th>
                                <th>Status</th>
                                <th class="shrink"></th>
                            </tr>
                            </thead>
                            <tbody data-overview-body>
                            @foreach ($rows as $row)
                                <tr>
                                    <td>
                                        <div class="who__name">{{ $row['name'] }}</div>
                                        <div class="who__meta">{{ $row['email'] }}</div>
                                    </td>
                                    <td>
                                        <div class="codes">
                                            @foreach ($row['codes'] as $code)
                                                <span class="code">{{ $code }}</span>
                                            @endforeach
                                        </div>
                                    </td>
                                    <td class="num">{{ $row['units'] }}</td>
                                    <td class="num">
                                        @if ($row['refunded'] > 0)
                                            <span class="badge badge--refunded">{{ $row['refunded'] }}</span>
                                        @else
                                            <span class="muted">0</span>
                                        @endif
                                    </td>
                                    <td class="num">{{ rtrim(rtrim(number_format($row['rate'] * 100, 2, '.', ''), '0'), '.') }}%</td>
                                    <td class="num">{{ $row['summary']->money($row['gross']) }}</td>
                                    <td class="num"><strong>{{ $row['summary']->money($row['payout']) }}</strong></td>
                                    <td>
                                        @php
                                            $settlement = $row['settlement'];
                                            $variant = match (true) {
                                                $settlement?->isPaid() => 'paid',
                                                (bool) $settlement?->hasInvoice() => 'invoiced',
                                                $sale->isLive() => 'live',
                                                default => 'awaiting',
                                            };
                                        @endphp
                                        <span class="badge badge--{{ $variant }}">{{ $row['status'] }}</span>
                                    </td>
                                    <td class="shrink">
                                        <a class="btn btn--outline btn--sm"
                                           href="{{ route('admin.creators.dashboard', [$row['user_id'], $sale]) }}">
                                            Open
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </section>
    </div>

@endsection

@push('scripts')
    <script src="{{ asset('assets/js/admin-overview.js') }}" defer></script>
@endpush
