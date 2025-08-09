@extends('layouts.app')

@section('content')
    <div class="container" style="max-width:640px">
        <h3 class="mb-3">Смена телефона</h3>

        @if ($errors->any())
            <div class="alert alert-danger">{{ $errors->first() }}</div>
        @endif
        @if (session('status'))
            <div class="alert alert-success">{{ session('status') }}</div>
        @endif

        <div class="mb-3">
            <div><strong>Текущий номер:</strong> {{ $currentPhone ? '***'.substr($currentPhone,-4) : 'не указан' }}</div>
            @if($pendingPhone)
                <div><strong>Новый (ожидает подтверждения):</strong> ***{{ substr($pendingPhone,-4) }}</div>
            @endif
            {{--<div><strong>Нужно подтверждать старый:</strong> {{ $mustVerifyOld ? 'да' : 'нет' }}</div>--}}
        </div>

        @if($stage === 'start')
            {{-- ШАГ 1 --}}
            <div class="card">
                <div class="card-header">Шаг 1 — проверка пароля и указание нового номера</div>
                <div class="card-body">
                    <form method="POST" action="{{ route('security.phone.start') }}" class="row g-3">
                        @csrf
                        <div class="col-12 col-md-6">
                            <label class="form-label">Текущий пароль</label>
                            <input type="password" name="current_password" class="form-control" required autocomplete="current-password">
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label">Новый номер телефона</label>
                            <input type="tel" name="new_phone" class="form-control" placeholder="+7 999 111 22 33" required>
                            <small class="text-muted">Формат: 79XXXXXXXXX</small>
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary">Продолжить</button>
                        </div>
                    </form>
                </div>
            </div>
        @elseif($stage === 'old')
            {{-- ШАГ 2 --}}
            <div class="card">
                <div class="card-header">Шаг 2 — подтвердите старый номер</div>
                <div class="card-body">
                    <form method="POST" action="{{ route('security.phone.verify_old') }}" class="mb-2 d-flex align-items-end gap-2">
                        @csrf
                        <div>
                            <label class="form-label">Код из SMS, номер {{ $oldPretty }}</label>
                            <input type="text" name="code_old" class="form-control" inputmode="numeric" pattern="\d{6}" maxlength="6" required>
                        </div>
                        <button type="submit" class="btn btn-success">Подтвердить старый</button>
                    </form>

                    <form id="resend-old-form" method="POST" action="{{ route('security.phone.resend_old') }}" class="d-flex align-items-center gap-2">
                        @csrf
                        <button type="submit" id="resend-old-btn" class="btn btn-outline-secondary"
                                @if(($cooldownOld ?? 0) > 0) disabled @endif
                                data-cooldown="{{ (int)($cooldownOld ?? 0) }}">
                            Отправить код ещё раз на старый
                        </button>
                        <span id="resend-old-timer" class="text-muted small">
                        @if(($cooldownOld ?? 0) > 0) до повторной отправки осталось {{ (int)$cooldownOld }} сек. @endif
                    </span>
                    </form>
                </div>
            </div>
        @elseif($stage === 'new')
            {{-- ШАГ 3 --}}
            <div class="card">
                <div class="card-header">Шаг 3 — подтвердите новый номер</div>
                <div class="card-body">
                    <form method="POST" action="{{ route('security.phone.verify_new') }}" class="mb-2 d-flex align-items-end gap-2">
                        @csrf
                        <div>
                            <label class="form-label">Код из SMS, номер {{ $newPretty }}</label>
                            <input type="text" name="code_new" class="form-control" inputmode="numeric" pattern="\d{6}" maxlength="6" required>
                        </div>
                        <button type="submit" class="btn btn-success">Подтвердить новый</button>
                    </form>

                    <form id="resend-new-form" method="POST" action="{{ route('security.phone.resend_new') }}" class="d-flex align-items-center gap-2">
                        @csrf
                        <button type="submit" id="resend-new-btn" class="btn btn-outline-secondary"
                                @if(($cooldownNew ?? 0) > 0) disabled @endif
                                data-cooldown="{{ (int)($cooldownNew ?? 0) }}">
                            Отправить код ещё раз на новый
                        </button>
                        <span id="resend-new-timer" class="text-muted small">
                        @if(($cooldownNew ?? 0) > 0) до повторной отправки осталось {{ (int)$cooldownNew }} сек. @endif
                    </span>
                    </form>
                </div>
            </div>
        @endif
    </div>
@endsection

@section('scripts')
    @parent
    <script>
        (function () {
            function attachTimer(btnId, formId, labId) {
                const btn = document.getElementById(btnId);
                const form = document.getElementById(formId);
                const label = document.getElementById(labId);
                if (!btn || !form || !label) return;

                let sec = parseInt(btn.getAttribute('data-cooldown') || '0', 10);
                let timerId = null;

                function render() {
                    if (sec > 0) { label.textContent = 'до повторной отправки осталось ' + sec + ' сек.'; btn.disabled = true; }
                    else { label.textContent = ''; btn.disabled = false; }
                }
                function tick() { if (sec<=0) {clearInterval(timerId); return;} sec--; render(); }

                render(); if (sec > 0) timerId = setInterval(tick, 1000);

                form.addEventListener('submit', function(){
                    if (sec > 0) return;
                    sec = 60; render();
                    clearInterval(timerId); timerId = setInterval(tick, 1000);
                });
            }

            attachTimer('resend-old-btn', 'resend-old-form', 'resend-old-timer');
            attachTimer('resend-new-btn', 'resend-new-form', 'resend-new-timer');
        })();
    </script>
@endsection
