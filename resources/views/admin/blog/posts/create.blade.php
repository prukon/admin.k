@extends('layouts.admin2')

@section('content')
    <div class="main-content text-start">
        <div class="d-flex align-items-center justify-content-between pt-3">
            <h4 class="mb-0">Новая статья</h4>
            <a href="{{ route('admin.blog.posts.index') }}" class="btn btn-outline-secondary">Назад</a>
        </div>

        <hr>

        <form action="{{ route('admin.blog.posts.store') }}" method="POST" enctype="multipart/form-data">
            @csrf
            @include('admin.blog.posts._form', ['categories' => $categories])

            <div class="mt-3">
                <button class="btn btn-primary" type="submit">Сохранить</button>
            </div>
        </form>
    </div>
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

