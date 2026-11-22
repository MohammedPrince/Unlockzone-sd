{{-- A list of services for visitors. Expects $list (services with slug, price, delivery_time, is_instant, created_at). --}}
<ul class="pub-list">
    @foreach ($list as $item)
        <li>
            <a class="pub-item" href="{{ \App\Support\Seo::serviceUrl($item) }}">
                <span class="pub-name" dir="auto">{{ \App\Support\Seo::name($item->name) }}
                    @if ($item->isNew()) <span class="new-tag">{{ __('New') }}</span> @endif
                    @if ($item->is_instant) <span class="tag-instant">{{ __('Instant') }}</span> @endif
                    @if ($item->delivery_time) <small class="muted">{{ \App\Support\Seo::name($item->delivery_time) }}</small> @endif
                </span>
                @if (\App\Support\Seo::showPrices()) <b class="num pub-price">{{ price($item->price) }}</b> @endif
            </a>
        </li>
    @endforeach
</ul>
