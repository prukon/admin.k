<!-- Модальное окно для настройки полей -->
<div class="modal fade" id="fieldModal" tabindex="-1" aria-labelledby="fieldModalLabel" aria-hidden="true">
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
                        <tr data-id="{{ $field->id }}">
                            <td>{{ $index + 1 }}</td>
                            <td><input type="text" class="form-control field-name" value="{{ $field->name }}">
                                <div class="invalid-feedback" style="display: none;">Заполните поле</div>
                            </td>

                            <td>
                                <select class="form-select field-type">
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
                                <button type="button" class="btn btn-danger btn-sm delete-field-btn">Удалить</button>
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
<div class="modal fade errorFieldModal" id="errorFieldModal" tabindex="-1" aria-labelledby="errorModalLabel"
     aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="errorModalLabel">Ошибка</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-danger" id="error-message">
                    Произошла ошибка при сохранении данных.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Закрыть</button>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Модальное окно успешного сохранения -->
<div class="modal fade successFieldModal" id="successFieldModal" tabindex="-1" aria-labelledby="successModalLabel"
     aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="successModalLabel">Поле успешно сохранено</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-success" id="success-message">
                    Поле было успешно сохранено.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" data-bs-dismiss="modal">ОК</button>
            </div>
        </div>
    </div>
</div>


<!-- Модальное окно подтверждения удаления -->
<div class="modal fade confirmDeleteModal" id="confirmDeleteModal" tabindex="-1"
     aria-labelledby="confirmDeleteModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="confirmDeleteModalLabel">Подтвердите удаление</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                Вы уверены, что хотите удалить это поле?
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                <button type="button" class="btn btn-danger" id="confirmDeleteBtn">Удалить</button>
            </div>
        </div>
    </div>
</div>


<!-- Модальное окно успешного обновления данных -->
<div class="modal fade" id="dataUpdatedModal" tabindex="-1" aria-labelledby="dataUpdatedModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="dataUpdatedModalLabel">Данные успешно обновлены</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-success">
                    Данные были успешно обновлены.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" data-bs-dismiss="modal">ОК</button>
            </div>
        </div>
    </div>
</div>


<script>
    $(document).ready(function () {
        let fieldIdToDelete = null; // ID поля для удаления

        // Функция для добавления новой строки в таблицу
        $('#new-field-btn').on('click', function () {
            const newRow = `
            <tr>
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
                <td><button type="button" class="btn btn-danger btn-sm delete-field-btn">Удалить</button></td>
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

        // Удаление поля с подтверждением
        $(document).on('click', '.delete-field-btn', function () {
            const row = $(this).closest('tr');
            fieldIdToDelete = row.data('id'); // Сохраняем ID для удаления
            $('#confirmDeleteModal').modal('show'); // Показываем модалку подтверждения
        });

        // Подтверждение удаления
        $('#confirmDeleteBtn').on('click', function () {
            if (fieldIdToDelete) {
                $(`#fields-table tr[data-id=${fieldIdToDelete}]`).remove(); // Удаляем строку из таблицы
                $('#confirmDeleteModal').modal('hide'); // Закрываем модалку
            }
        });

        // Сохранение изменений
        $('#save-fields-btn').on('click', function () {
            let fieldsData = [];
            let isValid = true;

            $('#fields-table tbody tr').each(function () {
                const id = $(this).data('id'); // ID, если поле уже существует в базе данных
                const name = $(this).find('.field-name').val();
                const fieldType = $(this).find('.field-type').val();
                const slug = generateSlug(name); // Генерация slug из name

                // Проверка на заполненность поля "название"
                if (!name) {
                    $(this).find('.invalid-feedback').show(); // Показываем ошибку
                    isValid = false;
                } else {
                    $(this).find('.invalid-feedback').hide(); // Скрываем ошибку, если поле заполнено
                }
                console.log(slug);

                fieldsData.push({
                    id: id || null,
                    name: name,
                    slug: slug,  // Отправляем slug на сервер
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
                    $('#dataUpdatedModal').modal('show'); // Показываем модалку успешного обновления
                    $('#fieldModal').modal('hide'); // Закрываем текущую модалку
                    // location.reload(); // Перезагружаем страницу после успешного добавления
                },
                error: function (response) {
                    let errorMessage = 'Произошла ошибка при сохранении данных.';
                    if (response.responseJSON && response.responseJSON.message) {
                        errorMessage = response.responseJSON.message; // Используем сообщение с сервера, если оно есть
                    }
                    $('#error-message').text(errorMessage); // Устанавливаем сообщение ошибки
                    $('#errorFieldModal').modal('show'); // Показываем модалку ошибки
                }
            });
        });

        // Генерация slug из name
        function generateSlug(name) {
            if (!name) return ''; // Если имя пустое, возвращаем пустую строку
            return name
                .toString()
                .toLowerCase()
                .replace(/\s+/g, '-') // Заменяем пробелы на дефисы
                .replace(/[^\w\-]+/g, '') // Убираем все символы, кроме букв, цифр и дефисов
                .replace(/--+/g, '-') // Заменяем несколько дефисов на один
                .trim('-'); // Убираем дефис в начале и в конце
        }
    });

    // Перезагрузка страницы при нажатии "ОК" в модалке успешного обновления
    $('#dataUpdatedModal .btn-primary').on('click', function () {
        location.reload(); // Перезагружаем страницу
    });
</script>