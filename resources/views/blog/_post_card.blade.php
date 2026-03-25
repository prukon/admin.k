{{--
  $post, $defaultOg required.
  $desktopCategoryBadge (bool, default true): показывать ссылку-категорию в теле карточки на md+.
  $lazyLoad (bool, default true): loading="lazy" для img (первый элемент списка обычно false).
--}}
@php
    $desktopCategoryBadge = $desktopCategoryBadge ?? true;
    $lazyLoad = $lazyLoad ?? true;
    $cover = $post->cover_image_path
        ? asset('storage/' . $post->cover_image_path)
        : $defaultOg;
@endphp
<div class="col-12 col-md-6 col-lg-4">
    <div class="card blog-card h-100 border-0">
        <a href="{{ route('blog.show', $post->slug) }}"
           class="blog-card__media-link d-block text-decoration-none"
           aria-label="{{ $post->title }}">
            <div class="blog-card__figure">
                <img src="{{ $cover }}"
                     class="blog-card__img"
                     alt=""
                     width="800"
                     height="800"
                     @if($lazyLoad) loading="lazy" @endif>
                <div class="blog-card__overlay blog-card__overlay--mobile d-md-none">
                    <div class="blog-card__overlay-inner">
                        <div class="blog-card__overlay-meta">
                            @if($post->published_at)
                                <time class="blog-card__overlay-date" datetime="{{ $post->published_at->toAtomString() }}">
                                    {{ $post->published_at->format('d.m.Y') }}
                                </time>
                            @endif
                            @if($post->category)
                                <span class="blog-card__overlay-chip">{{ $post->category->name }}</span>
                            @endif
                        </div>
                        <span class="blog-card__overlay-title">{{ $post->title }}</span>
                    </div>
                </div>
            </div>
        </a>
        <div class="card-body d-none d-md-block">
            <div class="d-flex justify-content-between align-items-center mb-2 gap-2">
                @if($desktopCategoryBadge && $post->category)
                    <a href="{{ route('blog.category', $post->category->slug) }}"
                       class="badge blog-badge text-decoration-none">
                        {{ $post->category->name }}
                    </a>
                @else
                    <span></span>
                @endif
                <div class="text-muted small flex-shrink-0">
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
