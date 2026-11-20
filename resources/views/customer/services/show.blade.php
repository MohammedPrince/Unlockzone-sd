@extends('layouts.panel')

@php
    // With a quantity field (e.g. "QTY") the total is price x quantity; app.js keeps these numbers live.
    $qtyField = $service->quantityField($fields);
    $qty = $qtyField ? max(1, min(\App\Models\Service::MAX_QUANTITY, (int) old('fields.' . $qtyField->id, 1))) : 1;
    $customer = auth()->user();
    // A VIP customer sees (and is charged) the VIP price; the site price is shown struck through when it differs.
    $unit = $service->priceFor($customer);
    $vipDeal = $customer?->is_vip && to_cents($unit) !== to_cents($service->price);
    $total = $service->totalFor($qty, $customer);
    $enough = to_cents($balance) >= to_cents($total);
    $after = from_cents(to_cents($balance) - to_cents($total));
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
                          data-confirm="{{ __('Order :service for :price? This amount will be deducted from your balance.', ['service' => $service->name, 'price' => price($total)]) }}"
                          @if ($qtyField)
                              data-qty-form data-unit-price="{{ $unit }}" data-balance="{{ $balance }}"
                              data-confirm-template="{{ __('Order :service for :price? This amount will be deducted from your balance.', ['service' => $service->name, 'price' => '__PRICE__']) }}"
                          @endif>
                        @csrf
                        {{-- Sent with the order so pressing the button twice is only charged once. --}}
                        <input type="hidden" name="idempotency_key" value="{{ $idempotencyKey }}">

                        @forelse ($fields as $field)
                            @if ($field->is($qtyField))
                                <div class="order-field">
                                    <label for="field-{{ $field->id }}">{{ __($field->label) }}</label>
                                    <input type="number" id="field-{{ $field->id }}" name="fields[{{ $field->id }}]" value="{{ $qty }}"
                                           min="1" max="{{ \App\Models\Service::MAX_QUANTITY }}" step="1" inputmode="numeric" dir="ltr" required data-qty
                                           class="{{ $errors->has('fields.' . $field->id) ? 'is-invalid' : '' }}">
                                    <p class="help">{{ __(':price each. The total is the price times the quantity.', ['price' => price($unit)]) }}</p>
                                    @error('fields.' . $field->id) <p class="error-text">{{ $message }}</p> @enderror
                                </div>
                                @continue
                            @endif
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
                        @elseif ($qtyField)
                            {{-- The quantity decides whether the balance is enough, so app.js switches this button. --}}
                            <button type="submit" class="btn btn-primary btn-order" data-qty-submit
                                    data-label-ok="{{ __('Place an order') }}" data-label-short="{{ __('Not enough credit') }}" @disabled(!$enough)>{{ $enough ? __('Place an order') : __('Not enough credit') }}</button>
                        @elseif ($enough)
                            <button type="submit" class="btn btn-primary btn-order">{{ __('Place an order') }}</button>
                        @else
                            <button type="button" class="btn btn-primary btn-order" disabled>{{ __('Not enough credit') }}</button>
                        @endif
                    </form>

                    <div class="order-price" aria-label="{{ __('Price') }}">
                        {{-- With a quantity field the big number is the total; app.js keeps it in step with QTY. --}}
                        <small>$</small><b @if ($qtyField) data-qty-amount @endif>{{ amount($qtyField ? $total : $unit) }}</b>
                        @if ($qtyField) <span class="muted" style="font-size: .85rem" dir="ltr">{{ price($unit) }} × <span data-qty-count>{{ $qty }}</span></span> @endif
                        @if ($service->is_instant) <span class="tag-instant">{{ __('Instant') }}</span> @endif
                        @if ($vipDeal) <span class="tag-vip-price" title="{{ __('Your VIP price') }}">{{ __('VIP') }}</span> <s class="muted vip-was num" title="{{ __('Normal price') }}">{{ price($service->price) }}</s> @endif
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
                    @if ($qtyField)
                        <dt>{{ __('Price') }}</dt><dd class="num">{{ price($unit) }} × <span data-qty-count>{{ $qty }}</span></dd>
                        <dt>{{ __('Total') }}</dt><dd class="num pay-price" data-qty-total>{{ price($total) }}</dd>
                    @else
                        <dt>{{ __('Price') }}</dt><dd class="num pay-price">{{ price($unit) }}</dd>
                    @endif
                    <dt>{{ __('Your balance') }}</dt><dd class="num pay-balance">{{ money($balance) }}</dd>
                    @if ($enough || $qtyField)
                        <dt @if ($qtyField) data-qty-after-row @endif @if (!$enough) hidden @endif>{{ __('Balance after') }}</dt>
                        <dd class="num pay-after" data-qty-after @if (!$enough) hidden @endif>{{ money($after) }}</dd>
                    @endif
                </dl>
                @if (!$canOrder)
                    <div class="alert alert-error" style="margin: 0">{{ __('This service is not available for your account. Please contact support.') }}</div>
                @elseif ($qtyField)
                    <div class="alert alert-error" style="margin: 0" data-qty-short @if ($enough) hidden @endif>
                        {{ __('Not enough credit for this quantity.') }}
                        {{ setting('support_contact') ? __('Contact :contact to recharge.', ['contact' => setting('support_contact')]) : __('Contact the administrator to recharge.') }}
                    </div>
                    <p class="muted" data-qty-ok @unless ($enough) hidden @endunless>{{ __('If the request fails or is cancelled, the full amount is refunded to your balance.') }}</p>
                @elseif (!$enough)
                    <div class="alert alert-error" style="margin: 0">
                        {{ __('You need :amount more to order this service.', ['amount' => money(from_cents(to_cents($unit) - to_cents($balance)))]) }}
                        {{ setting('support_contact') ? __('Contact :contact to recharge.', ['contact' => setting('support_contact')]) : __('Contact the administrator to recharge.') }}
                    </div>
                @else
                    <p class="muted">{{ __('If the request fails or is cancelled, the full amount is refunded to your balance.') }}</p>
                @endif
            </div>
        </div>
    </div>
@endsection
