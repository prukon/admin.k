(function () {
    function csrf() {
        return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    }

    function setFieldErrors(errors) {
        document.querySelectorAll('.abonement-field-error').forEach(function (el) {
            el.style.display = 'none';
            el.textContent = '';
        });

        if (!errors) return;

        Object.keys(errors).forEach(function (field) {
            var msg = (errors[field] && errors[field][0]) ? errors[field][0] : null;
            if (!msg) return;
            var el = document.querySelector('.abonement-field-error[data-field="' + field + '"]');
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

        // используем существующий toast из вкладки users (если он есть), иначе fallback alert
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

    function paidBadge(effectivePaid, note) {
        var cls = effectivePaid ? 'bg-success' : 'bg-secondary';
        var txt = effectivePaid ? 'Оплачено' : 'Не оплачено';
        var title = note ? String(note) : '';
        return '<span class="badge ' + cls + '" title="' + title.replace(/"/g, '&quot;') + '">' + txt + '</span>';
    }

    document.addEventListener('DOMContentLoaded', function () {
        // ensure global ajax header for JSON posts
        if (window.$) {
            $.ajaxSetup({
                headers: {
                    'X-CSRF-TOKEN': csrf(),
                    'Accept': 'application/json'
                }
            });
        }

        // select2 user search
        if (window.$ && $.fn && $.fn.select2) {
            var $userSelect = $('#abonement-user-id');
            if ($userSelect.length) {
                $userSelect.select2({
                    theme: 'bootstrap-5',
                    width: '100%',
                    placeholder: $userSelect.find('option:first').text() || 'Выберите ученика',
                    allowClear: true,
                    dropdownParent: $('#abonementCreateModal'),
                    ajax: {
                        url: '/admin/setting-prices/abonements/users-search',
                        delay: 250,
                        data: function (params) {
                            return {q: params.term || ''};
                        },
                        processResults: function (data) {
                            return data;
                        }
                    },
                    minimumInputLength: 0
                });
            }
        }

        var table = null;
        if (window.$ && $.fn && $.fn.DataTable) {
            table = $('#abonements-table').DataTable({
                processing: true,
                serverSide: true,
                ajax: {
                    url: '/admin/setting-prices/abonements/data',
                    type: 'GET'
                },
                columns: [
                    {data: 'id', name: 'id'},
                    {data: 'user_name', name: 'user_name'},
                    {data: 'period', name: 'period', orderable: false, searchable: false},
                    {
                        data: 'amount',
                        name: 'amount',
                        render: function (data, type, row) {
                            var v = parseInt(String(data || '0').replace(/[^\d]/g, ''), 10) || 0;
                            if (type === 'display') {
                                return v.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ' ') + ' руб';
                            }
                            return v;
                        }
                    },
                    {data: 'status', name: 'status', orderable: false, searchable: false},
                    {data: 'actions', name: 'actions', orderable: false, searchable: false},
                ],
                order: [[0, 'desc']],
                scrollX: true,
                language: {
                    processing: "Обработка...",
                    search: "",
                    searchPlaceholder: "Поиск...",
                    lengthMenu: "Показать _MENU_",
                    info: "С _START_ до _END_ из _TOTAL_ записей",
                    infoEmpty: "С 0 до 0 из 0 записей",
                    infoFiltered: "(отфильтровано из _MAX_ записей)",
                    loadingRecords: "Загрузка записей...",
                    zeroRecords: "Записи отсутствуют.",
                    emptyTable: "В таблице отсутствуют данные",
                    paginate: {first: "", previous: "", next: "", last: ""},
                    aria: {
                        sortAscending: ": активировать для сортировки столбца по возрастанию",
                        sortDescending: ": активировать для сортировки столбца по убыванию"
                    }
                }
            });
        }

        var reloadBtn = document.getElementById('abonement-reload');
        if (reloadBtn) {
            reloadBtn.addEventListener('click', function () {
                if (table) table.ajax.reload(null, false);
            });
        }

        var form = document.getElementById('abonement-create-form');
        if (form) {
            form.addEventListener('submit', function (e) {
                e.preventDefault();

                setFieldErrors(null);

                var payload = {
                    user_id: form.querySelector('[name="user_id"]').value,
                    date_start: form.querySelector('[name="date_start"]').value,
                    date_end: form.querySelector('[name="date_end"]').value,
                    amount: form.querySelector('[name="amount"]').value,
                    note: form.querySelector('[name="note"]').value,
                };

                var btn = document.getElementById('abonement-create-submit');
                if (btn) btn.disabled = true;

                fetch('/admin/setting-prices/abonements', {
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
                        } catch (err) {
                            // ignore
                        }
                        if (!res.ok) {
                            if (data && data.errors) {
                                setFieldErrors(data.errors);
                            }
                            throw new Error((data && data.message) ? data.message : 'Не удалось создать абонемент.');
                        }
                        return data;
                    })
                    .then(function (data) {
                        toast('Абонемент сохранён.', false);
                        form.reset();
                        if (window.$ && $('#abonement-user-id').length) {
                            $('#abonement-user-id').val(null).trigger('change');
                        }
                        var modalEl = document.getElementById('abonementCreateModal');
                        if (modalEl && window.bootstrap && bootstrap.Modal) {
                            bootstrap.Modal.getOrCreateInstance(modalEl).hide();
                        }
                        if (table) table.ajax.reload(null, false);
                    })
                    .catch(function (err) {
                        toast(err && err.message ? err.message : 'Ошибка', true);
                    })
                    .finally(function () {
                        if (btn) btn.disabled = false;
                    });
            });
        }

        // manual paid actions
        document.addEventListener('click', function (e) {
            var btn = e.target.closest('[data-abonement-action]');
            if (!btn) return;

            var action = btn.getAttribute('data-abonement-action');
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
                    fetch('/admin/setting-prices/abonements/' + id + '/manual-paid', {
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
                            toast('Статус оплаты обновлён.', false);
                            if (table) table.ajax.reload(null, false);
                        })
                        .catch(function (err) {
                            toast(err && err.message ? err.message : 'Ошибка', true);
                        });
                }
            );
        });
    });
})();

