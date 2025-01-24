<!-- Модальное окно для настройки полей -->
<div class="modal fade" id="fieldModal" tabindex="-1" aria-labelledby="fieldModalLabel">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fieldModalLabel" id="fieldModalLabel">Настройка пользовательских полей</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-start">
                <!-- Кнопка для добавления нового поля -->
                <button id="new-field-btn" type="button" class="btn btn-success mb-3">Новое поле</button>

                <!-- Таблица с существующими полями -->
                <table class="table table-bordered" id="fields-table">
                    <thead>
                    <tr>
                        <th>№</th>
                        <th>Название</th>
                        <th>Тип поля</th>
                        <th>Удалить</th>
                    </tr>
                    </thead>
                    <tbody>
                    <!-- Существующие поля будут загружаться сюда -->
                    @foreach($fields as $index => $field)
                        <tr data-id="{{ $field->id }}"  id="{{ $field->id }}">

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
                            <td>
                                <button type="button" class="btn btn-danger btn-sm confirm-delete-field-modal">Удалить</button>
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

            // Функция для добавления новой строки в таблицу
        $('#new-field-btn').on('click', function () {
            // Генерируем случайный ID в диапазоне от 10000 до 20000
            const randomId = Math.floor(Math.random() * (20000 - 10000 + 1)) + 10000;

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
            <td><button type="button" class="btn btn-danger btn-sm confirm-delete-field-modal">Удалить</button></td>
        </tr>
    `;
            $('#fields-table tbody').append(newRow);
            updateRowNumbers();
        });

        // Обновление номеров строк
        function updateRowNumbers() {
            $('#fields-table tbody tr').each(function (index) {
                $(this).find('td:first').text(index + 1);
            });
        }

        // Сохранение изменений
        $('#save-fields-btn').on('click', function () {
            let fieldsData = [];
            let isValid = true;

            $('#fields-table tbody tr').each(function () {
                const id = $(this).data('id'); // ID, если поле уже существует в базе данных
                const name = $(this).find('.field-name').val();
                const fieldType = $(this).find('.field-type').val();

                // Проверка на заполненность поля "название"
                if (!name) {
                    $(this).find('.invalid-feedback').show(); // Показываем ошибку
                    isValid = false;
                } else {
                    $(this).find('.invalid-feedback').hide(); // Скрываем ошибку, если поле заполнено
                }

                fieldsData.push({
                    id: id || null,
                    name: name,
                    // Поле slug убираем из запроса (т.к. оно формируется на сервере)
                    field_type: fieldType
                });
            });

            // Если хотя бы одно поле не заполнено, отменяем отправку данных
            if (!isValid) {
                return;
            }

            // Отправка данных на сервер
            $.ajax({
                url: '{{ route('admin.field.store') }}',  // Путь к контроллеру для сохранения
                method: 'POST',
                data: {
                    _token: '{{ csrf_token() }}',
                    fields: fieldsData
                },
                success: function (response) {
                    showSuccessModal("Обновление полей", "Пользовательские поля успешно обновлены.", 1);
                },
                error: function (response) {
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
                function() {
                    console.log(fieldIdToDelete);

                    if (fieldIdToDelete) {
                        $(`#fields-table tr[id=${fieldIdToDelete}]`).remove();
                    }
                }
            );
        }
    });

</script>