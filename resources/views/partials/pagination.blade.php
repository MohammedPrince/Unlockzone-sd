@if ($paginator->total() > 0)
    <nav class="pagination" aria-label="{{ __('Pages') }}">
        <span>{{ __('Showing :from–:to of :total', ['from' => $paginator->firstItem(), 'to' => $paginator->lastItem(), 'total' => number_format($paginator->total())]) }}</span>

        @if ($paginator->hasPages())
            <div class="pages">
                @if ($paginator->onFirstPage())
                    <span class="page off" aria-hidden="true">‹</span>
                @else
                    <a href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="{{ __('Previous page') }}">‹</a>
                @endif

                @foreach ($elements as $element)
                    @if (is_string($element))
                        <span class="page off">…</span>
                    @endif

                    @if (is_array($element))
                        @foreach ($element as $page => $url)
                            @if ($page == $paginator->currentPage())
                                <span class="page current" aria-current="page">{{ $page }}</span>
                            @else
                                <a href="{{ $url }}">{{ $page }}</a>
                            @endif
                        @endforeach
                    @endif
                @endforeach

                @if ($paginator->hasMorePages())
                    <a href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="{{ __('Next page') }}">›</a>
                @else
                    <span class="page off" aria-hidden="true">›</span>
                @endif
            </div>
        @endif
    </nav>
@endif
