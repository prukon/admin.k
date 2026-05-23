@extends('layouts.admin2')

@section('title', 'Шаблоны договоров')

@section('content')
    <div class="main-content text-start">
        <h4 class="pt-3">Шаблоны договоров</h4>
        <hr>

        <div class="d-flex flex-wrap gap-2 mb-3">
            <a href="{{ route('contract-templates.create') }}" class="btn btn-primary">Добавить шаблон</a>
            <a href="{{ route('contracts.index') }}" class="btn btn-outline-secondary">К договорам</a>
        </div>

        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        <div class="table-responsive">
            <table class="table table-sm align-middle">
                <thead>
                <tr>
                    <th>#</th>
                    <th>Название</th>
                    <th>Версия</th>
                    <th>Полей</th>
                    <th>Статус</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                @forelse($templates as $t)
                    <tr>
                        <td>{{ $t->id }}</td>
                        <td>{{ $t->title }}</td>
                        <td>{{ $t->currentVersion?->version ?? '—' }}</td>
                        <td>{{ is_array($t->currentVersion?->fields_schema) ? count($t->currentVersion->fields_schema) : 0 }}</td>
                        <td>
                            @if($t->is_archived)
                                <span class="badge bg-secondary">В архиве</span>
                            @elseif($t->isUsable())
                                <span class="badge bg-success">Активен</span>
                            @else
                                <span class="badge bg-warning text-dark">Нет версии</span>
                            @endif
                        </td>
                        <td class="text-end">
                            <a href="{{ route('contract-templates.edit', $t) }}" class="btn btn-sm btn-outline-primary">Изменить</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-muted text-center py-4">Шаблонов пока нет</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        {{ $templates->links() }}
    </div>
@endsection
