{{--@extends('layouts.app')--}}
@extends('layouts.admin2')


@section('content')
    <div class="container py-3">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h1 class="h4 mb-0">Tinkoff — Правила комиссий</h1>
            <a href="/admin/tinkoff/commissions/create" class="btn btn-primary btn-sm">Добавить правило</a>
        </div>

        @if(session('status'))
            <div class="alert alert-success">{{ session('status') }}</div>
        @endif

        <div class="table-responsive">
            <table class="table table-sm align-middle">
                <thead class="table-light">
                <tr>
                    <th>#</th>
                    <th>Партнёр</th>
                    <th>Метод</th>
                    <th>%</th>
                    <th>Мин. фикс, ₽</th>
                    <th>Вкл</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                @foreach($rules as $r)
                    <tr>
                        <td>{{ $r->id }}</td>
                        <td>{{ $r->partner_id ? optional($r->partner)->title ?? ('#'.$r->partner_id) : '— (глобально)' }}</td>
                        <td>{{ $r->method ?? '—' }}</td>
                        <td>{{ number_format($r->percent, 2, ',', ' ') }}</td>
                        <td>{{ number_format($r->min_fixed, 2, ',', ' ') }}</td>
                        <td>
                            @if($r->is_enabled)
                                <span class="badge text-bg-success">on</span>
                            @else
                                <span class="badge text-bg-secondary">off</span>
                            @endif
                        </td>
                        <td class="text-end">
                            <a class="btn btn-outline-primary btn-sm" href="/admin/tinkoff/commissions/{{ $r->id }}/edit">Править</a>
                            <form action="/admin/tinkoff/commissions/{{ $r->id }}" method="post" class="d-inline"
                                  onsubmit="return confirm('Удалить правило?')">
                                @csrf @method('delete')
                                <button class="btn btn-outline-danger btn-sm">Удалить</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>

        {{ $rules->links() }}
    </div>
@endsection
