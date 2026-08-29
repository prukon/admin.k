(function () {
    var root = document.getElementById('trainer-salary-app');
    if (!root) {
        return;
    }

    var meta = document.querySelector('meta[name="csrf-token"]');
    var csrfToken = meta ? meta.getAttribute('content') : '';

    var dataUrl = root.dataset.dataUrl || '';
    var draftUrlTemplate = root.dataset.draftUrlTemplate || '';
    var formOneUrlTemplate = root.dataset.formOneUrlTemplate || '';
    var formAllUrl = root.dataset.formAllUrl || '';
    var canManage = root.dataset.canManage === '1';

    var monthEl = document.getElementById('trainer-salary-month');
    var tableHost = document.getElementById('trainer-salary-table-host');
    var monthSettingsHost = document.getElementById('trainer-salary-kansas-month-settings-host');
    var monthSettingsBtn = document.getElementById('trainer-salary-kansas-month-settings-btn');
    var flashEl = document.getElementById('trainer-salary-flash');
    var formAllBtn = document.getElementById('trainer-salary-form-all-btn');
    var errorMonthEl = document.getElementById('trainer-salary-error-month');

    var debounceTimer = null;
    var abortController = null;
    var requestSeq = 0;
    var saveTimersByTrainer = {};

    function parseMonthValue() {
        if (!monthEl || !monthEl.value) {
            return null;
        }
        var parts = monthEl.value.split('-');
        if (parts.length !== 2) {
            return null;
        }
        return {
            year: parseInt(parts[0], 10),
            month: parseInt(parts[1], 10),
        };
    }

    function buildQueryParams() {
        var period = parseMonthValue();
        var params = new URLSearchParams();
        if (period) {
            params.set('year', String(period.year));
            params.set('month', String(period.month));
        }
        return params;
    }

    function syncUrl(params) {
        var qs = params.toString();
        var next = window.location.pathname + (qs ? '?' + qs : '');
        window.history.replaceState(null, '', next);
    }

    function setLoading(isLoading) {
        if (!tableHost) {
            return;
        }
        tableHost.classList.toggle('is-loading', isLoading);
    }

    function showFlash(message, type) {
        if (!flashEl || !message) {
            return;
        }
        flashEl.textContent = message;
        flashEl.className = 'alert alert-' + (type || 'success') + ' mb-3';
        flashEl.classList.remove('d-none');
    }

    function hideFlash() {
        if (flashEl) {
            flashEl.classList.add('d-none');
        }
    }

    function clearMonthError() {
        if (monthEl) {
            monthEl.classList.remove('is-invalid');
        }
        if (errorMonthEl) {
            errorMonthEl.textContent = '';
            errorMonthEl.classList.add('d-none');
        }
    }

    function urlFromTemplate(template, trainerId) {
        return template.replace('__ID__', String(trainerId));
    }

    function updateRowFromPayload(row) {
        if (!tableHost || !row || !row.trainer_profile_id) {
            return;
        }

        var tr = tableHost.querySelector('tr[data-trainer-id="' + row.trainer_profile_id + '"]');
        if (!tr) {
            return;
        }

        var trainingsCount = tr.querySelector('.trainer-salary-trainings-count');
        if (trainingsCount) {
            trainingsCount.textContent = String(row.trainings_count ?? 0);
        }

        var trainingsAmount = tr.querySelector('.trainer-salary-trainings-amount');
        if (trainingsAmount) {
            trainingsAmount.textContent = formatMoneyRublesDisplay(row.trainings_amount);
        }

        var totalEl = tr.querySelector('.trainer-salary-total');
        if (totalEl) {
            totalEl.textContent = formatMoneyRublesDisplay(row.total);
        }

        if (row.paid_months != null) {
            var paidMonths = tr.querySelector('.trainer-salary-paid-months');
            if (paidMonths) {
                paidMonths.textContent = formatMoneyRublesDisplay(row.paid_months);
            }
        }
        if (row.paid_packages != null) {
            var paidPackages = tr.querySelector('.trainer-salary-paid-packages');
            if (paidPackages) {
                paidPackages.textContent = formatMoneyRublesDisplay(row.paid_packages);
            }
        }
        if (row.sales_base != null) {
            var salesBase = tr.querySelector('.trainer-salary-sales-base');
            if (salesBase) {
                salesBase.textContent = formatMoneyRublesDisplay(row.sales_base);
            }
        }
        if (row.commission != null) {
            var commission = tr.querySelector('.trainer-salary-commission');
            if (commission) {
                commission.textContent = formatMoneyRublesDisplay(row.commission);
            }
        }

        if (row.latest_snapshot) {
            var hint = tr.querySelector('.trainer-salary-snapshot-hint');
            if (!hint) {
                var nameCell = tr.querySelector('.trainer-salary-trainer-name');
                if (nameCell) {
                    hint = document.createElement('div');
                    hint.className = 'trainer-salary-snapshot-hint small text-muted';
                    nameCell.appendChild(hint);
                }
            }
            if (hint) {
                var formedAt = row.latest_snapshot.formed_at
                    ? formatDateTime(row.latest_snapshot.formed_at)
                    : '';
                var byName = row.latest_snapshot.formed_by_name || '';
                hint.textContent = 'Слепок v' + row.latest_snapshot.version
                    + (formedAt ? ' · ' + formedAt : '')
                    + (byName ? ' · ' + byName : '');
            }
        }
    }

    function formatMoneyRublesDisplay(value) {
        var num = parseFloat(value);
        if (isNaN(num)) {
            num = 0;
        }
        var cents = Math.round(num * 100);
        var neg = cents < 0;
        cents = Math.abs(cents);
        var rub = Math.floor(cents / 100);
        var kop = cents % 100;
        var rubStr = String(rub).replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
        var body = kop === 0 ? rubStr : rubStr + ',' + (kop < 10 ? '0' + kop : String(kop));
        return neg ? '-' + body : body;
    }

    function formatDateTime(iso) {
        try {
            var d = new Date(iso);
            if (isNaN(d.getTime())) {
                return '';
            }
            var pad = function (n) { return n < 10 ? '0' + n : String(n); };
            return pad(d.getDate()) + '.' + pad(d.getMonth() + 1) + '.' + d.getFullYear()
                + ' ' + pad(d.getHours()) + ':' + pad(d.getMinutes());
        } catch (e) {
            return '';
        }
    }

    function clearFieldErrors(root) {
        if (!root) {
            return;
        }
        root.querySelectorAll('.trainer-salary-input.is-invalid').forEach(function (el) {
            el.classList.remove('is-invalid');
        });
        root.querySelectorAll('[data-error-for]').forEach(function (el) {
            el.textContent = '';
            el.classList.add('d-none');
        });
    }

    function clearRowErrors(tr) {
        clearFieldErrors(tr);
    }

    function clearHostFieldErrors() {
        if (tableHost) {
            clearFieldErrors(tableHost.querySelector('.trainer-salary-kansas-x'));
        }
        clearFieldErrors(monthSettingsHost);
    }

    function showErrorsIn(root, errors) {
        if (!root || !errors) {
            return;
        }
        Object.keys(errors).forEach(function (field) {
            var messages = errors[field];
            if (!messages || !messages.length) {
                return;
            }
            var input = root.querySelector('[data-field="' + field + '"]');
            var errEl = root.querySelector('[data-error-for="' + field + '"]');
            if (input) {
                input.classList.add('is-invalid');
            }
            if (errEl) {
                errEl.textContent = messages[0];
                errEl.classList.remove('d-none');
            }
        });
    }

    function showRowErrors(tr, errors) {
        showErrorsIn(tr, errors);
    }

    function showHostFieldErrors(errors) {
        if (tableHost) {
            showErrorsIn(tableHost.querySelector('.trainer-salary-kansas-x'), errors);
        }
        if (monthSettingsHost) {
            showErrorsIn(monthSettingsHost.querySelector('.trainer-salary-kansas-x'), errors);
        }
    }

    function fetchReport() {
        if (!dataUrl || !tableHost) {
            return;
        }

        var params = buildQueryParams();
        syncUrl(params);

        if (abortController) {
            abortController.abort();
        }
        abortController = new AbortController();

        var seq = ++requestSeq;
        setLoading(true);
        hideFlash();

        fetch(dataUrl + '?' + params.toString(), {
            method: 'GET',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
            signal: abortController.signal,
        })
            .then(function (resp) {
                return resp.json().then(function (body) {
                    return { ok: resp.ok, status: resp.status, body: body };
                });
            })
            .then(function (result) {
                if (seq !== requestSeq) {
                    return;
                }

                if (!result.ok) {
                    if (result.status === 422 && result.body && result.body.errors) {
                        clearMonthError();
                        if (result.body.errors.year && monthEl && errorMonthEl) {
                            monthEl.classList.add('is-invalid');
                            errorMonthEl.textContent = result.body.errors.year[0];
                            errorMonthEl.classList.remove('d-none');
                        }
                    }
                    return;
                }

                clearMonthError();
                var data = result.body || {};
                if (typeof data.table_html === 'string') {
                    applyTableHtml(data.table_html);
                }
                if (typeof data.month_settings_html === 'string') {
                    applyMonthSettingsHtml(data.month_settings_html);
                }
                if (monthSettingsBtn) {
                    monthSettingsBtn.classList.toggle('d-none', data.scheme_code !== 'kansas' || !canManage);
                }
            })
            .catch(function (err) {
                if (err && err.name === 'AbortError') {
                    return;
                }
                console.error(err);
            })
            .finally(function () {
                if (seq === requestSeq) {
                    setLoading(false);
                }
            });
    }

    function scheduleFetch(delayMs) {
        if (debounceTimer) {
            clearTimeout(debounceTimer);
        }
        debounceTimer = setTimeout(function () {
            debounceTimer = null;
            fetchReport();
        }, delayMs);
    }

    function applyMonthSettingsHtml(html) {
        if (!monthSettingsHost || typeof html !== 'string') {
            return false;
        }
        if (window.KidsCrmTooltip && typeof window.KidsCrmTooltip.dispose === 'function') {
            window.KidsCrmTooltip.dispose(monthSettingsHost, { scopes: ['hint'] });
        }
        monthSettingsHost.innerHTML = html;
        var titleRoot = monthSettingsHost.querySelector('[data-modal-title]');
        var titleEl = document.getElementById('trainerSalaryKansasMonthSettingsModalLabel');
        if (titleRoot && titleEl) {
            var nextTitle = titleRoot.getAttribute('data-modal-title');
            if (nextTitle) {
                titleEl.textContent = nextTitle;
            }
        }
        bindMonthSettingsEvents();
        if (window.KidsCrmTooltip && typeof window.KidsCrmTooltip.init === 'function') {
            window.KidsCrmTooltip.init(monthSettingsHost, { scopes: ['hint'] });
        }
        return true;
    }

    function applyTableHtml(html) {
        if (!tableHost || typeof html !== 'string') {
            return false;
        }
        if (window.KidsCrmTooltip && typeof window.KidsCrmTooltip.dispose === 'function') {
            window.KidsCrmTooltip.dispose(tableHost, { scopes: ['hint'] });
        }
        tableHost.innerHTML = html;
        bindTableEvents();
        if (window.KidsCrmTooltip && typeof window.KidsCrmTooltip.init === 'function') {
            window.KidsCrmTooltip.init(tableHost, { scopes: ['hint'] });
        }
        return true;
    }

    function flashSavedField(tr, field) {
        if (!tr || !field) {
            return;
        }
        var input = tr.querySelector('[data-field="' + field + '"]');
        if (!input) {
            return;
        }
        var cell = input.closest('td') || input;
        cell.classList.remove('trainer-salary-cell--saved');
        void cell.offsetWidth;
        cell.classList.add('trainer-salary-cell--saved');
        window.setTimeout(function () {
            cell.classList.remove('trainer-salary-cell--saved');
        }, 1400);
    }

    function saveDraft(trainerId, field, value, extra, errorRoot) {
        if (!canManage) {
            return;
        }

        var period = parseMonthValue();
        if (!period) {
            return;
        }

        var key = String(trainerId) + ':' + field + (extra && extra.team_id != null ? ':' + extra.team_id : '');
        if (saveTimersByTrainer[key]) {
            clearTimeout(saveTimersByTrainer[key]);
        }

        saveTimersByTrainer[key] = setTimeout(function () {
            saveTimersByTrainer[key] = null;

            var payload = {
                year: period.year,
                month: period.month,
            };
            payload[field] = value;
            if (extra) {
                Object.keys(extra).forEach(function (extraKey) {
                    payload[extraKey] = extra[extraKey];
                });
            }

            fetch(urlFromTemplate(draftUrlTemplate, trainerId), {
                method: 'PATCH',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrfToken,
                },
                credentials: 'same-origin',
                body: JSON.stringify(payload),
            })
                .then(function (resp) {
                    return resp.json().then(function (body) {
                        return { ok: resp.ok, status: resp.status, body: body };
                    });
                })
                .then(function (result) {
                    var tr = tableHost ? tableHost.querySelector('tr[data-trainer-id="' + trainerId + '"]') : null;
                    clearRowErrors(tr);
                    clearHostFieldErrors();
                    if (errorRoot) {
                        clearFieldErrors(errorRoot);
                    }

                    if (!result.ok) {
                        if (result.status === 422 && result.body && result.body.errors) {
                            showRowErrors(tr, result.body.errors);
                            showHostFieldErrors(result.body.errors);
                            if (errorRoot) {
                                showErrorsIn(errorRoot, result.body.errors);
                            }
                        }
                        return;
                    }

                    if (result.body && result.body.reload_table && applyTableHtml(result.body.table_html)) {
                        return;
                    }

                    if (result.body && result.body.row) {
                        updateRowFromPayload(result.body.row);
                    }
                    if (field === 'sales_percent') {
                        flashSavedField(tr, 'sales_percent');
                    }
                })
                .catch(function (err) {
                    console.error(err);
                });
        }, 400);
    }

    function formOne(trainerId, btn) {
        var period = parseMonthValue();
        if (!period) {
            return;
        }

        if (btn) {
            btn.disabled = true;
        }

        fetch(urlFromTemplate(formOneUrlTemplate, trainerId), {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrfToken,
            },
            credentials: 'same-origin',
            body: JSON.stringify({
                year: period.year,
                month: period.month,
            }),
        })
            .then(function (resp) {
                return resp.json().then(function (body) {
                    return { ok: resp.ok, status: resp.status, body: body };
                });
            })
            .then(function (result) {
                if (!result.ok) {
                    showFlash(result.body && result.body.message ? result.body.message : 'Не удалось сформировать слепок', 'danger');
                    return;
                }
                showFlash(result.body.message || 'Слепок сформирован', 'success');
                if (result.body.reload_table && applyTableHtml(result.body.table_html)) {
                    return;
                }
                if (result.body.row) {
                    updateRowFromPayload(result.body.row);
                }
            })
            .catch(function (err) {
                console.error(err);
                showFlash('Ошибка сети', 'danger');
            })
            .finally(function () {
                if (btn) {
                    btn.disabled = false;
                }
            });
    }

    function formAll() {
        var period = parseMonthValue();
        if (!period) {
            return;
        }

        if (!window.confirm('Сформировать слепки ЗП для всех активных тренеров за выбранный месяц?')) {
            return;
        }

        if (formAllBtn) {
            formAllBtn.disabled = true;
        }

        fetch(formAllUrl, {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrfToken,
            },
            credentials: 'same-origin',
            body: JSON.stringify({
                year: period.year,
                month: period.month,
            }),
        })
            .then(function (resp) {
                return resp.json().then(function (body) {
                    return { ok: resp.ok, status: resp.status, body: body };
                });
            })
            .then(function (result) {
                if (!result.ok) {
                    showFlash(result.body && result.body.message ? result.body.message : 'Не удалось сформировать слепки', 'danger');
                    return;
                }
                showFlash(result.body.message || 'Слепки сформированы', 'success');
                if (result.body.reload_table && applyTableHtml(result.body.table_html)) {
                    return;
                }
                if (Array.isArray(result.body.rows)) {
                    result.body.rows.forEach(updateRowFromPayload);
                } else {
                    fetchReport();
                }
            })
            .catch(function (err) {
                console.error(err);
                showFlash('Ошибка сети', 'danger');
            })
            .finally(function () {
                if (formAllBtn) {
                    formAllBtn.disabled = false;
                }
            });
    }

    function bindDraftInputs(root) {
        if (!root || !canManage) {
            return;
        }

        root.querySelectorAll('.trainer-salary-input').forEach(function (input) {
            input.addEventListener('change', function () {
                var field = input.getAttribute('data-field');
                if (!field) {
                    return;
                }

                var tr = input.closest('tr');
                var trainerId = tr ? tr.getAttribute('data-trainer-id') : null;
                if (!trainerId) {
                    trainerId = input.getAttribute('data-save-trainer-id');
                }
                if (!trainerId) {
                    var xHost = input.closest('.trainer-salary-kansas-x');
                    trainerId = xHost ? xHost.getAttribute('data-save-trainer-id') : null;
                }
                if (!trainerId) {
                    var settingsHost = input.closest('[data-save-trainer-id]');
                    trainerId = settingsHost ? settingsHost.getAttribute('data-save-trainer-id') : null;
                }
                if (!trainerId) {
                    return;
                }

                var extra = {};
                var teamHost = input.closest('[data-team-id]');
                if (teamHost && field === 'base_avg_students') {
                    extra.team_id = teamHost.getAttribute('data-team-id');
                }
                var errorRoot = input.closest('.trainer-salary-kansas-month-group')
                    || input.closest('.trainer-salary-kansas-x')
                    || tr;
                saveDraft(trainerId, field, input.value, extra, errorRoot);
            });
        });
    }

    function bindMonthSettingsEvents() {
        bindDraftInputs(monthSettingsHost);
    }

    function bindTableEvents() {
        if (!tableHost || !canManage) {
            return;
        }

        bindDraftInputs(tableHost);

        tableHost.querySelectorAll('.trainer-salary-form-one-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var trainerId = btn.getAttribute('data-trainer-id');
                if (trainerId) {
                    formOne(trainerId, btn);
                }
            });
        });
    }

    if (monthEl) {
        monthEl.addEventListener('change', function () {
            scheduleFetch(200);
        });
    }

    if (formAllBtn) {
        formAllBtn.addEventListener('click', formAll);
    }

    bindTableEvents();
    bindMonthSettingsEvents();
    if (window.KidsCrmTooltip && typeof window.KidsCrmTooltip.init === 'function' && monthSettingsHost) {
        window.KidsCrmTooltip.init(monthSettingsHost, { scopes: ['hint'] });
    }

    window.__reloadTrainerSalaryReport = fetchReport;
})();
