@extends('layouts.admin2')

@section('content')
    <div class="main-content text-start">
        <div class="d-flex align-items-center justify-content-between pt-3">
            <h4 class="mb-0">Новая категория</h4>
            <a href="{{ route('admin.blog.categories.index') }}" class="btn btn-outline-secondary">Назад</a>
        </div>

        <hr>

        <form action="{{ route('admin.blog.categories.store') }}" method="POST" class="row g-3">
            @csrf

            <div class="col-12 col-lg-6">
                <label class="form-label">Название</label>
                <input type="text" name="name" value="{{ old('name') }}" class="form-control @error('name') is-invalid @enderror">
                @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-12 col-lg-6">
                <label class="form-label">Slug (если пусто — сформируем автоматически)</label>
                <input type="text" name="slug" value="{{ old('slug') }}" class="form-control @error('slug') is-invalid @enderror">
                @error('slug')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-12 col-lg-6">
                <label class="form-label">SEO Title</label>
                <input type="text" name="meta_title" value="{{ old('meta_title') }}" class="form-control @error('meta_title') is-invalid @enderror">
                @error('meta_title')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-12 col-lg-6">
                <label class="form-label">SEO Description</label>
                <input type="text" name="meta_description" value="{{ old('meta_description') }}" class="form-control @error('meta_description') is-invalid @enderror">
                @error('meta_description')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-12">
                <button class="btn btn-primary" type="submit">Сохранить</button>
            </div>
        </form>
    </div>
@endsection

