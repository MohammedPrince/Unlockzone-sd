@extends('layouts.panel')

@php
    $editing = $service !== null;
    $currentCategory = (string) old('service_category_id', $service->service_category_id ?? $preselect ?? '');
    $checked = array_map('intval', old('field_ids', $editing ? $service->fieldIds() : []));

    // 5.00 is shown as 5; 5.50 stays 5.50.
    $priceValue = old('price', $editing ? str_replace(',', '', amount($service->price)) : '');

    // Words the description editor shows, so its toolbar follows the chosen language.
    $editorText = collect([
        'Paragraph', 'Heading 2', 'Heading 3', 'Heading 4', 'Quote', 'Code block',
        'Bold', 'Italic', 'Underline', 'Strikethrough', 'Bulleted list', 'Numbered list',
        'Align left', 'Align center', 'Align right', 'Insert link', 'Remove link', 'Insert image', 'Insert table',
        'Add row', 'Add column', 'Delete row', 'Delete column', 'Delete table', 'Horizontal line',
        'Clear formatting', 'Undo', 'Redo', 'HTML source', 'Visual editor',
        'Link address (https://...)', 'Upload a picture from this computer? Press Cancel to paste an image address instead.',
        'Image address (https://...)', 'Uploading image...', 'Image uploaded.', 'Upload failed', 'How many rows?', 'How many columns?',
        'Column', 'Editing HTML directly. Switch back to see the result.', 'Formatting, tables and images are kept. Scripts are removed when saved.',
    ])->mapWithKeys(fn ($t) => [$t => __($t)]);
@endphp

@section('title', $editing ? __('Edit service') : __('New service'))
@section('eyebrow', __('Service management'))
@section('page-title', $editing ? __('Edit') . ' ' . $service->name : __('New service'))
@section('page-description', __('Set the category, price and the information customers must provide.'))

@section('page-actions')
    <a class="btn" href="{{ route('admin.services.index') }}">@include('partials.icon', ['name' => 'back']) {{ __('Services') }}</a>
@endsection

@section('content')
    @if ($categories->isEmpty())
        <div class="card"><div class="empty"><strong>{{ __('Create a category first') }}</strong>{{ __('Services live inside categories.') }} <a href="{{ route('admin.categories.create') }}">{{ __('New category') }}</a></div></div>
    @else
        <form method="post" action="{{ $editing ? route('admin.services.update', $service->id) : route('admin.services.store') }}" class="stack" data-once>
            @csrf
            @if ($editing) @method('put') @endif

            <div class="card">
                <div class="card-head"><h2>{{ __('Service') }}</h2></div>
                <div class="card-body form-grid">
                    <div>
                        <label for="name">{{ __('Name') }}</label>
                        <input type="text" id="name" name="name" value="{{ old('name', $service->name ?? '') }}" required maxlength="150" placeholder="{{ __('e.g. Haafedk test 1') }}">
                        @error('name') <p class="error-text">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="service-category">{{ __('Category') }}</label>
                        <select id="service-category" name="service_category_id" required>
                            <option value="">{{ __('Select a category') }}</option>
                            @foreach ($categories as $category)
                                <option value="{{ $category->id }}" @selected($currentCategory === (string) $category->id)>{{ $category->name }}{{ $category->status === 'inactive' ? ' (' . __('inactive') . ')' : '' }}</option>
                            @endforeach
                        </select>
                        @error('service_category_id') <p class="error-text">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="price">{{ __('Price') }} ($)</label>
                        <div class="input-unit">
                            <input type="number" id="price" name="price" value="{{ $priceValue }}" step="0.01" min="0" max="999999.99" required inputmode="decimal" placeholder="5" dir="ltr">
                            <span aria-hidden="true">$</span>
                        </div>
                        <p class="help">{{ __("Deducted from the customer's balance when they order.") }}</p>
                        @error('price') <p class="error-text">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="delivery_time">{{ __('Delivery time') }} <span class="muted">({{ __('optional') }})</span></label>
                        <input type="text" id="delivery_time" name="delivery_time" value="{{ old('delivery_time', $service->delivery_time ?? '') }}" maxlength="60" placeholder="{{ __('e.g. 1-24 hours') }}">
                        <input type="hidden" name="is_instant" value="0">
                        <label class="check" style="margin-top: 10px">
                            <input type="checkbox" name="is_instant" value="1" @checked(old('is_instant', $service->is_instant ?? false))>
                            <span>{{ __('Instant service') }} <span class="tag-instant">{{ __('Instant') }}</span><br><span class="muted">{{ __('Shows a yellow "Instant" tag next to the price.') }}</span></span>
                        </label>
                    </div>
                    <div>
                        <label for="status">{{ __('Status') }}</label>
                        <select id="status" name="status" required>
                            <option value="active" @selected(old('status', $service->status ?? 'active') === 'active')>{{ __('Active (on sale)') }}</option>
                            <option value="inactive" @selected(old('status', $service->status ?? '') === 'inactive')>{{ __('Inactive (hidden)') }}</option>
                        </select>
                    </div>
                    <div class="full">
                        <label for="description">{{ __('Description') }} <span class="muted">({{ __('optional') }})</span></label>
                        {{-- Works as a plain HTML box without JavaScript; app.js turns it into the visual editor. --}}
                        <textarea id="description" name="description" maxlength="30000" rows="10"
                                  data-editor
                                  data-upload-url="{{ route('admin.uploads.image') }}"
                                  data-text='@json($editorText)'
                                  placeholder="{{ __('What the customer gets, requirements, notes...') }}">{{ old('description', $service->description ?? '') }}</textarea>
                        @error('description') <p class="error-text">{{ $message }}</p> @enderror
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-head">
                    <div>
                        <h2>{{ __('Required input fields') }}</h2>
                        <p>{{ __('Tick what the customer must enter when ordering this service. The list comes from the chosen category.') }}</p>
                    </div>
                </div>
                <div class="card-body">
                    @foreach ($categories as $category)
                        <div class="pick" data-category-fields="{{ $category->id }}" hidden>
                            @foreach ($category->fields as $field)
                                <label>
                                    <input type="checkbox" name="field_ids[]" value="{{ $field->id }}" @checked(in_array($field->id, $checked, true))>
                                    {{ $field->label }} <span class="muted">· {{ __($field->typeLabel()) }}</span>
                                </label>
                            @endforeach
                        </div>
                    @endforeach
                    <p class="muted" id="no-fields" hidden>{{ __("This category has no input fields yet. Add them on the category's edit page, or leave the service without inputs.") }}</p>
                </div>
            </div>

            <div class="card">
                <div class="card-head">
                    <div>
                        <h2>{{ __('Automatic processing through a provider') }} <span class="muted">({{ __('optional') }})</span></h2>
                        <p>{{ __('Link this service to a connected provider and its orders are forwarded there automatically. Leave empty to handle orders yourself.') }}</p>
                    </div>
                    <a class="btn btn-sm" href="{{ route('admin.providers.index') }}">{{ __('API providers') }}</a>
                </div>
                <div class="card-body form-grid">
                    <div>
                        <label for="api_provider_id">{{ __('Provider') }}</label>
                        <select id="api_provider_id" name="api_provider_id">
                            <option value="">{{ __('None (manual handling)') }}</option>
                            @foreach ($providers as $provider)
                                <option value="{{ $provider->id }}" @selected((string) old('api_provider_id', $service->api_provider_id ?? '') === (string) $provider->id)>{{ $provider->name }}{{ $provider->status === 'inactive' ? ' (' . __('inactive') . ')' : '' }}</option>
                            @endforeach
                        </select>
                        @error('api_provider_id') <p class="error-text">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="remote_service_id">{{ __('Service ID at the provider') }}</label>
                        <input type="text" id="remote_service_id" name="remote_service_id" value="{{ old('remote_service_id', $service->remote_service_id ?? '') }}" maxlength="40" dir="ltr" placeholder="{{ __('e.g. 134') }}">
                        <p class="help">{{ __("The ID column in the provider's price list. Importing from the price list fills this in for you.") }}</p>
                        @error('remote_service_id') <p class="error-text">{{ $message }}</p> @enderror
                    </div>
                    <p class="help full">{{ __('The IMEI or serial number field is sent as the IMEI. Other fields are sent as custom fields, so name them exactly like the provider does.') }}</p>
                </div>
            </div>

            <div class="form-actions">
                <a class="btn" href="{{ route('admin.services.index') }}">{{ __('Cancel') }}</a>
                <button type="submit" class="btn btn-primary">{{ $editing ? __('Save changes') : __('Create service') }}</button>
            </div>
        </form>
    @endif
@endsection
