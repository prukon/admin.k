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
            <option value="tpay" @selected($m==='tpay')>Tinkoff Pay</option>
        </select>
    </div>

    @php
        // fallback на legacy-поля для старых записей
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
@if ($errors->any())
    <div class="alert alert-danger mt-3">
        <ul class="mb-0">
            @foreach ($errors->all() as $e) <li>{{ $e }}</li> @endforeach
        </ul>
    </div>
@endif
