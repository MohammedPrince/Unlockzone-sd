@extends('layouts.panel')

@section('title', $customer->name)
@section('eyebrow', $customer->customer_tag ? __('Customer profile') . ' · ' . $customer->customer_tag : __('Customer profile'))
@section('page-title', $customer->name)
@section('page-description', '@' . $customer->username . ' · ' . __('registered :date', ['date' => $customer->created_at->translatedFormat('M j, Y')]))

@section('page-actions')
    <a class="btn" href="{{ route('admin.customers.index') }}">@include('partials.icon', ['name' => 'back']) {{ __('Customers') }}</a>
    <a class="btn" href="{{ route('admin.customers.edit', $customer->id) }}">{{ __('Edit profile') }}</a>
    <a class="btn btn-primary" href="{{ route('admin.credits.create', ['customer' => $customer->id]) }}">@include('partials.icon', ['name' => 'wallet']) {{ __('Recharge / adjust') }}</a>
@endsection

@section('content')
    <div class="stack">
        <div class="stats">
            <div class="stat accent">
                <div class="label">@include('partials.icon', ['name' => 'wallet']) {{ __('Credit balance') }}</div>
                <div class="value">{{ money($balance, false) }}</div>
            </div>
            <div class="stat">
                <div class="label">{{ __('Total recharged') }}</div>
                <div class="value">{{ money($totals['recharge'], false) }}</div>
            </div>
            <div class="stat">
                <div class="label">{{ __('Total spent') }}</div>
                <div class="value">{{ money(abs($totals['deduction']) - $totals['refund'], false) }}</div>
                <div class="hint">{{ __(':amount refunded', ['amount' => money($totals['refund'], false)]) }}</div>
            </div>
            <div class="stat">
                <div class="label">{{ __('Requests') }}</div>
                <div class="value">{{ number_format($requestCounts['all']) }}</div>
                <div class="hint">{{ __(':completed completed · :open open', ['completed' => $requestCounts['completed'], 'open' => $requestCounts['pending'] + $requestCounts['processing']]) }}</div>
            </div>
        </div>

        <div class="grid grid-side">
            <div class="stack">
                <div class="card">
                    <div class="card-head">
                        <h2>{{ __('Recent service requests') }}</h2>
                        <a class="btn btn-sm" href="{{ route('admin.requests.index', ['user_id' => $customer->id]) }}">{{ __('View all') }}</a>
                    </div>
                    @if ($requests->isEmpty())
                        <div class="empty"><strong>{{ __('No requests yet') }}</strong>{{ __('This customer has not ordered a service.') }}</div>
                    @else
                        <div class="table-wrap">
                            <table class="cards">
                                <thead><tr><th>{{ __('Request') }}</th><th>{{ __('Date') }}</th><th class="right">{{ __('Amount') }}</th><th>{{ __('Status') }}</th></tr></thead>
                                <tbody>
                                    @foreach ($requests as $r)
                                        <tr>
                                            <td class="primary"><a href="{{ route('admin.requests.show', $r->id) }}">{{ $r->service->name ?? __('Service') }}</a><span class="sub mono">{{ $r->reference }}</span></td>
                                            <td data-label="{{ __('Date') }}" class="nowrap">{{ $r->created_at->translatedFormat('M j, Y H:i') }}</td>
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
                        <h2>{{ __('Credit ledger') }}</h2>
                        <a class="btn btn-sm" href="{{ route('admin.credits.index', ['user_id' => $customer->id]) }}">{{ __('Open in ledger') }}</a>
                    </div>
                    @if ($transactions->isEmpty())
                        <div class="empty"><strong>{{ __('No credit activity') }}</strong>{{ __('Recharge this customer to add their first ledger entry.') }}</div>
                    @else
                        <div class="table-wrap">
                            <table class="cards">
                                <thead><tr><th>{{ __('Entry') }}</th><th>{{ __('Type') }}</th><th class="right">{{ __('Amount') }}</th><th class="right">{{ __('Before') }}</th><th class="right">{{ __('After') }}</th></tr></thead>
                                <tbody>
                                    @foreach ($transactions as $t)
                                        <tr>
                                            <td class="primary">{{ __($t->description ?: ucfirst($t->transaction_type)) }}<span class="sub"><span class="mono">{{ $t->reference }}</span> · {{ $t->created_at->translatedFormat('M j, Y H:i') }} · {{ __('by :name', ['name' => $t->performer->username ?? __('system')]) }}</span></td>
                                            <td data-label="{{ __('Type') }}"><span class="badge badge-{{ $t->transaction_type }}">{{ __(ucfirst($t->transaction_type)) }}</span></td>
                                            <td data-label="{{ __('Amount') }}" class="right num {{ $t->amount >= 0 ? 'pos' : 'neg' }}">{{ $t->amount >= 0 ? '+' : '' }}{{ money($t->amount, false) }}</td>
                                            <td data-label="{{ __('Before') }}" class="right num">{{ money($t->balance_before, false) }}</td>
                                            <td data-label="{{ __('After') }}" class="right num">{{ money($t->balance_after, false) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        {{ $transactions->links() }}
                    @endif
                </div>
            </div>

            <div class="stack">
                <div class="card">
                    <div class="card-head">
                        <h2>{{ __('Account status') }}</h2>
                        <span class="badge badge-{{ $customer->status }}">{{ __(ucfirst($customer->status)) }}</span>
                    </div>
                    <div class="card-body stack">
                        <p class="muted">
                            @if ($customer->status === 'active') {{ __('This customer can sign in and order services.') }}
                            @elseif ($customer->status === 'pending') {{ __('Waiting for your approval before they can sign in.') }}
                            @else {{ __('This customer cannot sign in. Balance and history are kept.') }}
                            @endif
                        </p>
                        <div class="actions">
                            @if ($customer->status !== 'active')
                                <form method="post" action="{{ route('admin.customers.status', $customer->id) }}">
                                    @csrf
                                    <input type="hidden" name="status" value="active">
                                    <button class="btn btn-success" type="submit">{{ $customer->status === 'pending' ? __('Approve account') : __('Reactivate') }}</button>
                                </form>
                            @endif
                            @if ($customer->status !== 'suspended')
                                <form method="post" action="{{ route('admin.customers.status', $customer->id) }}" data-confirm="{{ __('Suspend :name? They will be signed out and unable to sign in.', ['name' => $customer->username]) }}">
                                    @csrf
                                    <input type="hidden" name="status" value="suspended">
                                    <button class="btn btn-danger" type="submit">{{ __('Suspend') }}</button>
                                </form>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="card" id="service-access">
                    <div class="card-head">
                        <h2>{{ __('Service access') }}</h2>
                        <span class="badge badge-{{ $customer->services_suspended ? 'suspended' : 'active' }}">{{ $customer->services_suspended ? __('All suspended') : __('Allowed') }}</span>
                    </div>
                    <div class="card-body stack">
                        <p class="muted">
                            {{ $customer->services_suspended
                                ? __('This customer can sign in and see their history, but cannot order any service.')
                                : __('Stop this customer from ordering without closing the account: every service, or only the ones you choose.') }}
                        </p>
                        <form method="post" action="{{ route('admin.customers.services.suspend', $customer->id) }}"
                              @unless ($customer->services_suspended) data-confirm="{{ __('Suspend :name from all services? They can still sign in.', ['name' => $customer->username]) }}" @endunless>
                            @csrf
                            <input type="hidden" name="suspended" value="{{ $customer->services_suspended ? 0 : 1 }}">
                            <button class="btn {{ $customer->services_suspended ? 'btn-success' : 'btn-danger' }}" type="submit">
                                {{ $customer->services_suspended ? __('Allow services again') : __('Suspend from all services') }}
                            </button>
                        </form>

                        <div>
                            <span class="label">{{ __('Suspended from specific services') }}</span>
                            @if ($blockedServices->isEmpty())
                                <p class="muted">{{ __('None.') }}</p>
                            @else
                                <ul class="list-plain">
                                    @foreach ($blockedServices as $blocked)
                                        <li>
                                            <span>{{ $blocked->name }}</span>
                                            <form method="post" action="{{ route('admin.customers.services.unblock', [$customer->id, $blocked->id]) }}">
                                                @csrf @method('delete')
                                                <button class="btn btn-sm btn-success" type="submit">{{ __('Allow') }}</button>
                                            </form>
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                        </div>

                        @php $blockable = $allServices->whereNotIn('id', $blockedServices->pluck('id')->all()); @endphp
                        @if ($blockable->isNotEmpty())
                            <form method="post" action="{{ route('admin.customers.services.block', $customer->id) }}" class="inline-form">
                                @csrf
                                <div class="field">
                                    <label for="block-service">{{ __('Suspend from one service') }}</label>
                                    <select id="block-service" name="service_id" required>
                                        <option value="">{{ __('Choose a service') }}</option>
                                        @foreach ($blockable as $option)
                                            <option value="{{ $option->id }}">{{ $option->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <button class="btn btn-danger" type="submit">{{ __('Suspend') }}</button>
                            </form>
                        @endif
                    </div>
                </div>

                <div class="card">
                    <div class="card-head"><h2>{{ __('Details') }}</h2></div>
                    <div class="card-body">
                        <dl class="kv">
                            <dt>{{ __('Email') }}</dt><dd>{{ $customer->email }}</dd>
                            <dt>{{ __('Mobile') }}</dt><dd>{{ $customer->phone ?: '—' }}</dd>
                            <dt>{{ __('Address') }}</dt><dd>{{ collect([$customer->address1, $customer->address2])->filter()->implode(', ') ?: '—' }}</dd>
                            <dt>{{ __('City / State') }}</dt><dd>{{ collect([$customer->city, $customer->state])->filter()->implode(', ') ?: '—' }}</dd>
                            <dt>{{ __('Country') }}</dt><dd>{{ $customer->country ?: '—' }}</dd>
                            <dt>{{ __('Zip code') }}</dt><dd>{{ $customer->zip_code ?: '—' }}</dd>
                            <dt>{{ __('Last sign-in') }}</dt><dd>{{ $customer->last_login_at?->translatedFormat('M j, Y H:i') ?? __('Never') }}</dd>
                        </dl>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
