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
                    @include('blog._post_card', [
                        'post' => $post,
                        'defaultOg' => $defaultOg,
                        'desktopCategoryBadge' => true,
                        'lazyLoad' => ! $loop->first,
                    ])
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

