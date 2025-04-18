@extends('layouts.app')
{{--@extends('layouts/main2')--}}
{{--@extends('layouts.admin2')--}}


@section('content')
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        {{--                    {{ __('Register') }}--}}
                        Регистрация
                    </div>

                    <div class="card-body">
                        <form method="POST" action="{{ route('register') }}">
                            @csrf


                            <div class="row mb-3">
                                <label for="partner" class="col-md-4 col-form-label text-md-end">
                                    Партнёр
                                </label>
                                <div class="col-md-6">
                                    <select id="partnerSelect"
                                            name="partner_id"
                                            class="form-control @error('partner_id') is-invalid @enderror"
                                            required>
                                        <option value="">— Выберите партнёра —</option>
                                        @foreach($partners as $p)
                                            <option value="{{ $p->id }}"
                                                    data-active="{{ $p->isRegistrationActive ? '1' : '0' }}"
                                                    {{ old('partner_id') == $p->id ? 'selected' : '' }}>
                                                {{ $p->title }}
                                            </option>
                                        @endforeach
                                    </select>

                                    @error('partner_id')
                                    <span class="invalid-feedback" role="alert">
                <strong>{{ $message }}</strong>
            </span>
                                    @enderror

                                    {{-- 2) Сообщение о запрете регистрации --}}
                                    <div id="registrationDisabledMessage"
                                         class="alert alert-danger mt-2 d-none">
                                        {{-- сюда будет текст из JS --}}
                                    </div>
                                </div>
                            </div>

                            {{--<input type="hidden" name="partner_id" value="{{ old('partner_id', request('partner_id')) }}">--}}

                            <div class="row mb-3">
                                <label for="name" class="col-md-4 col-form-label text-md-end">
                                    {{--                                {{ __('Name') }}--}}
                                    Имя
                                </label>

                                <div class="col-md-6">
                                    <input id="name" type="text"
                                           class="form-control @error('name') is-invalid @enderror" name="name"
                                           value="{{ old('name') }}" required autocomplete="name" autofocus>

                                    @error('name')
                                    <span class="invalid-feedback" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                    @enderror
                                </div>
                            </div>

                            {{--<div class="row mb-3">--}}
                            {{--<label for="team" class="col-md-4 col-form-label text-md-end">{{ __('Team') }}</label>--}}

                            {{--<div class="col-md-6">--}}
                            {{--<input id="team" type="text" class="form-control @error('team') is-invalid @enderror" name="team" value="{{ old('team') }}" required autocomplete="team" autofocus>--}}

                            {{--<select id="team" class="form-control @error('team') is-invalid @enderror" name="team_id" required>--}}
                            {{--@foreach($allTeams as $team)--}}
                            {{--<option value="{{ $team->id }}">{{ $team->title }}</option>--}}
                            {{--@endforeach--}}
                            {{--</select>--}}

                            {{--@error('team')--}}
                            {{--<span class="invalid-feedback" role="alert">--}}
                            {{--<strong>{{ $message }}</strong>--}}
                            {{--</span>--}}
                            {{--@enderror--}}
                            {{--</div>--}}
                            {{--</div>--}}


                            <div class="row mb-3">
                                <label for="email" class="col-md-4 col-form-label text-md-end">
                                    {{--                                {{ __('Email Address') }}--}}
                                    Email адрес
                                </label>

                                <div class="col-md-6">
                                    <input id="email" type="email"
                                           class="form-control @error('email') is-invalid @enderror" name="email"
                                           value="{{ old('email') }}" required autocomplete="email">

                                    @error('email')
                                    <span class="invalid-feedback" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                    @enderror
                                </div>
                            </div>

                            <div class="row mb-3">
                                <label for="password" class="col-md-4 col-form-label text-md-end">
                                    Пароль
                                    {{--                                {{ __('Password') }}--}}
                                </label>

                                <div class="col-md-6">
                                    <input id="password" type="password"
                                           class="form-control @error('password') is-invalid @enderror" name="password"
                                           required autocomplete="new-password">

                                    @error('password')
                                    <span class="invalid-feedback" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                    @enderror
                                </div>
                            </div>

                            <div class="row mb-3">
                                <label for="password-confirm" class="col-md-4 col-form-label text-md-end">
                                    Повторите пароль
                                    {{--                                {{ __('Confirm Password') }}--}}
                                </label>

                                <div class="col-md-6">
                                    <input id="password-confirm" type="password" class="form-control"
                                           name="password_confirmation" required autocomplete="new-password">
                                </div>
                            </div>

                            <div class="row mb-0">
                                <div class="col-md-6 offset-md-4">
                                    <button id="registerButton" type="submit" class="btn btn-primary btn-istok">
                                        Регистрация
                                    </button>
                                </div>
                            </div>

                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>




@endsection

@section('scripts')
    <script>
        {{-- Добалвение партнеров--}}
        $(document).ready(function () {
            const $select = $('#partnerSelect');
            const $btn = $('#registerButton');
            const $msg = $('#registrationDisabledMessage');

            function updateState() {
                const val = $select.val();

                // 1) Если не выбран партнёр — дизейблим кнопку, скрываем плашку
                if (!val) {
                    $btn.prop('disabled', true);
                    $msg.addClass('d-none');
                    return;
                }

                // 2) Иначе проверяем data-active на выбранной опции
                const active = $select.find('option:selected').data('active') === 1;

                if (!active) {
                    $btn.prop('disabled', true);
                    $msg.text(`Регистрация у этого партнёра запрещена. Обратитесь к администратору: ${$select.find('option:selected').text()}`);
                    $msg.removeClass('d-none');
                } else {
                    $btn.prop('disabled', false);
                    $msg.addClass('d-none');
                }
            }

            // Инициализация select2 с плейсхолдером
            $select.select2({
                placeholder: '— Выберите партнёра —',
                width: '100%'
            });

            // Повесили обработчик изменения
            $select.on('change', updateState);

            // И сразу проверяем начальное состояние
            updateState();
        });
    </script>



    <style>
        .select2-container--default .select2-selection--single {
            height: calc(1.5em + .75rem + 2px) !important;
            padding: .375rem .75rem !important;
            background-color: var(--bs-body-bg) !important;
            border: var(--bs-border-width) solid var(--bs-border-color) !important;
            border-radius: var(--bs-border-radius) !important;
        }
        .select2-container--default .select2-selection--single
        .select2-selection__rendered {
            line-height: 1.6 !important;
        }
        .select2-selection__arrow {
            height: calc(1.5em + .75rem + 2px) !important;
        }

    </style>
@endsection