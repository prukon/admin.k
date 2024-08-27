@extends('layouts.admin2')
@section('content')


    <script>
        // Передача данных текущего пользователя из Blade в JavaScript
        let currentUserName = "{{ auth()->user()->name }}";
        let currentUserRole = "{{ auth()->user()->role }}";
        let currentUserRole = "";
    </script>


    <div class=" col-md-9 main-content" xmlns="http://www.w3.org/1999/html">
        <h4 class="pt-3">Страница оплаты</h4>
        <div>
            <p>Имя пользователя: {{ auth()->user()->name }}
            <p>Платежный период: {{ $paymentDate }}</p>


            <form id="paymentForm" action="{{ route('payment.pay') }}" method="POST">
                <input type="hidden" name="_token" value="{{ csrf_token() }}">
                <input type="hidden" name="user_id" value="{{ auth()->user()->id }}">
{{--                <input type="hidden" name="period" value=$paymentDate>--}}
                <button type="submit" class="btn btn-lg btn-bd-primary new-main-button">Оплатить</button>
            </form>

            <script>
                document.addEventListener('DOMContentLoaded', function () {

                });
            </script>

        </div>
    </div>

@endsection