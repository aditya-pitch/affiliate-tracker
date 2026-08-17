@extends('layouts.app')

@section('title', 'Creators')

@section('content')

    <div class="page-head">
        <div>
            <h1>Creator dashboards</h1>
            <p>Every creator with a dashboard, and the coupon codes that feed it.</p>
        </div>
        <div class="page-head__actions">
            <a class="btn btn--primary" href="{{ route('admin.creators.create') }}">Add a creator</a>
        </div>
    </div>

    <form method="GET" action="{{ route('admin.creators.index') }}" class="searchbar">
        <input type="search" name="q" class="input" value="{{ $search }}"
               placeholder="Search by name, email or coupon code" aria-label="Search creators">
        <button type="submit" class="btn btn--outline">Search</button>
        @if ($search !== '')
            <a class="btn btn--outline" href="{{ route('admin.creators.index') }}">Clear</a>
        @endif
    </form>

    <section class="card">
        <div class="card__body card__body--flush">
            @if ($creators->isEmpty())
                <div class="empty">
                    <h3>{{ $search !== '' ? 'Nobody matches that search' : 'No creators yet' }}</h3>
                    <p class="small">
                        {{ $search !== ''
                            ? 'Try a different name, email or coupon code.'
                            : 'Add your first creator to give them a dashboard.' }}
                    </p>
                </div>
            @else
                <div class="table-scroll">
                    <table class="data">
                        <thead>
                        <tr>
                            <th>Creator</th>
                            <th>Coupon codes</th>
                            <th class="num">Rate</th>
                            <th>Paid in</th>
                            <th class="num">Sales</th>
                            <th>Account</th>
                            <th class="shrink"></th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach ($creators as $creator)
                            <tr>
                                <td>
                                    <div class="who__name">{{ $creator->name }}</div>
                                    <div class="who__meta">{{ $creator->email }}</div>
                                </td>
                                <td>
                                    <div class="codes">
                                        @forelse ($creator->couponCodes as $code)
                                            <span class="code @unless ($code->is_active) is-inactive @endunless">{{ $code->code }}</span>
                                        @empty
                                            <span class="muted small">No code yet</span>
                                        @endforelse
                                    </div>
                                </td>
                                <td class="num">{{ $creator->profile?->commissionRatePercent() ?? '—' }}</td>
                                <td>{{ $creator->profile?->payout_currency ?? '—' }}</td>
                                <td class="num">{{ $creator->orders_count }}</td>
                                <td>
                                    @if (! $creator->is_active)
                                        <span class="badge badge--refunded">Disabled</span>
                                    @elseif ($creator->welcome_sent_at)
                                        <span class="badge badge--paid">Invited</span>
                                    @else
                                        <span class="badge badge--awaiting">Not yet invited</span>
                                    @endif
                                </td>
                                <td class="shrink">
                                    <a class="btn btn--outline btn--sm"
                                       href="{{ route('admin.creators.show', $creator) }}">Manage</a>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>

                @include('partials.pager', ['paginator' => $creators])
            @endif
        </div>
    </section>

@endsection
