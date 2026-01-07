
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
                <form id="teamForm" class="text-start" action="{{ route('admin.team.store') }}" method="post">
                    @csrf
                    <div class="mb-3">
                        <label for="title" class="form-label">Название группы*</label>
                        <input type="text"
                               name="title"
                               class="form-control @error('title') is-invalid @enderror"
                               id="title"
                               value="{{ old('title') }}">
                        <div id="title-error" class="invalid-feedback">
                            @error('title'){{ $message }}@enderror
                        </div>
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

                    <div class="modal-footer-create-team">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Закрыть</button>
                        <button type="submit" class="btn btn-primary">Создать</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<!-- Модальное окно подтверждения удаления -->
{{--@include('includes.modal.confirmDeleteModal')--}}

<!-- Модальное окно успешного обновления данных -->
{{--@include('includes.modal.successModal')--}}

<!-- Модальное окно ошибки -->
{{--@include('includes.modal.errorModal')--}}


<script>
    document.addEventListener('DOMContentLoaded', function () {
        function createTeam() {
            const teamForm = document.getElementById('teamForm');
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
                        return response.json().then(data => ({ ok: response.ok, status: response.status, data }));
                    })
                    .then(({ ok, status, data }) => {
                        // Сброс ошибок
                        const titleInput = document.getElementById('title');
                        const titleError = document.getElementById('title-error');
                        titleInput.classList.remove('is-invalid');
                        if (titleError) titleError.textContent = '';

                        if (!ok && status === 422) {
                            const errors = data?.errors || {};
                            if (errors.title?.length) {
                                titleInput.classList.add('is-invalid');
                                if (titleError) titleError.textContent = errors.title[0];
                            }
                            return;
                        }

                        if (!ok) {
                            throw new Error(`Ошибка HTTP: ${status}`);
                        }

                        if (data.message) {
                            showSuccessModal("Создание группы", "Группа успешно создана.", 1);
                        } else {
                            throw new Error('Произошла ошибка при создании группы.');
                        }
                    })
                    .catch(error => {
                        $('#errorModal').modal('show');
                    });
            });


        }
        createTeam();
    });
</script>
