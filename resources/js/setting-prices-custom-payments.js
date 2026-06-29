(function () {
    function csrf() {
        return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    }

    function setFieldErrors(errors) {
        document.querySelectorAll('.custom-payment-field-error').forEach(function (el) {
            el.style.display = 'none';
            el.textContent = '';
        });

        if (!errors) return;

        Object.keys(errors).forEach(function (field) {
            var msg = (errors[field] && errors[field][0]) ? errors[field][0] : null;
            if (!msg) return;
            var el = document.querySelector('.custom-payment-field-error[data-field="' + field + '"]');
            if (!el) return;
            el.textContent = msg;
            el.style.display = 'block';
        });
    }

    function toast(msg, isError) {
        if (typeof window.bootstrap === 'undefined' || !bootstrap.Toast) {
            alert(msg || (isError ? 'Ошибка' : 'OK'));
            return;
        }

        var wrapper = document.querySelector('.position-fixed.bottom-0.end-0.p-3');
        if (!wrapper) {
            alert(msg || (isError ? 'Ошибка' : 'OK'));
            return;
        }

        var toastEl = document.getElementById('priceToast');
        var bodyEl = document.getElementById('priceToastBody');
        if (!toastEl || !bodyEl) {
            alert(msg || (isError ? 'Ошибка' : 'OK'));
            return;
        }

        bodyEl.textContent = msg || (isError ? 'Ошибка' : 'OK');
        toastEl.classList.remove('bg-success', 'bg-danger');
        toastEl.classList.add(isError ? 'bg-danger' : 'bg-success');
        new bootstrap.Toast(toastEl).show();
    }

    document.addEventListener('DOMContentLoaded', function () {
        if (window.$) {
            $.ajaxSetup({
                headers: {
                    'X-CSRF-TOKEN': csrf(),
                    'Accept': 'application/json'
                }
            });
        }

        var dtApi = null;
        if (window.$ && window.KidsCrmDataTable && $.fn && $.fn.DataTable) {
            dtApi = KidsCrmDataTable.create('#custom-payments-table', {
                dataTable: {
                    ajax: {
                        url: '/admin/setting-prices/custom-payments/data',
                        type: 'GET'
                    },
                    order: [[0, 'desc']],
                    language: window.__kidsDatatableRu || {
                        processing: 'Обработка...',
                        search: '',
                        searchPlaceholder: 'Поиск...',
                        lengthMenu: 'Показать _MENU_',
                        info: 'С _START_ до _END_ из _TOTAL_ записей',
                        infoEmpty: 'С 0 до 0 из 0 записей',
                        infoFiltered: '(отфильтровано из _MAX_ записей)',
                        loadingRecords: 'Загрузка записей...',
                        zeroRecords: 'Записи отсутствуют.',
                        emptyTable: 'В таблице отсутствуют данные',
                        paginate: { first: '', previous: '', next: '', last: '' },
                        aria: {
                            sortAscending: ': активировать для сортировки столбца по возрастанию',
                            sortDescending: ': активировать для сортировки столбца по убыванию'
                        }
                    }
                },
                columns: [
                    { key: 'id', type: 'id' },
                    { key: 'user_name', type: 'text', data: 'user_name' },
                    { key: 'team_label', type: 'text', data: 'team_label', orderable: false },
                    {
                        key: 'period',
                        type: 'text',
                        data: 'period',
                        orderable: false,
                        searchable: false,
                    },
                    { key: 'amount', type: 'money', data: 'amount' },
                    {
                        key: 'note',
                        type: 'text',
                        data: 'note',
                        orderable: false,
                        render: function (data, type) {
                            if (type !== 'display') {
                                return data || '';
                            }
                            if (data == null || data === '') {
                                return '<span class="text-muted">—</span>';
                            }
                            return window.KidsCrmTooltip.renderText(data);
                        },
                    },
                    {
                        key: 'status',
                        type: 'badge',
                        data: 'status_label',
                        name: 'status',
                        className: 'dt-col-badge text-center',
                        orderable: false,
                        searchable: false,
                        badgeKey: 'effective_is_paid',
                        render: function (value, type, row) {
                            if (type !== 'display') {
                                return value || '';
                            }

                            var paid = !!row.effective_is_paid;
                            var badgeClass = paid ? 'bg-success' : 'bg-secondary';
                            var badgeText = value || (paid ? 'Оплачено' : 'Не оплачено');
                            var infoIcon = '';

                            if (row.is_manual_paid !== null && row.is_manual_paid !== undefined) {
                                var note = row.manual_paid_note != null ? String(row.manual_paid_note).trim() : '';
                                var hintTitle = note !== ''
                                    ? note
                                    : 'Комментарий к ручному изменению не заполнен.';
                                infoIcon = '<i class="fa fa-info-circle user-manual-info-icon" tabindex="0" '
                                    + 'data-bs-toggle="tooltip" data-bs-placement="top" data-bs-custom-class="ulp-assignment-paid-tooltip" '
                                    + 'title="' + hintTitle.replace(/"/g, '&quot;') + '" '
                                    + 'aria-label="Комментарий к ручной отметке оплаты"></i>';
                            }

                            return '<div class="setting-prices-monthly-status-view d-flex align-items-center flex-nowrap gap-1">'
                                + '<div class="setting-prices-monthly-badge-wrap position-relative">'
                                + '<span class="badge ' + badgeClass + '">' + badgeText + '</span>'
                                + infoIcon
                                + '</div>'
                                + '</div>';
                        },
                    },
                    {
                        key: 'actions',
                        type: 'actions',
                        className: 'text-nowrap',
                        render: function (data, type, row) {
                            if (type !== 'display' || !window.__customPaymentsCanManualPaid) {
                                return '';
                            }

                            var paid = !!row.effective_is_paid;
                            var id = String(row.id);
                            var btnPaid = '<button type="button" class="btn btn-sm btn-outline-success me-1" data-custom-payment-action="mark_paid" data-id="'
                                + id + '">Оплачено</button>';
                            var btnUnpaid = '<button type="button" class="btn btn-sm btn-outline-secondary" data-custom-payment-action="mark_unpaid" data-id="'
                                + id + '">Не оплачено</button>';

                            return paid ? btnUnpaid : (btnPaid + btnUnpaid);
                        },
                    },
                ],
            });
        }

        var form = document.getElementById('custom-payment-create-form');
        if (form) {
            form.addEventListener('submit', function (e) {
                e.preventDefault();
                setFieldErrors(null);

                var payload = {
                    user_id: form.querySelector('[name="user_id"]').value,
                    team_id: form.querySelector('[name="team_id"]').value,
                    date_start: form.querySelector('[name="date_start"]').value,
                    date_end: form.querySelector('[name="date_end"]').value,
                    amount: form.querySelector('[name="amount"]').value,
                    note: form.querySelector('[name="note"]').value,
                };

                var btn = document.getElementById('custom-payment-create-submit');
                if (btn) btn.disabled = true;

                fetch('/admin/setting-prices/custom-payments', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrf()
                    },
                    body: JSON.stringify(payload)
                })
                    .then(async function (res) {
                        var data = null;
                        try {
                            data = await res.json();
                        } catch (err) {}
                        if (!res.ok) {
                            if (data && data.errors) {
                                setFieldErrors(data.errors);
                            }
                            throw new Error((data && data.message) ? data.message : 'Не удалось создать дополнительный платеж.');
                        }
                        return data;
                    })
                    .then(function (data) {
                        toast('Дополнительный платеж сохранен.', false);
                        form.reset();
                        if (window.$ && $('#custom-payment-user-id').length) {
                            $('#custom-payment-user-id').val(null).trigger('change');
                        }
                        if (window.$ && $('#custom-payment-team-id').length) {
                            $('#custom-payment-team-id').val(null).trigger('change').prop('disabled', true);
                        }
                        var modalEl = document.getElementById('customPaymentCreateModal');
                        if (modalEl && window.bootstrap && bootstrap.Modal) {
                            bootstrap.Modal.getOrCreateInstance(modalEl).hide();
                        }
                        if (dtApi) {
                            dtApi.reload({ keepPage: true });
                        }
                    })
                    .catch(function (err) {
                        toast(err && err.message ? err.message : 'Ошибка', true);
                    })
                    .finally(function () {
                        if (btn) btn.disabled = false;
                    });
            });
        }

        document.addEventListener('click', function (e) {
            var btn = e.target.closest('[data-custom-payment-action]');
            if (!btn) return;

            var action = btn.getAttribute('data-custom-payment-action');
            var id = btn.getAttribute('data-id');
            if (!id) return;

            var mode = action === 'mark_paid' ? 'paid' : 'unpaid';
            var labelWant = mode === 'paid' ? 'оплачено' : 'не оплачено';

            if (typeof window.showManualPaidCommentModal !== 'function') {
                toast('Не загружена форма подтверждения. Обновите страницу.', true);
                return;
            }

            window.showManualPaidCommentModal(
                'Подтверждение',
                'Будет установлен статус: «' + labelWant + '». Укажите комментарий.',
                function (comment) {
                    fetch('/admin/setting-prices/custom-payments/' + id + '/manual-paid', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrf()
                        },
                        body: JSON.stringify({
                            mode: mode,
                            comment: comment
                        })
                    })
                        .then(async function (res) {
                            var data = null;
                            try { data = await res.json(); } catch (e) {}
                            if (!res.ok) {
                                throw new Error((data && data.message) ? data.message : 'Не удалось обновить статус оплаты.');
                            }
                            return data;
                        })
                        .then(function () {
                            toast('Статус оплаты обновлен.', false);
                            if (dtApi) {
                                dtApi.reload({ keepPage: true });
                            }
                        })
                        .catch(function (err) {
                            toast(err && err.message ? err.message : 'Ошибка', true);
                        });
                }
            );
        });
    });
})();
