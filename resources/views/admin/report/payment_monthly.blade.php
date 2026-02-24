<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 pt-3">
    <h4 class="text-start mb-0">Платежи по месяцам</h4>

    {{-- Переключатель режима группировки --}}
    <div class="btn-group" role="group" aria-label="Режим группировки">
        <button type="button" class="btn btn-outline-secondary js-group-mode-btn active" data-mode="subscription">
            По месяцу абонемента
        </button>
        <button type="button" class="btn btn-outline-secondary js-group-mode-btn" data-mode="operation">
            По дате платежа
        </button>
    </div>
</div>

<div class="sum-dept-wrap alert alert-warning d-flex justify-content-between align-items-center p-3 mt-3 mb-3 rounded">
    <span class="fw-bold">Общая сумма платежей:</span>
    <span class="fw-bold">{{ $totalPaidPrice }} руб</span>
</div>

<table class="table table-bordered" id="payments-monthly-table">
    <thead>
        <tr>
            <th style="width: 60px;"></th>
            <th>Месяц</th>
            <th>Количество платежей</th>
            <th>Сумма платежей</th>
        </tr>
    </thead>
</table>

@section('scripts')
    <script type="text/javascript">
        $(function() {

            // Текущий режим группировки: operation | subscription
            var currentMode = 'subscription';

            var monthlyTable = $('#payments-monthly-table').DataTable({
                processing: true,
                serverSide: true,
                ajax: {
                    url: '/admin/reports/payments/monthly/data',
                    type: 'GET',
                    data: function(d) {
                        d.mode = currentMode;
                    }
                },
                columns: [{
                        data: null,
                        className: 'details-control text-center',
                        orderable: false,
                        searchable: false,
                        defaultContent: '<button type="button" class="btn btn-sm btn-outline-secondary"><i class="fa-solid fa-chevron-down"></i></button>'
                    },
                    {
                        data: 'month_title',
                        name: 'month_title'
                    },
                    {
                        data: 'payments_count',
                        name: 'payments_count'
                    },
                    {
                        data: 'total_sum',
                        name: 'total_sum',
                        render: function(data, type, row) {
                            if (type === 'display') {
                                var num = parseFloat(data || 0);
                                return num.toLocaleString('ru-RU') + ' руб';
                            }
                            return data;
                        }
                    },
                    // тех. столбец с ключом месяца (YYYY-MM), скрытый
                    {
                        data: 'month_key',
                        name: 'month_key',
                        visible: false,
                        searchable: false
                    }
                ],
                order: [
                    [1, 'desc']
                ],
                scrollX: true,
                language: {
                    "processing": "Обработка...",
                    "search": "",
                    "searchPlaceholder": "Поиск...",
                    "lengthMenu": "Показать _MENU_",
                    "info": "С _START_ до _END_ из _TOTAL_ записей",
                    "infoEmpty": "С 0 до 0 из 0 записей",
                    "infoFiltered": "(отфильтровано из _MAX_ записей)",
                    "loadingRecords": "Загрузка записей...",
                    "zeroRecords": "Записи отсутствуют.",
                    "emptyTable": "В таблице отсутствуют данные",
                    "paginate": {
                        "first": "",
                        "previous": "",
                        "next": "",
                        "last": ""
                    },
                    "aria": {
                        "sortAscending": ": активировать для сортировки столбца по возрастанию",
                        "sortDescending": ": активировать для сортировки столбца по убыванию"
                    }
                }
            });

            // Переключатель режима
            $('.js-group-mode-btn').on('click', function() {
                var btn = $(this);
                var mode = btn.data('mode');

                if (mode === currentMode) {
                    return;
                }

                currentMode = mode;

                // визуально
                $('.js-group-mode-btn').removeClass('active');
                btn.addClass('active');

                // Перегружаем таблицу
                monthlyTable.ajax.reload(null, true);
            });







            function formatSubscriptionMonth(raw) {
                if (!raw) return '';

                // ожидаем формат "YYYY-MM-DD"
                var re = /^\d{4}-\d{2}-\d{2}$/;
                if (!re.test(raw)) {
                    // если формат другой — выводим как есть
                    return raw;
                }

                var parts = raw.split('-'); // [YYYY, MM, DD]
                var year = parts[0];
                var monthNum = parseInt(parts[1], 10);

                var monthNames = {
                    1: 'Январь',
                    2: 'Февраль',
                    3: 'Март',
                    4: 'Апрель',
                    5: 'Май',
                    6: 'Июнь',
                    7: 'Июль',
                    8: 'Август',
                    9: 'Сентябрь',
                    10: 'Октябрь',
                    11: 'Ноябрь',
                    12: 'Декабрь'
                };

                var monthName = monthNames[monthNum] || parts[1];

                return monthName + ' ' + year;
            }





            /**
             * Строим HTML вложенного блока с платежами за месяц.
             *
             * payments   — массив платежей
             * monthTitle — строка "Февраль 2026"
             * mode       — "subscription" | "operation"
             */
            function buildDetailsHtml(payments, monthTitle, mode) {
                var captionMode = (mode === 'operation') ?
                    'по дате платежа' :
                    'по месяцу абонемента';

                if (!payments || !payments.length) {
                    return '' +
                        '<div class="p-3 details-container bg-light border-start border-3 border-secondary">' +
                        '  <div class="fw-bold mb-2">' +
                        '    Платежи за месяц: ' + (monthTitle || '') +
                        '    <span class="text-muted small">(' + captionMode + ')</span>' +
                        '  </div>' +
                        '  <div class="text-muted small">Нет платежей за этот месяц.</div>' +
                        '</div>';
                }

                var totalSum = payments.reduce(function(acc, p) {
                    return acc + (parseFloat(p.summ || 0) || 0);
                }, 0);
                var totalSumFormatted = totalSum.toLocaleString('ru-RU');

                var html = '' +
                    '<div class="p-3 details-container bg-light border-start border-3 border-secondary">' +
                    '  <div class="d-flex flex-wrap justify-content-between align-items-center mb-2">' +
                    '    <div class="fw-bold">' +
                    '      Платежи за месяц: ' + (monthTitle || '') +
                    '      <span class="text-muted small">(' + captionMode + ')</span>' +
                    '    </div>' +
                    '    <div class="small text-muted">' +
                    '      Всего платежей: <b>' + payments.length + '</b>, ' +
                    '      на сумму <b>' + totalSumFormatted + ' руб</b>' +
                    '    </div>' +
                    '  </div>' +
                    '  <div class="table-responsive">' +
                    '    <table class="table table-sm table-bordered mb-0 align-middle">' +
                    '      <thead class="table-light small">' +
                    '        <tr>' +
                    '          <th style="width: 220px;">Дата и время платежа</th>' +
                    '          <th style="width: 180px;">ФИО</th>' +
                    '          <th style="width: 180px;">Группа</th>' +
                    '          <th style="width: 130px;" class="text-end">Сумма</th>' +
                    '          <th style="width: 160px;">Месяц абонемента</th>' +
                    '          <th style="width: 120px;">Провайдер</th>' +
                    '        </tr>' +
                    '      </thead>' +
                    '      <tbody>';

                payments.forEach(function(p) {
                    var amount = (parseFloat(p.summ || 0)).toLocaleString('ru-RU') + ' руб';

                    var providerLabel = '';
                    if (p.payment_provider === 'tbank') {
                        providerLabel = '<span class="badge bg-primary">T-Bank</span>';
                    } else if (p.payment_provider === 'robokassa') {
                        providerLabel = '<span class="badge bg-secondary">Robokassa</span>';
                    } else {
                        providerLabel = p.payment_provider || '';
                    }

                    var opDate = '';
                    if (p.operation_date) {
                        var d = new Date(p.operation_date);
                        if (!isNaN(d.getTime())) {
                            var day = ("0" + d.getDate()).slice(-2);
                            var month = ("0" + (d.getMonth() + 1)).slice(-2);
                            var year = d.getFullYear();
                            var hours = ("0" + d.getHours()).slice(-2);
                            var minutes = ("0" + d.getMinutes()).slice(-2);
                            opDate = day + '.' + month + '.' + year + ' / ' + hours + ':' + minutes;
                        } else {
                            opDate = p.operation_date;
                        }
                    }

                    var monthLabel = formatSubscriptionMonth(p.payment_month);


                    html += '' +
                        '<tr>' +
                        '  <td>' + opDate + '</td>' +
                        '  <td>' + (p.user_name || 'Без имени') + '</td>' +
                        '  <td>' + (p.team_title || 'Без команды') + '</td>' +
                        '  <td class="text-end">' + amount + '</td>' +
                        '  <td>' + monthLabel + '</td>' +
                        '  <td>' + providerLabel + '</td>' +
                        '</tr>';
                });

                html += '' +
                    '      </tbody>' +
                    '    </table>' +
                    '  </div>' +
                    '</div>';

                return html;
            }

            // Обработчик раскрытия/сворачивания строк
            $('#payments-monthly-table tbody').on('click', 'td.details-control button', function(e) {
                e.stopPropagation();

                var btn = $(this);
                var tr = btn.closest('tr');
                var row = monthlyTable.row(tr);

                if (row.child.isShown()) {
                    row.child.hide();
                    tr.removeClass('shown');
                    btn.find('i').removeClass('fa-chevron-up').addClass('fa-chevron-down');
                    return;
                }

                // показываем заглушку "Загрузка..."
                row.child('<div class="p-3 details-container">Загрузка...</div>').show();
                tr.addClass('shown');
                btn.find('i').removeClass('fa-chevron-down').addClass('fa-chevron-up');

                var data = row.data();
                var monthKey = data.month_key;
                var monthText = data.month_title;

                $.ajax({
                    url: '/admin/reports/payments/monthly/' + monthKey + '/payments',
                    type: 'GET',
                    dataType: 'json',
                    data: {
                        mode: currentMode
                    },
                    success: function(resp) {
                        var html = buildDetailsHtml(resp.payments || [], monthText,
                        currentMode);
                        tr.next('tr').find('div.details-container').replaceWith(html);
                    },
                    error: function() {
                        var html = '' +
                            '<div class="p-3 details-container bg-light border-start border-3 border-secondary">' +
                            '  <div class="fw-bold mb-2">' +
                            '    Платежи за месяц: ' + (monthText || '') +
                            '  </div>' +
                            '  <div class="text-danger">Ошибка загрузки данных.</div>' +
                            '</div>';
                        tr.next('tr').find('div.details-container').replaceWith(html);
                    }
                });
            });

        });
    </script>
@endsection
