@php
    /** @var string $mode */
    /** @var \Illuminate\Database\Eloquent\Collection|\App\Models\Partner[] $partners */
@endphp

<div class="container py-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h5 mb-0">Комиссии Т‑Банк</h1>

        @if(($mode ?? 'list') === 'list')
            <a href="{{ route('admin.setting.tbankCommissions.create') }}" class="btn btn-primary btn-sm">Добавить правило</a>
        @endif
    </div>

    @if(session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    @if(($mode ?? 'list') === 'create')
        <h2 class="h6 mb-3">Новое правило комиссии</h2>
        <form method="post" action="{{ route('admin.setting.tbankCommissions.store') }}">
            @csrf
            @include('tinkoff.commissions._form', ['rule' => null, 'partners' => $partners])
            <div class="mt-3">
                <button class="btn btn-primary">Сохранить</button>
                <a href="{{ route('admin.setting.tbankCommissions') }}" class="btn btn-link">Отмена</a>
            </div>
        </form>

    @elseif(($mode ?? 'list') === 'edit')
        @php /** @var \App\Models\TinkoffCommissionRule $rule */ @endphp
        <h2 class="h6 mb-3">Правка правила #{{ $rule->id }}</h2>

        @php
            $rulePartnerId = (int) ($rule->partner_id ?? 0);
            $auto = !empty($autoPayoutByPartnerId) ? (bool) ($autoPayoutByPartnerId[$rulePartnerId] ?? false) : false;
            $conn = !empty($tbankConnectedByPartnerId) ? (bool) ($tbankConnectedByPartnerId[$rulePartnerId] ?? false) : false;
            $ruleStats = ($autoPayoutStatsByPartnerId ?? collect())->get($rulePartnerId);
        @endphp

        <form method="post" action="{{ route('admin.setting.tbankCommissions.update', ['id' => $rule->id]) }}">
            @csrf
            @method('put')

            <div class="card mb-3">
                <div class="card-body">
                    <div class="fw-semibold">Автовыплата партнёру после успешной оплаты</div>
                    <div class="text-muted small">
                        Настройка сохранится вместе с правилами по кнопке <b>Сохранить</b>.
                    </div>

                    @if($rulePartnerId <= 0)
                        <div class="text-warning small mt-2">
                            Для “глобального” правила partner_id не задан. Сначала выбери партнёра в правиле и сохрани — тогда появится чекбокс автовыплаты.
                        </div>
                    @else
                        @if(!$conn)
                            <div class="text-warning small mt-2">
                                T‑Bank у партнёра не считается “подключённым” (проверь ключи eacq/e2c в “Платёжных системах”).
                            </div>
                        @endif

                        <div class="form-check form-switch mt-3">
                            <input class="form-check-input"
                                   type="checkbox"
                                   role="switch"
                                   id="autoPayoutEnabled"
                                   name="auto_payout_enabled"
                                   value="1"
                                   {{ $auto ? 'checked' : '' }}>
                            <label class="form-check-label" for="autoPayoutEnabled">Автовыплата включена</label>
                        </div>
                        <div class="small text-muted mt-2">
                            За 30 дн.: {{ $ruleStats['count'] ?? 0 }} автовыплат
                            @if(!empty($ruleStats['last_at']))
                                , последняя {{ $ruleStats['last_at']->format('d.m.Y H:i') }}
                            @endif
                            — <a href="{{ url('/admin/tinkoff/payouts?partner_id=' . $rulePartnerId . '&source=auto') }}" target="_blank">к выплатам (авто)</a>
                        </div>
                    @endif
                </div>
            </div>

            @include('tinkoff.commissions._form', ['rule' => $rule, 'partners' => $partners])
            <div class="mt-3">
                <button class="btn btn-primary">Сохранить</button>
                <a href="{{ route('admin.setting.tbankCommissions') }}" class="btn btn-link">Отмена</a>
            </div>
        </form>

    @else
        @php /** @var \Illuminate\Pagination\LengthAwarePaginator $rules */ @endphp

        <div class="card shadow-sm mb-4">
            <div class="card-header">Глобальные настройки выплат Т‑Банк</div>
            <div class="card-body">
                <form method="post" action="{{ route('admin.setting.tbankCommissions.payoutSettings') }}" class="row g-3 align-items-end">
                    @csrf
                    <div class="col-auto">
                        <label for="payout_auto_delay_hours" class="form-label mb-0">Задержка автовыплаты после оплаты (часы)</label>
                        <input type="number" class="form-control form-control-sm" id="payout_auto_delay_hours" name="payout_auto_delay_hours"
                               value="{{ old('payout_auto_delay_hours', $payoutAutoDelayHours ?? 48) }}" min="0" max="720" style="width: 6rem;">
                        <div class="form-text small">0 = сразу, 48 = через 48 ч (окно возврата)</div>
                    </div>
                    <div class="col-auto">
                        <label for="payout_scheduled_interval_minutes" class="form-label mb-0">Интервал запуска джобы (мин)</label>
                        <input type="number" class="form-control form-control-sm" id="payout_scheduled_interval_minutes" name="payout_scheduled_interval_minutes"
                               value="{{ old('payout_scheduled_interval_minutes', $payoutScheduledIntervalMinutes ?? 10) }}" min="1" max="1440" style="width: 6rem;">
                        <div class="form-text small">Как часто обрабатываются отложенные выплаты</div>
                    </div>
                    <div class="col-auto">
                        <button type="submit" class="btn btn-primary btn-sm">Сохранить</button>
                    </div>
                </form>
                <div class="small text-muted mt-2">Изменение интервала джобы применится после перезапуска планировщика (cron/queue).</div>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-sm align-middle">
                <thead class="table-light">
                <tr>
                    <th>#</th>
                    <th>Партнёр</th>
                    <th>Метод</th>
                    <th>Эквайринг банка</th>
                    <th>Выплата банка</th>
                    <th>Комиссия платформы</th>
                    <th>Вкл</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                @foreach($rules as $r)
                    <tr>
                        <td>{{ $r->id }}</td>
                        <td>
                            @if($r->partner_id)
                                @php
                                    $pid = (int) $r->partner_id;
                                    $auto = !empty($autoPayoutByPartnerId) ? (bool) ($autoPayoutByPartnerId[$pid] ?? false) : false;
                                    $conn = !empty($tbankConnectedByPartnerId) ? (bool) ($tbankConnectedByPartnerId[$pid] ?? false) : false;
                                    $stats = ($autoPayoutStatsByPartnerId ?? collect())->get($pid);
                                @endphp
                                <div>{{ optional($r->partner)->title ?? ('#'.$pid) }}</div>
                                <div class="small">
                                    <span class="text-muted">Автовыплата:</span>
                                    @if($auto)
                                        <span class="badge text-bg-success">on</span>
                                    @else
                                        <span class="badge text-bg-secondary">off</span>
                                    @endif
                                    @if(!$conn)
                                        <span class="badge text-bg-warning">ключи?</span>
                                    @endif
                                </div>
                                <div class="small text-muted mt-1">
                                    За 30 дн.: {{ $stats['count'] ?? 0 }} автовыплат
                                    @if(!empty($stats['last_at']))
                                        , последняя {{ $stats['last_at']->format('d.m.Y H:i') }}
                                    @endif
                                    <a href="{{ url('/admin/tinkoff/payouts?partner_id=' . $pid . '&source=auto') }}" class="ms-1" target="_blank" title="К выплатам (авто)">→</a>
                                </div>
                            @else
                                — (глобально)
                            @endif
                        </td>
                        <td>{{ $r->method ?? '—' }}</td>
                        <td>
                            {{ number_format($r->acquiring_percent ?? 2.49, 2, ',', ' ') }}%
                            <div class="text-muted small">мин {{ number_format($r->acquiring_min_fixed ?? 3.49, 2, ',', ' ') }} ₽</div>
                        </td>
                        <td>
                            {{ number_format($r->payout_percent ?? 0.10, 2, ',', ' ') }}%
                            <div class="text-muted small">мин {{ number_format($r->payout_min_fixed ?? 0.00, 2, ',', ' ') }} ₽</div>
                        </td>
                        <td>
                            {{ number_format($r->platform_percent ?? $r->percent ?? 0, 2, ',', ' ') }}%
                            <div class="text-muted small">мин {{ number_format($r->platform_min_fixed ?? $r->min_fixed ?? 0.00, 2, ',', ' ') }} ₽</div>
                        </td>
                        <td>
                            @if($r->is_enabled)
                                <span class="badge text-bg-success">on</span>
                            @else
                                <span class="badge text-bg-secondary">off</span>
                            @endif
                        </td>
                        <td class="text-end">
                            <a class="btn btn-outline-primary btn-sm"
                               href="{{ route('admin.setting.tbankCommissions.edit', ['id' => $r->id]) }}">Править</a>
                            <form action="{{ route('admin.setting.tbankCommissions.destroy', ['id' => $r->id]) }}"
                                  method="post"
                                  class="d-inline"
                                  onsubmit="return confirm('Удалить правило?')">
                                @csrf
                                @method('delete')
                                <button class="btn btn-outline-danger btn-sm">Удалить</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>

        {{ $rules->links() }}
    @endif
</div>

