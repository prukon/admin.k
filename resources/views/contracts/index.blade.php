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
                    <th>Имя</th>
                    <th>Фамилия</th>
                    <th>Группа</th>
                    <th>Телефон</th>
                    <th>Email</th>
                    <th>Статус</th>
                    <th>Обновлён</th>
                    <th class="text-end">Действия</th>
                </tr>
                </thead>
                <tbody>
                @forelse($contracts as $c)
                    <tr>
                        <td>{{ $c->id }}</td>
                        <td>{{ $c->user_name ?? '—' }}</td>
                        <td>{{ $c->user_lastname  ?? '—' }}</td>
                        <td>{{ $c->team_title ?? '—' }}</td>
                        <td>{{ $c->user_phone ?? '—' }}</td>
                        <td>{{ $c->user_email ?? '—' }}</td>
                        <td>
    <span class="badge {{ $c->status_badge_class }}">
        {{ $c->status_ru }}
    </span>
                        </td>

                        <td>{{ $c->updated_at?->format('d.m.Y H:i:s') }}</td>
                        <td class="text-end">
                            <div class="d-inline-flex gap-2">
                                {{--<a class="btn btn-sm btn-outline-primary"--}}
                                   {{--href="{{ url('/contracts/'.$c->id.'/download-original') }}">--}}
                                    {{--Скачать--}}
                                {{--</a>--}}

                                <a class="btn btn-sm btn-outline-secondary" href="{{ url('/contracts/'.$c->id) }}">
                                    Подробнее
                                </a>

                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="text-center text-muted">Пока пусто</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        {{ $contracts->withQueryString()->links() }}
    </div>
@endsection
