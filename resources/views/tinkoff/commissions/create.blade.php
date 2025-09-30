@extends('layouts.admin2')
@section('content')
    <div class="container py-3">
        <h1 class="h5 mb-3">Новое правило комиссии</h1>
        <form method="post" action="/admin/tinkoff/commissions">
            @csrf
            @include('tinkoff.commissions._form', ['rule'=>null])
            <div class="mt-3">
                <button class="btn btn-primary">Сохранить</button>
                <a href="/admin/tinkoff/commissions" class="btn btn-link">Отмена</a>
            </div>
        </form>
    </div>
@endsection
