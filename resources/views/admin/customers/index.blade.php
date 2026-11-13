@extends('layouts.panel')

@section('title', __('Customers'))
@section('eyebrow', __('Customer management'))
@section('page-title', __('Customers'))
@section('page-description', __('Everyone registered on the platform, their status and their credit balance.'))

@section('page-actions')
    <a class="btn" href="{{ route('admin.customers.export', request()->query()) }}">@include('partials.icon', ['name' => 'download']) {{ __('Export CSV') }}</a>
    <a class="btn btn-primary" href="{{ route('admin.customers.create') }}">@include('partials.icon', ['name' => 'plus']) {{ __('New customer') }}</a>
@endsection

@section('content')
    @include('partials.page-guide', [
        'key' => 'admin-customers',
        'title' => 'How customer accounts work',
        'intro' => 'Customers register themselves, or you create the account for them here.',
        'steps' => [
            '<b>Active</b>: can sign in, order services and spend credit.',
            '<b>Pending</b>: registered but waiting for your approval (only when Settings requires approval).',
            '<b>Suspended</b>: cannot sign in. Their balance and history are kept and nothing is deleted.',
        ],
        'note' => 'Open a customer to recharge credit, change status, or see their requests and ledger.',
    ])

    <div class="stack">
        <div class="tabs">
            @foreach (['' => ['All', $counts['all']], 'active' => ['Active', $counts['active']], 'pending' => ['Pending', $counts['pending']], 'suspended' => ['Suspended', $counts['suspended']]] as $value => [$label, $count])
                <a href="{{ route('admin.customers.index', array_filter(['status' => $value, 'q' => $filters['q'] ?? null])) }}" @class(['active' => ($filters['status'] ?? '') === $value])>{{ $label }}<span>{{ number_format($count) }}</span></a>
            @endforeach
        </div>

        <div class="card">
            <form class="filters" method="get" action="{{ route('admin.customers.index') }}">
                @if (!empty($filters['status'])) <input type="hidden" name="status" value="{{ $filters['status'] }}"> @endif
                <div class="field grow">
                    <label for="q">{{ __('Search') }}</label>
                    <input type="search" id="q" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="{{ __('Name, username, email or phone') }}">
                </div>
                <div class="field">
                    <label for="sort">{{ __('Sort by') }}</label>
                    <select id="sort" name="sort">
                        <option value="created_at" @selected(($filters['sort'] ?? '') === 'created_at')>{{ __('Newest first') }}</option>
                        <option value="name" @selected(($filters['sort'] ?? '') === 'name')>{{ __('Name A–Z') }}</option>
                        <option value="last_login_at" @selected(($filters['sort'] ?? '') === 'last_login_at')>{{ __('Last sign-in') }}</option>
                    </select>
                </div>
                <div class="btns">
                    <button class="btn btn-primary" type="submit">{{ __('Search') }}</button>
                    <a class="btn" href="{{ route('admin.customers.index') }}">{{ __('Reset') }}</a>
                </div>
            </form>

            @if ($customers->isEmpty())
                <div class="empty"><strong>{{ __('No customers found') }}</strong>{{ __('Try a different search, or create a customer.') }}</div>
            @else
                <div class="table-wrap">
                    <table class="cards">
                        <thead>
                            <tr><th>{{ __('Customer') }}</th><th>{{ __('Contact') }}</th><th>{{ __('Status') }}</th><th class="right">{{ __('Balance') }}</th><th class="right">{{ __('Requests') }}</th><th>{{ __('Registered') }}</th><th></th></tr>
                        </thead>
                        <tbody>
                            @foreach ($customers as $c)
                                <tr>
                                    <td class="primary">
                                        <a href="{{ route('admin.customers.show', $c->id) }}">{{ $c->name }}</a>
                                        @if ($c->customer_tag) <span class="tag-vip">{{ $c->customer_tag }}</span> @endif
                                        <span class="sub">{{ '@' . $c->username }}</span>
                                    </td>
                                    <td data-label="{{ __('Contact') }}">{{ $c->email }}<span class="sub">{{ $c->phone ?: '—' }}</span></td>
                                    <td data-label="{{ __('Status') }}"><span class="badge badge-{{ $c->status }}">{{ __(ucfirst($c->status)) }}</span></td>
                                    <td data-label="{{ __('Balance') }}" class="right num">{{ money($c->wallet->balance ?? 0, false) }}</td>
                                    <td data-label="{{ __('Requests') }}" class="right num">{{ number_format($c->service_requests_count) }}</td>
                                    <td data-label="{{ __('Registered') }}" class="nowrap">{{ $c->created_at->translatedFormat('M j, Y') }}</td>
                                    <td>
                                        <div class="row-actions">
                                            <a class="btn btn-sm btn-success" href="{{ route('admin.credits.create', ['customer' => $c->id]) }}">{{ __('Recharge') }}</a>
                                            <a class="btn btn-sm" href="{{ route('admin.customers.show', $c->id) }}">{{ __('Open') }}</a>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                {{ $customers->links() }}
            @endif
        </div>
    </div>
@endsection
