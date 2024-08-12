@extends('layouts.main2')
@extends('layouts.admin2')
@section('content')

    <script src="{{ asset('js/my-croppie.js') }}"></script>
    <script src="{{ asset('js/settings-prices-ajax.js') }}"></script>

    <div class=" col-md-9 main-content" xmlns="http://www.w3.org/1999/html">
        <h4 class="pt-3">Установка цен</h4>
        <div class="container">
            <div class="row justify-content-md-center">
                <div id='selectDate' class="col-10">
                    <select class="form-select" id="single-select-date" data-placeholder="Дата">
                        <option>{{$currentDate->date}}</option>
                    </select>
                    <script>
                        const selectElement = document.getElementById('single-select-date');
                        const startYear = 2024;
                        const startMonth = 8; // Июнь (месяцы в JavaScript считаются с 0: 0 = январь, 1 = февраль и т.д.)
                        let CountMonths = function () { // fix переписать для автоматизации
                            let currentYear = new Date().getFullYear();
                            if (currentYear == 2024) {
                                return 12;
                            } else if (currentYear == 2025) {
                                return 24;
                            }
                        }
                        // CountMonths();
                        for (let i = 0; i < CountMonths(); i++) {
                            const optionDate = new Date(startYear, startMonth + i, 1);
                            const monthYear = optionDate.toLocaleString('default', {month: 'long', year: 'numeric'});
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
                    <button id="set-price-all-teams" class="btn btn-primary btn-setting-prices mb-3 mt-3">Применить</button>
{{--                    {{dd($teamPrice)}}--}}
                 <div class="testDiv">
                     @foreach($teamPrices as $teamPrice)
{{--                         <pre>{{ var_dump($teamPrice->team_id) }}</pre>--}}
{{--                         <pre>{{ dd($allTeams) }}</pre>--}}
                         <!-- Остальной код -->
                     @endforeach
                 </div>
{{--                    @foreach($allTeams as $team)--}}
{{--                    @foreach($teamPrices as $teamPrice)--}}
{{--                        <div id="{{$teamPrice->team_id}}" class="row mb-2 wrap-team">--}}
{{--                            <div class="team-name col-3">{{}}</div>--}}
{{--                            <div class="team-price col-2"><input class="" type="number" value="{{$teamPrice->price}}"></div>--}}
{{--                            <div class="team-buttons col-7">--}}
{{--                                <input class="ok btn btn-primary" type="button" value="ok" id="">--}}
{{--                                <input class="detail btn btn-primary" type="button" value="Подробно" id="">--}}
{{--                            </div>--}}
{{--                        </div>--}}
{{--                    @endforeach--}}

                    @for($i = 0; $i < count($teamPrices); $i++)
                        <div id="{{ $teamPrices[$i]->team_id }}" class="row mb-2 wrap-team">
                            <div class="team-name col-3">{{$allTeams[$i]->title}}</div>
                            <div class="team-price col-2"><input class="" type="number" value="{{ $teamPrices[$i]->price }}"></div>
                            <div class="team-buttons col-7">
                                <input class="ok btn btn-primary" type="button" value="ok" id="">
                                <input class="detail btn btn-primary" type="button" value="Подробно" id="">
                            </div>
                        </div>
                    @endfor

                </div>
                <div class="col-md-auto"></div>
                <div id='right_bar' class="col col-lg-5">
                    <button disabled class="btn btn-primary btn-setting-prices mb-3 mt-3">Применить</button>
                        <div class="row mb-2 wrap-users"></div>
                </div>
            </div>
        </div>
    </div>

@endsection
