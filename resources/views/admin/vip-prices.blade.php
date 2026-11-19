@extends('layouts.panel')

@php
    $adjustValue = old('price_adjust', rtrim(rtrim(number_format($adjust, 2, '.', ''), '0'), '.'));
@endphp

@section('title', __('VIP prices'))
@section('eyebrow', __('Services'))
@section('page-title', __('VIP prices'))
@section('page-description', __('Customers you mark as VIP see and pay these prices. Everyone else keeps the normal site price.'))

@section('content')
    {{-- Its own card (not inside the prices form below): adding and removing customers are separate forms. --}}
    <div class="card" id="vip-customers" style="margin-bottom: 18px">
        <div class="card-head">
            <h2>{{ __('VIP customers') }}</h2>
            <span class="badge badge-active">{{ $vipCustomers->count() }}</span>
        </div>
        <div class="card-body form-grid">
            <div>
                <span class="label">{{ __('Customers paying VIP prices') }}</span>
                @if ($vipCustomers->isEmpty())
                    <p class="muted">{{ __('None yet. Add a customer here, or open a customer\'s profile and tap "Give VIP prices".') }}</p>
                @else
                    <ul class="vip-chips">
                        @foreach ($vipCustomers as $vip)
                            <li>
                                <a href="{{ route('admin.customers.show', $vip->id) }}" title="{{ $vip->name }}">{{ $vip->username }}</a>
                                <form method="post" action="{{ route('admin.vip-prices.customers.remove', $vip->id) }}"
                                      data-confirm="{{ __('Remove :name from VIP customers? They will pay the normal site prices.', ['name' => $vip->username]) }}">
                                    @csrf @method('delete')
                                    <button type="submit" class="vip-chip-x" aria-label="{{ __('Remove :name from VIP customers', ['name' => $vip->username]) }}" title="{{ __('Remove from VIP') }}">&times;</button>
                                </form>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
            <div>
                @if ($otherCustomers->isEmpty())
                    <span class="label">{{ __('Add a VIP customer') }}</span>
                    <p class="muted">{{ __('Every customer already pays VIP prices.') }}</p>
                @else
                    <form method="post" action="{{ route('admin.vip-prices.customers.add') }}" class="inline-form" data-once>
                        @csrf
                        <div class="field">
                            <label for="vip-add-customer">{{ __('Add a VIP customer') }}</label>
                            <select id="vip-add-customer" name="user_id" required data-search="{{ __('Search by name or username...') }}" data-search-empty="{{ __('No customer matches.') }}">
                                <option value="">{{ __('Choose a customer') }}</option>
                                @foreach ($otherCustomers as $other)
                                    <option value="{{ $other->id }}">{{ $other->username }} — {{ $other->name }}@if ($other->status !== 'active') ({{ __($other->status) }})@endif</option>
                                @endforeach
                            </select>
                        </div>
                        <button class="btn btn-primary" type="submit">{{ __('Add to VIP') }}</button>
                    </form>
                    @error('user_id') <p class="error-text">{{ $message }}</p> @enderror
                @endif
            </div>
        </div>
    </div>

    {{-- The percentage and the Save button belong to the prices form further down (form="vip-prices-form"):
         the filter form sits between them, and forms cannot be nested. --}}
    <div class="card" style="margin-bottom: 18px">
        <div class="card-head"><h2>{{ __('VIP percentage') }}</h2></div>
        <div class="card-body">
            <div style="max-width: 520px">
                <label for="price_adjust">{{ __('VIP price for every service') }} (%)</label>
                <input type="number" id="price_adjust" name="price_adjust" form="vip-prices-form" value="{{ $adjustValue }}" min="-90" max="1000" step="0.01" dir="ltr" required data-price-adjust>
                <p class="help">{{ __('Applied to the site price. 0 = same as the site, -10 = 10% discount, 5 = 5% more. A VIP price typed next to a service below replaces it for that service.') }}</p>
                @error('price_adjust') <p class="error-text">{{ $message }}</p> @enderror
            </div>
        </div>
    </div>

    <div class="card" data-vip-picker>
        <div class="card-head">
            <div>
                <h2>{{ __('Price per service') }}</h2>
                <p>{{ __('Leave a VIP price empty to use the percentage above. The grey number shows what the percentage gives.') }}</p>
            </div>
        </div>

        {{-- Results follow what is typed (data-live-search); Enter / Filter still work without JavaScript. --}}
        <form class="filters" method="get" action="{{ route('admin.vip-prices.edit') }}" id="vip-filter" data-live-search="#vip-price-list">
            <div class="field grow">
                <label for="vip-q">{{ __('Quick search') }}</label>
                <input type="search" id="vip-q" name="q" value="{{ $search }}" placeholder="{{ __('Search by service name...') }}" autocomplete="off">
            </div>
            <div class="field">
                <label for="vip-category">{{ __('Category') }}</label>
                <select id="vip-category" name="category">
                    <option value="">{{ __('All categories') }}</option>
                    @foreach ($categoryOptions as $option)
                        <option value="{{ $option->id }}" @selected($categoryId === $option->id)>{{ $option->name }} ({{ $option->services_count }})</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label for="vip-only">{{ __('Show') }}</label>
                <select id="vip-only" name="only">
                    <option value="">{{ __('All services') }}</option>
                    <option value="fixed" @selected($only === 'fixed')>{{ __('With a fixed VIP price') }}</option>
                    <option value="percent" @selected($only === 'percent')>{{ __('Using the percentage') }}</option>
                </select>
            </div>
            <div class="btns">
                <button class="btn btn-primary" type="submit">{{ __('Filter') }}</button>
                <a class="btn" href="{{ route('admin.vip-prices.edit') }}">{{ __('Reset') }}</a>
            </div>
        </form>

        <form method="post" action="{{ route('admin.vip-prices.update') }}" id="vip-prices-form" data-once autocomplete="off">
            @csrf
            @method('put')

            <div class="card-body" id="vip-price-list" data-live-results>
                {{-- Kept on the same page and filters after saving (swapped with the results by the live search). --}}
                <input type="hidden" name="q" value="{{ $search }}">
                <input type="hidden" name="category" value="{{ $categoryId ?: '' }}">
                <input type="hidden" name="only" value="{{ $only }}">
                <input type="hidden" name="page" value="{{ $services->currentPage() }}">

                <div class="vip-toolbar">
                    <span class="muted">
                        {{ __(':count services with a fixed VIP price', ['count' => $fixedCount]) }}
                        @if ($services->total() > 0) · {{ __('Showing :from–:to of :total', ['from' => $services->firstItem(), 'to' => $services->lastItem(), 'total' => $services->total()]) }} @endif
                    </span>
                    @if ($categories->isNotEmpty())
                        <span class="vip-toggle-all">
                            <button type="button" class="btn btn-sm" data-vip-groups="open">{{ __('Show all') }}</button>
                            <button type="button" class="btn btn-sm" data-vip-groups="close">{{ __('Hide all') }}</button>
                        </span>
                    @endif
                </div>
                @error('prices.*') <p class="error-text">{{ $message }}</p> @enderror

                @forelse ($categories as $category)
                    {{-- Click the category name to show / hide its services; remembered in this browser. --}}
                    <details class="price-group" open data-vip-group="{{ $category->id }}">
                        <summary>
                            <span class="pg-chevron" aria-hidden="true"></span>
                            <span class="pg-name" dir="auto">{{ $category->name }}</span>
                            <span class="muted pg-count">({{ $category->services->count() }})</span>
                            @if ($category->status !== 'active') <span class="badge badge-inactive">{{ __('Inactive') }}</span> @endif
                        </summary>
                        <div class="table-wrap">
                            <table class="price-table">
                                <thead><tr><th>{{ __('Service') }}</th><th class="right">{{ __('Site price') }}</th><th class="right pt-input">{{ __('VIP price') }}</th></tr></thead>
                                <tbody>
                                    @foreach ($category->services as $service)
                                        @php $fixed = old('prices.' . $service->id, $service->vip_price === null ? '' : rtrim(rtrim($service->vip_price, '0'), '.')); @endphp
                                        <tr>
                                            <td class="pt-name">
                                                <label for="vip-{{ $service->id }}">{{ $service->name }}</label>
                                                @if ($service->status !== 'active') <span class="badge badge-inactive">{{ __('Inactive') }}</span> @endif
                                            </td>
                                            <td class="right num pt-site" data-label="{{ __('Site price') }}">{{ money($service->price) }}</td>
                                            <td class="right pt-input" data-label="{{ __('VIP price') }}">
                                                <input type="number" id="vip-{{ $service->id }}" name="prices[{{ $service->id }}]" value="{{ $fixed }}" min="0" step="0.01" dir="ltr" inputmode="decimal"
                                                       data-vip-price data-base="{{ (float) $service->price }}" data-orig="{{ $service->vip_price === null ? '' : rtrim(rtrim($service->vip_price, '0'), '.') }}" aria-label="{{ __('VIP price') }}: {{ $service->name }}">
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </details>
                @empty
                    @if ($search !== '' || $categoryId || $only !== '')
                        <div class="empty"><strong>{{ __('No service matches.') }}</strong>{{ __('Change the search or the filters.') }}</div>
                    @else
                        <div class="empty"><strong>{{ __('No services yet') }}</strong></div>
                    @endif
                @endforelse

                @if ($services->hasPages())
                    <div class="vip-pages" data-vip-pages>{{ $services->links() }}</div>
                @endif
            </div>
        </form>
    </div>

    <div class="form-actions">
        <button type="submit" form="vip-prices-form" class="btn btn-primary">{{ __('Save VIP prices') }}</button>
    </div>
@endsection

@push('scripts')
<style>
    .price-group { min-width: 0; border: 1px solid var(--line, rgba(255,255,255,.1)); border-radius: 10px; padding: 0 14px; margin: 0 0 10px; }
    .price-group > summary { display: flex; align-items: center; gap: 8px; padding: 10px 0; cursor: pointer; font-weight: 700; list-style: none; user-select: none; }
    .price-group > summary::-webkit-details-marker { display: none; }
    .price-group > summary:hover .pg-name { color: var(--accent-ink); }
    .price-group > summary:focus-visible { outline: 2px solid var(--accent); outline-offset: 2px; border-radius: 6px; }
    .price-group[open] > summary { border-bottom: 1px solid var(--line-soft); }
    .price-group[open] { padding-bottom: 6px; }
    .pg-chevron { width: 8px; height: 8px; border-right: 2px solid var(--muted); border-bottom: 2px solid var(--muted); transform: rotate(-45deg); transition: transform .15s; flex: none; }
    [dir=rtl] .pg-chevron { transform: rotate(135deg); }
    .price-group[open] .pg-chevron { transform: rotate(45deg); }
    .pg-count { font-weight: 400; font-size: 13px; }
    .vip-toolbar { display: flex; flex-wrap: wrap; gap: 8px 12px; align-items: center; justify-content: space-between; margin-bottom: 12px; font-size: 13px; }
    .vip-toggle-all { display: inline-flex; gap: 6px; }
    #vip-price-list.is-loading { opacity: .55; transition: opacity .15s; }
    .price-table .pt-input { width: 170px; }
    .price-table .pt-input input { max-width: 150px; text-align: right; }
    .price-table .pt-name label { margin: 0; font-size: inherit; font-weight: 500; color: var(--text); }
    .vip-chips { list-style: none; margin: 0; padding: 0; display: flex; flex-wrap: wrap; gap: 8px; }
    .vip-chips li { display: inline-flex; align-items: center; gap: 2px; padding: 3px 4px 3px 12px; border: 1px solid var(--line-strong); border-radius: 999px; background: var(--panel-2); }
    [dir=rtl] .vip-chips li { padding: 3px 12px 3px 4px; }
    .vip-chips a { font-weight: 600; }
    .vip-chips form { margin: 0; }
    .vip-chip-x { width: 24px; height: 24px; border: 0; border-radius: 50%; background: transparent; color: var(--muted); font-size: 17px; line-height: 1; cursor: pointer; }
    .vip-chip-x:hover, .vip-chip-x:focus-visible { background: rgba(242, 96, 106, .15); color: var(--red); outline: none; }

    /* Phones: one small card per service — name on top, site price and VIP price below. */
    @media (max-width: 680px) {
        .price-group { padding: 0 10px; }
        .price-table thead { display: none; }
        .price-table, .price-table tbody { display: block; width: 100%; }
        .price-table tr { display: grid; grid-template-columns: minmax(0, 1fr) auto; grid-template-areas: "name name" "site input"; gap: 6px 10px; align-items: center; padding: 10px 0; border-bottom: 1px solid var(--line, rgba(255,255,255,.08)); }
        .price-table tr:last-child { border-bottom: 0; }
        .price-table td { display: block; padding: 0; border: 0; width: auto; }
        .price-table .pt-name { grid-area: name; overflow-wrap: anywhere; }
        .price-table .pt-site { grid-area: site; text-align: start; font-size: 13px; }
        .price-table .pt-site::before { content: attr(data-label) ": "; color: var(--muted); }
        .price-table .pt-input { grid-area: input; }
        .price-table .pt-input input { width: 110px; max-width: 110px; }
    }
</style>
<script>
(function () {
    var picker = document.querySelector('[data-vip-picker]');
    var adjust = document.querySelector('[data-price-adjust]');
    var pricesForm = document.getElementById('vip-prices-form');
    var filter = document.getElementById('vip-filter');
    if (!picker || !adjust || !pricesForm) { return; }
    var priceInputs = function () { return Array.prototype.slice.call(picker.querySelectorAll('[data-vip-price]')); };

    // Empty VIP price boxes show what the percentage gives.
    var placeholders = function () {
        var pct = parseFloat(adjust.value) || 0;
        priceInputs().forEach(function (input) {
            var p = Math.max(0, Math.round(parseFloat(input.getAttribute('data-base')) * (1 + pct / 100) * 100) / 100);
            input.placeholder = p.toFixed(2);
        });
    };
    adjust.addEventListener('input', placeholders);

    // Send only the prices that changed: with thousands of services the full form would pass PHP's
    // max_input_vars limit and the rest would be dropped. Disabled inputs are not submitted.
    pricesForm.addEventListener('submit', function () {
        priceInputs().forEach(function (input) {
            input.disabled = input.value.trim() === input.getAttribute('data-orig');
        });
    });

    // ----- Show / hide categories (remembered in this browser) -----
    var KEY = 'vipPricesHidden';
    var hidden = {};
    try { hidden = JSON.parse(localStorage.getItem(KEY) || '{}') || {}; } catch (e) { hidden = {}; }
    var save = function () { try { localStorage.setItem(KEY, JSON.stringify(hidden)); } catch (e) { /* storage blocked */ } };
    var groups = function () { return Array.prototype.slice.call(picker.querySelectorAll('[data-vip-group]')); };
    var applyHidden = function () {
        groups().forEach(function (g) { if (hidden[g.getAttribute('data-vip-group')]) { g.open = false; } });
    };
    picker.addEventListener('toggle', function (e) {
        var g = e.target;
        if (!g.matches || !g.matches('[data-vip-group]')) { return; }
        if (g.open) { delete hidden[g.getAttribute('data-vip-group')]; } else { hidden[g.getAttribute('data-vip-group')] = 1; }
        save();
    }, true); // "toggle" does not bubble
    picker.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-vip-groups]');
        if (!btn) { return; }
        var open = btn.getAttribute('data-vip-groups') === 'open';
        groups().forEach(function (g) { g.open = open; });
    });

    // ----- Unsaved prices -----
    // Typed prices live in the list; a new search, filter or page replaces it.
    var unsaved = function () {
        return priceInputs().some(function (input) { return input.value.trim() !== input.getAttribute('data-orig'); });
    };
    var warning = @json(__('You have VIP prices that are not saved. Leave this page without saving them?'));
    picker.addEventListener('click', function (e) {
        var link = e.target.closest('[data-vip-pages] a');
        if (link && unsaved() && !window.confirm(warning)) { e.preventDefault(); }
    });
    if (filter) {
        // app.js asks before each live search; cancelling keeps the current list and typed prices.
        filter.addEventListener('live-search:before', function (e) {
            if (unsaved() && !window.confirm(warning)) { e.preventDefault(); }
        });
        filter.addEventListener('live-search:done', function () { placeholders(); applyHidden(); });
    }

    applyHidden();
    placeholders();
})();
</script>
@endpush
