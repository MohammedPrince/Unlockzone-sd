@extends('layouts.auth')

@section('title', __('Forgot password'))

@section('content')
    <h1>{{ __('Forgot your password?') }}</h1>
    <p class="sub">{{ __('Enter your account email and we will send you a link to choose a new password.') }}</p>

    <form method="post" action="{{ route('password.email') }}" class="stack" data-once>
        @csrf
        <div>
            <label for="email">{{ __('Email') }}</label>
            <input type="email" id="email" name="email" value="{{ old('email') }}" required autofocus maxlength="191" autocomplete="email">
        </div>
        <button type="submit" class="btn btn-primary btn-block">{{ __('Send reset link') }}</button>
    </form>

    <div class="auth-links">
        <a href="{{ route('login') }}">{{ __('Back to sign in') }}</a>
    </div>
@endsection
