@extends('layouts.panel')

@php
    $messages = [
        'pending' => 'We received your order. It will be processed soon.',
        'processing' => 'We received your order and are working on it. You will see the result here when it is ready.',
        'completed' => 'Your request is complete. The result is below.',
        'failed' => 'This request could not be completed. Your credit was refunded.',
        'cancelled' => 'This request was cancelled. Your credit was refunded.',
    ];
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

                    @if ($req->status === 'completed')
                        <div>
                            <span class="label">{{ __('Result') }}</span>
                            <div class="result-box mono">{{ $req->response_data['result'] ?? '' }}</div>
                        </div>
                    @elseif (in_array($req->status, ['failed', 'cancelled'], true) && $req->error_message)
                        <div>
                            <span class="label">{{ __('Reason') }}</span>
                            <div class="result-box bad">{{ __($req->error_message) }}</div>
                        </div>
                    @endif

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
                        <dt>{{ __('Ordered') }}</dt><dd>{{ $req->created_at->translatedFormat('M j, Y H:i') }}</dd>
                        <dt>{{ __('Completed') }}</dt><dd>{{ $req->completed_at?->translatedFormat('M j, Y H:i') ?? '—' }}</dd>
                    </dl>
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
