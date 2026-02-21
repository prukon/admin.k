@extends('layouts.admin2')

@section('content')
    <div class="main-content text-start">
        <div class="d-flex align-items-center justify-content-between pt-3">
            <h4 class="mb-0">Редактирование статьи</h4>
            <a href="{{ route('admin.blog.posts.index') }}" class="btn btn-outline-secondary">Назад</a>
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
    </div>
@endsection

@section('scripts')
    <script>
        $(function () {
            $('#content').summernote({
                height: 420
            });
        });
    </script>
@endsection

