@extends('layouts.landingPage')

@php
    $indexTitle = \App\Models\Setting::query()
        ->where('name', 'blog.index.meta_title')
        ->whereNull('partner_id')
        ->value('text');
    $indexDescription = \App\Models\Setting::query()
        ->where('name', 'blog.index.meta_description')
        ->whereNull('partner_id')
        ->value('text');
    $defaultOgPath = \App\Models\Setting::query()
        ->where('name', 'blog.default.og_image_path')
        ->whereNull('partner_id')
        ->value('text');
    $defaultOg = $defaultOgPath ? asset('storage/' . ltrim($defaultOgPath, '/')) : asset('img/landing/dashboard.png');
@endphp

@section('title', $indexTitle ?: 'Блог — kidscrm.online')
@section('meta_description', $indexDescription ?: 'Полезные статьи для руководителей детских секций и студий: оплаты, договоры, учет, расписание, CRM и рост выручки.')
@section('og_image', $defaultOg)

@push('head')
    @vite('resources/css/blog.css')
@endpush

@section('content')
    <section class="blog-page py-5 bg-light">
        <div class="container blog-container">
            <h1 class="blog-title fw-bold mb-2">Блог</h1>
            <p class="blog-subtitle text-muted mb-4">Практические материалы о том, как меньше рутины и больше контроля: оплаты, долги, договоры, расписание и CRM.</p>

            <div class="row g-4">
                @forelse($posts as $post)
                    @php
                        $cover = $post->cover_image_path
                            ? asset('storage/' . $post->cover_image_path)
                            : $defaultOg;
                    @endphp
                    <div class="col-12 col-md-6 col-lg-4">
                        <div class="card blog-card h-100 border-0">
                            <a href="{{ route('blog.show', $post->slug) }}" class="text-decoration-none">
                                <img src="{{ $cover }}" class="card-img-top blog-card__img" alt="{{ $post->title }}">
                            </a>
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <a href="{{ route('blog.category', $post->category->slug) }}"
                                       class="badge blog-badge text-decoration-none">
                                        {{ $post->category->name }}
                                    </a>
                                    <div class="text-muted small">
                                        {{ $post->published_at?->format('d.m.Y') }}
                                    </div>
                                </div>

                                <h2 class="h5 fw-bold blog-card__title">
                                    <a href="{{ route('blog.show', $post->slug) }}" class="text-dark text-decoration-none">
                                        {{ $post->title }}
                                    </a>
                                </h2>

                                @if($post->excerpt)
                                    <div class="text-muted">
                                        {!! $post->excerpt !!}
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="col-12 text-muted">Пока нет опубликованных статей.</div>
                @endforelse
            </div>

            <div class="mt-4">
                {{ $posts->withQueryString()->links() }}
            </div>
        </div>
    </section>
@endsection

