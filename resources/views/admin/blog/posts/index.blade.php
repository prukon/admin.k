@extends('layouts.admin2')

@section('content')
    <div class="main-content text-start">
        @include('admin.blog._toolbar', ['active' => 'posts'])

        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        <div class="row g-3 mb-3">
            <div class="col-12 col-md-3">
                <div class="small text-muted">Опубликовано</div>
                <div class="h5 mb-0">{{ $stats['published'] ?? 0 }}</div>
            </div>
            <div class="col-12 col-md-3">
                <div class="small text-muted">Черновики</div>
                <div class="h5 mb-0">{{ $stats['drafts'] ?? 0 }}</div>
            </div>
            <div class="col-12 col-md-3">
                <div class="small text-muted">Запланировано</div>
                <div class="h5 mb-0">{{ $stats['scheduled'] ?? 0 }}</div>
            </div>
            <div class="col-12 col-md-3">
                <div class="small text-muted">Последняя публикация</div>
                <div class="h5 mb-0">{{ $stats['last_published_at'] ?? '—' }}</div>
            </div>
        </div>

        <form method="GET" class="row g-2 align-items-end mb-3">
            <div class="col-12 col-md-3">
                <label class="form-label mb-1">Статус</label>
                <select name="status" class="form-select form-select-sm">
                    @php($status = request('status', 'all'))
                    <option value="all" @selected($status==='all')>Все</option>
                    <option value="published" @selected($status==='published')>Опубликовано</option>
                    <option value="draft" @selected($status==='draft')>Черновики</option>
                    <option value="scheduled" @selected($status==='scheduled')>Запланировано</option>
                </select>
            </div>
            <div class="col-12 col-md-3">
                <label class="form-label mb-1">Категория</label>
                <select name="category" class="form-select form-select-sm">
                    <option value="">Все</option>
                    @foreach($categories as $cat)
                        <option value="{{ $cat->id }}" @selected((string)request('category') === (string)$cat->id)>{{ $cat->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-12 col-md-4">
                <label class="form-label mb-1">Поиск (заголовок/slug)</label>
                <input type="text" name="q" value="{{ request('q') }}" class="form-control form-control-sm">
            </div>
            <div class="col-12 col-md-2 d-flex gap-2">
                <button class="btn btn-sm btn-primary w-100" type="submit">Найти</button>
                <a class="btn btn-sm btn-outline-secondary w-100" href="{{ route('admin.blog.posts.index') }}">Сброс</a>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-striped align-middle">
                <thead>
                <tr>
                    <th>ID</th>
                    <th>Заголовок</th>
                    <th>Категория</th>
                    <th>Кол-во символов</th>
                    <th>Статус</th>
                    <th>Дата</th>
                    <th class="text-end">Действия</th>
                </tr>
                </thead>
                <tbody>
                @forelse($posts as $post)
                    <tr>
                        <td>{{ $post->id }}</td>
                        <td>
                            <div class="fw-bold">{{ $post->title }}</div>
                            <div class="text-muted small"><code>{{ $post->slug }}</code></div>
                        </td>
                        <td>{{ $post->category?->name }}</td>
                        <td class="text-muted small">{{ $post->visible_chars_count }}</td>
                        <td>
                            @if($post->is_published)
                                <span class="badge bg-success">Опубликовано</span>
                            @else
                                <span class="badge bg-secondary">Черновик</span>
                            @endif
                        </td>
                        <td class="text-muted small">
                            {{ $post->published_at?->format('d.m.Y H:i') }}
                        </td>
                        <td class="text-end">
                            <a href="{{ route('admin.blog.posts.edit', $post) }}" class="btn btn-sm btn-outline-primary">Редактировать</a>
                            <form action="{{ route('admin.blog.posts.destroy', $post) }}"
                                  method="POST"
                                  class="d-inline"
                                  onsubmit="return confirm('Удалить статью?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger">Удалить</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-muted">Статей пока нет.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-3">
            {{ $posts->withQueryString()->links() }}
        </div>
    </div>

    @include('admin.blog.posts._ai_modal', ['categories' => $categories])
@endsection

