@extends('layouts.panel')

@php $editing = $customer !== null; @endphp

@section('title', $editing ? __('Edit customer') : __('New customer'))
@section('eyebrow', __('Customer management'))
@section('page-title', $editing ? __('Edit') . ' ' . $customer->name : __('New customer'))
@section('page-description', $editing ? __('Update profile details. Leave the password blank to keep the current one.') : __('Create an account for a customer. An empty wallet is created with it.'))

@section('page-actions')
    <a class="btn" href="{{ $editing ? route('admin.customers.show', $customer->id) : route('admin.customers.index') }}">@include('partials.icon', ['name' => 'back']) {{ __('Back') }}</a>
@endsection

@section('content')
    <form method="post" action="{{ $editing ? route('admin.customers.update', $customer->id) : route('admin.customers.store') }}" class="stack" data-once>
        @csrf
        @if ($editing) @method('put') @endif

        <div class="card">
            <div class="card-head"><h2>{{ __('Account') }}</h2></div>
            <div class="card-body form-grid">
                <div>
                    <label for="username">{{ __('Username') }}</label>
                    <input type="text" id="username" name="username" value="{{ old('username', $customer->username ?? '') }}" required minlength="3" maxlength="60">
                    @error('username') <p class="error-text">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="email">{{ __('Email') }}</label>
                    <input type="email" id="email" name="email" value="{{ old('email', $customer->email ?? '') }}" required maxlength="191">
                    @error('email') <p class="error-text">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="password">{{ $editing ? __('New password') : __('Password') }}</label>
                    <input type="password" id="password" name="password" minlength="8" autocomplete="new-password" @required(!$editing)>
                    <p class="help">{{ $editing ? __('Leave blank to keep the current password.') : __('At least 8 characters. Share it with the customer securely.') }}</p>
                    @error('password') <p class="error-text">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="customer_tag">{{ __('Customer tag') }} <span class="muted">({{ __('optional') }})</span></label>
                    <input type="text" id="customer_tag" name="customer_tag" value="{{ old('customer_tag', $customer->customer_tag ?? '') }}" maxlength="30" list="customer-tags" placeholder="VIP">
                    <datalist id="customer-tags"><option value="VIP"><option value="Gold"><option value="Reseller"><option value="Dealer"></datalist>
                    <p class="help">{{ __('Shown as "(VIP) email" in the customer\'s menu. Leave empty for a normal customer.') }} {{ __('The tag "VIP" also gives VIP prices; removing it takes them away.') }}</p>
                    @error('customer_tag') <p class="error-text">{{ $message }}</p> @enderror
                </div>
                @unless ($editing)
                    <div>
                        <label for="status">{{ __('Status') }}</label>
                        <select id="status" name="status" required>
                            @foreach (\App\Models\User::STATUSES as $status)
                                <option value="{{ $status }}" @selected(old('status', 'active') === $status)>{{ __(ucfirst($status)) }}</option>
                            @endforeach
                        </select>
                    </div>
                @endunless
            </div>
        </div>

        <div class="card">
            <div class="card-head"><h2>{{ __('Personal details') }}</h2></div>
            <div class="card-body form-grid">
                <div>
                    <label for="first_name">{{ __('First name') }}</label>
                    <input type="text" id="first_name" name="first_name" value="{{ old('first_name', $customer->first_name ?? '') }}" required maxlength="100">
                    @error('first_name') <p class="error-text">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="last_name">{{ __('Last name') }}</label>
                    <input type="text" id="last_name" name="last_name" value="{{ old('last_name', $customer->last_name ?? '') }}" maxlength="100">
                </div>
                <div>
                    <label for="phone">{{ __('Mobile') }}</label>
                    <input type="tel" id="phone" name="phone" value="{{ old('phone', $customer->phone ?? '') }}" maxlength="30">
                    @error('phone') <p class="error-text">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>

        <div class="form-actions">
            <a class="btn" href="{{ $editing ? route('admin.customers.show', $customer->id) : route('admin.customers.index') }}">{{ __('Cancel') }}</a>
            <button type="submit" class="btn btn-primary">{{ $editing ? __('Save changes') : __('Create customer') }}</button>
        </div>
    </form>
@endsection
