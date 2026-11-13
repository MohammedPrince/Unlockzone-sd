{{-- Use the uploaded brand logo as the browser tab icon. Its path changes on upload; mtime busts browser caches. --}}
@php
    $faviconPath = (string) setting('site_logo', '');
    $faviconFile = $faviconPath !== '' ? public_path($faviconPath) : null;
@endphp
@if ($faviconFile && is_file($faviconFile))
    <link rel="icon" href="{{ asset($faviconPath) }}?v={{ filemtime($faviconFile) }}" type="{{ mime_content_type($faviconFile) }}">
    {{-- Icon used when a phone saves the site to its home screen. --}}
    <link rel="apple-touch-icon" href="{{ asset($faviconPath) }}?v={{ filemtime($faviconFile) }}">
@endif
