@extends('layouts.admin2')

@section('content')
    <div class="main-content text-start">
        @include('admin.blog._toolbar', ['active' => 'categories'])

        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        @error('category')
            <div class="alert alert-danger">{{ $message }}</div>
        @enderror

        <div class="table-responsive">
            <table class="table table-striped align-middle">
                <thead>
                <tr>
                    <th>ID</th>
                    <th>Название</th>
                    <th>Slug</th>
                    <th class="text-end">Действия</th>
                </tr>
                </thead>
                <tbody>
                @forelse($categories as $category)
                    <tr>
                        <td>{{ $category->id }}</td>
                        <td>{{ $category->name }}</td>
                        <td><code>{{ $category->slug }}</code></td>
                        <td class="text-end">
                            <a href="{{ route('admin.blog.categories.edit', $category) }}" class="btn btn-sm btn-outline-primary">Редактировать</a>
                            <form action="{{ route('admin.blog.categories.destroy', $category) }}"
                                  method="POST"
                                  class="d-inline"
                                  onsubmit="return confirm('Удалить категорию?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger">Удалить</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="text-muted">Категорий пока нет.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-3">
            {{ $categories->withQueryString()->links() }}
        </div>
    </div>
@endsection

