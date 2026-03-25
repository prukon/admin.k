{{-- favicon.ico в корне public; остальные файлы в public/favicons/ --}}
@php
    $faviconThemeColor = config('app.favicon_theme_color', '#ff6501');
@endphp
@if (file_exists(public_path('favicons/favicon.svg')))
    <link rel="icon" href="{{ asset('favicons/favicon.svg') }}" type="image/svg+xml">
@endif
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="48x48" type="image/x-icon">
    <link rel="icon" href="{{ asset('favicons/favicon-32x32.png') }}" type="image/png" sizes="32x32">
    <link rel="icon" href="{{ asset('favicons/favicon-16x16.png') }}" type="image/png" sizes="16x16">
    <link rel="icon" href="{{ asset('favicons/favicon-48x48.png') }}" type="image/png" sizes="48x48">
    <link rel="icon" href="{{ asset('favicons/favicon-96x96.png') }}" type="image/png" sizes="96x96">
    <link rel="apple-touch-icon" href="{{ asset('favicons/apple-touch-icon.png') }}" sizes="180x180">
    <link rel="manifest" href="{{ asset('favicons/site.webmanifest') }}">
    <meta name="theme-color" content="{{ $faviconThemeColor }}">
