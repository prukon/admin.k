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
                                    <option value="text" {{ $field->field_type == 'text'   ? 'selected' : '' }}>
                                        Многострочный текст
                                    </option>
                                    <option value="select" {{ $field->field_type == 'select' ? 'selected' : '' }}>
                                        Список
                                    </option>
                                </select>
                            </td>
                            <td>
                                @php
                                    // Берём ID ролей прямо из pivot user_field_role
                                    $allowed = $field->roles->pluck('id')->all();
                                @endphp

                                @foreach($roles as $role)
                                    @continue($role->name === 'superadmin')
                                    <div class="form-check">
                                        <input
                                                class="form-check-input permission-checkbox"
                                                type="checkbox"
                                                value="{{ $role->id }}"
                                                id="permission-{{ $role->id }}-field-{{ $field->id }}"
                                                {{ in_array($role->id, $allowed) ? 'checked' : '' }}
                                        >
                                        <label class="form-check-label"
                                               for="permission-{{ $role->id }}-field-{{ $field->id }}">
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
{{--@include('includes.modal.errorModal')--}}

<!-- Модальное окно подтверждения удаления -->
{{--@include('includes.modal.confirmDeleteModal')--}}

<!-- Модальное окно успешного обновления данных -->
{{--@include('includes.modal.successModal')--}}

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
                        ${role.label ?? role.name}
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
            const rows = document.querySelectorAll('#fields-table tbody tr');
            const fieldsData = [];

            rows.forEach(row => {
                const fieldId = row.getAttribute('data-id');
                const fieldName = row.querySelector('.field-name').value;
                const fieldType = row.querySelector('.field-type').value;

                // Собираем отмеченные роли
                const checked = [...row.querySelectorAll('.permission-checkbox:checked')]
                    .map(cb => parseInt(cb.value, 10));

                fieldsData.push({
                    id: fieldId || null,
                    name: fieldName,
                    field_type: fieldType,
                    roles: checked,      // теперь ключ называется roles
                });
            });

            $.ajax({
                url: "{{ route('admin.field.store') }}",
                type: "POST",
                contentType: "application/json",
                headers: {'X-CSRF-TOKEN': '{{ csrf_token() }}'},
                data: JSON.stringify({fields: fieldsData}),
                success() {
                    showSuccessModal("Обновление полей", "Пользовательские поля успешно обновлены.", 1);
                },
                error() {
                    $('#error-modal-message').text('Произошла ошибка при сохранении данных.');
                    $('#errorModal').modal('show');
                    $('#fieldModal').modal('hide');
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