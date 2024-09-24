@extends('layouts.main2')
@extends('layouts.admin2')
@section('content')

    <script src="{{ asset('js/my-croppie.js') }}"></script>
    <script src="{{ asset('js/settings-prices-ajax.js') }}"></script>

    <div class=" col-md-9 main-content" xmlns="http://www.w3.org/1999/html">
        <h4 class="pt-3">Установка цен</h4>
        <div class="container">
            <hr>
            <div class="buttons">
                <button type="button" class="btn btn-primary" id="logs" data-bs-toggle="modal" data-bs-target="#historyModal">История изменений</button>
                <hr>
             </div>
            <div class="row justify-content-md-center">
                <div id='selectDate' class="col-10">
                    <select class="form-select" id="single-select-date" data-placeholder="Дата">

                        @if($currentDate)
                            <option>{{ $currentDate }}</option>
                        @endif

                    </select>
                    <script>
                        const selectElement = document.getElementById('single-select-date');
                        const startYear = 2023;
                        const startMonth = 8; // Июнь (месяцы в JavaScript считаются с 0: 0 = январь, 1 = февраль и т.д.)
                        let CountMonths = function () { // fix переписать для автоматизации
                            let currentYear = new Date().getFullYear();
                            if (currentYear == 2024) {
                                return 24;
                            } else if (currentYear == 2025) {
                                return 36;
                            }
                        }

                        function capitalizeFirstLetter(string) {
                            return string.charAt(0).toUpperCase() + string.slice(1);
                        }

                        for (let i = 0; i < CountMonths(); i++) {
                            const optionDate = new Date(startYear, startMonth + i, 1);
                            let monthYear = optionDate.toLocaleString('ru-RU', {
                                month: 'long',
                                year: 'numeric'
                            }).replace(' г.', '');
                            monthYear = capitalizeFirstLetter(monthYear);
                            const option = document.createElement('option');
                            option.value = monthYear;
                            option.textContent = monthYear;
                            selectElement.appendChild(option);
                        }

                    </script>

                </div>
            </div>
            <div class="row justify-content-center  mt-3 " id='wrap-bars'>
                <div id='left_bar' class="col col-lg-5 mb-3">
                    <button id="set-price-all-teams" class="btn btn-primary btn-setting-prices mb-3 mt-3">Применить
                    </button>
                    {{--                    <i class="info-cicle fa-solid fa-circle-info"></i>--}}

                    @for($i = 0; $i < count($teamPrices); $i++)
                        <div id="{{ $teamPrices[$i]->team_id }}" class="row mb-2 wrap-team">
                            <div class="team-name col-3">{{$allTeams[$i]->title}}</div>
                            <div class="team-price col-2"><input class="" type="number"
                                                                 value="{{ $teamPrices[$i]->price }}"></div>
                            <div class="team-buttons col-7">
                                <input class="ok btn btn-primary" type="button" value="ok" id="">
                                <input class="detail btn btn-primary" type="button" value="Подробно" id="">
                            </div>
                        </div>
                    @endfor

                </div>
                <div class="col-md-auto"></div>
                <div id='right_bar' class="col col-lg-5">
                    <button disabled id="set-price-all-users" class="btn btn-primary btn-setting-prices mb-3 mt-3">
                        Применить
                    </button>
                    <div class="row mb-2 wrap-users"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Модальное окно прменения -->
    <div class="modal fade" id="confirmModal" tabindex="-1" aria-labelledby="confirmModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="confirmModalLabel">Подтверждение действия</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    Вы уверены, что хотите применить изменения?
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-primary" id="confirmApply">Да</button>
                    <button type="button" class="btn btn-danger" data-bs-dismiss="modal">Нет</button>

                </div>
            </div>
        </div>
    </div>

    <!-- Модальное окно -->
    <div class="modal fade" id="historyModal" tabindex="-1" aria-labelledby="historyModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h1 class="modal-title fs-5" id="historyModalLabel">История изменений</h1>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
                </div>
                <div class="modal-body">
                    <!-- Таблица для отображения логов -->
                    <table id="logsTable" class="display table table-striped w-100">
                        <thead>
                        <tr>
                            <th>ID</th>
                            <th>Тип</th>
                            <th>Автор</th>
                            <th>Описание</th>
                            <th>Дата создания</th>
                        </tr>
                        </thead>
                    </table>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Закрыть</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        $(document).ready(function() {
            // Инициализация DataTables с серверной пагинацией
            $('#logsTable').DataTable({
                processing: true,
                serverSide: true,
                ajax: "{{ route('logs.data') }}", // URL для получения данных с сервера
                columns: [
                    { data: 'id', name: 'id' },
                    { data: 'type', name: 'type' },
                    { data: 'author', name: 'author' },
                    { data: 'description', name: 'description' },
                    { data: 'created_at', name: 'created_at' }
                ],
                order: [[4, 'desc']], // Сортировка по дате создания (последние записи первыми)

                // Задаем ширину для столбца ID
                columnDefs: [
                    { width: "40px", targets: 0 }, // Устанавливаем ширину 50px для первого столбца
                    { width: "150px", targets: 4 } // Устанавливаем ширину 50px для первого столбца

                ],

                autoWidth: false, // Отключаем автоширину, чтобы вручную заданные стили применялись


                language: {
                    "processing": "Обработка...",
                    "search": "Поиск:",
                    "lengthMenu": "Показать _MENU_ записей",
                    "info": "Записи с _START_ до _END_ из _TOTAL_ записей",
                    "infoEmpty": "Записи с 0 до 0 из 0 записей",
                    "infoFiltered": "(отфильтровано из _MAX_ записей)",
                    "loadingRecords": "Загрузка записей...",
                    "zeroRecords": "Записи отсутствуют.",
                    "emptyTable": "В таблице отсутствуют данные",
                    "paginate": {
                        "first": "Первая",
                        "previous": "Предыдущая",
                        "next": "Следующая",
                        "last": "Последняя"
                    },
                    "aria": {
                        "sortAscending": ": активировать для сортировки столбца по возрастанию",
                        "sortDescending": ": активировать для сортировки столбца по убыванию"
                    }
                }
            });
        });
    </script>


@endsection
