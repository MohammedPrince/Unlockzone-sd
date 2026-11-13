@extends('layouts.panel')

@php
    $searching = !empty($filters['q']) || !empty($filters['status']);
    // Opened from "Order history" in the menu: only that category's orders.
    $category = !empty($filters['category']) ? ($navCategories ?? collect())->firstWhere('id', (int) $filters['category']) : null;
    $heading = $category ? __(':category Orders', ['category' => $category->name]) : __('Server Orders');
    $keep = array_filter(['q' => $filters['q'] ?? null, 'status' => $filters['status'] ?? null, 'category' => $category?->id]);
@endphp

@section('title', $heading)
@section('page-title', $heading)

@section('content')
    <div class="card orders-card">
        <div class="orders-tools">
            <button type="button" class="btn btn-primary" data-toggle-target="#orders-search" aria-expanded="{{ $searching ? 'true' : 'false' }}" aria-controls="orders-search">
                @include('partials.icon', ['name' => 'search']) {{ __('Search') }}
            </button>
            <a class="btn btn-primary" href="{{ route('customer.requests.export', $keep) }}">
                @include('partials.icon', ['name' => 'download']) {{ __('Export') }}
            </a>
            <a class="btn btn-primary" href="{{ route('customer.services.index') }}">
                @include('partials.icon', ['name' => 'cart']) {{ __('New order') }}
            </a>
        </div>

        <form id="orders-search" class="filters" method="get" action="{{ route('customer.requests.index') }}" @unless ($searching) hidden @endunless>
            @if ($category) <input type="hidden" name="category" value="{{ $category->id }}"> @endif
            <div class="field grow">
                <label for="q">{{ __('Search') }}</label>
                <input type="search" id="q" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="{{ __('Reference or service name') }}">
            </div>
            <div class="field">
                <label for="status">{{ __('Status') }}</label>
                <select id="status" name="status">
                    <option value="">{{ __('All') }} ({{ number_format($counts['all']) }})</option>
                    @foreach ($statuses as $status)
                        <option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ __(ucfirst($status)) }} ({{ number_format($counts[$status]) }})</option>
                    @endforeach
                </select>
            </div>
            <div class="btns">
                <button class="btn btn-primary" type="submit">{{ __('Search') }}</button>
                @if ($searching) <a class="btn" href="{{ route('customer.requests.index') }}">{{ __('Clear') }}</a> @endif
            </div>
        </form>

        @if ($requests->isEmpty())
            <div class="empty"><strong>{{ __('No orders here') }}</strong>{{ $searching ? __('Nothing matches this status or search.') : __('Browse the services and place your first order.') }}</div>
        @else
            @include('customer.requests.table', ['requests' => $requests])
            {{ $requests->links() }}
        @endif
    </div>
@endsection
