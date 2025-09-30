<!-- Модальное окно для редактирования партнёра -->
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
                                    <label for="edit-ceo_last_name" class="form-label">Фамилия</label>
                                    <input type="text" name="ceo[last_name]" class="form-control" id="edit-ceo_last_name" maxlength="100">
                                    <p class="text-danger" id="edit-ceo_last_name-error"></p>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="edit-ceo_first_name" class="form-label">Имя</label>
                                    <input type="text" name="ceo[first_name]" class="form-control" id="edit-ceo_first_name" maxlength="100">
                                    <p class="text-danger" id="edit-ceo_first_name-error"></p>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="edit-ceo_middle_name" class="form-label">Отчество</label>
                                    <input type="text" name="ceo[middle_name]" class="form-control" id="edit-ceo_middle_name" maxlength="100">
                                    <p class="text-danger" id="edit-ceo_middle_name-error"></p>
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

<script>
    $(function () {

        function normalizeCeo(raw) {
            var ceo = raw;
            if (typeof ceo === 'string') {
                try { ceo = JSON.parse(ceo) || {}; } catch (e) { ceo = {}; }
            }
            if (!ceo || typeof ceo !== 'object') ceo = {};
            ceo.last_name   = ceo.last_name   ?? '';
            ceo.first_name  = ceo.first_name  ?? '';
            ceo.middle_name = ceo.middle_name ?? '';
            ceo.phone       = ceo.phone       ?? '';
            return ceo;
        }

        function fillPartnerForm(response) {
            // Консольный лог: что получили с бэка (как просил)
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

            var ceo = normalizeCeo(response.ceo);
            $('#edit-ceo_last_name').val(ceo.last_name);
            $('#edit-ceo_first_name').val(ceo.first_name);
            $('#edit-ceo_middle_name').val(ceo.middle_name);
            $('#edit-ceo_phone').val(ceo.phone);

            // очистка ошибок
            $('#edit-partner-form p.text-danger').text('');
            $('#edit-partner-form .is-invalid').removeClass('is-invalid');
        }

        // Открытие модалки и загрузка данных
        $('.edit-partner-link').on('click', function () {
            var partnerId = $(this).data('id');

            // сброс формы
            document.getElementById('edit-partner-form').reset();

            $.ajax({
                url: '/admin/partner/' + partnerId + '/edit',
                type: 'GET',
                dataType: 'json',
                success: function (response) {
                    fillPartnerForm(response);
                    $('#editPartnerModal').modal('show');
                },
                error: function (xhr) {
                    console.error('[Partner.edit] error:', xhr?.responseText || xhr);
                    alert('Не удалось загрузить данные партнёра.');
                }
            });
        });

        // Обновление
        $('#update-partner-btn').on('click', function () {
            var partnerId = $('#edit-partner-id').val();
            var formData = $('#edit-partner-form').serialize();

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
                        var errors = xhr.responseJSON.errors || {};
                        $.each(errors, function (field, messages) {
                            var fieldId = field.replace(/\./g, '_'); // ceo.last_name -> ceo_last_name
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
                    var partnerId = $('#edit-partner-id').val();
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
