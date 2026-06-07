@extends('layouts.admin2')

@section('title','Договор #'.$contract->id)


@section('content')

    @php
        if (!function_exists('contractShowFileIconClass')) {
            function contractShowFileIconClass(?string $path): string
            {
                $ext = strtolower(pathinfo((string) $path, PATHINFO_EXTENSION));

                return match ($ext) {
                    'pdf' => 'fa-file-pdf',
                    'doc', 'docx' => 'fa-file-word',
                    'xls', 'xlsx' => 'fa-file-excel',
                    'ppt', 'pptx' => 'fa-file-powerpoint',
                    'png', 'jpg', 'jpeg', 'gif', 'webp', 'bmp' => 'fa-file-image',
                    'txt', 'rtf' => 'fa-file-lines',
                    'zip', 'rar', '7z' => 'fa-file-zipper',
                    default => 'fa-file',
                };
            }
        }
    @endphp

    <div class="container py-3">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h1 class="h4 m-0">Договор #{{ $contract->id }}</h1>
            <div class="d-flex gap-2">
                <a class="btn btn-outline-secondary" href="{{ url('/client-contracts') }}">Назад</a>
            </div>
        </div>

        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        <div class="row g-3">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-body">
                        <dl class="row mb-0">
                            <dt class="col-sm-4">Родитель</dt>
                            <dd class="col-sm-8">{{ $student?->parent_full_name ?: '—' }}</dd>

                            <dt class="col-sm-4">Ученик</dt>
                            <dd class="col-sm-8">{{ $student->full_name ?? $student->name ?? '—' }}</dd>

                            <dt class="col-sm-4">Группа</dt>
                            <dd class="col-sm-8">{{ $teamTitle ?? '—' }}</dd>

                            <dt class="col-sm-4">Статус</dt>
                            <dd class="col-sm-8">
                                <span class="badge {{ $contract->status_badge_class }}">{{ $contract->status_ru }}</span>
                            </dd>

                            <dt class="col-sm-4">Способ создания</dt>
                            <dd class="col-sm-8">
                                @if($contract->isTemplateMode())
                                    Форма клиента (шаблон
                                    @if($contract->templateVersion?->template)
                                        «{{ $contract->templateVersion->template->title }}», v{{ $contract->templateVersion->version }}
                                    @endif
                                    )
                                @else
                                    Готовый PDF
                                @endif
                            </dd>

                            @if($contract->isTemplateMode() && $contract->fill_expires_at)
                                <dt class="col-sm-4">Срок заполнения</dt>
                                <dd class="col-sm-8">{{ $contract->fill_expires_at->format('d.m.Y H:i') }}</dd>
                            @endif

                            <dt class="col-sm-4">Телефон</dt>
                            <dd class="col-sm-8">{{ $student->phone ?? '—' }}</dd>

                            <dt class="col-sm-4">Почта</dt>
                            <dd class="col-sm-8">{{ $student->email ?? '—' }}</dd>
                        </dl>
                    </div>
                </div>


                <div class="card mt-3">
                    <div class="card-body d-flex flex-wrap gap-2">

                        @if($contract->status !== 'signed')
                            <button class="btn btn-outline-primary js-open-email-modal"
                                    data-id="{{ $contract->id }}"
                                    data-signed="0">
                                Отправить на email
                            </button>
                        @endif


                        @if($contract->canAdminSendSms())
                            <button class="btn btn-success" id="openSendModal" data-id="{{ $contract->id }}">Отправить СМС
                                на подпись
                            </button>
                        @endif

                        @if($contract->canRevokeWithRefund())
                            <button type="button" class="btn btn-outline-danger" id="revokeAwaitingBtn" data-id="{{ $contract->id }}">
                                Отозвать (возврат 70 ₽)
                            </button>
                        @endif

                        @if($contract->isTemplateMode() && $contract->status === \App\Models\Contract::STATUS_AWAITING_CLIENT_FILL)
                            <span class="text-muted small align-self-center">
                                Клиент заполняет договор в личном кабинете и сам отправляет SMS на подпись.
                            </span>
                        @endif

                        @if(in_array($contract->status, ['sent','opened','failed','expired']))
                            <button class="btn btn-outline-success" id="openResendModal" data-id="{{ $contract->id }}">
                                Повторно отправить на подпись
                            </button>
                        @endif

                        @can('contracts.sync')
                            @if($contract->provider_doc_id && (in_array($contract->status, ['sent','opened','failed','expired'], true) || ($contract->status === 'signed' && !$contract->signed_pdf_path)))
                                <button type="button" class="btn btn-outline-secondary" id="syncStatusBtn" data-id="{{ $contract->id }}">
                                    Синхронизировать с Подпислон
                                </button>
                            @endif
                        @endcan


                        @if($contract->status === 'signed')
                            <button class="btn btn-outline-primary js-open-email-modal"
                                    data-id="{{ $contract->id }}"
                                    data-signed="1">
                                Отправить подписанный на email
                            </button>
                        @endif

                    </div>
                </div>

                {{-- Аккордеон: История отправок (независимый, сразу открыт) --}}
                <div class="accordion accordion-flush mt-3" id="historyAccordion">
                    <div class="accordion-item">
                        <h2 class="accordion-header" id="headingHistory">
                            <button class="accordion-button" type="button"
                                    data-bs-toggle="collapse" data-bs-target="#collapseHistory"
                                    aria-expanded="true" aria-controls="collapseHistory">
                                История отправок
                            </button>
                        </h2>
                        <div id="collapseHistory" class="accordion-collapse collapse show"
                             aria-labelledby="headingHistory">
                            <div class="accordion-body p-0">
                                <div class="table-responsive">
                                    {{--<table class="table table-sm m-0 align-middle">--}}
                                        {{--<thead>--}}
                                        {{--<tr>--}}
                                            {{--<th>#</th>--}}
                                            {{--<th>ФИО подписанта</th>--}}
                                            {{--<th>Телефон</th>--}}
                                            {{--<th>Статус</th>--}}
                                            {{--<th>Создано</th>--}}
                                        {{--</tr>--}}
                                        {{--</thead>--}}
                                        {{--<tbody>--}}
                                        {{--@forelse($requests as $r)--}}
                                            {{--<tr>--}}
                                                {{--<td>{{ $r->id }}</td>--}}
                                                {{--<td>{{ $r->signer_fio ?: ($r->signer_name ?? '—') }}</td>--}}
                                                {{--<td>{{ $r->signer_phone ?? '—' }}</td>--}}
                                                {{--<td>--}}
                                                    {{--<span class="badge {{ $r->status_badge_class }}">{{ $r->status_ru }}</span>--}}
                                                {{--</td>--}}
                                                {{--<td>{{ $r->created_at->format('d.m.Y H:i:s') }}</td>--}}
                                            {{--</tr>--}}
                                        {{--@empty--}}
                                            {{--<tr>--}}
                                                {{--<td colspan="6" class="text-center text-muted py-3">Нет отправок</td>--}}
                                            {{--</tr>--}}
                                        {{--@endforelse--}}
                                        {{--</tbody>--}}
                                    {{--</table>--}}

                                    <table class="table table-sm m-0 align-middle">
                                        <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>ФИО подписанта</th>
                                            <th>Телефон</th>
                                            <th>Статус</th>
                                            <th>Создано</th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        @forelse($requests as $r)
                                            <tr>
                                                <td>{{ $loop->remaining + 1 }}</td> {{-- обратная нумерация --}}
                                                <td>{{ $r->signer_fio ?: ($r->signer_name ?? '—') }}</td>
                                                <td>{{ $r->signer_phone ?? '—' }}</td>
                                                <td>
                                                    <span class="badge {{ $r->status_badge_class }}">{{ $r->status_ru }}</span>
                                                </td>
                                                <td>{{ $r->created_at->format('d.m.Y H:i:s') }}</td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="5" class="text-center text-muted py-3">Нет отправок</td>
                                            </tr>
                                        @endforelse
                                        </tbody>
                                    </table>


                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Аккордеон: Журнал событий (независимый, по умолчанию закрыт) --}}
                <div class="accordion accordion-flush mt-3" id="eventsAccordion">
                    <div class="accordion-item">
                        <h2 class="accordion-header" id="headingEvents">
                            <button class="accordion-button collapsed" type="button"
                                    data-bs-toggle="collapse" data-bs-target="#collapseEvents"
                                    aria-expanded="false" aria-controls="collapseEvents">
                                Журнал событий
                            </button>
                        </h2>
                        <div id="collapseEvents" class="accordion-collapse collapse" aria-labelledby="headingEvents">
                            <div class="accordion-body p-0">
                                <div class="table-responsive">



                                    <table class="table table-sm m-0 align-middle">
                                        <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>Автор</th>
                                            <th>Событие</th>
                                            <th>Дата</th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        @forelse($events as $e)
                                            <tr>
                                                <td>{{ $loop->remaining + 1 }}</td> {{-- обратная нумерация --}}
                                                <td>{{ $e->author_fio }}</td>
                                                <td>{{ $e->type_ru }}</td>
                                                <td>{{ $e->created_at->format('d.m.Y H:i:s') }}</td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="4" class="text-center text-muted py-3">Пока пусто</td>
                                            </tr>
                                        @endforelse
                                        </tbody>
                                    </table>


                                </div>
                            </div>
                        </div>
                    </div>
                </div>


            </div>

            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header">Файлы</div>
                    <div class="card-body text-center">
                        <div class="d-flex justify-content-center flex-wrap gap-5 mt-3">
                            @if($contract->status === \App\Models\Contract::STATUS_SIGNED)
                                {{-- Подписан: только подписанный файл --}}
                                <a href="{{ route('contracts.downloadSigned', $contract) }}"
                                   class="d-inline-flex flex-column align-items-center text-decoration-none"
                                   title="Скачать подписанный договор">
                                    <i class="fa-solid {{ contractShowFileIconClass($contract->signed_pdf_path ?? $contract->source_pdf_path) }} fa-3x mb-2"></i>
                                    <span>Скачать подписанный</span>
                                </a>
                            @else
                                {{-- Не подписан: только оригинал --}}
                                <a href="{{ route('contracts.downloadOriginal', $contract) }}"
                                   class="d-inline-flex flex-column align-items-center text-decoration-none"
                                   title="Скачать оригинал договора">
                                    <i class="fa-solid {{ contractShowFileIconClass($contract->source_pdf_path) }} fa-3x mb-2"></i>
                                    <span>Скачать оригинал</span>
                                </a>
                            @endif
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </div>



    {{-- Модал: отправка на подпись --}}
    <div class="modal fade" id="sendModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Отправка на подпись</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="contractId" value="{{ $contract->id }}">

                    @php
                        $parentFields = $student?->parentFormFields() ?? [];
                        $defaultLast   = trim($parentFields['parent_lastname'] ?? '');
                        $defaultFirst  = trim($parentFields['parent_firstname'] ?? '');
                        $defaultMiddle = trim($parentFields['parent_middlename'] ?? '');
                    @endphp

                    <div class="mb-3">
                        <label class="form-label">Данные подписанта</label>
                        <div class="row g-2">
                            <div class="col-12 col-md-4">
                                <input type="text" class="form-control" id="signerLastname" placeholder="Фамилия"
                                       value="{{ $defaultLast }}"
                                       maxlength="100" required>
                            </div>
                            <div class="col-12 col-md-4">
                                <input type="text" class="form-control" id="signerFirstname" placeholder="Имя"
                                       value="{{ $defaultFirst }}"
                                       maxlength="100" required>
                            </div>
                            <div class="col-12 col-md-4">
                                <input type="text" class="form-control" id="signerMiddlename" placeholder="Отчество"
                                       value="{{ $defaultMiddle }}" maxlength="100">
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Телефон для SMS*</label>
                        {{--<input type="text" class="form-control" id="signerPhone" placeholder="+7..."--}}
                               {{--value="{{ $student->phone ?? '' }}" required>--}}

                        @include('includes.fields.phone-input', [
                            'name' => 'signer_phone',
                            'id' => 'signerPhone',
                            'value' => $student->phone ?? '',
                            'unmask' => true,
                            'contractFill' => true,
                            'required' => true,
                        ])

                        <div id="signerPhoneErr" class="text-danger small mt-1 d-none"></div>
                    </div>




                    <div id="sendError" class="alert alert-danger d-none"></div>
                    <div id="sendSuccess" class="alert alert-success d-none"></div>
                </div>
                <div class="modal-footer d-flex align-items-center gap-2">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Отмена</button>
                    <button type="button" class="btn btn-success" id="sendSubmit">Отправить</button>
                    <span id="sendCooldownTimer" class="text-muted small d-none"></span>
                </div>
            </div>
        </div>
    </div>

    {{-- Модал: отправка на email --}}
    <div class="modal fade" id="emailModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="emailModalTitle">Отправка договора на e-mail</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="contractIdEmail" value="{{ $contract->id }}">
                    <input type="hidden" id="emailIsSigned" value="0"><!-- сюда будем класть 0/1 -->

                    <div class="mb-3">
                        <label class="form-label">E-mail получателя</label>
                        <input type="email" class="form-control" id="recipientEmail"
                               placeholder="user@example.com" value="{{ $student->email ?? '' }}" required>
                    </div>
                    <div>Отправка договора на почту для ознакомления. Договор не подписан сторонами.</div>

                    <div id="emailSendError" class="alert alert-danger d-none"></div>
                    <div id="emailSendSuccess" class="alert alert-success d-none"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Отмена</button>
                    <button type="button" class="btn btn-primary" id="emailSendSubmit">Отправить на e-mail</button>
                </div>
            </div>
        </div>
    </div>

    <meta name="csrf-token" content="{{ csrf_token() }}">
@endsection

@push('scripts')

    <script>
        (function () {
            const csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
            const sendModalEl = document.getElementById('sendModal');
            const emailModalEl = document.getElementById('emailModal');
            const bsSendModal = new bootstrap.Modal(sendModalEl);
            const bsEmailModal = new bootstrap.Modal(emailModalEl);

            const sendSubmitBtn = document.getElementById('sendSubmit');
            const sendCooldownTimer = document.getElementById('sendCooldownTimer');
            const SMS_COOLDOWN_SEC = 30;
            let sendInProgress = false;
            let sendCooldownSec = 0;
            let sendCooldownTimerId = null;

            function renderSendSubmitState() {
                if (!sendSubmitBtn) return;

                if (sendInProgress) {
                    sendSubmitBtn.disabled = true;
                    sendSubmitBtn.textContent = 'Отправка…';
                    if (sendCooldownTimer) {
                        sendCooldownTimer.classList.add('d-none');
                        sendCooldownTimer.textContent = '';
                    }
                    return;
                }

                if (sendCooldownSec > 0) {
                    sendSubmitBtn.disabled = true;
                    sendSubmitBtn.textContent = 'Отправить';
                    if (sendCooldownTimer) {
                        sendCooldownTimer.classList.remove('d-none');
                        sendCooldownTimer.textContent = 'Повторная отправка через ' + sendCooldownSec + ' сек.';
                    }
                    return;
                }

                sendSubmitBtn.disabled = false;
                sendSubmitBtn.textContent = 'Отправить';
                if (sendCooldownTimer) {
                    sendCooldownTimer.classList.add('d-none');
                    sendCooldownTimer.textContent = '';
                }
            }

            function startSendCooldown(seconds) {
                sendCooldownSec = Math.max(0, parseInt(seconds, 10) || SMS_COOLDOWN_SEC);
                clearInterval(sendCooldownTimerId);
                renderSendSubmitState();
                if (sendCooldownSec <= 0) return;

                sendCooldownTimerId = setInterval(function () {
                    sendCooldownSec--;
                    if (sendCooldownSec <= 0) {
                        clearInterval(sendCooldownTimerId);
                        sendCooldownTimerId = null;
                    }
                    renderSendSubmitState();
                }, 1000);
            }

            function resetSendModalAlerts() {
                document.getElementById('sendError').classList.add('d-none');
                document.getElementById('sendSuccess').classList.add('d-none');
            }

            // Открыть модал для отправки на подпись
            document.getElementById('openSendModal')?.addEventListener('click', function () {
                resetSendModalAlerts();
                renderSendSubmitState();
                bsSendModal.show();
            });

            // Повторная отправка — тот же модал
            document.getElementById('openResendModal')?.addEventListener('click', function () {
                resetSendModalAlerts();
                renderSendSubmitState();
                bsSendModal.show();
            });



            // Открытие модалки: одна точка входа для «оригинал» и «подписанный»
            document.querySelectorAll('.js-open-email-modal').forEach(btn => {
                btn.addEventListener('click', function () {
                    const isSigned = this.getAttribute('data-signed') === '1';
                    const contractId = this.getAttribute('data-id');

                    // проставим значения
                    document.getElementById('contractIdEmail').value = contractId;
                    document.getElementById('emailIsSigned').value = isSigned ? '1' : '0';

                    // заголовок модалки по типу
                    document.getElementById('emailModalTitle').textContent =
                        isSigned ? 'Отправка подписанного договора на e-mail'
                            : 'Отправка договора на e-mail';

                    // очистим алерты
                    document.getElementById('emailSendError').classList.add('d-none');
                    document.getElementById('emailSendSuccess').classList.add('d-none');

                    bsEmailModal.show();
                });
            });


            // Отправка на подпись (AJAX, прямой URL)
            $('#sendSubmit').on('click', function () {
                if (sendInProgress || sendCooldownSec > 0) {
                    return;
                }

                var contractId = $('#contractId').val();
                sendInProgress = true;
                renderSendSubmitState();

                function onSendSuccess() {
                    sendInProgress = false;
                    startSendCooldown(SMS_COOLDOWN_SEC);
                    showSuccessModal("Отправка сообщения", "СМС сообщение успешно отправлено.", 1);
                }

                function onSendFailure(resp) {
                    sendInProgress = false;
                    renderSendSubmitState();

                    var json = resp && resp.responseJSON ? resp.responseJSON : (resp || {});
                    if (json.code === 'sms_cooldown') {
                        startSendCooldown(json.cooldown_sec || SMS_COOLDOWN_SEC);
                    }

                    var msg = json.message ? json.message : 'Ошибка.';
                    $('#error-modal-message').text(msg);
                    eroorRespone({responseJSON: json.message ? json : {message: msg}});
                }

                $.ajax({
                        type: 'POST',
                        url: '/client-contracts/' + contractId + '/send',
                        dataType: 'json',
                        headers: {'Accept': 'application/json'},
                        data: {
                            _token: $('meta[name="csrf-token"]').attr('content'),
                            signer_lastname:   $('#signerLastname').val(),
                            signer_firstname:  $('#signerFirstname').val(),
                            signer_middlename: $('#signerMiddlename').val(),
                            signer_phone:      $('#signerPhone').val()
                            // ttl_hours не шлём (сервер подставит 72)
                    },

                    success: function (resp) {
                        if (resp && resp.success === true) {
                            onSendSuccess();
                        } else {
                            onSendFailure({responseJSON: resp || {message: 'Ошибка.'}});
                        }
                    },

                    error: function (xhr, textStatus, errorThrown) {
                        if (xhr && xhr.status === 200) {
                            try {
                                var json = JSON.parse(xhr.responseText || '{}');
                                if (json && json.success === true) {
                                    onSendSuccess();
                                    return;
                                }
                                onSendFailure({responseJSON: json || {message: 'Ошибка.'}});
                                return;
                            } catch (e) {
                                onSendSuccess();
                                return;
                            }
                        }

                        onSendFailure(xhr || {responseJSON: {message: errorThrown || 'Ошибка.'}});
                    },

                    complete: function () {
                        if (sendInProgress) {
                            sendInProgress = false;
                            renderSendSubmitState();
                        }
                    }
                });
            });


            $('#emailSendSubmit').on('click', function () {
                var contractId = $('#contractIdEmail').val();
                var isSigned = $('#emailIsSigned').val() === '1';

                $.ajax({
                    type: 'POST',
                    url: '/client-contracts/' + contractId + '/send-email',
                    data: {
                        _token: csrf,
                        email: $('#recipientEmail').val(),
                        signed: isSigned ? 1 : 0
                    },
                    success: function (resp) {
                        // ваш метод успеха
                        var title = isSigned ? 'Отправка подписанного договора' : 'Отправка договора';
                        var text = isSigned ? 'Подписанный договор отправлен на e-mail.'
                            : 'Договор успешно отправлен на e-mail.';
                        showSuccessModal(title, text, 1);
                    },
                    error: function (response) {
                        var msg = (response && response.responseJSON && response.responseJSON.message)
                            ? response.responseJSON.message
                            : (response && response.message) ? response.message : 'Ошибка.';
                        $('#error-modal-message').text(msg);
                        eroorRespone(response);
                    }
                });
            });

            $('#revokeAwaitingBtn').on('click', function () {
                if (!confirm('Отозвать договор? Клиент не сможет его заполнить. 70 ₽ будут возвращены на баланс партнёра.')) {
                    return;
                }
                var contractId = $(this).data('id');
                $.ajax({
                    method: 'POST',
                    url: '/client-contracts/' + contractId + '/revoke',
                    dataType: 'json',
                    headers: {'Accept': 'application/json'},
                    data: {_token: csrf}
                }).done(function (resp) {
                    alert((resp && resp.message) ? resp.message : 'Договор отозван.');
                    location.reload();
                }).fail(function (xhr) {
                    alert((xhr.responseJSON && xhr.responseJSON.message) || 'Ошибка отзыва.');
                });
            });

            // Обновить статус
            $('#syncStatusBtn').on('click', function () {
                var contractId = $(this).data('id');
                $.ajax({
                    method: 'GET',
                    url: '/client-contracts/' + contractId + '/status'
                }).done(function (resp) {
                    if (resp && resp.message && resp.synced === false) {
                        alert(resp.message);
                    }
                    location.reload();
                }).fail(function (xhr) {
                    alert((xhr.responseJSON && xhr.responseJSON.message) || 'Ошибка');
                });
            });
        })();
    </script>
@endpush
