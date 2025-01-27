{{--@extends('layouts.main2')--}}
@extends('layouts.admin2')
@section('content')

    <meta name="csrf-token" content="{{ csrf_token() }}">
    <script src="{{ asset('js/my-croppie.js') }}"></script>
    <script src="{{ asset('js/settings-prices.js') }}"></script>

    <!-- Модальное окно логов -->
    @include('includes.logModal')
    <!-- Модальное окно подтверждения удаления -->
    @include('includes.modal.confirmDeleteModal')
    <!-- Модальное окно успешного обновления данных -->
    @include('includes.modal.successModal')
    <!-- Модальное окно ошибки -->
    @include('includes.modal.errorModal')

    <div class="  main-content" xmlns="http://www.w3.org/1999/html">
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
                {{--<div id='selectDate' class="col-10">--}}
                <div id='selectDate' class="selectDate">
                    <select class="form-select" id="single-select-date" data-placeholder="Дата">

                        @if($currentDateString)
                            <option>{{ $currentDateString }}</option>
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
                {{--<div id='left_bar' class="col col-lg-5 mb-3">--}}
                <div id='left_bar' class="col-12 col-lg-5 mb-3 ">
                    <button id="set-price-all-teams"
                            class="btn btn-primary btn-setting-prices mb-3 mt-3 set-price-all-teams">Применить
                    </button>

                @if(isset($teamPrices) && count($teamPrices) > 0)
                    @for($i = 0; $i < count($teamPrices); $i++)
                        @if(isset($allTeams[$i])) <!-- Добавляем проверку на существование индекса $i -->
                            <div id="{{ $teamPrices[$i]->team_id }}" class="row mb-2 wrap-team ">

                                {{--<div class=" col-lg-12  d-flex justify-content-between flex-wrap ">--}}

                                <div class="team-name col-3">{{ $allTeams[$i]->title }}</div>
                                <div class="team-price col-4">
                                    <input class="" type="number" value="{{ $teamPrices[$i]->price }}">
                                </div>
                                <div class="team-buttons col-5 d-flex ">
                                    <input class="ok btn btn-primary mr-2" type="button" value="ok" id="">
                                    <input class="detail btn btn-primary" type="button" value="Подробно" id="">
                                </div>
                            </div>
                            @endif
                        @endfor
                    @endif

                </div>
                <div class="col-md-auto"></div>
                <div id='right_bar' class="col-12 col-lg-5">
                    <button disabled id="set-price-all-users"
                            class="btn btn-primary btn-setting-prices mb-3 mt-3 set-price-all-users">
                        Применить
                    </button>
                    <div class="row mb-2 wrap-users text-start "></div>
                </div>
            </div>
        </div>
    </div>


    <script>
        document.addEventListener('DOMContentLoaded', function () {
             showLogModal("{{ route('logs.data.settingPrice') }}"); // Здесь можно динамически передать route
        });    </script>
@endsection