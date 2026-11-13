{{--
    The scrolling bar at the top of the customer site.
    $tickerItems: the administrator's announcements. $tickerServices: the newest services.
    The list is printed twice so the scroll loops without a gap; the copy is hidden from screen readers.
--}}
@php
    $items = collect($tickerItems ?? []);
    $newServices = collect($tickerServices ?? []);
    $speedFactor = ['very-slow' => 2.25, 'slow' => 1.5, 'normal' => 1, 'fast' => 0.65][setting('ticker_speed', 'normal')] ?? 1;
    $tickerDuration = max(18, ($items->count() + $newServices->count()) * 7) * $speedFactor;
@endphp
@if ($items->isNotEmpty() || $newServices->isNotEmpty())
    <div class="ticker no-print" role="region" aria-label="{{ __('Announcements') }}">
        <div class="ticker-track" style="--ticker-time: {{ $tickerDuration }}s">
            @foreach ([false, true] as $copy)
                <div class="ticker-group" @if ($copy) aria-hidden="true" @endif>
                    @foreach ($items as $item)
                        @if ($item->link)
                            <a class="ticker-item tk-{{ $item->color }}" href="{{ $item->link }}" @if ($copy) tabindex="-1" @endif>@if ($item->icon)<span class="ticker-icon">{{ $item->icon }}</span>@endif{{ $item->localizedText() }}</a>
                        @else
                            <span class="ticker-item tk-{{ $item->color }}">@if ($item->icon)<span class="ticker-icon">{{ $item->icon }}</span>@endif{{ $item->localizedText() }}</span>
                        @endif
                    @endforeach
                    @foreach ($newServices as $service)
                        <a class="ticker-item tk-green" href="{{ route('customer.services.show', $service->id) }}" @if ($copy) tabindex="-1" @endif><span class="new-tag">{{ __('New') }}</span>{{ $service->name }}<span class="ticker-price num">{{ price($service->price) }}</span></a>
                    @endforeach
                </div>
            @endforeach
        </div>
    </div>
@endif
