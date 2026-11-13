@extends('layouts.auth')

@section('title', __('Registration'))
@section('card-class', 'wide')

{{-- Small helper: marks a field invalid and prints its error. --}}
@php
    $invalid = fn (string $field) => $errors->has($field) ? 'is-invalid' : '';
@endphp

@section('content')
    <form method="post" action="{{ route('register.submit') }}" data-wizard data-once novalidate>
        @csrf

        <div class="steps" aria-hidden="true"><i></i><i></i><i></i><i></i></div>

        {{-- Step 1 --}}
        <section class="wizard-step">
            <p class="step-title">{{ __('Step 1 of 4') }}</p>
            <h1>{{ __('Account details') }}</h1>
            <p class="sub">{{ __('Choose how you will sign in.') }}</p>

            <div class="form-grid">
                <div class="full">
                    <label for="username">{{ __('Username') }}</label>
                    <input type="text" id="username" name="username" class="{{ $invalid('username') }}" value="{{ old('username') }}" placeholder="{{ __('Username') }}" required minlength="3" maxlength="60" pattern="[A-Za-z0-9_\-]+" autocomplete="username">
                    <p class="help">{{ __('Letters, numbers, dashes and underscores.') }}</p>
                    @error('username') <p class="error-text">{{ $message }}</p> @enderror
                </div>
                <div class="full">
                    <label for="email">{{ __('Email') }}</label>
                    <input type="email" id="email" name="email" class="{{ $invalid('email') }}" value="{{ old('email') }}" placeholder="name@example.com" required maxlength="191" autocomplete="email">
                    @error('email') <p class="error-text">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="password">{{ __('Password') }}</label>
                    <input type="password" id="password" name="password" class="{{ $invalid('password') }}" placeholder="{{ __('At least 8 characters') }}" required minlength="8" autocomplete="new-password">
                    @error('password') <p class="error-text">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="password_confirmation">{{ __('Confirm password') }}</label>
                    <input type="password" id="password_confirmation" name="password_confirmation" placeholder="{{ __('Repeat the password') }}" required minlength="8" autocomplete="new-password">
                </div>
            </div>

            <div class="wizard-nav">
                <button type="button" class="btn btn-primary" data-next>{{ __('Next →') }}</button>
            </div>
        </section>

        {{-- Step 2 --}}
        <section class="wizard-step" hidden>
            <p class="step-title">{{ __('Step 2 of 4') }}</p>
            <h1>{{ __('Billing information') }}</h1>
            <p class="sub">{{ __('Used on your account and receipts.') }}</p>

            <div class="form-grid">
                <div>
                    <label for="first_name">{{ __('First Name') }}</label>
                    <input type="text" id="first_name" name="first_name" class="{{ $invalid('first_name') }}" value="{{ old('first_name') }}" placeholder="{{ __('First Name') }}" required maxlength="100" autocomplete="given-name">
                    @error('first_name') <p class="error-text">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="last_name">{{ __('Last Name') }}</label>
                    <input type="text" id="last_name" name="last_name" class="{{ $invalid('last_name') }}" value="{{ old('last_name') }}" placeholder="{{ __('Last Name') }}" required maxlength="100" autocomplete="family-name">
                    @error('last_name') <p class="error-text">{{ $message }}</p> @enderror
                </div>
                <div class="full">
                    <label for="address1">{{ __('Address 1') }}</label>
                    <input type="text" id="address1" name="address1" class="{{ $invalid('address1') }}" value="{{ old('address1') }}" placeholder="{{ __('Address 1') }}" required maxlength="255" autocomplete="address-line1">
                    @error('address1') <p class="error-text">{{ $message }}</p> @enderror
                </div>
                <div class="full">
                    <label for="address2">{{ __('Address 2') }}</label>
                    <textarea id="address2" name="address2" class="{{ $invalid('address2') }}" placeholder="{{ __('Address 2') }}" maxlength="255" rows="2" style="min-height: 64px" autocomplete="address-line2">{{ old('address2') }}</textarea>
                    @error('address2') <p class="error-text">{{ $message }}</p> @enderror
                </div>
                <div class="full">
                    <label for="country">{{ __('Country') }}</label>
                    <select id="country" name="country" class="{{ $invalid('country') }}" required autocomplete="country-name">
                        <option value="">{{ __('Select') }}</option>
                        @foreach ($countries as $country)
                            <option value="{{ $country }}" @selected(old('country') === $country)>{{ $country }}</option>
                        @endforeach
                    </select>
                    @error('country') <p class="error-text">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="state">{{ __('State') }}</label>
                    <input type="text" id="state" name="state" class="{{ $invalid('state') }}" value="{{ old('state') }}" placeholder="{{ __('State') }}" required maxlength="100" autocomplete="address-level1">
                    @error('state') <p class="error-text">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="city">{{ __('City') }}</label>
                    <input type="text" id="city" name="city" class="{{ $invalid('city') }}" value="{{ old('city') }}" placeholder="{{ __('City') }}" required maxlength="100" autocomplete="address-level2">
                    @error('city') <p class="error-text">{{ $message }}</p> @enderror
                </div>
                <div class="full">
                    <label for="zip_code">{{ __('Zip Code/Post Code') }}</label>
                    <input type="text" id="zip_code" name="zip_code" class="{{ $invalid('zip_code') }}" value="{{ old('zip_code') }}" placeholder="{{ __('Zip Code/Post Code') }}" maxlength="20" autocomplete="postal-code">
                    @error('zip_code') <p class="error-text">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="wizard-nav">
                <button type="button" class="btn" data-prev>{{ __('← Previous') }}</button>
                <button type="button" class="btn btn-primary" data-next>{{ __('Next →') }}</button>
            </div>
        </section>

        {{-- Step 3 --}}
        <section class="wizard-step" hidden>
            <p class="step-title">{{ __('Step 3 of 4') }}</p>
            <h1>{{ __('Contact info') }}</h1>
            <p class="sub">{{ __('So we can reach you about your requests.') }}</p>

            <div>
                <label for="phone">{{ __('Mobile') }}</label>
                <input type="tel" id="phone" name="phone" class="{{ $invalid('phone') }}" value="{{ old('phone') }}" placeholder="{{ __('Mobile No') }}" required maxlength="30" pattern="\+?[0-9\s\-()]{6,30}" autocomplete="tel">
                <p class="help">{{ __('Include the country code, for example +249 912 345 678.') }}</p>
                @error('phone') <p class="error-text">{{ $message }}</p> @enderror
            </div>

            <div class="wizard-nav">
                <button type="button" class="btn" data-prev>{{ __('← Previous') }}</button>
                <button type="button" class="btn btn-primary" data-next>{{ __('Next →') }}</button>
            </div>
        </section>

        {{-- Step 4 --}}
        <section class="wizard-step" hidden>
            <p class="step-title">{{ __('Step 4 of 4') }}</p>
            <h1>{{ __('Confirm') }}</h1>
            <p class="sub">{{ __('Check your details with Previous, then create the account.') }}</p>

            <label class="check">
                <input type="checkbox" name="terms" value="1" class="{{ $invalid('terms') }}" required @checked(old('terms'))>
                <span>
                    {{ __('I confirm my details are correct and I accept the terms of service. Credits are prepaid and are added to my account by the administrator.') }}
                    <br><a href="{{ route('legal.terms') }}" target="_blank" rel="noopener">{{ __('Terms of Service') }}</a> · <a href="{{ route('legal.privacy') }}" target="_blank" rel="noopener">{{ __('Privacy Policy') }}</a>
                </span>
            </label>
            @error('terms') <p class="error-text">{{ $message }}</p> @enderror

            <div class="wizard-nav">
                <button type="button" class="btn" data-prev>{{ __('← Previous') }}</button>
                <button type="submit" class="btn btn-primary">{{ __('Create account') }}</button>
            </div>
        </section>
    </form>

    <div class="auth-links">
        <span class="muted">{{ __('Already registered?') }}</span>
        <a href="{{ route('login') }}">{{ __('Sign in') }}</a>
    </div>
@endsection
