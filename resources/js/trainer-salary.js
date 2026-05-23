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
        return String(Math.round(num)).replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
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

    function clearRowErrors(tr) {
        if (!tr) {
            return;
        }
        tr.querySelectorAll('.trainer-salary-input.is-invalid').forEach(function (el) {
            el.classList.remove('is-invalid');
        });
        tr.querySelectorAll('[data-error-for]').forEach(function (el) {
            el.textContent = '';
            el.classList.add('d-none');
        });
    }

    function showRowErrors(tr, errors) {
        if (!tr || !errors) {
            return;
        }
        Object.keys(errors).forEach(function (field) {
            var messages = errors[field];
            if (!messages || !messages.length) {
                return;
            }
            var input = tr.querySelector('[data-field="' + field + '"]');
            var errEl = tr.querySelector('[data-error-for="' + field + '"]');
            if (input) {
                input.classList.add('is-invalid');
            }
            if (errEl) {
                errEl.textContent = messages[0];
                errEl.classList.remove('d-none');
            }
        });
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
                    tableHost.innerHTML = data.table_html;
                    bindTableEvents();
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

    function saveDraft(trainerId, field, value) {
        if (!canManage) {
            return;
        }

        var period = parseMonthValue();
        if (!period) {
            return;
        }

        var key = String(trainerId);
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
                    var tr = tableHost.querySelector('tr[data-trainer-id="' + trainerId + '"]');
                    clearRowErrors(tr);

                    if (!result.ok) {
                        if (result.status === 422 && result.body && result.body.errors) {
                            showRowErrors(tr, result.body.errors);
                        }
                        return;
                    }

                    if (result.body && result.body.row) {
                        updateRowFromPayload(result.body.row);
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

    function bindTableEvents() {
        if (!tableHost || !canManage) {
            return;
        }

        tableHost.querySelectorAll('.trainer-salary-input').forEach(function (input) {
            input.addEventListener('change', function () {
                var tr = input.closest('tr');
                if (!tr) {
                    return;
                }
                var trainerId = tr.getAttribute('data-trainer-id');
                var field = input.getAttribute('data-field');
                if (!trainerId || !field) {
                    return;
                }
                saveDraft(trainerId, field, input.value);
            });
        });

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
})();
