<!-- Modal -->

<!-- Модальное окно -->
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

<script>
    // Если нужно что-то еще настроить или добавить функционал, например, очистку формы, можно добавить обработчик событий
    document.addEventListener('DOMContentLoaded', function () {
        const teamForm = document.getElementById('teamForm');

        teamForm.addEventListener('submit', function (e) {
            e.preventDefault();  // Останавливаем стандартную отправку для проверки перед отправкой
            teamForm.submit();   // Если все ок, отправляем форму
        });
    });
</script>
