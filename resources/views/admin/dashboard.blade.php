@extends('layouts.panel')

@section('title', __('Dashboard'))
@section('page-title', __('Dashboard'))
@section('page-description', __('Customers, credits and service requests at a glance.'))

@section('page-actions')
    <a class="btn" href="{{ route('admin.customers.create') }}">@include('partials.icon', ['name' => 'plus']) {{ __('New customer') }}</a>
    <a class="btn" href="{{ route('admin.services.create') }}">@include('partials.icon', ['name' => 'plus']) {{ __('New service') }}</a>
    <a class="btn btn-primary" href="{{ route('admin.credits.create') }}">@include('partials.icon', ['name' => 'wallet']) {{ __('Recharge credit') }}</a>
@endsection

@section('content')
    @include('partials.page-guide', [
        'key' => 'admin-dashboard',
        'title' => 'How the platform works',
        'intro' => 'Customers prepay credit through you, then spend it on the services you publish.',
        'steps' => [
            '<b>Set up services</b>: create a category, add its input fields (IMEI, SN, Email, Lock Code), then add priced services.',
            '<b>Recharge</b>: when a customer pays you, add credit to their wallet. Every change is written to the ledger.',
            '<b>Customer orders</b>: the price is deducted and a pending request appears under Service requests.',
            '<b>You process it</b>: mark it completed with the result, or failed / cancelled, which refunds the customer automatically.',
        ],
        'note' => 'Watch "Pending requests": those customers have paid and are waiting for you.',
    ])

    <div class="stack">
        <div class="stats stats-6">
            <a class="stat accent" href="{{ route('admin.requests.index', ['status' => 'processing']) }}" style="color: inherit; text-decoration: none">
                <div class="label">@include('partials.icon', ['name' => 'list']) {{ __('Orders to process') }}</div>
                <div class="value">{{ number_format($requests['pending'] + $requests['processing']) }}</div>
                <div class="hint">{{ __('Paid and waiting for a result') }}</div>
            </a>
            <div class="stat">
                <div class="label">@include('partials.icon', ['name' => 'chart']) {{ __('Sales today') }}</div>
                <div class="value">{{ money($today['net_revenue'], false) }}</div>
                <div class="hint">{{ __(':amount this month', ['amount' => money($month['net_revenue'], false)]) }}</div>
            </div>
            <div class="stat">
                <div class="label">@include('partials.icon', ['name' => 'wallet']) {{ __('Recharged this month') }}</div>
                <div class="value">{{ money($month['recharges'], false) }}</div>
                <div class="hint">{{ __(':amount today', ['amount' => money($today['recharges'], false)]) }}</div>
            </div>
            <div class="stat">
                <div class="label">@include('partials.icon', ['name' => 'wallet']) {{ __('Credit held by customers') }}</div>
                <div class="value">{{ money($walletTotal, false) }}</div>
                <div class="hint">{{ __('Total of all wallet balances') }}</div>
            </div>
            <div class="stat">
                <div class="label">@include('partials.icon', ['name' => 'users']) {{ __('Customers') }}</div>
                <div class="value">{{ number_format($customers['all']) }}</div>
                <div class="hint">{{ __(':active active · :pending awaiting approval · :suspended suspended', ['active' => $customers['active'], 'pending' => $customers['pending'], 'suspended' => $customers['suspended']]) }}</div>
            </div>
            <div class="stat">
                <div class="label">@include('partials.icon', ['name' => 'box']) {{ __('Services') }}</div>
                <div class="value">{{ number_format($services['active']) }} <small>{{ __('of :total active', ['total' => $services['all']]) }}</small></div>
                <div class="hint">{{ __(':count requests all time', ['count' => number_format($requests['all'])]) }}</div>
            </div>
        </div>

        <div class="card">
            <div class="card-head">
                <div>
                    <h2>{{ __('Last 14 days') }}</h2>
                <p>{{ __('Daily sales, recharges and refunds in dollars.') }}</p>
                </div>
                <a class="btn btn-sm" href="{{ route('admin.reports.index') }}">{{ __('Full reports') }}</a>
            </div>
            <div class="card-body">
                @include('partials.chart', ['daily' => $daily])
            </div>
        </div>

        <div class="grid grid-2">
            <div class="card">
                <div class="card-head">
                    <h2>{{ __('Latest requests') }}</h2>
                    <a class="btn btn-sm" href="{{ route('admin.requests.index') }}">{{ __('View all') }}</a>
                </div>
                @if ($recentRequests->isEmpty())
                    <div class="empty"><strong>{{ __('No requests yet') }}</strong>{{ __('They appear here as soon as a customer orders a service.') }}</div>
                @else
                    <div class="table-wrap">
                        <table class="cards">
                            <thead><tr><th>{{ __('Request') }}</th><th>{{ __('Customer') }}</th><th class="right">{{ __('Amount') }}</th><th>{{ __('Status') }}</th></tr></thead>
                            <tbody>
                                @foreach ($recentRequests as $r)
                                    <tr>
                                        <td class="primary">
                                            <a href="{{ route('admin.requests.show', $r->id) }}">{{ $r->service->name ?? __('Service') }}</a>
                                            <span class="sub mono">{{ $r->reference }} · {{ $r->created_at->diffForHumans() }}</span>
                                        </td>
                                        <td data-label="{{ __('Customer') }}">{{ $r->user->username ?? '—' }}</td>
                                        <td data-label="{{ __('Amount') }}" class="right num">{{ money($r->amount, false) }}</td>
                                        <td data-label="{{ __('Status') }}"><span class="badge badge-{{ $r->status }}">{{ __(ucfirst($r->status)) }}</span></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

            <div class="card">
                <div class="card-head">
                    <h2>{{ __('Latest credit activity') }}</h2>
                    <a class="btn btn-sm" href="{{ route('admin.credits.index') }}">{{ __('Open ledger') }}</a>
                </div>
                @if ($recentTransactions->isEmpty())
                    <div class="empty"><strong>{{ __('No credit activity yet') }}</strong>{{ __('Recharge a customer to get started.') }}</div>
                @else
                    <div class="table-wrap">
                        <table class="cards">
                            <thead><tr><th>{{ __('Customer') }}</th><th>{{ __('Type') }}</th><th class="right">{{ __('Amount') }}</th><th class="right">{{ __('Balance after') }}</th></tr></thead>
                            <tbody>
                                @foreach ($recentTransactions as $t)
                                    <tr>
                                        <td class="primary">
                                            <a href="{{ route('admin.customers.show', $t->user_id) }}">{{ $t->user->username ?? '—' }}</a>
                                            <span class="sub">{{ $t->created_at->diffForHumans() }}</span>
                                        </td>
                                        <td data-label="{{ __('Type') }}"><span class="badge badge-{{ $t->transaction_type }}">{{ __(ucfirst($t->transaction_type)) }}</span></td>
                                        <td data-label="{{ __('Amount') }}" class="right num {{ $t->amount >= 0 ? 'pos' : 'neg' }}">{{ $t->amount >= 0 ? '+' : '' }}{{ money($t->amount, false) }}</td>
                                        <td data-label="{{ __('Balance after') }}" class="right num">{{ money($t->balance_after, false) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection
