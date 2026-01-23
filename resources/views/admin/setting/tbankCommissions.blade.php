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

