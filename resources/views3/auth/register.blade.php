@extends('layouts.app')
@extends('layouts/main2')


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
                            <label for="name" class="col-md-4 col-form-label text-md-end">
{{--                                {{ __('Name') }}--}}
                                Имя
                            </label>

                            <div class="col-md-6">
                                <input id="name" type="text" class="form-control @error('name') is-invalid @enderror" name="name" value="{{ old('name') }}" required autocomplete="name" autofocus>

                                @error('name')
                                    <span class="invalid-feedback" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                @enderror
                            </div>
                        </div>

{{--                        <div class="row mb-3">--}}
{{--                            <label for="team" class="col-md-4 col-form-label text-md-end">{{ __('Team') }}</label>--}}

{{--                            <div class="col-md-6">--}}
{{--                                <input id="team" type="text" class="form-control @error('team') is-invalid @enderror" name="team" value="{{ old('team') }}" required autocomplete="team" autofocus>--}}

{{--                                <select id="team" class="form-control @error('team') is-invalid @enderror" name="team_id" required>--}}
{{--                                    @foreach($allTeams as $team)--}}
{{--                                        <option value="{{ $team->id }}">{{ $team->title }}</option>--}}
{{--                                    @endforeach--}}
{{--                                </select>--}}

{{--                                @error('team')--}}
{{--                                <span class="invalid-feedback" role="alert">--}}
{{--                                        <strong>{{ $message }}</strong>--}}
{{--                                    </span>--}}
{{--                                @enderror--}}
{{--                            </div>--}}
{{--                        </div>--}}


                        <div class="row mb-3">
                            <label for="email" class="col-md-4 col-form-label text-md-end">
{{--                                {{ __('Email Address') }}--}}
                            Email адрес
                            </label>

                            <div class="col-md-6">
                                <input id="email" type="email" class="form-control @error('email') is-invalid @enderror" name="email" value="{{ old('email') }}" required autocomplete="email">

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
                                <input id="password" type="password" class="form-control @error('password') is-invalid @enderror" name="password" required autocomplete="new-password">

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
                                <input id="password-confirm" type="password" class="form-control" name="password_confirmation" required autocomplete="new-password">
                            </div>
                        </div>

                        <div class="row mb-0">
                            <div class="col-md-6 offset-md-4">
                                <button type="submit" class="btn btn-primary btn-istok">
{{--                                    {{ __('Register') }}--}}
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
