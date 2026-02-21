@extends('layouts.landingPage')

@php
    $titleTemplate = \App\Models\Setting::query()
        ->where('name', 'blog.post.title_template')
        ->whereNull('partner_id')
        ->value('text') ?: '{title} — Блог | kidscrm.online';
    $defaultOgPath = \App\Models\Setting::query()
        ->where('name', 'blog.default.og_image_path')
        ->whereNull('partner_id')
        ->value('text');
    $defaultOg = $defaultOgPath ? asset('storage/' . ltrim($defaultOgPath, '/')) : asset('img/landing/dashboard.png');

    $seoTitle = $post->meta_title ?: ($post->title . ' — Блог | kidscrm.online');
    $seoDescription = $post->meta_description ?: \Illuminate\Support\Str::limit(strip_tags($post->excerpt ?: ''), 160);
    $cover = $post->cover_image_path
        ? asset('storage/' . $post->cover_image_path)
        : $defaultOg;
@endphp

@section('title', $post->meta_title ?: str_replace('{title}', $post->title, $titleTemplate))
@section('meta_description', $seoDescription ?: 'Статья из блога kidscrm.online.')
@section('og_type', 'article')
@section('og_image', $cover)
@section('canonical', $post->canonical_url ?: url()->current())

@push('head')
    @vite('resources/css/blog.css')
    @php
        $breadcrumbs = [
            ['name' => 'Главная', 'item' => url('/')],
            ['name' => 'Блог', 'item' => route('blog.index')],
            ['name' => $post->category->name, 'item' => route('blog.category', $post->category->slug)],
            ['name' => $post->title, 'item' => route('blog.show', $post->slug)],
        ];

        $articleLd = [
            '@context' => 'https://schema.org',
            '@type' => 'BlogPosting',
            'headline' => $post->title,
            'description' => $seoDescription ?: null,
            'image' => [$cover],
            'datePublished' => optional($post->published_at)->toAtomString(),
            'dateModified' => optional($post->updated_at)->toAtomString(),
            'author' => [
                '@type' => 'Organization',
                'name' => 'kidscrm.online',
                'url' => url('/'),
            ],
            'publisher' => [
                '@type' => 'Organization',
                'name' => 'kidscrm.online',
                'url' => url('/'),
                'logo' => [
                    '@type' => 'ImageObject',
                    'url' => asset('img/landing/favicon.png'),
                ],
            ],
            'mainEntityOfPage' => [
                '@type' => 'WebPage',
                '@id' => $post->canonical_url ?: url()->current(),
            ],
        ];

        $breadcrumbsLd = [
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
        ];
    @endphp

    <script type="application/ld+json">
        {!! json_encode($articleLd, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}
    </script>
    <script type="application/ld+json">
        {!! json_encode($breadcrumbsLd, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}
    </script>
@endpush

@section('content')
    <section class="blog-page py-5 bg-light">
        <div class="container blog-article-container">
            <nav class="mb-3" aria-label="breadcrumb">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="{{ url('/') }}">Главная</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('blog.index') }}">Блог</a></li>
                    <li class="breadcrumb-item">
                        <a href="{{ route('blog.category', $post->category->slug) }}">{{ $post->category->name }}</a>
                    </li>
                    <li class="breadcrumb-item active" aria-current="page">{{ $post->title }}</li>
                </ol>
            </nav>

            <article class="blog-article p-4 p-md-5">
                <h1 class="blog-title fw-bold mb-2">{{ $post->title }}</h1>

                <div class="blog-meta mb-4">
                    <a href="{{ route('blog.category', $post->category->slug) }}" class="text-decoration-none">
                        <span class="badge blog-badge">{{ $post->category->name }}</span>
                    </a>
                    <span class="mx-2">·</span>
                    <span>{{ $post->published_at?->format('d.m.Y H:i') }}</span>
                </div>

                <img src="{{ $cover }}" alt="{{ $post->title }}" class="blog-cover mb-4">

                @if($post->excerpt)
                    <div class="blog-excerpt mb-4">
                        {!! $post->excerpt !!}
                    </div>
                @endif

                <div class="blog-content">
                    {!! $post->content !!}
                </div>

                <hr class="my-5">

                <div class="d-flex flex-wrap justify-content-between align-items-center">
                    <a href="{{ route('blog.category', $post->category->slug) }}" class="text-decoration-none">
                        ← Все статьи в категории «{{ $post->category->name }}»
                    </a>
                    <a href="{{ route('blog.index') }}" class="text-decoration-none">
                        К списку статей →
                    </a>
                </div>
            </article>
        </div>
    </section>
@endsection

