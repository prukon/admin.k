{{--@extends('layouts.main2')--}}
@extends('layouts.admin2')
@section('content')

    {{--<meta name="csrf-token" content="{{ csrf_token() }}">--}}
    {{--    <script src="{{ asset('js/settings-ajax.js') }}"></script>--}}

    <div class=" col-md-12 main-content text-start" xmlns="http://www.w3.org/1999/html">
        <h4 class="pt-3 pb-3">Настройки</h4>

        <!-- Вкладки -->
        <ul class="nav nav-tabs" id="paymentTabs" role="tablist">
            <li class="nav-item">
                <a class="nav-link {{ $activeTab == 'setting' ? 'active' : '' }}"
                   href="{{ route('admin.setting.setting') }}"
                   id="setting-tab"
                   role="tab"
                   aria-controls="setting"
                   aria-selected="{{ $activeTab == 'setting' ? 'true' : 'false' }}">
                    Общие
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ $activeTab == 'rule' ? 'active' : '' }}"
                   href="{{ route('admin.setting.rule') }}"
                   id="rule-tab"
                   role="tab"
                   aria-controls="rule"
                   aria-selected="{{ $activeTab == 'rule' ? 'true' : 'false' }}">
                    Права пользователей
                </a>
            </li>
        </ul>


        <div class="tab-content" id="myTabContent">
            <div class="tab-pane fade {{ $activeTab == 'setting' ? 'show active' : '' }}" id="profile" role="tabpanel">
                <div class="container-fluid">
                    <h4 class="pt-3 text-start">Общие</h4>


                </div>
            </div>
        </div>


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
                                        <label class="form-check-label"
                                               for="registrationActivity">Включена/Выключена</label>
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
                                    <button id="btnTextForUsers" class="btn btn-primary mb-3 mt-3">Применить
                                    </button>
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
                                    <button type="button" class="btn btn-primary width-160" id="logs"
                                            data-bs-toggle="modal" data-bs-target="#historyModal">История изменений
                                    </button>
                                </td>
                            </tr>
                            <tr>
                                <td>Меню в шапке</td>
                                <td class="text-center">
                                    <button type="button" class="btn btn-primary width-160" data-bs-toggle="modal"
                                            data-bs-target="#menuModal">Настроить
                                    </button>
                                </td>
                            </tr>
                            <tr>
                                <td>Социальные сети</td>
                                <td class="text-center">
                                    <button type="button" class="btn btn-primary width-160" data-bs-toggle="modal"
                                            data-bs-target="#socialMenuModal">Настроить
                                    </button>
                                </td>
                            </tr>

                            {{--<tr>--}}
                            {{--<td>Оплата сервиса</td>--}}
                            {{--<td class="text-center">--}}
                            {{--<a href="/partner-payment/recharge" id="club-fee" class="btn btn-primary width-160">Перейти к оплате</a>--}}
                            {{--</td>--}}
                            {{--</tr>--}}
                            </tbody>
                        </table>
                    </div>
                </div>

                {{--Модальные окна--}}
                {{--Модальное окно изменения пунтов меню в шапке--}}
                <div class="modal fade mt-3" id="menuModal" tabindex="-1" aria-labelledby="menuModalLabel">
                    {{--    <div class="modal-dialog modal-lg" style="margin: 0 auto;"> <!-- Установлено горизонтальное центрирование -->--}}
                    <div class="modal-dialog d-flex justify-content-center">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="menuModalLabel">Настройка меню в шапке</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"
                                        aria-label="Закрыть"></button>
                            </div>
                            <div class="modal-body text-start">
                                <button type="button" class="btn btn-secondary mb-3" id="addMenuItem">Новый пункт
                                    меню
                                </button>
                                <div class="table-responsive"> <!-- Добавлен контейнер для адаптивности таблицы -->
                                    <table class="table" id="menuTable">
                                        <thead>
                                        <tr>
                                            <th>Пункт меню</th>
                                            <th>Название</th>
                                            <th>Ссылка</th>
                                            <th>В новой вкладке</th>
                                            <th>Действия</th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        @foreach ($menuItems as $item)
                                            <tr data-id="{{ $item->id }}">
                                                <td>{{ $loop->index + 1 }}</td>
                                                <td>
                                                    <input type="text" name="menu_items[{{ $item->id }}][name]"
                                                           class="form-control" value="{{ $item->name }}"
                                                           data-key="menu_items[{{ $item->id }}][name]">
                                                    <div class="text-danger error-message"></div>
                                                    <!-- Контейнер для ошибки названия -->
                                                </td>
                                                <td>
                                                    <input type="text" name="menu_items[{{ $item->id }}][link]"
                                                           class="form-control" value="{{ $item->link }}">
                                                    <div class="text-danger error-message"></div>
                                                    <!-- Контейнер для ошибки ссылки -->
                                                </td>
                                                <td class="text-center">
                                                    <input type="checkbox"
                                                           name="menu_items[{{ $item->id }}][target_blank]"
                                                           value="1" {{ $item->target_blank ? 'checked' : '' }}>
                                                </td>
                                                <td>
                                                    <button type="button" class="btn btn-danger btn-sm deleteRow">
                                                        Удалить
                                                    </button>
                                                </td>
                                            </tr>
                                        @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена
                                </button>
                                <button type="button" class="btn btn-primary" id="saveMenu">Сохранить</button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Модальное окно настройки соц. сетей -->
                <div class="modal fade mt-3 socialMenuModal" id="socialMenuModal" tabindex="-1"
                     aria-labelledby="socialMenuModalLabel">
                    <div class="modal-dialog d-flex justify-content-center">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="socialMenuModalLabel">Настройка социальных сетей</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"
                                        aria-label="Закрыть"></button>
                            </div>
                            <div class="modal-body">
                                <div class="table-responsive">
                                    <table class="table" id="socialTable">
                                        <thead>
                                        <tr>
                                            <th>Иконка</th>
                                            <th>Название</th>
                                            <th>Ссылка</th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        @foreach ($socialItems as $item)
                                            <tr data-id="{{ $item->id }}">
                                                <td>
                                                    @switch($item->name)
                                                        @case('vk.com')
                                                        <i class="fa-brands fa-vk"></i>
                                                        @break
                                                        @case('YouTube.com')
                                                        <i class="fa-brands fa-youtube"></i>
                                                        @break
                                                        @case('RuTube.ru')
                                                        <i class="fa-brands fa-rutube"></i>
                                                        @break
                                                        @case('facebook.com')
                                                        <i class="fa-brands fa-facebook"></i>
                                                        @break
                                                        @case('Instagram.com')
                                                        <i class="fa-brands fa-instagram"></i>
                                                        @break
                                                        @case('Twitter.com')
                                                        <i class="fa-brands fa-twitter"></i>
                                                        @break
                                                        @case('LinkedIn.com')
                                                        <i class="fa-brands fa-linkedin"></i>
                                                        @break
                                                        @case('Telegram.org')
                                                        <i class="fa-brands fa-telegram"></i>
                                                        @break
                                                        @case('Pinterest.com')
                                                        <i class="fa-brands fa-pinterest"></i>
                                                        @break
                                                        @case('TikTok.com')
                                                        <i class="fa-brands fa-tiktok"></i>
                                                        @break
                                                        @case('Reddit.com')
                                                        <i class="fa-brands fa-reddit"></i>
                                                        @break
                                                        @case('Snapchat.com')
                                                        <i class="fa-brands fa-snapchat"></i>
                                                        @break
                                                        @case('WhatsApp.com')
                                                        <i class="fa-brands fa-whatsapp"></i>
                                                        @break
                                                        @case('Discord.com')
                                                        <i class="fa-brands fa-discord"></i>
                                                        @break
                                                        @case('Tumblr.com')
                                                        <i class="fa-brands fa-tumblr"></i>
                                                        @break
                                                        @case('Dribbble.com')
                                                        <i class="fa-brands fa-dribbble"></i>
                                                        @break
                                                        @case('GitHub.com')
                                                        <i class="fa-brands fa-github"></i>
                                                        @break
                                                        @case('Vimeo.com')
                                                        <i class="fa-brands fa-vimeo"></i>
                                                        @break
                                                        @case('Slack.com')
                                                        <i class="fa-brands fa-slack"></i>
                                                        @break
                                                        @case('Dropbox.com')
                                                        <i class="fa-brands fa-dropbox"></i>
                                                        @break
                                                        @default
                                                        <i class="fa fa-globe"></i>
                                                    @endswitch
                                                </td>
                                                <td>
                                                    <input type="text" name="social_items[{{ $item->id }}][name]"
                                                           class="form-control"
                                                           value="{{ $item->name }}" readonly>
                                                    <div class="text-danger error-message"></div>
                                                </td>
                                                <td>
                                                    <input type="text" name="social_items[{{ $item->id }}][link]"
                                                           class="form-control"
                                                           value="{{ $item->link }}">
                                                    <div class="text-danger error-message"></div>
                                                </td>
                                            </tr>
                                        @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена
                                </button>
                                <button type="button" class="btn btn-primary" id="saveSocialMenu">Сохранить</button>
                            </div>
                        </div>
                    </div>
                </div>


                <!-- Модальное окно настройки меню -->
                {{--@include('includes.menuModal')--}}

            <!-- Модальное окно логов -->
                @include('includes.logModal')

            <!-- Модальное окно подтверждения удаления -->
                @include('includes.modal.confirmDeleteModal')

            <!-- Модальное окно успешного обновления данных -->
                @include('includes.modal.successModal')

            <!-- Модальное окно ошибки -->
                @include('includes.modal.errorModal')


                <script>
                    $(document).ready(function () {
                        var token = '{{ csrf_token() }}';
                        showLogModal("{{ route('logs.all.data') }}");

                        // Вызов модалки Активность регистрации
                        $(document).on('click', '#btnRegistrationActivity', function () {
                            registrationActivity();
                        });

                        //Активность регистрации
                        function registrationActivity() {
                            // Показываем модалку с текстом и передаём колбэк, который удалит пользователя
                            showConfirmDeleteModal(
                                "Активность регистрации",
                                "Вы уверены, что хотите изменить правила возможности регистрации?",
                                function () {
                                    // ----
                                    var isRegistrationActivity = document.getElementById('registrationActivity').checked;

                                    if (1 == 1) {
                                        $.ajax({
                                            url: '/admin/settings/registration-activity',
                                            type: 'GET',
                                            data: {
                                                isRegistrationActivity: isRegistrationActivity,
                                            },

                                            success: function (response) {
                                                if (response.success) {
                                                    showSuccessModal("Обновление правил регистрации", "Правила регистрации обновлены.", 1);
                                                }
                                            }
                                        });
                                    }
                                    // ----
                                }
                            );
                        }

                        // Вызов модалки Текст уведомления
                        $(document).on('click', '#btnTextForUsers', function () {
                            textForUsers();
                        });

                        //Текст уведомления
                        function textForUsers() {
                            // Показываем модалку с текстом и передаём колбэк, который удалит пользователя
                            showConfirmDeleteModal(
                                "Текс для пользователей",
                                "Вы уверены, что хотите изменить текст для пользователей?",
                                function () {
                                    // ----
                                    var textForUsers = document.getElementById('textForUsers').value;
                                    const textForUsersTextarea = document.querySelector('.textForUsers');
                                    textForUsersTextarea.classList.remove('animated-input');

                                    $.ajax({
                                        url: '/admin/settings/text-for-users',
                                        method: 'POST',
                                        headers: {
                                            'X-CSRF-TOKEN': token,
                                        },
                                        contentType: 'application/json', // Указываем тип контента JSON

                                        data: JSON.stringify({
                                            textForUsers: textForUsers,
                                        }),

                                        success: function (response) {
                                            if (response.success) {
                                                var textForUsers = response.textForUsers;

                                                textForUsersTextarea.classList.add('animated-input');
                                                showSuccessModal("Обновление текста", "Текст для пользователей обновлен.", 1);
                                            }
                                        }
                                    });
                                    // ----
                                }
                            );
                        }

                        // //Меню в шапке
                        const menuTable = document.getElementById('menuTable').querySelector('tbody');
                        let newItemIndex = {{ $menuItems->count() }} +1;
                        const deletedItems = []; // Массив для хранения ID удаленных элементов

                        // Кнопка для добавления нового пункта меню
                        function addMenuItem() {
                            document.getElementById('addMenuItem').addEventListener('click', function () {
                                const newRow = document.createElement('tr');
                                newRow.innerHTML = `
                <td>${newItemIndex}</td>

<td>
    <input type="text" name="menu_items[{{ $item->id }}][name]" class="form-control w-100" value="{{ $item->name }}" data-key="menu_items[{{ $item->id }}][name]">
    <div class="text-danger error-message"></div>
</td>
<td>
    <input type="text" name="menu_items[{{ $item->id }}][link]" class="form-control w-100" value="{{ $item->link }}">
    <div class="text-danger error-message"></div>
</td>

                <td class="text-center">
                    <input type="checkbox" name="menu_items[new_${newItemIndex}][target_blank]" value="1">
                </td>
                <td><button type="button" class="btn btn-danger btn-sm deleteRow">Удалить</button></td>
            `;

                                menuTable.appendChild(newRow);
                                newItemIndex++;
                            });

                        }

                        addMenuItem();

                        // Обработчик для удаления строки
                        function deleteMenuItem() {
                            menuTable.addEventListener('click', function (event) {
                                if (event.target.classList.contains('deleteRow')) {
                                    const row = event.target.closest('tr');
                                    const id = row.getAttribute('data-id');
                                    if (id) {
                                        deletedItems.push(id); // Сохраняем ID удаленных элементов
                                    }
                                    row.remove(); // Удаляем строку из интерфейса
                                }
                            });
                        }

                        deleteMenuItem();

                        // Кнопка для сохранения данных
                        function saveMenu() {
                            document.getElementById('saveMenu').addEventListener('click', function () {
                                console.log('Saving menu...');
                                const formData = new FormData();

                                // Добавляем удаленные элементы в formData
                                deletedItems.forEach(id => formData.append('deleted_items[]', id));

                                // Очищаем предыдущие сообщения об ошибках
                                document.querySelectorAll('.error-message').forEach((el) => el.textContent = '');

                                // Собираем данные из таблицы
                                menuTable.querySelectorAll('tr').forEach((row) => {
                                    const id = row.getAttribute('data-id');
                                    const nameInput = row.querySelector(`input[name*="[name]"]`);
                                    const linkInput = row.querySelector(`input[name*="[link]"]`);
                                    const targetBlankInput = row.querySelector(`input[name*="[target_blank]"]`);

                                    if (nameInput && linkInput) {
                                        const baseKey = id ? `menu_items[${id}]` : `menu_items[new_${newItemIndex}]`;
                                        formData.append(`${baseKey}[name]`, nameInput.value);
                                        formData.append(`${baseKey}[link]`, linkInput.value);
                                        formData.append(`${baseKey}[target_blank]`, targetBlankInput.checked ? 1 : 0);

                                        if (!id) newItemIndex++;
                                    }
                                });

                                fetch('{{ route('settings.saveMenuItems') }}', {
                                    method: 'POST',
                                    headers: {
                                        'X-CSRF-TOKEN': token,
                                    },
                                    body: formData,
                                })
                                    .then(response => {
                                        if (response.status === 422) {
                                            return response.json().then(data => {
                                                throw data.errors;
                                            });
                                        }
                                        return response.json();
                                    })
                                    .then(response => {
                                        if (response.success) {
                                            showSuccessModal("Обновление меню", "Главное меню в шапке сайта обновлено.", 1);
                                        }
                                    })
                                    .catch(errors => {
                                        Object.keys(errors).forEach((key) => {
                                            const inputWithError = document.querySelector(`input[name="${key}"]`);
                                            if (inputWithError) {
                                                const errorContainer = inputWithError.nextElementSibling;
                                                errorContainer.textContent = errors[key][0]; // Отображаем первую ошибку для данного поля
                                            }
                                        });
                                    });
                            });

                        }

                        saveMenu();

                        //Изменение меню соц сетей
                        function saveSocialMenu() {
                            document.getElementById('saveSocialMenu').addEventListener('click', function () {
                                const formData = new FormData();

                                document.querySelectorAll('.error-message').forEach((el) => el.textContent = '');

                                document.querySelectorAll('#socialTable tbody tr').forEach((row) => {
                                    const id = row.getAttribute('data-id');
                                    const nameInput = row.querySelector(`input[name*="[name]"]`);
                                    const linkInput = row.querySelector(`input[name*="[link]"]`);

                                    if (nameInput && linkInput) {
                                        const baseKey = `social_items[${id}]`;
                                        formData.append(`${baseKey}[name]`, nameInput.value);
                                        formData.append(`${baseKey}[link]`, linkInput.value);
                                    }
                                });

                                fetch('{{ route('settings.saveSocialItems') }}', {
                                    method: 'POST',
                                    headers: {
                                        'X-CSRF-TOKEN': token,
                                    },
                                    body: formData,
                                })
                                    .then(response => {
                                        if (!response.ok) {
                                            return response.json().then(data => {
                                                throw new Error(JSON.stringify(data.errors));
                                            });
                                        }
                                        return response.json();
                                    })
                                    .then(response => {
                                        if (response.success) {
                                            showSuccessModal("Обновление меню", "Меню  соц. сетей обновлено.", 1);
                                        }
                                    })
                                    .catch(error => {
                                        const errors = JSON.parse(error.message);
                                        Object.keys(errors).forEach((key) => {
                                            const inputWithError = document.querySelector(`input[name="${key}"]`);
                                            if (inputWithError) {
                                                const errorContainer = inputWithError.nextElementSibling;
                                                errorContainer.textContent = errors[key][0];
                                            }
                                        });
                                    });
                            });
                        }

                        saveSocialMenu()

                    });
                </script>

            </div>
        </div>
    </div>

@endsection