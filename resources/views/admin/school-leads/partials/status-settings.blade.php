@php
    $schoolLeadStatusColorSwatches = [
        '#212529', '#495057', '#6c757d', '#dc3545', '#fd7e14', '#ffc107',
        '#198754', '#20c997', '#0dcaf0', '#0d6efd', '#6f42c1', '#d63384', '#adb5bd',
    ];
@endphp

<style>
    .sls-color-picker-input {
        width: 3rem;
        height: 2.25rem;
        padding: 0.125rem;
        cursor: pointer;
    }
    .sls-color-swatch {
        width: 1.75rem;
        height: 1.75rem;
        border-radius: .25rem;
        border: 1px solid rgba(0, 0, 0, .18);
        padding: 0;
        cursor: pointer;
        flex-shrink: 0;
    }
    .sls-color-swatch:focus-visible {
        outline: 2px solid var(--bs-primary);
        outline-offset: 2px;
    }
    .sls-sort-input::-webkit-outer-spin-button,
    .sls-sort-input::-webkit-inner-spin-button {
        -webkit-appearance: none;
        margin: 0;
    }
    .sls-sort-input {
        -moz-appearance: textfield;
    }
    #schoolLeadStatusesModal {
        z-index: 1055;
    }
    #schoolLeadStatusFormModal {
        z-index: 1065;
    }
</style>

<div class="modal fade" id="schoolLeadStatusesModal" tabindex="-1" aria-labelledby="schoolLeadStatusesModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="schoolLeadStatusesModalLabel">Настройки статусов заявок</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
            </div>
            <div class="modal-body text-start">
                <div class="d-flex justify-content-start mb-3">
                    <button type="button" class="btn btn-primary" id="schoolLeadStatusCreateBtn">
                        Новый статус
                    </button>
                </div>
                <div class="table-responsive">
                    <table class="table align-middle" id="school-lead-statuses-table">
                        <thead>
                        <tr>
                            <th>Название</th>
                            <th class="text-center">Сортировка</th>
                            <th>Цвет</th>
                            <th class="text-center">В фильтре</th>
                            <th>Действия</th>
                        </tr>
                        </thead>
                        <tbody id="school-lead-statuses-table-body"></tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Закрыть</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="schoolLeadStatusFormModal" tabindex="-1" aria-labelledby="schoolLeadStatusFormModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="schoolLeadStatusForm">
                <div class="modal-header">
                    <h5 class="modal-title" id="schoolLeadStatusFormModalLabel">Статус заявки</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="schoolLeadStatusFormId" value="">

                    <div class="mb-3">
                        <label for="schoolLeadStatusFormName" class="form-label">Название</label>
                        <input type="text" class="form-control" id="schoolLeadStatusFormName" name="name" required>
                        <div class="invalid-feedback d-block" data-error-for="name"></div>
                    </div>

                    <div class="mb-3">
                        <label for="schoolLeadStatusFormColorPicker" class="form-label">Цвет</label>
                        <input type="hidden" name="color" id="schoolLeadStatusFormColor" value="#0d6efd">
                        <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                            <input type="color" id="schoolLeadStatusFormColorPicker"
                                   class="form-control form-control-color sls-color-picker-input"
                                   value="#0d6efd" title="Выберите цвет">
                            <span class="small text-muted font-monospace" id="schoolLeadStatusFormColorHex">#0d6efd</span>
                        </div>
                        <div class="d-flex flex-wrap gap-1 mb-0 sls-color-swatches" id="schoolLeadStatusFormColorSwatches">
                            @foreach ($schoolLeadStatusColorSwatches as $hex)
                                <button type="button" class="sls-color-swatch" data-hex="{{ $hex }}"
                                        style="background-color: {{ $hex }};" title="{{ $hex }}"
                                        aria-label="Цвет {{ $hex }}"></button>
                            @endforeach
                        </div>
                        <div class="invalid-feedback d-block" data-error-for="color"></div>
                    </div>

                    <div class="mb-2">
                        <label for="schoolLeadStatusFormSortOrder" class="form-label">Порядок</label>
                        <input type="number"
                               class="form-control form-control-sm sls-sort-input"
                               id="schoolLeadStatusFormSortOrder"
                               name="sort_order"
                               min="0"
                               max="65535"
                               placeholder="10">
                        <div class="invalid-feedback d-block" data-error-for="sort_order"></div>
                    </div>

                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" value="1" id="schoolLeadStatusFormDefaultFilter" name="is_default_in_filter">
                        <label class="form-check-label" for="schoolLeadStatusFormDefaultFilter">Отображается заявки в этом статусе по умолчанию</label>
                        <div class="invalid-feedback d-block" data-error-for="is_default_in_filter"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                    <button type="submit" class="btn btn-primary" id="schoolLeadStatusFormSubmitBtn">Сохранить</button>
                </div>
            </form>
        </div>
    </div>
</div>
