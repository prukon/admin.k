{{-- resources/views/schedule/index.blade.php --}}

{{--@extends('layouts.app')--}}
@extends('layouts.admin2')



@section('content')
    <div class="container-fluid">
        <h1>Журнал расписания</h1>

        <!-- Фильтры -->
        <form method="GET" action="{{ route('schedule.index') }}" class="mb-3">
            <div class="row">
                <div class="col-md-2">
                    <label for="year" class="form-label">Год</label>
                    <select name="year" id="year" class="form-select">
                        @for($y = date('Y') - 5; $y <= date('Y') + 5; $y++)
                            <option value="{{ $y }}" {{ $year == $y ? 'selected' : '' }}>{{ $y }}</option>
                        @endfor
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="month" class="form-label">Месяц</label>
                    <select name="month" id="month" class="form-select">
                        @for($m = 1; $m <= 12; $m++)
                            <option value="{{ $m }}" {{ $month == $m ? 'selected' : '' }}>
                                {{ \Carbon\Carbon::create(null, $m, 1)->format('F') }}
                            </option>
                        @endfor
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="team" class="form-label">Группа</label>
                    <select name="team" id="team" class="form-select">
                        <option value="all" {{ $teamFilter === 'all' ? 'selected' : '' }}>Все группы</option>
                        <option value="none" {{ $teamFilter === 'none' ? 'selected' : '' }}>Без группы</option>
                        @foreach($teams as $team)
                            <option value="{{ $team->id }}" {{ $teamFilter == $team->id ? 'selected' : '' }}>{{ $team->title }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2 align-self-end">
                    <button type="submit" class="btn btn-primary">Применить</button>
                </div>
            </div>
        </form>

        <!-- Легенда для мобильных устройств (сворачиваемая) -->
        <div class="d-md-none mb-3">
            <button class="btn btn-info" type="button" data-bs-toggle="collapse" data-bs-target="#legendCollapse" aria-expanded="false" aria-controls="legendCollapse">
                Показать легенду
            </button>
            <div class="collapse" id="legendCollapse">
                <div class="card card-body">
                    <p><i class="fa-solid fa-check" style="color:green;"></i> - Оплата месяца (столбец "Статус")</p>
                    <p><span style="color:red; font-weight:bold;">Н</span> - Негативный статус</p>
                    <p><i class="fa-solid fa-check" style="color:yellow;"></i> - Рабочий день</p>
                    <p><i class="fa-solid fa-snowflake" style="color:deepskyblue;"></i> - Заморозка</p>
                    <p><span class="comment-indicator"></span> - Наличие комментария</p>
                </div>
            </div>
        </div>

        <!-- Легенда для десктопа -->
        <div class="d-none d-md-block mb-3">
            <div class="card card-body">
                <p><i class="fa-solid fa-check" style="color:green;"></i> - Оплата месяца (столбец "Статус")</p>
                <p><span style="color:red; font-weight:bold;">Н</span> - Негативный статус (ячейка)</p>
                <p><i class="fa-solid fa-check" style="color:yellow;"></i> - Рабочий день (ячейка)</p>
                <p><i class="fa-solid fa-snowflake" style="color:deepskyblue;"></i> - Заморозка (ячейка)</p>
                <p><span class="comment-indicator"></span> - Наличие комментария (ячейка)</p>
            </div>
        </div>

        <!-- Таблица расписания -->
        <div class="table-responsive">
            <table id="scheduleTable" class="table table-bordered table-sm">
                <thead>
                <tr>
                    <th>ФИО</th>
                    <th>Статус</th>
                    @for($d = 1; $d <= $daysInMonth; $d++)
                        @php
                            $currentDate = \Carbon\Carbon::create($year, $month, $d);
                            $weekday = $currentDate->dayOfWeekIso; // 1 - понедельник, 7 - воскресенье
                            $highlight = (isset($teamWeekdays) && in_array($weekday, $teamWeekdays)) ? 'border border-1' : '';
                        @endphp
                        <th class="{{ $highlight }}">
                            {{ $d }}<br>{{ $currentDate->format('D') }}
                        </th>
                    @endfor
                </tr>
                </thead>
                <tbody>
                @foreach($users as $user)
                    <tr>
                        <td>{{ $user->name }}</td>
                        <td class="text-center">
                            @if(isset($userPayments[$user->id]))
                                <i class="fa-solid fa-check" style="color:green;"></i>
                            @endif
                        </td>
                        @for($d = 1; $d <= $daysInMonth; $d++)
                            @php
                                $dateStr = \Carbon\Carbon::create($year, $month, $d)->toDateString();
                                $schedule = isset($scheduleRecords[$user->id]) ? $scheduleRecords[$user->id]->firstWhere('date', $dateStr) : null;
                                $cellCode = '';
                                $cellComment = '';
                                if($schedule && $schedule->description){
                                    if(str_contains($schedule->description, '::')){
                                        list($cellCode, $cellComment) = explode('::', $schedule->description, 2);
                                    } else {
                                        $cellCode = $schedule->description;
                                    }
                                }
                                $cellContent = '';
                                if($cellCode === 'N'){
                                    $cellContent = '<span style="color:red; font-weight:bold;">Н</span>';
                                } elseif($cellCode === 'R'){
                                    $cellContent = '<i class="fa-solid fa-check" style="color:yellow;"></i>';
                                } elseif($cellCode === 'Z'){
                                    $cellContent = '<i class="fa-solid fa-snowflake" style="color:deepskyblue;"></i>';
                                }
                            @endphp
                            <td class="schedule-cell" data-user-id="{{ $user->id }}" data-date="{{ $dateStr }}"
                                style="width: 10px; height: 10px; padding:0; text-align: center; vertical-align: middle; position: relative; cursor: pointer;">
                                {!! $cellContent !!}
                                @if($cellComment)
                                    <div style="position: absolute; top: 0; right: 0; width: 0; height: 0; border-top: 10px solid red; border-left: 10px solid transparent;"></div>
                                @endif
                            </td>
                        @endfor
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <!-- Модальное окно для редактирования ячейки -->
    <div class="modal fade" id="cellModal" tabindex="-1" aria-labelledby="cellModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <form id="cellForm">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="cellModalLabel">Редактирование ячейки</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="user_id" id="modalUserId">
                        <input type="hidden" name="date" id="modalDate">
                        <div class="mb-3">
                            <label for="code" class="form-label">Статус дня</label>
                            <select name="code" id="code" class="form-select">
                                <option value="">-- Выберите --</option>
                                <option value="N">Н</option>
                                <option value="R">Рабочий день</option>
                                <option value="Z">Заморозка</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="comment" class="form-label">Комментарий</label>
                            <textarea name="comment" id="comment" class="form-control" rows="2"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                        <button type="submit" class="btn btn-primary">Сохранить</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
@endsection

@section('scripts')
    <script>
        $(document).ready(function(){
            // Инициализация DataTables
            $('#scheduleTable').DataTable({
                paging: false,
                searching: false,
                info: false,
                scrollX: true,
            });

            // Обработчик клика (или правого клика) по ячейке расписания
            $('.schedule-cell').on('contextmenu click', function(e){
                e.preventDefault();
                var userId = $(this).data('user-id');
                var date = $(this).data('date');
                // Попытка определить текущий статус ячейки по содержимому
                var currentContent = $(this).html();
                var code = '';
                if(currentContent.indexOf('fa-check') !== -1 && currentContent.indexOf('yellow') !== -1){
                    code = 'R';
                } else if(currentContent.indexOf('fa-snowflake') !== -1){
                    code = 'Z';
                } else if(currentContent.indexOf('Н') !== -1){
                    code = 'N';
                }

                $('#modalUserId').val(userId);
                $('#modalDate').val(date);
                $('#code').val(code);
                $('#comment').val(''); // можно расширить логику для парсинга комментария, если потребуется

                var modal = new bootstrap.Modal(document.getElementById('cellModal'));
                modal.show();
            });

            // Отправка данных из модального окна (AJAX-запрос)
            $('#cellForm').submit(function(e){
                e.preventDefault();
                $.ajax({
                    url: "{{ route('schedule.update') }}",
                    method: "POST",
                    data: $(this).serialize(),
                    headers: {
                        'X-CSRF-TOKEN': "{{ csrf_token() }}"
                    },
                    success: function(response){
                        if(response.status === 'success'){
                            location.reload();
                        }
                    },
                    error: function(){
                        alert('Ошибка при сохранении данных.');
                    }
                });
            });
        });
    </script>
@endsection