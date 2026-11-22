{{-- "Home › Services › Category" above a public page. Expects $crumbs: [[name, url], ...]; the page itself is not linked. --}}
<nav class="pub-crumbs" aria-label="{{ __('You are here') }}">
    <ol>
        <li><a href="{{ \App\Support\Seo::url('home') }}">{{ __('Home') }}</a></li>
        @foreach ($crumbs as [$crumbName, $crumbUrl])
            <li><a href="{{ $crumbUrl }}" dir="auto">{{ $crumbName }}</a></li>
        @endforeach
    </ol>
</nav>
