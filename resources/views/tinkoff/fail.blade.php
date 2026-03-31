@extends('layouts.app')

@section('title', 'Оплата не завершена')

@section('content')
    <div class="container">
        <h1 class="h4 mb-3">Оплата не завершена</h1>
        <p class="mb-2">Платёж не был завершён или был отменён. Вы можете попробовать снова из личного кабинета или главной страницы.</p>
        <p class="text-muted small mb-3" id="tbankReturnRedirectMsg"></p>
        <p class="mb-0">
            <a href="#" id="tbankReturnGoNow" class="btn btn-outline-primary btn-sm">Перейти сейчас</a>
        </p>
    </div>
@endsection

@section('scripts')
    <script>
        (function () {
            var authenticated = @json($authenticated);
            var cabinetUrl = @json($cabinetUrl);
            var homeUrl = @json($homeUrl);
            var target = authenticated ? cabinetUrl : homeUrl;
            var msgEl = document.getElementById('tbankReturnRedirectMsg');
            var link = document.getElementById('tbankReturnGoNow');
            if (link) link.setAttribute('href', target);
            if (msgEl) {
                msgEl.textContent = authenticated
                    ? 'Через 3 секунды вы будете перенаправлены в личный кабинет.'
                    : 'Через 3 секунды вы будете перенаправлены на главную страницу.';
            }
            setTimeout(function () {
                window.location.href = target;
            }, 3000);
        })();
    </script>
@endsection
