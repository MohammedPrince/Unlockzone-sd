{{--
    Infinite scroll: the next 20 are fetched when this comes into view and appended to [data-infinite-items].
    The button is a normal link to the next page, so it also works without JavaScript.
    Needs: $paginator (LengthAwarePaginator).
--}}
@if ($paginator->hasMorePages())
    <div class="load-more" data-infinite-next="{{ $paginator->nextPageUrl() }}">
        <a class="btn" href="{{ $paginator->nextPageUrl() }}" data-infinite-button>{{ __('Show more') }}</a>
        <span class="muted">{{ __('Showing :shown of :total', ['shown' => $paginator->lastItem(), 'total' => $paginator->total()]) }}</span>
    </div>
@elseif ($paginator->total() > $paginator->perPage())
    <div class="load-more is-done"><span class="muted">{{ __('All :total shown', ['total' => $paginator->total()]) }}</span></div>
@endif
