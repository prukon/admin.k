@extends('layouts.admin2')

@section('content')
    <div class="main-content text-start">
        @include('admin.blog._toolbar', ['active' => 'settings'])

        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        <form action="{{ route('admin.blog.settings.update') }}" method="POST" enctype="multipart/form-data" class="row g-3">
            @csrf

            <div class="col-12 col-lg-6">
                <label class="form-label">SEO Title (страница /blog)</label>
                <input type="text"
                       name="index_meta_title"
                       value="{{ old('index_meta_title', $settings['index_meta_title'] ?? '') }}"
                       class="form-control @error('index_meta_title') is-invalid @enderror">
                @error('index_meta_title')<div class="invalid-feedback">{{ $message }}</div>@enderror
                <div class="text-muted small mt-1">Если пусто — используем дефолт “Блог — kidscrm.online”.</div>
            </div>

            <div class="col-12 col-lg-6">
                <label class="form-label">SEO Description (страница /blog)</label>
                <input type="text"
                       name="index_meta_description"
                       value="{{ old('index_meta_description', $settings['index_meta_description'] ?? '') }}"
                       class="form-control @error('index_meta_description') is-invalid @enderror">
                @error('index_meta_description')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-12 col-lg-6">
                <label class="form-label">Шаблон SEO Title (статья)</label>
                <input type="text"
                       name="post_title_template"
                       value="{{ old('post_title_template', $settings['post_title_template'] ?? '{title} — Блог | kidscrm.online') }}"
                       class="form-control @error('post_title_template') is-invalid @enderror">
                @error('post_title_template')<div class="invalid-feedback">{{ $message }}</div>@enderror
                <div class="text-muted small mt-1">Можно использовать переменную <code>{title}</code>.</div>
            </div>

            <div class="col-12 col-lg-6">
                <label class="form-label">OG‑изображение по умолчанию (для /blog и fallback)</label>
                <input type="file" name="default_og_image" class="form-control @error('default_og_image') is-invalid @enderror">
                @error('default_og_image')<div class="invalid-feedback">{{ $message }}</div>@enderror

                @if(!empty($settings['default_og_image_path']))
                    <div class="mt-2">
                        <div class="text-muted small mb-1">Текущее:</div>
                        <img src="{{ asset('storage/' . $settings['default_og_image_path']) }}"
                             alt="Default OG"
                             style="max-width: 280px; height: auto;">
                        <div class="text-muted small mt-1"><code>{{ $settings['default_og_image_path'] }}</code></div>
                    </div>
                @endif
            </div>

            <div class="col-12">
                <button class="btn btn-primary" type="submit">Сохранить</button>
            </div>
        </form>
    </div>
@endsection

