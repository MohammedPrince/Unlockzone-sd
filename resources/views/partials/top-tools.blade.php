{{-- Language switch (English / العربية) and the light/dark theme button. Used in the top bar and on the sign-in pages. --}}
<nav class="lang-switch" aria-label="{{ __('Language') }}">
    <a href="{{ route('locale', 'en') }}" lang="en" @class(['active' => app()->getLocale() === 'en']) @if (app()->getLocale() === 'en') aria-current="true" @endif>EN</a>
    <a href="{{ route('locale', 'ar') }}" lang="ar" @class(['active' => app()->getLocale() === 'ar']) @if (app()->getLocale() === 'ar') aria-current="true" @endif>عربي</a>
</nav>
<button type="button" class="btn theme-btn" data-theme-toggle
        title="{{ __('Switch light / dark') }}" aria-label="{{ __('Switch light / dark') }}">
    @include('partials.icon', ['name' => 'sun', 'class' => 'for-dark'])
    @include('partials.icon', ['name' => 'moon', 'class' => 'for-light'])
</button>
