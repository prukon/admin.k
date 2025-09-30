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
    <div class="col-md-2">
        <label class="form-label">% (моя)</label>
        <input type="number" step="0.01" min="0" name="percent" class="form-control"
               value="{{ old('percent', optional($rule)->percent) }}" required>
    </div>
    <div class="col-md-2">
        <label class="form-label">Мин., ₽</label>
        <input type="number" step="0.01" min="0" name="min_fixed" class="form-control"
               value="{{ old('min_fixed', optional($rule)->min_fixed) }}" required>
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
