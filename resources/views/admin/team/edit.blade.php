@extends('layouts/main2')
@extends('layouts.admin2')

@section('content')

    <div class="col-md-9 main-content team-data">

        <h4 class="mt-3">Редактирование группы</h4>

        <form action="{{ route('admin.team.update', $team->id)}}" method="post">
            @csrf
            @method('patch')
            <div class="mb-3">
                <label for="title" class="form-label">Название группы*</label>
                <input type="text" name="title" class="form-control" id="title" value="{{$team->title}}">
                @error('title' )
                <p class="text-danger">{{'Введите название'}}</p>
                @enderror
            </div>

            <div class="mb-3">
                <div class="form-group">
                    <label for="weekdays">Расписание</label>
                    <select multiple class="form-control" id="weekdays" name="weekdays[]">
                        @foreach($weekdays as $weekday)
                            <option
                                    @foreach($team->weekdays as $teamWeekday)
                                        {{$weekday->id === $teamWeekday->id ? 'selected' : ''}}
                                    @endforeach
                                    value="{{$weekday->id}}">{{$weekday->title}}</option>
                        @endforeach
                    </select>
                    @error('weekdays')
                    <p class="text-danger">{{'Укажите дни недели'}}</p>
                    @enderror
                </div>
            </div>

            <div class="mb-3">
                <label for="order_by" class="form-label">Сортировка</label>
                <input type="text" name="order_by" class="form-control" id="order_by" value="{{ $team->order_by }}">
            </div>

            <div class="mb-3">
                <label for="activity">Активность</label>
                <select name="is_enabled" class="form-control" id='activity' name='activity'>
                    @for($i=0; $i<2;$i++)
                        <option
                                {{ $i == $team->is_enabled ? 'selected' : ''}}
                                value=" {{ $i }} ">
                            @if($i == 0)
                                {{"Неактивен"}}
                            @else
                                {{"Активен"}}
                            @endif
                        </option>
                    @endfor
                </select>
            </div>

            <span><a class="btn btn-danger" href="{{ route('admin.team.index') }}">Назад</a></span>
            <button type="submit" class="btn btn-primary">Обновить</button>
        </form>

        <!-- Кнопка для удаления с модальным подтверждением -->
        <form class="mt-3" id="delete-team-form" action="{{ route('admin.team.delete', $team->id)}}" method="post">
            @csrf
            @method('delete')
            <input type="button" value="Удалить" class="btn btn-danger" id="delete-team-btn">
        </form>

        <!-- Модальное окно для подтверждения удаления -->
        <div class="modal fade" id="confirmDeleteModal" tabindex="-1" aria-labelledby="confirmDeleteModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="confirmDeleteModalLabel">Подтверждение удаления</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        Вы уверены, что хотите удалить эту группу?
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-primary" id="confirmDeleteBtn">Да</button>
                        <button type="button" class="btn btn-danger" data-bs-dismiss="modal">Нет</button>
                    </div>
                </div>
            </div>
        </div>

    </div>
@endsection

@section('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // Получаем форму и кнопки
            const deleteForm = document.getElementById('delete-team-form');
            const deleteButton = document.getElementById('delete-team-btn');
            const confirmDeleteButton = document.getElementById('confirmDeleteBtn');

            // Отключаем стандартное поведение кнопки "Удалить" и показываем модалку
            deleteButton.addEventListener('click', function (event) {
                event.preventDefault(); // Останавливаем стандартное поведение
                $('#confirmDeleteModal').modal('show'); // Показываем модалку
            });

            // Обрабатываем нажатие на кнопку "Да" в модальном окне для удаления
            confirmDeleteButton.addEventListener('click', function () {
                $('#confirmDeleteModal').modal('hide'); // Закрываем модалку
                deleteForm.submit(); // Отправляем форму для удаления
            });
        });
    </script>
@endsection
