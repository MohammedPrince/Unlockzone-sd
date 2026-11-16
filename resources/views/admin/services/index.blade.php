@extends('layouts.panel')

@section('title', __('Services'))
@section('eyebrow', __('Service management'))
@section('page-title', __('Services'))
@section('page-description', __('What customers can order, what each one costs, and whether it is on sale.'))

@section('page-actions')
    <a class="btn btn-primary" href="{{ route('admin.services.create') }}">@include('partials.icon', ['name' => 'plus']) {{ __('New service') }}</a>
@endsection

@section('content')
    @include('partials.page-guide', [
        'key' => 'admin-services',
        'title' => 'How services work',
        'intro' => 'A service is one thing a customer can buy with credit. It belongs to a category and asks for some of that category\'s input fields.',
        'steps' => [
            '<b>Price</b>: charged from the customer\'s balance the moment they order. The price is always read from here, never from the customer\'s browser.',
            '<b>Active / inactive</b>: inactive services disappear from the customer catalog but keep their history.',
            '<b>Changing a price</b> only affects new orders. Existing requests keep the amount they were charged.',
        ],
        'note' => 'A service with request history cannot be deleted, only deactivated.',
    ])

    <div class="card">
        <form class="filters" method="get" action="{{ route('admin.services.index') }}">
            <div class="field grow">
                <label for="q">{{ __('Search') }}</label>
                <input type="search" id="q" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="{{ __('Service name') }}">
            </div>
            <div class="field">
                <label for="category">{{ __('Category') }}</label>
                <select id="category" name="category">
                    <option value="">{{ __('All categories') }}</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}" @selected((string) ($filters['category'] ?? '') === (string) $category->id)>{{ $category->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label for="status">{{ __('Status') }}</label>
                <select id="status" name="status">
                    <option value="">{{ __('Any status') }}</option>
                    <option value="active" @selected(($filters['status'] ?? '') === 'active')>{{ __('Active') }}</option>
                    <option value="inactive" @selected(($filters['status'] ?? '') === 'inactive')>{{ __('Inactive') }}</option>
                </select>
            </div>
            <div class="btns">
                <button class="btn btn-primary" type="submit">{{ __('Filter') }}</button>
                <a class="btn" href="{{ route('admin.services.index') }}">{{ __('Reset') }}</a>
            </div>
        </form>

        @if ($services->isEmpty())
            <div class="empty"><strong>{{ __('No services found') }}</strong>{{ __('Create a service, or change the filters.') }}</div>
        @else
            <form id="bulk-move" class="bulk-bar" method="post" action="{{ route('admin.services.move') }}" data-once data-bulk
                data-confirm="{{ __('Move the ticked services to the chosen category?') }}">
                @csrf
                <label style="display: inline-flex; align-items: center; gap: 8px; margin: 0; cursor: pointer">
                    <input type="checkbox" data-check-all> {{ __('Select all') }}
                </label>
                <span class="bulk-count" data-bulk-count data-template="{{ __(':count selected') }}">{{ __(':count selected', ['count' => 0]) }}</span>
                <label for="target_category_id" class="sr-only">{{ __('Move to category') }}</label>
                <select id="target_category_id" name="target_category_id" required>
                    <option value="">{{ __('Move selected to…') }}</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}">{{ $category->name }}</option>
                    @endforeach
                </select>
                <button class="btn btn-sm btn-primary" type="submit" data-bulk-submit disabled>{{ __('Move') }}</button>
                @error('ids') <span class="error-text">{{ $message }}</span> @enderror
                @error('target_category_id') <span class="error-text">{{ $message }}</span> @enderror
            </form>

            <div class="table-wrap">
                <table class="cards">
                    <thead><tr><th style="width: 40px"><input type="checkbox" data-check-all aria-label="{{ __('Select all services on this page') }}"></th><th>{{ __('Service') }}</th><th>{{ __('Category') }}</th><th class="right">{{ __('Price') }}</th><th>{{ __('Delivery') }}</th><th class="right">{{ __('Requests') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
                    <tbody>
                        @foreach ($services as $service)
                            <tr>
                                <td class="check-cell"><input type="checkbox" name="ids[]" value="{{ $service->id }}" form="bulk-move" data-check-item aria-label="{{ __('Select :name', ['name' => $service->name]) }}"></td>
                                <td class="primary">
                                    <a href="{{ route('admin.services.edit', $service->id) }}">{{ $service->name }}</a>
                                    @if ($service->isNew()) <span class="new-tag">{{ __('New') }}</span> @endif
                                    @if ($service->usesProvider()) <span class="tag api-tag" title="{{ $service->provider->name ?? '' }}">{{ __('API') }}</span> @endif
                                    <span class="sub">{{ plain_text($service->description, 70) }}</span>
                                </td>
                                <td data-label="{{ __('Category') }}">
                                    {{ $service->category->name ?? '—' }}
                                    @if (($service->category->status ?? '') === 'inactive') <span class="sub">{{ __('Category inactive') }}</span> @endif
                                </td>
                                <td data-label="{{ __('Price') }}" class="right num">{{ price($service->price) }}</td>
                                <td data-label="{{ __('Delivery') }}">{{ $service->delivery_time ?: '—' }}</td>
                                <td data-label="{{ __('Requests') }}" class="right num"><a href="{{ route('admin.requests.index', ['service_id' => $service->id]) }}">{{ number_format($service->requests_count) }}</a></td>
                                <td data-label="{{ __('Status') }}"><span class="badge badge-{{ $service->status }}">{{ __(ucfirst($service->status)) }}</span></td>
                                <td>
                                    <div class="row-actions">
                                        <a class="btn btn-sm" href="{{ route('admin.services.edit', $service->id) }}">{{ __('Edit') }}</a>
                                        <form method="post" action="{{ route('admin.services.toggle', $service->id) }}">
                                            @csrf
                                            <button class="btn btn-sm" type="submit">{{ $service->status === 'active' ? __('Deactivate') : __('Activate') }}</button>
                                        </form>
                                        <form method="post" action="{{ route('admin.services.destroy', $service->id) }}" data-confirm="{{ __('Delete the service ":name"? This cannot be undone.', ['name' => $service->name]) }}">
                                            @csrf @method('delete')
                                            <button class="btn btn-sm btn-danger" type="submit">{{ __('Delete') }}</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            {{ $services->links() }}
        @endif
    </div>
@endsection
