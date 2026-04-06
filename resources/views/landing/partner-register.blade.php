@extends('layouts.landingPage')

@section('title', 'Регистрация школы — kidscrm.online')

@push('head')
    <meta name="robots" content="noindex, nofollow">
@endpush

@section('content')
    <div class="container py-4">
        <div class="row justify-content-center">
            <div class="col-lg-6">
                <h1 class="h3 fw-bold mb-2">Регистрация школы</h1>
                <p class="text-muted mb-4">
                    Укажите данные школы и администратора. После отправки формы вы сразу войдёте в личный кабинет.
                    Тип владельца по умолчанию: индивидуальный предприниматель (можно изменить позже в настройках).
                </p>

                <form method="post"
                      action="{{ route('partner.register.store') }}"
                      id="partner-register-form"
                      class="card shadow-sm border-0">
                    @csrf

                    <div class="card-body p-4">
                        <div class="mb-3">
                            <label for="school_title" class="form-label">Название школы</label>
                            <input type="text"
                                   id="school_title"
                                   name="school_title"
                                   value="{{ old('school_title') }}"
                                   required
                                   maxlength="255"
                                   autocomplete="organization"
                                   class="form-control @error('school_title') is-invalid @enderror">
                            @error('school_title')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="name" class="form-label">Ваше имя (администратор)</label>
                            <input type="text"
                                   id="name"
                                   name="name"
                                   value="{{ old('name') }}"
                                   required
                                   maxlength="255"
                                   autocomplete="name"
                                   class="form-control @error('name') is-invalid @enderror">
                            @error('name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="email" class="form-label">Email (логин)</label>
                            <input type="email"
                                   id="email"
                                   name="email"
                                   value="{{ old('email') }}"
                                   required
                                   maxlength="255"
                                   autocomplete="email"
                                   class="form-control @error('email') is-invalid @enderror">
                            @error('email')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="password" class="form-label">Пароль</label>
                            <input type="password"
                                   id="password"
                                   name="password"
                                   required
                                   minlength="8"
                                   autocomplete="new-password"
                                   class="form-control @error('password') is-invalid @enderror">
                            @error('password')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="password_confirmation" class="form-label">Повторите пароль</label>
                            <input type="password"
                                   id="password_confirmation"
                                   name="password_confirmation"
                                   required
                                   minlength="8"
                                   autocomplete="new-password"
                                   class="form-control">
                        </div>

                        <input type="hidden" name="recaptcha_token" id="recaptcha_token" value="">

                        @error('recaptcha_token')
                            <div class="alert alert-danger py-2 small mb-3">{{ $message }}</div>
                        @enderror

                        <button type="submit" class="btn btn-primary w-100" id="partner-register-submit">
                            Зарегистрироваться
                        </button>

                        <p class="small text-muted mt-3 mb-0">
                            Уже есть аккаунт?
                            <a href="{{ route('login') }}">Войти</a>
                        </p>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@section('scripts')
    @if (config('services.recaptcha.site_key'))
        <script src="https://www.google.com/recaptcha/api.js?render={{ config('services.recaptcha.site_key') }}"></script>
        <script>
            (function () {
                var form = document.getElementById('partner-register-form');
                if (!form || typeof grecaptcha === 'undefined') {
                    return;
                }
                form.addEventListener('submit', function (e) {
                    var tokenInput = document.getElementById('recaptcha_token');
                    if (tokenInput && tokenInput.value) {
                        return;
                    }
                    e.preventDefault();
                    grecaptcha.ready(function () {
                        grecaptcha.execute('{{ config('services.recaptcha.site_key') }}', {action: 'partner_register'})
                            .then(function (token) {
                                if (tokenInput) {
                                    tokenInput.value = token;
                                }
                                form.submit();
                            })
                            .catch(function () {
                                alert('Не удалось пройти проверку reCAPTCHA. Обновите страницу и попробуйте снова.');
                            });
                    });
                });
            })();
        </script>
    @endif
@endsection
