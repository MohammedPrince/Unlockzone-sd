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
            <form class="filters" method="get" action="{{ route('admin.providers.services', $provider->id) }}">
                <div class="field grow">
                    <label for="q">{{ __('Search') }}</label>
                    <input type="search" id="q" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="{{ __('Service name, group or ID') }}">
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

            @if ($remote->isEmpty())
                <div class="empty"><strong>{{ __('No services here') }}</strong>{{ __('Press "Sync services" to download the provider\'s price list, or change the search.') }}</div>
            @elseif ($categories->isEmpty())
                <div class="empty"><strong>{{ __('Create a category first') }}</strong>{{ __('Services live inside categories.') }} <a href="{{ route('admin.categories.create') }}">{{ __('New category') }}</a></div>
            @else
                <form method="post" action="{{ route('admin.providers.import', $provider->id) }}" data-once>
                    @csrf
                    <div class="table-wrap">
                        <table class="cards">
                            <thead><tr><th style="width: 40px"><span class="sr-only">{{ __('Select') }}</span></th><th>{{ __('Service') }}</th><th>{{ __('Type') }}</th><th>{{ __('Needs') }}</th><th>{{ __('Delivery') }}</th><th class="right">{{ __('Provider price') }}</th></tr></thead>
                            <tbody>
                                @foreach ($remote as $row)
                                    @php $localId = $imported[$row->remote_id] ?? null; @endphp
                                    <tr>
                                        <td>
                                            @if ($localId)
                                                <span class="pos" title="{{ __('Already imported') }}">✓</span>
                                            @else
                                                <input type="checkbox" name="remote_ids[]" value="{{ $row->remote_id }}" id="remote-{{ $row->id }}" @checked(in_array($row->remote_id, old('remote_ids', []), true))>
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
                                <select id="service_category_id" name="service_category_id" required>
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
                            <button type="submit" class="btn btn-primary">{{ __('Import selected services') }}</button>
                        </div>
                        <p class="help">{{ __('Your price = provider price + margin. Missing input fields are added to the category automatically. A free provider service (price 0) is imported with price 0; edit it to set your own price.') }}</p>
                    </div>
                </form>
            @endif
        </div>
    </div>
@endsection
