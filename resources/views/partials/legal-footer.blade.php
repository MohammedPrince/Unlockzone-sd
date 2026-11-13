{{-- Site footer with the legal links. Used on the home page and the Terms / Privacy pages. --}}
<footer class="home-footer">
    <div class="home-wrap">
        <nav class="legal-links" aria-label="{{ __('Legal') }}">
            <a href="{{ route('legal.terms') }}">{{ __('Terms of Service') }}</a>
            <span aria-hidden="true">·</span>
            <a href="{{ route('legal.privacy') }}">{{ __('Privacy Policy') }}</a>
        </nav>
        <p>{{ __('Connect to our API · Enjoy stability · Grow your business') }}</p>
        <p class="muted">© {{ now()->year }} {{ setting('site_name') }}</p>
    </div>
</footer>
