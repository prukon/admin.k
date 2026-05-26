<div class="main-content text-start">
    <p class="text-muted mt-3">Скопируйте код и вставьте его на лендинг (Tilda, Wix, HTML-страницу).</p>
    <hr>

    <div class="container" style="max-width: 900px;">
        <div class="mb-4">
            <label class="form-label fw-semibold" for="iframeCode">Код для вставки (iframe)</label>
            <textarea id="iframeCode" class="form-control font-monospace" rows="4" readonly>{{ $iframeCode }}</textarea>
            <button type="button" class="btn btn-primary mt-2" id="copyIframeBtn">Скопировать код</button>
            <span class="text-success ms-2 d-none" id="copySuccess">Скопировано</span>
        </div>

        <div class="mb-3">
            <label class="form-label fw-semibold">Прямая ссылка на форму</label>
            <div class="input-group">
                <input type="text" class="form-control" id="widgetUrl" value="{{ $widgetUrl }}" readonly>
                <button type="button" class="btn btn-outline-secondary" id="copyUrlBtn">Копировать</button>
            </div>
        </div>

        <hr class="my-4">

        <h5>Уведомления о новых заявках</h5>
        <p class="text-muted small">
            Email отправляется всем пользователям с ролью <strong>admin</strong> вашей организации
            и на email организации ({{ $partner->email ?: 'не указан' }}).
        </p>

        @if ($telegramConfigured)
            <div class="card mb-3">
                <div class="card-body">
                    <p class="mb-2">
                        Telegram:
                        @if ($partner->school_leads_telegram_chat_id)
                            <span class="badge bg-success">подключён</span>
                        @else
                            <span class="badge bg-secondary">не подключён</span>
                        @endif
                    </p>
                    <p class="text-muted small">
                        Бот {{ $telegramBotName ?: 'kidscrmLeadForm' }}
                        @if ($telegramBotUsername)
                            ({{ '@' . $telegramBotUsername }})
                        @endif
                    </p>

                    <button type="button" class="btn btn-primary" id="connectTelegramBtn">
                        Подключить Telegram
                    </button>
                    @if ($partner->school_leads_telegram_chat_id)
                        <button type="button" class="btn btn-outline-danger ms-2" id="disconnectTelegramBtn">
                            Отключить
                        </button>
                    @endif

                    <div class="alert alert-success d-none mt-2 mb-0 small" id="telegramConnectSuccess"></div>
                    <div class="alert alert-danger d-none mt-2 mb-0 small" id="telegramConnectError"></div>
                </div>
            </div>
        @else
            <div class="alert alert-warning">
                Telegram-бот платформы не настроен (<code>TELEGRAM_BOT_TOKEN</code>).
                Email-уведомления работают; для Telegram обратитесь к администратору сервиса.
            </div>
        @endif
    </div>
</div>

@section('scripts')
    <script>
        $(function () {
            var csrfToken = $('meta[name="csrf-token"]').attr('content');

            $('#copyIframeBtn').on('click', function () {
                var text = $('#iframeCode').val();
                navigator.clipboard.writeText(text).then(function () {
                    $('#copySuccess').removeClass('d-none');
                    setTimeout(function () { $('#copySuccess').addClass('d-none'); }, 2000);
                });
            });

            $('#copyUrlBtn').on('click', function () {
                navigator.clipboard.writeText($('#widgetUrl').val());
            });

            $('#connectTelegramBtn').on('click', function () {
                $('#telegramConnectError').addClass('d-none').text('');
                $('#telegramConnectSuccess').addClass('d-none').text('');

                $.ajax({
                    url: '{{ route('admin.school-widget.telegram-link') }}',
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': csrfToken },
                    success: function (resp) {
                        if (resp.url) {
                            window.open(resp.url, '_blank');
                            $('#telegramConnectSuccess')
                                .removeClass('d-none')
                                .text(resp.message || 'Откройте Telegram и нажмите «Старт». Затем обновите эту страницу.');
                        }
                    },
                    error: function (xhr) {
                        var msg = (xhr.responseJSON && xhr.responseJSON.message)
                            ? xhr.responseJSON.message
                            : 'Не удалось создать ссылку.';
                        $('#telegramConnectError').removeClass('d-none').text(msg);
                    }
                });
            });

            $('#disconnectTelegramBtn').on('click', function () {
                if (!confirm('Отключить Telegram-уведомления?')) {
                    return;
                }

                $.ajax({
                    url: '{{ route('admin.school-widget.telegram-disconnect') }}',
                    method: 'DELETE',
                    headers: { 'X-CSRF-TOKEN': csrfToken },
                    success: function () {
                        window.location.reload();
                    }
                });
            });
        });
    </script>
@endsection

