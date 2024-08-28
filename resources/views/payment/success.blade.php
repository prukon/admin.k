@extends('layouts.admin2')
@section('content')
    <div class=" col-md-9 main-content" xmlns="http://www.w3.org/1999/html">
        <h4 class="pt-3">Успешная оплаты</h4>
        <div>
            <p>Ваш платеж был успешно обработан. Спасибо за вашу оплату!</p>
            <a href="{{ url('/') }}">Вернуться на главную страницу</a>
            <script>
                document.addEventListener('DOMContentLoaded', function () {

                });
            </script>

        </div>
    </div>
@endsection