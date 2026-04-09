{{-- Модалка: комментарий к ручной отметке оплаты месяца (установка цен → по месяцам) --}}
<div class="modal fade" id="manualUserPricePaidModal" tabindex="-1" aria-labelledby="manualUserPricePaidModalLabel"
     aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="manualUserPricePaidModalLabel">Комментарий</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted small mb-2" id="manualUserPricePaidModalHint"></p>
                <label for="manualUserPricePaidComment" class="form-label">
                    Комментарий <span class="text-danger">*</span>
                </label>
                <textarea class="form-control" id="manualUserPricePaidComment" rows="4" maxlength="5000"
                          placeholder="Кратко укажите причину ручного изменения статуса оплаты"></textarea>
                <div class="invalid-feedback d-block" id="manualUserPricePaidCommentError" style="display: none;"></div>
            </div>
            <div class="modal-footer flex-column flex-sm-row gap-2">
                <button type="button" class="btn btn-secondary order-2 order-sm-1 w-100 w-sm-auto" data-bs-dismiss="modal">
                    Отмена
                </button>
                <button type="button" class="btn btn-primary order-1 order-sm-2 w-100 w-sm-auto" id="manualUserPricePaidConfirmBtn">
                    Сохранить
                </button>
            </div>
        </div>
    </div>
</div>
