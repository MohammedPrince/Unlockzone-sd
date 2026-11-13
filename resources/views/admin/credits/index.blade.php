@extends('layouts.panel')

@section('title', __('Credits & ledger'))
@section('eyebrow', __('Credit management'))
@section('page-title', __('Credits & ledger'))
@section('page-description', __('Every recharge, deduction, refund and adjustment, with the balance before and after.'))

@section('page-actions')
    <a class="btn" href="{{ route('admin.credits.export', request()->query()) }}">@include('partials.icon', ['name' => 'download']) {{ __('Export CSV') }}</a>
    <a class="btn btn-primary" href="{{ route('admin.credits.create') }}">@include('partials.icon', ['name' => 'plus']) {{ __('Recharge / adjust') }}</a>
@endsection

@section('content')
    @include('partials.page-guide', [
        'key' => 'admin-credits',
        'title' => 'How credits work',
        'intro' => 'Customers hold prepaid credit. A balance never changes without a ledger entry, and can never go below zero.',
        'steps' => [
            '<b>Recharge</b>: you add credit after the customer pays you. Record the payment reference in the note.',
            '<b>Deduction</b>: taken automatically when a customer orders a service.',
            '<b>Refund</b>: returned automatically when a request fails or is cancelled. It is a new entry; the deduction is never edited.',
            '<b>Adjustment</b>: your manual correction, up or down, with a required reason.',
        ],
        'note' => 'Ledger entries cannot be edited or deleted. To fix a mistake, record an adjustment.',
    ])

    <div class="stack">
        <div class="stats">
            <div class="stat"><div class="label"><i class="dot" style="background: var(--green)"></i> {{ __('Recharges') }}</div><div class="value pos">+{{ money($totals['recharge'], false) }}</div></div>
            <div class="stat"><div class="label"><i class="dot" style="background: var(--red)"></i> {{ __('Deductions') }}</div><div class="value neg">{{ money($totals['deduction'], false) }}</div></div>
            <div class="stat"><div class="label"><i class="dot" style="background: var(--blue)"></i> {{ __('Refunds') }}</div><div class="value">+{{ money($totals['refund'], false) }}</div></div>
            <div class="stat"><div class="label"><i class="dot" style="background: var(--amber)"></i> {{ __('Adjustments') }}</div><div class="value">{{ $totals['adjustment'] > 0 ? '+' : '' }}{{ money($totals['adjustment'], false) }}</div></div>
            <div class="stat accent"><div class="label">@include('partials.icon', ['name' => 'wallet']) {{ __('Held by all customers') }}</div><div class="value">{{ money($walletTotal, false) }}</div><div class="hint">{{ __('Not affected by the filters') }}</div></div>
        </div>

        <div class="card">
            <form class="filters" method="get" action="{{ route('admin.credits.index') }}">
                @if (!empty($filters['user_id'])) <input type="hidden" name="user_id" value="{{ $filters['user_id'] }}"> @endif
                <div class="field grow">
                    <label for="q">{{ __('Search') }}</label>
                    <input type="search" id="q" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="{{ __('Reference, note or customer') }}">
                </div>
                <div class="field">
                    <label for="type">{{ __('Type') }}</label>
                    <select id="type" name="type">
                        <option value="">{{ __('All types') }}</option>
                        @foreach ($types as $type)
                            <option value="{{ $type }}" @selected(($filters['type'] ?? '') === $type)>{{ __(ucfirst($type)) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field"><label for="from">{{ __('From') }}</label><input type="date" id="from" name="from" value="{{ $filters['from'] ?? '' }}"></div>
                <div class="field"><label for="to">{{ __('To') }}</label><input type="date" id="to" name="to" value="{{ $filters['to'] ?? '' }}"></div>
                <div class="btns">
                    <button class="btn btn-primary" type="submit">{{ __('Filter') }}</button>
                    <a class="btn" href="{{ route('admin.credits.index') }}">{{ __('Reset') }}</a>
                </div>
            </form>

            @if (!empty($filters['user_id']))
                <div class="card-body" style="padding-bottom: 0"><div class="alert alert-info" style="margin: 0">{{ __('Showing one customer only.') }} <a href="{{ route('admin.credits.index') }}">{{ __('Show everyone') }}</a></div></div>
            @endif

            @if ($transactions->isEmpty())
                <div class="empty"><strong>{{ __('No ledger entries') }}</strong>{{ __('Nothing matches these filters.') }}</div>
            @else
                <div class="table-wrap">
                    <table class="cards">
                        <thead>
                            <tr><th>{{ __('Date') }}</th><th>{{ __('Customer') }}</th><th>{{ __('Type') }}</th><th>{{ __('Reference / note') }}</th><th class="right">{{ __('Amount') }}</th><th class="right">{{ __('Before') }}</th><th class="right">{{ __('After') }}</th><th>{{ __('By') }}</th></tr>
                        </thead>
                        <tbody>
                            @foreach ($transactions as $t)
                                <tr>
                                    <td data-label="{{ __('Date') }}" class="nowrap">{{ $t->created_at->translatedFormat('M j, Y') }}<span class="sub">{{ $t->created_at->format('H:i') }}</span></td>
                                    <td class="primary"><a href="{{ route('admin.customers.show', $t->user_id) }}">{{ $t->user->name ?? '—' }}</a><span class="sub">{{ '@' . ($t->user->username ?? '') }}</span></td>
                                    <td data-label="{{ __('Type') }}"><span class="badge badge-{{ $t->transaction_type }}">{{ __(ucfirst($t->transaction_type)) }}</span></td>
                                    <td data-label="{{ __('Reference') }}">
                                        @if ($t->service_request_id)
                                            <a class="mono" href="{{ route('admin.requests.show', $t->service_request_id) }}">{{ $t->reference }}</a>
                                        @else
                                            <span class="mono">{{ $t->reference }}</span>
                                        @endif
                                        <span class="sub">{{ $t->description }}</span>
                                    </td>
                                    <td data-label="{{ __('Amount') }}" class="right num {{ $t->amount >= 0 ? 'pos' : 'neg' }}">{{ $t->amount >= 0 ? '+' : '' }}{{ money($t->amount, false) }}</td>
                                    <td data-label="{{ __('Before') }}" class="right num">{{ money($t->balance_before, false) }}</td>
                                    <td data-label="{{ __('After') }}" class="right num">{{ money($t->balance_after, false) }}</td>
                                    <td data-label="{{ __('By') }}">{{ $t->performer->username ?? 'system' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                {{ $transactions->links() }}
            @endif
        </div>
    </div>
@endsection
