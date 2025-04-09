{{--@extends('layouts.app')--}}
@extends('layouts.admin2')

@section('content')
    <div class="container mt-5">
        <h2 class="mb-4">Оплата заказа</h2>

        @if(session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif

        <form action="{{ route('tinkoff.init') }}" method="POST">
            @csrf

            <div class="mb-3">
                <label for="order_id" class="form-label">Номер заказа</label>
                <input type="text" class="form-control" id="order_id" name="order_id" required value="5">
            </div>

            <div class="mb-3">
                <label for="amount" class="form-label">Сумма (в рублях)</label>
                <input type="number" class="form-control" id="amount" name="amount" required min="1" step="0.01" value="150">

            </div>

            <div class="mb-3">
                <label for="user_name" class="form-label">ФИО пользователя</label>
                <input type="text" class="form-control" id="user_name" name="user_name" value="Иванов Петя">
            </div>

            <div class="mb-3">
                <label for="team_title" class="form-label">Название команды</label>
                <input type="text" class="form-control" id="team_title" name="team_title" value="Сокол">
            </div>

            <div class="mb-3">
                <label for="payment_month" class="form-label">Месяц оплаты (например, 2025-04)</label>
                <input type="text" class="form-control" id="payment_month" name="payment_month" value="2025-02">
            </div>

            <div class="mb-3">
                <label for="user_id" class="form-label">ID пользователя</label>
                <input type="number" class="form-control" id="user_id" name="user_id" value="10">

            </div>


            <button type="submit" class="btn btn-primary">Оплатить</button>
        </form>
    </div>
@endsection
