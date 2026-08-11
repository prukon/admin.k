@php
    $testEmailDefault = $paymentNotificationTestEmail ?? '';
@endphp

<div class="container setting-price-wrap payment-notifications-wrap mt-3"
     id="payment-notifications-root"
     data-rules-url="{{ route('admin.settingPrices.paymentNotifications.rules.index') }}"
     data-store-url="{{ route('admin.settingPrices.paymentNotifications.rules.store') }}"
     data-update-url-template="{{ route('admin.settingPrices.paymentNotifications.rules.update', ['id' => '__ID__']) }}"
     data-destroy-url-template="{{ route('admin.settingPrices.paymentNotifications.rules.destroy', ['id' => '__ID__']) }}"
     data-toggle-url-template="{{ route('admin.settingPrices.paymentNotifications.rules.toggle', ['id' => '__ID__']) }}"
     data-preview-url="{{ route('admin.settingPrices.paymentNotifications.preview') }}"
     data-test-send-url="{{ route('admin.settingPrices.paymentNotifications.testSend') }}"
     data-test-email="{{ e($testEmailDefault) }}">

    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
        <div class="min-w-0">
            <h5 class="mb-1">Уведомления об оплате</h5>
            <div class="text-muted small">
                Правила email-рассылки:<br>
                Абонемент не оплачен.<br>
                Отправка в 10:00 (Europe/Moscow).
            </div>
        </div>
        <button type="button" class="btn btn-primary" id="pn-rule-create-btn">Добавить правило</button>
    </div>

    <div id="pn-rules-loading" class="text-muted py-4">Загрузка правил…</div>
    <div id="pn-rules-empty" class="text-muted py-4 d-none">Правил пока нет. Создайте первое правило уведомления.</div>
    <div id="pn-rules-error" class="alert alert-danger d-none" role="alert"></div>

    <div class="table-responsive d-none" id="pn-rules-table-wrap">
        <table class="table table-bordered align-middle mb-0" id="pn-rules-table">
            <thead>
            <tr>
                <th style="width:1%">Вкл</th>
                <th>Название</th>
                <th>Типы абонементов</th>
                <th>Триггер</th>
                <th>Месяц</th>
                <th style="width:1%">Действия</th>
            </tr>
            </thead>
            <tbody id="pn-rules-tbody"></tbody>
        </table>
    </div>
</div>

{{-- Модалка правила (стандартная ширина Bootstrap) --}}
<div class="modal fade" id="pnRuleModal" tabindex="-1" aria-labelledby="pnRuleModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="pnRuleModalLabel">Правило уведомления</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
            </div>
            <div class="modal-body">
                <form id="pn-rule-form" novalidate>
                    <input type="hidden" id="pn-rule-id" value="">

                    <div class="mb-3">
                        <label class="form-label" for="pn-rule-name">Название</label>
                        <input type="text" class="form-control" id="pn-rule-name" name="name" maxlength="160" required>
                        <div class="invalid-feedback d-block pn-field-error" data-field="name" style="display:none;"></div>
                    </div>

                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" id="pn-rule-enabled" name="is_enabled" checked>
                        <label class="form-check-label" for="pn-rule-enabled">Правило включено</label>
                    </div>

                    <div class="mb-3">
                        <div class="form-label">Типы абонементов</div>
                        <div class="d-flex flex-wrap gap-3">
                            <div class="form-check">
                                <input class="form-check-input pn-schedule-type" type="checkbox" value="fixed" id="pn-type-fixed">
                                <label class="form-check-label" for="pn-type-fixed">Фиксированный</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input pn-schedule-type" type="checkbox" value="flexible" id="pn-type-flexible">
                                <label class="form-check-label" for="pn-type-flexible">Гибкий</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input pn-schedule-type" type="checkbox" value="postpay" id="pn-type-postpay">
                                <label class="form-check-label" for="pn-type-postpay">Постоплата</label>
                            </div>
                        </div>
                        <div class="invalid-feedback d-block pn-field-error" data-field="schedule_types" style="display:none;"></div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="pn-rule-trigger-type">Триггер</label>
                        <select class="form-select" id="pn-rule-trigger-type" name="trigger_type">
                            <option value="day_of_month">День месяца</option>
                            <option value="days_after_overdue">Дней после начала просрочки</option>
                        </select>
                        <div class="invalid-feedback d-block pn-field-error" data-field="trigger_type" style="display:none;"></div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="pn-rule-trigger-value" id="pn-rule-trigger-value-label">День месяца (1–31)</label>
                        <input type="number" class="form-control" id="pn-rule-trigger-value" name="trigger_value" min="1" max="90" value="5" required>
                        <div class="form-text" id="pn-rule-trigger-hint">Например, 5 — каждого 5-го числа.</div>
                        <div class="invalid-feedback d-block pn-field-error" data-field="trigger_value" style="display:none;"></div>
                    </div>

                    <div class="mb-3" id="pn-billing-offset-wrap">
                        <label class="form-label" for="pn-rule-billing-offset">Месяц начисления</label>
                        <select class="form-select" id="pn-rule-billing-offset" name="billing_month_offset">
                            <option value="0">Текущий календарный месяц</option>
                            <option value="-1">Предыдущий календарный месяц</option>
                        </select>
                        <div class="form-text">Для предоплаты обычно «текущий», для постоплаты на 1-е/6-е — «предыдущий».</div>
                        <div class="invalid-feedback d-block pn-field-error" data-field="billing_month_offset" style="display:none;"></div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="pn-rule-subject">Тема письма</label>
                        <input type="text" class="form-control" id="pn-rule-subject" name="subject_template" maxlength="255" required
                               placeholder="Оплата за @{{month_year}}">
                        <div class="invalid-feedback d-block pn-field-error" data-field="subject_template" style="display:none;"></div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="pn-rule-body">Текст письма (HTML)</label>
                        <textarea class="form-control" id="pn-rule-body" name="body_html_template" rows="8" required
                                  placeholder="<p>Здравствуйте!</p><p>Напоминаем об оплате @{{amount}} ₽ за @{{month_year}} (@{{team}}).</p>"></textarea>
                        <div class="invalid-feedback d-block pn-field-error" data-field="body_html_template" style="display:none;"></div>
                    </div>

                    <div class="pn-template-vars-help mb-3">
                        <button type="button"
                                class="btn btn-link btn-sm px-0 text-decoration-none"
                                id="pnTemplateVarsToggle"
                                aria-expanded="false"
                                aria-controls="pnTemplateVarsPanel">
                            Переменные шаблона
                        </button>
                        <div id="pnTemplateVarsPanel"
                             class="pn-template-vars-panel mt-2"
                             hidden
                             tabindex="0"
                             aria-label="Переменные шаблона">
                            <div class="border rounded bg-light p-2 small text-muted" id="pn-variables-list">
                                @foreach(\App\Services\PaymentNotifications\PaymentNotificationTemplateRenderer::availableVariables() as $var)
                                    <div><code>{{ '{'.'{'.$var['key'].'}'.'}' }}</code> — {{ $var['label'] }}</div>
                                @endforeach
                            </div>
                        </div>
                    </div>

                    <div class="border rounded p-2 bg-light mb-2">
                        <div class="fw-semibold small mb-2">Превью / тест</div>
                        <div class="mb-2">
                            <label class="form-label small mb-1" for="pn-test-email">Email для теста</label>
                            <input type="email" class="form-control form-control-sm" id="pn-test-email" value="{{ e($testEmailDefault) }}">
                            <div class="invalid-feedback d-block pn-field-error" data-field="to_email" style="display:none;"></div>
                        </div>
                        <div class="d-flex flex-wrap gap-2">
                            <button type="button" class="btn btn-outline-secondary btn-sm" id="pn-preview-btn">Превью</button>
                            <button type="button" class="btn btn-outline-primary btn-sm" id="pn-test-send-btn">Тестовая отправка</button>
                        </div>
                        <div id="pn-preview-box" class="mt-2 d-none">
                            <div class="small text-muted mb-1">Тема: <span id="pn-preview-subject"></span></div>
                            <div class="small text-muted mb-1 d-none" id="pn-preview-demo-note">
                                Демо-данные: текущий месяц (Europe/Moscow), без выбранного начисления.
                                Поле «Месяц начисления» в правиле на превью не влияет — оно используется при реальной рассылке.
                            </div>
                            <iframe id="pn-preview-frame"
                                    title="Превью письма"
                                    class="w-100 border rounded bg-white"
                                    style="min-height: 280px; height: 320px;"
                                    sandbox=""></iframe>
                        </div>
                        <div id="pn-test-result" class="small mt-2 d-none"></div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Отмена</button>
                <button type="button" class="btn btn-primary" id="pn-rule-save-btn">Сохранить</button>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script type="text/javascript">
(function () {
    var root = document.getElementById('payment-notifications-root');
    if (!root) return;

    var csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    var urls = {
        rules: root.dataset.rulesUrl,
        store: root.dataset.storeUrl,
        updateTpl: root.dataset.updateUrlTemplate,
        destroyTpl: root.dataset.destroyUrlTemplate,
        toggleTpl: root.dataset.toggleUrlTemplate,
        preview: root.dataset.previewUrl,
        testSend: root.dataset.testSendUrl
    };

    var scheduleLabels = { fixed: 'Фиксированный', flexible: 'Гибкий', postpay: 'Постоплата' };
    var triggerLabels = {
        day_of_month: 'День месяца',
        days_after_overdue: 'Дней после начала просрочки'
    };
    var rulesCache = [];
    var modalEl = document.getElementById('pnRuleModal');
    var modal = modalEl ? new bootstrap.Modal(modalEl) : null;

    function urlWithId(tpl, id) {
        return String(tpl || '').replace('__ID__', String(id));
    }

    function clearFieldErrors() {
        root.ownerDocument.querySelectorAll('.pn-field-error').forEach(function (el) {
            el.style.display = 'none';
            el.textContent = '';
        });
    }

    function showFieldErrors(errors) {
        clearFieldErrors();
        if (!errors || typeof errors !== 'object') return;
        Object.keys(errors).forEach(function (field) {
            var key = field.indexOf('.') >= 0 ? field.split('.')[0] : field;
            var box = document.querySelector('.pn-field-error[data-field="' + key + '"]');
            if (!box) return;
            var msgs = errors[field];
            box.textContent = Array.isArray(msgs) ? msgs.join(' ') : String(msgs);
            box.style.display = 'block';
        });
    }

    function api(method, url, body) {
        var opts = {
            method: method,
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrf
            },
            credentials: 'same-origin'
        };
        if (body !== undefined) {
            opts.headers['Content-Type'] = 'application/json';
            opts.body = JSON.stringify(body);
        }
        return fetch(url, opts).then(async function (res) {
            var data = null;
            try { data = await res.json(); } catch (e) { data = null; }
            return { ok: res.ok, status: res.status, data: data };
        });
    }

    function selectedScheduleTypes() {
        return Array.prototype.slice.call(document.querySelectorAll('.pn-schedule-type:checked'))
            .map(function (el) { return el.value; });
    }

    function setScheduleTypes(types) {
        var set = {};
        (types || []).forEach(function (t) { set[t] = true; });
        document.querySelectorAll('.pn-schedule-type').forEach(function (el) {
            el.checked = !!set[el.value];
        });
    }

    function syncTriggerUi() {
        var type = document.getElementById('pn-rule-trigger-type').value;
        var label = document.getElementById('pn-rule-trigger-value-label');
        var hint = document.getElementById('pn-rule-trigger-hint');
        var offsetWrap = document.getElementById('pn-billing-offset-wrap');
        if (type === 'days_after_overdue') {
            label.textContent = 'Дней после начала просрочки (1–90)';
            hint.textContent = 'Просрочка начинается с 1-го числа следующего месяца после месяца абонемента. Пример: абонемент за сентябрь, 3-й день = 3 октября.';
            offsetWrap.classList.add('d-none');
        } else {
            label.textContent = 'День месяца (1–31)';
            hint.textContent = 'Например, 5 — каждого 5-го числа. В коротких месяцах дни 29–31 не срабатывают.';
            offsetWrap.classList.remove('d-none');
        }
    }

    function triggerSummary(rule) {
        if (rule.trigger_type === 'days_after_overdue') {
            return 'Через ' + rule.trigger_value + ' дн. просрочки';
        }
        return 'Каждого ' + rule.trigger_value + '-го числа';
    }

    function monthSummary(rule) {
        if (rule.trigger_type === 'days_after_overdue') {
            return 'Авто (месяц просрочки)';
        }
        return Number(rule.billing_month_offset) === -1 ? 'Предыдущий' : 'Текущий';
    }

    function typesSummary(types) {
        return (types || []).map(function (t) { return scheduleLabels[t] || t; }).join(', ') || '—';
    }

    function renderRules(rules) {
        rulesCache = rules || [];
        var loading = document.getElementById('pn-rules-loading');
        var empty = document.getElementById('pn-rules-empty');
        var wrap = document.getElementById('pn-rules-table-wrap');
        var tbody = document.getElementById('pn-rules-tbody');
        var err = document.getElementById('pn-rules-error');
        err.classList.add('d-none');
        loading.classList.add('d-none');

        if (!rulesCache.length) {
            wrap.classList.add('d-none');
            empty.classList.remove('d-none');
            tbody.innerHTML = '';
            return;
        }

        empty.classList.add('d-none');
        wrap.classList.remove('d-none');
        tbody.innerHTML = rulesCache.map(function (rule) {
            return '<tr data-id="' + rule.id + '">' +
                '<td><div class="form-check form-switch m-0"><input class="form-check-input pn-toggle" type="checkbox" ' +
                (rule.is_enabled ? 'checked' : '') + ' data-id="' + rule.id + '"></div></td>' +
                '<td>' + escapeHtml(rule.name) + '</td>' +
                '<td>' + escapeHtml(typesSummary(rule.schedule_types)) + '</td>' +
                '<td>' + escapeHtml(triggerSummary(rule)) + '</td>' +
                '<td>' + escapeHtml(monthSummary(rule)) + '</td>' +
                '<td class="text-nowrap">' +
                '<button type="button" class="btn btn-sm btn-outline-primary me-1 pn-edit" data-id="' + rule.id + '">Изменить</button>' +
                '<button type="button" class="btn btn-sm btn-outline-danger pn-delete" data-id="' + rule.id + '">Удалить</button>' +
                '</td></tr>';
        }).join('');
    }

    function escapeHtml(str) {
        return String(str == null ? '' : str)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
    }

    function loadRules() {
        document.getElementById('pn-rules-loading').classList.remove('d-none');
        api('GET', urls.rules).then(function (res) {
            if (!res.ok) {
                document.getElementById('pn-rules-loading').classList.add('d-none');
                var err = document.getElementById('pn-rules-error');
                err.textContent = (res.data && res.data.message) ? res.data.message : 'Не удалось загрузить правила.';
                err.classList.remove('d-none');
                return;
            }
            if (res.data.schedule_type_labels) scheduleLabels = res.data.schedule_type_labels;
            if (res.data.trigger_type_labels) triggerLabels = res.data.trigger_type_labels;
            renderRules(res.data.rules || []);
        }).catch(function () {
            document.getElementById('pn-rules-loading').classList.add('d-none');
            var err = document.getElementById('pn-rules-error');
            err.textContent = 'Не удалось загрузить правила.';
            err.classList.remove('d-none');
        });
    }

    function openCreate() {
        clearFieldErrors();
        document.getElementById('pnRuleModalLabel').textContent = 'Новое правило';
        document.getElementById('pn-rule-id').value = '';
        document.getElementById('pn-rule-name').value = '';
        document.getElementById('pn-rule-enabled').checked = true;
        setScheduleTypes(['fixed', 'flexible']);
        document.getElementById('pn-rule-trigger-type').value = 'day_of_month';
        document.getElementById('pn-rule-trigger-value').value = '5';
        document.getElementById('pn-rule-billing-offset').value = '0';
        document.getElementById('pn-rule-subject').value = 'Оплата за @{{month_year}}';
        document.getElementById('pn-rule-body').value = '<p>Здравствуйте, @{{student_name}}!</p><p>Напоминаем об оплате <strong>@{{amount}} ₽</strong> за @{{month_year}} (группа @{{team}}).</p>';
        document.getElementById('pn-preview-box').classList.add('d-none');
        document.getElementById('pn-test-result').classList.add('d-none');
        syncTriggerUi();
        modal && modal.show();
    }

    function openEdit(id) {
        var rule = rulesCache.find(function (r) { return Number(r.id) === Number(id); });
        if (!rule) return;
        clearFieldErrors();
        document.getElementById('pnRuleModalLabel').textContent = 'Изменить правило';
        document.getElementById('pn-rule-id').value = String(rule.id);
        document.getElementById('pn-rule-name').value = rule.name || '';
        document.getElementById('pn-rule-enabled').checked = !!rule.is_enabled;
        setScheduleTypes(rule.schedule_types || []);
        document.getElementById('pn-rule-trigger-type').value = rule.trigger_type || 'day_of_month';
        document.getElementById('pn-rule-trigger-value').value = rule.trigger_value || 1;
        document.getElementById('pn-rule-billing-offset').value = String(rule.billing_month_offset == null ? 0 : rule.billing_month_offset);
        document.getElementById('pn-rule-subject').value = rule.subject_template || '';
        document.getElementById('pn-rule-body').value = rule.body_html_template || '';
        document.getElementById('pn-preview-box').classList.add('d-none');
        document.getElementById('pn-test-result').classList.add('d-none');
        syncTriggerUi();
        modal && modal.show();
    }

    function collectPayload() {
        return {
            name: document.getElementById('pn-rule-name').value,
            is_enabled: document.getElementById('pn-rule-enabled').checked,
            trigger_type: document.getElementById('pn-rule-trigger-type').value,
            trigger_value: Number(document.getElementById('pn-rule-trigger-value').value || 0),
            billing_month_offset: Number(document.getElementById('pn-rule-billing-offset').value || 0),
            schedule_types: selectedScheduleTypes(),
            subject_template: document.getElementById('pn-rule-subject').value,
            body_html_template: document.getElementById('pn-rule-body').value
        };
    }

    document.getElementById('pn-rule-create-btn').addEventListener('click', openCreate);
    document.getElementById('pn-rule-trigger-type').addEventListener('change', syncTriggerUi);

    (function initTemplateVarsToggle() {
        var toggle = document.getElementById('pnTemplateVarsToggle');
        var panel = document.getElementById('pnTemplateVarsPanel');
        if (!toggle || !panel) return;

        var defaultLabel = 'Переменные шаблона';
        toggle.addEventListener('click', function (e) {
            e.preventDefault();
            var willShow = panel.hasAttribute('hidden');
            if (willShow) {
                panel.removeAttribute('hidden');
                toggle.setAttribute('aria-expanded', 'true');
                toggle.textContent = 'Скрыть: переменные шаблона';
            } else {
                panel.setAttribute('hidden', 'hidden');
                toggle.setAttribute('aria-expanded', 'false');
                toggle.textContent = defaultLabel;
            }
        });

        if (modalEl) {
            modalEl.addEventListener('hidden.bs.modal', function () {
                panel.setAttribute('hidden', 'hidden');
                toggle.setAttribute('aria-expanded', 'false');
                toggle.textContent = defaultLabel;
            });
        }
    })();

    document.getElementById('pn-rule-save-btn').addEventListener('click', function () {
        clearFieldErrors();
        var id = document.getElementById('pn-rule-id').value;
        var payload = collectPayload();
        var method = id ? 'PUT' : 'POST';
        var url = id ? urlWithId(urls.updateTpl, id) : urls.store;
        api(method, url, payload).then(function (res) {
            if (!res.ok) {
                if (res.status === 422 && res.data && res.data.errors) {
                    showFieldErrors(res.data.errors);
                    return;
                }
                alert((res.data && res.data.message) ? res.data.message : 'Не удалось сохранить правило.');
                return;
            }
            modal && modal.hide();
            loadRules();
        });
    });

    document.getElementById('pn-preview-btn').addEventListener('click', function () {
        clearFieldErrors();
        api('POST', urls.preview, {
            subject_template: document.getElementById('pn-rule-subject').value,
            body_html_template: document.getElementById('pn-rule-body').value
        }).then(function (res) {
            if (!res.ok) {
                if (res.status === 422 && res.data && res.data.errors) {
                    showFieldErrors(res.data.errors);
                    return;
                }
                alert('Не удалось построить превью.');
                return;
            }
            document.getElementById('pn-preview-subject').textContent = res.data.subject || '';
            var demoNote = document.getElementById('pn-preview-demo-note');
            if (res.data.is_demo) {
                demoNote.classList.remove('d-none');
            } else {
                demoNote.classList.add('d-none');
            }
            var frame = document.getElementById('pn-preview-frame');
            frame.srcdoc = res.data.email_html || res.data.body_html || '';
            document.getElementById('pn-preview-box').classList.remove('d-none');
        });
    });

    document.getElementById('pn-test-send-btn').addEventListener('click', function () {
        clearFieldErrors();
        var result = document.getElementById('pn-test-result');
        result.classList.add('d-none');
        api('POST', urls.testSend, {
            to_email: document.getElementById('pn-test-email').value,
            subject_template: document.getElementById('pn-rule-subject').value,
            body_html_template: document.getElementById('pn-rule-body').value
        }).then(function (res) {
            if (!res.ok) {
                if (res.status === 422 && res.data && res.data.errors) {
                    showFieldErrors(res.data.errors);
                    return;
                }
                result.className = 'small mt-2 text-danger';
                result.textContent = (res.data && res.data.message) ? res.data.message : 'Ошибка тестовой отправки.';
                result.classList.remove('d-none');
                return;
            }
            result.className = 'small mt-2 text-success';
            result.textContent = res.data.message || 'Отправлено.';
            result.classList.remove('d-none');
        });
    });

    document.getElementById('pn-rules-tbody').addEventListener('click', function (e) {
        var editBtn = e.target.closest('.pn-edit');
        if (editBtn) {
            openEdit(editBtn.getAttribute('data-id'));
            return;
        }
        var delBtn = e.target.closest('.pn-delete');
        if (delBtn) {
            var id = delBtn.getAttribute('data-id');
            if (!confirm('Удалить правило?')) return;
            api('DELETE', urlWithId(urls.destroyTpl, id)).then(function (res) {
                if (!res.ok) {
                    alert('Не удалось удалить правило.');
                    return;
                }
                loadRules();
            });
        }
    });

    document.getElementById('pn-rules-tbody').addEventListener('change', function (e) {
        var toggle = e.target.closest('.pn-toggle');
        if (!toggle) return;
        var id = toggle.getAttribute('data-id');
        api('POST', urlWithId(urls.toggleTpl, id), { is_enabled: toggle.checked }).then(function (res) {
            if (!res.ok) {
                toggle.checked = !toggle.checked;
                alert('Не удалось изменить статус правила.');
                return;
            }
            if (res.data && res.data.rule) {
                var idx = rulesCache.findIndex(function (r) { return Number(r.id) === Number(id); });
                if (idx >= 0) rulesCache[idx] = res.data.rule;
            }
        });
    });

    syncTriggerUi();
    loadRules();
})();
</script>
@endpush
