
@section('title','Мои документы')


    <div class="main-content text-start">

        <div class="container-fluid px-0 px-md-2">

            {{-- Заголовок + фильтр статуса --}}
            <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2 pt-3 pb-2">
                <h4 class="mb-0">Договоры</h4>

{{--                <form action="/account-settings/documents" method="get" class="d-flex gap-2">--}}
{{--                    <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">--}}
{{--                        <option value="">Все статусы</option>--}}
{{--                        @foreach($statusMap as $key => $cfg)--}}
{{--                            <option value="{{ $key }}" {{ ($currentStatus===$key)?'selected':'' }}>--}}
{{--                                {{ $cfg['label'] }}--}}
{{--                            </option>--}}
{{--                        @endforeach--}}
{{--                    </select>--}}
{{--                    @if($currentStatus)--}}
{{--                        <a href="/account-settings/documents" class="btn btn-sm btn-outline-secondary">--}}
{{--                            Сбросить--}}
{{--                        </a>--}}
{{--                    @endif--}}
{{--                </form>--}}
            </div>

            @if($contracts->isEmpty())
                <div class="alert alert-light border mt-3">
                    Пока у вас нет загруженных договоров.
                </div>
            @else

                {{-- Сетка карточек (адаптивная) --}}
                <div class="row g-3 mt-1">
                    @foreach($contracts as $c)
                        @php
                            $cfg = $statusMap[$c->status] ?? ['label'=>'Неизвестно','class'=>'secondary'];
                        @endphp
                        <div class="col-12 col-md-6 col-lg-4">
                            <div class="card h-100 shadow-sm border-0">
                                <div class="card-body d-flex flex-column">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <span class="badge bg-{{ $cfg['class'] }}">{{ $cfg['label'] }}</span>
                                        <span class="text-muted small">#{{ $c->id }}</span>
                                    </div>

                                    <div class="mt-2">
                                        <div class="text-muted small">Обновлён</div>
                                        <div class="fw-semibold">{{ $c->updated_at?->format('d.m.Y H:i') }}</div>
                                    </div>

{{--                                    <div class="mt-2">--}}
{{--                                        <div class="text-muted small">SHA-256</div>--}}
{{--                                        <div class="small text-break" style="line-height:1.2;">--}}
{{--                                            <code>{{ \Illuminate\Support\Str::limit($c->source_sha256, 24, '…') }}</code>--}}
{{--                                        </div>--}}
{{--                                    </div>--}}

                                    <div class="mt-2">
                                        <div class="text-muted small">Ученик</div>
                                        <div class="fw-semibold">{{ $c->student_full_name }}</div>
                                    </div>

                                    <div class="mt-2">
                                        <div class="text-muted small">Подписант</div>
                                        <div class="fw-semibold">{{ $c->signer_name ?? '—' }}</div>
                                    </div>

                                    <div class="mt-2">
                                        <div class="text-muted small">Группа</div>
                                        <div class="fw-semibold">{{ $c->group_title }}</div>
                                    </div>

                                    {{-- Кнопки действий --}}
                                    <div class="mt-3 d-flex flex-wrap gap-2">







                                        @if($c->canClientFill())
                                            <button type="button"
                                                    class="btn btn-sm btn-primary js-open-contract-fill"
                                                    data-contract-id="{{ $c->id }}">
                                                Заполнить договор
                                            </button>
                                        @endif

                                        @if($c->canClientSign())
                                            <button type="button"
                                                    class="btn btn-sm btn-success js-open-contract-fill"
                                                    data-contract-id="{{ $c->id }}">
                                                Подписать
                                            </button>
                                        @endif

                                        @if($c->canClientEditFilledData())
                                            <button type="button"
                                                    class="btn btn-sm btn-outline-secondary js-open-contract-fill-edit"
                                                    data-contract-id="{{ $c->id }}">
                                                Изменить
                                            </button>
                                        @endif

                                        @if($c->status === \App\Models\Contract::STATUS_SIGNED)
                                            <a class="btn btn-sm btn-primary"
                                               href="{{ route('account.documents.downloadSigned', $c) }}">
                                                Скачать подписанный
                                            </a>
                                        @elseif($c->source_pdf_path)
                                            <a class="btn btn-sm btn-outline-primary"
                                               href="{{ route('account.documents.downloadOriginal', $c) }}">
                                                Скачать PDF
                                            </a>
                                        @endif
                                    </div>

                                    <hr>
                                    <button class="btn btn-sm btn-outline-secondary btn-history"
                                            data-id="{{ $c->id }}">
                                        История отправок
                                    </button>

                                    {{-- Подвал карточки (для мобилок фикс отступ) --}}
                                    <div class="mt-auto pt-2 d-flex justify-content-between align-items-center">
{{--                                        <span class="small text-muted">Провайдер: {{ $c->provider }}</span>--}}
                                        @if(in_array($c->status, ['sent','opened']))
                                            <span class="small text-muted">Ожидает подписания</span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                {{-- Пагинация --}}
                <div class="mt-3">
                    {{ $contracts->withQueryString()->links() }}
                </div>

            @endif
        </div>

        @include('account.partials.contract-fill-modal')

        <div class="modal fade" id="requestsModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content" style="max-width:600px;margin:auto;">
                    <div class="modal-header">
                        <h5 class="modal-title">История отправок</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"
                                aria-label="Закрыть"></button>
                    </div>
                    <div class="modal-body">
                        <div class="text-center text-muted py-4" id="requestsLoading">Загрузка…</div>
                        <div id="requestsBody" class="d-none"></div>
                        <div id="requestsError" class="alert alert-danger d-none mt-2"></div>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Закрыть</button>
                    </div>
                </div>
            </div>
        </div>




    </div>

    @push('scripts')
        <script>
            (function(){
                const fillModalEl = document.getElementById('contractFillModal');
                const fillModalTitle = document.getElementById('contractFillModalLabel');
                const fillLoading = document.getElementById('contractFillModalLoading');
                const fillError = document.getElementById('contractFillModalError');
                const fillContent = document.getElementById('contractFillModalContent');
                const fillModalOpts = {backdrop: 'static', keyboard: false};
                let fillBsModal = null;
                let fillPollTimer = null;
                let fillCurrentContractId = null;
                let fillCurrentMode = null;

                function getFillModal() {
                    if (!fillBsModal && fillModalEl) {
                        if (typeof window.showModalQueued === 'function') {
                            fillBsModal = bootstrap.Modal.getOrCreateInstance(fillModalEl, fillModalOpts);
                        } else {
                            fillBsModal = new bootstrap.Modal(fillModalEl, fillModalOpts);
                        }
                    }
                    return fillBsModal;
                }

                function clearFillPoll() {
                    if (fillPollTimer) {
                        clearTimeout(fillPollTimer);
                        fillPollTimer = null;
                    }
                }

                function refreshContractFillPhoneMask() {
                    if (!fillModalEl) {
                        return;
                    }

                    $(document).trigger('phone-inputmask:refresh', [fillModalEl]);
                }

                function showFillLoading() {
                    fillLoading?.classList.remove('d-none');
                    fillError?.classList.add('d-none');
                    fillContent?.classList.add('d-none');
                    if (fillError) {
                        fillError.textContent = '';
                    }
                }

                function showFillAjaxErrors(errors) {
                    if (!fillContent || !errors || typeof errors !== 'object') {
                        return;
                    }

                    const messages = [];
                    Object.keys(errors).forEach(function (key) {
                        const items = errors[key];
                        if (Array.isArray(items)) {
                            items.forEach(function (item) {
                                if (item) {
                                    messages.push(String(item));
                                }
                            });
                        }
                    });

                    if (messages.length === 0) {
                        return;
                    }

                    const list = messages.map(function (msg) {
                        return '<li>' + $('<div>').text(msg).html() + '</li>';
                    }).join('');

                    const alertHtml = '<div class="alert alert-danger mb-3 contract-fill-ajax-errors">'
                        + '<ul class="mb-0">' + list + '</ul></div>';

                    fillContent.querySelectorAll('.contract-fill-ajax-errors').forEach(function (node) {
                        node.remove();
                    });
                    fillContent.insertAdjacentHTML('afterbegin', alertHtml);
                }

                function loadContractFill(contractId, isPoll, mode) {
                    if (!contractId) {
                        return;
                    }

                    fillCurrentContractId = contractId;
                    fillCurrentMode = mode || null;
                    if (!isPoll) {
                        showFillLoading();
                        getFillModal()?.show();
                    }

                    const query = [];
                    if (isPoll) {
                        query.push('poll=1');
                    }
                    if (fillCurrentMode === 'edit') {
                        query.push('mode=edit');
                    }
                    const fillUrl = '/account-settings/documents/contracts/' + contractId + '/fill'
                        + (query.length ? '?' + query.join('&') : '');

                    return $.ajax({
                        method: 'GET',
                        url: fillUrl,
                        headers: {'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json'},
                        dataType: 'json',
                    }).done(function (resp) {
                        fillLoading?.classList.add('d-none');
                        fillError?.classList.add('d-none');
                        if (fillModalTitle && resp.title) {
                            fillModalTitle.textContent = resp.title;
                        }
                        if (fillContent) {
                            fillContent.innerHTML = resp.html || '';
                            fillContent.classList.remove('d-none');
                        }
                        refreshContractFillPhoneMask();
                        clearFillPoll();
                        if (resp.poll) {
                            fillPollTimer = setTimeout(function () {
                                loadContractFill(contractId, true);
                            }, 3000);
                        }
                    }).fail(function (xhr) {
                        fillLoading?.classList.add('d-none');
                        let msg = 'Не удалось загрузить договор';
                        if (xhr.responseJSON?.message) {
                            msg = xhr.responseJSON.message;
                        } else if (xhr.status === 404) {
                            msg = 'Договор не найден';
                        }
                        if (fillError) {
                            fillError.textContent = msg;
                            fillError.classList.remove('d-none');
                        }
                        if (fillContent) {
                            fillContent.classList.remove('d-none');
                        }
                    });
                }

                window.openContractFillModal = loadContractFill;

                $(document).on('click', '.js-open-contract-fill', function () {
                    loadContractFill($(this).data('contract-id'), false, null);
                });

                $(document).on('click', '.js-open-contract-fill-edit', function () {
                    loadContractFill($(this).data('contract-id'), false, 'edit');
                });

                $(document).on('submit', '#contractFillModal .contract-fill-form', function (e) {
                    e.preventDefault();

                    const form = this;
                    const contractId = fillCurrentContractId;
                    if (!contractId) {
                        return;
                    }

                    const $submit = $(form).find('[type="submit"]');
                    $submit.prop('disabled', true);
                    fillError?.classList.add('d-none');

                    $.ajax({
                        method: 'POST',
                        url: form.action,
                        data: $(form).serialize(),
                        headers: {'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json'},
                        dataType: 'json',
                    }).done(function (resp) {
                        loadContractFill(contractId, true);
                        if (resp.message && fillContent) {
                            const successHtml = '<div class="alert alert-success mb-3 contract-fill-ajax-success">'
                                + $('<div>').text(resp.message).html()
                                + '</div>';
                            fillContent.querySelectorAll('.contract-fill-ajax-success').forEach(function (node) {
                                node.remove();
                            });
                            fillContent.insertAdjacentHTML('afterbegin', successHtml);
                        }
                    }).fail(function (xhr) {
                        if (xhr.status === 422) {
                            loadContractFill(contractId, true).done(function () {
                                showFillAjaxErrors(xhr.responseJSON?.errors || {});
                                if (!xhr.responseJSON?.errors && xhr.responseJSON?.message && fillError) {
                                    fillError.textContent = xhr.responseJSON.message;
                                    fillError.classList.remove('d-none');
                                }
                            });
                            return;
                        }

                        let msg = 'Не удалось сформировать договор';
                        if (xhr.responseJSON?.message) {
                            msg = xhr.responseJSON.message;
                        }
                        if (fillError) {
                            fillError.textContent = msg;
                            fillError.classList.remove('d-none');
                        }
                    }).always(function () {
                        $submit.prop('disabled', false);
                    });
                });

                fillModalEl?.addEventListener('hidden.bs.modal', function () {
                    clearFillPoll();
                    fillCurrentContractId = null;
                    fillCurrentMode = null;
                    fillContent?.classList.add('d-none');
                    if (fillContent) {
                        fillContent.innerHTML = '';
                    }
                });

                @if(!empty($openFillContractId))
                loadContractFill(@json((int) $openFillContractId), false, @json($openFillMode ?? null));
                @endif

                const modalEl = document.getElementById('requestsModal');
                const bsModal = new bootstrap.Modal(modalEl);

                $(document).on('click', '.btn-history', function(){
                    const id = $(this).data('id');
                    $('#requestsLoading').removeClass('d-none').text('Загрузка…');
                    $('#requestsBody').addClass('d-none').empty();
                    $('#requestsError').addClass('d-none').text('');

                    bsModal.show();

                    $.ajax({
                        method: 'GET',
                        url: '/account-settings/documents/contracts/' + id + '/requests'
                    }).done(function(resp){
                        $('#requestsLoading').addClass('d-none');
                        const rows = resp.requests.map(r => `
        <tr>
          <td>${r.id}</td>
          <td>${r.signer ?? '—'}</td>
          <td>${r.phone ?? '—'}</td>
          <td><span class="badge ${r.badge}">${r.status}</span></td>
          <td>${r.created ?? '—'}</td>
        </tr>
      `).join('');

                        const table = `
        <div class="table-responsive">
          <table class="table table-sm align-middle mb-0">
            <thead>
              <tr>
                <th>#</th>
                <th>Подписант</th>
                <th>Телефон</th>
                <th>Статус</th>
                <th>Создано</th>
              </tr>
            </thead>
            <tbody>
              ${rows || '<tr><td colspan="5" class="text-center text-muted py-4">Пока нет отправок</td></tr>'}
            </tbody>
          </table>
        </div>
      `;

                        $('#requestsBody').removeClass('d-none').html(table);
                    }).fail(function(xhr){
                        $('#requestsLoading').addClass('d-none');
                        const msg = (xhr.responseText && xhr.status !== 0)
                            ? ('Ошибка '+xhr.status+': '+xhr.responseText)
                            : 'Не удалось загрузить историю';
                        $('#requestsError').removeClass('d-none').text(msg);
                    });
                });
            })();
        </script>
    @endpush

