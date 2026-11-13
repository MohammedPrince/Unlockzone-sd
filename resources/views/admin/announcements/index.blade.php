@extends('layouts.panel')

@php
    // A stale or invalid edit value should render the add form, never trigger property warnings.
    $editing = $editing instanceof \App\Models\Announcement ? $editing : null;
    $colorNames = ['default' => 'White', 'blue' => 'Blue', 'green' => 'Green', 'orange' => 'Orange', 'violet' => 'Violet', 'red' => 'Red'];
    $shown = $announcements->where('is_active', true);
@endphp

@section('title', __('Announcement bar'))
@section('eyebrow', __('Platform'))
@section('page-title', __('Announcement bar'))
@section('page-description', __('The scrolling bar at the top of every customer page: news, offers and new services.'))

@section('content')
    @include('partials.page-guide', [
        'key' => 'admin-announcements',
        'title' => 'How the announcement bar works',
        'intro' => 'Customers see one line that scrolls across the top of their pages. You decide what is in it.',
        'steps' => [
            '<b>Add</b> a message below. It appears in the bar straight away.',
            '<b>Show / Hide</b> keeps a message for later without deleting it.',
            '<b>New services</b> can be listed in the bar automatically while they carry the "New" tag.',
            '<b>Turn the bar off</b> with the first switch to hide it completely.',
        ],
        'note' => 'Write the Arabic text too if you want customers who use Arabic to read the message in Arabic.',
    ])

    <div class="stack">
        <form method="post" action="{{ route('admin.announcements.bar') }}" class="card">
            @csrf
            <div class="card-head"><h2>{{ __('Bar switches') }}</h2></div>
            <div class="card-body switch-row">
                <label class="check">
                    <input type="checkbox" name="ticker_enabled" value="1" @checked(setting('ticker_enabled') === '1')>
                    <span>{{ __('Show the announcement bar to customers') }}</span>
                </label>
                <label class="check">
                    <input type="checkbox" name="ticker_new_services" value="1" @checked(setting('ticker_new_services') === '1')>
                    <span>{{ __('List new services in the bar automatically') }}</span>
                </label>
                <div style="min-width: 180px">
                    <label for="ticker_speed">{{ __('Scroll speed') }}</label>
                    <select id="ticker_speed" name="ticker_speed">
                        @foreach (['very-slow' => __('Very slow'), 'slow' => __('Slow'), 'normal' => __('Normal'), 'fast' => __('Fast')] as $speed => $label)
                            <option value="{{ $speed }}" @selected(old('ticker_speed', setting('ticker_speed', 'normal')) === $speed)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="btn btn-primary" style="margin-inline-start: auto">{{ __('Save') }}</button>
            </div>
        </form>

        @if ($shown->isNotEmpty() && setting('ticker_enabled') === '1')
            <div class="card">
                <div class="card-head"><h2>{{ __('Preview') }}</h2><p>{{ __('This is what customers see.') }}</p></div>
                @include('partials.ticker', ['tickerItems' => $shown, 'tickerServices' => collect()])
            </div>
        @endif

        <form method="post" action="{{ $editing ? route('admin.announcements.update', $editing->id) : route('admin.announcements.store') }}" class="card" data-once id="announcement-form">
            @csrf
            @if ($editing) @method('put') @endif
            <div class="card-head"><h2>{{ $editing ? __('Edit announcement') : __('Add announcement') }}</h2></div>
            <div class="card-body form-grid">
                <div class="full">
                    <label for="text">{{ __('Text') }}</label>
                    <input type="text" id="text" name="text" value="{{ old('text', $editing->text ?? '') }}" required maxlength="200" placeholder="{{ __('e.g. Added credit for all box tools') }}">
                    @error('text') <p class="error-text">{{ $message }}</p> @enderror
                </div>
                <div class="full">
                    <label for="text_ar">{{ __('Arabic text') }} <span class="muted">({{ __('optional') }})</span></label>
                    <input type="text" id="text_ar" name="text_ar" value="{{ old('text_ar', $editing->text_ar ?? '') }}" maxlength="200" dir="rtl" lang="ar">
                    <p class="help">{{ __('Shown instead of the text above when the customer uses Arabic.') }}</p>
                </div>
                <div>
                    <label for="icon">{{ __('Icon') }} <span class="muted">({{ __('optional') }})</span></label>
                    <input type="text" id="icon" name="icon" value="{{ old('icon', $editing->icon ?? '') }}" maxlength="16" placeholder="⚡ 🎁 🛒 🌐">
                    <p class="help">{{ __('An emoji shown before the text.') }}</p>
                </div>
                <div>
                    <label for="color">{{ __('Colour') }}</label>
                    <select id="color" name="color" required>
                        @foreach ($colors as $color)
                            <option value="{{ $color }}" @selected(old('color', $editing->color ?? 'default') === $color)>{{ __($colorNames[$color]) }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="link">{{ __('Link') }} <span class="muted">({{ __('optional') }})</span></label>
                    <input type="text" id="link" name="link" value="{{ old('link', $editing->link ?? '') }}" maxlength="255" placeholder="https://" dir="ltr">
                    <p class="help">{{ __('Customers who click the message go to this address.') }}</p>
                    @error('link') <p class="error-text">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="sort_order">{{ __('Order') }}</label>
                    <input type="number" id="sort_order" name="sort_order" value="{{ old('sort_order', $editing->sort_order ?? $announcements->count() + 1) }}" min="0" max="9999">
                    <p class="help">{{ __('Lower numbers come first.') }}</p>
                </div>
                <div class="full">
                    <label class="check">
                        <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $editing->is_active ?? true))>
                        <span>{{ __('Show this message in the bar') }}</span>
                    </label>
                </div>
                <div class="full form-actions">
                    @if ($editing) <a class="btn" href="{{ route('admin.announcements.index') }}">{{ __('Cancel') }}</a> @endif
                    <button type="submit" class="btn btn-primary">{{ $editing ? __('Save changes') : __('Add announcement') }}</button>
                </div>
            </div>
        </form>

        <div class="card">
            <div class="card-head"><h2>{{ __('Messages') }}</h2></div>
            @if ($announcements->isEmpty())
                <div class="empty"><strong>{{ __('No announcements yet') }}</strong>{{ __('Add your first message with the form above.') }}</div>
            @else
                <div class="table-wrap">
                    <table class="cards">
                        <thead><tr><th>{{ __('Message') }}</th><th>{{ __('Colour') }}</th><th class="right">{{ __('Order') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
                        <tbody>
                            @foreach ($announcements as $item)
                                <tr>
                                    <td class="primary">
                                        <span class="tk-{{ $item->color }}">{{ $item->icon }} {{ $item->text }}</span>
                                        @if ($item->text_ar) <span class="sub" dir="rtl" lang="ar">{{ $item->text_ar }}</span> @endif
                                        @if ($item->link) <span class="sub mono">{{ $item->link }}</span> @endif
                                    </td>
                                    <td data-label="{{ __('Colour') }}">{{ __($colorNames[$item->color] ?? 'White') }}</td>
                                    <td data-label="{{ __('Order') }}" class="right num">{{ $item->sort_order }}</td>
                                    <td data-label="{{ __('Status') }}"><span class="badge badge-{{ $item->is_active ? 'active' : 'inactive' }}">{{ $item->is_active ? __('Shown') : __('Hidden') }}</span></td>
                                    <td>
                                        <div class="row-actions">
                                            <a class="btn btn-sm" href="{{ route('admin.announcements.index', ['edit' => $item->id]) }}#announcement-form">{{ __('Edit') }}</a>
                                            <form method="post" action="{{ route('admin.announcements.toggle', $item->id) }}">
                                                @csrf
                                                <button class="btn btn-sm" type="submit">{{ $item->is_active ? __('Hide') : __('Show') }}</button>
                                            </form>
                                            <form method="post" action="{{ route('admin.announcements.destroy', $item->id) }}" data-confirm="{{ __('Delete this announcement? This cannot be undone.') }}">
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
            @endif
        </div>
    </div>
@endsection
