<div class="modal fade"
     id="contractFillModal"
     tabindex="-1"
     aria-labelledby="contractFillModalLabel"
     aria-hidden="true"
     data-bs-backdrop="static"
     data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-scrollable modal-dialog-centered contract-fill-modal-dialog">
        <div class="modal-content background-color-grey contract-fill-modal-content">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title" id="contractFillModalLabel">Договор</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
            </div>
            <div class="modal-body pt-2" id="contractFillModalBody">
                <div class="text-center text-muted py-4" id="contractFillModalLoading">Загрузка…</div>
                <div id="contractFillModalError" class="alert alert-danger d-none" role="alert"></div>
                <div id="contractFillModalContent" class="d-none"></div>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Закрыть</button>
            </div>
        </div>
    </div>
</div>

<style>
    .contract-fill-modal-dialog {
        max-width: 720px;
    }

    #contractFillModal,
    #contractFillModal .modal-content,
    #contractFillModal .modal-header,
    #contractFillModal .modal-body,
    #contractFillModal .modal-footer,
    #contractFillModal .contract-fill-form,
    #contractFillModal .contract-fill-panel,
    #contractFillModal .contract-fill-panel__body,
    #contractFillModal .contract-fill-sign-block {
        text-align: left;
    }

    #contractFillModal .form-label,
    #contractFillModal label.form-label {
        display: block;
        width: 100%;
        text-align: left;
    }

    #contractFillModal .invalid-feedback,
    #contractFillModal .form-text {
        text-align: left;
    }

    .contract-fill-modal-content .modal-body {
        padding-top: 0.25rem;
    }

    .contract-fill-panel {
        background: #fff;
        border: 1px solid rgba(0, 0, 0, 0.08);
        border-radius: 0.5rem;
        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.04);
        overflow: hidden;
    }

    .contract-fill-panel__head {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 0.5rem 0.75rem;
        padding: 0.65rem 1rem;
        border-bottom: 1px solid rgba(0, 0, 0, 0.06);
        background: linear-gradient(180deg, #fafbfc 0%, #f3f4f6 100%);
    }

    .contract-fill-panel--parent .contract-fill-panel__head {
        border-left: 3px solid var(--bs-primary);
    }

    .contract-fill-panel--child .contract-fill-panel__head {
        border-left: 3px solid #20c997;
    }

    .contract-fill-panel__icon {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 1.75rem;
        height: 1.75rem;
        border-radius: 50%;
        background: rgba(255, 255, 255, 0.85);
        color: inherit;
        font-size: 0.9rem;
    }

    .contract-fill-panel--parent .contract-fill-panel__icon {
        color: var(--bs-primary);
    }

    .contract-fill-panel--child .contract-fill-panel__icon {
        color: #20c997;
    }

    .contract-fill-panel__title {
        font-weight: 600;
        font-size: 0.95rem;
    }

    .contract-fill-panel__hint {
        font-size: 0.8rem;
        margin-left: auto;
    }

    @media (max-width: 575.98px) {
        .contract-fill-panel__hint {
            margin-left: 0;
            width: 100%;
        }
    }

    .contract-fill-panel__body {
        padding: 1rem;
    }

    .contract-fill-form .form-label {
        font-size: 0.875rem;
    }
</style>
