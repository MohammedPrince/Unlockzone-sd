@extends('layouts.panel')

@section('title', $provider->name)
@section('eyebrow', __('API providers'))
@section('page-title', $provider->name)
@section('page-description', __('The provider\'s price list. Tick services and import them to sell them here.'))

@section('page-actions')
    <a class="btn" href="{{ route('admin.providers.index') }}">@include('partials.icon', ['name' => 'back']) {{ __('API providers') }}</a>
    <form method="post" action="{{ route('admin.providers.sync', $provider->id) }}" class="inline" data-once>
        @csrf
        <button class="btn btn-primary" type="submit">{{ __('Sync services') }}</button>
    </form>
@endsection

@section('content')
    <div class="stack">
        <div class="stats">
            <div class="stat accent">
                <div class="label">@include('partials.icon', ['name' => 'wallet']) {{ __('Your balance at the provider') }}</div>
                <div class="value">{{ $provider->balance !== null ? amount($provider->balance) : '—' }} <small>{{ $provider->currency }}</small></div>
                <div class="hint">{{ $provider->last_checked_at ? __('Checked') . ' ' . $provider->last_checked_at->diffForHumans() : __('Not checked yet') }}</div>
            </div>
            <div class="stat">
                <div class="label">{{ __('Services in the price list') }}</div>
                <div class="value">{{ number_format($provider->remote_services_count) }}</div>
                <div class="hint">{{ $provider->services_synced_at ? __('Synced') . ' ' . $provider->services_synced_at->diffForHumans() : __('Not synced yet') }}</div>
            </div>
            <div class="stat">
                <div class="label">{{ __('Linked services') }}</div>
                <div class="value">{{ number_format($provider->services_count) }}</div>
                <div class="hint"><span class="badge badge-{{ $provider->status }}">{{ __(ucfirst($provider->status)) }}</span></div>
            </div>
        </div>

        <div class="card">
            <form class="filters" method="get" action="{{ route('admin.providers.services', $provider->id) }}" data-live-search="#provider-results">
                <div class="field grow">
                    <label for="q">{{ __('Search') }}</label>
                    <input type="search" id="q" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="{{ __('Service name, group or ID') }}" autocomplete="off">
                </div>
                <div class="field">
                    <label for="type">{{ __('Type') }}</label>
                    <select id="type" name="type">
                        <option value="">{{ __('All types') }}</option>
                        @foreach (['IMEI', 'SERVER', 'REMOTE'] as $type)
                            <option value="{{ $type }}" @selected(($filters['type'] ?? '') === $type)>{{ $type }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="btns">
                    <button class="btn btn-primary" type="submit">{{ __('Filter') }}</button>
                    <a class="btn" href="{{ route('admin.providers.services', $provider->id) }}">{{ __('Reset') }}</a>
                </div>
            </form>

            <div id="provider-results" data-live-results>
            @if ($remote->isEmpty())
                <div class="empty"><strong>{{ __('No services here') }}</strong>{{ __('Press "Sync services" to download the provider\'s price list, or change the search.') }}</div>
            @else
                <form method="post" action="{{ route('admin.providers.import', $provider->id) }}" id="provider-import" data-once data-bulk>
                    @csrf
                    {{-- The page's search, so "Import all" takes exactly the services listed. --}}
                    <input type="hidden" name="q" value="{{ $filters['q'] ?? '' }}">
                    <input type="hidden" name="type" value="{{ $filters['type'] ?? '' }}">

                    <div class="bulk-bar">
                        <label style="display: inline-flex; align-items: center; gap: 8px; margin: 0; cursor: pointer">
                            <input type="checkbox" data-check-all> {{ __('Select all on this page') }}
                        </label>
                        <span class="bulk-count" data-bulk-count data-template="{{ __(':count selected') }}">{{ __(':count selected', ['count' => 0]) }}</span>
                        <span class="muted" style="font-size: 13px">{{ trans_choice('{1} :count service matches|[2,*] :count services match', $remote->total(), ['count' => number_format($remote->total())]) }}</span>
                    </div>

                    <div class="table-wrap">
                        <table class="cards">
                            <thead><tr><th style="width: 40px"><input type="checkbox" data-check-all aria-label="{{ __('Select all on this page') }}"></th><th>{{ __('Service') }}</th><th>{{ __('Type') }}</th><th>{{ __('Needs') }}</th><th>{{ __('Delivery') }}</th><th class="right">{{ __('Provider price') }}</th></tr></thead>
                            <tbody>
                                @foreach ($remote as $row)
                                    @php $localId = $imported[$row->remote_id] ?? null; @endphp
                                    <tr>
                                        <td>
                                            @if ($localId)
                                                <span class="pos" title="{{ __('Already imported') }}">✓</span>
                                            @else
                                                <input type="checkbox" name="remote_ids[]" value="{{ $row->remote_id }}" id="remote-{{ $row->id }}" data-check-item @checked(in_array($row->remote_id, old('remote_ids', []), true))>
                                            @endif
                                        </td>
                                        <td class="primary">
                                            <label for="remote-{{ $row->id }}" style="margin: 0; font-size: inherit; font-weight: 600; color: var(--text)">{{ $row->name }}</label>
                                            <span class="sub">{{ $row->group_name }} · <span class="mono">ID {{ $row->remote_id }}</span></span>
                                            @if ($localId) <a class="sub" href="{{ route('admin.services.edit', $localId) }}">{{ __('Already imported') }} · {{ __('Edit') }}</a> @endif
                                        </td>
                                        <td data-label="{{ __('Type') }}"><span class="tag">{{ $row->type }}</span></td>
                                        <td data-label="{{ __('Needs') }}">
                                            @if ($row->needs_imei) <span class="tag">IMEI</span> @endif
                                            @foreach ($row->fields ?? [] as $field) <span class="tag">{{ $field['name'] }}</span> @endforeach
                                        </td>
                                        <td data-label="{{ __('Delivery') }}">{{ $row->delivery_time ?: '—' }}</td>
                                        <td data-label="{{ __('Provider price') }}" class="right num">{{ rtrim(rtrim(number_format((float) $row->price, 4), '0'), '.') }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    {{ $remote->links() }}

                    <div class="sticky-actions">
                        <div class="inline-form">
                            <div class="field">
                                <label for="service_category_id">{{ __('Import into category') }}</label>
                                <select id="service_category_id" name="service_category_id">
                                    <option value="">{{ __('Provider\'s group (category created if missing)') }}</option>
                                    @foreach ($categories as $category)
                                        <option value="{{ $category->id }}" @selected((string) old('service_category_id') === (string) $category->id)>{{ $category->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="field">
                                <label for="margin">{{ __('Profit margin') }} (%)</label>
                                <input type="number" id="margin" name="margin" value="{{ old('margin', 20) }}" min="0" max="1000" step="0.01" required dir="ltr">
                            </div>
                            <div class="field">
                                <label for="import-status">{{ __('Import as') }}</label>
                                <select id="import-status" name="status" required>
                                    <option value="active">{{ __('Active (on sale)') }}</option>
                                    <option value="inactive" @selected(old('status') === 'inactive')>{{ __('Inactive (hidden)') }}</option>
                                </select>
                            </div>
                            <button type="submit" class="btn btn-primary" name="scope" value="selected" data-bulk-submit>{{ __('Import selected services') }}</button>
                            <button type="submit" class="btn" name="scope" value="all" formnovalidate
                                    onclick="return confirm(this.getAttribute('data-ask'))"
                                    data-ask="{{ __('Import all :count services that match this search (up to 1000)?', ['count' => number_format($remote->total())]) }}">
                                {{ __('Import all :count', ['count' => number_format(min($remote->total(), 1000))]) }}
                            </button>
                        </div>
                        <p class="help">{{ __('Your price = provider price + margin. With "Provider\'s group" each service goes into a category named like the provider\'s group, created when missing. A service whose name already exists on your site is not duplicated: the existing one is switched to this API (it keeps its category, status and order history). Missing input fields are added to the category automatically. A free provider service (price 0) is imported with price 0; edit it to set your own price.') }} {{ __('A service that already exists keeps your price and delivery time: only its required input fields come from the API.') }} {{ __('Every "Sync services" also updates the required input fields of services already imported from this provider when they no longer match the API.') }}</p>
                    </div>
                </form>
            @endif
            </div>
        </div>
    </div>
@endsection
