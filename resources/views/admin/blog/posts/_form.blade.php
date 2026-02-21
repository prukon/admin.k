@php
    /** @var \App\Models\BlogPost|null $post */
    $post = $post ?? null;
@endphp

<div class="row g-3">
    <div class="col-12 col-lg-6">
        <label class="form-label">Категория</label>
        <select name="blog_category_id" class="form-select @error('blog_category_id') is-invalid @enderror">
            <option value="">— выберите —</option>
            @foreach($categories as $category)
                <option value="{{ $category->id }}"
                    @selected((int) old('blog_category_id', $post?->blog_category_id) === (int) $category->id)>
                    {{ $category->name }}
                </option>
            @endforeach
        </select>
        @error('blog_category_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-12 col-lg-6">
        <label class="form-label">Обложка (JPG/PNG/WEBP)</label>
        <input type="file" name="cover_image" class="form-control @error('cover_image') is-invalid @enderror">
        @error('cover_image')<div class="invalid-feedback">{{ $message }}</div>@enderror

        @if($post?->cover_image_path)
            <div class="mt-2">
                <div class="text-muted small mb-1">Текущая обложка:</div>
                <img src="{{ asset('storage/' . $post->cover_image_path) }}" alt="Cover" style="max-width: 280px; height: auto;">
            </div>
        @endif
    </div>

    <div class="col-12">
        <label class="form-label">Заголовок</label>
        <input type="text" name="title" value="{{ old('title', $post?->title) }}" class="form-control @error('title') is-invalid @enderror">
        @error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-12 col-lg-6">
        <label class="form-label">Slug (если пусто — сформируем автоматически)</label>
        <input type="text" name="slug" value="{{ old('slug', $post?->slug) }}" class="form-control @error('slug') is-invalid @enderror">
        @error('slug')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-12 col-lg-6">
        <div class="form-check mt-4">
            <input class="form-check-input" type="checkbox" value="1" id="is_published" name="is_published"
                @checked((bool) old('is_published', $post?->is_published))>
            <label class="form-check-label" for="is_published">
                Опубликовано
            </label>
        </div>
        @error('is_published')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
    </div>

    <div class="col-12 col-lg-6">
        <label class="form-label">Дата публикации</label>
        @php
            $publishedAt = old(
                'published_at',
                $post?->published_at ? $post->published_at->format('Y-m-d\TH:i') : null
            );
        @endphp
        <input type="datetime-local" name="published_at" value="{{ $publishedAt }}" class="form-control @error('published_at') is-invalid @enderror">
        @error('published_at')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-12 col-lg-6">
        <label class="form-label">Canonical URL (опционально)</label>
        <input type="text" name="canonical_url" value="{{ old('canonical_url', $post?->canonical_url) }}" class="form-control @error('canonical_url') is-invalid @enderror">
        @error('canonical_url')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-12">
        <label class="form-label">Краткое описание (excerpt)</label>
        <textarea name="excerpt" rows="3" class="form-control @error('excerpt') is-invalid @enderror">{{ old('excerpt', $post?->excerpt) }}</textarea>
        @error('excerpt')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-12">
        <label class="form-label">Текст статьи</label>
        <textarea id="content" name="content" rows="12" class="form-control @error('content') is-invalid @enderror">{{ old('content', $post?->content) }}</textarea>
        @error('content')<div class="invalid-feedback">{{ $message }}</div>@enderror
        <div class="text-muted small mt-1">Совет: используйте H2/H3 (не H1) внутри текста.</div>
    </div>

    <div class="col-12 col-lg-6">
        <label class="form-label">SEO Title</label>
        <input type="text" name="meta_title" value="{{ old('meta_title', $post?->meta_title) }}" class="form-control @error('meta_title') is-invalid @enderror">
        @error('meta_title')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-12 col-lg-6">
        <label class="form-label">SEO Description</label>
        <input type="text" name="meta_description" value="{{ old('meta_description', $post?->meta_description) }}" class="form-control @error('meta_description') is-invalid @enderror">
        @error('meta_description')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
</div>

