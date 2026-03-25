<h4 class="pt-3 text-start">Очереди</h4>

<div class="d-flex gap-2 mb-3">
    <button type="button" class="btn btn-primary" id="btnQueueRefresh">Обновить</button>
    @can('settings.queues.manage')
        <button type="button" class="btn btn-warning" id="btnQueueRestart">Перезапустить worker</button>
    @endcan
    <button type="button" class="btn btn-outline-secondary" id="btnQueueLogs">Показать queue.log</button>
</div>

<div class="table-responsive">
    <table class="table">
        <thead>
        <tr>
            <th>Показатель</th>
            <th>Значение</th>
        </tr>
        </thead>
        <tbody>
        <tr><td>Статус worker</td><td id="q_worker_status">—</td></tr>
        <tr><td>Последний heartbeat</td><td id="q_last_heartbeat">—</td></tr>
        <tr><td>Статус планировщика (cron → schedule:run)</td><td id="q_scheduler_status">—</td></tr>
        <tr><td>Последний тик планировщика</td><td id="q_scheduler_last_tick">—</td></tr>
        <tr><td>Просроченные отложенные выплаты T‑Bank (счётчик)</td><td id="q_overdue_payouts_count">—</td></tr>
        <tr><td>Количество задач в jobs</td><td id="q_jobs_count">—</td></tr>
        <tr><td>Количество задач в failed_jobs</td><td id="q_failed_jobs_count">—</td></tr>
        <tr><td>Возраст самой старой задачи в jobs</td><td id="q_oldest_age">—</td></tr>
        <tr><td>Время последней успешной обработки</td><td id="q_last_success">—</td></tr>
        <tr><td>Время последней неуспешной обработки</td><td id="q_last_failed">—</td></tr>
        <tr><td>Последнее обновление виджета</td><td id="q_generated_at">—</td></tr>
        </tbody>
    </table>
</div>

<div id="queueOverdueWrap" class="mt-4 d-none">
    <h5 class="mb-2">Просроченные отложенные выплаты T‑Bank (пример)</h5>
    <div class="small text-muted mb-2">
        Условие: <code>when_to_run</code> в прошлом, статус <code>INITIATED</code>, нет <code>tinkoff_payout_payment_id</code>.
        Для не‑superadmin считается только по текущему партнёру.
    </div>
    <div class="table-responsive">
        <table class="table table-sm" id="queueOverduePayoutsTable">
            <thead>
            <tr>
                <th>ID</th>
                <th>Партнёр</th>
                <th>Запланировано</th>
                <th></th>
            </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>
</div>

<h5 class="mt-4">Разбивка по очередям</h5>
<div class="table-responsive">
    <table class="table" id="queueByQueueTable">
        <thead>
        <tr>
            <th>Очередь</th>
            <th>Ожидают (jobs)</th>
            <th>Ошибки (failed_jobs)</th>
        </tr>
        </thead>
        <tbody>
        <tr><td colspan="3" class="text-muted">Нет данных</td></tr>
        </tbody>
    </table>
</div>

<h5 class="mt-4">Разбивка по типам задач</h5>
<div class="table-responsive">
    <table class="table" id="queueByGroupTable">
        <thead>
        <tr>
            <th>Тип задач</th>
            <th>Ожидают (jobs)</th>
            <th>Ошибки (failed_jobs)</th>
        </tr>
        </thead>
        <tbody>
        <tr><td colspan="3" class="text-muted">Нет данных</td></tr>
        </tbody>
    </table>
</div>

<div class="mt-4">
    <label for="queueLogsText" class="form-label">Последние строки queue.log</label>
    <textarea id="queueLogsText" class="form-control" rows="12" readonly></textarea>
</div>

@section('scripts')
    <script>
        (function () {
            const statusUrl = '{{ route('admin.setting.queues.status') }}';
            const restartUrl = '{{ route('admin.setting.queues.restart') }}';
            const logsUrl = '{{ route('admin.setting.queues.logs') }}';
            const csrf = '{{ csrf_token() }}';

            function fmtDate(value) {
                return value ? value : '—';
            }

            function fmtAge(seconds) {
                if (seconds === null || seconds === undefined) return '—';
                const sec = Number(seconds) || 0;
                const m = Math.floor(sec / 60);
                const s = sec % 60;
                return `${m} мин ${s} сек`;
            }

            function workerClass(code) {
                if (code === 'alive') return 'text-success fw-bold';
                if (code === 'stale') return 'text-warning fw-bold';
                if (code === 'dead') return 'text-danger fw-bold';
                return 'text-muted';
            }

            function renderQueueRows(data) {
                const pending = {};
                (data.queues_pending || []).forEach((row) => {
                    pending[row.queue] = Number(row.pending || 0);
                });
                const failed = {};
                (data.queues_failed || []).forEach((row) => {
                    failed[row.queue] = Number(row.failed || 0);
                });

                const queues = Array.from(new Set([...Object.keys(pending), ...Object.keys(failed)]));
                const tbody = document.querySelector('#queueByQueueTable tbody');
                tbody.innerHTML = '';

                if (!queues.length) {
                    tbody.innerHTML = '<tr><td colspan="3" class="text-muted">Нет данных</td></tr>';
                    return;
                }

                queues.forEach((name) => {
                    const tr = document.createElement('tr');
                    tr.innerHTML = `<td>${name}</td><td>${pending[name] || 0}</td><td>${failed[name] || 0}</td>`;
                    tbody.appendChild(tr);
                });
            }

            function renderGroupRows(data) {
                const tbody = document.querySelector('#queueByGroupTable tbody');
                tbody.innerHTML = '';
                const groups = data.job_groups || [];
                if (!groups.length) {
                    tbody.innerHTML = '<tr><td colspan="3" class="text-muted">Нет данных</td></tr>';
                    return;
                }

                groups.forEach((row) => {
                    const tr = document.createElement('tr');
                    tr.innerHTML = `<td>${row.label}</td><td>${Number(row.pending || 0)}</td><td>${Number(row.failed || 0)}</td>`;
                    tbody.appendChild(tr);
                });
            }

            function renderOverduePayouts(data) {
                const wrap = document.getElementById('queueOverdueWrap');
                const tbody = document.querySelector('#queueOverduePayoutsTable tbody');
                if (!wrap || !tbody) {
                    return;
                }
                const count = Number(data.overdue_scheduled_payouts_count || 0);
                const sample = data.overdue_scheduled_payouts_sample || [];
                tbody.innerHTML = '';
                if (count <= 0) {
                    wrap.classList.add('d-none');
                    return;
                }
                wrap.classList.remove('d-none');
                sample.forEach((row) => {
                    const tr = document.createElement('tr');
                    const tdId = document.createElement('td');
                    tdId.textContent = String(row.id ?? '');
                    const tdPartner = document.createElement('td');
                    tdPartner.textContent = row.partner_title ? String(row.partner_title) : ('#' + String(row.partner_id ?? ''));
                    const tdWhen = document.createElement('td');
                    tdWhen.textContent = row.when_to_run ? String(row.when_to_run) : '—';
                    const tdLink = document.createElement('td');
                    const a = document.createElement('a');
                    a.href = '/admin/tinkoff/payouts/' + encodeURIComponent(String(row.id ?? ''));
                    a.className = 'btn btn-sm btn-outline-primary';
                    a.textContent = 'Карточка';
                    tdLink.appendChild(a);
                    tr.appendChild(tdId);
                    tr.appendChild(tdPartner);
                    tr.appendChild(tdWhen);
                    tr.appendChild(tdLink);
                    tbody.appendChild(tr);
                });
            }

            async function loadStatus() {
                try {
                    const response = await fetch(statusUrl, {
                        headers: {'X-Requested-With': 'XMLHttpRequest'}
                    });
                    const json = await response.json();
                    if (!response.ok || !json.success) {
                        throw new Error((json && json.message) ? json.message : 'Ошибка загрузки статуса очередей.');
                    }

                    const data = json.data || {};
                    const ws = data.worker_status || {};
                    const statusEl = document.getElementById('q_worker_status');
                    statusEl.className = workerClass(ws.code);
                    statusEl.textContent = ws.title ? `${ws.title}${ws.seconds_since_heartbeat != null ? ` (${ws.seconds_since_heartbeat} сек назад)` : ''}` : '—';

                    document.getElementById('q_last_heartbeat').textContent = fmtDate(data.last_heartbeat_at);

                    const ss = data.scheduler_status || {};
                    const schEl = document.getElementById('q_scheduler_status');
                    schEl.className = workerClass(ss.code);
                    schEl.textContent = ss.title
                        ? `${ss.title}${ss.seconds_since_tick != null ? ` (${ss.seconds_since_tick} сек назад)` : ''}`
                        : '—';
                    document.getElementById('q_scheduler_last_tick').textContent = fmtDate(data.scheduler_last_tick_at);
                    document.getElementById('q_overdue_payouts_count').textContent = Number(data.overdue_scheduled_payouts_count || 0);
                    renderOverduePayouts(data);

                    document.getElementById('q_jobs_count').textContent = Number(data.jobs_count || 0);
                    document.getElementById('q_failed_jobs_count').textContent = Number(data.failed_jobs_count || 0);
                    document.getElementById('q_oldest_age').textContent = fmtAge(data.oldest_job_age_seconds);
                    document.getElementById('q_last_success').textContent = fmtDate(data.last_success_at);
                    document.getElementById('q_last_failed').textContent = fmtDate(data.last_failed_at);
                    document.getElementById('q_generated_at').textContent = fmtDate(data.generated_at);

                    renderQueueRows(data);
                    renderGroupRows(data);
                } catch (e) {
                    if (typeof showErrorModal === 'function') {
                        showErrorModal('Очереди', e.message || 'Ошибка загрузки статуса очередей.');
                    } else {
                        alert(e.message || 'Ошибка загрузки статуса очередей.');
                    }
                }
            }

            async function loadLogs() {
                try {
                    const response = await fetch(logsUrl, {
                        headers: {'X-Requested-With': 'XMLHttpRequest'}
                    });
                    const json = await response.json();
                    if (!response.ok || !json.success) {
                        throw new Error((json && json.message) ? json.message : 'Ошибка чтения queue.log.');
                    }

                    document.getElementById('queueLogsText').value = (json.lines || []).join('\n');
                } catch (e) {
                    if (typeof showErrorModal === 'function') {
                        showErrorModal('Логи очередей', e.message || 'Ошибка чтения queue.log.');
                    } else {
                        alert(e.message || 'Ошибка чтения queue.log.');
                    }
                }
            }

            document.getElementById('btnQueueRefresh').addEventListener('click', function () {
                loadStatus();
            });

            document.getElementById('btnQueueLogs').addEventListener('click', function () {
                loadLogs();
            });

            const restartBtn = document.getElementById('btnQueueRestart');
            if (restartBtn) {
                restartBtn.addEventListener('click', function () {
                    showConfirmDeleteModal(
                        'Перезапуск worker',
                        'Выполнить queue:restart? Текущие задачи завершатся и worker перезапустится.',
                        async function () {
                            try {
                                const response = await fetch(restartUrl, {
                                    method: 'POST',
                                    headers: {
                                        'X-CSRF-TOKEN': csrf,
                                        'X-Requested-With': 'XMLHttpRequest',
                                        'Content-Type': 'application/json'
                                    },
                                    body: JSON.stringify({})
                                });
                                const json = await response.json();
                                if (!response.ok || !json.success) {
                                    throw new Error((json && json.message) ? json.message : 'Не удалось выполнить queue:restart.');
                                }

                                if (typeof showSuccessModal === 'function') {
                                    showSuccessModal('Очереди', json.message || 'Команда queue:restart отправлена.', 1);
                                }
                                loadStatus();
                                loadLogs();
                            } catch (e) {
                                if (typeof showErrorModal === 'function') {
                                    showErrorModal('Очереди', e.message || 'Не удалось выполнить queue:restart.');
                                } else {
                                    alert(e.message || 'Не удалось выполнить queue:restart.');
                                }
                            }
                        }
                    );
                });
            }

            loadStatus();
            loadLogs();
            setInterval(loadStatus, 10000);
        })();
    </script>
@endsection
