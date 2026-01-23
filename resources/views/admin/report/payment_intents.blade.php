<h4 class="pt-3 text-start">Платежные запросы</h4>

<form id="payment-intents-filters" method="GET" action="/admin/reports/payment-intents" class="mb-3">
    <div class="row g-2 align-items-end">
        <div class="col-12 col-md-2">
            <label class="form-label">InvId</label>
            <input class="form-control" name="inv_id" value="{{ $filters['inv_id'] ?? '' }}" placeholder="123">
        </div>
        <div class="col-12 col-md-2">
            <label class="form-label">Partner ID</label>
            <input class="form-control" name="partner_id" value="{{ $filters['partner_id'] ?? '' }}" placeholder="1">
        </div>
        <div class="col-12 col-md-2">
            <label class="form-label">User ID</label>
            <input class="form-control" name="user_id" value="{{ $filters['user_id'] ?? '' }}" placeholder="42">
        </div>
        <div class="col-12 col-md-2">
            <label class="form-label">Provider</label>
            <select class="form-select" name="provider">
                @php($provider = $filters['provider'] ?? '')
                <option value="">—</option>
                <option value="robokassa" {{ $provider === 'robokassa' ? 'selected' : '' }}>robokassa</option>
                <option value="tbank" {{ $provider === 'tbank' ? 'selected' : '' }}>tbank</option>
            </select>
        </div>
        <div class="col-12 col-md-2">
            <label class="form-label">Status</label>
            @php($status = $filters['status'] ?? '')
            <select class="form-select" name="status">
                <option value="">—</option>
                <option value="pending" {{ $status === 'pending' ? 'selected' : '' }}>pending</option>
                <option value="paid" {{ $status === 'paid' ? 'selected' : '' }}>paid</option>
                <option value="failed" {{ $status === 'failed' ? 'selected' : '' }}>failed</option>
                <option value="cancelled" {{ $status === 'cancelled' ? 'selected' : '' }}>cancelled</option>
            </select>
        </div>

        <div class="col-12 col-md-2">
            <button class="btn btn-primary w-100" type="submit">Фильтр</button>
        </div>

        <div class="col-12 col-md-2">
            <label class="form-label">Created from</label>
            <input class="form-control" type="date" name="created_from" value="{{ $filters['created_from'] ?? '' }}">
        </div>
        <div class="col-12 col-md-2">
            <label class="form-label">Created to</label>
            <input class="form-control" type="date" name="created_to" value="{{ $filters['created_to'] ?? '' }}">
        </div>
        <div class="col-12 col-md-2">
            <label class="form-label">Paid from</label>
            <input class="form-control" type="date" name="paid_from" value="{{ $filters['paid_from'] ?? '' }}">
        </div>
        <div class="col-12 col-md-2">
            <label class="form-label">Paid to</label>
            <input class="form-control" type="date" name="paid_to" value="{{ $filters['paid_to'] ?? '' }}">
        </div>
        <div class="col-12 col-md-2">
            <button class="btn btn-outline-secondary w-100" type="button" id="paymentIntentsResetBtn">Сброс</button>
        </div>
    </div>
</form>

<div class="table-responsive">
    <table class="table table-bordered" id="payment-intents-table">
        <thead>
        <tr>
            <th>InvId</th>
            <th>Provider InvId</th>
            <th>Partner</th>
            <th>User</th>
            <th>Provider</th>
            <th>Status</th>
            <th>OutSum</th>
            <th>PaymentDate</th>
            <th>Created</th>
            <th>Paid</th>
            <th>Meta</th>
        </tr>
        </thead>
    </table>
</div>

@push('scripts')
    <script type="text/javascript">
        $(function () {
            var $form = $('#payment-intents-filters');

            function dtLanguageRu() {
                return {
                    "processing": "Обработка...",
                    "search": "",
                    "searchPlaceholder": "Поиск...",
                    "lengthMenu": "Показать _MENU_",
                    "info": "С _START_ до _END_ из _TOTAL_ записей",
                    "infoEmpty": "С 0 до 0 из 0 записей",
                    "infoFiltered": "(отфильтровано из _MAX_ записей)",
                    "loadingRecords": "Загрузка записей...",
                    "zeroRecords": "Записи отсутствуют.",
                    "emptyTable": "В таблице отсутствуют данные",
                    "paginate": {
                        "first": "",
                        "previous": "",
                        "next": "",
                        "last": ""
                    },
                    "aria": {
                        "sortAscending": ": активировать для сортировки столбца по возрастанию",
                        "sortDescending": ": активировать для сортировки столбца по убыванию"
                    }
                };
            }

            function formatNumber(number) {
                if (number === null || number === undefined) return '';
                return number.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
            }

            function renderStatusBadge(status) {
                if (!status) return '';
                var s = String(status);
                if (s === 'pending') return '<span class="badge bg-warning text-dark">в ожидании</span>';
                if (s === 'paid') return '<span class="badge bg-success">оплачен</span>';
                if (s === 'failed') return '<span class="badge bg-danger">ошибка</span>';
                if (s === 'cancelled') return '<span class="badge bg-secondary">отменён</span>';
                return '<span class="badge bg-light text-dark">' + $('<div/>').text(s).html() + '</span>';
            }

            var table = $('#payment-intents-table').DataTable({
                processing: true,
                serverSide: true,
                ajax: {
                    url: "{{ route('reports.payment-intents.data') }}",
                    data: function (d) {
                        d.inv_id = $form.find('[name="inv_id"]').val();
                        d.partner_id = $form.find('[name="partner_id"]').val();
                        d.user_id = $form.find('[name="user_id"]').val();
                        d.provider = $form.find('[name="provider"]').val();
                        d.status = $form.find('[name="status"]').val();
                        d.created_from = $form.find('[name="created_from"]').val();
                        d.created_to = $form.find('[name="created_to"]').val();
                        d.paid_from = $form.find('[name="paid_from"]').val();
                        d.paid_to = $form.find('[name="paid_to"]').val();
                    }
                },
                columns: [
                    {data: 'id', name: 'id'},
                    {data: 'provider_inv_id', name: 'provider_inv_id'},
                    {
                        data: null,
                        name: 'partner_id',
                        orderable: false,
                        searchable: false,
                        render: function (data, type, row) {
                            var title = row.partner_title ? ('<div class="small text-muted">' + row.partner_title + '</div>') : '';
                            return (row.partner_id ? row.partner_id : '') + title;
                        }
                    },
                    {
                        data: null,
                        name: 'user_id',
                        orderable: false,
                        searchable: false,
                        render: function (data, type, row) {
                            var name = row.user_name ? ('<div class="small text-muted">' + row.user_name + '</div>') : '';
                            return (row.user_id ? row.user_id : '') + name;
                        }
                    },
                    {data: 'provider', name: 'provider'},
                    {
                        data: 'status',
                        name: 'status',
                        render: function (data, type, row) {
                            if (type === 'display') return renderStatusBadge(data);
                            return data;
                        }
                    },
                    {
                        data: 'out_sum',
                        name: 'out_sum',
                        render: function (data, type, row) {
                            if (type === 'display') {
                                var formattedPrice = formatNumber(data);
                                return formattedPrice ? (formattedPrice + ' руб') : '';
                            }
                            return data;
                        }
                    },
                    {data: 'payment_date', name: 'payment_date'},
                    {data: 'created_at', name: 'created_at'},
                    {data: 'paid_at', name: 'paid_at'},
                    {
                        data: 'meta',
                        name: 'meta',
                        orderable: false,
                        render: function (data, type, row) {
                            if (!data) return '';
                            var text = String(data);
                            var safe = $('<div/>').text(text).html();
                            var short = safe.length > 160 ? (safe.slice(0, 160) + '…') : safe;
                            return '<span class="small" title="' + safe + '">' + short + '</span>';
                        }
                    }
                ],
                order: [[0, 'desc']],
                scrollX: true,
                fixedColumns: {leftColumns: 2},
                language: dtLanguageRu()
            });

            $form.on('submit', function (e) {
                e.preventDefault();
                table.ajax.reload();
            });

            $('#paymentIntentsResetBtn').on('click', function () {
                $form[0].reset();
                table.ajax.reload();
            });
        });
    </script>
@endpush
