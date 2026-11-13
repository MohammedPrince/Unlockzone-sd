@extends('layouts.panel')

@php
    // How each ledger row reads on the statement, Dhru style: what happened, and CR (money in) or DR (money out).
    $describe = function ($t) {
        return match ($t->transaction_type) {
            'deduction' => __('New Order Placed'),
            'recharge' => __('+ Add Funds'),
            'refund' => __('Order Refunded'),
            default => $t->description ? __($t->description) : __('Balance Adjustment'),
        };
    };
@endphp

@section('title', __('Statement'))
@section('page-title', __('Statement'))

@section('content')
    <div class="card orders-card">
        <form class="statement-tools" method="get" action="{{ route('customer.credits') }}">
            <label for="type" class="sr-only">{{ __('Type') }}</label>
            <select id="type" name="type">
                <option value="">{{ __('Select') }}</option>
                @foreach ($types as $type)
                    <option value="{{ $type }}" @selected(($filters['type'] ?? '') === $type)>{{ __(ucfirst($type)) }}</option>
                @endforeach
            </select>
            <button class="btn btn-primary" type="submit">@include('partials.icon', ['name' => 'search']) {{ __('Search') }}</button>
        </form>
        <div class="orders-tools" style="padding-top: 0">
            <a class="btn btn-primary" href="{{ route('customer.credits.export', array_filter(['type' => $filters['type'] ?? null])) }}">
                @include('partials.icon', ['name' => 'download']) {{ __('Export to Excel') }}
            </a>
            <span class="statement-balance">{{ __('Current balance') }}: <b class="num">{{ money($balance) }}</b></span>
        </div>

        @if ($transactions->isEmpty())
            <div class="empty"><strong>{{ __('No credit activity') }}</strong>{{ __('Contact the administrator to recharge your account.') }}</div>
        @else
            <div class="table-wrap orders-wrap">
                <table class="orders-table statement-table">
                    <thead>
                        <tr>
                            <th>{{ __('Date') }}</th>
                            <th>{{ __('Description') }}</th>
                            <th>{{ __('Type') }}</th>
                            <th class="right">{{ __('Amount') }}</th>
                            <th class="right">{{ __('Balance') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($transactions as $t)
                            @php $credit = $t->amount >= 0; @endphp
                            <tr>
                                <td class="nowrap">{{ $t->created_at->format('m/d/Y') }}<span class="sub">{{ $t->created_at->format('H:i') }}</span></td>
                                <td class="o-service">
                                    {{ $describe($t) }}
                                    @if ($t->service_request_id)
                                        <a class="sub mono" href="{{ route('customer.requests.show', $t->service_request_id) }}">(#{{ $t->reference }})</a>
                                    @else
                                        <span class="sub mono">(#{{ $t->reference }})</span>
                                    @endif
                                </td>
                                <td><span class="dc-tag {{ $credit ? 'cr' : 'dr' }}" title="{{ $credit ? __('Credit') : __('Debit') }}">{{ $credit ? 'CR' : 'DR' }}</span></td>
                                <td class="right num {{ $credit ? 'pos' : 'neg' }}">{{ $credit ? '+' : '' }}{{ money($t->amount) }}</td>
                                <td class="right num">{{ money($t->balance_after) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            {{ $transactions->links() }}
        @endif
    </div>
@endsection
