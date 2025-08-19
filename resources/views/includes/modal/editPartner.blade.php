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
                            <label for="edit-address" class="form-label">Почтовый адрес</label>
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

                    {{-- Реквизиты --}}
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
                                <label for="edit-bank_account" class="form-label">Расчетный счет</label>
                                <input type="text" name="bank_account" class="form-control" id="edit-bank_account">
                                <p class="text-danger" id="edit-bank_account-error"></p>
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

{{--@include('includes.modal.confirmDeleteModal')--}}
{{--@include('includes.modal.successModal')--}}
{{--@include('includes.modal.errorModal')--}}

<script>
    $(document).ready(function() {
        // Открытие модалки и загрузка данных партнёра
        $('.edit-partner-link').on('click', function() {
            var partnerId = $(this).data('id');

            $.ajax({
                url: '/admin/partner/' + partnerId + '/edit',
                type: 'GET',
                dataType: 'json',
                success: function(response) {
                    $('#edit-partner-id').val(response.id);
                    $('#edit-business_type').val(response.business_type);
                    $('#edit-title').val(response.title);
                    $('#edit-tax_id').val(response.tax_id);
                    $('#edit-kpp').val(response.kpp);
                    $('#edit-registration_number').val(response.registration_number);
                    $('#edit-address').val(response.address);
                    $('#edit-phone').val(response.phone);
                    $('#edit-email').val(response.email);
                    $('#edit-website').val(response.website);
                    $('#edit-bank_name').val(response.bank_name);
                    $('#edit-bank_bik').val(response.bank_bik);
                    $('#edit-bank_account').val(response.bank_account);
                    $('#edit-order_by').val(response.order_by);
                    $('#edit-is_enabled').val(response.is_enabled ? '1' : '0');
                    $('#edit-title').siblings('label').text(
                        response.business_type === 'physical_person' ? 'ФИО*' : 'Наименование*'
                    );
                    $('#edit-business_type').trigger('change');

                    // сброс старых ошибок
                    $('#edit-partner-form p.text-danger').text('');
                    $('#edit-partner-form .is-invalid').removeClass('is-invalid');

                    $('#editPartnerModal').modal('show');
                },
                error: function() {
                    alert('Не удалось загрузить данные партнёра.');
                }
            });
        });

        // Отправка формы обновления через AJAX
        $('#update-partner-btn').on('click', function() {
            var partnerId = $('#edit-partner-id').val();
            var formData = $('#edit-partner-form').serialize();

            $.ajax({
                url: '/admin/partner/' + partnerId,
                type: 'PATCH',
                data: formData,
                success: function() {
                    showSuccessModal("Редактирование партнера", "Партнер успешно отредактирован.", 1);
                    $('#editPartnerModal').modal('hide');
                },
                error: function(xhr) {
                    if (xhr.status === 422) {
                        // выводим сообщения валидации под соответствующим полем
                        var errors = xhr.responseJSON.errors;
                        $.each(errors, function(field, messages) {
                            $('#edit-' + field + '-error').text(messages.join(' '));
                            $('#edit-' + field).addClass('is-invalid');
                        });
                    } else {
                        $('#errorModal').modal('show');
                    }
                }
            });
        });

        // Удаление партнёра
        $(document).on('click', '#delete-partner-btn', function () {
            deletePartner();
        });

        function deletePartner() {
            showConfirmDeleteModal(
                "Удаление партнёра",
                "Вы уверены, что хотите удалить партнёра?",
                function() {
                    var partnerId = $('#edit-partner-id').val();
                    $.ajax({
                        url: '/admin/partner/' + partnerId,
                        type: 'DELETE',
                        data: { _token: $('input[name="_token"]').val() },
                        success: function() {
                            showSuccessModal("Удаление партнёра", "Партнёр успешно удалён.", 1);
                            $('#editPartnerModal').modal('hide');
                        },
                        error: function() {
                            $('#errorModal').modal('show');
                        }
                    });
                }
            );
        }
    });
</script>
