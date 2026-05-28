<div class="container setting-price-wrap">
    @include('includes.modal.manualUserPricePaidModal')

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
                    <table class="table table-bordered w-100" id="custom-payments-table">
                        <thead>
                        <tr>
                            <th>ID</th>
                            <th>Ученик</th>
                            <th>Период</th>
                            <th>Сумма</th>
                            <th>Комментарий</th>
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
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable" style="max-width: 560px;">
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

                    <div class="row g-2">
                        <div class="col-12 col-md-6 mb-3">
                            <label class="form-label" for="custom-payment-date-start">Дата начала</label>
                            <input class="form-control" type="date" id="custom-payment-date-start" name="date_start">
                            <div class="invalid-feedback d-block custom-payment-field-error" data-field="date_start" style="display:none;"></div>
                        </div>
                        <div class="col-12 col-md-6 mb-3">
                            <label class="form-label" for="custom-payment-date-end">Дата окончания</label>
                            <input class="form-control" type="date" id="custom-payment-date-end" name="date_end">
                            <div class="invalid-feedback d-block custom-payment-field-error" data-field="date_end" style="display:none;"></div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="custom-payment-amount">Сумма</label>
                        <input class="form-control" type="number" step="0.01" min="0" id="custom-payment-amount" name="amount" required>
                        <div class="invalid-feedback d-block custom-payment-field-error" data-field="amount" style="display:none;"></div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="custom-payment-note">Комментарий</label>
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

@push('scripts')
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
            }

            $(function () {
                initCustomPaymentUserSelect2();
            });
        })(window.jQuery);
    </script>
    @vite(['resources/js/setting-prices-custom-payments.js', 'resources/js/setting-prices-manual-paid-modal.js'])
@endpush
