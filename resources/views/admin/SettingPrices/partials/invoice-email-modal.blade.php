<style>
    .setting-prices-row-actions {
        display: inline-flex;
        vertical-align: middle;
    }

    .setting-prices-row-actions-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 1.75rem;
        height: 1.75rem;
        border-radius: 0.45rem;
        color: #6c757d;
        line-height: 1;
        text-decoration: none;
    }

    .setting-prices-row-actions-btn:hover,
    .setting-prices-row-actions-btn:focus,
    .setting-prices-row-actions-btn.show {
        color: #0d6efd;
        background: rgba(13, 110, 253, 0.08);
    }

    .setting-prices-row-actions .dropdown-menu {
        z-index: 20;
        min-width: 18.5rem;
        margin-top: 0.35rem;
        padding: 0.4rem;
        border: 1px solid #e9ecef;
        border-radius: 0.65rem;
        box-shadow: 0 8px 24px rgba(33, 37, 41, 0.12);
        text-align: left;
    }

    .setting-prices-row-actions .dropdown-menu > li {
        text-align: left;
    }

    .setting-prices-row-actions .dropdown-item {
        display: flex;
        align-items: center;
        justify-content: flex-start;
        gap: 0.65rem;
        width: 100%;
        margin: 0;
        padding: 0.55rem 0.7rem;
        border-radius: 0.5rem;
        color: #212529;
        font-size: 0.875rem;
        font-weight: 500;
        line-height: 1.3;
        text-align: left;
        white-space: normal;
    }

    .setting-prices-row-actions .dropdown-item::before {
        content: "";
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        width: 1.85rem;
        height: 1.85rem;
        border-radius: 0.4rem;
        background: #f1f3f5;
        color: #495057;
        font-family: "Font Awesome 6 Free";
        font-weight: 900;
        font-size: 0.8rem;
    }

    .setting-prices-row-actions .user-price-manual-edit::before {
        content: "\f304";
    }

    .setting-prices-row-actions .setting-prices-invoice-email::before {
        content: "\f0e0";
    }

    .setting-prices-row-actions .dropdown-item:hover,
    .setting-prices-row-actions .dropdown-item:focus {
        color: #0a58ca;
        background: #f8f9fa;
    }

    .setting-prices-row-actions .dropdown-item:hover::before,
    .setting-prices-row-actions .dropdown-item:focus::before {
        background: rgba(13, 110, 253, 0.1);
        color: #0d6efd;
    }

    /* Старый карандаш: padding:0 и оранжевый цвет не должны попадать в пункт меню. */
    #right_bar .wrap-users .setting-prices-row-actions .dropdown-item.setting-prices-monthly-edit-btn,
    #right_bar .wrap-users .setting-prices-row-actions .dropdown-item.user-price-manual-edit {
        min-width: 0;
        padding: 0.55rem 0.7rem !important;
        font-size: 0.875rem;
        line-height: 1.3;
        color: #212529 !important;
    }

    #right_bar .wrap-users .setting-prices-row-actions .dropdown-item.user-price-manual-edit:hover,
    #right_bar .wrap-users .setting-prices-row-actions .dropdown-item.user-price-manual-edit:focus {
        color: #0a58ca !important;
    }

    #setting-prices-invoice-email-modal .modal-body {
        padding-top: 1rem;
        padding-bottom: 0.75rem;
    }

    #setting-prices-invoice-email-modal .setting-prices-invoice-email-card {
        margin-bottom: 1rem;
        padding: 0.85rem 1rem;
        border: 1px solid #e9ecef;
        border-radius: 0.65rem;
        background: #f8f9fa;
    }

    #setting-prices-invoice-email-modal .setting-prices-invoice-email-card__name {
        font-size: 1.05rem;
        font-weight: 600;
        color: #212529;
        line-height: 1.35;
        word-break: break-word;
    }

    #setting-prices-invoice-email-modal .setting-prices-invoice-email-card__line {
        margin-top: 0.2rem;
        color: #6c757d;
        font-size: 0.8125rem;
        line-height: 1.3;
        word-break: break-word;
    }

    #setting-prices-invoice-email-modal .setting-prices-invoice-email-card__line:empty,
    #setting-prices-invoice-email-modal .setting-prices-invoice-email-card__pill:empty {
        display: none;
    }

    #setting-prices-invoice-email-modal .setting-prices-invoice-email-card__pill {
        display: inline-block;
        margin-top: 0.65rem;
        padding: 0.28rem 0.7rem;
        border: 1px solid #dee2e6;
        border-radius: 999px;
        background: #fff;
        color: #495057;
        font-size: 0.8125rem;
        line-height: 1.3;
    }

    #setting-prices-invoice-email-modal .setting-prices-invoice-email-fact {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 0.75rem;
        margin: 0;
        padding: 0.7rem 0.85rem;
        border: 1px solid #e9ecef;
        border-radius: 0.55rem;
        background: #fff;
    }

    #setting-prices-invoice-email-modal .setting-prices-invoice-email-list {
        display: flex;
        flex-direction: column;
        gap: 0.4rem;
    }

    #setting-prices-invoice-email-modal .setting-prices-invoice-email-fact__label {
        color: #6c757d;
        font-size: 0.8125rem;
        font-weight: 600;
        flex-shrink: 0;
    }

    #setting-prices-invoice-email-modal .setting-prices-invoice-email-fact__value {
        color: #212529;
        font-size: 0.9375rem;
        font-weight: 500;
        text-align: right;
        line-height: 1.3;
        min-width: 0;
        word-break: break-word;
    }

    #setting-prices-invoice-email-modal .modal-footer {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 0.5rem;
    }

    #setting-prices-invoice-email-modal .btn:disabled {
        opacity: 1;
        pointer-events: none;
    }

    #setting-prices-invoice-email-modal .btn-primary:disabled {
        color: #fff !important;
        background-color: #b8bec5 !important;
        border-color: #a8b0b8 !important;
    }

    #setting-prices-invoice-email-preview-frame {
        width: 100%;
        height: 280px;
        border: 1px solid #e9ecef;
        border-radius: 0.65rem;
        background: #fff;
    }
</style>

<div class="modal fade" id="setting-prices-invoice-email-modal" tabindex="-1"
     aria-labelledby="setting-prices-invoice-email-title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="setting-prices-invoice-email-title">Отправить счёт на email</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
            </div>
            <div class="modal-body">
                <div class="small text-danger mb-2" data-error-for="user_id" style="display:none"></div>
                <div class="small text-danger mb-2" data-error-for="team_id" style="display:none"></div>
                <div class="small text-danger mb-2" data-error-for="new_month" style="display:none"></div>
                <div class="small text-danger mb-2" id="setting-prices-invoice-email-load-error" style="display:none"></div>

                <div id="setting-prices-invoice-email-loading" class="text-muted mb-0">Загрузка…</div>
                <div id="setting-prices-invoice-email-facts" style="display:none">
                    <div class="setting-prices-invoice-email-card">
                        <div class="setting-prices-invoice-email-card__name" data-fact="student_name"></div>
                        <div class="setting-prices-invoice-email-card__line" data-fact="team"></div>
                        <div class="setting-prices-invoice-email-card__line" data-fact="month"></div>
                        <span class="setting-prices-invoice-email-card__pill" data-fact="amount"></span>
                    </div>
                    <div class="small text-danger mb-2" data-error-for="amount" style="display:none"></div>

                    <div class="setting-prices-invoice-email-list">
                        <div>
                            <div class="setting-prices-invoice-email-fact">
                                <span class="setting-prices-invoice-email-fact__label">Абонемент</span>
                                <span class="setting-prices-invoice-email-fact__value" data-fact="package"></span>
                            </div>
                            <div class="small text-danger mt-1" data-error-for="package" style="display:none"></div>
                        </div>
                        <div class="setting-prices-invoice-email-fact">
                            <span class="setting-prices-invoice-email-fact__label">Родитель</span>
                            <span class="setting-prices-invoice-email-fact__value" data-fact="parent_name"></span>
                        </div>
                        <div>
                            <div class="setting-prices-invoice-email-fact">
                                <span class="setting-prices-invoice-email-fact__label">Email</span>
                                <span class="setting-prices-invoice-email-fact__value" data-fact="email"></span>
                            </div>
                            <div class="small text-danger mt-1" data-error-for="email" style="display:none"></div>
                        </div>
                    </div>
                    <div class="small text-danger mt-1" data-error-for="pay_url" style="display:none"></div>
                </div>

                <div id="setting-prices-invoice-email-preview" class="mt-3" style="display:none">
                    <div class="small text-muted mb-1">Тема: <span id="setting-prices-invoice-email-subject"></span></div>
                    <iframe id="setting-prices-invoice-email-preview-frame" title="Превью письма"></iframe>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Отмена</button>
                <button type="button" class="btn btn-primary" id="setting-prices-invoice-email-send" disabled>Отправить</button>
            </div>
        </div>
    </div>
</div>

<script>
    (function () {
        var oldEditLabel = 'Изменить статус оплаты и стоимость';
        var editLabel = 'Изменить статус и сумму';
        function renameEditItems(root) {
            root.querySelectorAll('.user-price-manual-edit').forEach(function (btn) {
                if ((btn.textContent || '').trim() === oldEditLabel) {
                    btn.textContent = editLabel;
                }
            });
        }
        var pricesRoot = document.getElementById('right_bar');
        if (pricesRoot && window.MutationObserver) {
            renameEditItems(pricesRoot);
            new MutationObserver(function () {
                renameEditItems(pricesRoot);
            }).observe(pricesRoot, { childList: true, subtree: true });
        }

        var pending = null;
        var previewHtml = '';

        function $modal() {
            return $('#setting-prices-invoice-email-modal');
        }

        function clearErrors() {
            var root = $modal();
            root.find('[data-error-for]').hide().text('');
            root.find('#setting-prices-invoice-email-load-error').hide().text('');
        }

        function showErrors(errors) {
            var root = $modal();
            var unmatched = [];
            Object.keys(errors || {}).forEach(function (key) {
                var msg = errors[key] && errors[key][0] ? String(errors[key][0]) : '';
                if (!msg) {
                    return;
                }
                var slot = root.find('[data-error-for="' + key + '"]');
                if (slot.length) {
                    slot.text(msg).show();
                } else {
                    unmatched.push(msg);
                }
            });
            if (unmatched.length) {
                root.find('#setting-prices-invoice-email-load-error').text(unmatched.join(' ')).show();
            }
        }

        function setBusy(busy) {
            $modal().find('#setting-prices-invoice-email-send').prop('disabled', !!busy);
        }

        function showPreview() {
            var box = $modal().find('#setting-prices-invoice-email-preview');
            if (!previewHtml) {
                box.hide();
                return;
            }
            $modal().find('#setting-prices-invoice-email-preview-frame').attr('srcdoc', previewHtml);
            box.show();
        }

        function openModal() {
            var el = document.getElementById('setting-prices-invoice-email-modal');
            if (!el || !window.bootstrap || !bootstrap.Modal) {
                return;
            }
            bootstrap.Modal.getOrCreateInstance(el).show();
        }

        function closeModal() {
            var el = document.getElementById('setting-prices-invoice-email-modal');
            if (!el || !window.bootstrap || !bootstrap.Modal) {
                return;
            }
            var instance = bootstrap.Modal.getInstance(el);
            if (instance) {
                instance.hide();
            }
        }

        function loadPreview(params) {
            pending = params;
            previewHtml = '';
            clearErrors();
            setBusy(true);
            $modal().find('#setting-prices-invoice-email-loading').show();
            $modal().find('#setting-prices-invoice-email-facts, #setting-prices-invoice-email-preview').hide();
            openModal();

            $.ajax({
                url: '/admin/setting-prices/invoice-email/preview',
                method: 'POST',
                dataType: 'json',
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                data: params,
                success: function (response) {
                    var facts = response && response.facts ? response.facts : {};
                    $modal().find('[data-fact]').each(function () {
                        var key = this.getAttribute('data-fact');
                        this.textContent = facts[key] != null ? String(facts[key]) : '';
                    });
                    $modal().find('#setting-prices-invoice-email-subject').text(response.subject || '');
                    previewHtml = response.email_html || '';
                    $modal().find('#setting-prices-invoice-email-loading').hide();
                    $modal().find('#setting-prices-invoice-email-facts').show();
                    showErrors(response.errors || {});
                    showPreview();
                    $modal().find('#setting-prices-invoice-email-send').prop('disabled', !response.can_send);
                },
                error: function (xhr) {
                    $modal().find('#setting-prices-invoice-email-loading').hide();
                    $modal().find('#setting-prices-invoice-email-facts').show();
                    var body = xhr.responseJSON || {};
                    if (body.errors) {
                        showErrors(body.errors);
                    }
                    var message = body.message || 'Не удалось подготовить письмо.';
                    if (!body.errors) {
                        $modal().find('#setting-prices-invoice-email-load-error').text(message).show();
                    }
                    setBusy(true);
                }
            });
        }

        $(document).on('click', '.setting-prices-invoice-email', function (e) {
            e.preventDefault();
            e.stopPropagation();
            var $btn = $(this);
            var userId = $btn.attr('data-user-id');
            var teamId = $btn.attr('data-team-id');
            var newMonth = $btn.attr('data-new-month');
            if (!userId || !teamId || !newMonth) {
                return;
            }
            loadPreview({
                user_id: userId,
                team_id: teamId,
                new_month: newMonth
            });
        });

        $(document).on('click', '#setting-prices-invoice-email-send', function () {
            if (!pending) {
                return;
            }
            var $btn = $(this);
            $btn.prop('disabled', true);
            clearErrors();
            $.ajax({
                url: '/admin/setting-prices/invoice-email/send',
                method: 'POST',
                dataType: 'json',
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                data: pending,
                success: function (response) {
                    closeModal();
                    if (typeof window.showToast === 'function') {
                        window.showToast((response && response.message) || 'Счёт отправлен.', 'success');
                    }
                },
                error: function (xhr) {
                    var body = xhr.responseJSON || {};
                    if (body.errors) {
                        showErrors(body.errors);
                    } else {
                        $modal().find('#setting-prices-invoice-email-load-error')
                            .text(body.message || 'Не удалось отправить счёт.')
                            .show();
                    }
                    $btn.prop('disabled', false);
                }
            });
        });

        document.addEventListener('hidden.bs.modal', function (event) {
            if (!event.target || event.target.id !== 'setting-prices-invoice-email-modal') {
                return;
            }
            pending = null;
            previewHtml = '';
            showPreview();
        });
    })();
</script>
