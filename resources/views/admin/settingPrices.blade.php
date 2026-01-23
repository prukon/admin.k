@extends('layouts.admin2')
@section('content')
    <!-- Модальное окно логов -->
    @include('includes.logModal')


    <h4 class="pt-3 text-start">Установка цен</h4>
    <div class="container setting-price-wrap">
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
                    // let CountMonths = function () { // fix переписать для автоматизации
                    //     let currentYear = new Date().getFullYear();
                    //     if (currentYear == 2024) {
                    //         return 24;
                    //     } else if (currentYear == 2025) {
                    //         return 24;
                    //     } else if (currentYear == 2026) {
                    //         return 24;
                    //     }
                    // } 

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
            <div id='left_bar' class="col-12 col-lg-5 mb-3 ">
                <button id="set-price-all-teams"
                        class="btn btn-primary btn-setting-prices mb-3 mt-3 set-price-all-teams">Применить
                </button>
                @if(isset($allTeams) && $allTeams->count() > 0)
                    @foreach($allTeams as $idx => $team)
                        @php
                            $price = optional($teamPrices->get($team->id))->price ?? 0;
                        @endphp

                        <div id="{{ $team->id }}" class="row mb-2 wrap-team">
                            <div class="team-name col-4">
                                {{ ($idx + 1) . '. ' . $team->title }}
                            </div>
                            <div class="team-price col-3">
                                <input type="number" value="{{ $price }}">
                            </div>
                            <div class="team-buttons col-5 d-flex">
                                <input class="ok btn btn-primary mr-2" type="button" value="ok">
                                <input class="detail btn btn-primary" type="button" value="Подробно">
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
@endsection

@section('scripts')
    @vite(['resources/js/settings-prices.js',])
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
