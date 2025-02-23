{{--@extends('layouts.app')--}}
@extends('layouts.admin2')





@section('content')
    <div class="container">
        <h2>Управление расписанием</h2>

        {{-- Форма выбора года и месяца --}}
        <form action="{{ route('schedule.index') }}" method="GET" class="row row-cols-lg-auto g-3 align-items-center mb-3">
            <div class="col-12">
                <label for="year" class="form-label">Год:</label>
                <select name="year" id="year" class="form-select">
                    @for($y = date('Y') - 1; $y <= date('Y') + 1; $y++)
                        <option value="{{ $y }}" {{ $y == $year ? 'selected' : '' }}>{{ $y }}</option>
                    @endfor
                </select>
            </div>
            <div class="col-12">
                <label for="month" class="form-label">Месяц:</label>
                <select name="month" id="month" class="form-select">
                    @for($m = 1; $m <= 12; $m++)
                        <option value="{{ $m }}" {{ $m == $month ? 'selected' : '' }}>
                            {{ $m }} ({{ \Carbon\Carbon::create()->month($m)->locale('ru')->monthName }})
                        </option>
                    @endfor
                </select>
            </div>

            <div class="col-12">
                <button type="submit" class="btn btn-primary">Показать</button>
            </div>
        </form>

        {{-- Обёртка, чтобы DataTables мог корректно фиксировать заголовок и показывать скроллы --}}
        <div class="table-responsive">
            <table id="scheduleTable" class="table table-bordered table-hover table-sm" style="width: 100%;">
                <thead class="table-light">
                <tr>
                    <th style="min-width: 150px;">ФИО</th>
                    @for($d = 1; $d <= $daysInMonth; $d++)
                        <th class="text-center" style="min-width: 40px;">{{ $d }}</th>
                    @endfor
                </tr>
                </thead>
                <tbody>
                @foreach($users as $user)
                    <tr>
                        <td>{{ $user->name }}</td>
                        @for($d = 1; $d <= $daysInMonth; $d++)
                            @php
                                $currentDate = \Carbon\Carbon::create($year, $month, $d)->format('Y-m-d');
                                $key = $user->id . '_' . $currentDate;

                                // Безопасно извлекаем запись (иначе может быть null).
                                $entry = optional($scheduleData->get($key))->first();

                                // Статус (R, Z, N) и признак оплаты
                                $status  = $entry ? $entry->status : 'N';
                                $isPaid  = $entry ? $entry->is_paid : 0;
                                $comment = $entry ? $entry->description : '';

                                // Цвет фона
                                $bgColor = '';
                                if ($status === 'R') {
                                    $bgColor = $isPaid
                                        ? 'background-color: #5cb85c;'   // Зеленый (оплачено)
                                        : 'background-color: #f0ad4e;';  // Желтый (не оплачено)
                                } elseif ($status === 'Z') {
                                    $bgColor = 'background-color: #5bc0de;'; // Синий
                                }
                            @endphp
                            <td
                                    class="schedule-cell text-center align-middle"
                                    style="cursor: pointer; {{ $bgColor }}"
                                    data-user-id="{{ $user->id }}"
                                    data-date="{{ $currentDate }}"
                                    data-status="{{ $status }}"
                                    data-is-paid="{{ $isPaid }}"
                                    data-comment="{{ $comment }}"
                            >
                                @if($status === 'R')
                                    <i class="fa fa-check"></i>
                                @elseif($status === 'Z')
                                    <i class="fa fa-times"></i>
                                @endif
                                {{-- Если N, оставляем пусто --}}
                            </td>
                        @endfor
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>

    {{-- Модальное окно для изменения статуса --}}
    <div class="modal fade" id="scheduleModal" tabindex="-1" aria-labelledby="scheduleModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form id="scheduleForm">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title" id="scheduleModalLabel">Изменить расписание</h5>
                        <!-- Крестик Bootstrap 5 -->
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">

                        <input type="hidden" id="formUserId" name="user_id">
                        <input type="hidden" id="formDate" name="date">

                        <div class="mb-3">
                            <label for="formStatus" class="form-label">Статус:</label>
                            <select name="status" id="formStatus" class="form-select">
                                <option value="N">N (не был)</option>
                                <option value="Z">Z (заморозка)</option>
                                <option value="R">R (рабочий день)</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="formIsPaid" class="form-label">Оплачен:</label>
                            <select name="is_paid" id="formIsPaid" class="form-select">
                                <option value="0">Нет</option>
                                <option value="1">Да</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="formComment" class="form-label">Комментарий:</label>
                            <textarea name="description" id="formComment" class="form-control"></textarea>
                        </div>

                    </div>
                    <div class="modal-footer">
                        <!-- Кнопка «Закрыть» Bootstrap 5 -->
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Закрыть</button>
                        <button type="submit" class="btn btn-primary">Сохранить</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@section('scripts')
    <!-- jQuery (если нужно для DataTables, можно без jQuery если использовать DataTables с ImportMap) -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    <!-- Popper & Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.9.2/dist/umd/popper.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.min.js"></script>

    <!-- DataTables -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.1/css/dataTables.bootstrap5.min.css">
    <script src="https://cdn.datatables.net/1.13.1/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.1/js/dataTables.bootstrap5.min.js"></script>

    <!-- FixedHeader плагин для DataTables (фиксирует шапку при прокрутке) -->
    <link rel="stylesheet" href="https://cdn.datatables.net/fixedheader/3.3.1/css/fixedHeader.bootstrap5.min.css">
    <script src="https://cdn.datatables.net/fixedheader/3.3.1/js/dataTables.fixedHeader.min.js"></script>

    <!-- Font Awesome (иконки галочка/крестик) -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">

    <style>
        /* Чтобы горизонтальный скролл был всегда виден, указываем фиксированную высоту для тела таблицы */
        div.dataTables_wrapper div.dataTables_scrollBody {
            overflow-x: auto !important;
            overflow-y: auto !important;
        }
    </style>

    <script>
        $(document).ready(function() {
            // Инициализация DataTables с фиксированной шапкой и скроллом
            let table = $('#scheduleTable').DataTable({
                scrollX: true,
                scrollY: '50vh',       // фиксированная высота, чтобы видеть горизонтальную прокрутку сразу
                scrollCollapse: true,
                fixedHeader: true,     // фиксируем шапку
                paging: false,
                searching: false,
                ordering: false,
                info: false,
            });

            // Обработка клика по ячейке для открытия модалки
            $('.schedule-cell').on('click', function(e) {
                e.preventDefault();
                let userId   = $(this).data('user-id');
                let date     = $(this).data('date');
                let status   = $(this).data('status');
                let isPaid   = $(this).data('is-paid');
                let comment  = $(this).data('comment');

                // Заполняем форму
                $('#formUserId').val(userId);
                $('#formDate').val(date);
                $('#formStatus').val(status);
                $('#formIsPaid').val(isPaid);
                $('#formComment').val(comment);

                // Открываем модальное окно
                $('#scheduleModal').modal('show');
            });

            // Отправка формы (AJAX) на обновление
            $('#scheduleForm').on('submit', function(e) {
                e.preventDefault();
                let formData = $(this).serialize();

                $.ajax({
                    url: '{{ route("schedule.update") }}',
                    type: 'POST',
                    data: formData,
                    success: function(response) {
                        let userId  = $('#formUserId').val();
                        let date    = $('#formDate').val();
                        let status  = $('#formStatus').val();
                        let isPaid  = $('#formIsPaid').val();
                        let comment = $('#formComment').val();

                        // Обновляем ячейку
                        let cell = $('.schedule-cell[data-user-id="'+userId+'"][data-date="'+date+'"]');
                        cell.data('status', status);
                        cell.data('is-paid', isPaid);
                        cell.data('comment', comment);

                        // Цвет фона
                        let bgColor = '';
                        if (status === 'R') {
                            bgColor = (isPaid == 1)
                                ? 'background-color: #5cb85c;'
                                : 'background-color: #f0ad4e;';
                        } else if (status === 'Z') {
                            bgColor = 'background-color: #5bc0de;';
                        }
                        // N – без цвета

                        cell.attr('style', 'cursor: pointer; text-align: center; vertical-align: middle; ' + bgColor);

                        // Ставим нужную иконку
                        let iconHtml = '';
                        if (status === 'R') {
                            iconHtml = '<i class="fa fa-check"></i>';
                        } else if (status === 'Z') {
                            iconHtml = '<i class="fa fa-times"></i>';
                        }
                        // N – пусто

                        cell.html(iconHtml);

                        // Закрываем модалку
                        $('#scheduleModal').modal('hide');
                    },
                    error: function(xhr) {
                        alert('Произошла ошибка при обновлении данных');
                    }
                });
            });
        });
    </script>
@endsection
