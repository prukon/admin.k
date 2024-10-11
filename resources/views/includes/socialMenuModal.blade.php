<!-- Подключение CSS Bootstrap -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

<!-- Подключение JavaScript Bootstrap и Popper -->
<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.6/dist/umd/popper.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.min.js"></script>

<!-- Модальное окно настройки социальных сетей -->
<div class="modal fade mt-3" id="socialMenuModal" tabindex="-1" aria-labelledby="socialMenuModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" style="margin: 0 auto;"> <!-- Установлено горизонтальное центрирование -->
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="socialMenuModalLabel">Настройка социальных сетей</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
            </div>
            <div class="modal-body">
                <div class="table-responsive"> <!-- Добавлен контейнер для адаптивности таблицы -->
                    <table class="table" id="socialTable">
                        <thead>
                        <tr>
                            <th>Социальная сеть</th>
                            <th>Название</th>
                            <th>Ссылка</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach ($socialItems as $item)
                            <tr data-id="{{ $item->id }}">
                                <td>{{ $loop->index + 1 }}</td>
                                <td>
                                    <input type="text" name="social_items[{{ $item->id }}][name]" class="form-control" value="{{ $item->name }}" readonly>
                                    <div class="text-danger error-message"></div> <!-- Контейнер для ошибки названия -->
                                </td>
                                <td>
                                    <input type="text" name="social_items[{{ $item->id }}][link]" class="form-control" value="{{ $item->link }}">
                                    <div class="text-danger error-message"></div> <!-- Контейнер для ошибки ссылки -->
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                <button type="button" class="btn btn-primary" id="saveSocialMenu">Сохранить</button>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        console.log('Document is ready');
        var token = '{{ csrf_token() }}';

        // Кнопка для сохранения данных
        document.getElementById('saveSocialMenu').addEventListener('click', function() {
            console.log('Saving menu...');
            const formData = new FormData();

            // Очищаем предыдущие сообщения об ошибках
            document.querySelectorAll('.error-message').forEach((el) => el.textContent = '');

            // Собираем данные из таблицы
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
                        alert('Социальные сети успешно сохранены.');
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
