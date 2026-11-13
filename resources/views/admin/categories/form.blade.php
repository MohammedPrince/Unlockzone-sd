@extends('layouts.panel')

@php
    $editing = $category !== null;
    // After a validation error use what was typed; otherwise the saved fields.
    $rows = old('fields', $editing ? $category->fields->map->only(['id', 'label', 'input_type', 'placeholder', 'is_required'])->all() : []);
@endphp

@section('title', $editing ? __('Edit category') : __('New category'))
@section('eyebrow', __('Service management'))
@section('page-title', $editing ? __('Edit') . ' ' . $category->name : __('New category'))
@section('page-description', __('Name the category and define the input fields its services can ask customers for.'))

@section('page-actions')
    <a class="btn" href="{{ route('admin.categories.index') }}">@include('partials.icon', ['name' => 'back']) {{ __('Categories') }}</a>
@endsection

@section('content')
    <form method="post" action="{{ $editing ? route('admin.categories.update', $category->id) : route('admin.categories.store') }}" class="stack" data-once>
        @csrf
        @if ($editing) @method('put') @endif

        <div class="card">
            <div class="card-head"><h2>{{ __('Category') }}</h2></div>
            <div class="card-body form-grid">
                <div>
                    <label for="name">{{ __('Name') }}</label>
                    <input type="text" id="name" name="name" value="{{ old('name', $category->name ?? '') }}" required maxlength="150" placeholder="{{ __('e.g. EMI Services') }}">
                    @error('name') <p class="error-text">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="status">{{ __('Status') }}</label>
                    <select id="status" name="status" required>
                        <option value="active" @selected(old('status', $category->status ?? 'active') === 'active')>{{ __('Active (visible to customers)') }}</option>
                        <option value="inactive" @selected(old('status', $category->status ?? '') === 'inactive')>{{ __('Inactive (hidden)') }}</option>
                    </select>
                </div>
                <div class="full">
                    <label for="description">{{ __('Description') }} <span class="muted">{{ __('(optional)') }}</span></label>
                    <textarea id="description" name="description" maxlength="2000">{{ old('description', $category->description ?? '') }}</textarea>
                </div>
                <div>
                    <label for="sort_order">{{ __('Display order') }}</label>
                    <input type="number" id="sort_order" name="sort_order" value="{{ old('sort_order', $category->sort_order ?? 0) }}" min="0" max="9999">
                    <p class="help">{{ __('Lower numbers are listed first.') }}</p>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-head">
                <div>
                    <h2>{{ __('Input fields') }}</h2>
                    <p>{{ __('What a customer may be asked for when ordering from this category. Each service picks the ones it needs.') }}</p>
                </div>
                <button type="button" class="btn btn-sm" data-add-field>@include('partials.icon', ['name' => 'plus']) {{ __('Add field') }}</button>
            </div>
            <div class="card-body">
                <div id="field-rows">@foreach ($rows as $i => $field)@include('admin.categories.field-row', ['i' => $i, 'field' => $field])@endforeach</div>
                <p class="help">{{ __('Removing a field also removes it from the services that use it. Past requests keep the values they were submitted with.') }}</p>
                @error('fields.*') <p class="error-text">{{ $message }}</p> @enderror
            </div>
        </div>

        <div class="form-actions">
            <a class="btn" href="{{ route('admin.categories.index') }}">{{ __('Cancel') }}</a>
            <button type="submit" class="btn btn-primary">{{ $editing ? __('Save changes') : __('Create category') }}</button>
        </div>
    </form>

    <template id="field-row-template">@include('admin.categories.field-row', ['i' => '__i__', 'field' => []])</template>
@endsection
