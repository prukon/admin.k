@extends('layouts.admin2')

@php
    use Illuminate\Support\Carbon;

    $statusLabel = match ($log->status) {
        \App\Models\OutgoingEmailLog::STATUS_SENT    => 'отправлено',
        \App\Models\OutgoingEmailLog::STATUS_SENDING => 'в процессе',
        \App\Models\OutgoingEmailLog::STATUS_FAILED  => 'ошибка',
        default => (string) $log->status,
    };
    $statusClass = match ($log->status) {
        \App\Models\OutgoingEmailLog::STATUS_SENT    => 'bg-success',
        \App\Models\OutgoingEmailLog::STATUS_SENDING => 'bg-warning text-dark',
        \App\Models\OutgoingEmailLog::STATUS_FAILED  => 'bg-danger',
        default => 'bg-secondary',
    };

    $renderAddrs = function ($list) {
        if (! is_array($list) || $list === []) {
            return '—';
        }
        $parts = [];
        foreach ($list as $a) {
            if (! is_array($a)) {
                continue;
            }
            $addr = (string) ($a['address'] ?? '');
            if ($addr === '') {
                continue;
            }
            $name = (string) ($a['name'] ?? '');
            $parts[] = $name !== '' ? sprintf('%s <%s>', $name, $addr) : $addr;
        }
        return $parts === [] ? '—' : e(implode(', ', $parts));
    };
@endphp

@section('content')
<div class="container-fluid py-3">
    <div class="d-flex flex-wrap gap-2 align-items-center mb-3">
        <a href="{{ route('reports.emails.index') }}" class="btn btn-outline-secondary btn-sm">
            <i class="fas fa-arrow-left me-1"></i> Назад к списку
        </a>
        <h1 class="h4 mb-0 ms-2">Письмо #{{ $log->id }}</h1>
        <span class="badge {{ $statusClass }} ms-2">{{ $statusLabel }}</span>
    </div>

    <div class="card mb-3">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-12 col-md-6">
                    <div class="text-muted small">Создано</div>
                    <div>{{ optional($log->created_at)->format('Y-m-d H:i:s') ?? '—' }}</div>
                </div>
                <div class="col-12 col-md-6">
                    <div class="text-muted small">Отправлено</div>
                    <div>{{ optional($log->sent_at)->format('Y-m-d H:i:s') ?? '—' }}</div>
                </div>
                <div class="col-12 col-md-6">
                    <div class="text-muted small">От кого</div>
                    <div>
                        @if($log->from_address)
                            @if($log->from_name)
                                {{ $log->from_name }} &lt;{{ $log->from_address }}&gt;
                            @else
                                {{ $log->from_address }}
                            @endif
                        @else
                            —
                        @endif
                    </div>
                </div>
                <div class="col-12 col-md-6">
                    <div class="text-muted small">Кому</div>
                    <div>{!! $renderAddrs($log->to_addresses) !!}</div>
                </div>
                @if(is_array($log->cc_addresses) && $log->cc_addresses !== [])
                    <div class="col-12 col-md-6">
                        <div class="text-muted small">Cc</div>
                        <div>{!! $renderAddrs($log->cc_addresses) !!}</div>
                    </div>
                @endif
                @if(is_array($log->bcc_addresses) && $log->bcc_addresses !== [])
                    <div class="col-12 col-md-6">
                        <div class="text-muted small">Bcc</div>
                        <div>{!! $renderAddrs($log->bcc_addresses) !!}</div>
                    </div>
                @endif
                @if(is_array($log->reply_to) && $log->reply_to !== [])
                    <div class="col-12 col-md-6">
                        <div class="text-muted small">Reply-To</div>
                        <div>{!! $renderAddrs($log->reply_to) !!}</div>
                    </div>
                @endif
                <div class="col-12">
                    <div class="text-muted small">Тема</div>
                    <div class="fw-semibold">{{ $log->subject ?? '—' }}</div>
                </div>
                <div class="col-12 col-md-6">
                    <div class="text-muted small">Mailable / Notification</div>
                    <div>
                        {{ $log->mailable_class ?? ($log->notification_class ?? '—') }}
                    </div>
                </div>
                <div class="col-12 col-md-6">
                    <div class="text-muted small">Очередь</div>
                    <div>{{ $log->queue ?? '—' }}@if($log->database_queue_job_id) (job #{{ $log->database_queue_job_id }})@endif</div>
                </div>
                <div class="col-12 col-md-6">
                    <div class="text-muted small">Попытки</div>
                    <div>{{ (int) $log->send_attempts }}</div>
                </div>
                <div class="col-12 col-md-6">
                    <div class="text-muted small">Ошибка (failed_at)</div>
                    <div>{{ optional($log->failed_at)->format('Y-m-d H:i:s') ?? '—' }}</div>
                </div>
            </div>
        </div>
    </div>

    @if(! empty($log->error_message))
        <div class="card mb-3 border-danger">
            <div class="card-header bg-danger text-white">Сообщение об ошибке</div>
            <div class="card-body">
                <pre class="mb-0 small" style="white-space: pre-wrap; word-break: break-word;">{{ $log->error_message }}</pre>
            </div>
        </div>
    @endif

    @if(is_array($log->attachments) && $log->attachments !== [])
        <div class="card mb-3">
            <div class="card-header">Вложения</div>
            <div class="card-body">
                <ul class="mb-0">
                    @foreach($log->attachments as $a)
                        <li>
                            {{ is_array($a) ? ($a['filename'] ?? '—') : (string) $a }}
                            @if(is_array($a) && ! empty($a['content_type']))
                                <span class="text-muted small">({{ $a['content_type'] }})</span>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    @endif

    <ul class="nav nav-tabs" id="emailBodyTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="email-html-tab" data-bs-toggle="tab" data-bs-target="#email-html" type="button" role="tab">HTML</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="email-text-tab" data-bs-toggle="tab" data-bs-target="#email-text" type="button" role="tab">Текст</button>
        </li>
    </ul>
    <div class="tab-content border border-top-0 rounded-bottom p-3 bg-white">
        <div class="tab-pane fade show active" id="email-html" role="tabpanel">
            @if(! empty($log->html_body))
                <iframe sandbox="" srcdoc="{{ e($log->html_body) }}" style="width:100%; min-height: 480px; border: 1px solid #e5e5e5; border-radius: 4px;"></iframe>
            @else
                <div class="text-muted">HTML-тело отсутствует.</div>
            @endif
        </div>
        <div class="tab-pane fade" id="email-text" role="tabpanel">
            @if(! empty($log->text_body))
                <pre class="mb-0 small" style="white-space: pre-wrap; word-break: break-word;">{{ $log->text_body }}</pre>
            @else
                <div class="text-muted">Текстовое тело отсутствует.</div>
            @endif
        </div>
    </div>
</div>
@endsection
