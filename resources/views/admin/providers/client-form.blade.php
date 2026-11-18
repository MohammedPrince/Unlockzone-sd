@extends('layouts.panel')

@php
    $editing = $client !== null;
    $access = old('access', $client->access ?? 'all');
    $ticked = array_map('intval', old('service_ids', $editing ? $client->services->pluck('id')->all() : []));
    $adjust = old('price_adjust', $editing ? rtrim(rtrim(number_format((float) $client->price_adjust, 2, '.', ''), '0'), '.') : '0');
    // [service id => fixed price] from the form after an error, or from the database.
    $fixed = old('prices', $editing ? $client->prices->mapWithKeys(fn ($p) => [$p->service_id => rtrim(rtrim($p->price, '0'), '.')])->all() : []);
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
                    <h2>{{ __('Shared services and prices') }}</h2>
                    <p>{{ __('Only active services are offered. Services this customer is suspended from are never offered.') }}</p>
                </div>
            </div>
            <div class="card-body">
                <div class="form-grid" style="margin-bottom: 16px">
                    <div>
                        <label for="price_adjust">{{ __('Price for this client') }} (%)</label>
                        <input type="number" id="price_adjust" name="price_adjust" value="{{ $adjust }}" min="-90" max="1000" step="0.01" dir="ltr" data-price-adjust>
                        <p class="help">{{ __('Applied to your site price for every service. 0 = same as your site, 10 = 10% more, -5 = 5% discount. A price typed next to a service below replaces it for that service.') }}</p>
                        @error('price_adjust') <p class="error-text">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label>{{ __('Which services') }}</label>
                        <label class="access-choice">
                            <input type="radio" name="access" value="all" @checked($access === 'all') data-access> <b>{{ __('All services') }}</b>
                            <span class="muted">{{ __('(services you add later are shared too)') }}</span>
                        </label>
                        <label class="access-choice">
                            <input type="radio" name="access" value="selected" @checked($access === 'selected') data-access> <b>{{ __('Only the services I tick') }}</b>
                        </label>
                        @error('service_ids') <p class="error-text">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div data-service-picker data-mode="{{ $access }}">
                    <div style="display: flex; gap: 12px; align-items: center; flex-wrap: wrap; margin-bottom: 12px">
                        <input type="search" placeholder="{{ __('Filter services') }}" data-service-filter style="max-width: 320px; flex: 1 1 200px; min-width: 0" autocomplete="off">
                        <span class="muted" data-picked-count data-template="{{ __(':count selected') }}" data-only-selected>{{ __(':count selected', ['count' => count($ticked)]) }}</span>
                        <span class="muted" style="font-size: 13px">{{ __('Client price: leave empty to use the percentage above.') }}</span>
                    </div>
                    @error('prices.*') <p class="error-text">{{ $message }}</p> @enderror

                    @forelse ($categories as $category)
                        <fieldset data-service-group class="price-group">
                            <legend style="padding: 0 6px">
                                <label style="display: inline-flex; gap: 8px; align-items: center; margin: 0; cursor: pointer; font-weight: 700">
                                    <input type="checkbox" data-group-all data-only-selected> {{ $category->name }}
                                    @if ($category->status !== 'active') <span class="badge badge-inactive">{{ __('Inactive') }}</span> @endif
                                </label>
                            </legend>
                            <div class="table-wrap">
                                <table class="price-table">
                                    <thead><tr><th class="pt-check" data-only-selected></th><th>{{ __('Service') }}</th><th class="right">{{ __('Site price') }}</th><th class="right pt-input">{{ __('Client price') }}</th></tr></thead>
                                    <tbody>
                                        @foreach ($category->services as $service)
                                            <tr data-service-row data-name="{{ mb_strtolower($service->name) }}">
                                                <td class="pt-check" data-only-selected><input type="checkbox" name="service_ids[]" value="{{ $service->id }}" id="svc-{{ $service->id }}" data-service-item @checked(in_array($service->id, $ticked, true))></td>
                                                <td class="pt-name">
                                                    <label for="svc-{{ $service->id }}">{{ $service->name }}</label>
                                                    @if ($service->status !== 'active') <span class="badge badge-inactive">{{ __('Inactive') }}</span> @endif
                                                </td>
                                                <td class="right num pt-site" data-label="{{ __('Site price') }}">{{ money($service->price) }}</td>
                                                <td class="right pt-input" data-label="{{ __('Client price') }}">
                                                    <input type="number" name="prices[{{ $service->id }}]" value="{{ $fixed[$service->id] ?? '' }}" min="0" step="0.01" dir="ltr" inputmode="decimal"
                                                           data-client-price data-base="{{ (float) $service->price }}" aria-label="{{ __('Client price') }}: {{ $service->name }}">
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
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
<style>
    /* A fieldset never shrinks below its content by default, which pushed the page wider than a phone. */
    .price-group { min-width: 0; border: 1px solid var(--line, rgba(255,255,255,.1)); border-radius: 10px; padding: 6px 14px 10px; margin: 0 0 10px; }
    .price-table .pt-check { width: 32px; }
    .price-table .pt-input { width: 170px; }
    .price-table .pt-input input { max-width: 150px; text-align: right; }
    .price-table .pt-name label { margin: 0; font-size: inherit; font-weight: 500; color: var(--text); }
    .access-choice { display: flex; flex-wrap: wrap; gap: 4px 8px; align-items: center; margin: 6px 0; cursor: pointer; font-weight: 400; }

    /* Phones: one small card per service — tick box and name on top, site price and client price below. */
    @media (max-width: 680px) {
        .price-group { padding: 4px 10px 6px; }
        .price-table thead { display: none; }
        .price-table, .price-table tbody { display: block; width: 100%; }
        .price-table tr { display: grid; grid-template-columns: auto minmax(0, 1fr) auto; grid-template-areas: "check name name" "check site input"; gap: 6px 10px; align-items: center; padding: 10px 0; border-bottom: 1px solid var(--line, rgba(255,255,255,.08)); }
        .price-table tr[hidden] { display: none; }
        .price-table tr:last-child { border-bottom: 0; }
        .price-table td { display: block; padding: 0; border: 0; width: auto; }
        .price-table td[hidden] { display: none; }
        .price-table .pt-check { grid-area: check; align-self: start; padding-top: 2px; }
        .price-table .pt-name { grid-area: name; overflow-wrap: anywhere; }
        .price-table .pt-site { grid-area: site; text-align: start; font-size: 13px; }
        .price-table .pt-site::before { content: attr(data-label) ": "; color: var(--muted); }
        .price-table .pt-input { grid-area: input; }
        .price-table .pt-input input { width: 110px; max-width: 110px; }
        .price-table:not(:has(.pt-check:not([hidden]))) tr { grid-template-areas: "name name" "site input"; grid-template-columns: minmax(0, 1fr) auto; }
    }
</style>
<script>
(function () {
    var picker = document.querySelector('[data-service-picker]');
    if (!picker) { return; }
    var items = function (root) { return Array.prototype.slice.call((root || picker).querySelectorAll('[data-service-item]')); };
    var counter = picker.querySelector('[data-picked-count]');
    var adjust = document.querySelector('[data-price-adjust]');

    // Tick boxes only matter for "Only the services I tick".
    var setMode = function (mode) {
        picker.querySelectorAll('[data-only-selected]').forEach(function (el) { el.hidden = mode !== 'selected'; });
    };

    // Empty price boxes show what the percentage gives.
    var placeholders = function () {
        var pct = parseFloat(adjust.value) || 0;
        picker.querySelectorAll('[data-client-price]').forEach(function (input) {
            var p = Math.max(0, Math.round(parseFloat(input.getAttribute('data-base')) * (1 + pct / 100) * 100) / 100);
            input.placeholder = p.toFixed(2);
        });
    };

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
        radio.addEventListener('change', function () { if (radio.checked) { setMode(radio.value); } });
    });
    adjust.addEventListener('input', placeholders);

    picker.addEventListener('change', function (e) {
        if (e.target.matches('[data-group-all]')) {
            // Ticks only the services the filter currently shows.
            items(e.target.closest('[data-service-group]')).forEach(function (b) {
                if (!b.closest('[data-service-row]').hidden) { b.checked = e.target.checked; }
            });
        }
        // Typing a client price for a service also ticks it.
        if (e.target.matches('[data-client-price]') && e.target.value !== '') {
            var box = e.target.closest('[data-service-row]').querySelector('[data-service-item]');
            if (box) { box.checked = true; }
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

    setMode(picker.getAttribute('data-mode'));
    placeholders();
    refresh();
})();
</script>
@endpush
