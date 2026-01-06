<style>
    /* короткая цветная анимация подтверждения */
    .applied-flash {
        animation: appliedFlash 1000ms ease-in-out;
    }

    @keyframes appliedFlash {
        0% {
            background-color: #fff;
        }
        20% {
            background-color: #d1fae5;
        }
        /* зелёный мягкий */
        50% {
            background-color: #a7f3d0;
        }
        100% {
            background-color: transparent;
        }
    }
</style>

<h4 class="pt-3 text-start">Общие</h4>

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
                    <input
                            class="form-check-input"
                            type="checkbox"
                            id="registrationActivity"
                            name="registrationActivity"
                            value="1"
                            {{ $isRegistrationActive ? 'checked' : '' }}
                    >
                    <label class="form-check-label" for="registrationActivity">
                        Регистрация {{ $isRegistrationActive ? 'включена' : 'выключена' }}
                    </label>
                </div>
            </td>


            <td>
                <button id="btnRegistrationActivity" class="btn btn-primary">Применить</button>
            </td>
        </tr>

        {{-- НОВОЕ: Обязательная 2FA для администраторов --}}
        @if(auth()->user()->role_id == 1)
            <tr id="rowForce2faAdmins">
                <td>Обязательная 2FA для администраторов</td>
                <td>
                    <div class="form-check">
                        <input class="form-check-input"
                               type="checkbox"
                               id="force2faAdmins"
                               name="force2faAdmins"
                               value="1"
                                {{ !empty($force2faAdmins) ? 'checked' : '' }}>
                        <label class="form-check-label" for="force2faAdmins" id="force2faAdminsLabel">
                            {{ !empty($force2faAdmins) ? 'включена' : 'выключена' }}
                        </label>
                    </div>
                </td>
                <td>
                    <button id="btnForce2faAdmins" class="btn btn-primary">Применить</button>
                </td>
            </tr>
        @endif

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

<div class="table-responsive mt-3">
    <table class="table ">
        <tbody>
        @can('viewing-all-logs')
            <tr>
                <td>Журнал событий</td>
                <td class="text-center">
                    <button type="button" class="btn btn-primary width-160" id="logs"
                            data-bs-toggle="modal" data-bs-target="#historyModal">История изменений
                    </button>
                </td>
            </tr>
        @endcan
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

        </tbody>
    </table>
</div>

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
                    {{--{{dd($menuItems)}}--}}
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
                                        @case('facebook.com')
                                        <i class="fa-brands fa-facebook"></i>
                                        @break
                                        @case('Instagram.com')
                                        <i class="fa-brands fa-instagram"></i>
                                        @break
                                        @case('Telegram.org')
                                        <i class="fa-brands fa-telegram"></i>
                                        @break
                                        @case('TikTok.com')
                                        <i class="fa-brands fa-tiktok"></i>
                                        @break
                                        @case('WhatsApp.com')
                                        <i class="fa-brands fa-whatsapp"></i>
                                        @break
                                        @case('Vimeo.com')
                                        <i class="fa-brands fa-vimeo"></i>
                                        @break

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


@section('scripts')
    <script>
        $(document).ready(function () {
            var token = '{{ csrf_token() }}';


            // === НОВОЕ: сохрание чекбокса "Обязательная 2FA для админов" ===
            $(document).on('click', '#btnForce2faAdmins2', function () {
                var $row = $('#rowForce2faAdmins');
                var $cb = $('#force2faAdmins');
                var $label = $('#force2faAdminsLabel');
                var active = $cb.is(':checked');

                $.ajax({
                    url: '{{ route('settings.force2fa.admins') }}',
                    method: 'POST',
                    headers: {'X-CSRF-TOKEN': token},
                    data: {force2faAdmins: active ? 1 : 0},
                    success: function (resp) {
                        if (resp && resp.success) {
                            // обновим подпись
                            $label.text(active ? 'включена' : 'выключена');
                            // цветная анимация строки таблицы
                            $row.addClass('applied-flash');
                            setTimeout(function () {
                                $row.removeClass('applied-flash');
                            }, 1100);

                            // можно показать и модалку "успех"
                            if (typeof showSuccessModal === 'function') {
                                showSuccessModal('Настройки безопасности', 'Обязательная 2FA для администраторов ' + (active ? 'включена' : 'выключена') + '.', 1);
                            }
                        }
                    },
                    error: function () {
                        // в случае ошибки — вернём чекбокс в старое положение (видимый откат)
                        $cb.prop('checked', !active);
                        if (typeof showErrorModal === 'function') {
                            showErrorModal('Ошибка', 'Не удалось сохранить настройку. Повторите попытку.');
                        } else {
                            alert('Ошибка сохранения настройки.');
                        }
                    }
                });
            });

            // === НОВОЕ: сохранение чекбокса "Обязательная 2FA для админов" с логами ===
            $(document).on('click', '#btnForce2faAdmins', function () {
                var $row   = $('#rowForce2faAdmins');
                var $cb    = $('#force2faAdmins');
                var $label = $('#force2faAdminsLabel');
                var active = $cb.is(':checked');
                var token  = '{{ csrf_token() }}';

                console.log('[force2fa] sending', { active });

                $.ajax({
                    url: '{{ route('settings.force2fa.admins') }}',
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': token, 'X-Requested-With': 'XMLHttpRequest' },
                    data: { force2faAdmins: active ? 1 : 0 },

                    success: function (resp, status, xhr) {
                        console.log('[force2fa] success', { status: xhr.status, resp });
                        if (resp && resp.success) {
                            $label.text(active ? 'включена' : 'выключена');
                            $row.addClass('applied-flash');
                            setTimeout(function(){ $row.removeClass('applied-flash'); }, 1100);
                            if (typeof showSuccessModal === 'function') {
                                showSuccessModal('Настройки безопасности', 'Обязательная 2FA для администраторов ' + (active ? 'включена' : 'выключена') + '.', 1);
                            }
                        } else {
                            console.warn('[force2fa] backend returned success=false', resp);
                            if (typeof showErrorModal === 'function') {
                                showErrorModal('Ошибка', 'Не удалось сохранить настройку (success=false).');
                            } else {
                                alert('Не удалось сохранить настройку.');
                            }
                        }
                    },

                    error: function (xhr, status, err) {
                        console.error('[force2fa] error', { status: xhr.status, err, responseText: xhr.responseText });
                        // визуальный откат чекбокса
                        $cb.prop('checked', !active);

                        var msg = 'Ошибка сохранения настройки.';
                        if (xhr.status === 419) msg = 'CSRF-токен устарел. Обновите страницу.';
                        if (xhr.status === 403) msg = 'Нет прав (требуется суперадмин).';
                        if (xhr.responseText) {
                            try {
                                var j = JSON.parse(xhr.responseText);
                                if (j && j.message) msg = j.message;
                            } catch (e) { /* оставим базовое сообщение */ }
                        }

                        if (typeof showErrorModal === 'function') showErrorModal('Ошибка', msg);
                        else alert(msg);
                    }
                });
            });


            @can('viewing-all-logs')
                showLogModal("{{ route('logs.all.data') }}");
            @endcan

            // Вызов модалки Активность регистрации
            $(document).on('click', '#btnRegistrationActivity', function () {
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
            });

            // Вызов  Текст уведомления
            $(document).on('click', '#btnTextForUsers', function () {
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
            });

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


            addMenuItem();
            deleteMenuItem();
            saveMenu();
            saveSocialMenu()

        });
    </script>
@endsection
