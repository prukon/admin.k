<!-- Подключение CSS Bootstrap (в head) -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-...ваш-интегрити-ключ..." crossorigin="anonymous">

<!-- Ваш HTML-контент, включая модальные окна и форму -->

<!-- Модальное окно для создания группы -->
<div class="modal fade" id="createTeamModal" tabindex="-1" aria-labelledby="createTeamModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="createTeamModalLabel">Создание группы</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
            </div>
            <div class="modal-body">
                <form id="teamForm" action="{{ route('admin.team.store') }}" method="post">
                    @csrf
                    <div class="mb-3">
                        <label for="title" class="form-label">Название группы*</label>
                        <input type="text" name="title" class="form-control" id="title" value="{{ old('title') }}">
                        @error('title')
                        <p class="text-danger">{{ 'Введите название' }}</p>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <div class="form-group">
                            <label for="weekdays">Расписание*</label>
                            <div id="weekdays">
                                @foreach($weekdays as $weekday)
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="weekday-{{ $weekday->id }}" name="weekdays[]" value="{{ $weekday->id }}">
                                        <label class="form-check-label" for="weekday-{{ $weekday->id }}">
                                            {{ $weekday->title }}
                                        </label>
                                    </div>
                                @endforeach
                            </div>
                            @error('weekdays')
                            <p class="text-danger">{{ 'Укажите дни недели' }}</p>
                            @enderror
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="order_by" class="form-label">Сортировка</label>
                        <input type="text" name="order_by" placeholder="10" class="form-control" id="order_by" value="{{ old('order_by') }}">
                    </div>

                    <div class="mb-3">
                        <label for="activity">Активность</label>
                        <select name="is_enabled" class="form-control" id='activity'>
                            <option value="1">Активен</option>
                            <option value="0">Неактивен</option>
                        </select>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Закрыть</button>
                        <button type="submit" class="btn btn-primary">Создать</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Модальное окно для успешного создания -->
<div class="modal fade" id="successModal" tabindex="-1" aria-labelledby="successModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="successModalLabel">Успех</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
            </div>
            <div class="modal-body" id="successModalBody">
                Группа создана успешно!
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Закрыть</button>
            </div>
        </div>
    </div>
</div>

<!-- Подключение JavaScript Bootstrap и ваш скрипт для работы с модальными окнами -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js" integrity="sha384-...ваш-интегрити-ключ..." crossorigin="anonymous"></script>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const teamForm = document.getElementById('teamForm');
        const createTeamModalElement = document.getElementById('createTeamModal');
        const successModalElement = document.getElementById('successModal');

        teamForm.addEventListener('submit', function (e) {
            e.preventDefault();  // Останавливаем стандартную отправку формы

            // Собираем данные формы
            const formData = new FormData(teamForm);

            // Отправка данных с использованием AJAX
            fetch(teamForm.action, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',  // Обозначаем, что это AJAX-запрос
                    'X-CSRF-TOKEN': document.querySelector('input[name="_token"]').value
                }
            })
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`Ошибка HTTP: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.message) {
                        // Закрываем модалку создания группы
                        const createTeamModal = bootstrap.Modal.getInstance(createTeamModalElement);
                        createTeamModal.hide();

                        // Обновляем текст модального окна успешного создания группы
                        const successModalBody = document.getElementById('successModalBody');
                        successModalBody.textContent = `Группа "${data.team.title}" создана успешно!`;

                        // Показываем модальное окно успешного создания группы
                        const successModal = new bootstrap.Modal(successModalElement);
                        successModal.show();

                        // Очищаем форму
                        teamForm.reset();
                    } else {
                        throw new Error('Произошла ошибка при создании группы.');
                    }
                })
                .catch(error => {
                    console.error('Ошибка:', error.message);
                    alert('Произошла ошибка при создании группы.');
                });
        });

        // Добавляем перезагрузку страницы после закрытия `successModal`
        successModalElement.addEventListener('hidden.bs.modal', function () {
            window.location.reload();
        });
    });
</script>
