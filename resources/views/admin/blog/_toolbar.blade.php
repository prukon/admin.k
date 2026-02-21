@php
    $active = $active ?? 'posts'; // posts|categories|settings
@endphp

<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 pt-3">
    <div class="d-flex flex-wrap align-items-center gap-2">
        <div class="btn-group" role="group" aria-label="Blog navigation">
            <a href="{{ route('admin.blog.posts.index') }}"
               class="btn btn-sm {{ $active === 'posts' ? 'btn-primary' : 'btn-outline-primary' }}">
                Статьи
            </a>
            <a href="{{ route('admin.blog.categories.index') }}"
               class="btn btn-sm {{ $active === 'categories' ? 'btn-primary' : 'btn-outline-primary' }}">
                Категории
            </a>
            <a href="{{ route('admin.blog.settings.edit') }}"
               class="btn btn-sm {{ $active === 'settings' ? 'btn-primary' : 'btn-outline-primary' }}">
                SEO-настройки
            </a>
        </div>
    </div>

    <div class="d-flex flex-wrap align-items-center gap-2">
        <a href="{{ route('admin.blog.posts.create') }}" class="btn btn-sm btn-success">+ Статья</a>
        <a href="{{ route('admin.blog.categories.create') }}" class="btn btn-sm btn-outline-success">+ Категория</a>

        <a href="{{ route('blog.index') }}" target="_blank" class="btn btn-sm btn-outline-secondary">Открыть блог</a>
        <a href="{{ route('sitemap') }}" target="_blank" class="btn btn-sm btn-outline-secondary">Sitemap</a>
    </div>
</div>

<hr>

