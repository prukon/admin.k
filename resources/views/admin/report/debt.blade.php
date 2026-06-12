@php
    $filters = $filters ?? [];
    $paymentsFilterUser = $paymentsFilterUser ?? null;
    $paymentsFilterTeam = $paymentsFilterTeam ?? null;
    $paymentsFilterTrainer = $paymentsFilterTrainer ?? null;
    $canViewTrainers = $canViewTrainers ?? (auth()->user() && auth()->user()->can('trainers.view'));
    $canViewLocations = $canViewLocations ?? (auth()->user() && auth()->user()->can('locations.view'));
    $activeLocations = $activeLocations ?? collect();
    $payFilterKeys = ['filter_user_id', 'filter_team_id', 'filter_trainer_profile_id', 'filter_location_id', 'user_name', 'team_title', 'debt_month'];
    $payFilterLocation = $filters['filter_location_id'] ?? '';
    $payHasActiveFilters = false;
    foreach ($payFilterKeys as $k) {
        $v = $filters[$k] ?? null;
        if ($v !== null && $v !== '') {
            $payHasActiveFilters = true;
            break;
        }
    }
@endphp

@vite(['resources/css/admin-list-toolbar.css'])

<div class="card payments-report-surface border-0 shadow-sm mb-2 mb-md-3 mt-2">
    <div class="card-body px-3 py-3">
        <div class="payments-report-toolbar d-flex flex-nowrap align-items-center justify-content-between gap-2 gap-md-3 min-w-0">
            <h1 class="h5 mb-0 fw-semibold text-body payments-report-title text-truncate min-w-0 flex-shrink-1">Задолженности</h1>
            <div class="d-flex flex-nowrap align-items-center gap-2 gap-md-3 min-w-0 flex-shrink-0">
                <div class="payments-report-total-inline payments-report-total-stat text-end" id="debtReportTotalStat">
                    <div class="payments-report-total-label text-muted small mb-0">Общая сумма</div>
                    <div class="payments-report-total-value fs-6 fw-semibold text-body tabular-nums lh-sm mt-1">
                        <span class="payments-report-total-value-inner">
                            <span class="payments-report-total-amount">{{ $totalUnpaidPrice }}</span><span class="payments-report-total-currency fw-normal text-muted ms-1">руб</span>
                        </span>
                    </div>
                </div>
                <div class="d-flex align-items-center gap-2 payments-report-toolbar-actions flex-shrink-0">
                    <button class="payments-report-toolbar-action payments-report-filters-toggle d-inline-flex align-items-center gap-2"
                            type="button"
                            data-bs-toggle="collapse"
                            data-bs-target="#debtReportFiltersCollapse"
                            aria-expanded="{{ $payHasActiveFilters ? 'true' : 'false' }}"
                            aria-controls="debtReportFiltersCollapse"
                            id="debtReportFiltersToggle">
                        <span class="payments-report-toolbar-icon-wrap" aria-hidden="true">
                            <i class="fas fa-sliders-h payments-report-toolbar-icon"></i>
                        </span>
                        <span class="payments-report-toolbar-label d-none d-sm-inline">Фильтры</span>
                        <i class="fas fa-chevron-down payments-report-toolbar-chevron" aria-hidden="true"></i>
                    </button>

                    <div class="dropdown payments-report-toolbar-dropdown">
                        <button class="payments-report-toolbar-action payments-report-columns-toggle d-inline-flex align-items-center gap-2"
                                type="button"
                                id="columnsDropdownDebtReport"
                                data-bs-toggle="dropdown"
                                data-bs-auto-close="outside"
                                aria-expanded="false"
                                aria-haspopup="true"
                                title="Какие колонки показывать в таблице">
                            <span class="payments-report-toolbar-icon-wrap" aria-hidden="true">
                                <i class="fas fa-table-columns payments-report-toolbar-icon"></i>
                            </span>
                            <span class="payments-report-toolbar-label d-none d-sm-inline">Колонки</span>
                            <i class="fas fa-chevron-down payments-report-toolbar-chevron" aria-hidden="true"></i>
                        </button>

                        <div class="dropdown-menu dropdown-menu-end payments-report-toolbar-dropdown-panel payments-report-columns-menu"
                             aria-labelledby="columnsDropdownDebtReport">
                            <div class="small text-muted text-uppercase mb-2 px-1 payments-report-columns-menu-label">Вид таблицы</div>
                            <div class="form-check">
                                <input class="form-check-input debt-column-toggle" type="checkbox" id="debtColName" data-column-key="user_name" checked>
                                <label class="form-check-label" for="debtColName">Имя пользователя</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input debt-column-toggle" type="checkbox" id="debtColMonth" data-column-key="month" checked>
                                <label class="form-check-label" for="debtColMonth">Месяц</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input debt-column-toggle" type="checkbox" id="debtColPrice" data-column-key="price" checked>
                                <label class="form-check-label" for="debtColPrice">Сумма</label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="collapse {{ $payHasActiveFilters ? 'show' : '' }} mb-2 mb-md-3" id="debtReportFiltersCollapse">
    <form id="debt-report-filters" method="GET" action="{{ route('debts') }}" class="border rounded p-2 p-md-3 bg-light">
        <div class="row g-2 align-items-end">
            <div class="col-12 col-md-3">
                <label class="form-label" for="pay-debt-filter-user">Ученик</label>
                <select class="form-select payments-report-filter-select2"
                        id="pay-debt-filter-user"
                        name="filter_user_id"
                        data-placeholder="Все ученики"
                        data-search-url="{{ route('reports.payments.users.search') }}">
                    <option value=""></option>
                    @if($paymentsFilterUser)
                        <option value="{{ $paymentsFilterUser['id'] }}" selected>{{ $paymentsFilterUser['text'] }}</option>
                    @endif
                </select>
            </div>
            <div class="col-12 col-md-3">
                <label class="form-label" for="pay-debt-filter-team">Группа</label>
                <select class="form-select payments-report-filter-select2"
                        id="pay-debt-filter-team"
                        name="filter_team_id"
                        data-placeholder="Все группы"
                        data-search-url="{{ route('reports.payments.teams.search') }}">
                    <option value=""></option>
                    @if($paymentsFilterTeam)
                        <option value="{{ $paymentsFilterTeam['id'] }}" selected>{{ $paymentsFilterTeam['text'] }}</option>
                    @endif
                </select>
            </div>
            @if($canViewTrainers)
            <div class="col-12 col-md-3">
                <label class="form-label" for="pay-debt-filter-trainer">Тренер</label>
                <select class="form-select payments-report-filter-select2"
                        id="pay-debt-filter-trainer"
                        name="filter_trainer_profile_id"
                        data-placeholder="Все тренеры"
                        data-search-url="{{ route('reports.payments.trainers.search') }}">
                    <option value=""></option>
                    @if($paymentsFilterTrainer)
                        <option value="{{ $paymentsFilterTrainer['id'] }}" selected>{{ $paymentsFilterTrainer['text'] }}</option>
                    @endif
                </select>
            </div>
            @endif
            @if($canViewLocations)
            <div class="col-12 col-md-3">
                <label class="form-label" for="pay-debt-filter-location">Локация</label>
                <select class="form-select" id="pay-debt-filter-location" name="filter_location_id">
                    <option value="">Все локации</option>
                    <option value="none" {{ (string) $payFilterLocation === 'none' ? 'selected' : '' }}>Без локации</option>
                    @foreach($activeLocations as $location)
                        <option value="{{ $location->id }}" {{ (string) $payFilterLocation === (string) $location->id ? 'selected' : '' }}>
                            {{ $location->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            @endif
            <div class="col-12 col-md-3">
                <label class="form-label" for="pay-debt-filter-debt-month">Месяц задолженности</label>
                <input class="form-control" id="pay-debt-filter-debt-month" type="month" name="debt_month"
                       value="{{ $filters['debt_month'] ?? '' }}">
            </div>
            <div class="col-12 col-md-auto d-flex flex-wrap align-items-stretch gap-2 ms-md-auto payments-report-filters-actions">
                <button class="btn btn-primary payments-report-filters-submit" type="submit">Применить</button>
                <button class="btn btn-outline-secondary payments-report-filters-reset" type="button" id="debtReportFiltersResetBtn">Сброс</button>
            </div>
        </div>
    </form>
</div>

<table class="table table-bordered dt-columns-managed w-100" id="debts-table">
    <thead>
    <tr>
        <th>№</th>
        <th>Имя пользователя</th>
        <th>Месяц</th>
        <th>Сумма</th>
    </tr>
    </thead>
</table>

@section('scripts')
    <script type="text/javascript">
        $(function () {
            var canViewLocations = @json($canViewLocations);
            var $debtFiltersForm = $('#debt-report-filters');
            var $debtFilterUser = $('#pay-debt-filter-user');
            var $debtFilterTeam = $('#pay-debt-filter-team');
            var $debtFilterTrainer = $('#pay-debt-filter-trainer');
            var $debtReportTotalAmount = $('.payments-report-total-amount');
            var $debtReportTotalStat = $('#debtReportTotalStat');
            var $debtReportTotalValueInner = $('.payments-report-total-value-inner');

            function debtReportParseTotalToInt(str) {
                return parseInt(String(str || '').replace(/\s/g, ''), 10) || 0;
            }

            function debtReportFormatTotalSpaces(n) {
                var v = Math.round(Number(n));
                if (isNaN(v)) {
                    return '0';
                }
                return v.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
            }

            function debtReportAnimateTotalChange(prevText, nextText, nextRaw) {
                var $amount = $debtReportTotalAmount;
                if (!$amount.length) {
                    return;
                }

                var nextVal = typeof nextRaw === 'number' && !isNaN(nextRaw)
                    ? Math.round(nextRaw)
                    : debtReportParseTotalToInt(nextText);
                var prevVal = debtReportParseTotalToInt(prevText);

                var runFlashAndPop = function () {
                    if ($debtReportTotalStat.length) {
                        $debtReportTotalStat.removeClass('payments-report-total-stat--flash');
                        void $debtReportTotalStat[0].offsetWidth;
                        $debtReportTotalStat.addClass('payments-report-total-stat--flash');
                    }
                    if ($debtReportTotalValueInner.length) {
                        $debtReportTotalValueInner.removeClass('payments-report-total-value-inner--pop');
                        void $debtReportTotalValueInner[0].offsetWidth;
                        $debtReportTotalValueInner.addClass('payments-report-total-value-inner--pop');
                    }
                };

                var prefersReduced = window.matchMedia
                    && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

                if (prefersReduced || prevText === nextText) {
                    $amount.text(nextText);
                    if (!prefersReduced && prevText !== nextText) {
                        runFlashAndPop();
                    }
                    return;
                }

                if (prevVal === nextVal) {
                    $amount.text(nextText);
                    runFlashAndPop();
                    return;
                }

                var duration = 480;
                var start = null;

                function easeInOutQuad(t) {
                    return t < 0.5 ? 2 * t * t : 1 - Math.pow(-2 * t + 2, 2) / 2;
                }

                function step(ts) {
                    if (start === null) {
                        start = ts;
                    }
                    var elapsed = ts - start;
                    var t = Math.min(1, elapsed / duration);
                    var eased = easeInOutQuad(t);
                    var cur = Math.round(prevVal + (nextVal - prevVal) * eased);
                    $amount.text(debtReportFormatTotalSpaces(cur));
                    if (t < 1) {
                        window.requestAnimationFrame(step);
                    } else {
                        $amount.text(nextText);
                    }
                }

                runFlashAndPop();
                window.requestAnimationFrame(step);
            }

            function initPaymentsReportFilterSelect2($el) {
                var searchUrl = $el.data('search-url');
                if (!$el.length || !searchUrl) {
                    return;
                }
                $el.select2({
                    theme: 'bootstrap-5',
                    width: '100%',
                    placeholder: $el.data('placeholder') || '',
                    language: @include('partials.select2.ru'),
                    allowClear: true,
                    ajax: {
                        url: searchUrl,
                        delay: 250,
                        data: function (params) {
                            return {q: params.term || ''};
                        },
                        processResults: function (data) {
                            return data;
                        }
                    },
                    minimumInputLength: 0
                });
            }

            initPaymentsReportFilterSelect2($debtFilterUser);
            initPaymentsReportFilterSelect2($debtFilterTeam);
            initPaymentsReportFilterSelect2($debtFilterTrainer);

            function debtReportFilterParams() {
                var uid = $debtFiltersForm.find('[name="filter_user_id"]').val() || '';
                var tid = $debtFiltersForm.find('[name="filter_team_id"]').val() || '';
                var tpid = $debtFilterTrainer.length
                    ? ($debtFiltersForm.find('[name="filter_trainer_profile_id"]').val() || '')
                    : '';
                return {
                    filter_user_id: uid,
                    filter_team_id: tid,
                    filter_trainer_profile_id: tpid,
                    filter_location_id: canViewLocations
                        ? ($debtFiltersForm.find('[name="filter_location_id"]').val() || '')
                        : '',
                    user_name: '',
                    team_title: '',
                    debt_month: $debtFiltersForm.find('[name="debt_month"]').val() || ''
                };
            }

            function refreshDebtReportTotal() {
                var prevText = $debtReportTotalAmount.length ? $debtReportTotalAmount.text() : '';
                if ($debtReportTotalStat.length) {
                    $debtReportTotalStat.addClass('payments-report-total-stat--loading');
                }
                $.get(@json(route('reports.debts.total')), debtReportFilterParams())
                    .done(function (res) {
                        if ($debtReportTotalStat.length) {
                            $debtReportTotalStat.removeClass('payments-report-total-stat--loading');
                        }
                        if (!res || res.total_formatted === undefined || !$debtReportTotalAmount.length) {
                            return;
                        }
                        var nextText = res.total_formatted;
                        debtReportAnimateTotalChange(prevText, nextText, res.total_raw);
                    })
                    .fail(function () {
                        if ($debtReportTotalStat.length) {
                            $debtReportTotalStat.removeClass('payments-report-total-stat--loading');
                        }
                    });
            }

            var dtApi = KidsCrmDataTable.create('#debts-table', {
                columnsSettings: {
                    defaults: {
                        user_name: true,
                        month: true,
                        price: true
                    },
                    urls: {
                        get: @json(route('reports.debts.columns-settings.get')),
                        save: @json(route('reports.debts.columns-settings.save'))
                    },
                    toggleSelector: '.debt-column-toggle',
                    csrfToken: '{{ csrf_token() }}'
                },
                dataTable: {
                    ajax: {
                        url: "{{ route('debts.getDebts') }}",
                        type: 'GET',
                        data: function (d) {
                            var extra = debtReportFilterParams();
                            Object.keys(extra).forEach(function (key) {
                                d[key] = extra[key];
                            });
                        }
                    },
                    order: [[2, 'asc']],
                    language: @include('partials.datatables.ru')
                },
                columns: [
                    { type: 'rownum' },
                    {
                        key: 'user_name',
                        type: 'text',
                        data: 'user_name',
                        name: 'user_name',
                        render: function (data, type, row) {
                            if (type !== 'display') {
                                return row.user_name || '';
                            }
                            if (row.user_id) {
                                return row.user_name || '';
                            }
                            return row.user_name ? row.user_name : 'Без имени';
                        }
                    },
                    { key: 'month', type: 'text', data: 'month', name: 'month' },
                    { key: 'price', type: 'money', data: 'price', name: 'price' }
                ]
            });

            $debtFiltersForm.on('submit', function (e) {
                e.preventDefault();
                refreshDebtReportTotal();
                dtApi.reload({ keepPage: true });
            });

            $('#debtReportFiltersResetBtn').on('click', function () {
                $debtFiltersForm[0].reset();
                $debtFilterUser.val(null).trigger('change');
                $debtFilterTeam.val(null).trigger('change');
                $debtFilterTrainer.val(null).trigger('change');
                if (canViewLocations) {
                    $('#pay-debt-filter-location').val('');
                }
                refreshDebtReportTotal();
                dtApi.reload();
            });
        });
    </script>
@endsection
