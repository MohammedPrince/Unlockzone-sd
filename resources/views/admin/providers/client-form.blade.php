@extends('layouts.panel')

@php
    $editing = $client !== null;
    $access = old('access', $client->access ?? 'all');
    $ticked = array_map('intval', old('service_ids', $editing ? $client->services->pluck('id')->all() : []));
@endphp

@section('title', $editing ? __('Edit API client') : __('Add API client'))
@section('eyebrow', __('API providers'))
@section('page-title', $editing ? __('API access of :name', ['name' => $client->user->username]) : __('Add API client'))
@section('page-description', __('Another server orders your services through your API, signed in as one of your customers. Orders are paid from that customer\'s credit.'))

@section('page-actions')
    <a class="btn" href="{{ route('admin.providers.index') }}#api-clients">@include('partials.icon', ['name' => 'back']) {{ __('API providers') }}</a>
@endsection

@section('content')
    <form method="post" action="{{ $editing ? route('admin.api-clients.update', $client->id) : route('admin.api-clients.store') }}" class="stack" data-once autocomplete="off">
        @csrf
        @if ($editing) @method('put') @endif

        @error('client') <div class="alert alert-error">{{ $message }}</div> @enderror

        <div class="card">
            <div class="card-head"><h2>{{ __('Client') }}</h2></div>
            <div class="card-body form-grid">
                <div>
                    <label for="user_id">{{ __('Customer account') }}</label>
                    @if ($editing)
                        <input type="text" id="user_id" value="{{ $client->user->name }} ({{ $client->user->username }})" disabled>
                        <p class="help">{{ __('The other server signs in with this username. Its orders and credit are on this account.') }}</p>
                    @else
                        <select id="user_id" name="user_id" required>
                            <option value="">{{ __('Choose a customer') }}</option>
                            @foreach ($customers as $customer)
                                <option value="{{ $customer->id }}" @selected((string) old('user_id') === (string) $customer->id)>{{ $customer->name }} ({{ $customer->username }})@if ($customer->status !== 'active') — {{ __($customer->status) }}@endif</option>
                            @endforeach
                        </select>
                        <p class="help">{{ __('Create a customer account for the other server first (Customers > Add) and give it credit. Its username becomes the API username.') }}</p>
                    @endif
                    @error('user_id') <p class="error-text">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="status">{{ __('Status') }}</label>
                    <select id="status" name="status" required>
                        <option value="active" @selected(old('status', $client->status ?? 'active') === 'active')>{{ __('Active (can use the API)') }}</option>
                        <option value="suspended" @selected(old('status', $client->status ?? '') === 'suspended')>{{ __('Suspended (every API call is refused)') }}</option>
                    </select>
                </div>
                <div>
                    <label for="note">{{ __('Note') }} <span class="muted">{{ __('(optional)') }}</span></label>
                    <input type="text" id="note" name="note" value="{{ old('note', $client->note ?? '') }}" maxlength="255" placeholder="{{ __('e.g. their website address') }}">
                    <p class="help">{{ __('Only you see this note.') }}</p>
                </div>
                <div>
                    <label for="allowed_ips">{{ __('Allowed IP addresses') }} <span class="muted">{{ __('(optional)') }}</span></label>
                    <textarea id="allowed_ips" name="allowed_ips" rows="2" maxlength="2000" dir="ltr" placeholder="203.0.113.10">{{ old('allowed_ips', $client->allowed_ips ?? '') }}</textarea>
                    <p class="help">{{ __('One per line. When filled in, calls from any other address are refused. Leave empty to allow any address.') }}</p>
                    @error('allowed_ips') <p class="error-text">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-head">
                <div>
                    <h2>{{ __('Shared services') }}</h2>
                    <p>{{ __('Only active services are offered. Services this customer is suspended from are never offered.') }}</p>
                </div>
            </div>
            <div class="card-body">
                <div style="display: flex; gap: 24px; flex-wrap: wrap; margin-bottom: 12px">
                    <label style="display: inline-flex; gap: 8px; align-items: center; margin: 0; cursor: pointer">
                        <input type="radio" name="access" value="all" @checked($access === 'all') data-access> <b>{{ __('All services') }}</b>
                        <span class="muted">{{ __('(services you add later are shared too)') }}</span>
                    </label>
                    <label style="display: inline-flex; gap: 8px; align-items: center; margin: 0; cursor: pointer">
                        <input type="radio" name="access" value="selected" @checked($access === 'selected') data-access> <b>{{ __('Only the services I tick') }}</b>
                    </label>
                </div>
                @error('service_ids') <p class="error-text">{{ $message }}</p> @enderror

                <div data-service-picker @if ($access !== 'selected') hidden @endif>
                    <div style="display: flex; gap: 12px; align-items: center; flex-wrap: wrap; margin-bottom: 12px">
                        <input type="search" placeholder="{{ __('Filter services') }}" data-service-filter style="max-width: 320px" autocomplete="off">
                        <span class="muted" data-picked-count data-template="{{ __(':count selected') }}">{{ __(':count selected', ['count' => count($ticked)]) }}</span>
                    </div>

                    @forelse ($categories as $category)
                        <fieldset data-service-group style="border: 1px solid var(--border, rgba(255,255,255,.1)); border-radius: 10px; padding: 10px 14px; margin: 0 0 10px">
                            <legend style="padding: 0 6px">
                                <label style="display: inline-flex; gap: 8px; align-items: center; margin: 0; cursor: pointer; font-weight: 700">
                                    <input type="checkbox" data-group-all> {{ $category->name }}
                                    @if ($category->status !== 'active') <span class="badge badge-inactive">{{ __('Inactive') }}</span> @endif
                                </label>
                            </legend>
                            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 6px 16px">
                                @foreach ($category->services as $service)
                                    <label data-service-row data-name="{{ mb_strtolower($service->name) }}" style="display: flex; gap: 8px; align-items: flex-start; margin: 0; cursor: pointer; font-weight: 400">
                                        <input type="checkbox" name="service_ids[]" value="{{ $service->id }}" data-service-item @checked(in_array($service->id, $ticked, true))>
                                        <span>{{ $service->name }} <span class="muted num">· {{ money($service->price) }}</span>
                                            @if ($service->status !== 'active') <span class="badge badge-inactive">{{ __('Inactive') }}</span> @endif
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                        </fieldset>
                    @empty
                        <div class="empty"><strong>{{ __('No services yet') }}</strong></div>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="form-actions">
            <a class="btn" href="{{ route('admin.providers.index') }}#api-clients">{{ __('Cancel') }}</a>
            <button type="submit" class="btn btn-primary">{{ $editing ? __('Save') : __('Create API access') }}</button>
        </div>
    </form>
@endsection

@push('scripts')
<script>
(function () {
    var picker = document.querySelector('[data-service-picker]');
    if (!picker) { return; }
    var items = function (root) { return Array.prototype.slice.call((root || picker).querySelectorAll('[data-service-item]')); };
    var counter = picker.querySelector('[data-picked-count]');

    var refresh = function () {
        picker.querySelectorAll('[data-service-group]').forEach(function (group) {
            var boxes = items(group);
            var n = boxes.filter(function (b) { return b.checked; }).length;
            var all = group.querySelector('[data-group-all]');
            all.checked = n > 0 && n === boxes.length;
            all.indeterminate = n > 0 && n < boxes.length;
        });
        var total = items().filter(function (b) { return b.checked; }).length;
        counter.textContent = counter.getAttribute('data-template').replace(':count', total);
    };

    document.querySelectorAll('[data-access]').forEach(function (radio) {
        radio.addEventListener('change', function () { picker.hidden = radio.value !== 'selected' || !radio.checked; });
    });

    picker.addEventListener('change', function (e) {
        if (e.target.matches('[data-group-all]')) {
            // Ticks only the services the filter currently shows.
            items(e.target.closest('[data-service-group]')).forEach(function (b) {
                if (!b.closest('[data-service-row]').hidden) { b.checked = e.target.checked; }
            });
        }
        refresh();
    });

    picker.querySelector('[data-service-filter]').addEventListener('input', function () {
        var q = this.value.trim().toLowerCase();
        picker.querySelectorAll('[data-service-group]').forEach(function (group) {
            var shown = 0;
            group.querySelectorAll('[data-service-row]').forEach(function (row) {
                row.hidden = q !== '' && row.getAttribute('data-name').indexOf(q) === -1;
                if (!row.hidden) { shown++; }
            });
            group.hidden = shown === 0;
        });
    });

    refresh();
})();
</script>
@endpush
