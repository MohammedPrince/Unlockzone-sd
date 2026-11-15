@extends('layouts.panel')

@php
    $messages = [
        'pending' => 'We received your order. It will be processed soon.',
        'processing' => 'We received your order and are working on it. You will see the result here when it is ready.',
        'completed' => 'Your request is complete. The result is below.',
        'failed' => 'This request could not be completed. Your credit was refunded.',
        'cancelled' => 'This request was cancelled. Your credit was refunded.',
    ];

    // The first thing the customer entered (usually the IMEI or serial number), shown large like the code.
    $mainField = collect($req->request_data ?? [])->first(fn ($i) => ($i['value'] ?? '') !== '');

    // When the order got its answer: completed, or failed / cancelled (refunded). Open orders have none yet.
    $repliedAt = $req->status === 'completed' ? ($req->completed_at ?? $req->updated_at)
        : (in_array($req->status, ['failed', 'cancelled'], true) ? $req->updated_at : null);
@endphp

@section('title', __('Request') . ' ' . $req->reference)
@section('eyebrow', __('My request'))
@section('page-title', $req->service->name ?? __('Service request'))
@section('page-description', $req->reference . ' · ' . __('ordered :date', ['date' => $req->created_at->translatedFormat('M j, Y H:i')]))

@section('page-actions')
    <a class="btn" href="{{ route('customer.requests.index') }}">@include('partials.icon', ['name' => 'back']) {{ __('My requests') }}</a>
    @if ($req->isOpen()) <a class="btn" href="{{ url()->current() }}">{{ __('Refresh status') }}</a> @endif
@endsection

@section('content')
    <div class="grid grid-side">
        <div class="stack">
            <div class="card">
                <div class="card-head">
                    <h2>{{ __('Status') }}</h2>
                    <span class="badge badge-{{ $req->status }}">{{ __(ucfirst($req->status)) }}</span>
                </div>
                <div class="card-body stack">
                    <p>{{ __($messages[$req->status] ?? '') }}</p>

                    @if ($mainField)
                        <div class="req-main-field">
                            <span class="label">{{ $mainField['label'] ?? __('Field') }}</span>
                            <div class="req-main-value">
                                <b class="mono" dir="ltr">{{ $mainField['value'] }}</b>
                                <button type="button" class="btn btn-sm btn-copy" data-copy="{{ $mainField['value'] }}" data-copied="{{ __('Copied') }}" title="{{ __('Copy') }}">@include('partials.icon', ['name' => 'copy'])<span data-copy-label>{{ __('Copy') }}</span></button>
                            </div>
                        </div>
                    @endif

                    @if ($req->status === 'completed')
                        @php $code = (string) ($req->response_data['result'] ?? ''); @endphp
                        <div>
                            <div class="code-head">
                                <span class="label">{{ __('Code') }}</span>
                                @if ($code !== '')
                                    <button type="button" class="btn btn-sm btn-copy" data-copy="{{ $code }}" data-copied="{{ __('Copied') }}">@include('partials.icon', ['name' => 'copy'])<span data-copy-label>{{ __('Copy') }}</span></button>
                                @endif
                            </div>
                            <div class="result-box mono" dir="auto">{{ $code }}</div>
                        </div>
                    @elseif (in_array($req->status, ['failed', 'cancelled'], true) && $req->error_message)
                        <div>
                            <span class="label">{{ __('Reason') }}</span>
                            <div class="result-box bad">{{ __($req->error_message) }}</div>
                        </div>
                    @endif

                    {{-- When it was ordered, when it was answered and how long that took. --}}
                    <dl class="req-times">
                        <div>
                            <dt>{{ __('Submitted on') }}</dt>
                            <dd class="num" dir="ltr">{{ $req->created_at->format('Y/m/d H:i:s') }}</dd>
                        </div>
                        <div>
                            <dt>{{ __('Replied on') }}</dt>
                            <dd class="num" dir="ltr">{{ $repliedAt?->format('Y/m/d H:i:s') ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt>{{ $repliedAt ? __('Replied in') : __('Waiting for') }}</dt>
                            <dd>{{ $req->created_at->diffForHumans($repliedAt ?? now(), ['syntax' => \Carbon\CarbonInterface::DIFF_ABSOLUTE, 'parts' => 2]) }}</dd>
                        </div>
                    </dl>

                </div>
            </div>

            <div class="card">
                <div class="card-head"><h2>{{ __('What you submitted') }}</h2></div>
                <div class="card-body">
                    @if (empty($req->request_data))
                        <p class="muted">{{ __('This service did not need any extra information.') }}</p>
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
        </div>

        <div class="stack">
            <div class="card">
                <div class="card-head"><h2>{{ __('Summary') }}</h2></div>
                <div class="card-body">
                    <dl class="kv">
                        <dt>{{ __('Reference') }}</dt><dd class="mono">{{ $req->reference }}</dd>
                        <dt>{{ __('Category') }}</dt><dd>{{ $req->service->category->name ?? '—' }}</dd>
                        <dt>{{ __('Amount') }}</dt><dd class="num">{{ money($req->amount) }}</dd>
                        <dt>{{ __('Ordered') }}</dt><dd>{{ $req->created_at->translatedFormat('M j, Y H:i') }}</dd>                    </dl>
                </div>
            </div>

            <div class="card">
                <div class="card-head"><h2>{{ __('Credit movements') }}</h2></div>
                <div class="card-body stack">
                    @foreach ($req->transactions as $t)
                        <div style="display: flex; justify-content: space-between; gap: 12px; align-items: center">
                            <span><span class="badge badge-{{ $t->transaction_type }}">{{ __(ucfirst($t->transaction_type)) }}</span><span class="sub muted" style="display: block; font-size: 12px; margin-top: 4px">{{ $t->created_at->translatedFormat('M j, Y H:i') }}</span></span>
                            <b class="num {{ $t->amount >= 0 ? 'pos' : 'neg' }}">{{ $t->amount >= 0 ? '+' : '' }}{{ money($t->amount, false) }}</b>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
@endsection
