(function () {
    var root = document.getElementById('trainer-salary-sheets-app');
    if (!root) {
        return;
    }

    var dataUrl = root.dataset.dataUrl || '';
    var monthEl = document.getElementById('trainer-salary-sheets-month');
    var latestOnlyEl = document.getElementById('trainer-salary-sheets-latest-only');
    var tableHost = document.getElementById('trainer-salary-sheets-table-host');
    var latestHost = document.getElementById('trainer-salary-sheets-latest-host');

    var debounceTimer = null;
    var abortController = null;
    var requestSeq = 0;

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
        if (latestOnlyEl && latestOnlyEl.checked) {
            params.set('latest_only', '1');
        }
        return params;
    }

    function syncUrl(params) {
        var qs = params.toString();
        var next = window.location.pathname + (qs ? '?' + qs : '');
        window.history.replaceState(null, '', next);
    }

    function setLoading(isLoading) {
        if (tableHost) {
            tableHost.classList.toggle('is-loading', isLoading);
        }
    }

    function fetchList() {
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
                    return { ok: resp.ok, body: body };
                });
            })
            .then(function (result) {
                if (seq !== requestSeq || !result.ok) {
                    return;
                }

                var data = result.body || {};
                if (typeof data.table_html === 'string') {
                    tableHost.innerHTML = data.table_html;
                }
                if (latestHost && typeof data.latest_html === 'string') {
                    latestHost.innerHTML = data.latest_html;
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
            fetchList();
        }, delayMs);
    }

    if (monthEl) {
        monthEl.addEventListener('change', function () {
            scheduleFetch(200);
        });
    }

    if (latestOnlyEl) {
        latestOnlyEl.addEventListener('change', function () {
            scheduleFetch(0);
        });
    }
})();
