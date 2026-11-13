@extends('layouts.auth')

@section('title', __('Choose a new password'))

@section('content')
    <h1>{{ __('Choose a new password') }}</h1>
    <p class="sub">{{ __('The link from your email works once and expires after 60 minutes.') }}</p>

    <form method="post" action="{{ route('password.update') }}" class="stack" data-once>
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <div>
            <label for="email">{{ __('Email') }}</label>
            <input type="email" id="email" name="email" value="{{ old('email', $email) }}" required maxlength="191" autocomplete="email">
        </div>
        <div>
            <label for="password">{{ __('New password') }}</label>
            <input type="password" id="password" name="password" required minlength="8" autocomplete="new-password">
        </div>
        <div>
            <label for="password_confirmation">{{ __('Confirm new password') }}</label>
            <input type="password" id="password_confirmation" name="password_confirmation" required minlength="8" autocomplete="new-password">
        </div>
        <button type="submit" class="btn btn-primary btn-block">{{ __('Change password') }}</button>
    </form>
@endsection
