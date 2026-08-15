<div class="modal fade" id="trainerTypesModal" tabindex="-1" aria-labelledby="trainerTypesModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content background-color-grey">
            <div class="modal-header">
                <h5 class="modal-title" id="trainerTypesModalLabel">Типы тренеров</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-start">
                <div class="alert alert-danger d-none js-form-error mb-3" role="alert"></div>

                <div id="trainer-types-list-wrap">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <p class="small text-muted mb-0">Оклад за тренировку и базовая премия задаются здесь и сразу попадают в черновики ЗП Канзаса.</p>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead>
                            <tr>
                                <th>Название</th>
                                <th class="text-end">Оклад за трен.</th>
                                <th class="text-end">Баз. премия</th>
                                <th></th>
                            </tr>
                            </thead>
                            <tbody id="trainer-types-list-body"></tbody>
                        </table>
                    </div>
                    <div class="d-flex justify-content-end gap-2 mt-3">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Закрыть</button>
                        @if($canManageTrainerTypes ?? false)
                            <button type="button" class="btn btn-primary" id="trainer-types-add-btn">Добавить тип</button>
                        @endif
                    </div>
                </div>

                <form id="trainer-type-form" class="d-none">
                    @csrf
                    <input type="hidden" name="id" id="trainer-type-id" value="">
                    <div class="mb-3">
                        <label class="form-label" for="trainer-type-name">Название*</label>
                        <input type="text" class="form-control" name="name" id="trainer-type-name" maxlength="255">
                        <div class="invalid-feedback d-block" data-error-for="name"></div>
                    </div>
                    <div class="row g-3">
                        <div class="col-12 col-md-6">
                            <div class="mb-3">
                                <label class="form-label" for="trainer-type-rate">Оклад за тренировку*</label>
                                <input type="number" class="form-control" name="rate_per_training" id="trainer-type-rate" min="0" step="0.01">
                                <div class="invalid-feedback d-block" data-error-for="rate_per_training"></div>
                            </div>
                        </div>
                        <div class="col-12 col-md-6">
                            <div class="mb-3">
                                <label class="form-label" for="trainer-type-premium">Базовая премия за тренировку*</label>
                                <input type="number" class="form-control" name="base_premium" id="trainer-type-premium" min="0" step="0.01">
                                <div class="invalid-feedback d-block" data-error-for="base_premium"></div>
                            </div>
                        </div>
                        <div class="col-12 col-md-6">
                            <div class="mb-3">
                                <label class="form-label" for="trainer-type-sort">Порядок сортировки</label>
                                <input type="number" class="form-control" name="sort_order" id="trainer-type-sort" min="0" value="10">
                                <div class="invalid-feedback d-block" data-error-for="sort_order"></div>
                            </div>
                        </div>
                        <div class="col-12 col-md-6" id="trainer-type-enabled-wrap">
                            <div class="mb-3">
                                <label class="form-label" for="trainer-type-enabled">Активен</label>
                                <select class="form-select" name="is_enabled" id="trainer-type-enabled">
                                    <option value="1">Да</option>
                                    <option value="0">Нет</option>
                                </select>
                                <div class="invalid-feedback d-block" data-error-for="is_enabled"></div>
                            </div>
                        </div>
                    </div>
                    <div class="d-flex justify-content-between gap-2 mt-2">
                        <button type="button" class="btn btn-secondary" id="trainer-type-form-back">К списку</button>
                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-danger d-none" id="trainer-type-delete-btn">Удалить</button>
                            <button type="button" class="btn btn-primary" id="trainer-type-save-btn">Сохранить</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
