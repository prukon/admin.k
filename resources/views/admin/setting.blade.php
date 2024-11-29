{{--@extends('layouts.main2')--}}
@extends('layouts.admin2')
@section('content')

    <meta name="csrf-token" content="{{ csrf_token() }}">
    <script src="{{ asset('js/settings-ajax.js') }}"></script>

    <div class=" col-md-12 main-content text-start" xmlns="http://www.w3.org/1999/html">
        <h4 class="pt-3 pb-3">Настройки</h4>
        <div class="container">
            <div class="row justify-content-md-center">
                <div class="container">
                    <!-- Адаптивная обертка для мобильных устройств -->
                    <div class="table-responsive">


                        <table class="table ">
                            <thead class="">
                            <tr>
                                <th scope="col">Название</th>
                                <th scope="col">Состояние</th>
                                <th scope="col">Действие</th>
                            </tr>
                            </thead>
                            <tbody>
                            <tr>
                                <td>Регистрация на сайте</td>
                                <td>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="registrationActivity"
                                                {{ $isRegistrationActivity ? 'checked' : '' }}>
                                        <label class="form-check-label" for="registrationActivity">Включена/Выключена</label>
                                    </div>
                                </td>
                                <td>
                                    <button id="btnRegistrationActivity" class="btn btn-primary">Применить</button>
                                </td>
                            </tr>
{{--                            <tr>--}}
{{--                                <td>Текст уведомления у пользователей</td>--}}
{{--                                <td colspan="2">--}}
{{--                                    <textarea class="form-control" id="textForUsers" rows="3">{{ trim($textForUsers) }}</textarea>--}}
{{--                                </td>--}}
{{--                            </tr>--}}


                            <tr>
                                <td class="col-4">Текст уведомления у пользователей</td>
                                <td colspan="col-6">
                                <textarea class="form-control text-start textForUsers" id="textForUsers"
                                          rows="3">{{ trim($textForUsers) }}</textarea>
                                </td>
                                <td class="col-2">
                                    <button id="btnTextForUsers" class="btn btn-primary mb-3 mt-3">Применить</button>
                                </td>
                            </tr>


                            </tbody>
                        </table>
                    </div>

                    <div class="table-responsive mt-5">
                        <table class="table ">
                            <tbody>
                            <tr>
                                <td>Журнал событий</td>
                                <td class="text-center">
                                    <button type="button" class="btn btn-primary width-160" id="logs" data-bs-toggle="modal" data-bs-target="#historyModal">История изменений</button>
                                </td>
                            </tr>
                            <tr>
                                <td>Меню в шапке</td>
                                <td class="text-center">
                                    <button type="button" class="btn btn-primary width-160" data-bs-toggle="modal" data-bs-target="#menuModal">Настроить</button>
                                </td>
                            </tr>
                            <tr>
                                <td>Социальные сети</td>
                                <td class="text-center">
                                    <button type="button" class="btn btn-primary width-160" data-bs-toggle="modal" data-bs-target="#socialMenuModal">Настроить</button>
                                </td>
                            </tr>

                            <tr>
                                <td>Оплата сервиса</td>
                                <td class="text-center">
                                    <a href="/partner-payment/recharge" id="club-fee" class="btn btn-primary width-160">Перейти к оплате</a>
                                </td>
                            </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

            <!-- Модальное окно настройки меню -->
            @include('includes.menuModal')

            <!-- Модальное окно настройки соц. сетей -->
            @include('includes.socialMenuModal')

            <!-- Модальное окно логов -->
            @include('includes.logModal')
            <!-- Модальное окно логов -->
            <script>
                $(document).ready(function () {
                    showLogModal("{{ route('logs.all.data') }}");
                })
            </script>

            </div>
        </div>
    </div>
@endsection
