@extends('layouts.main2')
@extends('layouts.admin2')
@section('content')

    <meta name="csrf-token" content="{{ csrf_token() }}">


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
                            <td class="col-6 align-middle text-center">
                                <button type="button" class="btn btn-primary fixed-width-btn width-160" id="logs" data-bs-toggle="modal" data-bs-target="#historyModal">История изменений</button>
                            </td>
                        </tr>
                        <tr>
                            <td class="col-4 align-middle">Оплата сервиса</td>
                            <td class="col-6 align-middle text-center">
                                <a href="/payment/service" id="club-fee" class="btn btn-primary fixed-width-btn width-160">Перейти к оплате</a>
                            </td>
                        </tr>

{{--                        <tr>--}}
{{--                            <td class="col-4 align-middle">Меню в шапке</td>--}}
{{--                            <td class="col-6 align-middle text-center">--}}
{{--                                <a href="" id="club-fee" class="btn btn-primary fixed-width-btn width-160">Настроить</a>--}}
{{--                                <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#menuModal"--}}
{{--                                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#menuModal">--}}

{{--                                    Настроить--}}
{{--                                </button>--}}
{{--                            </td>--}}
{{--                        </tr>--}}

                        </tbody>
                    </table>
                </div>
            </div>


    <!-- Модальное окно логов -->
    @include('includes.logModal')
    <!-- Модальное окно логов -->
    <script>
        $(document).ready(function() {
            showLogModal("{{ route('logs.all.data') }}");
        })
    </script>

    <!-- Модальное окно -->
    <div class="modal fade" id="menuModal" tabindex="-1" aria-labelledby="menuModalLabel" aria-hidden="true"    >

        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form id="menuForm">
                    <div class="modal-header">
                        <h5 class="modal-title" id="menuModalLabel">Настройка меню в шапке</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>

{{--                            <span aria-hidden="true">&times;</span>--}}
                        </button>
                    </div>
                    <div class="modal-body">
                        <table class="table">
                            <thead>
                            <tr>
                                <th>Пункт меню</th>
                                <th>Название</th>
                                <th>Ссылка</th>
                                <th>Открывать в новой вкладке</th>
                            </tr>
                            </thead>
                            <tbody>
                            @for ($i = 1; $i <= 4; $i++)
                                <tr>
                                    <td>{{ $i }}</td>
                                    <td>
                                        <input type="text" name="menu_items[{{ $i }}][name]" class="form-control">
                                    </td>
                                    <td>
                                        <input type="text" name="menu_items[{{ $i }}][link]" class="form-control">
                                    </td>
                                    <td class="text-center">
                                        <input type="checkbox" name="menu_items[{{ $i }}][target_blank]" value="1">
                                    </td>
                                </tr>
                            @endfor
                            </tbody>
                        </table>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                        <button type="submit" class="btn btn-primary">Сохранить</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    console.log('Document is ready');
                    var token = '{{ csrf_token() }}';

                    // Получаем форму и добавляем обработчик события submit
                    const menuForm = document.getElementById('menuForm');
                    menuForm.addEventListener('submit', function(e) {
                        e.preventDefault();
                        console.log('Form submitted');

                        // Создаем объект FormData из формы
                        const formData = new FormData(menuForm);

                        // Преобразуем FormData в объект
                        const data = {};
                        formData.forEach((value, key) => {
                            // Разбираем ключи с массивами, например, menu_items[1][name]
                            const keys = key.match(/([^\]\[]+)/g);
                            if (keys.length > 1) {
                                if (!data[keys[0]]) data[keys[0]] = {};
                                if (!data[keys[0]][keys[1]]) data[keys[0]][keys[1]] = {};
                                data[keys[0]][keys[1]][keys[2]] = value;
                            } else {
                                data[key] = value;
                            }
                        });

                        // Отправляем AJAX-запрос
                        fetch('{{ route('settings.saveMenuItems') }}', {
                            method: 'POST',
                            // headers: {
                            //     'Content-Type': 'application/json',
                            //     'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                            // },
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': token,
                            },
                            body: JSON.stringify(data),
                        })
                            .then(response => response.json())
                            .then(response => {
                                if (response.success) {
                                    alert('Меню успешно сохранено.');
                                    const menuModal = new bootstrap.Modal(document.getElementById('menuModal'));
                                    menuModal.hide();
                                } else {
                                    alert('Ошибка при сохранении меню.');
                                }
                            })
                            .catch(error => {
                                console.error(error);
                                alert('Произошла ошибка при сохранении меню.');
                            });
                    });
                });
            </script>


        </div>
    </div>
@endsection
