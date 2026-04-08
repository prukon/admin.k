<div class="container setting-price-wrap">
    <hr>

    <div class="row justify-content-center mt-3" id="wrap-bars">
        {{-- Левый блок: список учеников (аналогично левому бару групп) --}}
        <div id="left_bar" class="col-12 col-lg-5 mb-3">
            {{-- Фильтры над списком --}}
            <div class="row mb-3 mt-3">
                <div class="col-6">
                    <select class="form-select" id="filter-team">
                        <option value="">Все группы</option>
                        @foreach($allTeams as $team)
                            <option value="{{ $team->id }}">{{ $team->title }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6">
                    <input type="text"
                           class="form-control"
                           id="filter-user"
                           placeholder="Поиск по ученикам">
                </div>
            </div>

            {{-- Список учеников --}}
            @if(isset($users) && $users->count() > 0)
                @foreach($users as $idx => $user)
                    @php
                        $fullName = trim($user->lastname . ' ' . $user->name);
                    @endphp

                    <div class="row mb-2 wrap-team user-row"
                         data-user-id="{{ $user->id }}"
                         data-user-name="{{ $fullName }}"
                         data-team-id="{{ $user->team_id }}"
                         data-team-name="{{ optional($user->team)->title }}">
                        <div class="team-name col-7">
                            {{ ($idx + 1) . '. ' . $fullName }}
                            <div class="small text-muted">
                                {{ optional($user->team)->title ?? 'Группа не указана' }}
                            </div>
                        </div>
                        <div class="team-price col-2">
                            {{-- можно потом вывести какую-то актуальную цену, сейчас оставим пустым --}}
                        </div>
                        <div class="team-buttons col-3 d-flex justify-content-end">
                            <input class="detail btn btn-primary" type="button" value="Подробно">
                        </div>
                    </div>
                @endforeach
            @else
                <p class="text-muted">Ученики не найдены.</p>
            @endif
        </div>

        <div class="col-md-auto"></div>

        {{-- Правый блок: цены по месяцам за год (аналогично правому бару) --}}
        <div id="right_bar" class="col-12 col-lg-5">
            <button disabled id="save-user-year-prices"
                    class="btn btn-primary btn-setting-prices mb-3 mt-3">
                Применить
            </button>

            <div class="row mb-2 wrap-users text-start">
                <div class="col-12 mb-2">
                    <h5 id="user-detail-name" class="mb-0">Выберите ученика слева</h5>
                    <small id="user-detail-team" class="text-muted"></small>
                </div>

                <div class="col-12 mb-2">
                    @php $currentYear = now()->year; @endphp
                    {{-- <label for="user-year-select" class="form-label small mb-1">Год</label> --}}
                    <select class="form-select form-select-sm" id="user-year-select">
                        @for($year = $currentYear - 1; $year <= $currentYear + 1; $year++)
                            <option value="{{ $year }}" {{ $year === $currentYear ? 'selected' : '' }}>
                                {{ $year }}
                            </option>
                        @endfor
                    </select>
                </div>

                <div class="col-12" id="user-prices-table-wrapper">
                    <p class="text-muted mb-0">
                        После выбора ученика здесь появятся цены по месяцам за выбранный год.
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Toast Bootstrap 5 для "Сохранено / Ошибка" --}}
<div class="position-fixed bottom-0 end-0 p-3" style="z-index: 1080;">
    <div id="priceToast" class="toast align-items-center text-white bg-success border-0" role="alert"
         aria-live="assertive" aria-atomic="true">
        <div class="d-flex">
            <div class="toast-body" id="priceToastBody">
                Сохранено
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto"
                    data-bs-dismiss="toast" aria-label="Закрыть"></button>
        </div>
    </div>
</div>

<script>
    (function () {
        let currentUserId = null;

        function showToast(message, isError) {
            const toastEl = document.getElementById('priceToast');
            const bodyEl = document.getElementById('priceToastBody');

            if (!toastEl || !bodyEl) return;

            bodyEl.textContent = message || (isError ? 'Ошибка' : 'Сохранено');

            toastEl.classList.remove('bg-success', 'bg-danger');
            toastEl.classList.add(isError ? 'bg-danger' : 'bg-success');

            if (!window.bootstrap || !bootstrap.Toast) {
                return;
            }

            const toast = new bootstrap.Toast(toastEl);
            toast.show();
        }

        function renderUserPricesTable(response) {
            const wrapper = $('#user-prices-table-wrapper');
            const btnSave = $('#save-user-year-prices');

            if (!response || !response.months || !response.months.length) {
                wrapper.html('<p class="text-muted mb-0">Нет данных по ценам за этот год.</p>');
                btnSave.prop('disabled', true);
                return;
            }

            let html = '<table class="table table-sm align-middle mb-0">';
            html += '<thead><tr>';
            html += '<th>Месяц</th>';
            html += '<th style="width: 120px;">Цена, ₽</th>';
            html += '<th style="width: 110px;">Статус</th>';
            html += '</tr></thead><tbody>';

            response.months.forEach(function (item) {
                const isPaid = item.is_paid ? 1 : 0;
                const disabledAttr = isPaid ? 'disabled' : '';
                const badge = isPaid
                    ? '<span class="badge bg-success">Оплачено</span>'
                    : '<span class="badge bg-secondary">Не оплачено</span>';

                html += '<tr>';
                html += '<td>' + item.month_label + '</td>';
                html += '<td>';
                html += '<input type="number" class="form-control form-control-sm user-price-input" ' +
                    'data-new-month="' + item.new_month + '" ' +
                    'data-is-paid="' + isPaid + '" ' +
                    'value="' + item.price + '" ' + disabledAttr + '>';
                html += '</td>';
                html += '<td>' + badge + '</td>';
                html += '</tr>';
            });

            html += '</tbody></table>';

            wrapper.html(html);
            btnSave.prop('disabled', false);
        }

        function loadUserYearPrices() {
            const userId = currentUserId;
            if (!userId) {
                return;
            }

            const year = $('#user-year-select').val();
            const token = $('meta[name="csrf-token"]').attr('content');

            $.ajax({
                url: '/admin/setting-prices/user-year-prices',
                method: 'POST',
                data: {
                    user_id: userId,
                    year: year,
                    _token: token
                },
                success: function (response) {
                    if (response.success) {
                        renderUserPricesTable(response);
                    } else {
                        $('#user-prices-table-wrapper').html(
                            '<p class="text-danger mb-0">' + (response.message || 'Не удалось загрузить данные.') + '</p>'
                        );
                        $('#save-user-year-prices').prop('disabled', true);
                        showToast(response.message || 'Не удалось загрузить данные.', true);
                    }
                },
                error: function () {
                    $('#user-prices-table-wrapper').html(
                        '<p class="text-danger mb-0">Ошибка при загрузке данных.</p>'
                    );
                    $('#save-user-year-prices').prop('disabled', true);
                    showToast('Ошибка при загрузке данных.', true);
                }
            });
        }

        function filterUsers() {
            const teamId = $('#filter-team').val(); // строка или ''
            const query  = ($('#filter-user').val() || '').toLowerCase().trim();

            $('#left_bar .user-row').each(function () {
                const item       = $(this);
                const itemTeamId = (item.attr('data-team-id') || '').toString();
                const userName   = (item.attr('data-user-name') || '').toLowerCase();

                const matchTeam = !teamId || itemTeamId === teamId.toString();
                const matchName = !query || userName.indexOf(query) !== -1;

                if (matchTeam && matchName) {
                    item.removeClass('d-none');
                } else {
                    item.addClass('d-none');
                }
            });
        }

        $(document).ready(function () {

            // выбор ученика слева (клик по строке или «Подробно» — визуал как на вкладке «По месяцам»)
            $('#left_bar').on('click', '.user-row', function () {
                const row      = $(this);
                const userId   = row.attr('data-user-id');
                const userName = row.attr('data-user-name') || '';
                const teamName = row.attr('data-team-name') || '';

                $('#left_bar .user-row').removeClass('wrap-team--active');
                $('#left_bar .detail').removeClass('action-button');
                row.addClass('wrap-team--active');
                row.find('.detail').addClass('action-button');

                currentUserId = userId;

                $('#user-detail-name').text(userName);
                // $('#user-detail-team').text(teamName ? 'Группа: ' + teamName : '');

                loadUserYearPrices();
            });

            // смена года
            $('#user-year-select').on('change', function () {
                loadUserYearPrices();
            });

            // фильтр по команде
            $('#filter-team').on('change', function () {
                filterUsers();
            });

            // поиск по ФИО
            $('#filter-user').on('input', function () {
                filterUsers();
            });

            // сохранение цен за год
            $('#save-user-year-prices').on('click', function () {
                const userId = currentUserId;
                if (!userId) {
                    return;
                }

                const year  = $('#user-year-select').val();
                const token = $('meta[name="csrf-token"]').attr('content');

                const payload = [];
                $('#user-prices-table-wrapper .user-price-input').each(function () {
                    const input    = $(this);
                    const isPaid   = Number(input.data('is-paid')) === 1;
                    const newMonth = input.data('new-month');
                    const price    = Number(input.val()) || 0;

                    if (newMonth && !isPaid) {
                        payload.push({
                            new_month: newMonth,
                            price: price
                        });
                    }
                });

                if (!payload.length) {
                    showToast('Нет изменений для сохранения.', false);
                    return;
                }

                const $saveBtn = $('#save-user-year-prices');

                showConfirmDeleteModal(
                    'Установка цен по ученику',
                    'Вы уверены, что хотите применить изменения?',
                    function () {
                        $saveBtn.prop('disabled', true);

                        $.ajax({
                            url: '/admin/setting-prices/user-year-prices/save',
                            method: 'POST',
                            data: {
                                user_id: userId,
                                year: year,
                                prices: payload,
                                _token: token
                            },
                            success: function (response) {
                                if (response.success) {
                                    showToast('Изменения сохранены.', false);
                                    loadUserYearPrices();
                                } else {
                                    showToast(response.message || 'Не удалось сохранить изменения.', true);
                                }
                            },
                            error: function () {
                                showToast('Ошибка при сохранении изменений.', true);
                            },
                            complete: function () {
                                $saveBtn.prop('disabled', false);
                            }
                        });
                    }
                );
            });
        });
    })();
</script>