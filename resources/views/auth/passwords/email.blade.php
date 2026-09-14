@extends('layouts.app')
{{--@extends('layouts/main2')--}}

@push('meta')
    <meta name="robots" content="noindex, nofollow">
@endpush

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
{{--                    {{ __('Reset Password') }}--}}
                    Сброс пароля
                </div>

                <div class="card-body">
                    @if (session('status'))
                        <div class="alert alert-success" role="alert">
                            {{ session('status') }}
                        </div>
                    @endif

                    <form id="forgot-password-form" method="POST" action="{{ route('password.email') }}">
                        @csrf

                        <div class="row mb-3">
                            <label for="email" class="col-md-4 col-form-label text-md-end">
{{--                                {{ __('Email Address') }}--}}
                                Email адрес
                            </label>

                            <div class="col-md-6">
                                <input id="email" type="email" class="form-control @error('email') is-invalid @enderror" name="email" value="{{ old('email') }}" required autocomplete="email" autofocus>

                                @error('email')
                                    <span class="invalid-feedback" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                @enderror
                            </div>
                        </div>

                        <div class="row mb-0">
                            <div class="col-md-6 offset-md-4">
                                <button type="submit" id="forgot-password-submit" class="btn btn-primary btn-istok">
{{--                                    {{ __('Send Password Reset Link') }}--}}
                                    Отправить пароль на почту
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
(function () {
    var form = document.getElementById('forgot-password-form');
    if (!form) {
        return;
    }
    form.addEventListener('submit', function (event) {
        if (form.getAttribute('data-submitting') === '1') {
            event.preventDefault();
            return;
        }
        form.setAttribute('data-submitting', '1');
        var button = document.getElementById('forgot-password-submit');
        if (button) {
            setTimeout(function () {
                button.disabled = true;
            }, 0);
        }
    });
})();
</script>
@endsection
