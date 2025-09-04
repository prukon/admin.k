@extends('layouts.admin2')

@section('title','Документы')

@section('content')
    <div class="container py-3">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h1 class="h4 m-0">Документы</h1>
            <a href="{{ url('/contracts/create') }}" class="btn btn-primary">Создать договор</a>
        </div>

        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        <div class="table-responsive">
            <table class="table align-middle">
                <thead>
                <tr>
                    <th>#</th>
                    <th>Ученик</th>
                    <th>Группа</th>
                    <th>Файл</th>
                    <th>Статус</th>
                    <th>Обновлён</th>
                    <th class="text-end">Действия</th>
                </tr>
                </thead>
                <tbody>
                @forelse($contracts as $c)
                    <tr>
                        <td>{{ $c->id }}</td>
                        <td>{{ $c->user_id }}</td>
                        <td>{{ $c->group_id ?? '—' }}</td>
                        <td><a href="{{ url('/contracts/'.$c->id) }}">Просмотр</a></td>
                        <td><span class="badge bg-secondary">{{ $c->status }}</span></td>
                        <td>{{ $c->updated_at->format('d.m.Y H:i') }}</td>
                        <td class="text-end">
                            <a class="btn btn-sm btn-outline-secondary" href="{{ url('/contracts/'.$c->id) }}">Открыть</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-muted">Пока пусто</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>

        {{ $contracts->withQueryString()->links() }}
    </div>
@endsection
