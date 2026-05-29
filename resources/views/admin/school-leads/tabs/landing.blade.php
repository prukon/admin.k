<div class="main-content text-start">
    <p class="text-muted mt-3">
        Брендированная страница с полной формой заявки. Задайте короткий адрес и отправьте ссылку клиентам или разместите её в рекламе.
        Виджет для встраивания на сайт — на вкладке «Виджет для сайта».
    </p>
    <hr>

    <div class="container" style="max-width: 900px;">
        <form id="landingSlugForm" class="mb-4" novalidate>
            <label class="form-label fw-semibold" for="landingSlugInput">Адрес страницы</label>
            <div class="input-group mb-1">
                <span class="input-group-text text-muted">{{ rtrim(url('/'), '/') }}/lead/</span>
                <input type="text"
                       class="form-control"
                       id="landingSlugInput"
                       name="landing_slug"
                       value="{{ $widget->landing_slug }}"
                       placeholder="fk-dinamo"
                       autocomplete="off"
                       spellcheck="false"
                       maxlength="40">
                <button type="submit" class="btn btn-primary" id="saveLandingSlugBtn">Сохранить</button>
            </div>
            <div class="invalid-feedback d-block" id="landingSlugError"></div>
            <div class="form-text">
                Латинские буквы, цифры и дефис, от 3 до 40 символов. Пример: <code>shkola-rossi</code>
            </div>
            <div class="alert alert-success d-none mt-2 mb-0 small" id="landingSlugSuccess"></div>
        </form>

        @if ($landingUrl)
            <div class="mb-3">
                <label class="form-label fw-semibold">Ссылка на страницу заявки</label>
                <div class="input-group">
                    <input type="text" class="form-control" id="landingUrl" value="{{ $landingUrl }}" readonly>
                    <button type="button" class="btn btn-outline-secondary" id="copyLandingUrlBtn">Копировать</button>
                </div>
                <span class="text-success ms-2 d-none" id="copyLandingSuccess">Скопировано</span>
            </div>

            <div class="mb-4">
                <a href="{{ $landingUrl }}" class="btn btn-outline-primary" target="_blank" rel="noopener noreferrer">
                    Открыть страницу
                </a>
            </div>
        @else
            <div class="alert alert-info mb-4">
                Сохраните адрес страницы — после этого появится готовая ссылка для клиентов.
            </div>
        @endif

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
            var csrfToken = $('meta[name="csrf-token"]').attr('content');
            var $form = $('#landingSlugForm');
            var $slugInput = $('#landingSlugInput');
            var $slugError = $('#landingSlugError');
            var $slugSuccess = $('#landingSlugSuccess');
            var $saveBtn = $('#saveLandingSlugBtn');

            function showSlugErrors(errors) {
                $slugError.empty();
                if (!errors) {
                    $slugInput.removeClass('is-invalid');
                    return;
                }
                $slugInput.addClass('is-invalid');
                var messages = errors.landing_slug || [errors.message || 'Проверьте адрес страницы.'];
                if (!Array.isArray(messages)) {
                    messages = [messages];
                }
                messages.forEach(function (msg) {
                    $slugError.append($('<div></div>').text(msg));
                });
            }

            $form.on('submit', function (e) {
                e.preventDefault();
                $slugSuccess.addClass('d-none');
                showSlugErrors(null);
                $saveBtn.prop('disabled', true);

                $.ajax({
                    url: @json(route('admin.school-leads.landing-slug.update')),
                    method: 'PUT',
                    headers: { 'X-CSRF-TOKEN': csrfToken },
                    data: { landing_slug: $slugInput.val() },
                })
                    .done(function (data) {
                        $slugInput.val(data.landing_slug || $slugInput.val());
                        $slugSuccess.text(data.message || 'Сохранено.').removeClass('d-none');
                        window.location.reload();
                    })
                    .fail(function (xhr) {
                        var body = xhr.responseJSON || {};
                        showSlugErrors(body.errors || { message: body.message });
                    })
                    .always(function () {
                        $saveBtn.prop('disabled', false);
                    });
            });

            $('#copyLandingUrlBtn').on('click', function () {
                var text = $('#landingUrl').val();
                if (!text) {
                    return;
                }
                navigator.clipboard.writeText(text).then(function () {
                    $('#copyLandingSuccess').removeClass('d-none');
                    setTimeout(function () { $('#copyLandingSuccess').addClass('d-none'); }, 2000);
                });
            });
        });
    </script>
@endsection
