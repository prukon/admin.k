{{--@extends('layouts.main2')--}}
@extends('layouts.admin2')
@section('content')

    <meta name="csrf-token" content="{{ csrf_token() }}">
    <script src="{{ asset('js/settings-ajax.js') }}"></script>

    <div class="col-md-12 main-content text-start">
        <h4 class="pt-3 pb-3">Организация</h4>
        <div class="container">
            <!-- Логотип и описание -->
            <div class="row mb-4 align-items-center">
                {{--<div class="col-md-2 text-center">--}}
                    {{--<img src="{{ asset('/storage/logo.png') }}" alt="Логотип KidsLink" class="img-fluid" style="max-height: 100px;">--}}
                {{--</div>--}}
                <div class="col-md-10">
                    <p><strong>KidsLink.ru</strong> — сервис для управления спортивной школой, который помогает автоматизировать процессы записи, учета и администрирования, делая управление эффективнее и удобнее.</p>
                </div>
            </div>

            <!-- Основная информация -->
            <div class="row">
                <div class="col-md-6">
                    <h5>Основная информация</h5>
                    <table class="table table-bordered">
                        <tbody>
                        <tr>
                            <th>Название</th>
                            <td>ИП Устьян Евгений Артурович</td>
                        </tr>
                        <tr>
                            <th>ИНН</th>
                            <td>110211351590</td>
                        </tr>
                        <tr>
                            <th>ЕГРНИП</th>
                            <td>324784700017432</td>
                        </tr>
                        <tr>
                            <th>Юридический адрес</th>
                            <td>г. Санкт-Петербург, Плесецкая ул. д. 16, стр. 1, кв. 354, индекс 197373</td>
                        </tr>
                        </tbody>
                    </table>
                </div>
                <div class="col-md-6">
                    <h5>Банковские реквизиты</h5>
                    <table class="table table-bordered">
                        <tbody>
                        <tr>
                            <th>Банк</th>
                            <td>АО «Тинькофф Банк»</td>
                        </tr>
                        <tr>
                            <th>Расчетный счет</th>
                            <td>40802810300005891658</td>
                        </tr>
                        <tr>
                            <th>БИК</th>
                            <td>044525974</td>
                        </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Контактная информация -->
            <div class="row mt-4">
                <div class="col-md-6">
                    <h5>Контактная информация</h5>
                    <table class="table table-bordered">
                        <tbody>
                        {{--<tr>--}}
                            {{--<th>Телефон</th>--}}
                            {{--<td></td>--}}
                        {{--</tr>--}}
                        <tr>
                            <th>Почта</th>
                            <td><a href="mailto:kidslinkru@yandex.ru">kidslinkru@yandex.ru</a></td>
                        </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

@endsection
