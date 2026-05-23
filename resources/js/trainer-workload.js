(function () {
    var root = document.getElementById('trainer-workload-app');
    if (!root) {
        return;
    }

    var dataUrl = root.dataset.dataUrl || '';
    var dateFromEl = document.getElementById('trainer-workload-date-from');
    var dateToEl = document.getElementById('trainer-workload-date-to');
    var showGroupsEl = document.getElementById('trainer-workload-show-groups');
    var tableHost = document.getElementById('trainer-workload-table-host');
    var errorFromEl = document.getElementById('trainer-workload-error-date-from');
    var errorToEl = document.getElementById('trainer-workload-error-date-to');
    var monthLinks = root.querySelectorAll('[data-trainer-workload-month]');

    var debounceTimer = null;
    var abortController = null;
    var requestSeq = 0;

    function showGroupsValue() {
        return showGroupsEl ? showGroupsEl.checked : false;
    }

    function buildQueryParams() {
        var params = new URLSearchParams();
        if (dateFromEl && dateFromEl.value) {
            params.set('date_from', dateFromEl.value);
        }
        if (dateToEl && dateToEl.value) {
            params.set('date_to', dateToEl.value);
        }
        params.set('show_groups', showGroupsValue() ? '1' : '0');
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

    function clearErrors() {
        [dateFromEl, dateToEl].forEach(function (el) {
            if (!el) {
                return;
            }
            el.classList.remove('is-invalid');
        });
        if (errorFromEl) {
            errorFromEl.textContent = '';
            errorFromEl.classList.add('d-none');
        }
        if (errorToEl) {
            errorToEl.textContent = '';
            errorToEl.classList.add('d-none');
        }
    }

    function syncActiveMonthLinks() {
        var from = dateFromEl ? dateFromEl.value : '';
        var to = dateToEl ? dateToEl.value : '';

        monthLinks.forEach(function (link) {
            var isActive = link.dataset.dateFrom === from && link.dataset.dateTo === to;
            link.classList.toggle('is-active', isActive);
            if (isActive) {
                link.setAttribute('aria-current', 'true');
            } else {
                link.removeAttribute('aria-current');
            }
        });
    }

    function showFieldErrors(errors) {
        if (errors.date_from && errors.date_from.length && dateFromEl && errorFromEl) {
            dateFromEl.classList.add('is-invalid');
            errorFromEl.textContent = errors.date_from[0];
            errorFromEl.classList.remove('d-none');
        }
        if (errors.date_to && errors.date_to.length && dateToEl && errorToEl) {
            dateToEl.classList.add('is-invalid');
            errorToEl.textContent = errors.date_to[0];
            errorToEl.classList.remove('d-none');
        }
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
                        clearErrors();
                        showFieldErrors(result.body.errors);
                    }
                    return;
                }

                clearErrors();

                var data = result.body || {};
                if (typeof data.table_html === 'string') {
                    tableHost.innerHTML = data.table_html;
                }
                if (dateFromEl && data.date_from) {
                    dateFromEl.value = data.date_from;
                }
                if (dateToEl && data.date_to) {
                    dateToEl.value = data.date_to;
                }
                if (showGroupsEl && typeof data.show_groups === 'boolean') {
                    showGroupsEl.checked = data.show_groups;
                }
                syncActiveMonthLinks();
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

    if (dateFromEl) {
        dateFromEl.addEventListener('change', function () {
            syncActiveMonthLinks();
            scheduleFetch(300);
        });
    }

    if (dateToEl) {
        dateToEl.addEventListener('change', function () {
            syncActiveMonthLinks();
            scheduleFetch(300);
        });
    }

    if (showGroupsEl) {
        showGroupsEl.addEventListener('change', function () {
            scheduleFetch(0);
        });
    }

    monthLinks.forEach(function (link) {
        link.addEventListener('click', function (event) {
            event.preventDefault();
            if (!dateFromEl || !dateToEl) {
                return;
            }
            dateFromEl.value = link.dataset.dateFrom || '';
            dateToEl.value = link.dataset.dateTo || '';
            syncActiveMonthLinks();
            scheduleFetch(0);
        });
    });

    syncActiveMonthLinks();
})();
