@extends('layouts.main2')
@extends('layouts.admin2')
@section('content')

    <script src="{{ asset('js/settings-ajax.js') }}"></script>


    <div class=" col-md-9 main-content" xmlns="http://www.w3.org/1999/html">
        <h4 class="pt-3">Настройки</h4>
        <div class="container">
            <div class="row justify-content-md-center">


                <div class="container mt-5">
                    <table class="table  table-bordered ">
                        <thead class="">
                        <tr>
                            <th scope="col">Название</th>
                            <th scope="col">Состояние</th>
                        </tr>
                        </thead>
                        <tbody>

                        <tr>
                            <td class="col-4">Регистрация на сайте</td>
                            <td class="col-6">
                                <div class="form-check">
                                    {{--                                    <input class="form-check-input" type="checkbox" value="" id="registrationActivity">--}}
                                    <input class="form-check-input" type="checkbox" value="" id="registrationActivity"
                                            {{ $isRegistrationActivity ? 'checked' : '' }}>
                                    <label class="form-check-label"
                                           for="registrationActivity">Включена/Выключена</label>


                                </div>

                            </td>
                            <td class="col-2">
                                <button id="btnRegistrationActivity" class="btn btn-primary mb-3 mt-3">Применить
                                </button>
                            </td>
                        </tr>


                        <tr>
                            <td class="col-4">Текст уведомления у пользователей</td>
                            <td colspan="col-6">
                                <textarea class="form-control text-start" id="textForUsers"
                                          rows="3">{{ trim($textForUsers) }}</textarea>
                            </td>
                            <td class="col-2">
                                <button id="btnTextForUsers" class="btn btn-primary mb-3 mt-3">Применить</button>
                            </td>
                        </tr>

                        </tbody>
                    </table>

                    <table class="table table-bordered mt-5 settings-table">
                        <tbody>
                        <tr>
                            <td class="col-4 align-middle">Журнал событий</td>
                            <td class="col-6 text-center align-middle">
                                <button id="btnTextForUsers" class="btn btn-primary mb-3 mt-3">Посмотреть</button>
                            </td>
                        </tr>
                        <tr>
                            <td class="col-4 align-middle">Оплата сервиса</td>
                            <td class="col-6 text-center align-middle">
                                <a href="/payment/service">
                                    <button type="button" id="club-fee" class="btn btn-primary">Перейти к оплате</button>
                                </a>
                            </td>
                        </tr>
                        </tbody>
                    </table>


                </div>
            </div>
        </div>
    </div>

@endsection
