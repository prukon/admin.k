@extends('layouts.landingPage')

@php
    $defaultOgPath = \App\Models\Setting::query()
        ->where('name', 'blog.default.og_image_path')
        ->whereNull('partner_id')
        ->value('text');
    $defaultOg = $defaultOgPath ? asset('storage/' . ltrim($defaultOgPath, '/')) : asset('img/landing/dashboard.png');
@endphp

@section('title', ($category->meta_title ?: ($category->name . ' — Блог | kidscrm.online')))
@section('meta_description', ($category->meta_description ?: ('Статьи по теме «' . $category->name . '»: практические советы, чек-листы и инструкции для детских секций и студий.')))
@section('og_image', $defaultOg)

@push('head')
    @vite('resources/css/blog.css')
    @php
        $breadcrumbs = [
            ['name' => 'Главная', 'item' => url('/')],
            ['name' => 'Блог', 'item' => route('blog.index')],
            ['name' => $category->name, 'item' => route('blog.category', $category->slug)],
        ];
    @endphp
    <script type="application/ld+json">
        {!! json_encode([
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => collect($breadcrumbs)->values()->map(function ($b, $i) {
                return [
                    '@type' => 'ListItem',
                    'position' => $i + 1,
                    'name' => $b['name'],
                    'item' => $b['item'],
                ];
            })->all(),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}
    </script>
@endpush

@section('content')
    <section class="blog-page py-5 bg-light">
        <div class="container blog-container">
            <nav class="mb-3" aria-label="breadcrumb">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="{{ url('/') }}">Главная</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('blog.index') }}">Блог</a></li>
                    <li class="breadcrumb-item active" aria-current="page">{{ $category->name }}</li>
                </ol>
            </nav>

            <h1 class="blog-title fw-bold mb-2">{{ $category->name }}</h1>
            <p class="blog-subtitle text-muted mb-4">Подборка статей по теме.</p>

            <div class="row g-4">
                @forelse($posts as $post)
                    @include('blog._post_card', [
                        'post' => $post,
                        'defaultOg' => $defaultOg,
                        'desktopCategoryBadge' => false,
                        'lazyLoad' => ! $loop->first,
                    ])
                @empty
                    <div class="col-12 text-muted">Пока нет опубликованных статей в этой категории.</div>
                @endforelse
            </div>

            <div class="mt-4">
                {{ $posts->withQueryString()->links() }}
            </div>
        </div>
    </section>
@endsection

