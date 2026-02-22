@php
    /** @var \App\Models\BlogPost $post */
    /** @var \Illuminate\Support\Collection|\Illuminate\Database\Eloquent\Collection $aiImages */
    $aiImages = $aiImages ?? collect();
@endphp

<div class="card mt-4">
    <div class="card-header d-flex align-items-center justify-content-between">
        <div class="fw-bold">Сгенерированные изображения (ИИ)</div>
        <div class="text-muted small">{{ $aiImages->count() }} шт.</div>
    </div>
    <div class="card-body">
        @if($aiImages->isEmpty())
            <div class="text-muted">Пока нет сгенерированных изображений для этой статьи.</div>
        @else
            <div class="table-responsive">
                <table class="table table-sm align-middle">
                    <thead>
                    <tr>
                        <th style="width: 110px;">Превью</th>
                        <th>Тип</th>
                        <th>Статус</th>
                        <th class="text-muted">Файл</th>
                        <th class="text-end">Действия</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($aiImages as $img)
                        @php
                            $kindLabel = $img->kind === 'cover' ? 'Обложка' : 'В тексте';
                            $badge = $img->status === 'succeeded' ? 'bg-success'
                                : ($img->status === 'failed' ? 'bg-danger'
                                    : ($img->status === 'running' ? 'bg-warning text-dark' : 'bg-secondary'));
                        @endphp
                        <tr>
                            <td>
                                @if($img->path)
                                    <img src="{{ asset('storage/' . $img->path) }}"
                                         alt="preview"
                                         style="width: 96px; height: 64px; object-fit: cover;"
                                         class="rounded border">
                                @else
                                    <div class="text-muted small">—</div>
                                @endif
                            </td>
                            <td>
                                <div class="fw-bold">{{ $kindLabel }}</div>
                                <div class="text-muted small">
                                    {{ $img->aspect ? 'aspect: ' . $img->aspect : '' }}
                                </div>
                            </td>
                            <td>
                                <span class="badge {{ $badge }}">{{ $img->status }}</span>
                                @if($img->status === 'failed' && $img->error_message)
                                    <div class="text-danger small mt-1">{{ $img->error_message }}</div>
                                @endif
                            </td>
                            <td class="text-muted small">
                                @if($img->path)
                                    <code>{{ $img->path }}</code>
                                @else
                                    —
                                @endif
                            </td>
                            <td class="text-end">
                                <button type="button"
                                        class="btn btn-sm btn-outline-primary js-ai-img-regenerate"
                                        data-image-id="{{ (int) $img->id }}"
                                        data-image-kind="{{ $img->kind }}"
                                        data-image-status="{{ $img->status }}">
                                    Перегенерировать
                                </button>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>

<div class="modal fade" id="blogAiImageRegenModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Перегенерация изображения (ИИ)</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="aiImgId" value="">

                <div class="mb-2">
                    <div class="text-muted small">Можно добавить доп. указания (опционально). Например: “сделай более минималистично”, “добавь больше оранжевого”, “персонаж держит планшет”.</div>
                </div>
                <textarea id="aiImgPromptExtra" rows="5" class="form-control" placeholder="Доп. указания (опционально)"></textarea>
                <div class="invalid-feedback" id="aiImgPromptExtraError"></div>

                <div class="alert alert-danger mt-3 mb-0" id="aiImgTopError" style="display:none;"></div>

                <div class="mt-3" id="aiImgProgressWrap" style="display:none;">
                    <div class="d-flex align-items-center justify-content-between mb-1">
                        <div class="small text-muted" id="aiImgProgressText">Готовим задачу…</div>
                        <div class="small text-muted"><span id="aiImgProgressPercent">0</span>%</div>
                    </div>
                    <div class="progress">
                        <div id="aiImgProgressBar" class="progress-bar progress-bar-striped progress-bar-animated" style="width: 0%"></div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Закрыть</button>
                <button type="button" class="btn btn-primary" id="aiImgRegenBtn">Перегенерировать</button>
            </div>
        </div>
    </div>
</div>

@section('scripts')
    @parent
    <script>
        (function () {
            let pollTimer = null;

            function resetErrors() {
                $('#aiImgTopError').hide().text('');
                $('#aiImgPromptExtra').removeClass('is-invalid');
                $('#aiImgPromptExtraError').text('');
            }

            function showProgress(percent, text) {
                $('#aiImgProgressWrap').show();
                $('#aiImgProgressPercent').text(percent);
                $('#aiImgProgressText').text(text || '');
                $('#aiImgProgressBar').css('width', percent + '%');
            }

            function stopPolling() {
                if (pollTimer) {
                    clearInterval(pollTimer);
                    pollTimer = null;
                }
            }

            function pollGeneration(generationId) {
                stopPolling();
                pollTimer = setInterval(function () {
                    $.get("{{ route('admin.blog.posts.ai.status', ['generation' => 0]) }}".replace('/0', '/' + generationId))
                        .done(function (res) {
                            if (res.status === 'queued') {
                                showProgress(15, 'Задача в очереди…');
                                return;
                            }
                            if (res.status === 'running') {
                                const p = res.progress || 45;
                                let t = 'Генерируем изображение…';
                                if (res.phase === 'cover') t = 'Генерируем обложку…';
                                if (res.phase === 'inline') t = 'Генерируем изображение в тексте…';
                                showProgress(Math.max(25, p), t);
                                return;
                            }
                            if (res.status === 'succeeded') {
                                showProgress(100, 'Готово. Обновляем страницу…');
                                stopPolling();
                                setTimeout(function () { window.location.reload(); }, 700);
                                return;
                            }
                            if (res.status === 'failed') {
                                showProgress(100, 'Ошибка.');
                                stopPolling();
                                const msg = res.error_message || 'Не удалось перегенерировать изображение.';
                                $('#aiImgTopError').text(msg).show();
                                $('#aiImgPromptExtra').addClass('is-invalid');
                                $('#aiImgPromptExtraError').text(msg);
                            }
                        });
                }, 2000);
            }

            $('.js-ai-img-regenerate').on('click', function () {
                resetErrors();
                $('#aiImgProgressWrap').hide();
                showProgress(0, '');
                $('#aiImgProgressWrap').hide();

                $('#aiImgId').val($(this).data('image-id'));
                $('#aiImgPromptExtra').val('');
                showModalQueued('blogAiImageRegenModal');
            });

            $('#blogAiImageRegenModal').on('hidden.bs.modal', function () {
                stopPolling();
            });

            $('#aiImgRegenBtn').on('click', function () {
                resetErrors();
                const imageId = $('#aiImgId').val();
                const url = "{{ route('admin.blog.posts.ai.images.regenerate', ['post' => $post->id, 'image' => 0]) }}".replace('/0/regenerate', '/' + imageId + '/regenerate');

                showProgress(10, 'Создаём задачу…');

                $.ajax({
                    method: 'POST',
                    url: url,
                    data: {prompt_extra: $('#aiImgPromptExtra').val()},
                    headers: {'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')},
                }).done(function (res) {
                    showProgress(15, 'Задача в очереди…');
                    pollGeneration(res.generation_id);
                }).fail(function (xhr) {
                    $('#aiImgProgressWrap').hide();
                    const json = xhr.responseJSON || {};
                    const errors = json.errors || {};
                    if (errors.prompt_extra && errors.prompt_extra.length) {
                        $('#aiImgPromptExtra').addClass('is-invalid');
                        $('#aiImgPromptExtraError').text(errors.prompt_extra[0]);
                        return;
                    }
                    const msg = json.message || 'Не удалось запустить перегенерацию.';
                    $('#aiImgTopError').text(msg).show();
                });
            });
        })();
    </script>
@endsection

