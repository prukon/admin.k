<!-- Модальное окно для настройки полей -->
<div class="modal fade" id="fieldModal" tabindex="-1" aria-labelledby="fieldModalLabel">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fieldModalLabel" id="fieldModalLabel">Настройка пользовательских полей</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <button id="new-field-btn" type="button" class="btn btn-success new-field-btn mt-3 ml-3">Новое поле</button>

            <div class="modal-body text-start">
                <!-- Кнопка для добавления нового поля -->

                <!-- Таблица с существующими полями -->
                <table class="table table-bordered" id="fields-table">
                    <thead>
                    <tr>
                        <th>№</th>
                        <th>Название</th>
                        <th>Тип поля</th>
                        <th>Разрешено редактировать</th>
                        <th>Удалить</th>
                    </tr>
                    </thead>
                    <tbody>
                    <!-- Существующие поля будут загружаться сюда -->
                    @foreach($fields as $index => $field)
                        <tr data-id="{{ $field->id }}" id="{{ $field->id }}">

                            <td>{{ $index + 1 }}</td>
                            <td>
                                <input type="text" class="form-control field-name" value="{{ $field->name }}">
                                <div class="invalid-feedback" style="display: none;">Заполните поле</div>
                            </td>

                            <td>
                                <select disabled class="form-select field-type">
                                    <option value="string" {{ $field->field_type == 'string' ? 'selected' : '' }}>
                                        Текст
                                    </option>
                                    <option value="text" {{ $field->field_type == 'text' ? 'selected' : '' }}>
                                        Многострочный текст
                                    </option>
                                    <option value="select" {{ $field->field_type == 'select' ? 'selected' : '' }}>
                                        Список
                                    </option>
                                </select>
                            </td>
                            {{--<td>--}}
                                {{--<!-- Чекбоксы для разрешений -->--}}
                                {{--<div class="form-check">--}}
                                    {{--<input--}}
                                            {{--class="form-check-input permission-admin"--}}
                                            {{--type="checkbox"--}}
                                            {{--value="admin"--}}
                                            {{--id="permission-admin-{{ $field->id }}"--}}
                                            {{--{{ in_array('admin', $field->permissions) ? 'checked' : '' }}--}}
                                    {{-->--}}
                                    {{--<label class="form-check-label" for="permission-admin-{{ $field->id }}">Админ</label>--}}
                                {{--</div>--}}

                                {{--<div class="form-check">--}}
                                    {{--<input--}}
                                            {{--class="form-check-input permission-manager"--}}
                                            {{--type="checkbox"--}}
                                            {{--value="manager"--}}
                                            {{--id="permission-manager-{{ $field->id }}"--}}
                                            {{--{{ in_array('manager', $field->permissions) ? 'checked' : '' }}--}}
                                    {{-->--}}
                                    {{--<label class="form-check-label" for="permission-manager-{{ $field->id }}">Менеджер</label>--}}
                                {{--</div>--}}

                                {{--<div class="form-check">--}}
                                    {{--<input--}}
                                            {{--class="form-check-input permission-user"--}}
                                            {{--type="checkbox"--}}
                                            {{--value="user"--}}
                                            {{--id="permission-user-{{ $field->id }}"--}}
                                            {{--{{ in_array('user', $field->permissions) ? 'checked' : '' }}--}}
                                    {{-->--}}
                                    {{--<label class="form-check-label" for="permission-user-{{ $field->id }}">Пользователь</label>--}}
                                {{--</div>--}}
                            {{--</td>--}}

                            <td>
                                <!-- Генерируем чекбоксы на основе списка всех ролей -->
                                @foreach($roles as $role)
                                    <div class="form-check">
                                        <input
                                                class="form-check-input permission-checkbox"
                                                type="checkbox"
                                                value="{{ $role->id }}"
                                                id="permission-{{ $role->id }}-field-{{ $field->id }}"
                                                @if(is_array($field->permissions_id) && in_array($role->id, $field->permissions_id)) checked @endif
                                        >
                                        <label class="form-check-label" for="permission-{{ $role->id }}-field-{{ $field->id }}">
                                            {{ $role->label ?? $role->name }}
                                        </label>
                                    </div>
                                @endforeach
                            </td>
                            <td>
                                <button type="button" class="btn btn-danger btn-sm confirm-delete-field-modal">Удалить
                                </button>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                <button type="button" class="btn btn-primary" id="save-fields-btn">Сохранить</button>
            </div>
        </div>
    </div>
</div>

<!-- Модальное окно ошибки -->
@include('includes.modal.errorModal')

<!-- Модальное окно подтверждения удаления -->
@include('includes.modal.confirmDeleteModal')

<!-- Модальное окно успешного обновления данных -->
@include('includes.modal.successModal')

<script>
    $(document).ready(function () {
        let fieldIdToDelete = null; // ID поля для удаления

        // Сериализуем в JSON, чтобы использовать в скрипте:
        const roles = @json($roles);

        // Функция для добавления новой строки в таблицу
        const newFieldBtn = document.getElementById('new-field-btn');
        newFieldBtn.addEventListener('click', function () {
            // Генерируем случайный ID для новой строки (диапазон 10000-20000)
            const randomId = Math.floor(Math.random() * (20000 - 10000 + 1)) + 10000;

            // Генерируем HTML для чекбоксов, перебирая все роли
            let rolesHtml = '';
            roles.forEach(role => {
                rolesHtml += `
                <div class="form-check">
                    <input
                        class="form-check-input permission-checkbox"
                        type="checkbox"
                        value="${role.id}"
                        id="permission-${role.id}-${randomId}">
                    <label class="form-check-label" for="permission-${role.id}-${randomId}">
                        ${role.display_name ?? role.name}
                    </label>
                </div>
            `;
            });

            // Формируем новую строку для таблицы
            const newRow = `
            <tr id="${randomId}">
                <td></td>
                <td>
                    <input type="text" class="form-control field-name" placeholder="Введите название">
                    <div class="invalid-feedback" style="display: none;">Заполните поле</div>
                </td>
                <td>
                    <select class="form-select field-type">
                        <option value="string">Текст</option>
                        <option value="text">Многострочный текст</option>
                        <option value="select">Список</option>
                    </select>
                </td>
                <td>
                    ${rolesHtml}
                </td>
                <td>
                    <button type="button" class="btn btn-danger btn-sm confirm-delete-field-modal">Удалить</button>
                </td>
            </tr>
        `;

            // Добавляем новую строку в тело таблицы
            const tbody = document.querySelector('#fields-table tbody');
            tbody.insertAdjacentHTML('beforeend', newRow);

            // После добавления строки обновляем нумерацию
            updateRowNumbers();
        });

        // Функция для обновления нумерации (1,2,3...) в первом столбце
        function updateRowNumbers() {
            const rows = document.querySelectorAll('#fields-table tbody tr');
            rows.forEach((row, index) => {
                // Ищем первую ячейку (td) и ставим индекс+1
                const numberCell = row.querySelector('td:first-child');
                if (numberCell) {
                    numberCell.textContent = (index + 1).toString();
                }
            });
        }


















// Сохранение данных
            $('#save-fields-btn').on('click', function () {
            let rows = document.querySelectorAll('#fields-table tbody tr');
            let fieldsData = [];

            rows.forEach((row) => {
                let fieldId   = row.getAttribute('data-id');
                let fieldName = row.querySelector('.field-name').value;
                let fieldType = row.querySelector('.field-type').value;

                // Собираем все чекбоксы
                let permissionCheckboxes = row.querySelectorAll('.permission-checkbox');
                let permissionsId = [];
                permissionCheckboxes.forEach((checkbox) => {
                    if (checkbox.checked) {
                        permissionsId.push(checkbox.value); // это ID роли
                    }
                });

                fieldsData.push({
                    id: fieldId,
                    name: fieldName,
                    field_type: fieldType,
                    permissions_id: permissionsId,
                });
            });

            // Теперь отправляем на сервер AJAX-запрос

            $.ajax({
                url: "{{ route('admin.field.store') }}",
                type: "POST",
                data: JSON.stringify({ fields: fieldsData }),
                contentType: "application/json",
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                success: function(data) {
                    showSuccessModal("Обновление полей", "Пользовательские поля успешно обновлены.", 1);

                },
                error: function(xhr, status, error) {
                    $('#error-message').text('Произошла ошибка при сохранении данных.'); // Устанавливаем сообщение ошибки
                    $('#errorModal').modal('show');    // Показываем модалку ошибки
                    $('#fieldModal').modal('hide');       // Закрываем текущую модалку
                }
            });

            });

        // Отображение модалки удаления
        $(document).on('click', '.confirm-delete-field-modal', function () {
            const row = $(this).closest('tr');
            fieldIdToDelete = row.attr('id'); // Получаем значение из "id"
            deleteField()
        });

        //Удаление доп. поля
        function deleteField() {
            // Показываем ту же модалку, но логика при клике — другая
            showConfirmDeleteModal(
                "Удаление поля",
                "Вы уверены, что хотите удалить это поле?",
                function () {
                    console.log(fieldIdToDelete);

                    if (fieldIdToDelete) {
                        $(`#fields-table tr[id=${fieldIdToDelete}]`).remove();
                    }
                }
            );
        }
    });

</script>