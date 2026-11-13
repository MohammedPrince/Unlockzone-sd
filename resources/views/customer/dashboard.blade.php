@extends('layouts.panel')

@php
    $all = max(0, (int) $counts['all']);
    $done = (int) $counts['completed'];
    $open = (int) $counts['pending'] + (int) $counts['processing'];
    $failed = (int) $counts['failed'] + (int) $counts['cancelled'];
    $pct = fn (int $n) => $all > 0 ? round($n / $all * 100, 1) : 0;

    // Signed-in customers are offered WhatsApp only (Telegram is for visitors on the home page).
    $whatsapp = setting('whatsapp_enabled') === '1' ? preg_replace('/[^0-9]+/', '', (string) setting('whatsapp_number')) : '';
    $telegram = '';
    $buyLink = $whatsapp !== '' ? 'https://wa.me/' . $whatsapp : route('customer.credits');
@endphp

@section('title', __('Client Area Dashboard'))
@section('page-title', __('Client Area Dashboard'))

@section('content')
    <div class="dash-grid">
        <div class="dash-card dash-balance">
            <div>
                <p class="dash-label">{{ __('Your Current Balance') }}</p>
                <p class="dash-money"><small>$</small>{{ amount($balance) }}</p>
            </div>
            <a class="btn btn-primary" href="{{ $buyLink }}" @if ($buyLink !== route('customer.credits')) target="_blank" rel="noopener noreferrer" @endif>
                @include('partials.icon', ['name' => 'cart']) {{ __('Buy Credit') }}
            </a>
        </div>

        <div class="dash-card dash-center">
            @include('partials.icon', ['name' => 'receipt'])
            <p class="dash-label">{{ __('Total Receipts') }}</p>
            <p class="dash-money sm"><small>$</small>{{ amount($totals['recharge']) }}</p>
        </div>

        <div class="dash-card dash-center">
            @include('partials.icon', ['name' => 'lock'])
            <p class="dash-label">{{ __('Locked Amount') }}</p>
            <p class="dash-money sm"><small>$</small>{{ amount($locked) }}</p>
            <p class="dash-note">{{ __('Paid for orders that are still open.') }}</p>
        </div>

        <a class="dash-card dash-center dash-link" href="{{ route('customer.requests.index', ['status' => 'completed']) }}">
            <p class="dash-count"><b>{{ number_format($done) }}</b> <span>/ {{ number_format($all) }}</span></p>
            <div class="dash-bar" aria-hidden="true"><i class="ok" style="width: {{ $pct($done) }}%"></i></div>
            <p class="dash-label">{{ __('Orders process successfully') }}</p>
        </a>

        <a class="dash-card dash-center dash-link" href="{{ route('customer.requests.index', ['status' => 'processing']) }}">
            <p class="dash-count"><b>{{ number_format($open) }}</b> <span>/ {{ number_format($all) }}</span></p>
            <div class="dash-bar" aria-hidden="true"><i class="run" style="width: {{ $pct($open) }}%"></i></div>
            <p class="dash-label">{{ __('Orders in process') }}</p>
        </a>

        <a class="dash-card dash-center dash-link" href="{{ route('customer.requests.index', ['status' => 'failed']) }}">
            <p class="dash-count"><b>{{ number_format($failed) }}</b> <span>/ {{ number_format($all) }}</span></p>
            <div class="dash-bar" aria-hidden="true"><i class="bad" style="width: {{ $pct($failed) }}%"></i></div>
            <p class="dash-label">{{ __('Orders process fails') }}</p>
        </a>
    </div>

    @if ($telegram !== '' || $whatsapp !== '')
        <div class="contact-row">
            @if ($telegram !== '')
                <a class="btn btn-contact tg" href="https://t.me/{{ $telegram }}" target="_blank" rel="noopener noreferrer">
                    @include('partials.icon', ['name' => 'telegram']) {{ __('Contact us on Telegram') }}
                </a>
            @endif
            @if ($whatsapp !== '')
                <a class="btn btn-contact wa" href="https://wa.me/{{ $whatsapp }}" target="_blank" rel="noopener noreferrer">
                    @include('partials.icon', ['name' => 'whatsapp']) {{ __('Contact us on WhatsApp') }}
                </a>
            @endif
        </div>
    @endif

    <div class="card" style="margin-top: 18px">
        <div class="card-head">
            <h2>{{ __('Latest orders') }}</h2>
            <a class="btn btn-sm" href="{{ route('customer.requests.index') }}">{{ __('View all') }}</a>
        </div>
        @if ($requests->isEmpty())
            <div class="empty"><strong>{{ __('No orders yet') }}</strong>{{ __('Browse the services and place your first order.') }} <a href="{{ route('customer.services.index') }}">{{ __('See services') }}</a></div>
        @else
            @include('customer.requests.table', ['requests' => $requests])
        @endif
    </div>
@endsection
