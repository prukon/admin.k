@extends('layouts.admin2')
@section('content')
    <div class=" col-md-9 main-content" xmlns="http://www.w3.org/1999/html">
        <h4 class="pt-3">Ошибка оплаты</h4>
        <div>
            <p>К сожалению, произошла ошибка при обработке вашего платежа. Пожалуйста, попробуйте еще раз или свяжитесь с поддержкой.</p>
            <a href="{{ url('/') }}">Вернуться на главную страницу</a>
            <script>
                document.addEventListener('DOMContentLoaded', function () {

                });
            </script>

        </div>
    </div>
@endsection