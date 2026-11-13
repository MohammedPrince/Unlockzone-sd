@extends('layouts.panel')

@section('title', __('Service requests'))
@section('eyebrow', __('Customer orders'))
@section('page-title', __('Service requests'))
@section('page-description', __('Every service customers ordered, and how far each one has gone.'))

@section('page-actions')
    <a class="btn" href="{{ route('admin.requests.export', request()->query()) }}">@include('partials.icon', ['name' => 'download']) {{ __('Export CSV') }}</a>
    <a class="btn" href="{{ url()->full() }}">{{ __('Refresh') }}</a>
@endsection

@section('content')
    @include('partials.page-guide', [
        'key' => 'admin-requests',
        'title' => 'How service requests work',
        'intro' => 'Customers pick a service and enter the details it needs. The price is deducted from their credit immediately, then you process the request.',
        'steps' => [
            '<b>Pending</b>: the customer has paid and is waiting. Nobody has started on it.',
            '<b>Processing</b>: you are working on it. The customer can no longer cancel.',
            '<b>Completed</b>: you entered the result (code, confirmation...). The customer sees it on their request page.',
            '<b>Failed / Cancelled</b>: the request is closed and the full amount is refunded to the customer automatically.',
        ],
        'note' => 'Admins can follow every request here. Watch "Pending": those customers have paid and have not heard from you yet.',
    ])

    <div class="stack">
        <div class="tabs">
            <a href="{{ route('admin.requests.index', array_filter(array_merge($filters, ['status' => null]))) }}" @class(['active' => empty($filters['status'])])>{{ __('All') }}<span>{{ number_format($counts['all']) }}</span></a>
            @foreach ($statuses as $status)
                <a href="{{ route('admin.requests.index', array_filter(array_merge($filters, ['status' => $status]))) }}" @class(['active' => ($filters['status'] ?? '') === $status])>{{ __(ucfirst($status)) }}<span>{{ number_format($counts[$status]) }}</span></a>
            @endforeach
        </div>

        <div class="card">
            <form class="filters" method="get" action="{{ route('admin.requests.index') }}">
                @foreach (['status', 'user_id', 'service_id'] as $keep)
                    @if (!empty($filters[$keep])) <input type="hidden" name="{{ $keep }}" value="{{ $filters[$keep] }}"> @endif
                @endforeach
                <div class="field grow">
                    <label for="q">{{ __('Search') }}</label>
                    <input type="search" id="q" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="{{ __('Reference, customer or service') }}">
                </div>
                <div class="field"><label for="from">{{ __('From') }}</label><input type="date" id="from" name="from" value="{{ $filters['from'] ?? '' }}"></div>
                <div class="field"><label for="to">{{ __('To') }}</label><input type="date" id="to" name="to" value="{{ $filters['to'] ?? '' }}"></div>
                <div class="btns">
                    <button class="btn btn-primary" type="submit">{{ __('Filter') }}</button>
                    <a class="btn" href="{{ route('admin.requests.index') }}">{{ __('Reset') }}</a>
                </div>
            </form>

            @if (!empty($filters['user_id']) || !empty($filters['service_id']))
                <div class="card-body" style="padding-bottom: 0"><div class="alert alert-info" style="margin: 0">{{ !empty($filters['user_id']) ? __('Showing requests for one customer only.') : __('Showing requests for one service only.') }} <a href="{{ route('admin.requests.index') }}">{{ __('Show everything') }}</a></div></div>
            @endif

            @if ($requests->isEmpty())
                <div class="empty"><strong>{{ __('No requests here') }}</strong>{{ __('Nothing matches this status and these filters.') }}</div>
            @else
                <div class="table-wrap">
                    <table class="cards">
                        <thead><tr><th>{{ __('Request') }}</th><th>{{ __('Customer') }}</th><th>{{ __('Submitted') }}</th><th class="right">{{ __('Amount') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
                        <tbody>
                            @foreach ($requests as $r)
                                <tr>
                                    <td class="primary">
                                        <a href="{{ route('admin.requests.show', $r->id) }}">{{ $r->service->name ?? __('Service') }}</a>
                                        <span class="sub mono">{{ $r->reference }}</span>
                                    </td>
                                    <td data-label="{{ __('Customer') }}"><a href="{{ route('admin.customers.show', $r->user_id) }}">{{ $r->user->name ?? '—' }}</a><span class="sub">{{ '@' . ($r->user->username ?? '') }}</span></td>
                                    <td data-label="{{ __('Submitted') }}" class="nowrap">{{ $r->created_at->translatedFormat('M j, Y H:i') }}<span class="sub">{{ $r->created_at->diffForHumans() }}</span></td>
                                    <td data-label="{{ __('Amount') }}" class="right num">{{ money($r->amount, false) }}@if ($r->refunded_at)<span class="sub">{{ __('refunded') }}</span>@endif</td>
                                    <td data-label="{{ __('Status') }}"><span class="badge badge-{{ $r->status }}">{{ __(ucfirst($r->status)) }}</span></td>
                                    <td><div class="row-actions"><a class="btn btn-sm {{ $r->isOpen() ? 'btn-primary' : '' }}" href="{{ route('admin.requests.show', $r->id) }}">{{ $r->isOpen() ? __('Process') : __('View') }}</a></div></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                {{ $requests->links() }}
            @endif
        </div>
    </div>
@endsection
