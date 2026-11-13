@extends('layouts.auth')

@php $isAdmin = $portal === 'admin'; @endphp

@section('title', $isAdmin ? __('Admin sign in') : __('Login'))
@section('strip-title', $isAdmin ? __('Admin sign in') : __('Login'))
@section('portal', $isAdmin ? __('ADMIN CONTROL PANEL') : __('CUSTOMER PORTAL'))

@section('content')
    <h1 class="auth-title">{{ $isAdmin ? __('Admin sign in') : __('Login to Your Account') }}</h1>

    <form method="post" action="{{ route($isAdmin ? 'admin.login.submit' : 'login.submit') }}" class="stack" data-once>
        @csrf
        <div class="input-icon">
            <label for="login" class="sr-only">{{ __('Username or email') }}</label>
            @include('partials.icon', ['name' => 'user'])
            <input type="text" id="login" name="login" value="{{ old('login') }}" placeholder="{{ __('Username') }}" required autofocus autocomplete="username" maxlength="191">
        </div>
        <div>
            <div class="input-icon">
                <label for="password" class="sr-only">{{ __('Password') }}</label>
                @include('partials.icon', ['name' => 'lock'])
                <input type="password" id="password" name="password" placeholder="{{ __('Password') }}" required autocomplete="current-password">
            </div>
            <p class="forgot"><a href="{{ route('password.request') }}">{{ __('Forgot Password?') }}</a></p>
        </div>
        <label class="check"><input type="checkbox" name="remember" value="1"> {{ __('Trust this device?') }}</label>
        <button type="submit" class="btn btn-primary btn-block btn-lg">{{ __('Login') }}</button>
    </form>

    <div class="auth-links">
        @if (!$isAdmin && setting('allow_registration') === '1')
            <span class="muted">{{ __("Don't have an account?") }}</span>
            <a href="{{ route('register') }}">{{ __('Registration') }}</a>
        @endif
        @if ($isAdmin)
            <a href="{{ route('login') }}">{{ __('Customer sign in') }}</a>
        @endif
    </div>
@endsection
