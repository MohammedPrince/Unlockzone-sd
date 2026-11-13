{{-- "My profile" for both portals. $action is the portal's own update route. --}}
@extends('layouts.panel')

@section('title', __('My profile'))
@section('eyebrow', __('Account'))
@section('page-title', __('My profile'))
@section('page-description', __('Your contact details and password. Your username cannot be changed.'))

@section('content')
    <form method="post" action="{{ $action }}" class="stack" data-once>
        @csrf @method('put')

        <div class="card">
            <div class="card-head"><h2>{{ __('Details') }}</h2><span class="tag">{{ '@' . $user->username }}</span></div>
            <div class="card-body form-grid">
                <div>
                    <label for="first_name">{{ __('First name') }}</label>
                    <input type="text" id="first_name" name="first_name" value="{{ old('first_name', $user->first_name) }}" required maxlength="100">
                </div>
                <div>
                    <label for="last_name">{{ __('Last name') }}</label>
                    <input type="text" id="last_name" name="last_name" value="{{ old('last_name', $user->last_name) }}" maxlength="100">
                </div>
                <div>
                    <label for="email">{{ __('Email') }}</label>
                    <input type="email" id="email" name="email" value="{{ old('email', $user->email) }}" required maxlength="191">
                    @error('email') <p class="error-text">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="phone">{{ __('Mobile') }}</label>
                    <input type="tel" id="phone" name="phone" value="{{ old('phone', $user->phone) }}" maxlength="30">
                    @error('phone') <p class="error-text">{{ $message }}</p> @enderror
                </div>
                <div class="full">
                    <label for="address1">{{ __('Address 1') }}</label>
                    <input type="text" id="address1" name="address1" value="{{ old('address1', $user->address1) }}" maxlength="255">
                </div>
                <div class="full">
                    <label for="address2">{{ __('Address 2') }}</label>
                    <input type="text" id="address2" name="address2" value="{{ old('address2', $user->address2) }}" maxlength="255">
                </div>
                <div>
                    <label for="country">{{ __('Country') }}</label>
                    <select id="country" name="country">
                        <option value="">{{ __('Select') }}</option>
                        @foreach ($countries as $country)
                            <option value="{{ $country }}" @selected(old('country', $user->country) === $country)>{{ $country }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="state">{{ __('State') }}</label>
                    <input type="text" id="state" name="state" value="{{ old('state', $user->state) }}" maxlength="100">
                </div>
                <div>
                    <label for="city">{{ __('City') }}</label>
                    <input type="text" id="city" name="city" value="{{ old('city', $user->city) }}" maxlength="100">
                </div>
                <div>
                    <label for="zip_code">{{ __('Zip code / post code') }}</label>
                    <input type="text" id="zip_code" name="zip_code" value="{{ old('zip_code', $user->zip_code) }}" maxlength="20">
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-head"><div><h2>{{ __('Change password') }}</h2><p>{{ __('Leave all three empty to keep your current password.') }}</p></div></div>
            <div class="card-body form-grid">
                <div class="full">
                    <label for="current_password">{{ __('Current password') }}</label>
                    <input type="password" id="current_password" name="current_password" autocomplete="current-password">
                    @error('current_password') <p class="error-text">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="password">{{ __('New password') }}</label>
                    <input type="password" id="password" name="password" minlength="8" autocomplete="new-password">
                    @error('password') <p class="error-text">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="password_confirmation">{{ __('Confirm new password') }}</label>
                    <input type="password" id="password_confirmation" name="password_confirmation" minlength="8" autocomplete="new-password">
                </div>
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">{{ __('Save profile') }}</button>
        </div>
    </form>
@endsection
