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
            <div class="card-head"><h2>{{ __('Connection') }}</h2></div>
            <div class="card-body form-grid">
                @php $type = old('type', $provider->type ?? 'dhru'); @endphp
                <div style="grid-column: 1 / -1">
                    <label for="type">{{ __('API type') }}</label>
                    <select id="type" name="type" required data-provider-type>
                        @foreach (\App\Models\ApiProvider::TYPES as $value => $label)
                            <option value="{{ $value }}" @selected($type === $value)>{{ __($label) }}</option>
                        @endforeach
                    </select>
                    <p class="help" data-type-help="dhru" @if ($type !== 'dhru') hidden @endif>{{ __('Classic DHRU Fusion servers: username + API access key, address like https://provider-site.com ("/api/index.php" is added).') }}</p>
                    <p class="help" data-type-help="dhru_reseller" @if ($type !== 'dhru_reseller') hidden @endif>{{ __('DHRU Fusion Pro workspaces: an API token from the reseller account\'s "API Access" page, address like https://your-workspace.dhru.com ("/api/reseller/v1" is added). The provider may require this server\'s IP address on its allow-list.') }}</p>
                    @error('type') <p class="error-text">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="name">{{ __('Name') }}</label>
                    <input type="text" id="name" name="name" value="{{ old('name', $provider->name ?? '') }}" required maxlength="100" placeholder="{{ __('e.g. GSM Free Service Server') }}">
                    <p class="help">{{ __('Only you see this name.') }}</p>
                    @error('name') <p class="error-text">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="url">{{ __('Server address') }}</label>
                    <input type="text" id="url" name="url" value="{{ old('url', $provider->url ?? '') }}" required maxlength="255" placeholder="https://provider-site.com" dir="ltr" inputmode="url">
                    <p class="help">{{ __('The provider\'s website address.') }}</p>
                    @error('url') <p class="error-text">{{ $message }}</p> @enderror
                </div>
                <div data-type-only="dhru" @if ($type !== 'dhru') hidden @endif>
                    <label for="username">{{ __('Username') }}</label>
                    <input type="text" id="username" name="username" value="{{ old('username', $provider->username ?? '') }}" @required($type === 'dhru') maxlength="150" dir="ltr" autocomplete="off">
                    <p class="help">{{ __('Your username (or email) on the provider\'s website.') }}</p>
                    @error('username') <p class="error-text">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="api_key">{{ __('API key / token') }}</label>
                    <input type="password" id="api_key" name="api_key" value="" @required(!$editing) maxlength="2000" dir="ltr" autocomplete="new-password"
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
