@extends('layouts.admin2')

@section('title','Договор #'.$contract->id)

@section('content')
    <div class="container py-3">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h1 class="h4 m-0">Договор #{{ $contract->id }}</h1>
            <div class="d-flex gap-2">
                <a class="btn btn-outline-secondary" href="{{ url('/contracts') }}">Назад</a>
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
                            <dt class="col-sm-4">Ученик (user_id)</dt>
                            <dd class="col-sm-8">{{ $contract->user_id }}</dd>

                            <dt class="col-sm-4">Группа</dt>
                            <dd class="col-sm-8">{{ $contract->group_id ?? '—' }}</dd>

                            <dt class="col-sm-4">Статус</dt>
                            <dd class="col-sm-8"><span class="badge bg-secondary">{{ $contract->status }}</span></dd>

                            <dt class="col-sm-4">Провайдер</dt>
                            <dd class="col-sm-8">{{ $contract->provider }}</dd>

                            <dt class="col-sm-4">SHA-256</dt>
                            <dd class="col-sm-8"><code style="word-break: break-all;">{{ $contract->source_sha256 }}</code></dd>
                        </dl>
                    </div>
                </div>

                <div class="card mt-3">
                    <div class="card-body d-flex flex-wrap gap-2">
                        <a href="{{ url('/contracts/'.$contract->id.'/download-original') }}" class="btn btn-outline-primary">Скачать оригинал</a>
                        @if($contract->signed_pdf_path)
                            <a href="{{ url('/contracts/'.$contract->id.'/download-signed') }}" class="btn btn-primary">Скачать подписанный</a>
                        @endif

                        @if($contract->status === 'draft')
                            <button class="btn btn-success" id="openSendModal" data-id="{{ $contract->id }}">Отправить на подпись</button>
                        @endif

                        @if(in_array($contract->status, ['sent','opened','failed','expired']))
                            <button class="btn btn-outline-success" id="openResendModal" data-id="{{ $contract->id }}">Повторно отправить</button>
                            <button class="btn btn-outline-danger" id="revokeBtn" data-id="{{ $contract->id }}">Отозвать</button>
                            <a class="btn btn-outline-secondary" id="syncStatusBtn" data-id="{{ $contract->id }}">Обновить статус</a>
                        @endif
                    </div>
                </div>

                <div class="card mt-3">
                    <div class="card-header">История отправок</div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table m-0">
                                <thead><tr><th>#</th><th>Имя</th><th>Телефон</th><th>TTL</th><th>Статус</th><th>Создан</th></tr></thead>
                                <tbody>
                                @forelse($requests as $r)
                                    <tr>
                                        <td>{{ $r->id }}</td>
                                        <td>{{ $r->signer_name ?? '—' }}</td>
                                        <td>{{ $r->signer_phone }}</td>
                                        <td>{{ $r->ttl_hours ?? '—' }}</td>
                                        <td>{{ $r->status }}</td>
                                        <td>{{ $r->created_at->format('d.m.Y H:i') }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="6" class="text-center text-muted py-3">Нет отправок</td></tr>
                                @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="card mt-3">
                    <div class="card-header">Журнал событий</div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table m-0">
                                <thead><tr><th>#</th><th>Событие</th><th>Дата</th></tr></thead>
                                <tbody>
                                @forelse($events as $e)
                                    <tr>
                                        <td>{{ $e->id }}</td>
                                        <td>{{ $e->type }}</td>
                                        <td>{{ $e->created_at->format('d.m.Y H:i') }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="3" class="text-center text-muted py-3">Пока пусто</td></tr>
                                @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            </div>

            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header">Файлы</div>
                    <div class="card-body">
                        <p class="mb-1"><strong>Оригинал:</strong></p>
                        <p class="small text-break">{{ $contract->source_pdf_path }}</p>
                        @if($contract->signed_pdf_path)
                            <hr>
                            <p class="mb-1"><strong>Подписанный:</strong></p>
                            <p class="small text-break">{{ $contract->signed_pdf_path }}</p>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Модал отправки --}}
    <div class="modal fade" id="sendModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Отправка на подпись</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="contractId" value="{{ $contract->id }}">
                    <div class="mb-3">
                        <label class="form-label">Имя подписанта (опц.)</label>
                        <input type="text" class="form-control" id="signerName" maxlength="255">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Телефон для SMS (обяз.)</label>
                        <input type="text" class="form-control" id="signerPhone" placeholder="+7..." required>
                        <div id="signerPhoneErr" class="text-danger small mt-1 d-none"></div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Срок действия ссылки (часов)</label>
                        <input type="number" class="form-control" id="ttlHours" value="72" min="1" max="168">
                    </div>
                    <div id="sendError" class="alert alert-danger d-none"></div>
                    <div id="sendSuccess" class="alert alert-success d-none"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Отмена</button>
                    <button type="button" class="btn btn-success" id="sendSubmit">Отправить</button>
                </div>
            </div>
        </div>
    </div>

    <meta name="csrf-token" content="{{ csrf_token() }}">

@endsection

@push('scripts')
    <script>
        (function(){
            const csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
            const sendModalEl = document.getElementById('sendModal');
            const bsSendModal = new bootstrap.Modal(sendModalEl);

            // Открыть модал для отправки
            document.getElementById('openSendModal')?.addEventListener('click', function(){
                document.getElementById('sendError').classList.add('d-none');
                document.getElementById('sendSuccess').classList.add('d-none');
                bsSendModal.show();
            });

            // Повторная отправка = такой же модал
            document.getElementById('openResendModal')?.addEventListener('click', function(){
                document.getElementById('sendError').classList.add('d-none');
                document.getElementById('sendSuccess').classList.add('d-none');
                bsSendModal.show();
            });

            // Отправка (jQuery + прямой URL)
            $('#sendSubmit').on('click', function(){
                var contractId = $('#contractId').val();
                $.ajax({
                    method: 'POST',
                    url: '/contracts/' + contractId + '/send',
                    data: {
                        _token: csrf,
                        signer_name: $('#signerName').val(),
                        signer_phone: $('#signerPhone').val(),
                        ttl_hours: $('#ttlHours').val()
                    }
                }).done(function(resp){
                    $('#sendError').addClass('d-none');
                    $('#sendSuccess').removeClass('d-none').text(resp.message || 'Отправлено');
                    setTimeout(function(){ location.reload(); }, 800);
                }).fail(function(xhr){
                    var msg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Ошибка отправки';
                    $('#sendError').removeClass('d-none').text(msg);
                });
            });

            // Отозвать
            $('#revokeBtn').on('click', function(){
                var contractId = $(this).data('id');
                if(!confirm('Отозвать подпись?')) return;
                $.ajax({
                    method: 'POST',
                    url: '/contracts/' + contractId + '/revoke',
                    data: {_token: csrf}
                }).done(function(resp){
                    alert(resp.message || 'Отозвано');
                    location.reload();
                }).fail(function(xhr){
                    alert((xhr.responseJSON && xhr.responseJSON.message) || 'Ошибка');
                });
            });

            // Обновить статус
            $('#syncStatusBtn').on('click', function(){
                var contractId = $(this).data('id');
                $.ajax({
                    method: 'GET',
                    url: '/contracts/' + contractId + '/status'
                }).done(function(resp){
                    location.reload();
                }).fail(function(xhr){
                    alert((xhr.responseJSON && xhr.responseJSON.message) || 'Ошибка');
                });
            });
        })();
    </script>
@endpush
