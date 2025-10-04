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
                <form id="partnerForm" class="text-start row" action="{{ route('admin.partner.store') }}" method="POST">
                    @csrf

                    {{-- Основная информация --}}
                    <div class="col-12 col-lg-6 mb-3">
                        <h4>Основная информация</h4>

                        {{-- Тип бизнеса --}}
                        <div class="mb-3">
                            <label for="business_type" class="form-label">Тип бизнеса*</label>
                            <select name="business_type" id="business_type" class="form-control">
                                <option value="company">ООО</option>
                                <option value="individual_entrepreneur">ИП</option>
                                <option value="non_commercial_organization">НКО</option>
                                <option value="physical_person">Физическое лицо</option>
                            </select>
                            <div class="text-danger error-business_type"></div>
                        </div>

                        {{-- Наименование (или ФИО) --}}
                        <div class="mb-3">
                            <label for="title" class="form-label" id="label-title">Наименование*</label>
                            <input type="text" class="form-control" id="title" name="title" value="{{ old('title') }}">
                            <div class="text-danger error-title"></div>
                        </div>

                        {{-- ИНН --}}
                        <div class="mb-3" id="tax_id_wrapper">
                            <label for="tax_id" class="form-label">ИНН</label>
                            <input type="text" class="form-control" id="tax_id" name="tax_id" value="{{ old('tax_id') }}">
                            <div class="text-danger error-tax_id"></div>
                        </div>

                        {{-- КПП --}}
                        <div class="mb-3" id="kpp_wrapper">
                            <label for="kpp" class="form-label">КПП</label>
                            <input type="text" class="form-control" id="kpp" name="kpp" value="{{ old('kpp') }}">
                            <div class="text-danger error-kpp"></div>
                        </div>

                        {{-- ОГРН / ОГРНИП --}}
                        <div class="mb-3" id="registration_number_wrapper">
                            <label for="registration_number" class="form-label" id="label-registration_number">ОГРН (ОГРНИП)</label>
                            <input type="text" class="form-control" id="registration_number" name="registration_number" value="{{ old('registration_number') }}">
                            <div class="text-danger error-registration_number"></div>
                        </div>

                        {{-- Название для SMS/выписок --}}
                        <div class="mb-3">
                            <label for="sms_name" class="form-label">Название для SMS/выписок</label>
                            <input type="text" class="form-control" id="sms_name" name="sms_name" maxlength="14" value="{{ old('sms_name') }}">
                            <div class="text-danger error-sms_name"></div>
                        </div>

                        {{-- Город --}}
                        <div class="mb-3">
                            <label for="city" class="form-label">Город</label>
                            <input type="text" class="form-control" id="city" name="city" maxlength="100" value="{{ old('city') }}">
                            <div class="text-danger error-city"></div>
                        </div>

                        {{-- Индекс --}}
                        <div class="mb-3">
                            <label for="zip" class="form-label">Индекс</label>
                            <input type="text" class="form-control" id="zip" name="zip" maxlength="20" pattern="\d{6}" value="{{ old('zip') }}">
                            <div class="text-danger error-zip"></div>
                        </div>

                        {{-- Адрес --}}
                        <div class="mb-3">
                            <label for="address" class="form-label">Адрес</label>
                            <input type="text" class="form-control" id="address" name="address" value="{{ old('address') }}">
                            <div class="text-danger error-address"></div>
                        </div>

                        {{-- Телефон --}}
                        <div class="mb-3">
                            <label for="phone" class="form-label">Телефон</label>
                            <input type="text" class="form-control" id="phone" name="phone" value="{{ old('phone') }}">
                            <div class="text-danger error-phone"></div>
                        </div>

                        {{-- E-mail партнёра --}}
                        <div class="mb-3">
                            <label for="email-partner" class="form-label">E-mail партнёра*</label>
                            <input type="email" class="form-control" id="email-partner" name="email" value="{{ old('email') }}">
                            <div class="text-danger error-email"></div>
                        </div>

                        {{-- Сайт --}}
                        <div class="mb-3">
                            <label for="website" class="form-label">Сайт</label>
                            <input type="text" class="form-control" id="website" name="website" value="{{ old('website') }}">
                            <div class="text-danger error-website"></div>
                        </div>
                    </div>

                    {{-- Реквизиты + Данные руководителя --}}
                    <div class="col-12 col-lg-6 mb-3">
                        <h4 id="requisites">Реквизиты</h4>
                        <div id="bankFields">
                            <div class="mb-3">
                                <label for="bank_name" class="form-label">Наименование банка</label>
                                <input type="text" class="form-control" id="bank_name" name="bank_name" value="{{ old('bank_name') }}">
                                <div class="text-danger error-bank_name"></div>
                            </div>
                            <div class="mb-3">
                                <label for="bank_bik" class="form-label">БИК</label>
                                <input type="text" class="form-control" id="bank_bik" name="bank_bik" value="{{ old('bank_bik') }}">
                                <div class="text-danger error-bank_bik"></div>
                            </div>
                            <div class="mb-3">
                                <label for="bank_account" class="form-label">Расчётный счёт</label>
                                <input type="text" class="form-control" id="bank_account" name="bank_account" value="{{ old('bank_account') }}">
                                <div class="text-danger error-bank_account"></div>
                            </div>
                        </div>

                        <div class="col-12 mb-3">
                            <h4>Данные руководителя</h4>
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label for="ceo_lastName" class="form-label">Фамилия</label>
                                    <input type="text" class="form-control" id="ceo_lastName" name="ceo[lastName]" maxlength="100" value="{{ old('ceo.lastName') }}">
                                    <div class="text-danger error-ceo.lastName"></div>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="ceo_firstName" class="form-label">Имя</label>
                                    <input type="text" class="form-control" id="ceo_firstName" name="ceo[firstName]" maxlength="100" value="{{ old('ceo.firstName') }}">
                                    <div class="text-danger error-ceo.firstName"></div>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="ceo_middleName" class="form-label">Отчество</label>
                                    <input type="text" class="form-control" id="ceo_middleName" name="ceo[middleName]" maxlength="100" value="{{ old('ceo.middleName') }}">
                                    <div class="text-danger error-ceo.middleName"></div>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="ceo_phone" class="form-label">Телефон руководителя</label>
                                    <input type="text" class="form-control" id="ceo_phone" name="ceo[phone]" maxlength="20" value="{{ old('ceo.phone') }}">
                                    <div class="text-danger error-ceo.phone"></div>
                                </div>
                            </div>
                        </div>

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

                    {{-- Основная информация --}}
                    <div class="col-12 col-lg-6 mb-3">
                        <h4>Основная информация</h4>

                        <div class="mb-3">
                            <label for="edit-business_type" class="form-label">Тип бизнеса*</label>
                            <select name="business_type" id="edit-business_type" class="form-control">
                                <option value="company">ООО</option>
                                <option value="individual_entrepreneur">ИП</option>
                                <option value="non_commercial_organization">НКО</option>
                                <option value="physical_person">Физическое лицо</option>
                            </select>
                            <p class="text-danger" id="edit-business_type-error"></p>
                        </div>

                        <div class="mb-3">
                            <label for="edit-title" class="form-label">Наименование*</label>
                            <input type="text" name="title" class="form-control" id="edit-title">
                            <p class="text-danger" id="edit-title-error"></p>
                        </div>

                        <div class="mb-3">
                            <label for="edit-tax_id" class="form-label">ИНН</label>
                            <input type="text" name="tax_id" class="form-control" id="edit-tax_id">
                            <p class="text-danger" id="edit-tax_id-error"></p>
                        </div>

                        <div class="mb-3">
                            <label for="edit-kpp" class="form-label">КПП</label>
                            <input type="text" name="kpp" class="form-control" id="edit-kpp">
                            <p class="text-danger" id="edit-kpp-error"></p>
                        </div>

                        <div class="mb-3">
                            <label for="edit-registration_number" class="form-label">ОГРН (ОГРНИП)</label>
                            <input type="text" name="registration_number" class="form-control" id="edit-registration_number">
                            <p class="text-danger" id="edit-registration_number-error"></p>
                        </div>

                        <div class="mb-3">
                            <label for="edit-sms_name" class="form-label">Название для SMS/выписок</label>
                            <input type="text" name="sms_name" class="form-control" id="edit-sms_name" maxlength="14">
                            <p class="text-danger" id="edit-sms_name-error"></p>
                        </div>

                        <div class="mb-3">
                            <label for="edit-city" class="form-label">Город</label>
                            <input type="text" name="city" class="form-control" id="edit-city" maxlength="100">
                            <p class="text-danger" id="edit-city-error"></p>
                        </div>

                        <div class="mb-3">
                            <label for="edit-zip" class="form-label">Индекс</label>
                            <input type="text" name="zip" class="form-control" id="edit-zip" maxlength="20" pattern="\d{6}">
                            <p class="text-danger" id="edit-zip-error"></p>
                        </div>

                        <div class="mb-3">
                            <label for="edit-address" class="form-label">Адрес</label>
                            <input type="text" name="address" class="form-control" id="edit-address">
                            <p class="text-danger" id="edit-address-error"></p>
                        </div>

                        <div class="mb-3">
                            <label for="edit-phone" class="form-label">Телефон</label>
                            <input type="text" name="phone" class="form-control" id="edit-phone">
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

                    {{-- Реквизиты + Данные руководителя --}}
                    <div class="col-12 col-lg-6 mb-3">
                        <h4>Реквизиты</h4>
                        <div id="edit-bankFields">
                            <div class="mb-3">
                                <label for="edit-bank_name" class="form-label">Наименование банка</label>
                                <input type="text" name="bank_name" class="form-control" id="edit-bank_name">
                                <p class="text-danger" id="edit-bank_name-error"></p>
                            </div>
                            <div class="mb-3">
                                <label for="edit-bank_bik" class="form-label">БИК</label>
                                <input type="text" name="bank_bik" class="form-control" id="edit-bank_bik">
                                <p class="text-danger" id="edit-bank_bik-error"></p>
                            </div>
                            <div class="mb-3">
                                <label for="edit-bank_account" class="form-label">Расчётный счёт</label>
                                <input type="text" name="bank_account" class="form-control" id="edit-bank_account">
                                <p class="text-danger" id="edit-bank_account-error"></p>
                            </div>
                        </div>

                        <div class="col-12 mb-3">
                            <h4>Данные руководителя</h4>
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label for="edit-ceo_lastName" class="form-label">Фамилия</label>
                                    <input type="text" name="ceo[lastName]" class="form-control" id="edit-ceo_lastName" maxlength="100">
                                    <p class="text-danger" id="edit-ceo_lastName-error"></p>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="edit-ceo_firstName" class="form-label">Имя</label>
                                    <input type="text" name="ceo[firstName]" class="form-control" id="edit-ceo_firstName" maxlength="100">
                                    <p class="text-danger" id="edit-ceo_firstName-error"></p>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="edit-ceo_middleName" class="form-label">Отчество</label>
                                    <input type="text" name="ceo[middleName]" class="form-control" id="edit-ceo_middleName" maxlength="100">
                                    <p class="text-danger" id="edit-ceo_middleName-error"></p>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="edit-ceo_phone" class="form-label">Телефон руководителя</label>
                                    <input type="text" name="ceo[phone]" class="form-control" id="edit-ceo_phone" maxlength="20">
                                    <p class="text-danger" id="edit-ceo_phone-error"></p>
                                </div>
                            </div>
                        </div>

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

        /* ========== CREATE ========== */
        const createForm = document.getElementById('partnerForm');

        function findCreateInputByField(field) {
            let el = createForm.querySelector(`[name="${field}"]`);
            if (el) return el;
            if (field.includes('.')) {
                const arrName = field.replace(/\.(\w+)/g, '[$1]');
                el = createForm.querySelector(`[name="${arrName}"]`);
                if (el) return el;
            }
            return null;
        }

        if (createForm) {
            createForm.addEventListener('submit', e => {
                e.preventDefault();

                // очистка ошибок
                createForm.querySelectorAll('[class^="text-danger error-"]').forEach(div => div.textContent = '');
                createForm.querySelectorAll('.is-invalid').forEach(i => i.classList.remove('is-invalid'));

                const data = new FormData(createForm);

                fetch(createForm.action, {
                    method: 'POST',
                    body: data,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': createForm.querySelector('input[name="_token"]').value
                    }
                })
                    .then(res => {
                        if (res.status === 422) return res.json().then(j => { throw j.errors; });
                        if (!res.ok) throw { general: ['Ошибка сервера'] };
                        return res.json();
                    })
                    .then(json => {
                        console.log('[Partner.store] success:', json);
                        showSuccessModal('Создание партнёра', json.message, 1);
                        createForm.reset();
                        const modal = bootstrap.Modal.getInstance(document.getElementById('createPartnerModal'));
                        modal && modal.hide();
                    })
                    .catch(errors => {
                        console.warn('[Partner.store] validation errors:', errors);
                        Object.keys(errors).forEach(field => {
                            const input = findCreateInputByField(field);
                            const errDiv = createForm.querySelector(`.error-${CSS.escape(field)}`);
                            if (input) input.classList.add('is-invalid');
                            if (errDiv) errDiv.textContent = Array.isArray(errors[field]) ? errors[field].join(' ') : String(errors[field]);
                        });
                        if (errors.general) { $('#errorModal').modal('show'); }
                    });
            });

            function toggleCreateFields() {
                const type = createForm.business_type.value;
                const isPP = type === 'physical_person';
                ['tax_id_wrapper','kpp_wrapper','registration_number_wrapper','requisites','bankFields']
                    .forEach(id => { const node = document.getElementById(id); if (node) node.style.display = isPP ? 'none' : ''; });
                document.getElementById('label-title').textContent = isPP ? 'ФИО*' : 'Наименование*';
            }
            createForm.business_type.addEventListener('change', toggleCreateFields);
            toggleCreateFields();
        }

        /* =========== EDIT =========== */
        function normalizeCeoCamel(raw) {
            let ceo = raw || {};
            if (typeof ceo === 'string') {
                try { ceo = JSON.parse(ceo) || {}; } catch (e) { ceo = {}; }
            }
            // принимаем и снег, и кэмел — но на фронте показываем camelCase
            return {
                lastName:   ceo.lastName   ?? ceo.last_name   ?? '',
                firstName:  ceo.firstName  ?? ceo.first_name  ?? '',
                middleName: ceo.middleName ?? ceo.middle_name ?? '',
                phone:      ceo.phone      ?? ''
            };
        }

        $('.edit-partner-link').on('click', function () {
            const partnerId = $(this).data('id');

            document.getElementById('edit-partner-form').reset();

            $.ajax({
                url: '/admin/partner/' + partnerId + '/edit',
                type: 'GET',
                dataType: 'json',
                success: function (response) {
                    console.log('[Partner.edit] payload from backend:', response);

                    $('#edit-partner-id').val(response.id);
                    $('#edit-business_type').val(response.business_type);
                    $('#edit-title').val(response.title);
                    $('#edit-tax_id').val(response.tax_id);
                    $('#edit-kpp').val(response.kpp);
                    $('#edit-registration_number').val(response.registration_number);

                    $('#edit-sms_name').val(response.sms_name || '');
                    $('#edit-city').val(response.city || '');
                    $('#edit-zip').val(response.zip || '');
                    $('#edit-address').val(response.address || '');
                    $('#edit-phone').val(response.phone || '');
                    $('#edit-email').val(response.email || '');
                    $('#edit-website').val(response.website || '');
                    $('#edit-bank_name').val(response.bank_name || '');
                    $('#edit-bank_bik').val(response.bank_bik || '');
                    $('#edit-bank_account').val(response.bank_account || '');
                    $('#edit-order_by').val(response.order_by);
                    $('#edit-is_enabled').val(response.is_enabled ? '1' : '0');

                    $('#edit-title').siblings('label').text(
                        response.business_type === 'physical_person' ? 'ФИО*' : 'Наименование*'
                    );

                    const ceo = normalizeCeoCamel(response.ceo);
                    $('#edit-ceo_lastName').val(ceo.lastName);
                    $('#edit-ceo_firstName').val(ceo.firstName);
                    $('#edit-ceo_middleName').val(ceo.middleName);
                    $('#edit-ceo_phone').val(ceo.phone);

                    // очистка ошибок
                    $('#edit-partner-form p.text-danger').text('');
                    $('#edit-partner-form .is-invalid').removeClass('is-invalid');

                    $('#editPartnerModal').modal('show');
                },
                error: function (xhr) {
                    console.error('[Partner.edit] error:', xhr?.responseText || xhr);
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
                success: function (resp) {
                    console.log('[Partner.update] success:', resp);
                    showSuccessModal("Редактирование партнера", "Партнёр успешно отредактирован.", 1);
                    $('#editPartnerModal').modal('hide');
                },
                error: function (xhr) {
                    console.error('[Partner.update] error:', xhr?.responseText || xhr);
                    if (xhr.status === 422) {
                        const errors = xhr.responseJSON.errors || {};
                        $.each(errors, function (field, messages) {
                            const fieldId = field.replace(/\./g, '_'); // ceo.firstName -> ceo_firstName
                            $('#edit-' + fieldId + '-error').text(messages.join(' '));
                            $('#edit-' + fieldId).addClass('is-invalid');
                        });
                    } else {
                        $('#errorModal').modal('show');
                    }
                }
            });
        });

        // Удаление
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
                        success: function (resp) {
                            console.log('[Partner.delete] success:', resp);
                            showSuccessModal("Удаление партнёра", "Партнёр успешно удалён.", 1);
                            $('#editPartnerModal').modal('hide');
                        },
                        error: function (xhr) {
                            console.error('[Partner.delete] error:', xhr?.responseText || xhr);
                            $('#errorModal').modal('show');
                        }
                    });
                }
            );
        });

    });
</script>
