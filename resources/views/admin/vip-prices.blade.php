@extends('layouts.panel')

@php
    // The same page serves VIP and Web Owner prices: on the Web Owner tab, "VIP" in the texts reads "Web Owner".
    $T = fn (string $text, array $replace = []) => $tier === \App\Support\PriceTier::VIP
        ? __($text, $replace)
        : str_replace('VIP', __('Web Owner'), __($text, $replace));
    $adjustValue = old('price_adjust', rtrim(rtrim(number_format($adjust, 2, '.', ''), '0'), '.'));
    // Confirm texts for the bulk change (the page script fills in :count and :value).
    $bulkTexts = [
        'fixed' => $T('Set the VIP price of :count services to :value$?'),
        'percent' => $T('Set the VIP price of :count services to their site price :value%?'),
        'clear' => $T('Clear the fixed VIP price of :count services? They will use the general percentage.'),
        'unsaved' => $T('VIP prices typed in the list and not saved yet will be lost.'),
    ];
@endphp

@section('title', $T('VIP prices'))
@section('eyebrow', __('Services'))
@section('page-title', $T('VIP prices'))
@section('page-description', $T('Customers you mark as VIP see and pay these prices. Everyone else keeps the normal site price.'))

@section('content')
    {{-- Price levels: a customer is VIP or Web Owner (never both), each level with its own prices. --}}
    <div class="tabs" style="margin-bottom: 18px">
        @foreach (\App\Support\PriceTier::all() as $t)
            <a href="{{ route('admin.vip-prices.edit', $t === \App\Support\PriceTier::VIP ? [] : ['tier' => $t]) }}" @class(['active' => $tier === $t])>
                {{ __(\App\Support\PriceTier::label($t)) }}<span>{{ number_format($levelCounts[$t] ?? 0) }}</span>
            </a>
        @endforeach
    </div>

    {{-- Its own card (not inside the prices form below): adding and removing customers are separate forms. --}}
    <div class="card" id="vip-customers" style="margin-bottom: 18px">
        <div class="card-head">
            <h2>{{ $T('VIP customers') }}</h2>
            <span class="badge badge-active">{{ $vipCustomers->count() }}</span>
        </div>
        <div class="card-body form-grid">
            <div>
                <span class="label">{{ $T('Customers paying VIP prices') }}</span>
                @if ($vipCustomers->isEmpty())
                    <p class="muted">{{ $T('None yet. Add a customer here, or open a customer\'s profile and tap "Give VIP prices".') }}</p>
                @else
                    <ul class="vip-chips">
                        @foreach ($vipCustomers as $vip)
                            <li>
                                <a href="{{ route('admin.customers.show', $vip->id) }}" title="{{ $vip->name }}">{{ $vip->username }}</a>
                                <form method="post" action="{{ route('admin.vip-prices.customers.remove', $vip->id) }}"
                                      data-confirm="{{ $T('Remove :name from VIP customers? They will pay the normal site prices.', ['name' => $vip->username]) }}">
                                    @csrf @method('delete')
                                    <input type="hidden" name="tier" value="{{ $tier }}">
                                    <button type="submit" class="vip-chip-x" aria-label="{{ $T('Remove :name from VIP customers', ['name' => $vip->username]) }}" title="{{ $T('Remove from VIP') }}">&times;</button>
                                </form>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
            <div>
                @if ($otherCustomers->isEmpty())
                    <span class="label">{{ $T('Add a VIP customer') }}</span>
                    <p class="muted">{{ $T('Every customer already pays VIP prices.') }}</p>
                @else
                    <form method="post" action="{{ route('admin.vip-prices.customers.add') }}" class="inline-form" data-once>
                        @csrf
                        <input type="hidden" name="tier" value="{{ $tier }}">
                        <div class="field">
                            <label for="vip-add-customer">{{ $T('Add a VIP customer') }}</label>
                            <select id="vip-add-customer" name="user_id" required data-search="{{ __('Search by name or username...') }}" data-search-empty="{{ __('No customer matches.') }}">
                                <option value="">{{ __('Choose a customer') }}</option>
                                @foreach ($otherCustomers as $other)
                                    <option value="{{ $other->id }}">{{ $other->username }} — {{ $other->name }}{{ $other->status !== 'active' ? ' (' . __($other->status) . ')' : '' }}{{ $other->priceTier() ? ' — ' . __('now :level, will be moved', ['level' => __(\App\Support\PriceTier::label($other->priceTier()))]) : '' }}</option>
                                @endforeach
                            </select>
                        </div>
                        <button class="btn btn-primary" type="submit">{{ $T('Add to VIP') }}</button>
                    </form>
                    @error('user_id') <p class="error-text">{{ $message }}</p> @enderror
                @endif
            </div>
        </div>
    </div>

    {{-- The percentage and the Save button belong to the prices form further down (form="vip-prices-form"):
         the filter form sits between them, and forms cannot be nested. --}}
    <div class="card" style="margin-bottom: 18px">
        <div class="card-head"><h2>{{ $T('VIP percentage') }}</h2></div>
        <div class="card-body">
            <div style="max-width: 520px">
                <label for="price_adjust">{{ $T('VIP price for every service') }} (%)</label>
                <input type="number" id="price_adjust" name="price_adjust" form="vip-prices-form" value="{{ $adjustValue }}" min="-100" max="1000" step="0.01" dir="ltr" required data-price-adjust>
                <p class="help">{{ $T('Applied to the site price. 0 = same as the site, -10 = 10% discount, 5 = 5% more. A VIP price typed next to a service below replaces it for that service.') }}</p>
                @error('price_adjust') <p class="error-text">{{ $message }}</p> @enderror
            </div>
        </div>
    </div>

    <div class="card" data-vip-picker>
        <div class="card-head">
            <div>
                <h2>{{ __('Price per service') }}</h2>
                <p>{{ $T('Leave a VIP price empty to use the percentage above. The grey number shows what the percentage gives.') }}</p>
            </div>
        </div>

        {{-- Results follow what is typed (data-live-search); Enter / Filter still work without JavaScript. --}}
        <form class="filters" method="get" action="{{ route('admin.vip-prices.edit') }}" id="vip-filter" data-live-search="#vip-price-list">
            @if ($tierQuery) <input type="hidden" name="tier" value="{{ $tier }}"> @endif
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
                    <option value="fixed" @selected($only === 'fixed')>{{ $T('With a fixed VIP price') }}</option>
                    <option value="percent" @selected($only === 'percent')>{{ __('Using the percentage') }}</option>
                </select>
            </div>
            <div class="btns">
                <button class="btn btn-primary" type="submit">{{ __('Filter') }}</button>
                <a class="btn" href="{{ route('admin.vip-prices.edit', $tierQuery) }}">{{ __('Reset') }}</a>
            </div>
        </form>

        {{-- Bulk change: tick services one by one, a whole category or page, or every service the filters match.
             The row tick boxes sit in the list below but belong to this form (form="vip-bulk"). --}}
        <form method="post" action="{{ route('admin.vip-prices.bulk') }}" id="vip-bulk" class="bulk-bar vip-bulk" data-once autocomplete="off">
            @csrf
            <input type="hidden" name="scope" value="selected" data-vip-scope>
            <input type="hidden" name="tier" value="{{ $tier }}">
            {{-- Filled from the list on submit: which filters "all matching" means, and where to come back to. --}}
            <input type="hidden" name="q" data-vip-keep="q">
            <input type="hidden" name="category" data-vip-keep="category">
            <input type="hidden" name="only" data-vip-keep="only">
            <input type="hidden" name="page" data-vip-keep="page">

            <label class="vip-check-page">
                <input type="checkbox" data-vip-check-page> {{ __('Select all on this page') }}
            </label>
            <span class="bulk-count" data-vip-count data-template="{{ __(':count selected') }}">{{ __(':count selected', ['count' => 0]) }}</span>
            <button type="button" class="vip-link" data-vip-select-matching hidden data-template="{{ __('Select all :total matching services') }}"></button>
            <button type="button" class="vip-link" data-vip-clear-selection hidden>{{ __('Clear selection') }}</button>

            <span class="vip-bulk-action">
                <label for="vip-bulk-action" class="sr-only">{{ __('Action') }}</label>
                <select id="vip-bulk-action" name="action" data-vip-action>
                    <option value="fixed" @selected(old('action') === 'fixed')>{{ $T('Set VIP price to ($)') }}</option>
                    <option value="percent" @selected(old('action') === 'percent')>{{ $T('Set VIP price to site price + (%)') }}</option>
                    <option value="clear" @selected(old('action') === 'clear')>{{ __('Clear: use the general percentage') }}</option>
                </select>
                <label for="vip-bulk-value" class="sr-only">{{ __('Value') }}</label>
                <input type="number" id="vip-bulk-value" name="value" value="{{ old('value') }}" step="0.01" dir="ltr" inputmode="decimal" data-vip-value
                       data-ph-fixed="{{ __('e.g. 2.50') }}" data-ph-percent="{{ __('e.g. -10') }}">
                <button class="btn btn-sm btn-primary" type="submit" data-vip-bulk-submit disabled>{{ __('Apply') }}</button>
            </span>
            @error('ids') <span class="error-text">{{ $message }}</span> @enderror
            @error('value') <span class="error-text">{{ $message }}</span> @enderror
        </form>

        <form method="post" action="{{ route('admin.vip-prices.update') }}" id="vip-prices-form" data-once autocomplete="off">
            @csrf
            @method('put')
            <input type="hidden" name="tier" value="{{ $tier }}">

            <div class="card-body" id="vip-price-list" data-live-results data-total="{{ $services->total() }}" data-per-page="{{ $services->count() }}">
                {{-- Kept on the same page and filters after saving (swapped with the results by the live search). --}}
                <input type="hidden" name="q" value="{{ $search }}">
                <input type="hidden" name="category" value="{{ $categoryId ?: '' }}">
                <input type="hidden" name="only" value="{{ $only }}">
                <input type="hidden" name="page" value="{{ $services->currentPage() }}">

                <div class="vip-toolbar">
                    <span class="muted">
                        {{ $T(':count services with a fixed VIP price', ['count' => $fixedCount]) }}
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
                                <thead><tr><th class="pt-check"><input type="checkbox" data-vip-check-group aria-label="{{ __('Select all in :category', ['category' => $category->name]) }}"></th><th>{{ __('Service') }}</th><th class="right">{{ __('Site price') }}</th><th class="right pt-input">{{ $T('VIP price') }}</th></tr></thead>
                                <tbody>
                                    @foreach ($category->services as $service)
                                        @php $fixed = old('prices.' . $service->id, $service->tier_price === null ? '' : rtrim(rtrim($service->tier_price, '0'), '.')); @endphp
                                        <tr>
                                            <td class="pt-check"><input type="checkbox" name="ids[]" value="{{ $service->id }}" form="vip-bulk" data-vip-check aria-label="{{ __('Select :name', ['name' => $service->name]) }}"></td>
                                            <td class="pt-name">
                                                <label for="vip-{{ $service->id }}">{{ $service->name }}</label>
                                                @if ($service->status !== 'active') <span class="badge badge-inactive">{{ __('Inactive') }}</span> @endif
                                            </td>
                                            <td class="right num pt-site" data-label="{{ __('Site price') }}">{{ money($service->price) }}</td>
                                            <td class="right pt-input" data-label="{{ $T('VIP price') }}">
                                                <input type="number" id="vip-{{ $service->id }}" name="prices[{{ $service->id }}]" value="{{ $fixed }}" min="0" step="0.01" dir="ltr" inputmode="decimal"
                                                       data-vip-price data-base="{{ (float) $service->price }}" data-orig="{{ $service->tier_price === null ? '' : rtrim(rtrim($service->tier_price, '0'), '.') }}" aria-label="{{ $T('VIP price') }}: {{ $service->name }}">
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
        <button type="submit" form="vip-prices-form" class="btn btn-primary">{{ $T('Save VIP prices') }}</button>
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
    .price-table .pt-check { width: 34px; padding-inline-end: 0; }
    .price-table .pt-check input, .vip-check-page input { width: 17px; height: 17px; cursor: pointer; accent-color: var(--accent); vertical-align: middle; }
    .price-table tr.is-picked td { background: var(--accent-soft); }
    .vip-bulk { margin: 0; padding: 10px 20px; border-top: 1px solid var(--line-soft); }
    .vip-check-page { display: inline-flex; align-items: center; gap: 8px; margin: 0; cursor: pointer; font-size: 13px; font-weight: 600; }
    .vip-link { border: 0; background: none; padding: 0; color: var(--blue); font: 600 13px var(--font); cursor: pointer; text-decoration: underline; text-underline-offset: 2px; }
    .vip-link[hidden] { display: none; }
    .vip-bulk-action { display: inline-flex; flex-wrap: wrap; gap: 8px; align-items: center; margin-inline-start: auto; }
    .vip-bulk-action select { min-width: 0; flex: 0 1 290px; }
    .vip-bulk-action input { width: 120px; min-height: 34px; padding: 6px 10px; }
    .vip-bulk-action input[hidden] { display: none; }
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
        .price-table tr { display: grid; grid-template-columns: auto minmax(0, 1fr) auto; grid-template-areas: "check name name" "check site input"; gap: 6px 10px; align-items: center; padding: 10px 0; border-bottom: 1px solid var(--line, rgba(255,255,255,.08)); }
        .price-table tr:last-child { border-bottom: 0; }
        .price-table td { display: block; padding: 0; border: 0; width: auto; }
        .price-table .pt-check { grid-area: check; align-self: start; padding-top: 2px; }
        .vip-bulk { padding: 10px 14px; }
        .vip-bulk-action { margin-inline-start: 0; width: 100%; }
        .vip-bulk-action select { flex: 1 1 100%; }
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
    var warning = @json($T('You have VIP prices that are not saved. Leave this page without saving them?'));
    picker.addEventListener('click', function (e) {
        var link = e.target.closest('[data-vip-pages] a');
        if (link && unsaved() && !window.confirm(warning)) { e.preventDefault(); }
    });
    if (filter) {
        // app.js asks before each live search; cancelling keeps the current list and typed prices.
        filter.addEventListener('live-search:before', function (e) {
            if (unsaved() && !window.confirm(warning)) { e.preventDefault(); }
        });
        filter.addEventListener('live-search:done', function () { placeholders(); applyHidden(); allMatching = false; refreshPicks(); });
    }

    // ----- Select services: one by one, a category, the page, or every service the filters match -----
    var bulk = document.getElementById('vip-bulk');
    var allMatching = false; // true after "Select all N matching services": the action covers every page
    var checks = function (root) { return Array.prototype.slice.call((root || picker).querySelectorAll('[data-vip-check]')); };
    var list = function () { return document.getElementById('vip-price-list'); };
    var total = function () { return parseInt(list().getAttribute('data-total'), 10) || 0; };
    var picked = function () { return checks().filter(function (c) { return c.checked; }).length; };
    var tpl = function (el, values) {
        var text = el.getAttribute('data-template');
        Object.keys(values).forEach(function (k) { text = text.replace(':' + k, values[k]); });
        return text;
    };

    var refreshPicks = function () {
        if (!bulk) { return; }
        var all = checks();
        var n = picked();
        var count = allMatching ? total() : n;

        // Tick boxes that cover several rows: ticked when all are, half-ticked when some are.
        var mark = function (box, items) {
            var on = items.filter(function (c) { return c.checked; }).length;
            box.checked = on > 0 && on === items.length;
            box.indeterminate = on > 0 && on < items.length;
        };
        picker.querySelectorAll('[data-vip-group]').forEach(function (g) {
            var box = g.querySelector('[data-vip-check-group]');
            if (box) { mark(box, checks(g)); }
        });
        mark(bulk.querySelector('[data-vip-check-page]'), all);
        all.forEach(function (c) { c.closest('tr').classList.toggle('is-picked', c.checked); });

        var counter = bulk.querySelector('[data-vip-count]');
        counter.textContent = tpl(counter, { count: count });
        bulk.classList.toggle('has-selection', count > 0);
        bulk.querySelector('[data-vip-bulk-submit]').disabled = count === 0;

        // Every row on this page ticked and more on other pages: offer all matching services.
        var offer = bulk.querySelector('[data-vip-select-matching]');
        offer.hidden = allMatching || all.length === 0 || n !== all.length || total() <= all.length;
        offer.textContent = tpl(offer, { total: total() });
        bulk.querySelector('[data-vip-clear-selection]').hidden = count === 0;
        bulk.querySelector('[data-vip-scope]').value = allMatching ? 'all' : 'selected';
    };

    picker.addEventListener('change', function (e) {
        var t = e.target;
        if (t.matches('[data-vip-check-page]')) {
            checks().forEach(function (c) { c.checked = t.checked; });
        } else if (t.matches('[data-vip-check-group]')) {
            checks(t.closest('[data-vip-group]')).forEach(function (c) { c.checked = t.checked; });
        } else if (!t.matches('[data-vip-check]')) {
            return;
        }
        allMatching = false; // changing the ticks by hand goes back to "only the ticked ones"
        refreshPicks();
    });
    // The category tick box sits in the table head, not in <summary>: clicking it never folds the category.
    picker.addEventListener('click', function (e) {
        if (e.target.matches('[data-vip-select-matching]')) {
            allMatching = true;
            checks().forEach(function (c) { c.checked = true; });
            refreshPicks();
        } else if (e.target.matches('[data-vip-clear-selection]')) {
            allMatching = false;
            checks().forEach(function (c) { c.checked = false; });
            refreshPicks();
        }
    });

    if (bulk) {
        // The value box is not needed for "Clear"; its hint follows the action.
        var action = bulk.querySelector('[data-vip-action]');
        var value = bulk.querySelector('[data-vip-value]');
        var syncAction = function () {
            value.hidden = action.value === 'clear';
            value.required = action.value !== 'clear';
            value.min = action.value === 'percent' ? '-100' : '0';
            value.max = action.value === 'percent' ? '1000' : '999999.99';
            value.placeholder = value.getAttribute(action.value === 'percent' ? 'data-ph-percent' : 'data-ph-fixed');
        };
        action.addEventListener('change', syncAction);
        syncAction();

        var texts = @json($bulkTexts);
        bulk.addEventListener('submit', function () {
            // The filters of the list shown (they decide "all matching") and the page to come back to.
            bulk.querySelectorAll('[data-vip-keep]').forEach(function (input) {
                var src = list().querySelector('input[type=hidden][name="' + input.getAttribute('data-vip-keep') + '"]');
                input.value = src ? src.value : '';
            });
            var n = allMatching ? total() : picked();
            var v = action.value === 'percent' && parseFloat(value.value) > 0 ? '+' + value.value : value.value;
            var text = texts[action.value].replace(':count', n).replace(':value', v);
            if (unsaved()) { text += '\n\n' + texts.unsaved; }
            // Read by app.js' confirm step, which runs after this listener.
            bulk.setAttribute('data-confirm', text);
        });
    }

    applyHidden();
    placeholders();
    refreshPicks();
})();
</script>
@endpush
