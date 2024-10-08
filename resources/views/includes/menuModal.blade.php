
<!-- Подключение CSS Bootstrap -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

<!-- Подключение JavaScript Bootstrap и Popper -->
<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.6/dist/umd/popper.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.min.js"></script>

<div class="modal fade" id="menuModal" tabindex="-1" aria-labelledby="menuModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="menuModalLabel">Настройка меню в шапке</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
            </div>
            <div class="modal-body">
                <button type="button" class="btn btn-secondary mb-3" id="addMenuItem">Новый пункт меню</button>
                <table class="table" id="menuTable">
                    <thead>
                    <tr>
                        <th>Пункт меню</th>
                        <th>Название</th>
                        <th>Ссылка</th>
                        <th>Открывать в новой вкладке</th>
                        <th>Действия</th>
                    </tr>
                    </thead>
                    {{--                    <tbody>--}}
                    {{--                    @foreach ($menuItems as $item)--}}
                    {{--                        <tr data-id="{{ $item->id }}">--}}
                    {{--                            <td>{{ $loop->index + 1 }}</td>--}}
                    {{--                            <td><input type="text" name="menu_items[{{ $item->id }}][name]" class="form-control" value="{{ $item->name }}"></td>--}}
                    {{--                            <td><input type="text" name="menu_items[{{ $item->id }}][link]" class="form-control" value="{{ $item->link }}"></td>--}}
                    {{--                            <td class="text-center"><input type="checkbox" name="menu_items[{{ $item->id }}][target_blank]" value="1" {{ $item->target_blank ? 'checked' : '' }}></td>--}}
                    {{--                            <td><button type="button" class="btn btn-danger btn-sm deleteRow">Удалить</button></td>--}}
                    {{--                        </tr>--}}
                    {{--                    @endforeach--}}
                    {{--                    </tbody>--}}
                    {{--                    --}}


                    {{--                    <tbody>--}}
                    {{--                    @foreach ($menuItems as $item)--}}
                    {{--                        <tr data-id="{{ $item->id }}">--}}
                    {{--                            <td>{{ $loop->index + 1 }}</td>--}}
                    {{--                            <td>--}}
                    {{--                                <input type="text" name="menu_items[{{ $item->id }}][name]" class="form-control" value="{{ $item->name }}">--}}
                    {{--                                <div class="text-danger error-message"></div> <!-- Контейнер для ошибки -->--}}
                    {{--                            </td>--}}
                    {{--                            <td><input type="text" name="menu_items[{{ $item->id }}][link]" class="form-control" value="{{ $item->link }}"></td>--}}
                    {{--                            <td class="text-center"><input type="checkbox" name="menu_items[{{ $item->id }}][target_blank]" value="1" {{ $item->target_blank ? 'checked' : '' }}></td>--}}
                    {{--                            <td><button type="button" class="btn btn-danger btn-sm deleteRow">Удалить</button></td>--}}
                    {{--                        </tr>--}}
                    {{--                    @endforeach--}}
                    {{--                    </tbody>--}}

                    <tbody>
                    @foreach ($menuItems as $item)
                        <tr data-id="{{ $item->id }}">
                            <td>{{ $loop->index + 1 }}</td>
                            <td>
                                <input type="text" name="menu_items[{{ $item->id }}][name]" class="form-control" value="{{ $item->name }}" data-key="menu_items[{{ $item->id }}][name]">
                                <div class="text-danger error-message"></div> <!-- Контейнер для ошибки -->
                            </td>
                            <td><input type="text" name="menu_items[{{ $item->id }}][link]" class="form-control" value="{{ $item->link }}"></td>
                            <td class="text-center"><input type="checkbox" name="menu_items[{{ $item->id }}][target_blank]" value="1" {{ $item->target_blank ? 'checked' : '' }}></td>
                            <td><button type="button" class="btn btn-danger btn-sm deleteRow">Удалить</button></td>
                        </tr>
                    @endforeach
                    </tbody>




                </table >
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                <button type="button" class="btn btn-primary" id="saveMenu">Сохранить</button>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        console.log('Document is ready');
        var token = '{{ csrf_token() }}';

        const menuTable = document.getElementById('menuTable').querySelector('tbody');
        let newItemIndex = {{ $menuItems->count() }} + 1;
        const deletedItems = []; // Массив для хранения ID удаленных элементов

        // Кнопка для добавления нового пункта меню
        document.getElementById('addMenuItem').addEventListener('click', function() {
            const newRow = document.createElement('tr');

            newRow.innerHTML = `
    <td>${newItemIndex}</td>
    <td>
        <input type="text" name="menu_items[new_${newItemIndex}][name]" class="form-control" data-key="menu_items[new_${newItemIndex}][name]">
        <div class="text-danger error-message"></div> <!-- Контейнер для ошибки -->
    </td>
    <td><input type="text" name="menu_items[new_${newItemIndex}][link]" class="form-control"></td>
    <td class="text-center"><input type="checkbox" name="menu_items[new_${newItemIndex}][target_blank]" value="1"></td>
    <td><button type="button" class="btn btn-danger btn-sm deleteRow">Удалить</button></td>
`;



            menuTable.appendChild(newRow);
            newItemIndex++;
        });

        // Обработчик для удаления строки
        menuTable.addEventListener('click', function(event) {
            if (event.target.classList.contains('deleteRow')) {
                const row = event.target.closest('tr');
                const id = row.getAttribute('data-id');
                if (id) {
                    deletedItems.push(id); // Сохраняем ID удаленных элементов
                }
                row.remove(); // Удаляем строку из интерфейса
            }
        });

        // Кнопка для сохранения данных
        document.getElementById('saveMenu').addEventListener('click', function() {
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
                    if (!response.ok) {
                        return response.json().then(data => {
                            throw new Error(JSON.stringify(data.errors));
                        });
                    }
                    return response.json();
                })
                .then(response => {
                    if (response.success) {
                        alert('Меню успешно сохранено.');
                        location.reload();
                    }
                })
                .catch(error => {
                    const errors = JSON.parse(error.message);
                    Object.keys(errors).forEach((key) => {
                        const inputWithError = document.querySelector(`input[name="${key}"]`);
                        if (inputWithError) {
                            const errorContainer = inputWithError.nextElementSibling;
                            errorContainer.textContent = errors[key][0]; // Отображаем первую ошибку для данного поля
                        }
                    });
                });
        });


    });
</script>