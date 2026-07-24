<div class="container setting-price-wrap">
    @include('includes.modal.manualUserPricePaidModal')

    @push('styles')
        <style>
            /* Таблица доп. платежей: «Действия» по кнопке, текстовые колонки делят остаток */
            #custom-payments-table.dt-columns-managed th.dt-col-actions,
            #custom-payments-table.dt-columns-managed td.dt-col-actions {
                width: 1% !important;
                max-width: 9.5rem;
                white-space: nowrap;
            }

            #custom-payments-table.dt-columns-managed th.dt-col-text,
            #custom-payments-table.dt-columns-managed td.dt-col-text {
                width: auto;
                max-width: 16rem;
            }

            #custom-payments-table.dt-columns-managed th.dt-col-id,
            #custom-payments-table.dt-columns-managed td.dt-col-id,
            #custom-payments-table.dt-columns-managed th.dt-col-count,
            #custom-payments-table.dt-columns-managed td.dt-col-count,
            #custom-payments-table.dt-columns-managed th.dt-col-badge,
            #custom-payments-table.dt-columns-managed td.dt-col-badge {
                width: 1%;
                white-space: nowrap;
            }
        </style>
    @endpush

    <div class="row mt-3">
        <div class="col-12 mb-3">
            <div class="d-flex justify-content-end">
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#customPaymentCreateModal">
                    Добавить дополнительный платеж
                </button>
            </div>
        </div>

        <div class="col-12 mb-3">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <strong>Список дополнительных платежей</strong>
                </div>
                <div class="card-body">
                    <table class="table table-bordered w-100 dt-columns-managed" id="custom-payments-table">
                        <thead>
                        <tr>
                            <th>ID</th>
                            <th>Ученик</th>
                            <th>Группа</th>
                            <th>Сумма</th>
                            <th>Описание</th>
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

<div class="modal fade" id="customPaymentCreateModal" tabindex="-1" aria-labelledby="customPaymentCreateModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="customPaymentCreateModalLabel">Добавить дополнительный платеж</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
            </div>
            <div class="modal-body">
                <form id="custom-payment-create-form" novalidate>
                    <div class="mb-3">
                        <label class="form-label" for="custom-payment-user-id">Ученик</label>
                        <select class="form-select" id="custom-payment-user-id" name="user_id" required data-placeholder="Выберите ученика">
                            <option value=""></option>
                        </select>
                        <div class="invalid-feedback d-block custom-payment-field-error" data-field="user_id" style="display:none;"></div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="custom-payment-team-id">Группа</label>
                        <select class="form-select" id="custom-payment-team-id" name="team_id" required data-placeholder="Сначала выберите ученика" disabled>
                            <option value=""></option>
                        </select>
                        <div class="invalid-feedback d-block custom-payment-field-error" data-field="team_id" style="display:none;"></div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="custom-payment-amount">Сумма</label>
                        <input class="form-control" type="number" step="1" min="0" id="custom-payment-amount" name="amount" required>
                        <div class="invalid-feedback d-block custom-payment-field-error" data-field="amount" style="display:none;"></div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="custom-payment-note">Описание (отображается у ученика)</label>
                        <input class="form-control" type="text" maxlength="255" id="custom-payment-note" name="note" placeholder="Например: 1-7 мая (интенсив)">
                        <div class="invalid-feedback d-block custom-payment-field-error" data-field="note" style="display:none;"></div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    Отмена
                </button>
                <button type="submit" class="btn btn-primary" id="custom-payment-create-submit" form="custom-payment-create-form">
                    Добавить
                </button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="customPaymentEditModal" tabindex="-1" aria-labelledby="customPaymentEditModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="customPaymentEditModalLabel">Редактировать дополнительный платеж</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
            </div>
            <div class="modal-body">
                <form id="custom-payment-edit-form" novalidate>
                    <input type="hidden" name="id" id="custom-payment-edit-id" value="">
                    <input type="hidden" name="initial_is_paid" id="custom-payment-edit-initial-is-paid" value="0">

                    <div class="mb-3">
                        <label class="form-label" for="custom-payment-edit-amount">Сумма</label>
                        <input class="form-control" type="number" step="1" min="0" id="custom-payment-edit-amount" name="amount">
                        <div class="invalid-feedback d-block custom-payment-edit-field-error" data-field="amount" style="display:none;"></div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="custom-payment-edit-is-paid">Статус оплаты</label>
                        <select class="form-select" id="custom-payment-edit-is-paid" name="is_paid">
                            <option value="0">Не оплачено</option>
                            <option value="1">Оплачено</option>
                        </select>
                        <div class="invalid-feedback d-block custom-payment-edit-field-error" data-field="is_paid" style="display:none;"></div>
                    </div>

                    <div class="mb-3" id="custom-payment-edit-status-comment-wrap" style="display:none;">
                        <label class="form-label" for="custom-payment-edit-status-comment">Комментарий к изменению статуса оплаты</label>
                        <textarea class="form-control" id="custom-payment-edit-status-comment" name="status_comment" rows="3" maxlength="5000"></textarea>
                        <div class="invalid-feedback d-block custom-payment-edit-field-error" data-field="status_comment" style="display:none;"></div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="custom-payment-edit-note">Описание (отображается у ученика)</label>
                        <input class="form-control" type="text" maxlength="255" id="custom-payment-edit-note" name="note">
                        <div class="invalid-feedback d-block custom-payment-edit-field-error" data-field="note" style="display:none;"></div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-danger me-auto" id="custom-payment-edit-delete" style="display:none;">
                    Удалить
                </button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    Отмена
                </button>
                <button type="submit" class="btn btn-primary" id="custom-payment-edit-submit" form="custom-payment-edit-form">
                    Сохранить
                </button>
            </div>
        </div>
    </div>
</div>

    @push('scripts')
    <script>
        window.__kidsDatatableRu = @include('partials.datatables.ru');
    </script>
    <script>
        (function ($) {
            function initCustomPaymentUserSelect2() {
                var $userSelect = $('#custom-payment-user-id');
                if (!$userSelect.length || !$.fn.select2) {
                    return;
                }

                if ($userSelect.data('select2')) {
                    $userSelect.select2('destroy');
                }

                $userSelect.select2({
                    theme: 'bootstrap-5',
                    width: '100%',
                    placeholder: $userSelect.data('placeholder') || 'Выберите ученика',
                    language: @include('partials.select2.ru'),
                    allowClear: true,
                    dropdownParent: $('#customPaymentCreateModal'),
                    ajax: {
                        url: @json(route('admin.settingPrices.customPayments.users-search')),
                        delay: 250,
                        data: function (params) {
                            return { q: params.term || '' };
                        },
                        processResults: function (data) {
                            return data;
                        }
                    },
                    minimumInputLength: 0
                });

                $userSelect.on('change', function () {
                    var $teamSelect = $('#custom-payment-team-id');
                    if (!$teamSelect.length) return;
                    $teamSelect.val(null).trigger('change');
                    $teamSelect.prop('disabled', !$(this).val());
                });
            }

            function initCustomPaymentTeamSelect2() {
                var $teamSelect = $('#custom-payment-team-id');
                if (!$teamSelect.length || !$.fn.select2) {
                    return;
                }

                if ($teamSelect.data('select2')) {
                    $teamSelect.select2('destroy');
                }

                $teamSelect.select2({
                    theme: 'bootstrap-5',
                    width: '100%',
                    placeholder: $teamSelect.data('placeholder') || 'Выберите группу',
                    language: @include('partials.select2.ru'),
                    allowClear: true,
                    dropdownParent: $('#customPaymentCreateModal'),
                    ajax: {
                        url: window.__customPaymentsTeamsForUserUrl || '',
                        delay: 250,
                        data: function (params) {
                            return {
                                q: params.term || '',
                                user_id: $('#custom-payment-user-id').val() || ''
                            };
                        },
                        processResults: function (data) {
                            return data;
                        }
                    },
                    minimumInputLength: 0
                });
            }

            $(function () {
                initCustomPaymentUserSelect2();
                initCustomPaymentTeamSelect2();
            });
        })(window.jQuery);
    </script>
    <script>
        window.__customPaymentsTeamsForUserUrl = @json(route('admin.settingPrices.customPayments.teams-for-user'));
        window.__customPaymentsCanManualPaid = @json(auth()->user()?->can('setPrices.manualPaid.manage') ?? false);
    </script>
    <script type="module" src="{{ asset('js/setting-prices-custom-payments.js') }}?v={{ @filemtime(public_path('js/setting-prices-custom-payments.js')) ?: time() }}"></script>
    @vite(['resources/js/setting-prices-manual-paid-modal.js'])
@endpush
