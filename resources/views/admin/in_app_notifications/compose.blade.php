@extends('layouts.admin2')

@php
    $cssPath = public_path('css/in-app-notifications.css');
@endphp

@section('content')
    @vite(['resources/css/admin-list-toolbar.css'])
    <link rel="stylesheet" href="{{ asset('css/in-app-notifications.css') }}?v={{ @filemtime($cssPath) ?: time() }}">

    <div class="main-content text-start ian-page ian-compose">
        <div class="card payments-report-surface border-0 shadow-sm mb-3 mt-2">
            <div class="card-body px-3 py-3">
                <div class="payments-report-toolbar d-flex flex-nowrap align-items-center justify-content-between gap-2 gap-md-3 min-w-0">
                    <h1 class="h5 mb-0 fw-semibold text-body payments-report-title text-truncate min-w-0 flex-shrink-1">Новое уведомление</h1>
                    <div class="d-flex align-items-center gap-2 payments-report-toolbar-actions flex-shrink-0">
                        <a href="{{ route('inAppNotifications.index') }}"
                           class="payments-report-toolbar-action d-inline-flex align-items-center gap-2 text-decoration-none">
                            <span class="payments-report-toolbar-icon-wrap" aria-hidden="true">
                                <i class="fas fa-arrow-left payments-report-toolbar-icon"></i>
                            </span>
                            <span class="payments-report-toolbar-label d-none d-sm-inline">К ленте</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div class="card payments-report-surface border-0 shadow-sm">
            <div class="card-body px-3 py-4">
                <form method="POST"
                      action="{{ route('inAppNotifications.store') }}"
                      id="inAppNotificationComposeForm"
                      data-roles-url="{{ route('inAppNotifications.compose.roles') }}"
                      novalidate>
                    @csrf

                    <div class="mb-3">
                        <label for="inAppTitle" class="form-label">Заголовок</label>
                        <input type="text"
                               name="title"
                               id="inAppTitle"
                               class="form-control @error('title') is-invalid @enderror"
                               value="{{ old('title') }}"
                               maxlength="160"
                               required>
                        @error('title')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label for="inAppBody" class="form-label">Текст</label>
                        <textarea name="body"
                                  id="inAppBody"
                                  rows="6"
                                  class="form-control @error('body') is-invalid @enderror"
                                  required>{{ old('body') }}</textarea>
                        <div class="form-text ian-compose-hint">
                            Выделите фрагмент и нажмите иконку ссылки, чтобы сделать переход внутри текста.
                        </div>
                        @error('body')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label for="inAppCategory" class="form-label">Тип</label>
                        <select name="category" id="inAppCategory" class="form-select @error('category') is-invalid @enderror" required>
                            @foreach($categories as $value => $label)
                                <option value="{{ $value }}" @selected(old('category', $defaults['category']) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('category')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3 form-check">
                        <input type="hidden" name="all_partners" value="0">
                        <input type="checkbox"
                               class="form-check-input @error('all_partners') is-invalid @enderror"
                               name="all_partners"
                               id="inAppAllPartners"
                               value="1"
                               @checked(old('all_partners', $defaults['all_partners']))>
                        <label class="form-check-label" for="inAppAllPartners">Все школы</label>
                        @error('all_partners')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3" id="inAppPartnersWrap">
                        <label for="inAppPartnerIds" class="form-label">Школы</label>
                        <select name="partner_ids[]"
                                id="inAppPartnerIds"
                                class="form-select @error('partner_ids') is-invalid @enderror"
                                multiple>
                            @foreach($partners as $partner)
                                <option value="{{ $partner->id }}"
                                        @selected(collect(old('partner_ids', []))->contains($partner->id))>
                                    {{ $partner->title }}{{ $partner->is_enabled ? '' : ' (выкл.)' }}
                                </option>
                            @endforeach
                        </select>
                        @error('partner_ids')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label for="inAppRoleIds" class="form-label">Роли</label>
                        <select name="role_ids[]"
                                id="inAppRoleIds"
                                class="form-select @error('role_ids') is-invalid @enderror"
                                multiple
                                required>
                            @foreach($roles as $role)
                                <option value="{{ $role->id }}"
                                        @selected(collect(old('role_ids', []))->contains($role->id))>
                                    {{ $role->label ?: $role->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('role_ids')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                        <div class="form-text">Кастомные роли доступны, только если выбрана одна школа.</div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="inAppTtlPreset" class="form-label">Срок жизни</label>
                            <select name="ttl_preset" id="inAppTtlPreset" class="form-select @error('ttl_preset') is-invalid @enderror" required>
                                @foreach($ttlPresets as $value => $label)
                                    <option value="{{ $value }}" @selected(old('ttl_preset', $defaults['ttl_preset']) === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('ttl_preset')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-6 mb-3" id="inAppCustomExpiresWrap">
                            <label for="inAppCustomExpiresAt" class="form-label">Дата окончания</label>
                            <input type="date"
                                   name="custom_expires_at"
                                   id="inAppCustomExpiresAt"
                                   class="form-control @error('custom_expires_at') is-invalid @enderror"
                                   value="{{ old('custom_expires_at') }}"
                                   min="{{ now('Europe/Moscow')->toDateString() }}">
                            @error('custom_expires_at')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary">Отправить</button>
                </form>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('plugins/summernote/lang/summernote-ru-RU.min.js') }}"></script>
    <script>
        (function () {
            var form = document.getElementById('inAppNotificationComposeForm');
            if (!form) {
                return;
            }
            var rolesUrl = form.getAttribute('data-roles-url');
            var allPartners = document.getElementById('inAppAllPartners');
            var partnersWrap = document.getElementById('inAppPartnersWrap');
            var partnerSelect = document.getElementById('inAppPartnerIds');
            var roleSelect = document.getElementById('inAppRoleIds');
            var ttlPreset = document.getElementById('inAppTtlPreset');
            var customWrap = document.getElementById('inAppCustomExpiresWrap');
            var body = document.getElementById('inAppBody');

            if (window.jQuery && body && window.jQuery.fn.summernote) {
                var $body = window.jQuery(body);
                $body.summernote({
                    height: 220,
                    lang: 'ru-RU',
                    disableDragAndDrop: true,
                    placeholder: 'Текст сообщения…',
                    toolbar: [
                        ['style', ['bold', 'italic', 'underline', 'clear']],
                        ['para', ['ul', 'ol']],
                        ['insert', ['link']]
                    ],
                    popover: {
                        image: [],
                        air: []
                    }
                });
                if ($body.hasClass('is-invalid')) {
                    $body.next('.note-editor').addClass('is-invalid');
                }
                form.addEventListener('submit', function () {
                    $body.val($body.summernote('code'));
                });
            }

            function togglePartners() {
                var checked = !!(allPartners && allPartners.checked);
                if (partnersWrap) {
                    partnersWrap.style.display = checked ? 'none' : '';
                }
            }

            function toggleCustomDate() {
                var show = ttlPreset && ttlPreset.value === 'custom';
                if (customWrap) {
                    customWrap.style.display = show ? '' : 'none';
                }
            }

            function selectedPartnerIds() {
                if (!partnerSelect) {
                    return [];
                }
                return Array.prototype.slice.call(partnerSelect.selectedOptions).map(function (opt) {
                    return Number(opt.value);
                }).filter(function (id) { return id > 0; });
            }

            function reloadRoles() {
                if (!rolesUrl || !roleSelect) {
                    return;
                }
                var params = new URLSearchParams();
                var isAll = !!(allPartners && allPartners.checked);
                params.set('all_partners', isAll ? '1' : '0');
                if (!isAll) {
                    selectedPartnerIds().forEach(function (id) {
                        params.append('partner_ids[]', String(id));
                    });
                }
                var selected = Array.prototype.slice.call(roleSelect.selectedOptions).map(function (opt) {
                    return String(opt.value);
                });
                fetch(rolesUrl + '?' + params.toString(), {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin'
                }).then(function (response) {
                    if (!response.ok) {
                        return null;
                    }
                    return response.json();
                }).then(function (data) {
                    if (!data || !Array.isArray(data.roles)) {
                        return;
                    }
                    roleSelect.innerHTML = '';
                    data.roles.forEach(function (role) {
                        var opt = document.createElement('option');
                        opt.value = String(role.id);
                        opt.textContent = role.label || role.name;
                        if (selected.indexOf(String(role.id)) !== -1) {
                            opt.selected = true;
                        }
                        roleSelect.appendChild(opt);
                    });
                    if (window.jQuery && window.jQuery.fn.select2) {
                        window.jQuery(roleSelect).trigger('change.select2');
                    }
                }).catch(function () {});
            }

            if (window.jQuery && window.jQuery.fn.select2) {
                window.jQuery(partnerSelect).select2({
                    theme: 'bootstrap-5',
                    width: '100%',
                    placeholder: 'Выберите школы'
                });
                window.jQuery(roleSelect).select2({
                    theme: 'bootstrap-5',
                    width: '100%',
                    placeholder: 'Выберите роли'
                });
                window.jQuery(partnerSelect).on('change', reloadRoles);
            } else if (partnerSelect) {
                partnerSelect.addEventListener('change', reloadRoles);
            }

            if (allPartners) {
                allPartners.addEventListener('change', function () {
                    togglePartners();
                    reloadRoles();
                });
            }
            if (ttlPreset) {
                ttlPreset.addEventListener('change', toggleCustomDate);
            }

            togglePartners();
            toggleCustomDate();
        })();
    </script>
@endpush
