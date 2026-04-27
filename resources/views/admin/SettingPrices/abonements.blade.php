<div class="container setting-price-wrap">
    @include('includes.modal.manualUserPricePaidModal')

    <div class="row mt-3">
        <div class="col-12 mb-3">
            <div class="d-flex justify-content-end">
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#abonementCreateModal">
                    Добавить абонемент
                </button>
            </div>
        </div>

        <div class="col-12 mb-3">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <strong>Список абонементов</strong>
                </div>
                <div class="card-body">
                    <table class="table table-bordered w-100" id="abonements-table">
                        <thead>
                        <tr>
                            <th>ID</th>
                            <th>Ученик</th>
                            <th>Период</th>
                            <th>Сумма</th>
                            <th>Статус</th>
                            <th>Действия</th>
                        </tr>
                        </thead>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="abonementCreateModal" tabindex="-1" aria-labelledby="abonementCreateModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable" style="max-width: 560px;">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="abonementCreateModalLabel">Добавить абонемент</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
            </div>
            <div class="modal-body">
                <form id="abonement-create-form" novalidate>
                    <div class="mb-3">
                        <label class="form-label" for="abonement-user-id">Ученик</label>
                        <select class="form-select" id="abonement-user-id" name="user_id" required>
                            <option value="">Выберите ученика</option>
                            @foreach(($users ?? []) as $u)
                                @php
                                    $fullName = trim(($u->lastname ?? '') . ' ' . ($u->name ?? ''));
                                @endphp
                                <option value="{{ $u->id }}">{{ $fullName !== '' ? $fullName : ('#' . $u->id) }}</option>
                            @endforeach
                        </select>
                        <div class="invalid-feedback d-block abonement-field-error" data-field="user_id" style="display:none;"></div>
                    </div>

                    <div class="row g-2">
                        <div class="col-12 col-md-6 mb-3">
                            <label class="form-label" for="abonement-date-start">Дата начала</label>
                            <input class="form-control" type="date" id="abonement-date-start" name="date_start" required>
                            <div class="invalid-feedback d-block abonement-field-error" data-field="date_start" style="display:none;"></div>
                        </div>
                        <div class="col-12 col-md-6 mb-3">
                            <label class="form-label" for="abonement-date-end">Дата окончания</label>
                            <input class="form-control" type="date" id="abonement-date-end" name="date_end" required>
                            <div class="invalid-feedback d-block abonement-field-error" data-field="date_end" style="display:none;"></div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="abonement-amount">Сумма</label>
                        <input class="form-control" type="number" step="0.01" min="0" id="abonement-amount" name="amount" required>
                        <div class="invalid-feedback d-block abonement-field-error" data-field="amount" style="display:none;"></div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="abonement-note">Комментарий</label>
                        <input class="form-control" type="text" maxlength="255" id="abonement-note" name="note" placeholder="Например: 1–7 мая (интенсив)">
                        <div class="invalid-feedback d-block abonement-field-error" data-field="note" style="display:none;"></div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    Отмена
                </button>
                <button type="submit" class="btn btn-primary" id="abonement-create-submit" form="abonement-create-form">
                    Добавить
                </button>
            </div>
        </div>
    </div>
</div>

@push('scripts')
    @vite(['resources/js/setting-prices-abonements.js', 'resources/js/setting-prices-manual-paid-modal.js'])
@endpush

