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

{{--                        @if($currentDate)--}}
{{--                            <option>{{ $currentDate }}</option>--}}
{{--                        @endif --}}
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

    <!-- Модальное окно приенения -->
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

    <!-- Модальное окно логов -->
    @include('includes.logModal')
    <!-- Модальное окно логов -->
<script>
    $(document).ready(function() {
        showLogModal("{{ route('logs.data.settingPrice') }}"); // Здесь можно динамически передать route
    })
</script>
@endsection