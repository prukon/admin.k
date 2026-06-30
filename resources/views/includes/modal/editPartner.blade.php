{{-- resources/views/admin/partners/_modals.blade.php --}}

<!-- =========================
     МОДАЛКА: СОЗДАНИЕ ПАРТНЁРА
========================== -->
<div class="modal fade" id="createPartnerModal" tabindex="-1" aria-labelledby="createPartnerModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="createPartnerModalLabel">Создание партнёра</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
            </div>
            <div class="modal-body">
                <form id="partnerForm" class="text-start row" action="{{ route('admin.partner.store') }}" method="POST" novalidate>
                    @csrf

                    <div class="col-12 mb-3">
                        <div class="alert alert-info mb-0">
                            Реквизиты юр. лица (ИНН, банк, НДС и т.д.) добавляются после создания партнёра в справочнике
                            @can('legal_entities.view')
                                <a href="{{ route('admin.legal-entities.index') }}">«Юр. лица»</a>.
                            @else
                                «Юр. лица».
                            @endcan
                        </div>
                    </div>

                    <div class="col-12 col-lg-6 mb-3">
                        <h4>Основная информация</h4>

                        <div class="mb-3">
                            <label for="title" class="form-label">Название школы/секции*</label>
                            <input type="text" class="form-control" id="title" name="title" value="{{ old('title') }}">
                            <div class="text-danger error-title"></div>
                        </div>

                        <div class="mb-3">
                            <label for="sms_name" class="form-label">Название для SMS/выписок</label>
                            <input type="text" class="form-control" id="sms_name" name="sms_name" maxlength="14" value="{{ old('sms_name') }}">
                            <div class="text-danger error-sms_name"></div>
                        </div>

                        <div class="mb-3">
                            <label for="phone" class="form-label">Телефон</label>
                            @include('includes.fields.phone-input', [
                                'name' => 'phone',
                                'id' => 'phone',
                                'value' => old('phone'),
                            ])
                            <div class="text-danger error-phone"></div>
                        </div>

                        <div class="mb-3">
                            <label for="email-partner" class="form-label">E-mail партнёра*</label>
                            <input type="email" class="form-control" id="email-partner" name="email" value="{{ old('email') }}">
                            <div class="text-danger error-email"></div>
                        </div>

                        <div class="mb-3">
                            <label for="website" class="form-label">Сайт</label>
                            <input type="text" class="form-control" id="website" name="website" value="{{ old('website') }}">
                            <div class="text-danger error-website"></div>
                        </div>
                    </div>

                    <div class="col-12 col-lg-6 mb-3">
                        <h4>Служебное</h4>

                        <div class="mb-3">
                            <label for="order_by" class="form-label">Сортировка</label>
                            <input type="number" name="order_by" class="form-control" id="order_by" value="{{ old('order_by', 0) }}">
                            <div class="text-danger error-order_by"></div>
                        </div>

                        <div class="mb-3">
                            <label for="is_enabled" class="form-label">Активность</label>
                            <select name="is_enabled" class="form-control" id="is_enabled">
                                <option value="1"{{ old('is_enabled',1)==1?' selected':'' }}>Активен</option>
                                <option value="0"{{ old('is_enabled')==0?' selected':'' }}>Неактивен</option>
                            </select>
                            <div class="text-danger error-is_enabled"></div>
                        </div>
                    </div>

                    <div class="col-12 text-end">
                        <hr>
                        <button type="button" class="btn btn-secondary me-2" data-bs-dismiss="modal">Закрыть</button>
                        <button type="submit" class="btn btn-primary">Создать партнёра</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- =========================
     МОДАЛКА: РЕДАКТИРОВАНИЕ ПАРТНЁРА
========================== -->
<div class="modal fade" id="editPartnerModal" tabindex="-1" aria-labelledby="editPartnerModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editPartnerModalLabel">Редактирование партнёра</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
            </div>
            <div class="modal-body">
                <form id="edit-partner-form" class="text-start row">
                    @csrf
                    @method('patch')
                    <input type="hidden" id="edit-partner-id">

                    <div class="col-12 mb-3">
                        <div class="alert alert-info mb-0">
                            Реквизиты юр. лица редактируются в справочнике
                            @can('legal_entities.view')
                                <a href="{{ route('admin.legal-entities.index') }}">«Юр. лица»</a>.
                            @else
                                «Юр. лица».
                            @endcan
                        </div>
                    </div>

                    <div class="col-12 col-lg-6 mb-3">
                        <h4>Основная информация</h4>

                        <div class="mb-3">
                            <label for="edit-title" class="form-label">Название школы/секции*</label>
                            <input type="text" name="title" class="form-control" id="edit-title">
                            <p class="text-danger" id="edit-title-error"></p>
                        </div>

                        <div class="mb-3">
                            <label for="edit-sms_name" class="form-label">Название для SMS/выписок</label>
                            <input type="text" name="sms_name" class="form-control" id="edit-sms_name" maxlength="14">
                            <p class="text-danger" id="edit-sms_name-error"></p>
                        </div>

                        <div class="mb-3">
                            <label for="edit-phone" class="form-label">Телефон</label>
                            @include('includes.fields.phone-input', [
                                'name' => 'phone',
                                'id' => 'edit-phone',
                            ])
                            <p class="text-danger" id="edit-phone-error"></p>
                        </div>

                        <div class="mb-3">
                            <label for="edit-email" class="form-label">E-mail партнёра*</label>
                            <input type="email" name="email" class="form-control" id="edit-email">
                            <p class="text-danger" id="edit-email-error"></p>
                        </div>

                        <div class="mb-3">
                            <label for="edit-website" class="form-label">Сайт</label>
                            <input type="text" name="website" class="form-control" id="edit-website">
                            <p class="text-danger" id="edit-website-error"></p>
                        </div>
                    </div>

                    <div class="col-12 col-lg-6 mb-3">
                        <h4>Служебное</h4>

                        <div class="mb-3">
                            <label for="edit-order_by" class="form-label">Сортировка</label>
                            <input type="number" name="order_by" class="form-control" id="edit-order_by">
                            <p class="text-danger" id="edit-order_by-error"></p>
                        </div>

                        <div class="mb-3">
                            <label for="edit-is_enabled" class="form-label">Активность</label>
                            <select name="is_enabled" class="form-control" id="edit-is_enabled">
                                <option value="1">Активен</option>
                                <option value="0">Неактивен</option>
                            </select>
                            <p class="text-danger" id="edit-is_enabled-error"></p>
                        </div>
                    </div>

                    <hr>
                    <div class="buttons-wrap mb-3">
                        <button type="button" class="btn btn-primary me-2" id="update-partner-btn">Обновить</button>
                        <button type="button" class="btn btn-danger confirm-delete-modal" id="delete-partner-btn" data-bs-toggle="modal" data-bs-target="#deleteConfirmationModal">Удалить</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

{{-- ======================
     SCRIPTS (Create/Edit)
====================== --}}
<script>
    document.addEventListener('DOMContentLoaded', () => {

        function escapeHtml(value) {
            return String(value ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function normalizeMessages(msg) {
            if (Array.isArray(msg)) return msg;
            if (msg === null || msg === undefined) return [];
            return [String(msg)];
        }

        function initLaravelErrorMap(formEl) {
            const map = new Map();
            if (!formEl) return map;

            formEl.querySelectorAll('[class*="error-"]').forEach(node => {
                const token = Array.from(node.classList).find(c => c.startsWith('error-'));
                if (!token) return;
                const field = token.slice('error-'.length);
                if (!field) return;

                node.dataset.errorFor = field;
                node.classList.add('invalid-feedback');
                map.set(field, node);
            });

            return map;
        }

        function clearLaravelErrors(formEl, errorMap) {
            if (!formEl) return;
            (errorMap ? Array.from(errorMap.values()) : []).forEach(node => {
                node.innerHTML = '';
                node.classList.remove('d-block');
            });
            formEl.querySelectorAll('.is-invalid').forEach(i => i.classList.remove('is-invalid'));
        }

        function renderLaravelErrors(formEl, errorMap, errors) {
            if (!formEl) return;
            if (!errors || typeof errors !== 'object') return;

            Object.keys(errors).forEach(field => {
                const messages = normalizeMessages(errors[field]);
                if (!messages.length) return;

                const input =
                    formEl.querySelector(`[name="${field}"]`) ||
                    (field.includes('.') ? formEl.querySelector(`[name="${field.replace(/\.(\w+)/g, '[$1]')}"]`) : null);

                if (input) input.classList.add('is-invalid');

                const node = (errorMap && errorMap.get(field)) ? errorMap.get(field) : null;
                if (node) {
                    node.classList.add('d-block');
                    node.innerHTML = messages.map(m => `<div>${escapeHtml(m)}</div>`).join('');
                }
            });
        }

        const createForm = document.getElementById('partnerForm');
        const createErrorMap = initLaravelErrorMap(createForm);

        if (createForm) {
            createForm.addEventListener('submit', e => {
                e.preventDefault();
                clearLaravelErrors(createForm, createErrorMap);

                fetch(createForm.action, {
                    method: 'POST',
                    body: new FormData(createForm),
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': createForm.querySelector('input[name="_token"]').value
                    }
                })
                    .then(res => {
                        if (res.status === 422) return res.json().then(j => { throw (j && j.errors) ? j.errors : j; });
                        if (!res.ok) throw { general: ['Ошибка сервера'] };
                        return res.json();
                    })
                    .then(json => {
                        showSuccessModal('Создание партнёра', json.message, 1);
                        createForm.reset();
                        const modal = bootstrap.Modal.getInstance(document.getElementById('createPartnerModal'));
                        modal && modal.hide();
                        if (typeof window.reloadPartnersTable === 'function') {
                            window.reloadPartnersTable();
                        }
                    })
                    .catch(errors => {
                        renderLaravelErrors(createForm, createErrorMap, errors);
                        if (errors.general) { $('#errorModal').modal('show'); }
                    });
            });

            createForm.addEventListener('input', (e) => {
                const t = e.target;
                if (!t || !t.name) return;
                const dotName = t.name.replace(/\[(\w+)\]/g, '.$1');
                const key = createErrorMap.has(t.name) ? t.name : dotName;
                const node = createErrorMap.get(key);
                if (node) {
                    node.innerHTML = '';
                    node.classList.remove('d-block');
                }
                t.classList.remove('is-invalid');
            });
        }

        $(document).on('click', '.edit-partner-link', function (e) {
            e.preventDefault();
            const partnerId = $(this).data('id');

            document.getElementById('edit-partner-form').reset();

            $.ajax({
                url: '/admin/partner/' + partnerId + '/edit',
                type: 'GET',
                dataType: 'json',
                success: function (response) {
                    $('#edit-partner-id').val(response.id);
                    $('#edit-title').val(response.title);
                    $('#edit-sms_name').val(response.sms_name || '');
                    window.PhoneInputMask?.setValue('#edit-phone', response.phone || '');
                    $('#edit-email').val(response.email || '');
                    $('#edit-website').val(response.website || '');
                    $('#edit-order_by').val(response.order_by);
                    $('#edit-is_enabled').val(response.is_enabled ? '1' : '0');

                    $('#edit-partner-form p.text-danger').text('');
                    $('#edit-partner-form .is-invalid').removeClass('is-invalid');

                    $('#editPartnerModal').modal('show');
                },
                error: function () {
                    alert('Не удалось загрузить данные партнёра.');
                }
            });
        });

        $('#update-partner-btn').on('click', function () {
            const partnerId = $('#edit-partner-id').val();
            const formData = $('#edit-partner-form').serialize();

            $.ajax({
                url: '/admin/partner/' + partnerId,
                type: 'PATCH',
                data: formData,
                success: function () {
                    showSuccessModal("Редактирование партнера", "Партнёр успешно отредактирован.", 1);
                    $('#editPartnerModal').modal('hide');
                    if (typeof window.reloadPartnersTable === 'function') {
                        window.reloadPartnersTable();
                    }
                },
                error: function (xhr) {
                    if (xhr.status === 422) {
                        const errors = xhr.responseJSON.errors || {};
                        $('#edit-partner-form .is-invalid').removeClass('is-invalid');
                        $('#edit-partner-form p.text-danger').each(function () {
                            $(this).addClass('invalid-feedback d-block').text('');
                        });
                        $.each(errors, function (field, messages) {
                            const fieldId = field.replace(/\./g, '_');
                            $('#edit-' + fieldId + '-error').addClass('invalid-feedback d-block').html(messages.map(m => `<div>${escapeHtml(m)}</div>`).join(''));
                            $('#edit-' + fieldId).addClass('is-invalid');
                        });
                    } else {
                        $('#errorModal').modal('show');
                    }
                }
            });
        });

        $(document).on('click', '#delete-partner-btn', function () {
            showConfirmDeleteModal(
                "Удаление партнёра",
                "Вы уверены, что хотите удалить партнёра?",
                function () {
                    const partnerId = $('#edit-partner-id').val();
                    $.ajax({
                        url: '/admin/partner/' + partnerId,
                        type: 'DELETE',
                        data: { _token: $('input[name="_token"]').val() },
                        success: function () {
                            showSuccessModal("Удаление партнёра", "Партнёр успешно удалён.", 1);
                            $('#editPartnerModal').modal('hide');
                            if (typeof window.reloadPartnersTable === 'function') {
                                window.reloadPartnersTable();
                            }
                        },
                        error: function () {
                            $('#errorModal').modal('show');
                        }
                    });
                }
            );
        });

    });
</script>
