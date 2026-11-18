@extends('layouts.panel')

@php
    $importable = collect($rows)->reject(fn ($r) => $r['exists'])->count();
@endphp

@section('title', __('Import services'))
@section('eyebrow', __('Settings'))
@section('page-title', __('Import services'))
@section('page-description', __('Check the services found in the file, choose where they go and the price margin, then import the ticked ones.'))

@section('page-actions')
    <form method="post" action="{{ route('admin.settings.import.cancel') }}" class="inline">
        @csrf @method('delete')
        <button class="btn" type="submit">@include('partials.icon', ['name' => 'back']) {{ __('Cancel') }}</button>
    </form>
@endsection

@section('content')
    <div class="stack">
        <div class="stats">
            <div class="stat accent">
                <div class="label">{{ __('File') }}</div>
                <div class="value" style="font-size: 1rem; word-break: break-all">{{ $file }}</div>
            </div>
            <div class="stat">
                <div class="label">{{ __('Services found') }}</div>
                <div class="value">{{ count($rows) }}</div>
            </div>
            <div class="stat">
                <div class="label">{{ __('Can be imported') }}</div>
                <div class="value">{{ $importable }}</div>
                <div class="hint">{{ __('Already existing services are not imported again.') }}</div>
            </div>
        </div>

        @if ($skipped)
            <div class="alert alert-error" role="alert">
                {{ __('These lines were not understood and are left out:') }}
                <ul>@foreach ($skipped as $line) <li>{{ $line }}</li> @endforeach</ul>
            </div>
        @endif

        <div class="card">
            <form class="filters" method="get" action="{{ route('admin.settings.import.show') }}">
                <div class="field grow">
                    <label for="category">{{ __('Import into category') }}</label>
                    <select id="category" name="category" data-autosubmit>
                        <option value="">{{ __('Category from the first line of the file (created if missing)') }}</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}" @selected($categoryId === $category->id)>{{ $category->name }}</option>
                        @endforeach
                    </select>
                </div>
            </form>

            <form method="post" action="{{ route('admin.settings.import.store') }}" data-once>
                @csrf
                <input type="hidden" name="service_category_id" value="{{ $categoryId }}">

                <div class="table-wrap">
                    <table class="cards">
                        <thead><tr>
                            <th style="width: 40px"><span class="sr-only">{{ __('Select') }}</span></th>
                            <th>{{ __('Service') }}</th>
                            <th>{{ __('Customer enters') }}</th>
                            <th>{{ __('Delivery') }}</th>
                            <th class="right">{{ __('File price') }}</th>
                        </tr></thead>
                        <tbody>
                            @foreach ($rows as $i => $row)
                                <tr>
                                    <td>
                                        @if ($row['exists'])
                                            <span class="pos" title="{{ __('Already exists') }}">✓</span>
                                        @else
                                            <input type="checkbox" name="rows[]" value="{{ $i }}" id="row-{{ $i }}"
                                                @checked(old('rows') ? in_array((string) $i, old('rows'), true) : !$row['repeat'])>
                                        @endif
                                    </td>
                                    <td class="primary">
                                        <label for="row-{{ $i }}" style="margin: 0; font-size: inherit; font-weight: 600; color: var(--text)">{{ $row['name'] }}</label>
                                        <span class="sub">
                                            {{ $categoryId ? $categories->firstWhere('id', $categoryId)?->name : ($row['category'] ?? __('No category')) }}
                                            @if ($row['exists']) · {{ __('Already exists') }} @elseif ($row['repeat']) · {{ __('Same name appears earlier in the file') }} @endif
                                        </span>
                                    </td>
                                    <td data-label="{{ __('Customer enters') }}"><span class="tag">{{ \App\Services\ServiceImportService::fieldLabel($row['field']) }}</span></td>
                                    <td data-label="{{ __('Delivery') }}">
                                        @if ($row['instant']) <span class="tag-instant">{{ __('Instant') }}</span> @else {{ $row['delivery'] ?: '—' }} @endif
                                    </td>
                                    <td data-label="{{ __('File price') }}" class="right num">{{ amount($row['price']) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="sticky-actions">
                    <div class="inline-form">
                        <div class="field">
                            <label for="margin">{{ __('Profit margin') }} (%)</label>
                            <input type="number" id="margin" name="margin" value="{{ old('margin', 0) }}" min="0" max="1000" step="0.01" required dir="ltr">
                        </div>
                        <div class="field">
                            <label for="import-status">{{ __('Import as') }}</label>
                            <select id="import-status" name="status" required>
                                <option value="active">{{ __('Active (on sale)') }}</option>
                                <option value="inactive" @selected(old('status') === 'inactive')>{{ __('Inactive (hidden)') }}</option>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary" @disabled($importable === 0)>{{ __('Import selected services') }}</button>
                    </div>
                    <p class="help">{{ __('Your price = file price + margin. The input field the customer fills in (IMEI, SN or SN / IMEI) is guessed from the service name and added to the category when missing; you can change it later by editing the service.') }}</p>
                </div>
            </form>
        </div>
    </div>
@endsection
