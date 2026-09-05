    <!-- Модальное окно логов -->
    @include('includes.logModal')

    @push('styles')
        @vite(['resources/css/schedule.css'])
        <style>
            /* Длинные названия абонемента: «...» в закрытом select */
            #left_bar .setting-prices-team-package-select,
            #right_bar .wrap-users .setting-prices-monthly-package-select {
                overflow: hidden;
                text-overflow: ellipsis;
                white-space: nowrap;
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


    <div class="container setting-price-wrap">
        @include('includes.modal.manualUserPricePaidModal')
        <hr>
        <div class="buttons text-start">
            <button type="button" class="btn btn-primary" id="logs" data-bs-toggle="modal"
                    data-bs-target="#historyModal">История изменений
            </button>
            <button type="button"
                    class="btn btn-outline-primary"
                    id="setting-prices-prolong-btn"
                    data-bs-toggle="modal"
                    data-bs-target="#setting-prices-prolong-modal"
                    data-preview-url="{{ route('setting-prices.prolong-month.preview') }}"
                    data-apply-url="{{ route('setting-prices.prolong-month.apply') }}">
                Пролонгировать на следующий месяц
            </button>
            <hr>
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

@section('scripts')
    @include('partials.ui.discount-percent-js')
    @vite(['resources/js/settings-prices.js'])
    <script>
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

@endsection
