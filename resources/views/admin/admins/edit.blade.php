@extends('layouts.panel')

@php $self = $admin->id === auth()->id(); @endphp

@section('title', __('Edit administrator'))
@section('eyebrow', __('Administrators'))
@section('page-title', __('Edit') . ' ' . ($admin->name ?: $admin->username))
@section('page-description', __('Update the administrator\'s details. Leave the password blank to keep the current one.'))

@section('page-actions')
    <a class="btn" href="{{ route('admin.admins.index') }}">@include('partials.icon', ['name' => 'back']) {{ __('Back') }}</a>
@endsection

@section('content')
    <form method="post" action="{{ route('admin.admins.update', $admin->id) }}" class="stack" data-once>
        @csrf @method('put')

        <div class="card">
            <div class="card-head"><h2>{{ __('Personal details') }}</h2></div>
            <div class="card-body form-grid">
                <div>
                    <label for="first_name">{{ __('First name') }}</label>
                    <input type="text" id="first_name" name="first_name" value="{{ old('first_name', $admin->first_name) }}" required maxlength="100" placeholder="{{ __('e.g. Sara') }}">
                    @error('first_name') <p class="error-text">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="last_name">{{ __('Last name') }} <span class="muted">({{ __('optional') }})</span></label>
                    <input type="text" id="last_name" name="last_name" value="{{ old('last_name', $admin->last_name) }}" maxlength="100" placeholder="{{ __('e.g. Ahmed') }}">
                </div>
                <div>
                    <label for="username">{{ __('Username') }}</label>
                    <input type="text" id="username" name="username" value="{{ old('username', $admin->username) }}" required minlength="3" maxlength="60" autocomplete="off" placeholder="{{ __('e.g. sara.admin') }}">
                    @error('username') <p class="error-text">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="email">{{ __('Email') }}</label>
                    <input type="email" id="email" name="email" value="{{ old('email', $admin->email) }}" required maxlength="191" autocomplete="off" placeholder="name@example.com">
                    @error('email') <p class="error-text">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="phone">{{ __('Mobile') }} <span class="muted">({{ __('optional') }})</span></label>
                    <input type="tel" id="phone" name="phone" value="{{ old('phone', $admin->phone) }}" maxlength="30" placeholder="+249 9XX XXX XXX">
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-head"><h2>{{ __('Password') }}</h2></div>
            <div class="card-body form-grid">
                <div>
                    <label for="password">{{ __('New password') }}</label>
                    <input type="password" id="password" name="password" minlength="8" autocomplete="new-password" placeholder="{{ __('Leave blank to keep the current one') }}">
                    @error('password') <p class="error-text">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="password_confirmation">{{ __('Confirm password') }}</label>
                    <input type="password" id="password_confirmation" name="password_confirmation" minlength="8" autocomplete="new-password" placeholder="{{ __('Type the password again') }}">
                </div>
            </div>
        </div>

        <div class="form-actions">
            <a class="btn" href="{{ route('admin.admins.index') }}">{{ __('Cancel') }}</a>
            <button type="submit" class="btn btn-primary">{{ __('Save changes') }}</button>
        </div>
    </form>

    @unless ($self)
        <div class="card" style="margin-top:1rem">
            <div class="card-head"><h2>{{ __('Delete administrator') }}</h2></div>
            <div class="card-body">
                <p class="help">{{ __('The account is deleted and can no longer sign in. Their past actions stay in the activity log.') }}</p>
                <form method="post" action="{{ route('admin.admins.destroy', $admin->id) }}" data-confirm="{{ __('Delete administrator :name? This cannot be undone.', ['name' => $admin->username]) }}">
                    @csrf @method('delete')
                    <button class="btn btn-danger" type="submit">{{ __('Delete') }}</button>
                </form>
            </div>
        </div>
    @endunless
@endsection
