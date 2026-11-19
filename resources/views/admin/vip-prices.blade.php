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

    <form method="get" action="{{ route('admin.vip-prices.edit') }}#vip-price-list" id="vip-search" hidden></form>

    <form method="post" action="{{ route('admin.vip-prices.update') }}" class="stack" data-once autocomplete="off">
        @csrf
        @method('put')

        <div class="card">
            <div class="card-head"><h2>{{ __('VIP percentage') }}</h2></div>
            <div class="card-body">
                <div style="max-width: 520px">
                    <label for="price_adjust">{{ __('VIP price for every service') }} (%)</label>
                    <input type="number" id="price_adjust" name="price_adjust" value="{{ $adjustValue }}" min="-90" max="1000" step="0.01" dir="ltr" required data-price-adjust>
                    <p class="help">{{ __('Applied to the site price. 0 = same as the site, -10 = 10% discount, 5 = 5% more. A VIP price typed next to a service below replaces it for that service.') }}</p>
                    @error('price_adjust') <p class="error-text">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-head">
                <div>
                    <h2>{{ __('Price per service') }}</h2>
                    <p>{{ __('Leave a VIP price empty to use the percentage above. The grey number shows what the percentage gives.') }}</p>
                </div>
            </div>
            <div class="card-body" data-vip-picker id="vip-price-list">
                {{-- Kept on the same page and search after saving. --}}
                <input type="hidden" name="q" value="{{ $search }}">
                <input type="hidden" name="page" value="{{ $services->currentPage() }}">

                {{-- The search box belongs to the GET form outside this one (form="vip-search"): it searches every page. --}}
                <div style="display: flex; gap: 8px 12px; align-items: center; flex-wrap: wrap; margin-bottom: 12px">
                    <input type="search" name="q" form="vip-search" value="{{ $search }}" placeholder="{{ __('Search by service name...') }}" style="max-width: 320px; flex: 1 1 200px; min-width: 0" autocomplete="off" aria-label="{{ __('Search by service name...') }}">
                    <button type="submit" form="vip-search" class="btn">{{ __('Search') }}</button>
                    @if ($search !== '') <a class="btn" href="{{ route('admin.vip-prices.edit') }}#vip-price-list">{{ __('Reset') }}</a> @endif
                    <span class="muted" style="font-size: 13px">
                        {{ __(':count services with a fixed VIP price', ['count' => $fixedCount]) }}
                        @if ($services->total() > 0) · {{ __('Showing :from–:to of :total', ['from' => $services->firstItem(), 'to' => $services->lastItem(), 'total' => $services->total()]) }} @endif
                    </span>
                </div>
                @error('prices.*') <p class="error-text">{{ $message }}</p> @enderror

                @forelse ($categories as $category)
                    <fieldset data-service-group class="price-group">
                        <legend style="padding: 0 6px; font-weight: 700">
                            {{ $category->name }}
                            @if ($category->status !== 'active') <span class="badge badge-inactive">{{ __('Inactive') }}</span> @endif
                        </legend>
                        <div class="table-wrap">
                            <table class="price-table">
                                <thead><tr><th>{{ __('Service') }}</th><th class="right">{{ __('Site price') }}</th><th class="right pt-input">{{ __('VIP price') }}</th></tr></thead>
                                <tbody>
                                    @foreach ($category->services as $service)
                                        @php $fixed = old('prices.' . $service->id, $service->vip_price === null ? '' : rtrim(rtrim($service->vip_price, '0'), '.')); @endphp
                                        <tr data-service-row data-name="{{ mb_strtolower($service->name) }}">
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
                    </fieldset>
                @empty
                    @if ($search !== '')
                        <div class="empty"><strong>{{ __('No service matches.') }}</strong></div>
                    @else
                        <div class="empty"><strong>{{ __('No services yet') }}</strong></div>
                    @endif
                @endforelse

                @if ($services->hasPages())
                    <div class="vip-pages" data-vip-pages>{{ $services->links() }}</div>
                @endif
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">{{ __('Save VIP prices') }}</button>
        </div>
    </form>
@endsection

@push('scripts')
<style>
    .price-group { min-width: 0; border: 1px solid var(--line, rgba(255,255,255,.1)); border-radius: 10px; padding: 6px 14px 10px; margin: 0 0 10px; }
    .price-group[hidden], .price-table tr[hidden] { display: none; }
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
        .price-group { padding: 4px 10px 6px; }
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
    if (!picker || !adjust) { return; }

    // Empty VIP price boxes show what the percentage gives.
    var placeholders = function () {
        var pct = parseFloat(adjust.value) || 0;
        picker.querySelectorAll('[data-vip-price]').forEach(function (input) {
            var p = Math.max(0, Math.round(parseFloat(input.getAttribute('data-base')) * (1 + pct / 100) * 100) / 100);
            input.placeholder = p.toFixed(2);
        });
    };
    adjust.addEventListener('input', placeholders);

    // Send only the prices that changed: with thousands of services the full form would pass PHP's
    // max_input_vars limit and the rest would be dropped. Disabled inputs are not submitted.
    adjust.form.addEventListener('submit', function () {
        picker.querySelectorAll('[data-vip-price]').forEach(function (input) {
            input.disabled = input.value.trim() === input.getAttribute('data-orig');
        });
    });

    // Changing page or searching leaves this page: warn when typed prices were not saved yet.
    var unsaved = function () {
        var adjustChanged = adjust.value.trim() !== adjust.defaultValue.trim();
        return adjustChanged || Array.prototype.some.call(picker.querySelectorAll('[data-vip-price]'), function (input) {
            return input.value.trim() !== input.getAttribute('data-orig');
        });
    };
    var warning = @json(__('You have VIP prices that are not saved. Leave this page without saving them?'));
    picker.addEventListener('click', function (e) {
        var link = e.target.closest('[data-vip-pages] a');
        if (link && unsaved() && !window.confirm(warning)) { e.preventDefault(); }
    });
    var search = document.getElementById('vip-search');
    if (search) {
        search.addEventListener('submit', function (e) {
            if (unsaved() && !window.confirm(warning)) { e.preventDefault(); }
        });
    }

    placeholders();
})();
</script>
@endpush
