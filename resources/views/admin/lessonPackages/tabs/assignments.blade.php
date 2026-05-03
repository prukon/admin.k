<div class="tab-content">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 pt-3">
        <h4 class="mb-0">Назначение абонементов</h4>
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
        <div class="card mb-3">
            <div class="card-body">
                <form method="POST" action="{{ route('admin.lesson-packages.assignments.store') }}" novalidate>
                    @csrf

                    <div class="row g-3 align-items-end">
                        <div class="col-12 col-md-4">
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

                        <div class="col-12 col-md-4">
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

                        <div class="col-12 col-md-2">
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

                        <div class="col-12 col-md-2">
                            <button type="submit" class="btn btn-primary w-100">
                                Назначить
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    @endcan

    <div class="table-responsive">
        <table class="table table-striped table-bordered align-middle w-100">
            <thead>
            <tr>
                <th>Ученик</th>
                <th>Абонемент</th>
                <th>Период</th>
                <th>Сумма</th>
                <th>Оплачен</th>
                <th>Остаток</th>
                <th>Тип</th>
                @can('lessonPackages.view')
                    <th class="text-start text-nowrap">Ссылка на оплату</th>
                    <th class="text-start" style="min-width: 200px;">Действия</th>
                @endcan
            </tr>
            </thead>
            <tbody>
            @forelse ($assignments as $a)
                <tr>
                    <td>{{ trim(($a->user->lastname ?? '').' '.($a->user->name ?? '')) }}</td>
                    <td>{{ $a->lessonPackage->name ?? '—' }}</td>
                    <td class="text-center">
                        @if ($a->starts_at && $a->ends_at)
                            {{ $a->starts_at->locale('ru')->isoFormat('D MMMM YYYY') }}
                            —
                            {{ $a->ends_at->locale('ru')->isoFormat('D MMMM YYYY') }}
                        @else
                            —
                        @endif
                    </td>
                    <td class="text-center">{{ (int) round((float) ($a->fee_amount ?? 0)) }}</td>
                    <td class="text-center">
                        @if ($a->effective_is_paid)
                            <span class="badge bg-success">да</span>
                        @else
                            <span class="badge bg-secondary">нет</span>
                        @endif
                        @if ($a->is_manual_paid !== null)
                            <div class="small text-muted mt-1">руч.</div>
                        @endif
                    </td>
                    <td class="text-center">{{ $a->lessons_remaining }} / {{ $a->lessons_total }}</td>
                    <td class="text-center">
                        @php
                            $t = (string) ($a->lessonPackage->schedule_type ?? '');
                            echo match ($t) {
                                'fixed' => 'Фиксированное расписание',
                                'flexible' => 'Гибкий абонемент',
                                'no_schedule' => 'Разовое занятие',
                                default => 'Абонемент',
                            };
                        @endphp
                    </td>
                    @can('lessonPackages.view')
                        <td class="text-start text-nowrap">
                            @if (!empty($ulpPublicPayTbankReady ?? false) && ! $a->effective_is_paid && (float) ($a->fee_amount ?? 0) >= 10)
                                <button type="button"
                                        class="btn btn-sm btn-outline-secondary js-ulp-copy-pay-link"
                                        data-assignment-id="{{ (int) $a->id }}"
                                        aria-label="Скопировать ссылку на оплату через СБП"
                                        title="Скопировать ссылку на оплату через СБП">
                                    <i class="fas fa-copy me-1" aria-hidden="true"></i>
                                    Копировать ссылку
                                </button>
                            @else
                                <span class="text-muted small">—</span>
                            @endif
                        </td>
                        <td class="text-start text-nowrap">
                            <button type="button"
                                    class="btn btn-sm btn-outline-primary js-ulp-assignment-edit"
                                    data-assignment-id="{{ (int) $a->id }}">
                                Изменить
                            </button>
                            @if ((int) $a->lessons_remaining === (int) $a->lessons_total)
                                <button type="button"
                                        class="btn btn-sm btn-outline-danger ms-1 js-ulp-assignment-delete"
                                        data-assignment-id="{{ (int) $a->id }}">
                                    Удалить
                                </button>
                            @endif
                        </td>
                    @endcan
                </tr>
            @empty
                <tr>
                    <td colspan="{{ auth()->user()?->can('lessonPackages.view') ? 9 : 7 }}" class="text-center text-muted">Назначений пока нет.</td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>

    @can('lessonPackages.view')
        <div class="modal fade" id="ulpAssignmentEditModal" tabindex="-1" aria-labelledby="ulpAssignmentEditModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable" style="max-width: 440px;">
                <div class="modal-content">
                    <div class="modal-header py-2">
                        <h5 class="modal-title fs-6" id="ulpAssignmentEditModalLabel">Изменение абонемента</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
                    </div>
                    <div class="modal-body py-2 px-3">
                        <div id="ulp-modal-alert" class="alert alert-danger py-2 px-2 small d-none" role="alert"></div>

                        <div class="row g-2">
                            <div class="col-12">
                                <label class="form-label small text-muted mb-0">Ученик</label>
                                <input type="text" class="form-control form-control-sm" id="ulp-modal-user" disabled readonly>
                            </div>
                            <div class="col-12">
                                <label class="form-label small text-muted mb-0">Абонемент</label>
                                <input type="text" class="form-control form-control-sm" id="ulp-modal-package" disabled readonly>
                            </div>
                            <div class="col-12">
                                <label class="form-label small text-muted mb-0">Период</label>
                                <input type="text" class="form-control form-control-sm" id="ulp-modal-period" disabled readonly>
                            </div>
                            <div class="col-6">
                                <label class="form-label small text-muted mb-0">Остаток занятий</label>
                                <input type="text" class="form-control form-control-sm" id="ulp-modal-balance" disabled readonly>
                            </div>
                            <div class="col-6">
                                <label class="form-label small text-muted mb-0">Тип расписания</label>
                                <input type="text" class="form-control form-control-sm" id="ulp-modal-sched-type" disabled readonly>
                            </div>
                            <div class="col-12">
                                <label class="form-label small mb-0" for="ulp-modal-fee">Стоимость для ученика</label>
                                <input type="number" class="form-control form-control-sm" id="ulp-modal-fee" step="0.01" min="0" max="999999.99">
                                <div class="invalid-feedback d-block" id="ulp-modal-fee-err"></div>
                            </div>
                        </div>

                        @can('lessonPackages.manualPaid.manage')
                            <div class="mt-2 pt-2 border-top">
                                <label class="form-label small mb-0" for="ulp-modal-payment-status">Статус оплаты</label>
                                <select class="form-select form-select-sm" id="ulp-modal-payment-status">
                                    <option value="paid">Оплачено</option>
                                    <option value="unpaid">Не оплачено</option>
                                </select>
                                <div id="ulp-modal-payment-comment-wrap" class="mt-2 d-none">
                                    <label class="form-label small mb-0" for="ulp-modal-payment-comment">Комментарий <span class="text-danger">*</span></label>
                                    <textarea class="form-control form-control-sm" id="ulp-modal-payment-comment" rows="2" maxlength="5000"
                                              placeholder="Обязательно при смене статуса оплаты"></textarea>
                                    <div class="invalid-feedback d-block" id="ulp-modal-payment-comment-err"></div>
                                </div>
                                <div id="ulp-modal-manual-meta" class="small text-muted mt-2"></div>
                            </div>
                        @endcan
                    </div>
                    <div class="modal-footer py-2 px-3">
                        <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Закрыть</button>
                        <button type="button" class="btn btn-sm btn-primary" id="ulp-modal-save">Сохранить</button>
                    </div>
                </div>
            </div>
        </div>
    @endcan

    <div class="d-flex justify-content-center">
        {{ $assignments->links() }}
    </div>
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

            $('#ulp_user_id').select2({
                theme: 'bootstrap-5',
                width: '100%',
                placeholder: 'Выберите ученика',
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
            });

            const oldUserId = @json(old('user_id'));
            if (oldUserId) {
                const option = new Option('Выбранный ученик', oldUserId, true, true);
                $('#ulp_user_id').append(option).trigger('change');
            }
        });
    </script>

    @can('lessonPackages.view')
        <script>
            (function () {
                const assignmentsBase = @json(rtrim(route('admin.lesson-packages.assignments'), '/'));
                const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
                const modalEl = document.getElementById('ulpAssignmentEditModal');
                if (!modalEl || typeof bootstrap === 'undefined' || !bootstrap.Modal) {
                    return;
                }
                const modal = new bootstrap.Modal(modalEl);
                let currentId = null;

                const alertBox = document.getElementById('ulp-modal-alert');
                const feeErr = document.getElementById('ulp-modal-fee-err');
                const paymentCommentErr = document.getElementById('ulp-modal-payment-comment-err');
                const paymentStatusSel = document.getElementById('ulp-modal-payment-status');
                const paymentCommentEl = document.getElementById('ulp-modal-payment-comment');
                const paymentCommentWrap = document.getElementById('ulp-modal-payment-comment-wrap');

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

                document.querySelectorAll('.js-ulp-assignment-edit').forEach(function (btn) {
                    btn.addEventListener('click', async function () {
                        const id = btn.getAttribute('data-assignment-id');
                        if (!id) return;
                        clearErrors();
                        try {
                            const { ok, status, payload } = await fetchJson(assignmentsBase + '/' + id, { method: 'GET' });
                            if (!ok) {
                                showErr((payload && payload.message) || ('Ошибка загрузки (' + status + ')'));
                                modal.show();
                                return;
                            }
                            fillModal(payload);
                            modal.show();
                        } catch (e) {
                            showErr('Не удалось загрузить назначение.');
                            modal.show();
                        }
                    });
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
                    window.location.reload();
                });

                document.querySelectorAll('.js-ulp-assignment-delete').forEach(function (btn) {
                    btn.addEventListener('click', async function () {
                        const id = btn.getAttribute('data-assignment-id');
                        if (!id || !confirm('Удалить это назначение абонемента? Связанные слоты и заморозки будут удалены.')) {
                            return;
                        }
                        const { ok, payload } = await fetchJson(assignmentsBase + '/' + id, { method: 'DELETE' });
                        if (!ok) {
                            alert((payload && payload.message) || 'Не удалось удалить.');
                            return;
                        }
                        window.location.reload();
                    });
                });

                modalEl.addEventListener('hidden.bs.modal', function () {
                    clearErrors();
                });

                const copyToast = document.getElementById('ulp-copy-pay-toast');
                function showCopyToast() {
                    if (!copyToast) return;
                    copyToast.classList.remove('d-none');
                    clearTimeout(copyToast._t);
                    copyToast._t = setTimeout(function () {
                        copyToast.classList.add('d-none');
                    }, 3200);
                }

                document.querySelectorAll('.js-ulp-copy-pay-link').forEach(function (btn) {
                    btn.addEventListener('click', async function () {
                        const id = btn.getAttribute('data-assignment-id');
                        if (!id) return;
                        const prevHtml = btn.innerHTML;
                        btn.disabled = true;
                        try {
                            const { ok, status, payload } = await fetchJson(assignmentsBase + '/' + id + '/public-pay-link', {
                                method: 'POST',
                                body: '{}',
                            });
                            if (!ok || !payload.url) {
                                window.alert((payload && payload.message) || ('Не удалось получить ссылку (' + status + ')'));
                                return;
                            }
                            await navigator.clipboard.writeText(payload.url);
                            showCopyToast();
                        } catch (e) {
                            window.alert('Не удалось скопировать ссылку.');
                        } finally {
                            btn.disabled = false;
                            btn.innerHTML = prevHtml;
                        }
                    });
                });
            })();
        </script>
    @endcan
@endsection

