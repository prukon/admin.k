{{-- Локальный Font Awesome 6.5.1 Free. Kit и внешний CDN не используем. --}}
{{-- ?v=filemtime: тот же путь, что у старого FA5 — без версии браузер держит кэш и рисует квадратики. --}}
@php
    $faUrl = static function (string $relative): string {
        $version = @filemtime(public_path($relative)) ?: '6.5.1';

        return asset($relative).'?v='.$version;
    };
@endphp
<link rel="preload" href="{{ $faUrl('plugins/fontawesome-free/webfonts/fa-solid-900.woff2') }}" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="{{ $faUrl('plugins/fontawesome-free/css/all.min.css') }}">
<link rel="stylesheet" href="{{ $faUrl('plugins/fontawesome-free/css/v4-shims.min.css') }}">
<link rel="stylesheet" href="{{ $faUrl('plugins/fontawesome-free/css/v5-font-face.min.css') }}">
<link rel="stylesheet" href="{{ $faUrl('plugins/fontawesome-free/css/v4-font-face.min.css') }}">
