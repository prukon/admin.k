@extends('layouts.admin2')
@section('content')
    <div class="container py-3">
        <h1 class="h5 mb-3">Правка правила #{{ $rule->id }}</h1>
        <form method="post" action="/admin/tinkoff/commissions/{{ $rule->id }}">
            @csrf @method('put')
            @include('tinkoff.commissions._form', ['rule'=>$rule])
            <div class="mt-3">
                <button class="btn btn-primary">Сохранить</button>
                <a href="/admin/tinkoff/commissions" class="btn btn-link">Отмена</a>
            </div>
        </form>
    </div>
@endsection
