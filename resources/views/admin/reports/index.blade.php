@extends('layouts.panel')

@php
    $query = array_filter(['period' => $period['preset'], 'from' => $period['from']->toDateString(), 'to' => $period['to']->toDateString()]);
    $range = ['from' => $period['from']->toDateString(), 'to' => $period['to']->toDateString()];
    $statusColors = ['completed' => 'var(--green)', 'processing' => 'var(--blue)', 'pending' => 'var(--amber)', 'failed' => 'var(--red)', 'cancelled' => 'var(--muted)'];
    $topRevenue = max(1, (float) $topServices->max('revenue'));
    $topSpent = max(1, (float) $topCustomers->max('spent'));
    $categoryMax = max(1, (float) $categories->max('revenue'));
@endphp

@section('title', __('Reports'))
@section('eyebrow', __('Insights'))
@section('page-title', __('Reports'))
@section('page-description', $period['label'])

@section('page-actions')
    <a class="btn" href="{{ route('admin.reports.export', $query) }}">@include('partials.icon', ['name' => 'download']) {{ __('Daily CSV') }}</a>
    <a class="btn" href="{{ route('admin.credits.export', $range) }}">@include('partials.icon', ['name' => 'download']) {{ __('Ledger CSV') }}</a>
    <a class="btn" href="{{ route('admin.requests.export', $range) }}">@include('partials.icon', ['name' => 'download']) {{ __('Requests CSV') }}</a>
    <button type="button" class="btn" onclick="window.print()">{{ __('Print') }}</button>
@endsection

@section('content')
    @include('partials.page-guide', [
        'key' => 'admin-reports',
        'title' => 'How to read these reports',
        'intro' => 'All figures cover the selected period and come straight from the credit ledger and the request list.',
        'steps' => [
            '<b>Net revenue</b> = credit customers spent on services, minus refunds. This is what the platform actually earned.',
            '<b>Recharges</b> = credit you added to customer wallets. It is money received, not yet earned.',
            '<b>Success rate</b> = completed requests out of all closed requests (completed, failed, cancelled).',
        ],
        'note' => 'Export buttons download the same period as CSV for Excel.',
    ])

    <div class="stack">
        <div class="card no-print">
            <form class="filters" method="get" action="{{ route('admin.reports.index') }}" style="border-bottom: 0">
                <div class="field">
                    <label for="period">{{ __('Period') }}</label>
                    <select id="period" name="period">
                        @foreach ($presets as $value => $label)
                            <option value="{{ $value }}" @selected($period['preset'] === $value)>{{ __($label) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field"><label for="from">{{ __('From') }}</label><input type="date" id="from" name="from" value="{{ $period['from']->toDateString() }}"></div>
                <div class="field"><label for="to">{{ __('To') }}</label><input type="date" id="to" name="to" value="{{ $period['to']->toDateString() }}"></div>
                <div class="btns"><button class="btn btn-primary" type="submit">{{ __('Apply') }}</button></div>
                <p class="help" style="flex-basis: 100%; margin: 0">{{ __('The From / To dates are used when Period is "Custom range".') }}</p>
            </form>
        </div>

        <div class="stats stats-6">
            <div class="stat accent">
                <div class="label">@include('partials.icon', ['name' => 'chart']) {{ __('Net revenue') }}</div>
                <div class="value">{{ money($credit['net_revenue'], false) }}</div>
                <div class="hint">{{ __(':sales sales − :refunds refunds', ['sales' => money($credit['sales'], false), 'refunds' => money($credit['refunds'], false)]) }}</div>
            </div>
            <div class="stat">
                <div class="label">@include('partials.icon', ['name' => 'wallet']) {{ __('Recharges') }}</div>
                <div class="value">{{ money($credit['recharges'], false) }}</div>
                <div class="hint">{{ __('Adjustments') }}: {{ $credit['adjustments'] > 0 ? '+' : '' }}{{ money($credit['adjustments'], false) }}</div>
            </div>
            <div class="stat">
                <div class="label">@include('partials.icon', ['name' => 'list']) {{ __('Requests') }}</div>
                <div class="value">{{ number_format($requests['all']) }}</div>
                <div class="hint">{{ __(':completed completed · :open open', ['completed' => $requests['completed'], 'open' => $requests['pending'] + $requests['processing']]) }}</div>
            </div>
            <div class="stat">
                <div class="label">{{ __('Success rate') }}</div>
                <div class="value">{{ $successRate === null ? '—' : $successRate . '%' }}</div>
                <div class="hint">{{ __(':failed failed · :cancelled cancelled', ['failed' => $requests['failed'], 'cancelled' => $requests['cancelled']]) }}</div>
            </div>
            <div class="stat">
                <div class="label">@include('partials.icon', ['name' => 'users']) {{ __('New customers') }}</div>
                <div class="value">{{ number_format($newCustomers) }}</div>
                <div class="hint">{{ __('Registered in this period') }}</div>
            </div>
            <div class="stat">
                <div class="label">{{ __('Credit held now') }}</div>
                <div class="value">{{ money($walletTotal, false) }}</div>
                <div class="hint">{{ __('All wallets, today') }}</div>
            </div>
        </div>

        <div class="card">
            <div class="card-head">
                <div><h2>{{ __('Daily activity') }}</h2><p>{{ __('Dollars per day. Hover or tap a day for the numbers.') }}</p></div>
            </div>
            <div class="card-body">
                @include('partials.chart', ['daily' => $daily])
            </div>
        </div>

        <div class="grid grid-2">
            <div class="card">
                <div class="card-head"><h2>{{ __('Requests by status') }}</h2></div>
                <div class="card-body stack">
                    @if ($requests['all'] === 0)
                        <p class="muted">{{ __('No requests in this period.') }}</p>
                    @else
                        <div class="split" role="img" aria-label="{{ __('Share of requests by status') }}">
                            @foreach ($statusColors as $status => $color)
                                @if ($requests[$status] > 0)
                                    <i style="width: {{ round($requests[$status] / $requests['all'] * 100, 2) }}%; background: {{ $color }}" title="{{ __(ucfirst($status)) }}: {{ $requests[$status] }}"></i>
                                @endif
                            @endforeach
                        </div>
                        <table>
                            <tbody>
                                @foreach ($statusColors as $status => $color)
                                    <tr>
                                        <td style="padding-inline-start: 0"><i class="dot" style="background: {{ $color }}"></i> {{ __(ucfirst($status)) }}</td>
                                        <td class="right num">{{ number_format($requests[$status]) }}</td>
                                        <td class="right num muted" style="padding-right: 0">{{ round($requests[$status] / $requests['all'] * 100, 1) }}%</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </div>
            </div>

            <div class="card">
                <div class="card-head"><h2>{{ __('Revenue by category') }}</h2></div>
                @if ($categories->isEmpty())
                    <div class="empty">{{ __('No sales in this period.') }}</div>
                @else
                    <div class="table-wrap">
                        <table>
                            <thead><tr><th>{{ __('Category') }}</th><th class="right">{{ __('Orders') }}</th><th class="right">{{ __('Revenue') }}</th><th class="hide-sm" style="width: 30%"></th></tr></thead>
                            <tbody>
                                @foreach ($categories as $row)
                                    <tr>
                                        <td>{{ $row->name }}</td>
                                        <td class="right num">{{ number_format($row->orders) }}</td>
                                        <td class="right num">{{ money($row->revenue, false) }}</td>
                                        <td class="hide-sm"><div class="meter"><i style="width: {{ round($row->revenue / $categoryMax * 100, 1) }}%"></i></div></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>

        <div class="grid grid-2">
            <div class="card">
                <div class="card-head"><div><h2>{{ __('Top services') }}</h2><p>{{ __('By number of orders.') }}</p></div></div>
                @if ($topServices->isEmpty())
                    <div class="empty">{{ __('No orders in this period.') }}</div>
                @else
                    <div class="table-wrap">
                        <table class="cards">
                            <thead><tr><th>{{ __('Service') }}</th><th class="right">{{ __('Orders') }}</th><th class="right">{{ __('Completed') }}</th><th class="right">{{ __('Failed') }}</th><th class="right">{{ __('Revenue') }}</th></tr></thead>
                            <tbody>
                                @foreach ($topServices as $row)
                                    <tr>
                                        <td class="primary"><a href="{{ route('admin.requests.index', ['service_id' => $row->id] + $range) }}">{{ $row->name }}</a><div class="meter" style="margin-top: 6px"><i style="width: {{ round($row->revenue / $topRevenue * 100, 1) }}%"></i></div></td>
                                        <td data-label="{{ __('Orders') }}" class="right num">{{ number_format($row->orders) }}</td>
                                        <td data-label="{{ __('Completed') }}" class="right num">{{ number_format($row->completed) }}</td>
                                        <td data-label="{{ __('Failed') }}" class="right num">{{ number_format($row->failed) }}</td>
                                        <td data-label="{{ __('Revenue') }}" class="right num">{{ money($row->revenue, false) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

            <div class="card">
                <div class="card-head"><div><h2>{{ __('Top customers') }}</h2><p>{{ __('By credit spent (refunds excluded).') }}</p></div></div>
                @if ($topCustomers->isEmpty())
                    <div class="empty">{{ __('No orders in this period.') }}</div>
                @else
                    <div class="table-wrap">
                        <table class="cards">
                            <thead><tr><th>{{ __('Customer') }}</th><th class="right">{{ __('Orders') }}</th><th class="right">{{ __('Spent') }}</th></tr></thead>
                            <tbody>
                                @foreach ($topCustomers as $row)
                                    <tr>
                                        <td class="primary"><a href="{{ route('admin.customers.show', $row->id) }}">{{ $row->name }}</a> <span class="muted">{{ '@' . $row->username }}</span><div class="meter" style="margin-top: 6px"><i style="width: {{ round($row->spent / $topSpent * 100, 1) }}%"></i></div></td>
                                        <td data-label="{{ __('Orders') }}" class="right num">{{ number_format($row->orders) }}</td>
                                        <td data-label="{{ __('Spent') }}" class="right num">{{ money($row->spent, false) }}</td>
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
