@if ($compact ?? false)
    {{-- Разметка как в модалке «Создание пользователя»: text-start, mb-3, обычные размеры полей --}}
    @php
        $idPrefix = $idPrefix ?? 'tbank_create';
        $autoPayoutBlockId = $autoPayoutBlockId ?? ($idPrefix.'_auto_payout_block');
        $partnerSelectId = $partnerSelectId ?? ($idPrefix.'_partner_id');
        $partnerTitleId = $partnerTitleId ?? ($idPrefix.'_partner_title');
        $methodLabelId = $methodLabelId ?? ($idPrefix.'_method_label');
        $enabledId = $enabledId ?? ($idPrefix.'_auto_payout_enabled');
        $delayHoursId = $delayHoursId ?? ($idPrefix.'_auto_payout_delay_hours');
        $isEnabledId = $isEnabledId ?? ($idPrefix.'_is_enabled');
        $showPayoutStats = $showPayoutStats ?? false;
        $lockScope = $lockScope ?? false;
        $lockedPartnerId = old('partner_id', optional($rule)->partner_id);
        $lockedMethod = old('method', optional($rule)->method);
        $lockedPartnerTitle = '— Глобально —';
        if ($lockedPartnerId) {
            $foundPartner = collect($partners ?? [])->first(function ($p) use ($lockedPartnerId) {
                return (int) $p->id === (int) $lockedPartnerId;
            });
            $lockedPartnerTitle = $foundPartner ? (string) $foundPartner->title : ('#'.$lockedPartnerId);
        }
        $lockedMethodLabel = \App\Models\TinkoffCommissionRule::methodFormLabel(
            is_string($lockedMethod) && $lockedMethod !== '' ? $lockedMethod : null
        );
        $acqP = old('acquiring_percent', optional($rule)->acquiring_percent) ?? 2.49;
        $acqM = old('acquiring_min_fixed', optional($rule)->acquiring_min_fixed) ?? 3.49;
        $poP  = old('payout_percent', optional($rule)->payout_percent) ?? 0.10;
        $poM  = old('payout_min_fixed', optional($rule)->payout_min_fixed) ?? 0.00;
        $plP  = old('platform_percent', optional($rule)->platform_percent) ?? (old('percent', optional($rule)->percent) ?? 0);
        $plM  = old('platform_min_fixed', optional($rule)->platform_min_fixed) ?? (old('min_fixed', optional($rule)->min_fixed) ?? 0);
        $m = old('method', optional($rule)->method);
    @endphp
    <div class="text-start">
        <div class="mb-3">
            @if($lockScope)
                <label class="form-label">Партнёр</label>
                <div class="form-control-plaintext py-0" id="{{ $partnerTitleId }}">{{ $lockedPartnerTitle }}</div>
                <input type="hidden" name="partner_id" id="{{ $partnerSelectId }}" value="{{ $lockedPartnerId }}">
                <div class="invalid-feedback" data-error-for="partner_id">@error('partner_id'){{ $message }}@enderror</div>
            @else
                <label class="form-label" for="{{ $partnerSelectId }}">Партнёр (опционально)</label>
                <select name="partner_id" class="form-select @error('partner_id') is-invalid @enderror" id="{{ $partnerSelectId }}">
                    <option value="">— Глобально —</option>
                    @foreach($partners as $p)
                        <option value="{{ $p->id }}" @selected(old('partner_id', optional($rule)->partner_id) == $p->id)>{{ $p->title }}</option>
                    @endforeach
                </select>
                <div class="invalid-feedback" data-error-for="partner_id">@error('partner_id'){{ $message }}@enderror</div>
            @endif
        </div>
        <div class="mb-3 tbank-auto-payout-block d-none" id="{{ $autoPayoutBlockId }}">
            <div class="alert alert-light border mb-0">
                <div class="fw-semibold mb-1">Автовыплата партнёру после успешной оплаты</div>
                <div class="text-muted small mb-2">В разрезе выбранного партнёра и метода оплаты.</div>
                @if($showPayoutStats)
                    <div class="text-warning small mb-2 d-none" id="tbank-edit-keys-warning">
                        T‑Bank на платформе не считается «подключённым» (проверь глобальные ключи eacq/e2c в «Платёжных системах»).
                    </div>
                @endif
                <div class="form-check form-switch mb-2">
                    <input class="form-check-input"
                           type="checkbox"
                           role="switch"
                           id="{{ $enabledId }}"
                           name="auto_payout_enabled"
                           value="1"
                           {{ old('auto_payout_enabled', optional($rule)->auto_payout_enabled) ? 'checked' : '' }}>
                    <label class="form-check-label" for="{{ $enabledId }}">Автовыплата включена</label>
                </div>
                <div>
                    <label for="{{ $delayHoursId }}" class="form-label">Задержка после оплаты (часы)</label>
                    <input type="number"
                           class="form-control @error('auto_payout_delay_hours') is-invalid @enderror"
                           id="{{ $delayHoursId }}"
                           name="auto_payout_delay_hours"
                           min="0"
                           max="720"
                           style="max-width: 8rem;"
                           value="{{ old('auto_payout_delay_hours', optional($rule)->auto_payout_delay_hours ?? 0) }}">
                    <div class="form-text">0 = сразу, 48 = через 48 ч (окно возврата)</div>
                    <div class="invalid-feedback" data-error-for="auto_payout_delay_hours">@error('auto_payout_delay_hours'){{ $message }}@enderror</div>
                </div>
                @if($showPayoutStats)
                    <div class="small text-muted mt-2" id="tbank-edit-payout-stats">
                        За 30 дн.: <span id="tbank-edit-payouts-count">0</span> автовыплат<span id="tbank-edit-payouts-last-wrap" class="d-none">, последняя <span id="tbank-edit-payouts-last"></span></span>
                        — <a href="#" id="tbank-edit-payouts-link" target="_blank">к выплатам (авто)</a>
                    </div>
                @endif
            </div>
        </div>
        <div class="mb-3">
            @if($lockScope)
                <label class="form-label">Метод</label>
                <div class="form-control-plaintext py-0" id="{{ $methodLabelId }}">{{ $lockedMethodLabel }}</div>
                <input type="hidden" name="method" id="{{ $idPrefix }}_method" value="{{ $lockedMethod }}">
                <div class="invalid-feedback" data-error-for="method">@error('method'){{ $message }}@enderror</div>
            @else
                <label class="form-label" for="{{ $idPrefix }}_method">Метод</label>
                <select name="method" class="form-select @error('method') is-invalid @enderror" id="{{ $idPrefix }}_method">
                    <option value="">— Для всех —</option>
                    <option value="card" @selected($m==='card')>Карты</option>
                    <option value="sbp"  @selected($m==='sbp')>СБП</option>
                    <option value="tpay" @selected($m==='tpay')>T‑Pay</option>
                </select>
                <div class="invalid-feedback" data-error-for="method">@error('method'){{ $message }}@enderror</div>
            @endif
        </div>
        <div class="mb-3">
            <div class="alert alert-light border mb-0">
                <div class="fw-semibold mb-2">Комиссии (удерживаются с партнёра)</div>

                <div class="mb-3">
                    <div class="fw-semibold mb-2">Банк: эквайринг</div>
                    <div class="row g-2">
                        <div class="col-6">
                            <label class="form-label" for="{{ $idPrefix }}_acquiring_percent">% </label>
                            <input type="number" step="0.01" min="0" name="acquiring_percent" id="{{ $idPrefix }}_acquiring_percent" class="form-control @error('acquiring_percent') is-invalid @enderror" value="{{ $acqP }}" required>
                            <div class="invalid-feedback" data-error-for="acquiring_percent">@error('acquiring_percent'){{ $message }}@enderror</div>
                        </div>
                        <div class="col-6">
                            <label class="form-label" for="{{ $idPrefix }}_acquiring_min_fixed">Мин., ₽</label>
                            <input type="number" step="0.01" min="0" name="acquiring_min_fixed" id="{{ $idPrefix }}_acquiring_min_fixed" class="form-control @error('acquiring_min_fixed') is-invalid @enderror" value="{{ $acqM }}" required>
                            <div class="invalid-feedback" data-error-for="acquiring_min_fixed">@error('acquiring_min_fixed'){{ $message }}@enderror</div>
                        </div>
                    </div>
                </div>

                <div class="mb-3">
                    <div class="fw-semibold mb-2">Банк: выплата партнёру</div>
                    <div class="row g-2">
                        <div class="col-6">
                            <label class="form-label" for="{{ $idPrefix }}_payout_percent">% </label>
                            <input type="number" step="0.01" min="0" name="payout_percent" id="{{ $idPrefix }}_payout_percent" class="form-control @error('payout_percent') is-invalid @enderror" value="{{ $poP }}" required>
                            <div class="invalid-feedback" data-error-for="payout_percent">@error('payout_percent'){{ $message }}@enderror</div>
                        </div>
                        <div class="col-6">
                            <label class="form-label" for="{{ $idPrefix }}_payout_min_fixed">Мин., ₽</label>
                            <input type="number" step="0.01" min="0" name="payout_min_fixed" id="{{ $idPrefix }}_payout_min_fixed" class="form-control @error('payout_min_fixed') is-invalid @enderror" value="{{ $poM }}" required>
                            <div class="invalid-feedback" data-error-for="payout_min_fixed">@error('payout_min_fixed'){{ $message }}@enderror</div>
                        </div>
                    </div>
                </div>

                <div class="mb-3">
                    <div class="fw-semibold mb-2">Платформа</div>
                    <div class="row g-2">
                        <div class="col-6">
                            <label class="form-label" for="{{ $idPrefix }}_platform_percent">% </label>
                            <input type="number" step="0.01" min="0" name="platform_percent" id="{{ $idPrefix }}_platform_percent" class="form-control @error('platform_percent') is-invalid @enderror" value="{{ $plP }}" required>
                            <div class="invalid-feedback" data-error-for="platform_percent">@error('platform_percent'){{ $message }}@enderror</div>
                        </div>
                        <div class="col-6">
                            <label class="form-label" for="{{ $idPrefix }}_platform_min_fixed">Мин., ₽</label>
                            <input type="number" step="0.01" min="0" name="platform_min_fixed" id="{{ $idPrefix }}_platform_min_fixed" class="form-control @error('platform_min_fixed') is-invalid @enderror" value="{{ $plM }}" required>
                            <div class="invalid-feedback" data-error-for="platform_min_fixed">@error('platform_min_fixed'){{ $message }}@enderror</div>
                        </div>
                    </div>
                </div>

                <div class="form-text mt-2">
                    Все комиссии вычитаются из суммы оплаты при расчёте суммы выплаты партнёру.
                </div>
            </div>
        </div>
        <div class="mb-3">
            <div class="form-check">
                <input class="form-check-input" type="checkbox" name="is_enabled" value="1" id="{{ $isEnabledId }}"
                       @checked(old('is_enabled', optional($rule)->is_enabled) ?? true)>
                <label class="form-check-label" for="{{ $isEnabledId }}">Включено</label>
            </div>
            <div class="invalid-feedback d-block" data-error-for="is_enabled">@error('is_enabled'){{ $message }}@enderror</div>
        </div>
    </div>
@else
    <div class="row g-3">
        <div class="col-md-4">
            <label class="form-label">Партнёр (опционально)</label>
            <select name="partner_id" class="form-select">
                <option value="">— Глобально —</option>
                @foreach($partners as $p)
                    <option value="{{ $p->id }}" @selected(old('partner_id', optional($rule)->partner_id) == $p->id)>{{ $p->title }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label">Метод</label>
            @php $m = old('method', optional($rule)->method); @endphp
            <select name="method" class="form-select">
                <option value="">— Для всех —</option>
                <option value="card" @selected($m==='card')>Карты</option>
                <option value="sbp"  @selected($m==='sbp')>СБП</option>
                <option value="tpay" @selected($m==='tpay')>T‑Pay</option>
            </select>
        </div>

        @php
            $acqP = old('acquiring_percent', optional($rule)->acquiring_percent) ?? 2.49;
            $acqM = old('acquiring_min_fixed', optional($rule)->acquiring_min_fixed) ?? 3.49;
            $poP  = old('payout_percent', optional($rule)->payout_percent) ?? 0.10;
            $poM  = old('payout_min_fixed', optional($rule)->payout_min_fixed) ?? 0.00;
            $plP  = old('platform_percent', optional($rule)->platform_percent) ?? (old('percent', optional($rule)->percent) ?? 0);
            $plM  = old('platform_min_fixed', optional($rule)->platform_min_fixed) ?? (old('min_fixed', optional($rule)->min_fixed) ?? 0);
        @endphp

        <div class="col-12">
            <div class="alert alert-light border mb-0">
                <div class="fw-semibold mb-2">Комиссии (удерживаются с партнёра)</div>
                <div class="row g-3">
                    <div class="col-md-4">
                        <div class="fw-semibold">Банк: эквайринг</div>
                        <div class="row g-2">
                            <div class="col-6">
                                <label class="form-label">% </label>
                                <input type="number" step="0.01" min="0" name="acquiring_percent" class="form-control" value="{{ $acqP }}" required>
                            </div>
                            <div class="col-6">
                                <label class="form-label">Мин., ₽</label>
                                <input type="number" step="0.01" min="0" name="acquiring_min_fixed" class="form-control" value="{{ $acqM }}" required>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="fw-semibold">Банк: выплата партнёру</div>
                        <div class="row g-2">
                            <div class="col-6">
                                <label class="form-label">% </label>
                                <input type="number" step="0.01" min="0" name="payout_percent" class="form-control" value="{{ $poP }}" required>
                            </div>
                            <div class="col-6">
                                <label class="form-label">Мин., ₽</label>
                                <input type="number" step="0.01" min="0" name="payout_min_fixed" class="form-control" value="{{ $poM }}" required>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="fw-semibold">Платформа</div>
                        <div class="row g-2">
                            <div class="col-6">
                                <label class="form-label">% </label>
                                <input type="number" step="0.01" min="0" name="platform_percent" class="form-control" value="{{ $plP }}" required>
                            </div>
                            <div class="col-6">
                                <label class="form-label">Мин., ₽</label>
                                <input type="number" step="0.01" min="0" name="platform_min_fixed" class="form-control" value="{{ $plM }}" required>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="form-text mt-2">
                    Все комиссии вычитаются из суммы оплаты при расчёте суммы выплаты партнёру.
                </div>
            </div>
        </div>
        <div class="col-md-12">
            <div class="form-check mt-2">
                <input class="form-check-input" type="checkbox" name="is_enabled" value="1"
                       @checked(old('is_enabled', optional($rule)->is_enabled) ?? true)>
                <label class="form-check-label">Включено</label>
            </div>
        </div>
    </div>
@endif
@if (!($compact ?? false) && $errors->any())
    <div class="alert alert-danger mt-3">
        <ul class="mb-0">
            @foreach ($errors->all() as $e) <li>{{ $e }}</li> @endforeach
        </ul>
    </div>
@endif
