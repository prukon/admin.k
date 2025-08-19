@extends('layouts.app')

@section('content')
    <div class="container" style="max-width:480px">
        <h3 class="mb-3">Подтверждение входа</h3>

        Мы отправили SMS с кодом на номер <b>{{ $formattedPhone }}</b>.<br>
        Введите код ниже или отправьте заново.

        @if ($errors->has('code'))
            <div class="alert alert-danger mt-2">{{ $errors->first('code') }}</div>
        @endif
        @if ($errors->has('resend'))
            <div class="alert alert-danger mt-2">{{ $errors->first('resend') }}</div>
        @endif
        @if (session('status'))
            <div class="alert alert-success mt-2">{{ session('status') }}</div>
        @endif

        <form method="POST" action="{{ route('two-factor.verify') }}" class="mb-3 mt-3">
            @csrf
            <div class="mb-3">
                <label for="code" class="form-label">Код из SMS</label>
                <input type="text" inputmode="numeric" pattern="\d{6}" maxlength="6"
                       class="form-control" id="code" name="code" placeholder="••••••" required autofocus>
            </div>
            <button type="submit" class="btn btn-primary">Подтвердить</button>
        </form>

        <form id="resend-form" method="POST" action="{{ route('two-factor.resend') }}" class="mb-2 d-flex align-items-center gap-2">
            @csrf
            @php $cooldown = (int)($cooldownSec ?? 0); @endphp
            <button type="submit" id="resend-btn" class="btn btn-outline-secondary"
                    @if($cooldown > 0) disabled @endif
                    data-cooldown="{{ $cooldown }}">
                Отправить повторно
            </button>
            <span id="resend-timer" class="text-muted small">
            @if($cooldown > 0) до повторной отправки осталось {{ $cooldown }} сек. @endif
        </span>
        </form>

        <div class="text-muted small">
            <a href="{{ route('security.phone.form') }}">Изменить номер телефона</a>
        </div>
    </div>
@endsection

@section('scripts')
    @parent
    <script>
        (function () {
            const btn = document.getElementById('resend-btn');
            const form = document.getElementById('resend-form');
            const label = document.getElementById('resend-timer');
            if (!btn || !label) return;

            let sec = parseInt(btn.getAttribute('data-cooldown') || '0', 10);
            let timerId = null;

            function render() {
                if (sec > 0) { label.textContent = 'до повторной отправки осталось ' + sec + ' сек.'; btn.disabled = true; }
                else { label.textContent = ''; btn.disabled = false; }
            }
            function tick() { if (sec<=0) {clearInterval(timerId); return;} sec--; render(); }

            render(); if (sec > 0) timerId = setInterval(tick, 1000);

            form.addEventListener('submit', function () {
                if (sec > 0) return;
                sec = 60; render();
                clearInterval(timerId); timerId = setInterval(tick, 1000);
            });
        })();
    </script>
@endsection
