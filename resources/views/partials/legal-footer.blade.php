{{-- Site footer with the legal links. Used on the home page and the Terms / Privacy pages. --}}
<footer class="home-footer">
    <div class="home-wrap">
        <nav class="legal-links" aria-label="{{ __('Legal') }}">
            <a href="{{ route('legal.terms') }}">{{ __('Terms of Service') }}</a>
            <span aria-hidden="true">·</span>
            <a href="{{ route('legal.privacy') }}">{{ __('Privacy Policy') }}</a>
        </nav>
        @php
            try {
                $visitorCounts = app(\App\Repositories\VisitRepository::class)->footerCounts();
            } catch (\Throwable $e) {
                $visitorCounts = null; // The counter must never break a page.
            }
        @endphp
        @if ($visitorCounts)
            <div class="visitor-counter" aria-label="{{ __('Visitors') }}">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg>
                <span>{{ __('Visitors today') }}: <strong>{{ bidi(number_format($visitorCounts['today'])) }}</strong></span>
                <span aria-hidden="true">·</span>
                <span>{{ __('Total visitors') }}: <strong>{{ bidi(number_format($visitorCounts['total'])) }}</strong></span>
            </div>
        @endif
        <p>{{ __('Connect to our API · Enjoy stability · Grow your business') }}</p>
        <p class="muted">© {{ now()->year }} {{ setting('site_name') }}</p>
    </div>
</footer>
