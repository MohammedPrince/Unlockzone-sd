@extends('layouts.panel')

@section('title', __('Request') . ' ' . $req->reference)
@section('eyebrow', __('Service request'))
@section('page-title', $req->service->name ?? __('Service request'))
@section('page-description', $req->reference . ' · ' . __('submitted :date', ['date' => $req->created_at->translatedFormat('M j, Y H:i')]))

@section('page-actions')
    <a class="btn" href="{{ route('admin.requests.index') }}">@include('partials.icon', ['name' => 'back']) {{ __('Requests') }}</a>
@endsection

@section('content')
    <div class="grid grid-side">
        <div class="stack">
            <div class="card">
                <div class="card-head">
                    <h2>{{ __('Submitted details') }}</h2>
                    <span class="badge badge-{{ $req->status }}">{{ __(ucfirst($req->status)) }}</span>
                </div>
                <div class="card-body">
                    @if (empty($req->request_data))
                        <p class="muted">{{ __('This service does not ask the customer for any input.') }}</p>
                    @else
                        <dl class="kv">
                            @foreach ($req->request_data as $item)
                                <dt>{{ $item['label'] ?? __('Field') }}</dt>
                                <dd class="mono">{{ ($item['value'] ?? '') !== '' ? $item['value'] : '—' }}</dd>
                            @endforeach
                        </dl>
                    @endif
                </div>
            </div>

            @if ($req->status === 'completed')
                <div class="card">
                    <div class="card-head"><h2>{{ __('Result sent to the customer') }}</h2></div>
                    <div class="card-body"><div class="result-box mono">{{ $req->response_data['result'] ?? '' }}</div></div>
                </div>
            @elseif (in_array($req->status, ['failed', 'cancelled'], true))
                <div class="card">
                    <div class="card-head"><h2>{{ __(ucfirst($req->status)) }}</h2></div>
                    <div class="card-body"><div class="result-box bad">{{ __($req->error_message) }}</div></div>
                </div>
            @endif

            @if ($req->isOpen())
                <form method="post" action="{{ route('admin.requests.update', $req->id) }}" class="card" data-once>
                    @csrf @method('put')
                    <div class="card-head">
                        <div>
                            <h2>{{ __('Process this request') }}</h2>
                            <p>{{ __('Failed and cancelled refund :amount to the customer automatically.', ['amount' => money($req->amount)]) }}</p>
                        </div>
                    </div>
                    <div class="card-body stack">
                        <div>
                            <label for="request-status">{{ __('New status') }}</label>
                            <select id="request-status" name="status" required>
                                @foreach ($req->nextStatuses() as $status)
                                    <option value="{{ $status }}" @selected(old('status', $req->status === 'pending' ? 'processing' : 'completed') === $status)>{{ __(ucfirst($status)) }}</option>
                                @endforeach
                            </select>
                            @error('status') <p class="error-text">{{ $message }}</p> @enderror
                        </div>
                        <div data-for-status="completed" hidden>
                            <label for="result">{{ __('Result for the customer') }}</label>
                            <textarea id="result" name="result" maxlength="5000" placeholder="{{ __('Unlock code, confirmation, instructions...') }}">{{ old('result') }}</textarea>
                            <p class="help">{{ __('Shown to the customer exactly as typed.') }}</p>
                            @error('result') <p class="error-text">{{ $message }}</p> @enderror
                        </div>
                        <div data-for-status="failed cancelled" hidden>
                            <label for="error">{{ __('Reason') }} <span class="muted">{{ __('(shown to the customer)') }}</span></label>
                            <textarea id="error" name="error" maxlength="1000" placeholder="{{ __('Why the request could not be completed') }}">{{ old('error') }}</textarea>
                        </div>
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">{{ __('Update request') }}</button>
                        </div>
                    </div>
                </form>
            @endif

            <div class="card">
                <div class="card-head"><h2>{{ __('Ledger entries for this request') }}</h2></div>
                <div class="table-wrap">
                    <table class="cards">
                        <thead><tr><th>{{ __('Date') }}</th><th>{{ __('Type') }}</th><th class="right">{{ __('Amount') }}</th><th class="right">{{ __('Before') }}</th><th class="right">{{ __('After') }}</th></tr></thead>
                        <tbody>
                            @foreach ($req->transactions as $t)
                                <tr>
                                    <td data-label="{{ __('Date') }}" class="nowrap">{{ $t->created_at->translatedFormat('M j, Y H:i') }}</td>
                                    <td data-label="{{ __('Type') }}"><span class="badge badge-{{ $t->transaction_type }}">{{ __(ucfirst($t->transaction_type)) }}</span></td>
                                    <td data-label="{{ __('Amount') }}" class="right num {{ $t->amount >= 0 ? 'pos' : 'neg' }}">{{ $t->amount >= 0 ? '+' : '' }}{{ money($t->amount, false) }}</td>
                                    <td data-label="{{ __('Before') }}" class="right num">{{ money($t->balance_before, false) }}</td>
                                    <td data-label="{{ __('After') }}" class="right num">{{ money($t->balance_after, false) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="stack">
            @if ($req->service?->usesProvider() || $req->remote_order_id)
                @php $providerName = $req->provider->name ?? $req->service?->provider?->name ?? '—'; @endphp
                <div class="card">
                    <div class="card-head">
                        <h2>{{ __('Provider') }}</h2>
                        <span class="tag api-tag">{{ __('API') }}</span>
                    </div>
                    <div class="card-body stack">
                        <dl class="kv">
                            <dt>{{ __('Provider') }}</dt><dd>{{ $providerName }}</dd>
                            <dt>{{ __('Provider order') }}</dt><dd class="mono">{{ $req->remote_order_id ?: '—' }}</dd>
                            <dt>{{ __('State') }}</dt>
                            <dd>
                                @if ($req->remote_order_id && $req->isOpen()) {{ __('Sent, waiting for the result') }}
                                @elseif ($req->remote_order_id) {{ __('Answered by the provider') }}
                                @elseif ($req->provider_status === 'error') <span class="neg">{{ __('Not accepted by the provider') }}</span>
                                @elseif ($req->provider_status === 'sending') {{ __('Being sent') }}
                                @else {{ __('Not sent') }}
                                @endif
                            </dd>
                            <dt>{{ __('Last check') }}</dt><dd>{{ $req->provider_checked_at?->diffForHumans() ?? '—' }}</dd>
                        </dl>
                        @if ($req->provider_message)
                            <div class="result-box bad">{{ $req->provider_message }}</div>
                        @endif
                        @if ($req->isOpen())
                            <div class="actions">
                                @if ($req->remote_order_id)
                                    <form method="post" action="{{ route('admin.requests.provider.check', $req->id) }}" data-once>
                                        @csrf
                                        <button class="btn" type="submit">{{ __('Check status now') }}</button>
                                    </form>
                                @elseif ($req->service?->usesProvider())
                                    <form method="post" action="{{ route('admin.requests.provider.send', $req->id) }}" data-once
                                          data-confirm="{{ __('Send this order to :provider? Your balance there will be charged.', ['provider' => $providerName]) }}">
                                        @csrf
                                        <button class="btn btn-primary" type="submit">{{ __('Send to provider') }}</button>
                                    </form>
                                @endif
                            </div>
                            <p class="help">{{ __('You can also close the request yourself with the form on this page.') }}</p>
                        @endif
                    </div>
                </div>
            @endif

            <div class="card">
                <div class="card-head"><h2>{{ __('Summary') }}</h2></div>
                <div class="card-body">
                    <dl class="kv">
                        <dt>{{ __('Reference') }}</dt><dd class="mono">{{ $req->reference }}</dd>
                        <dt>{{ __('Service') }}</dt><dd>{{ $req->service->name ?? '—' }}</dd>
                        <dt>{{ __('Category') }}</dt><dd>{{ $req->service->category->name ?? '—' }}</dd>
                        <dt>{{ __('Amount charged') }}</dt><dd class="num">{{ money($req->amount) }}</dd>
                        <dt>{{ __('Refunded') }}</dt><dd>{{ $req->refunded_at ? $req->refunded_at->translatedFormat('M j, Y H:i') : 'No' }}</dd>
                        <dt>{{ __('Submitted') }}</dt><dd>{{ $req->created_at->translatedFormat('M j, Y H:i') }}</dd>
                        <dt>{{ __('Completed') }}</dt><dd>{{ $req->completed_at?->translatedFormat('M j, Y H:i') ?? '—' }}</dd>
                        <dt>{{ __('Handled by') }}</dt><dd>{{ $req->processor->username ?? '—' }}</dd>
                    </dl>
                </div>
            </div>

            <div class="card">
                <div class="card-head">
                    <h2>{{ __('Customer') }}</h2>
                    <a class="btn btn-sm" href="{{ route('admin.customers.show', $req->user_id) }}">{{ __('Open profile') }}</a>
                </div>
                <div class="card-body">
                    <dl class="kv">
                        <dt>{{ __('Name') }}</dt><dd>{{ $req->user->name ?? '—' }}</dd>
                        <dt>{{ __('Username') }}</dt><dd>{{ '@' . ($req->user->username ?? '') }}</dd>
                        <dt>{{ __('Email') }}</dt><dd>{{ $req->user->email ?? '—' }}</dd>
                        <dt>{{ __('Mobile') }}</dt><dd>{{ $req->user->phone ?? '—' }}</dd>
                        <dt>{{ __('Balance now') }}</dt><dd class="num">{{ money($req->user->wallet->balance ?? 0) }}</dd>
                    </dl>
                </div>
            </div>
        </div>
    </div>
@endsection
