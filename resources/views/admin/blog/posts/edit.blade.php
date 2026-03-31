@extends('layouts.admin2')

@section('content')
    <div class="main-content text-start">
        <div class="d-flex align-items-center justify-content-between pt-3">
            <h4 class="mb-0">Редактирование статьи</h4>
            <div class="d-flex flex-wrap gap-2">
                <div class="btn-group" role="group" aria-label="AI actions">
                    <button type="button" class="btn btn-outline-success"
                            onclick="window.openBlogAiModal && window.openBlogAiModal({mode: 'post', postId: {{ (int)$post->id }}, action: 'improve'});">
                        Улучшить (ИИ)
                    </button>
                    <button type="button" class="btn btn-outline-success"
                            onclick="window.openBlogAiModal && window.openBlogAiModal({mode: 'post', postId: {{ (int)$post->id }}, action: 'seo'});">
                        Более SEO (ИИ)
                    </button>
                    <button type="button" class="btn btn-outline-success"
                            onclick="window.openBlogAiModal && window.openBlogAiModal({mode: 'post', postId: {{ (int)$post->id }}, action: 'checklist'});">
                        Таблица/чеклист (ИИ)
                    </button>
                    <button type="button" class="btn btn-outline-warning"
                            onclick="window.openBlogAiModal && window.openBlogAiModal({mode: 'post', postId: {{ (int)$post->id }}, action: 'regenerate'});">
                        Перегенерировать (ИИ)
                    </button>
                </div>

                <a href="{{ route('admin.blog.posts.index') }}" class="btn btn-outline-secondary">Назад</a>
            </div>
        </div>

        <hr>

        <form action="{{ route('admin.blog.posts.update', $post) }}" method="POST" enctype="multipart/form-data">
            @csrf
            @method('PUT')

            @include('admin.blog.posts._form', ['post' => $post, 'categories' => $categories])

            <div class="mt-3">
                <button class="btn btn-primary" type="submit">Сохранить</button>
            </div>
        </form>

        @include('admin.blog.posts._ai_images_block', ['post' => $post, 'aiImages' => $aiImages ?? collect()])
    </div>

    @include('admin.blog.posts._ai_modal')
@endsection

@section('scripts')
    @parent
    <script>
        $(function () {
            const $content = $('#content');
            $content.summernote({
                height: 420
            });

            const updateCharsCount = (html) => {
                const $target = $('#contentCharsCount');
                if (!$target.length) return;

                const div = document.createElement('div');
                div.innerHTML = html ?? '';
                const text = (div.textContent || div.innerText || '')
                    .replace(/\u00A0/g, ' ')
                    .trim();

                $target.text(Array.from(text).length);
            };

            updateCharsCount($content.summernote('code'));
            $content.on('summernote.change', function (we, contents) {
                updateCharsCount(contents);
            });

            // Важно: если пользователь в Code View и жмёт submit,
            // содержимое может не успеть синхронизироваться в textarea.
            $content.closest('form').on('submit', function () {
                $content.val($content.summernote('code'));
            });
        });
    </script>
@endsection

