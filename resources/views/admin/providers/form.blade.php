@extends('layouts.panel')

@php $editing = $provider !== null; @endphp

@section('title', $editing ? __('Edit provider') : __('Connect a provider'))
@section('eyebrow', __('API providers'))
@section('page-title', $editing ? __('Edit') . ' ' . $provider->name : __('Connect a provider'))
@section('page-description', __('The details come from your account on the provider\'s website, usually under "API settings".'))

@section('page-actions')
    <a class="btn" href="{{ route('admin.providers.index') }}">@include('partials.icon', ['name' => 'back']) {{ __('API providers') }}</a>
@endsection

@section('content')
    <form method="post" action="{{ $editing ? route('admin.providers.update', $provider->id) : route('admin.providers.store') }}" class="stack" data-once autocomplete="off">
        @csrf
        @if ($editing) @method('put') @endif

        <div class="card">
            <div class="card-head"><h2>{{ __('Connection') }}</h2><p>{{ __('DHRU Fusion API') }}</p></div>
            <div class="card-body form-grid">
                <div>
                    <label for="name">{{ __('Name') }}</label>
                    <input type="text" id="name" name="name" value="{{ old('name', $provider->name ?? '') }}" required maxlength="100" placeholder="{{ __('e.g. GSM Free Service Server') }}">
                    <p class="help">{{ __('Only you see this name.') }}</p>
                    @error('name') <p class="error-text">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="url">{{ __('Server address') }}</label>
                    <input type="text" id="url" name="url" value="{{ old('url', $provider->url ?? '') }}" required maxlength="255" placeholder="https://provider-site.com" dir="ltr" inputmode="url">
                    <p class="help">{{ __('The provider\'s website address. "/api/index.php" is added automatically.') }}</p>
                    @error('url') <p class="error-text">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="username">{{ __('Username') }}</label>
                    <input type="text" id="username" name="username" value="{{ old('username', $provider->username ?? '') }}" required maxlength="150" dir="ltr" autocomplete="off">
                    <p class="help">{{ __('Your username (or email) on the provider\'s website.') }}</p>
                    @error('username') <p class="error-text">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="api_key">{{ __('API key') }}</label>
                    <input type="password" id="api_key" name="api_key" value="" @required(!$editing) maxlength="255" dir="ltr" autocomplete="new-password"
                           placeholder="{{ $editing ? __('Leave empty to keep the saved key') : 'XXX-XXX-XXX-XXX-XXX-XXX-XXX-XXX' }}">
                    <p class="help">{{ __('Stored encrypted and never shown again.') }}</p>
                    @error('api_key') <p class="error-text">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="status">{{ __('Status') }}</label>
                    <select id="status" name="status" required>
                        <option value="active" @selected(old('status', $provider->status ?? 'active') === 'active')>{{ __('Active (orders are forwarded)') }}</option>
                        <option value="inactive" @selected(old('status', $provider->status ?? '') === 'inactive')>{{ __('Inactive (orders wait for you)') }}</option>
                    </select>
                </div>
            </div>
        </div>

        <div class="form-actions">
            <a class="btn" href="{{ route('admin.providers.index') }}">{{ __('Cancel') }}</a>
            <button type="submit" class="btn btn-primary">{{ __('Save and test connection') }}</button>
        </div>
    </form>
@endsection
