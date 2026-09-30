    <!-- Модальное окно логов -->
    @include('includes.logModal')

    @push('styles')
        @vite(['resources/css/schedule.css'])
        @vite(['resources/css/admin-list-toolbar.css'])
        <style>
            /* Длинные названия абонемента: «...» в закрытом select */
            #left_bar .setting-prices-team-package-select,
            #right_bar .wrap-users .setting-prices-monthly-package-select {
                overflow: hidden;
                text-overflow: ellipsis;
                white-space: nowrap;
            }

            #right_bar .wrap-users .setting-prices-monthly-name-host {
                cursor: pointer;
            }

            #right_bar .wrap-users .setting-prices-monthly-name-host .dt-cell-ellipsis,
            #right_bar .wrap-users .setting-prices-monthly-name-host .setting-prices-monthly-name-text,
            #right_bar .wrap-users .setting-prices-monthly-name-host .js-user-card {
                color: var(--bs-link-color, #0d6efd);
                cursor: pointer;
            }

            #right_bar .wrap-users .setting-prices-monthly-name-host .dt-cell-ellipsis,
            #right_bar .wrap-users .setting-prices-monthly-name-host .setting-prices-monthly-name-text {
                text-decoration: underline;
            }

            #setting-prices-prolong-modal .setting-prices-prolong-stat {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 0.75rem;
                margin: 0 0 0.4rem;
                padding: 0.55rem 0.85rem;
                border: 1px solid #e9ecef;
                border-radius: 0.55rem;
                background: #fff;
            }

            #setting-prices-prolong-modal .setting-prices-prolong-stat:last-child {
                margin-bottom: 0;
            }

            #setting-prices-prolong-modal .setting-prices-prolong-stat__label {
                min-width: 0;
                font-size: 0.9375rem;
                font-weight: 500;
                color: #212529;
                line-height: 1.3;
            }

            #setting-prices-prolong-modal .setting-prices-prolong-stat__value {
                display: inline-flex;
                align-items: center;
                flex-wrap: wrap;
                justify-content: flex-end;
                gap: 0.15rem;
                flex-shrink: 0;
                font-size: 0.8125rem;
                font-weight: 600;
                color: #495057;
                line-height: 1.3;
                text-align: right;
            }

            #setting-prices-prolong-modal .setting-prices-prolong-items {
                max-height: 220px;
                overflow: auto;
                border: 1px solid #e9ecef;
                border-radius: 0.55rem;
            }

            #setting-prices-prolong-modal .btn-primary:disabled,
            #setting-prices-prolong-modal .btn-primary.disabled {
                opacity: 1;
                color: #fff !important;
                background-color: #b8bec5 !important;
                border-color: #a8b0b8 !important;
            }
        </style>
        @include('partials.ui.discount-percent-badge-styles')
    @endpush


    @php
        $monthlyFilters = $monthlyFilters ?? [
            'team_title' => '',
            'team_package' => '',
            'team_price' => '',
            'user_name' => '',
            'user_paid' => '',
            'user_membership' => '',
            'user_package' => '',
            'location_id' => '',
            'admin_user_id' => '',
        ];
        $monthlyFiltersActive = $monthlyFiltersActive ?? false;
        $monthlyPackageOptions = $lessonPackages ?? [];
    @endphp

    <div class="container setting-price-wrap">
        @include('includes.modal.manualUserPricePaidModal')

        <div class="card payments-report-surface border-0 shadow-sm mb-2 mb-md-3 mt-3">
            <div class="card-body px-3 py-3">
                <div class="payments-report-toolbar d-flex flex-nowrap align-items-center justify-content-between gap-2 gap-md-3 min-w-0">
                    <h1 class="h5 mb-0 fw-semibold text-body payments-report-title text-truncate min-w-0 flex-shrink-1">По месяцам</h1>
                    <div class="d-flex align-items-center gap-2 payments-report-toolbar-actions payments-report-toolbar-actions--many flex-shrink-0">
                        <button type="button"
                                class="payments-report-toolbar-action d-inline-flex align-items-center gap-2"
                                id="logs"
                                data-bs-toggle="modal"
                                data-bs-target="#historyModal"
                                title="История изменений">
                            <span class="payments-report-toolbar-icon-wrap" aria-hidden="true">
                                <i class="fas fa-clock-rotate-left payments-report-toolbar-icon"></i>
                            </span>
                            <span class="payments-report-toolbar-label d-none d-sm-inline">История</span>
                        </button>

                        <button type="button"
                                class="payments-report-toolbar-action d-inline-flex align-items-center gap-2"
                                id="setting-prices-prolong-btn"
                                data-bs-toggle="modal"
                                data-bs-target="#setting-prices-prolong-modal"
                                data-preview-url="{{ route('setting-prices.prolong-month.preview') }}"
                                data-apply-url="{{ route('setting-prices.prolong-month.apply') }}"
                                title="Пролонгировать на следующий месяц">
                            <span class="payments-report-toolbar-icon-wrap" aria-hidden="true">
                                <i class="fas fa-calendar-plus payments-report-toolbar-icon"></i>
                            </span>
                            <span class="payments-report-toolbar-label d-none d-sm-inline">Пролонгировать на следующий месяц</span>
                        </button>

                        <button class="payments-report-toolbar-action payments-report-filters-toggle d-inline-flex align-items-center gap-2"
                                type="button"
                                data-bs-toggle="collapse"
                                data-bs-target="#settingPricesMonthlyFiltersCollapse"
                                aria-expanded="{{ $monthlyFiltersActive ? 'true' : 'false' }}"
                                aria-controls="settingPricesMonthlyFiltersCollapse"
                                id="settingPricesMonthlyFiltersToggle">
                            <span class="payments-report-toolbar-icon-wrap" aria-hidden="true">
                                <i class="fas fa-sliders-h payments-report-toolbar-icon"></i>
                            </span>
                            <span class="payments-report-toolbar-label d-none d-sm-inline">Фильтры</span>
                            <i class="fas fa-chevron-down payments-report-toolbar-chevron" aria-hidden="true"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="collapse {{ $monthlyFiltersActive ? 'show' : '' }} mb-2 mb-md-3" id="settingPricesMonthlyFiltersCollapse">
            <form id="setting-prices-monthly-filters"
                  class="border rounded p-2 p-md-3 bg-light"
                  method="POST"
                  action="{{ route('setting-prices.monthly-filters') }}"
                  novalidate>
                @csrf
                <div class="row g-2 align-items-end">
                    <div class="col-12 col-md-3">
                        <label class="form-label" for="filter-monthly-team-title">Название группы</label>
                        <input id="filter-monthly-team-title"
                               name="team_title"
                               class="form-control"
                               type="text"
                               maxlength="255"
                               value="{{ $monthlyFilters['team_title'] ?? '' }}"
                               placeholder="Название группы"
                               autocomplete="off">
                        <div class="small text-danger mt-1 setting-prices-monthly-filter-error" data-error-for="team_title" style="display:none;"></div>
                    </div>

                    @can('locations.view')
                        <div class="col-12 col-md-3">
                            <label class="form-label" for="filter-monthly-location">Объект</label>
                            <select id="filter-monthly-location" name="location_id" class="form-select">
                                <option value="">Все объекты</option>
                                <option value="none" @selected(($monthlyFilters['location_id'] ?? '') === 'none')>Без привязки к объектам</option>
                                @foreach(($monthlyLocationOptions ?? collect()) as $location)
                                    <option value="{{ (int) $location->id }}" @selected((string) ($monthlyFilters['location_id'] ?? '') === (string) $location->id)>
                                        {{ $location->name }}
                                    </option>
                                @endforeach
                            </select>
                            <div class="small text-danger mt-1 setting-prices-monthly-filter-error" data-error-for="location_id" style="display:none;"></div>
                        </div>

                        <div class="col-12 col-md-3">
                            <label class="form-label" for="filter-monthly-admin">Администратор объекта</label>
                            <select id="filter-monthly-admin" name="admin_user_id" class="form-select">
                                <option value="">Все администраторы</option>
                                <option value="none" @selected(($monthlyFilters['admin_user_id'] ?? '') === 'none')>Без администратора</option>
                                @foreach(($monthlyAdminOptions ?? collect()) as $admin)
                                    <option value="{{ (int) $admin->id }}" @selected((string) ($monthlyFilters['admin_user_id'] ?? '') === (string) $admin->id)>
                                        {{ $admin->full_name }}
                                    </option>
                                @endforeach
                            </select>
                            <div class="small text-danger mt-1 setting-prices-monthly-filter-error" data-error-for="admin_user_id" style="display:none;"></div>
                        </div>
                    @endcan

                    <div class="col-12 col-md-3">
                        <label class="form-label" for="filter-monthly-team-package">Абонемент группы</label>
                        <select id="filter-monthly-team-package" name="team_package" class="form-select">
                            <option value="">Все абонементы</option>
                            <option value="none" @selected(($monthlyFilters['team_package'] ?? '') === 'none')>Без абонемента</option>
                            @foreach($monthlyPackageOptions as $pkg)
                                <option value="{{ (int) $pkg['id'] }}" @selected((string) ($monthlyFilters['team_package'] ?? '') === (string) $pkg['id'])>
                                    {{ $pkg['name'] }}
                                </option>
                            @endforeach
                        </select>
                        <div class="small text-danger mt-1 setting-prices-monthly-filter-error" data-error-for="team_package" style="display:none;"></div>
                    </div>

                    <div class="col-12 col-md-3">
                        <label class="form-label" for="filter-monthly-team-price">Цена группы</label>
                        <select id="filter-monthly-team-price" name="team_price" class="form-select">
                            <option value="">Любая</option>
                            <option value="set" @selected(($monthlyFilters['team_price'] ?? '') === 'set')>Задана</option>
                            <option value="unset" @selected(($monthlyFilters['team_price'] ?? '') === 'unset')>Не задана</option>
                        </select>
                        <div class="small text-danger mt-1 setting-prices-monthly-filter-error" data-error-for="team_price" style="display:none;"></div>
                    </div>

                    <div class="col-12 col-md-3">
                        <label class="form-label" for="filter-monthly-user-name">Ученик</label>
                        <input id="filter-monthly-user-name"
                               name="user_name"
                               class="form-control"
                               type="text"
                               maxlength="255"
                               value="{{ $monthlyFilters['user_name'] ?? '' }}"
                               placeholder="Фамилия или имя"
                               autocomplete="off">
                        <div class="small text-danger mt-1 setting-prices-monthly-filter-error" data-error-for="user_name" style="display:none;"></div>
                    </div>

                    <div class="col-12 col-md-3">
                        <label class="form-label" for="filter-monthly-user-paid">Оплата</label>
                        <select id="filter-monthly-user-paid" name="user_paid" class="form-select">
                            <option value="">Все</option>
                            <option value="paid" @selected(($monthlyFilters['user_paid'] ?? '') === 'paid')>Оплачено</option>
                            <option value="unpaid" @selected(($monthlyFilters['user_paid'] ?? '') === 'unpaid')>Не оплачено</option>
                        </select>
                        <div class="small text-danger mt-1 setting-prices-monthly-filter-error" data-error-for="user_paid" style="display:none;"></div>
                    </div>

                    <div class="col-12 col-md-3">
                        <label class="form-label" for="filter-monthly-user-membership">Состав</label>
                        <select id="filter-monthly-user-membership" name="user_membership" class="form-select">
                            <option value="">Все</option>
                            <option value="current" @selected(($monthlyFilters['user_membership'] ?? '') === 'current')>В группе</option>
                            <option value="former" @selected(($monthlyFilters['user_membership'] ?? '') === 'former')>Не в группе</option>
                        </select>
                        <div class="small text-danger mt-1 setting-prices-monthly-filter-error" data-error-for="user_membership" style="display:none;"></div>
                    </div>

                    <div class="col-12 col-md-3">
                        <label class="form-label" for="filter-monthly-user-package">Абонемент ученика</label>
                        <select id="filter-monthly-user-package" name="user_package" class="form-select">
                            <option value="">Все абонементы</option>
                            <option value="none" @selected(($monthlyFilters['user_package'] ?? '') === 'none')>Без абонемента</option>
                            @foreach($monthlyPackageOptions as $pkg)
                                <option value="{{ (int) $pkg['id'] }}" @selected((string) ($monthlyFilters['user_package'] ?? '') === (string) $pkg['id'])>
                                    {{ $pkg['name'] }}
                                </option>
                            @endforeach
                        </select>
                        <div class="small text-danger mt-1 setting-prices-monthly-filter-error" data-error-for="user_package" style="display:none;"></div>
                    </div>

                    <div class="col-12 col-md-auto d-flex flex-wrap align-items-stretch gap-2 ms-md-auto payments-report-filters-actions">
                        <button id="setting-prices-monthly-filters-apply" class="btn btn-primary payments-report-filters-submit" type="submit">Применить</button>
                        <button id="setting-prices-monthly-filters-reset" class="btn btn-outline-secondary payments-report-filters-reset" type="submit" name="reset" value="1">Сброс</button>
                    </div>
                </div>
            </form>
        </div>

        <div class="modal fade" id="setting-prices-prolong-modal" tabindex="-1"
             aria-labelledby="setting-prices-prolong-modal-title" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content schedule-modal-content cell-edit-modal">
                    <div class="modal-header">
                        <h5 class="modal-title" id="setting-prices-prolong-modal-title">Пролонгация на следующий месяц</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
                    </div>
                    <div class="modal-body">
                        <div class="cell-edit-context">
                            <div class="cell-edit-context__name">Пролонгация абонементов</div>
                            <div class="cell-edit-context__date" id="setting-prices-prolong-period"></div>
                            <div class="cell-edit-context__summary" id="setting-prices-prolong-message"></div>
                        </div>
                        <div id="setting-prices-prolong-selected-date-err" class="invalid-feedback d-none" data-error-for="selectedDate"></div>
                        <div id="setting-prices-prolong-body">
                            <p class="text-muted mb-0">Загрузка превью…</p>
                        </div>
                    </div>
                    <div class="modal-footer cell-edit-modal__footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Отмена</button>
                        <button type="button" class="btn btn-primary" id="setting-prices-prolong-confirm" disabled>
                            Пролонгировать
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <template id="setting-prices-prolong-skip-hint-tpl">
            @include('partials.ui.tooltip-hint', [
                'title' => 'Причины пропусков',
                'placement' => 'top',
                'iconClass' => 'fa fa-info-circle',
                'wrapperClass' => 'ms-1',
            ])
        </template>
        <div class="row justify-content-md-center">
            <div id='selectDate' class="selectDate">
                <select class="form-select" id="single-select-date" data-placeholder="Дата"
                        data-start-year="{{ (int) $monthlySelectStartYear }}"
                        data-start-month-index="{{ (int) $monthlySelectStartMonthIndex }}"
                        data-month-count="{{ (int) $monthlySelectMonthCount }}"
                        data-selected-label="{{ $monthString }}">
                </select>
                <script>
                    const selectElement = document.getElementById('single-select-date');
                    const startYear = Number(selectElement.dataset.startYear);
                    const startMonth = Number(selectElement.dataset.startMonthIndex);
                    const monthCount = Number(selectElement.dataset.monthCount);
                    const selectedLabel = (selectElement.dataset.selectedLabel || '').trim();

                    function capitalizeFirstLetter(string) {
                        return string.charAt(0).toUpperCase() + string.slice(1);
                    }

                    selectElement.innerHTML = '';

                    for (let i = 0; i < monthCount; i++) {
                        const optionDate = new Date(startYear, startMonth + i, 1);
                        let monthYear = optionDate.toLocaleString('ru-RU', {
                            month: 'long',
                            year: 'numeric'
                        }).replace(' г.', '');
                        monthYear = capitalizeFirstLetter(monthYear);
                        const option = document.createElement('option');
                        option.value = monthYear;
                        option.textContent = monthYear;
                        if (monthYear === selectedLabel) {
                            option.selected = true;
                        }
                        selectElement.appendChild(option);
                    }

                </script>

            </div>
        </div>
        <div class="row justify-content-center  mt-3 " id='wrap-bars'>
{{--            Применить слева--}}
            <div id='left_bar' class="col-12 col-lg-6 mb-3 ">
                <button id="set-price-all-teams"
                        class="btn btn-primary btn-setting-prices mb-3 mt-3 set-price-all-teams">Применить
                </button>
                @if(isset($allTeams) && $allTeams->count() > 0)
                    @foreach($allTeams as $idx => $team)
                        @php
                            $teamPriceRow = $teamPrices->get($team->id);
                            $priceCents = (int) (optional($teamPriceRow)->price_cents ?? 0);
                            $price = $priceCents > 0 ? $priceCents / 100 : 0;
                            $selectedPackageId = optional($teamPriceRow)->lesson_package_id;
                            $teamLabel = ($idx + 1) . '. ' . $team->title;
                            $packages = $lessonPackages ?? [];
                        @endphp

                        <div id="{{ $team->id }}"
                             class="mb-2 wrap-team setting-prices-team-row d-flex align-items-center flex-nowrap gap-1 gap-md-2 min-w-0 w-100"
                             data-legacy-price="{{ e($price) }}">
                            <div class="team-name setting-prices-team-name-col min-w-0">
                                <span class="dt-cell-ellipsis js-dt-cell-ellipsis-tooltip"
                                      data-dt-ellipsis-title="{{ e($teamLabel) }}"
                                      tabindex="0"
                                      aria-label="{{ e($teamLabel) }}">{{ $teamLabel }}</span>
                            </div>
                            <div class="setting-prices-team-package-col flex-shrink-0">
                                <select class="form-select form-select-sm setting-prices-team-package-select"
                                        aria-label="Абонемент группы">
                                    <option value="">Без абонемента</option>
                                    @foreach($packages as $pkg)
                                        <option value="{{ (int) $pkg['id'] }}"
                                                data-price="{{ e($pkg['price']) }}"
                                                data-is-postpay="{{ !empty($pkg['is_postpay']) ? '1' : '0' }}"
                                                @selected((int) $selectedPackageId === (int) $pkg['id'])>
                                            {{ $pkg['name'] }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="setting-prices-team-price-display flex-shrink-0">
                                <span class="setting-prices-team-price-value"
                                      data-price="{{ e($price) }}">{{ $price }}</span>
                            </div>
                            <div class="team-buttons setting-prices-team-buttons-col flex-shrink-0 d-flex align-items-center">
                                <input class="ok btn btn-primary btn-sm setting-prices-team-ok @if(empty($selectedPackageId)) is-visually-disabled @endif"
                                       type="button"
                                       value="Применить"
                                       @if(empty($selectedPackageId))
                                           aria-disabled="true"
                                           title="Выберите абонемент"
                                           data-kids-tooltip-hint="1"
                                           data-bs-toggle="tooltip"
                                           data-bs-placement="top"
                                           data-bs-custom-class="ulp-assignment-paid-tooltip"
                                       @endif>
                            </div>
                        </div>
                    @endforeach
                @else
                    <p class="text-muted mb-0">Группы не найдены.</p>
                @endif
            </div>

            <div class="col-md-auto"></div>
            {{--            Применить справа--}}
            <div id='right_bar' class="col-12 col-lg-5">
                <button disabled id="set-price-all-users"
                        class="btn btn-primary btn-setting-prices mb-3 mt-3 set-price-all-users">
                    Применить
                </button>
                <div class="row mb-2 wrap-users text-start "></div>
            </div>
        </div>
    </div>

@include('partials.ui.user-card-modal', [
    'userCardUrl' => url('/admin/setting-prices/user-cards'),
])

@section('scripts')
    @include('partials.ui.discount-percent-js')
    @vite(['resources/js/settings-prices.js'])
    <script>
        const monthlyFiltersForm = document.getElementById('setting-prices-monthly-filters');
        if (monthlyFiltersForm) {
            monthlyFiltersForm.addEventListener('submit', function (event) {
                event.preventDefault();
                const submitter = event.submitter;
                const body = new FormData(monthlyFiltersForm);
                if (submitter && submitter.name === 'reset') {
                    body.set('reset', '1');
                } else {
                    body.delete('reset');
                }

                const token = document.querySelector('meta[name="csrf-token"]');
                fetch(monthlyFiltersForm.action, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': token ? token.getAttribute('content') : ''
                    },
                    body: body
                }).then(function (response) {
                    return response.json().then(function (payload) {
                        return { ok: response.ok, status: response.status, payload: payload };
                    }).catch(function () {
                        return { ok: response.ok, status: response.status, payload: null };
                    });
                }).then(function (result) {
                    monthlyFiltersForm.querySelectorAll('.is-invalid').forEach(function (el) {
                        el.classList.remove('is-invalid');
                    });
                    monthlyFiltersForm.querySelectorAll('[data-error-for]').forEach(function (el) {
                        el.textContent = '';
                        el.style.display = 'none';
                    });

                    if (result.ok) {
                        window.location.reload();
                        return;
                    }

                    const errors = result.payload && result.payload.errors ? result.payload.errors : null;
                    if (!errors) {
                        return;
                    }

                    Object.keys(errors).forEach(function (field) {
                        const message = errors[field] && errors[field][0] ? errors[field][0] : '';
                        const input = monthlyFiltersForm.querySelector('[name="' + field + '"]');
                        if (input) {
                            input.classList.add('is-invalid');
                        }
                        const errorEl = monthlyFiltersForm.querySelector('[data-error-for="' + field + '"]');
                        if (errorEl) {
                            errorEl.textContent = message;
                            errorEl.style.display = message ? 'block' : 'none';
                        }
                    });
                }).catch(function () {
                    monthlyFiltersForm.submit();
                });
            });
        }

        $('#single-select-date').on('change', function () {
            const selectedMonth = $(this).val();

            $.ajax({
                url: '/admin/setting-prices/update-date',
                method: 'POST',
                data: {
                    month: selectedMonth,
                    _token: $('meta[name="csrf-token"]').attr('content')
                },
                success: function () {
                    // после смены месяца перезагружаем страницу,
                    // и в index() уже подхватится month из сессии
                    window.location.reload();
                },
                error: function (xhr, status, error) {
                    console.error('Error setting month:', error);
                }
            });
        });

    </script>

    <script> 
        document.addEventListener('DOMContentLoaded', function () {
            showLogModal("{{ route('logs.data.settingPrice') }}"); // Здесь можно динамически передать route
        });
    </script>

    <script>
        $(document).on('click', '#right_bar .user-name', function (e) {
            if (e.target.closest('.js-user-card, .js-payment-user-card')) {
                return;
            }
            var card = this.closest('.setting-prices-user-card');
            var id = card ? String(card.getAttribute('data-user-id') || '').replace(/[^0-9]/g, '') : '';
            if (!id) {
                return;
            }
            e.preventDefault();
            var link = document.createElement('a');
            link.className = 'js-user-card js-payment-user-card';
            link.setAttribute('data-user-id', id);
            link.href = 'javascript:void(0);';
            link.hidden = true;
            document.body.appendChild(link);
            link.click();
            link.remove();
        });
    </script>

@endsection
