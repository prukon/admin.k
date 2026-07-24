    <!-- Модальное окно логов -->
    @include('includes.logModal')

    @push('styles')
        <style>
            /* Длинные названия абонемента: «...» в закрытом select */
            #left_bar .setting-prices-team-package-select,
            #right_bar .wrap-users .setting-prices-monthly-package-select {
                overflow: hidden;
                text-overflow: ellipsis;
                white-space: nowrap;
            }
        </style>
    @endpush


    <div class="container setting-price-wrap">
        @include('includes.modal.manualUserPricePaidModal')
        <hr>
        <div class="buttons text-start">
            <button type="button" class="btn btn-primary" id="logs" data-bs-toggle="modal"
                    data-bs-target="#historyModal">История изменений
            </button>
            <hr>
        </div>
        <div class="row justify-content-md-center">
            <div id='selectDate' class="selectDate">
                <select class="form-select" id="single-select-date" data-placeholder="Дата">

                    @if($monthString)
                        <option>{{ $monthString  }}</option>
                    @endif

                </select>
                <script>
                    const selectElement = document.getElementById('single-select-date');
                    const startYear = 2024;
                    const startMonth = 8; // Июнь (месяцы в JavaScript считаются с 0: 0 = январь, 1 = февраль и т.д.)

                    let CountMonths = function () {
                        return 24;
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
{{--            Применить слева--}}
            <div id='left_bar' class="col-12 col-lg-6 mb-3 ">
                <button id="set-price-all-teams"
                        class="btn btn-primary btn-setting-prices mb-3 mt-3 set-price-all-teams">Применить
                </button>
                @if(isset($allTeams) && $allTeams->count() > 0)
                    @foreach($allTeams as $idx => $team)
                        @php
                            $teamPriceRow = $teamPrices->get($team->id);
                            $price = optional($teamPriceRow)->price ?? 0;
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
    {{-- Vite public/build сейчас root-only; бандл кладём в public/js до нормальной сборки --}}
    <script type="module" src="{{ asset('js/settings-prices.js') }}?v={{ @filemtime(public_path('js/settings-prices.js')) ?: time() }}"></script>
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
