@extends('layouts.admin2')

@php
    $activeTab = 'legal-entities';
    $isRegistered = trim((string) ($entity->tinkoff_shop_code ?? '')) !== '';
@endphp

@section('content')
    <div class="main-content text-start">
        <h4 class="pt-3 pb-3 text-start">Справочники</h4>

        @include('admin.directories._section_tabs')

        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
            <div>
                <h1 class="h5 mb-1">{{ $entity->title }}</h1>
                <div class="text-muted small">
                    <a href="{{ route('admin.legal-entities.index') }}">← К списку юр. лиц</a>
                </div>
            </div>
            @can('legal_entities.manage')
                <div class="d-flex gap-2">
                    @if ($isRegistered)
                        <form method="POST" action="{{ route('admin.legal-entities.sm-refresh', $entity) }}" class="d-inline js-sm-action-form">
                            @csrf
                            <button type="submit" class="btn btn-sm btn-outline-secondary">Обновить статус</button>
                        </form>
                        <form method="POST" action="{{ route('admin.legal-entities.sm-pull', $entity) }}" class="d-inline js-sm-action-form">
                            @csrf
                            <button type="submit" class="btn btn-sm btn-outline-secondary">Подтянуть из банка</button>
                        </form>
                    @endif
                </div>
            @endcan
        </div>

        @if ($errors->any())
            <div class="alert alert-danger">
                @foreach ($errors->all() as $e)
                    <div>{{ $e }}</div>
                @endforeach
            </div>
        @endif
        @if (session('ok'))
            <div class="alert alert-success">{{ session('ok') }}</div>
        @endif

        <div class="card mb-3">
            <div class="card-body">
                <div class="row g-2 small">
                    <div class="col-md-3"><b>ShopCode:</b> {{ $entity->tinkoff_shop_code ?? '—' }}</div>
                    <div class="col-md-3"><b>sm-register:</b> {{ $entity->sm_register_status ?? 'Не зарегистрирован' }}</div>
                    <div class="col-md-3"><b>Версия реквизитов:</b> {{ $entity->bank_details_version ?? 0 }}</div>
                    <div class="col-md-3"><b>Обновлены:</b> {{ $entity->bank_details_last_updated_at?->format('d.m.Y H:i') ?? '—' }}</div>
                    <div class="col-md-3"><b>Основное:</b> {{ $entity->is_default ? 'Да' : 'Нет' }}</div>
                    <div class="col-md-3"><b>Групп:</b> {{ $entity->teams()->count() }}</div>
                </div>
            </div>
        </div>

        @can('legal_entities.manage')
            <div class="card mb-3">
                <div class="card-header">Реквизиты CRM</div>
                <div class="card-body">
                    <form id="legalEntityCrudForm" method="POST" action="{{ route('admin.legal-entities.update', $entity) }}">
                        @csrf
                        @method('PUT')
                        @include('admin.legal-entities.partials.crud-fields', ['prefix' => 'show', 'entity' => $entity])
                        <div class="mt-3">
                            <button type="submit" class="btn btn-primary">Сохранить реквизиты CRM</button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header">{{ $isRegistered ? 'Обновление в sm-register' : 'Регистрация в sm-register' }}</div>
                <div class="card-body">
                    <form id="legalEntitySmForm"
                          action="{{ $isRegistered ? route('admin.legal-entities.sm-patch', $entity) : route('admin.legal-entities.sm-register', $entity) }}"
                          method="POST">
                        @csrf
                        @include('admin.legal-entities.partials.sm-form-fields', ['entity' => $entity, 'partner' => $partner])
                        <div class="mt-3">
                            <button type="submit" class="btn btn-success">
                                {{ $isRegistered ? 'Обновить в sm-register' : 'Зарегистрировать в sm-register' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        @else
            <div class="alert alert-secondary">Редактирование доступно при праве «Юр. лица: создание/редактирование».</div>
        @endcan
    </div>
@endsection

@push('scripts')
    <script>
        $(function () {
            function toggleKpp() {
                const bt = $('.js-legal-entity-business-type').val();
                const isOoo = bt === 'OOO';
                $('.js-kpp-field').toggle(isOoo);
            }

            toggleKpp();
            $(document).on('change', '.js-legal-entity-business-type', toggleKpp);

            const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

            function clearSmErrors(form) {
                form.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
                form.querySelectorAll('[data-error-for]').forEach(el => el.textContent = '');
                const alert = document.getElementById('legalEntitySmError');
                if (alert) {
                    alert.classList.add('d-none');
                    alert.textContent = '';
                }
            }

            function applySmErrors(form, errors) {
                Object.entries(errors || {}).forEach(([key, messages]) => {
                    const message = (messages && messages[0]) ? messages[0] : 'Ошибка';
                    const input = form.querySelector('[name="' + key + '"]');
                    const err = form.querySelector('[data-error-for="' + key + '"]');
                    if (input) input.classList.add('is-invalid');
                    if (err) err.textContent = message;
                });
            }

            $('#legalEntitySmForm').on('submit', async function (e) {
                e.preventDefault();
                const form = this;
                clearSmErrors(form);
                const fd = new FormData(form);
                const res = await fetch(form.action, {
                    method: 'POST',
                    body: fd,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': token,
                    }
                });
                const data = await res.json().catch(() => ({}));
                if (!res.ok) {
                    applySmErrors(form, data.errors || {});
                    const alert = document.getElementById('legalEntitySmError');
                    if (alert && (data.message || data.error)) {
                        alert.textContent = data.message || data.error;
                        alert.classList.remove('d-none');
                    }
                    return;
                }
                window.location.reload();
            });

            $('#legalEntityCrudForm').on('submit', async function (e) {
                e.preventDefault();
                const form = this;
                form.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
                form.querySelectorAll('[data-error-for]').forEach(el => el.textContent = '');
                const fd = new FormData(form);
                fd.set('_method', 'PUT');
                const res = await fetch(form.action, {
                    method: 'POST',
                    body: fd,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': token,
                    }
                });
                const data = await res.json().catch(() => ({}));
                if (!res.ok && res.status === 422) {
                    Object.entries(data.errors || {}).forEach(([key, messages]) => {
                        const message = (messages && messages[0]) ? messages[0] : 'Ошибка';
                        const input = form.querySelector('[name="' + key + '"]');
                        const err = form.querySelector('[data-error-for="' + key + '"]');
                        if (input) input.classList.add('is-invalid');
                        if (err) err.textContent = message;
                    });
                    return;
                }
                if (res.ok) {
                    window.location.reload();
                }
            });

            $('.js-sm-action-form').on('submit', async function (e) {
                e.preventDefault();
                const form = this;
                const res = await fetch(form.action, {
                    method: 'POST',
                    body: new FormData(form),
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': token,
                    }
                });
                if (res.ok) {
                    window.location.reload();
                } else {
                    const data = await res.json().catch(() => ({}));
                    alert(data.message || data.error || 'Ошибка запроса');
                }
            });
        });
    </script>
@endpush
