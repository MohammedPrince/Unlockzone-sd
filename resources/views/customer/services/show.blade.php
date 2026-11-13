@extends('layouts.panel')

@php
    $enough = to_cents($balance) >= to_cents($service->price);
    $after = from_cents(to_cents($balance) - to_cents($service->price));
    $inputTypes = ['email' => 'email', 'number' => 'number'];
    $needTerms = setting('require_terms') === '1';
@endphp

@section('title', $service->name)
@section('eyebrow', $service->category->name)
@section('page-title', $service->name)

@section('page-actions')
    <a class="btn" href="{{ route('customer.services.index') }}">@include('partials.icon', ['name' => 'back']) {{ __('All services') }}</a>
@endsection

@section('content')
    <div class="grid grid-side">
        <div class="stack">
            <div class="card order-card">
                <div class="card-head">
                    <h2>{{ $service->name }} @if ($service->isNew()) <span class="new-tag">{{ __('New') }}</span> @endif</h2>
                </div>
                <div class="card-body">
                    <form method="post" action="{{ route('customer.services.order', $service->id) }}" data-once class="stack"
                          data-confirm="{{ __('Order :service for :price? This amount will be deducted from your balance.', ['service' => $service->name, 'price' => price($service->price)]) }}">
                        @csrf
                        {{-- Sent with the order so pressing the button twice is only charged once. --}}
                        <input type="hidden" name="idempotency_key" value="{{ $idempotencyKey }}">

                        @forelse ($fields as $field)
                            <div class="order-field">
                                <label for="field-{{ $field->id }}">{{ __($field->label) }} @unless ($field->is_required) <span class="muted">({{ __('optional') }})</span> @endunless</label>
                                <input type="{{ $inputTypes[$field->input_type] ?? 'text' }}"
                                       id="field-{{ $field->id }}"
                                       name="fields[{{ $field->id }}]"
                                       value="{{ old('fields.' . $field->id) }}"
                                       class="{{ $errors->has('fields.' . $field->id) ? 'is-invalid' : '' }}"
                                       placeholder="{{ $field->placeholder }}"
                                       dir="ltr"
                                       @if ($field->input_type === 'imei') inputmode="numeric" pattern="[0-9]{15}" maxlength="15" title="{{ __('15 digits') }}" @else maxlength="255" @endif
                                       @if ($field->input_type === 'number') step="any" @endif
                                       @required($field->is_required)
                                       autocomplete="off">
                                <p class="help">{{ __($field->hint()) }}</p>
                                @error('fields.' . $field->id) <p class="error-text">{{ $message }}</p> @enderror
                            </div>
                        @empty
                            <p class="muted">{{ __('This service needs no extra information. Just confirm the order.') }}</p>
                        @endforelse

                        @if ($needTerms)
                            <div>
                                <label class="terms-box">
                                    <input type="checkbox" name="terms" value="1" required @checked(old('terms'))>
                                    <span>{{ __('Agree with terms of services') }}</span>
                                </label>
                                @error('terms') <p class="error-text">{{ $message }}</p> @enderror
                                <p class="terms-text"><a href="{{ route('legal.terms') }}" target="_blank" rel="noopener">{{ __('Read the terms of services') }}</a></p>
                            </div>
                        @endif

                        @if (!$canOrder)
                            <button type="button" class="btn btn-primary btn-order" disabled>{{ __('Not available for your account') }}</button>
                        @elseif ($enough)
                            <button type="submit" class="btn btn-primary btn-order">{{ __('Place an order') }}</button>
                        @else
                            <button type="button" class="btn btn-primary btn-order" disabled>{{ __('Not enough credit') }}</button>
                        @endif
                    </form>

                    <div class="order-price" aria-label="{{ __('Price') }}">
                        <small>$</small><b>{{ amount($service->price) }}</b>
                        @if ($service->is_instant) <span class="tag-instant">{{ __('Instant') }}</span> @endif
                    </div>

                    @if ($service->delivery_time)
                        <div class="order-meta">
                            <p class="label">{{ __('Delivery time') }}</p>
                            <div class="value" dir="auto">{{ $service->delivery_time }}</div>
                            <div class="bar" aria-hidden="true"></div>
                        </div>
                    @endif

                    <form method="post" action="{{ route('customer.services.favorite', $service->id) }}">
                        @csrf
                        <button type="submit" class="btn btn-fav" aria-pressed="{{ $isFavorite ? 'true' : 'false' }}">
                            <span class="box" aria-hidden="true">{{ $isFavorite ? '✓' : '' }}</span>
                            {{ $isFavorite ? __('In your frequently used list') : __('Want to use frequently') }}
                        </button>
                    </form>
                </div>
            </div>

            @if ($service->description)
                <div class="card">
                    <div class="card-head"><h2>{{ __('About this service') }}</h2></div>
                    <div class="card-body"><div class="rich rich-scroll" dir="auto">{{ rich_text($service->description) }}</div></div>
                </div>
            @endif
        </div>

        <div class="card" style="align-self: start">
            <div class="card-head"><h2>{{ __('Payment') }}</h2></div>
            <div class="card-body stack">
                <dl class="kv pay-kv">
                    <dt>{{ __('Price') }}</dt><dd class="num pay-price">{{ price($service->price) }}</dd>
                    <dt>{{ __('Your balance') }}</dt><dd class="num pay-balance">{{ money($balance) }}</dd>
                    @if ($enough)
                        <dt>{{ __('Balance after') }}</dt><dd class="num pay-after">{{ money($after) }}</dd>
                    @endif
                </dl>
                @if (!$canOrder)
                    <div class="alert alert-error" style="margin: 0">{{ __('This service is not available for your account. Please contact support.') }}</div>
                @elseif (!$enough)
                    <div class="alert alert-error" style="margin: 0">
                        {{ __('You need :amount more to order this service.', ['amount' => money(from_cents(to_cents($service->price) - to_cents($balance)))]) }}
                        {{ setting('support_contact') ? __('Contact :contact to recharge.', ['contact' => setting('support_contact')]) : __('Contact the administrator to recharge.') }}
                    </div>
                @else
                    <p class="muted">{{ __('If the request fails or is cancelled, the full amount is refunded to your balance.') }}</p>
                @endif
            </div>
        </div>
    </div>
@endsection
