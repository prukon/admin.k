@extends('layouts.admin2')
@section('content')
    <div class="main-content">
        <h4 class="pt-3">Успешная оплаты</h4>
        <div>
            <p>Ваш платеж был успешно обработан. Спасибо за вашу оплату!</p>
            <a href="{{ url('/cabinet') }}">Вернуться на главную страницу</a>
            <script>
                document.addEventListener('DOMContentLoaded', function () {

                });
            </script>

        </div>
    </div>
@endsection