<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 pt-3">
    <h4 class="text-start mb-3 ">Платежи по ученикам</h4>
</div>

<table class="table table-bordered mt-3" id="ltv-table">
    <thead>
        <tr>
            <th style="width: 60px;"></th>
            <th>ФИО</th>
            <th>Группа</th>
            <th>Сумма</th>
            <th>Кол-во платежей</th>
            <th>Перв. платёж</th>
            <th>Посл. платёж</th>
            <th>Статус</th>
        </tr>
    </thead>
</table>

@section('scripts')
    <script type="text/javascript">
        $(function() {

            // Основная таблица LTV
            var ltvTable = $('#ltv-table').DataTable({
                processing: true,
                serverSide: true,
                ajax: '/admin/reports/ltv/data',
                columns: [{
                        data: null,
                        className: 'details-control text-center',
                        orderable: false,
                        searchable: false,
                        defaultContent: '<button type="button" class="btn btn-sm btn-outline-secondary"><i class="fa-solid fa-chevron-down"></i></button>'
                    },
                    {
                        data: 'user_name',
                        name: 'user_name'
                    },
                    {
                        data: 'team_title',
                        name: 'team_title'
                    },
                    {
                        data: 'total_price',
                        name: 'total_price',
                        render: function(data, type, row) {
                            if (type === 'display') {
                                var num = parseFloat(data || 0);
                                return num.toLocaleString('ru-RU') + ' руб';
                            }
                            return data;
                        }
                    },
                    {
                        data: 'payment_count',
                        name: 'payment_count'
                    },
                    {
                        data: 'first_payment_date',
                        name: 'first_payment_date',
                        render: function(data, type, row) {
                            if (!data) return '';
                            var d = new Date(data);
                            if (isNaN(d.getTime())) {
                                return data;
                            }
                            var day = ("0" + d.getDate()).slice(-2);
                            var month = ("0" + (d.getMonth() + 1)).slice(-2);
                            var year = d.getFullYear();
                            return day + '.' + month + '.' + year;
                        }
                    },
                    {
                        data: 'last_payment_date',
                        name: 'last_payment_date',
                        render: function(data, type, row) {
                            if (!data) return '';
                            var d = new Date(data);
                            if (isNaN(d.getTime())) {
                                return data;
                            }
                            var day = ("0" + d.getDate()).slice(-2);
                            var month = ("0" + (d.getMonth() + 1)).slice(-2);
                            var year = d.getFullYear();
                            return day + '.' + month + '.' + year;
                        }
                    },
                    {
                        data: 'is_enabled',
                        name: 'is_enabled',
                        render: function(data, type, row) {
                            if (data) {
                                return '<span class="badge bg-success">Активен</span>';
                            }
                            return '<span class="badge bg-secondary">Отключен</span>';
                        }
                    },
                    // техническая колонка, чтобы легко доставать id
                    {
                        data: 'user_id',
                        name: 'user_id',
                        visible: false,
                        searchable: false
                    }
                ],
                // индексы: 0 – кнопка, 1 – ФИО, 2 – Группа, 3 – LTV
                order: [
                    [3, 'desc']
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


            function formatSubscriptionMonth(raw) {
                if (!raw) return '';

                // ожидаем формат YYYY-MM-DD
                var re = /^\d{4}-\d{2}-\d{2}$/;
                if (!re.test(raw)) {
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
             * Строим HTML вложенного блока с платежами.
             *
             * payments   — массив платежей из /admin/reports/ltv/{user}/payments
             * userName   — ФИО из родительской строки
             * teamTitle  — группа из родительской строки
             */
            function buildDetailsHtml(payments, userName, teamTitle) {
                var safeUserName = userName || 'Без имени';
                var safeTeam = teamTitle || 'Без команды';

                if (!payments || !payments.length) {
                    return '' +
                        '<div class="p-3 details-container bg-light border-start border-3 border-secondary">' +
                        '  <div class="fw-bold mb-2">' +
                        '    Платежи ученика: ' + safeUserName + ' (' + safeTeam + ')' +
                        '  </div>' +
                        '  <div class="text-muted small">У этого ученика ещё нет платежей.</div>' +
                        '</div>';
                }

                // считаем итого
                var totalSum = payments.reduce(function(acc, p) {
                    return acc + (parseFloat(p.summ || 0) || 0);
                }, 0);
                var totalSumFormatted = totalSum.toLocaleString('ru-RU');

                var html = '' +
                    '<div class="p-3 details-container bg-light border-start border-3 border-secondary">' +
                    '  <div class="d-flex flex-wrap justify-content-between align-items-center mb-2">' +
                    '    <div class="fw-bold">' +
                    '      Платежи ученика: ' + safeUserName + ' (' + safeTeam + ')' +
                    '    </div>' +
                    '    <div class="small text-muted">' +
                    '      Всего платежей: <b>' + payments.length + '</b>, на сумму <b>' + totalSumFormatted +
                    ' руб</b>' +
                    '    </div>' +
                    '  </div>' +
                    '  <div class="table-responsive">' +
                    '    <table class="table table-sm table-bordered mb-0 align-middle">' +
                    '      <thead class="table-light small">' +
                    '        <tr>' +
                    '          <th style="width: 220px;">Дата и время платежа</th>' +
                    '          <th style="width: 130px;">Сумма</th>' +
                    '          <th style="width: 160px;">Месяц абонемента</th>' +
                    '          <th style="width: 120px;">Провайдер</th>' +
                    '        </tr>' +
                    '      </thead>' +
                    '      <tbody>';

                payments.forEach(function(p) {
                    var amount = (parseFloat(p.summ || 0)).toLocaleString('ru-RU') + ' руб';

                    var providerLabel = '';
                    if (p.payment_provider === 'tbank') {
                        providerLabel = '<span class="badge" style="background-color:#ffdd2d !important; color:black !important;">T-Bank</span>';
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

            // Обработчик раскрытия/сворачивания строк LTV
            $('#ltv-table tbody').on('click', 'td.details-control button', function(e) {
                e.stopPropagation();

                var btn = $(this);
                var tr = btn.closest('tr');
                var row = ltvTable.row(tr);

                if (row.child.isShown()) {
                    // свернуть
                    row.child.hide();
                    tr.removeClass('shown');
                    btn.find('i').removeClass('fa-chevron-up').addClass('fa-chevron-down');
                    return;
                }

                var data = row.data();
                var userId = data.user_id;
                var userName = data.user_name;
                var teamTitle = data.team_title;

                // показываем заглушку "Загрузка..."
                row.child('<div class="p-3 details-container">Загрузка...</div>').show();
                tr.addClass('shown');
                btn.find('i').removeClass('fa-chevron-down').addClass('fa-chevron-up');

                $.ajax({
                    url: '/admin/reports/ltv/' + userId + '/payments',
                    type: 'GET',
                    dataType: 'json',
                    success: function(resp) {
                        var html = buildDetailsHtml(resp.payments || [], userName, teamTitle);
                        tr.next('tr').find('div.details-container').replaceWith(html);
                    },
                    error: function() {
                        var errorHtml = '' +
                            '<div class="p-3 details-container">' +
                            '  <div class="fw-bold mb-2">' +
                            '    Платежи ученика: ' + (userName || 'Без имени') +
                            (teamTitle ? ' (' + teamTitle + ')' : '') +
                            '  </div>' +
                            '  <div class="text-danger">Ошибка загрузки данных.</div>' +
                            '</div>';

                        tr.next('tr').find('div.details-container').replaceWith(errorHtml);
                    }
                });
            });

        });
    </script>
@endsection
