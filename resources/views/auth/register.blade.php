@extends('layouts.auth')

@section('title', __('Registration'))
@section('card-class', 'wide')

{{-- Small helper: marks a field invalid and prints its error. --}}
@php
    $invalid = fn (string $field) => $errors->has($field) ? 'is-invalid' : '';
@endphp

@section('content')
    {{-- One page: no name or address is asked for (the first name starts as the username, editable in My profile). --}}
    <form method="post" action="{{ route('register.submit') }}" data-once>
        @csrf

        <h1>{{ __('Create your account') }}</h1>
        <p class="sub">{{ __('Choose how you will sign in.') }}</p>

        <div class="form-grid">
            <div class="full">
                <label for="username">{{ __('Username') }}</label>
                <input type="text" id="username" name="username" class="{{ $invalid('username') }}" value="{{ old('username') }}" placeholder="{{ __('Username') }}" required minlength="3" maxlength="60" pattern="[A-Za-z0-9_\-]+" autocomplete="username" autofocus>
                <p class="help">{{ __('Letters, numbers, dashes and underscores.') }}</p>
                @error('username') <p class="error-text">{{ $message }}</p> @enderror
            </div>
            <div class="full">
                <label for="email">{{ __('Email') }}</label>
                <input type="email" id="email" name="email" class="{{ $invalid('email') }}" value="{{ old('email') }}" placeholder="name@example.com" required maxlength="191" autocomplete="email">
                @error('email') <p class="error-text">{{ $message }}</p> @enderror
            </div>
            <div class="full">
                <label for="phone">{{ __('Mobile') }}</label>
                <input type="tel" id="phone" name="phone" class="{{ $invalid('phone') }}" value="{{ old('phone') }}" placeholder="{{ __('Mobile No') }}" required maxlength="30" pattern="\+?[0-9\s\-()]{6,30}" autocomplete="tel">
                <p class="help">{{ __('Include the country code, for example +249 912 345 678.') }}</p>
                @error('phone') <p class="error-text">{{ $message }}</p> @enderror
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
            <div class="full">
                <label class="check">
                    <input type="checkbox" name="terms" value="1" class="{{ $invalid('terms') }}" required @checked(old('terms'))>
                    <span>
                        {{ __('I confirm my details are correct and I accept the terms of service. Credits are prepaid and are added to my account by the administrator.') }}
                        <br><a href="{{ route('legal.terms') }}" target="_blank" rel="noopener">{{ __('Terms of Service') }}</a> · <a href="{{ route('legal.privacy') }}" target="_blank" rel="noopener">{{ __('Privacy Policy') }}</a>
                    </span>
                </label>
                @error('terms') <p class="error-text">{{ $message }}</p> @enderror
            </div>
        </div>

        <div class="wizard-nav">
            <button type="submit" class="btn btn-primary">{{ __('Create account') }}</button>
        </div>
    </form>

    <div class="auth-links">
        <span class="muted">{{ __('Already registered?') }}</span>
        <a href="{{ route('login') }}">{{ __('Sign in') }}</a>
    </div>
@endsection
