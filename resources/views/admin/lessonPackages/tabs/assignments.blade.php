<div class="tab-content">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 pt-3">
        <h4 class="mb-0">Назначение абонементов</h4>
        @can('lessonPackages.view')
            <button type="button"
                    class="btn btn-primary"
                    data-bs-toggle="modal"
                    data-bs-target="#ulpAssignmentCreateModal">
                Назначить абонемент
            </button>
        @endcan
    </div>

    <div id="ulp-copy-pay-toast" class="alert alert-success py-2 px-3 small d-none mt-2 mb-0" role="status">
        Ссылка скопирована
    </div>

    <hr>

    @if (session('success'))
        <div class="alert alert-success">
            {{ session('success') }}
        </div>
    @endif

    @can('lessonPackages.view')
        <div class="modal fade" id="ulpAssignmentCreateModal" tabindex="-1" aria-labelledby="ulpAssignmentCreateModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <form method="POST" action="{{ route('admin.lesson-packages.assignments.store') }}" id="ulp-assign-create-form" novalidate>
                        @csrf
                        <div class="modal-header">
                            <h5 class="modal-title" id="ulpAssignmentCreateModalLabel">Назначение абонемента</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
                        </div>
                        <div class="modal-body">
                            <div class="row g-3">
                                <div class="col-12">
                                    <label class="form-label">Ученик</label>
                                    <select name="user_id"
                                            id="ulp_user_id"
                                            class="form-select @error('user_id') is-invalid @enderror"
                                            required>
                                        <option value="">Выберите ученика</option>
                                    </select>
                                    @error('user_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-12">
                                    <label class="form-label">Абонемент</label>
                                    <select name="lesson_package_id"
                                            id="ulp_lesson_package_id"
                                            class="form-select @error('lesson_package_id') is-invalid @enderror"
                                            required>
                                        <option value="">Выберите абонемент</option>
                                        @foreach ($packagesList as $p)
                                            <option value="{{ $p->id }}"
                                                    data-schedule-type="{{ $p->schedule_type }}"
                                                    data-duration-days="{{ $p->duration_days }}"
                                                    data-lessons-count="{{ $p->lessons_count }}"
                                                    data-price-cents="{{ (int) ($p->price_cents ?? 0) }}"
                                                {{ (int)old('lesson_package_id') === (int)$p->id ? 'selected' : '' }}>
                                                {{ $p->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('lesson_package_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-12">
                                    <label class="form-label">Стоимость</label>
                                    <input type="number"
                                           name="fee_amount"
                                           id="ulp_fee_amount"
                                           step="0.01"
                                           min="0"
                                           max="999999.99"
                                           value="{{ old('fee_amount') }}"
                                           class="form-control @error('fee_amount') is-invalid @enderror"
                                           required>
                                    @error('fee_amount')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Отмена</button>
                            <button type="submit" class="btn btn-primary">Назначить</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endcan

    <div class="table-responsive">
        <table id="ulp-assignments-table" class="table table-sm table-striped table-bordered align-middle w-100 dt-columns-managed" style="width:100%">
            <thead>
            <tr>
                <th class="d-none">ID</th>
                <th>Ученик</th>
                <th>Абонемент</th>
                <th class="text-center text-nowrap" style="width: 1%">Тип</th>
                <th class="text-center text-nowrap" style="width: 1%">Период</th>
                <th class="text-center text-nowrap" style="width: 1%">Сумма</th>
                <th class="text-center" style="width: 1%">Оплачен</th>
                <th class="text-center text-nowrap" style="width: 1%">Остаток</th>
                <th class="text-start text-nowrap" style="width: 1%">Ссылка на оплату</th>
                <th class="text-start text-nowrap" style="width: 1%">Действия</th>
            </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>

    @can('lessonPackages.view')
        <div class="modal fade" id="ulpAssignmentEditModal" tabindex="-1" aria-labelledby="ulpAssignmentEditModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="ulpAssignmentEditModalLabel">Изменение абонемента</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
                    </div>
                    <div class="modal-body">
                        <div id="ulp-modal-alert" class="alert alert-danger d-none" role="alert"></div>

                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label">Ученик</label>
                                <input type="text" class="form-control" id="ulp-modal-user" disabled readonly>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Абонемент</label>
                                <input type="text" class="form-control" id="ulp-modal-package" disabled readonly>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Период</label>
                                <input type="text" class="form-control" id="ulp-modal-period" disabled readonly>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Остаток занятий</label>
                                <input type="text" class="form-control" id="ulp-modal-balance" disabled readonly>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Тип расписания</label>
                                <input type="text" class="form-control" id="ulp-modal-sched-type" disabled readonly>
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="ulp-modal-fee">Стоимость для ученика</label>
                                <input type="number" class="form-control" id="ulp-modal-fee" step="0.01" min="0" max="999999.99">
                                <div class="invalid-feedback d-block" id="ulp-modal-fee-err"></div>
                            </div>
                        </div>

                        @can('lessonPackages.manualPaid.manage')
                            <div class="mt-3 pt-3 border-top">
                                <label class="form-label" for="ulp-modal-payment-status">Статус оплаты</label>
                                <select class="form-select" id="ulp-modal-payment-status">
                                    <option value="paid">Оплачено</option>
                                    <option value="unpaid">Не оплачено</option>
                                </select>
                                <div id="ulp-modal-payment-comment-wrap" class="mt-3 d-none">
                                    <label class="form-label" for="ulp-modal-payment-comment">Комментарий <span class="text-danger">*</span></label>
                                    <textarea class="form-control" id="ulp-modal-payment-comment" rows="2" maxlength="5000"
                                              placeholder="Обязательно при смене статуса оплаты"></textarea>
                                    <div class="invalid-feedback d-block" id="ulp-modal-payment-comment-err"></div>
                                </div>
                                <div id="ulp-modal-manual-meta" class="form-text text-muted mt-2"></div>
                            </div>
                        @endcan
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-danger d-none" id="ulp-modal-delete">Удалить</button>
                        <div class="ms-auto d-flex gap-2">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Закрыть</button>
                            <button type="button" class="btn btn-primary" id="ulp-modal-save">Сохранить</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="modal fade" id="ulpPayLinkModal" tabindex="-1" aria-labelledby="ulpPayLinkModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="ulpPayLinkModalLabel">Ссылка на оплату через СБП</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
                    </div>
                    <div class="modal-body">
                        <p class="text-muted small mb-2">Отправьте ссылку ученику. Если копирование не сработало автоматически, нажмите «Скопировать» или выделите ссылку вручную (Ctrl+C / Cmd+C).</p>
                        <label class="form-label visually-hidden" for="ulp-pay-link-url">Ссылка на оплату</label>
                        <input type="text" class="form-control font-monospace small" id="ulp-pay-link-url" readonly>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Закрыть</button>
                        <button type="button" class="btn btn-primary" id="ulp-pay-link-copy-btn">
                            <i class="fas fa-copy me-1" aria-hidden="true"></i>Скопировать
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endcan

</div>

@section('scripts')
    @parent
    <script>
        (function () {
            const scheduleSelect = document.getElementById('ulp_lesson_package_id');
            const feeInput = document.getElementById('ulp_fee_amount');
            const hadOldFee = @json(old('fee_amount') !== null && old('fee_amount') !== '');

            function syncFeeFromSelectedPackage() {
                if (!feeInput || !scheduleSelect) return;
                const opt = scheduleSelect.options[scheduleSelect.selectedIndex];
                const cents = opt ? parseInt(opt.getAttribute('data-price-cents') || '0', 10) : 0;
                if (!isNaN(cents) && cents >= 0) {
                    feeInput.value = (cents / 100).toFixed(2);
                }
            }

            scheduleSelect?.addEventListener('change', syncFeeFromSelectedPackage);
            if (!hadOldFee && feeInput && String(feeInput.value).trim() === '') {
                syncFeeFromSelectedPackage();
            }
        })();
    </script>

    <script>
        // Select2 для выбора ученика (если select2 подключён в layout)
        $(document).ready(function () {
            if (!$.fn.select2) return;

            var $ulpUser = $('#ulp_user_id');
            if (!$ulpUser.length) return;

            var ulpUserSelect2 = {
                theme: 'bootstrap-5',
                width: '100%',
                placeholder: 'Выберите ученика',
                language: @include('partials.select2.ru'),
                allowClear: true,
                ajax: {
                    url: @json(route('admin.lesson-packages.assignments.users-search')),
                    dataType: 'json',
                    delay: 250,
                    data: function (params) {
                        return { q: params.term };
                    },
                    processResults: function (data) {
                        return data;
                    }
                }
            };
            var $ulpCreateModal = $('#ulpAssignmentCreateModal');
            if ($ulpCreateModal.length) {
                ulpUserSelect2.dropdownParent = $ulpCreateModal;
            }
            $ulpUser.select2(ulpUserSelect2);

            const oldUserId = @json(old('user_id'));
            if (oldUserId) {
                const option = new Option('Выбранный ученик', oldUserId, true, true);
                $ulpUser.append(option).trigger('change');
            }

            @if ($errors->has('user_id') || $errors->has('lesson_package_id') || $errors->has('fee_amount'))
            if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                const createModalEl = document.getElementById('ulpAssignmentCreateModal');
                if (createModalEl) {
                    bootstrap.Modal.getOrCreateInstance(createModalEl).show();
                }
            }
            @endif
        });
    </script>

    @can('lessonPackages.view')
        <script>
            $(function () {
                function ulpTextRender(data, type) {
                    if (type !== 'display' && type !== 'filter') {
                        return data != null ? data : '';
                    }
                    return window.KidsCrmTooltip.renderText(data != null ? data : '');
                }

                function ulpPaidRender(data, type, row) {
                    if (type !== 'display') {
                        return row.effective_is_paid ? 1 : 0;
                    }

                    var paidInner = row.effective_is_paid
                        ? '<span class="badge bg-success">да</span>'
                        : '<span class="badge bg-secondary">нет</span>';
                    if (row.is_manual_paid !== null && row.is_manual_paid !== undefined) {
                        paidInner += '<div class="small text-muted mt-1">руч.</div>';
                    }

                    var manualNote = row.manual_paid_note != null ? String(row.manual_paid_note).trim() : '';
                    if (row.is_manual_paid !== null && row.is_manual_paid !== undefined && manualNote !== '') {
                        var tooltipPlain = 'Комментарий: ' + manualNote.replace(/\s+/g, ' ');
                        var titleAttr = window.KidsCrmTooltip.escapeHtml(tooltipPlain);
                        return '<span class="ulp-paid-manual-hint d-inline-block text-center" tabindex="0" '
                            + 'aria-label="' + titleAttr + '" '
                            + 'data-bs-toggle="tooltip" data-bs-placement="top" data-bs-custom-class="ulp-assignment-paid-tooltip" '
                            + 'title="' + titleAttr + '">' + paidInner + '</span>';
                    }

                    return '<span class="d-inline-block text-center">' + paidInner + '</span>';
                }

                function ulpPayLinkRender(data, type, row) {
                    if (type !== 'display') {
                        return row.pay_link_available ? 1 : 0;
                    }

                    if (!row.pay_link_available) {
                        return '<span class="text-muted small">—</span>';
                    }

                    return '<button type="button" class="btn btn-sm btn-outline-secondary js-ulp-copy-pay-link" '
                        + 'data-assignment-id="' + window.KidsCrmTooltip.escapeHtml(String(row.id)) + '" '
                        + 'aria-label="Скопировать ссылку на оплату через СБП" '
                        + 'title="Скопировать ссылку на оплату через СБП">'
                        + '<i class="fas fa-copy me-1" aria-hidden="true"></i>Скопировать</button>';
                }

                var dtApi = KidsCrmDataTable.create('#ulp-assignments-table', {
                    dataTable: {
                        pageLength: 20,
                        lengthMenu: [[10, 20, 50, 100], [10, 20, 50, 100]],
                        searching: true,
                        order: [[0, 'desc']],
                        ajax: {
                            url: @json(route('admin.lesson-packages.assignments.data')),
                            type: 'GET'
                        },
                        language: @include('partials.datatables.ru'),
                    },
                    columns: [
                        {
                            key: 'id',
                            type: 'id',
                            data: 'id',
                            name: 'id',
                            visible: false,
                            searchable: false,
                        },
                        {
                            key: 'student',
                            type: 'text',
                            data: 'student',
                            className: 'text-start',
                            render: ulpTextRender,
                        },
                        {
                            key: 'package_name',
                            type: 'text',
                            data: 'package_name',
                            className: 'text-start',
                            render: ulpTextRender,
                        },
                        {
                            key: 'type_label',
                            type: 'text',
                            data: 'type_label',
                            className: 'text-center text-nowrap',
                            render: ulpTextRender,
                        },
                        {
                            key: 'period',
                            type: 'text',
                            data: 'period',
                            className: 'text-center text-nowrap',
                            render: ulpTextRender,
                        },
                        {
                            key: 'fee',
                            type: 'text',
                            data: 'fee',
                            className: 'text-center text-nowrap',
                            render: ulpTextRender,
                        },
                        {
                            key: 'paid',
                            type: 'badge',
                            data: 'effective_is_paid',
                            name: 'paid',
                            className: 'dt-col-badge text-center',
                            render: ulpPaidRender,
                        },
                        {
                            key: 'balance',
                            type: 'text',
                            data: 'balance',
                            className: 'text-center text-nowrap',
                            render: ulpTextRender,
                        },
                        {
                            key: 'pay_link',
                            type: 'text',
                            data: 'pay_link_available',
                            name: 'pay_link',
                            orderable: false,
                            searchable: false,
                            className: 'text-start text-nowrap',
                            render: ulpPayLinkRender,
                        },
                        {
                            key: 'actions',
                            type: 'actions',
                            className: 'text-start text-nowrap',
                            render: function (data, type, row) {
                                if (type !== 'display') {
                                    return '';
                                }

                                return '<button type="button" class="btn btn-sm btn-outline-primary js-ulp-assignment-edit" '
                                    + 'data-assignment-id="' + window.KidsCrmTooltip.escapeHtml(String(row.id)) + '">Изменить</button>';
                            },
                        },
                    ],
                });

                const assignmentsBase = @json(rtrim(route('admin.lesson-packages.assignments'), '/'));
                const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
                const modalEl = document.getElementById('ulpAssignmentEditModal');
                if (!modalEl || typeof bootstrap === 'undefined' || !bootstrap.Modal) {
                    return;
                }
                const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
                let currentId = null;

                const alertBox = document.getElementById('ulp-modal-alert');
                const feeErr = document.getElementById('ulp-modal-fee-err');
                const paymentCommentErr = document.getElementById('ulp-modal-payment-comment-err');
                const paymentStatusSel = document.getElementById('ulp-modal-payment-status');
                const paymentCommentEl = document.getElementById('ulp-modal-payment-comment');
                const paymentCommentWrap = document.getElementById('ulp-modal-payment-comment-wrap');
                const deleteBtn = document.getElementById('ulp-modal-delete');

                function showErr(msg) {
                    if (!alertBox) return;
                    alertBox.textContent = msg || '';
                    alertBox.classList.toggle('d-none', !msg);
                }

                function clearErrors() {
                    showErr('');
                    if (feeErr) feeErr.textContent = '';
                    if (paymentCommentErr) paymentCommentErr.textContent = '';
                }

                function syncPaymentCommentVisibility() {
                    if (!paymentStatusSel || !paymentCommentWrap) return;
                    const initial = paymentStatusSel.dataset.initial || '';
                    const changed = paymentStatusSel.value !== initial;
                    paymentCommentWrap.classList.toggle('d-none', !changed);
                    if (!changed && paymentCommentEl) {
                        paymentCommentEl.value = '';
                    }
                }

                function fillModal(data) {
                    const a = data.assignment;
                    currentId = String(a.id);
                    document.getElementById('ulp-modal-user').value = a.user_display || '';
                    document.getElementById('ulp-modal-package').value = a.lesson_package_name || '';
                    document.getElementById('ulp-modal-period').value = a.period_display || '';
                    document.getElementById('ulp-modal-balance').value =
                        String(a.lessons_remaining) + ' / ' + String(a.lessons_total);
                    document.getElementById('ulp-modal-sched-type').value = a.schedule_type_label || '';

                    const feeInput = document.getElementById('ulp-modal-fee');
                    const saveBtn = document.getElementById('ulp-modal-save');
                    feeInput.value = a.fee_amount || '';
                    feeInput.disabled = !a.fee_editable;
                    if (saveBtn) {
                        saveBtn.disabled = false;
                    }

                    const metaEl = document.getElementById('ulp-modal-manual-meta');
                    if (metaEl) {
                        const parts = [];
                        if (a.manual_paid_note) {
                            parts.push('Последний комментарий: ' + a.manual_paid_note);
                        }
                        if (a.manual_paid_at) {
                            parts.push('Когда: ' + a.manual_paid_at);
                        }
                        if (a.manual_paid_by_display) {
                            parts.push('Кем: ' + a.manual_paid_by_display);
                        }
                        metaEl.textContent = parts.length ? parts.join(' · ') : '';
                    }

                    if (paymentStatusSel) {
                        const init = a.effective_is_paid ? 'paid' : 'unpaid';
                        paymentStatusSel.value = init;
                        paymentStatusSel.dataset.initial = init;
                        paymentStatusSel.disabled = false;
                    }
                    if (paymentCommentEl) {
                        paymentCommentEl.value = '';
                    }
                    syncPaymentCommentVisibility();

                    if (deleteBtn) {
                        deleteBtn.classList.toggle('d-none', !a.can_delete);
                        deleteBtn.disabled = !a.can_delete;
                    }
                }

                paymentStatusSel?.addEventListener('change', syncPaymentCommentVisibility);

                async function fetchJson(url, options) {
                    const headers = Object.assign({
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': csrf,
                    }, options.headers || {});
                    if (options.body && !(options.body instanceof FormData) && !headers['Content-Type']) {
                        headers['Content-Type'] = 'application/json';
                    }
                    const r = await fetch(url, Object.assign({ credentials: 'same-origin' }, options, { headers: headers }));
                    const ct = r.headers.get('content-type') || '';
                    const payload = ct.includes('application/json') ? await r.json() : {};
                    return { ok: r.ok, status: r.status, payload: payload };
                }

                $(document).on('click', '.js-ulp-assignment-edit', async function (e) {
                    e.preventDefault();
                    const id = $(this).attr('data-assignment-id');
                    if (!id) return;
                    clearErrors();
                    try {
                        const { ok, status, payload } = await fetchJson(assignmentsBase + '/' + id, { method: 'GET' });
                        if (!ok) {
                            currentId = null;
                            if (deleteBtn) {
                                deleteBtn.classList.add('d-none');
                            }
                            showErr((payload && payload.message) || ('Ошибка загрузки (' + status + ')'));
                            modal.show();
                            return;
                        }
                        fillModal(payload);
                        modal.show();
                    } catch (err) {
                        currentId = null;
                        if (deleteBtn) {
                            deleteBtn.classList.add('d-none');
                        }
                        showErr('Не удалось загрузить назначение.');
                        modal.show();
                    }
                });

                document.getElementById('ulp-modal-save')?.addEventListener('click', async function () {
                    if (!currentId) return;
                    clearErrors();
                    const feeInput = document.getElementById('ulp-modal-fee');
                    const body = { fee_amount: feeInput.value };

                    if (paymentStatusSel) {
                        body.payment_status = paymentStatusSel.value;
                        const initial = paymentStatusSel.dataset.initial || '';
                        if (paymentStatusSel.value !== initial) {
                            body.payment_comment = paymentCommentEl ? paymentCommentEl.value.trim() : '';
                        }
                    }

                    const { ok, status, payload } = await fetchJson(assignmentsBase + '/' + currentId, {
                        method: 'PUT',
                        body: JSON.stringify(body),
                    });
                    if (!ok) {
                        if (payload.errors && payload.errors.fee_amount) {
                            feeErr.textContent = Array.isArray(payload.errors.fee_amount)
                                ? payload.errors.fee_amount[0]
                                : String(payload.errors.fee_amount);
                        }
                        if (payload.errors && payload.errors.payment_comment && paymentCommentErr) {
                            paymentCommentErr.textContent = Array.isArray(payload.errors.payment_comment)
                                ? payload.errors.payment_comment[0]
                                : String(payload.errors.payment_comment);
                        }
                        if (payload.errors && payload.errors.payment_status) {
                            const ps = Array.isArray(payload.errors.payment_status)
                                ? payload.errors.payment_status[0]
                                : String(payload.errors.payment_status);
                            if (paymentCommentErr) {
                                paymentCommentErr.textContent = ps;
                            } else {
                                showErr(ps);
                            }
                        }
                        if (!payload.errors || (!payload.errors.fee_amount && !payload.errors.payment_comment && !payload.errors.payment_status)) {
                            showErr((payload && payload.message) || ('Не удалось сохранить (' + status + ')'));
                        }
                        return;
                    }
                    dtApi.reload({ keepPage: true });
                    modal.hide();
                });

                deleteBtn?.addEventListener('click', async function () {
                    if (!currentId) {
                        return;
                    }
                    if (!confirm('Удалить это назначение абонемента? Связанные слоты и заморозки будут удалены.')) {
                        return;
                    }
                    const { ok, payload } = await fetchJson(assignmentsBase + '/' + currentId, { method: 'DELETE' });
                    if (!ok) {
                        window.alert((payload && payload.message) || 'Не удалось удалить.');
                        return;
                    }
                    dtApi.reload({ keepPage: true });
                    modal.hide();
                });

                modalEl.addEventListener('hidden.bs.modal', function () {
                    clearErrors();
                    currentId = null;
                    if (deleteBtn) {
                        deleteBtn.classList.add('d-none');
                    }
                });

                const copyToast = document.getElementById('ulp-copy-pay-toast');
                const payLinkModalEl = document.getElementById('ulpPayLinkModal');
                const payLinkInputEl = document.getElementById('ulp-pay-link-url');
                const payLinkCopyBtn = document.getElementById('ulp-pay-link-copy-btn');
                const payLinkModal = payLinkModalEl && typeof bootstrap !== 'undefined' && bootstrap.Modal
                    ? bootstrap.Modal.getOrCreateInstance(payLinkModalEl)
                    : null;

                function showCopyToast() {
                    if (!copyToast) return;
                    copyToast.classList.remove('d-none');
                    clearTimeout(copyToast._t);
                    copyToast._t = setTimeout(function () {
                        copyToast.classList.add('d-none');
                    }, 3200);
                }

                function copyViaTextarea(text) {
                    try {
                        const ta = document.createElement('textarea');
                        ta.value = text;
                        ta.setAttribute('readonly', '');
                        ta.style.position = 'fixed';
                        ta.style.left = '-9999px';
                        ta.style.top = '0';
                        document.body.appendChild(ta);
                        ta.focus();
                        ta.select();
                        ta.setSelectionRange(0, text.length);
                        const ok = document.execCommand('copy');
                        document.body.removeChild(ta);
                        return !!ok;
                    } catch (err) {
                        return false;
                    }
                }

                function copyTextToClipboard(text) {
                    if (navigator.clipboard && typeof navigator.clipboard.writeText === 'function') {
                        return navigator.clipboard.writeText(text).then(function () {
                            return true;
                        }).catch(function () {
                            return copyViaTextarea(text);
                        });
                    }
                    return Promise.resolve(copyViaTextarea(text));
                }

                function selectPayLinkInput() {
                    if (!payLinkInputEl) return;
                    payLinkInputEl.focus();
                    payLinkInputEl.select();
                    if (typeof payLinkInputEl.setSelectionRange === 'function') {
                        payLinkInputEl.setSelectionRange(0, payLinkInputEl.value.length);
                    }
                }

                function showPayLinkModal(url) {
                    if (!payLinkModal || !payLinkInputEl) {
                        window.prompt('Ссылка на оплату:', url);
                        return;
                    }
                    payLinkInputEl.value = url;
                    payLinkModal.show();
                }

                if (payLinkModalEl) {
                    payLinkModalEl.addEventListener('shown.bs.modal', selectPayLinkInput);
                }

                payLinkCopyBtn?.addEventListener('click', function () {
                    const url = payLinkInputEl?.value || '';
                    if (!url) return;
                    copyTextToClipboard(url).then(function (copied) {
                        if (copied) {
                            showCopyToast();
                            payLinkModal?.hide();
                            return;
                        }
                        selectPayLinkInput();
                    });
                });

                $(document).on('click', '.js-ulp-copy-pay-link', async function (e) {
                    e.preventDefault();
                    const btn = $(this);
                    const id = btn.attr('data-assignment-id');
                    if (!id) return;
                    const prevHtml = btn.html();
                    btn.prop('disabled', true);
                    try {
                        const { ok, status, payload } = await fetchJson(assignmentsBase + '/' + id + '/public-pay-link', {
                            method: 'POST',
                            body: '{}',
                        });
                        if (!ok || !payload.url) {
                            window.alert((payload && payload.message) || ('Не удалось получить ссылку (' + status + ')'));
                            return;
                        }
                        const copied = await copyTextToClipboard(payload.url);
                        if (copied) {
                            showCopyToast();
                        } else {
                            showPayLinkModal(payload.url);
                        }
                    } catch (err) {
                        window.alert('Не удалось получить ссылку. Проверьте соединение и попробуйте снова.');
                    } finally {
                        btn.prop('disabled', false);
                        btn.html(prevHtml);
                    }
                });
            });
        </script>
    @endcan
@endsection

