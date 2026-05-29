<div class="main-content text-start">
    <p class="text-muted mt-3">
        Брендированная страница с полной формой заявки. Отправьте ссылку клиентам или разместите её в рекламе.
        Виджет для встраивания на сайт — на вкладке «Виджет для сайта».
    </p>
    <hr>

    <div class="container" style="max-width: 900px;">
        <div class="mb-3">
            <label class="form-label fw-semibold">Ссылка на страницу заявки</label>
            <div class="input-group">
                <input type="text" class="form-control" id="landingUrl" value="{{ $landingUrl }}" readonly>
                <button type="button" class="btn btn-outline-secondary" id="copyLandingUrlBtn">Копировать</button>
            </div>
            <span class="text-success ms-2 d-none" id="copyLandingSuccess">Скопировано</span>
        </div>

        <div class="mb-4">
            <a href="{{ $landingUrl }}" class="btn btn-primary" target="_blank" rel="noopener noreferrer">
                Открыть страницу
            </a>
        </div>

        @if (!$widget->is_landing_active)
            <div class="alert alert-warning">
                Страница заявки отключена. Обратитесь к администратору платформы для включения.
            </div>
        @endif
    </div>
</div>

@section('scripts')
    <script>
        $(function () {
            $('#copyLandingUrlBtn').on('click', function () {
                var text = $('#landingUrl').val();
                navigator.clipboard.writeText(text).then(function () {
                    $('#copyLandingSuccess').removeClass('d-none');
                    setTimeout(function () { $('#copyLandingSuccess').addClass('d-none'); }, 2000);
                });
            });
        });
    </script>
@endsection
