@php
    /** @var \Illuminate\Support\Collection|\Illuminate\Database\Eloquent\Collection|array $categories */
    $categories = $categories ?? [];
    $aiDefaultCover = (int) (\App\Models\Setting::query()->where('name', 'blog.ai.images.default_cover_count')->whereNull('partner_id')->value('text') ?? 1);
    $aiDefaultInline = (int) (\App\Models\Setting::query()->where('name', 'blog.ai.images.default_inline_count')->whereNull('partner_id')->value('text') ?? 2);
    $aiDefaultInline = max(0, min(3, $aiDefaultInline));
@endphp

<div class="modal fade" id="blogAiModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Статья (ИИ)</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body">
                <input type="hidden" id="blogAiMode" value="new">
                <input type="hidden" id="blogAiPostId" value="">
                <input type="hidden" id="blogAiAction" value="new_post">

                <div class="row g-3">
                    <div class="col-12 col-lg-6" id="blogAiCategoryWrap">
                        <label class="form-label">Категория</label>
                        <select id="blogAiCategory" class="form-select">
                            <option value="">— выберите —</option>
                            @foreach($categories as $cat)
                                <option value="{{ is_array($cat) ? $cat['id'] : $cat->id }}">
                                    {{ is_array($cat) ? $cat['name'] : $cat->name }}
                                </option>
                            @endforeach
                        </select>
                        <div class="invalid-feedback" id="blogAiCategoryError"></div>
                    </div>

                    <div class="col-12 col-lg-6" id="blogAiImagesWrap">
                        <label class="form-label">Изображения</label>
                        <div class="d-flex flex-wrap gap-3 align-items-center">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" value="1" id="blogAiWantCover" @checked($aiDefaultCover >= 1)>
                                <label class="form-check-label" for="blogAiWantCover">Обложка</label>
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                <label class="form-label mb-0">Внутри:</label>
                                <select id="blogAiInlineCount" class="form-select form-select-sm" style="width: 90px;">
                                    <option value="0" @selected($aiDefaultInline === 0)>0</option>
                                    <option value="1" @selected($aiDefaultInline === 1)>1</option>
                                    <option value="2" @selected($aiDefaultInline === 2)>2</option>
                                    <option value="3" @selected($aiDefaultInline === 3)>3</option>
                                </select>
                            </div>
                        </div>
                        <div class="invalid-feedback d-block" id="blogAiImagesError" style="display:none;"></div>
                        <div class="text-muted small mt-1">
                            Иллюстрации без текста/логотипов. Стиль настраивается в “SEO‑настройках” блога.
                        </div>
                    </div>

                    <div class="col-12" id="blogAiPromptWrap">
                        <label class="form-label" id="blogAiPromptLabel">Промпт статьи</label>
                        <textarea id="blogAiPrompt" rows="6" class="form-control" placeholder="Например: статья о том, как автоматизировать запись клиентов и снизить пропуски занятий..."></textarea>
                        <div class="invalid-feedback" id="blogAiPromptError"></div>
                        <div class="text-muted small mt-1" id="blogAiPromptHelp">
                            Чем конкретнее запрос (аудитория, проблема, примеры) — тем лучше результат.
                        </div>
                    </div>

                    <div class="col-12" id="blogAiProgressWrap" style="display:none;">
                        <div class="d-flex align-items-center justify-content-between mb-1">
                            <div class="small text-muted" id="blogAiProgressText">Готовим задачу…</div>
                            <div class="small text-muted">
                                <span id="blogAiProgressPercent">0</span>%
                                <span class="mx-1">·</span>
                                <span>Время:</span> <span id="blogAiTimer">00:00</span>
                                <span class="mx-1">·</span>
                                <span id="blogAiPhaseLabel">—</span>
                            </div>
                        </div>
                        <div class="progress">
                            <div id="blogAiProgressBar" class="progress-bar progress-bar-striped progress-bar-animated" style="width: 0%"></div>
                        </div>
                        <div class="mt-2" id="blogAiResultWrap" style="display:none;">
                            <a href="#" id="blogAiEditLink" class="btn btn-sm btn-primary">Открыть черновик</a>
                        </div>
                    </div>
                </div>

                <div class="alert alert-danger mt-3 mb-0" id="blogAiTopError" style="display:none;"></div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Закрыть</button>
                <button type="button" class="btn btn-success" id="blogAiStartBtn">Сгенерировать</button>
            </div>
        </div>
    </div>
</div>

@section('scripts')
    @parent
    <script>
        (function () {
            let pollTimer = null;
            let timerInterval = null;
            let timerStartMs = null; // epoch ms
            let timerLocked = false; // once we got server created_at
            let lastGenerationId = null;

            const blogSettingsUrl = "{{ route('admin.blog.settings.edit') }}";

            function resetErrors() {
                $('#blogAiTopError').hide().html('');

                $('#blogAiPrompt').removeClass('is-invalid');
                $('#blogAiPromptError').text('');

                $('#blogAiCategory').removeClass('is-invalid');
                $('#blogAiCategoryError').text('');

                $('#blogAiImagesError').hide().text('');
            }

            function humanPhase(status, phase) {
                if (status === 'queued') return 'Очередь';
                if (status === 'succeeded') return 'Готово';
                if (status === 'failed') return 'Ошибка';
                if (status !== 'running') return '—';

                if (phase === 'text') return 'Текст';
                if (phase === 'cover') return 'Обложка';
                if (phase === 'inline') return 'Иллюстрации';
                if (phase === 'done') return 'Завершение';
                return 'Генерация';
            }

            function escapeHtml(str) {
                return String(str ?? '')
                    .replaceAll('&', '&amp;')
                    .replaceAll('<', '&lt;')
                    .replaceAll('>', '&gt;')
                    .replaceAll('"', '&quot;')
                    .replaceAll("'", '&#039;');
            }

            function formatElapsed(totalSeconds) {
                const s = Math.max(0, parseInt(totalSeconds || '0', 10));
                const mm = String(Math.floor(s / 60)).padStart(2, '0');
                const ss = String(s % 60).padStart(2, '0');
                return mm + ':' + ss;
            }

            function stopTimer() {
                if (timerInterval) {
                    clearInterval(timerInterval);
                    timerInterval = null;
                }
            }

            function startTimer() {
                stopTimer();
                timerLocked = false;
                timerStartMs = Date.now();
                $('#blogAiTimer').text('00:00');
                timerInterval = setInterval(function () {
                    if (!timerStartMs) return;
                    const elapsed = Math.floor((Date.now() - timerStartMs) / 1000);
                    $('#blogAiTimer').text(formatElapsed(elapsed));
                }, 250);
            }

            function lockTimerToServer(createdAtIso) {
                if (timerLocked) return;
                if (!createdAtIso) return;
                const ms = Date.parse(createdAtIso);
                if (!Number.isFinite(ms)) return;
                timerStartMs = ms;
                timerLocked = true;
            }

            function showAiError(message) {
                const msg = String(message || 'Не удалось сгенерировать статью.');
                const safe = escapeHtml(msg);

                let hint = '';
                if (msg.includes('Не удалось подключиться к OpenAI')) {
                    hint = 'Похоже на временный сбой сети или недоступность OpenAI. Попробуйте повторить через 30–60 секунд. '
                        + 'Если повторяется — проверьте ключ/лимиты в настройках.';
                } else if (msg.includes('Неверный ключ OpenAI')) {
                    hint = 'Проверьте ключ OpenAI и доступ к API в настройках блога.';
                } else if (msg.includes('Превышены лимиты OpenAI')) {
                    hint = 'Похоже, сработали лимиты. Подождите и повторите, либо увеличьте лимиты/бюджет в настройках.';
                }

                const hintHtml = hint ? ('<div class="small text-muted mt-1">' + escapeHtml(hint) + '</div>') : '';

                const actionsHtml = `
                    <div class="d-flex flex-wrap gap-2 mt-2">
                        <button type="button" class="btn btn-sm btn-outline-primary" id="blogAiRetryBtn">Повторить</button>
                        <a class="btn btn-sm btn-outline-secondary" href="${blogSettingsUrl}" target="_blank">Открыть настройки</a>
                    </div>
                `;

                $('#blogAiTopError')
                    .html('<div class="fw-semibold mb-1">' + safe + '</div>' + hintHtml + actionsHtml)
                    .show();
            }

            function setMode(mode, opts = {}) {
                $('#blogAiMode').val(mode);
                $('#blogAiPostId').val(opts.postId || '');
                $('#blogAiAction').val(opts.action || (mode === 'new' ? 'new_post' : 'improve'));

                if (mode === 'new') {
                    $('#blogAiCategoryWrap').show();
                    $('#blogAiImagesWrap').show();
                    $('#blogAiPromptLabel').text('Промпт статьи');
                    $('#blogAiPrompt').attr('placeholder', 'Например: статья о том, как внедрить CRM в кружок и снизить хаос в оплатах...');
                    $('#blogAiStartBtn').text('Сгенерировать');
                } else {
                    $('#blogAiCategoryWrap').hide();
                    $('#blogAiImagesWrap').hide();
                    const action = $('#blogAiAction').val();
                    const actionTitle = ({
                        'improve': 'Улучшить',
                        'seo': 'Сделать более SEO',
                        'checklist': 'Добавить таблицу/чеклист',
                        'regenerate': 'Перегенерировать'
                    })[action] || 'Улучшить';
                    $('#blogAiPromptLabel').text('Доп. указания (опционально) — ' + actionTitle);
                    $('#blogAiPrompt').attr('placeholder', 'Например: добавь больше примеров, сократи воду, сделай стиль более деловым...');
                    $('#blogAiStartBtn').text(actionTitle);
                }
            }

            function showProgress(percent, text) {
                $('#blogAiProgressWrap').show();
                $('#blogAiProgressPercent').text(percent);
                $('#blogAiProgressText').text(text || '');
                $('#blogAiProgressBar').css('width', percent + '%');
            }

            function stopPolling() {
                if (pollTimer) {
                    clearInterval(pollTimer);
                    pollTimer = null;
                }
            }

            function startPolling(generationId) {
                stopPolling();
                lastGenerationId = generationId;
                // таймер стартуем при клике по кнопке; здесь только привязываем к серверному времени
                $('#blogAiPhaseLabel').text('Очередь');

                pollTimer = setInterval(function () {
                    $.get("{{ route('admin.blog.posts.ai.status', ['generation' => 0]) }}".replace('/0', '/' + generationId))
                        .done(function (res) {
                            const status = res.status;
                            const progress = res.progress || 30;
                            const phase = res.phase || null;
                            lockTimerToServer(res.created_at);
                            $('#blogAiPhaseLabel').text(humanPhase(status, phase));

                            if (status === 'queued') {
                                showProgress(15, 'Задача в очереди…');
                            } else if (status === 'running') {
                                let text = 'Генерируем…';
                                if (phase === 'text') text = 'Генерируем текст…';
                                else if (phase === 'cover') text = 'Генерируем обложку…';
                                else if (phase === 'inline') text = 'Генерируем изображения в тексте…';
                                showProgress(Math.max(25, progress), text);
                            } else if (status === 'succeeded') {
                                showProgress(100, 'Готово.');
                                stopPolling();
                                stopTimer();
                                if (typeof res.elapsed_seconds !== 'undefined') {
                                    $('#blogAiTimer').text(formatElapsed(res.elapsed_seconds));
                                }
                                if (res.edit_url) {
                                    $('#blogAiEditLink').attr('href', res.edit_url);
                                    $('#blogAiResultWrap').show();
                                }
                            } else if (status === 'failed') {
                                showProgress(100, 'Ошибка.');
                                stopPolling();
                                stopTimer();
                                if (typeof res.elapsed_seconds !== 'undefined') {
                                    $('#blogAiTimer').text(formatElapsed(res.elapsed_seconds));
                                }
                                const msg = res.error_message || 'Не удалось сгенерировать статью.';
                                showAiError(msg);
                                $('#blogAiPrompt').addClass('is-invalid');
                                $('#blogAiPromptError').text(msg);
                            }
                        })
                        .fail(function () {
                            // keep polling; transient errors
                        });
                }, 2000);
            }

            window.openBlogAiModal = function (opts = {}) {
                stopPolling();
                resetErrors();

                $('#blogAiPrompt').val('');
                $('#blogAiResultWrap').hide();
                $('#blogAiProgressWrap').hide();
                $('#blogAiProgressBar').css('width', '0%');
                $('#blogAiProgressPercent').text('0');
                $('#blogAiProgressText').text('');
                $('#blogAiTimer').text('00:00');
                $('#blogAiPhaseLabel').text('—');
                stopTimer();
                lastGenerationId = null;

                setMode(opts.mode || 'new', opts);

                showModalQueued('blogAiModal');
            };

            $(document).on('click', '#blogAiRetryBtn', function () {
                // повторяем: запускаем новую генерацию с теми же параметрами
                $('#blogAiStartBtn').trigger('click');
            });

            $('#blogAiStartBtn').on('click', function () {
                resetErrors();
                $('#blogAiResultWrap').hide();

                const mode = $('#blogAiMode').val();
                const prompt = $('#blogAiPrompt').val();

                let url = "{{ route('admin.blog.posts.ai.start') }}";
                let data = {
                    prompt: prompt,
                    blog_category_id: $('#blogAiCategory').val(),
                    want_cover_image: $('#blogAiWantCover').is(':checked') ? 1 : 0,
                    inline_images_count: parseInt($('#blogAiInlineCount').val() || '0', 10),
                };

                if (mode !== 'new') {
                    const postId = $('#blogAiPostId').val();
                    url = "{{ route('admin.blog.posts.ai.post.start', ['post' => 0]) }}".replace('/0/ai', '/' + postId + '/ai');
                    data = {action: $('#blogAiAction').val(), prompt: prompt};
                }

                // Таймер и фаза должны быть видны сразу, даже до первого polling.
                startTimer();
                $('#blogAiPhaseLabel').text('Очередь');
                showProgress(10, 'Создаём задачу…');

                $.ajax({
                    method: 'POST',
                    url: url,
                    data: data,
                    headers: {'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')},
                }).done(function (res) {
                    showProgress(15, 'Задача в очереди…');
                    startPolling(res.generation_id);
                }).fail(function (xhr) {
                    showProgress(0, '');
                    $('#blogAiProgressWrap').hide();
                    stopTimer();

                    const json = xhr.responseJSON || {};
                    const errors = json.errors || {};

                    if (errors.prompt && errors.prompt.length) {
                        $('#blogAiPrompt').addClass('is-invalid');
                        $('#blogAiPromptError').text(errors.prompt[0]);
                    }
                    if (errors.blog_category_id && errors.blog_category_id.length) {
                        $('#blogAiCategory').addClass('is-invalid');
                        $('#blogAiCategoryError').text(errors.blog_category_id[0]);
                    }
                    if (errors.inline_images_count && errors.inline_images_count.length) {
                        $('#blogAiImagesError').text(errors.inline_images_count[0]).show();
                    }

                    if ((!errors.prompt || !errors.prompt.length) && (!errors.blog_category_id || !errors.blog_category_id.length)) {
                        const msg = json.message || 'Не удалось запустить генерацию.';
                        showAiError(msg);
                    }
                });
            });

            $('#blogAiModal').on('hidden.bs.modal', function () {
                stopPolling();
                stopTimer();
            });
        })();
    </script>
@endsection

