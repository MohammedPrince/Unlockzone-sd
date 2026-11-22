{{--
    Search engine tags for a public page, plus its link preview (share-meta).
    Expects $seo: title, description, and optionally noindex (bool) and jsonld (list of schema.org arrays).
--}}
@php
    $seoCanonical = \App\Support\Seo::canonical(request());
    $seoNoindex = !empty($seo['noindex']);
@endphp
@include('partials.share-meta')
@if ($seoNoindex)
    <meta name="robots" content="noindex, follow">
@else
    <meta name="robots" content="index, follow, max-image-preview:large, max-snippet:-1">
    @if ($seoCanonical)
        <link rel="canonical" href="{{ $seoCanonical }}">
    @endif
    @foreach (\App\Support\Seo::alternates(request()) as $hreflang => $address)
        <link rel="alternate" hreflang="{{ $hreflang }}" href="{{ $address }}">
    @endforeach
@endif
@foreach ($seo['jsonld'] ?? [] as $graph)
    <script type="application/ld+json">{!! json_encode($graph, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
@endforeach
