{{--ВКЛАДКА ОРГАНИЗАЦИЯ--}}
@can('partner-company')
    <form id="partnerUpdateForm"
          action="{{ route('admin.cur.partner.update', $partner->id) }}"
          method="POST">
        @csrf
        @method('PATCH')
        <div class="row text-start partner-form-wrap">
            {{--Основные данные организации--}}
            <div class="col-12 col-lg-5 user-data-wrap mb-3">
                <h4>Основная информация</h4>
                {{-- Тип бизнеса --}}
                <div class="mb-3">
                    <label for="business_type" class="form-label">Тип бизнеса*</label>
                    <select name="business_type" id="business_type" class="form-control">
                        <option value="company"
                                {{ old('business_type', $partner->business_type) === 'company' ? 'selected' : '' }}>
                            ООО
                        </option>
                        <option value="individual_entrepreneur"
                                {{ old('business_type', $partner->business_type) === 'individual_entrepreneur' ? 'selected' : '' }}>
                            ИП
                        </option>
                        <option value="non_commercial_organization"
                                {{ old('business_type', $partner->business_type) === 'non_commercial_organization' ? 'selected' : '' }}>
                            НКО
                        </option>
                        <option value="physical_person"
                                {{ old('business_type', $partner->business_type) === 'physical_person' ? 'selected' : '' }}>
                            Физическое лицо
                        </option>
                    </select>
                    @error('business_type')
                    <p class="text-danger">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Наименование (или ФИО) --}}
                <div class="mb-3">
                    <label for="title" class="form-label">Наименование*</label>
                    <input type="text" class="form-control" id="title" name="title"
                           value="{{ old('title', $partner->title) }}">
                    @error('title')
                    <p class="text-danger">{{ $message }}</p>
                    @enderror
                </div>

                {{-- ИНН --}}
                <div class="mb-3 " id='tax_id_wrapper'>
                    <label for="tax_id" class="form-label">ИНН</label>
                    <input type="text" class="form-control" id="tax_id" name="tax_id"
                           value="{{ old('tax_id', $partner->tax_id) }}">
                    @error('tax_id')
                    <p class="text-danger">{{ $message }}</p>
                    @enderror
                </div>

                {{-- КПП --}}
                <div class="mb-3 " id='kpp_wrapper'>
                    <label for="kpp" class="form-label">КПП</label>
                    <input type="text" class="form-control" id="kpp" name="kpp"
                           value="{{ old('kpp', $partner->kpp) }}">
                    @error('kpp')
                    <p class="text-danger">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Регистрационный номер --}}
                <div class="mb-3" id="registration_number_wrapper">
                    <label for="registration_number" class="form-label"
                           id="label-registration_number">ОГРН (ОГРНИП)</label>
                    <input type="text" class="form-control" id="registration_number" name="registration_number"
                           value="{{ old('registration_number', $partner->registration_number) }}">
                    @error('registration_number')
                    <p class="text-danger">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Адрес --}}
                <div class="mb-3">
                    <label for="address" class="form-label">Почтовый адрес</label>
                    <input type="text" class="form-control" id="address" name="address"
                           value="{{ old('address', $partner->address) }}">
                    @error('address')
                    <p class="text-danger">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Телефон --}}
                <div class="mb-3">
                    <label for="phone" class="form-label">Телефон</label>
                    <input type="text" class="form-control" id="phone" name="phone"
                           value="{{ old('phone', $partner->phone) }}">
                    @error('phone')
                    <p class="text-danger">{{ $message }}</p>
                    @enderror
                </div>

                {{-- E-mail партнёра --}}
                <div class="mb-3">
                    <label for="email-partner" class="form-label">E-mail партнёра*</label>
                    <input type="email" class="form-control" id="email-partner" name="email"
                           value="{{ old('email', $partner->email) }}">
                    @error('email')
                    <p class="text-danger">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Сайт --}}
                <div class="mb-3">
                    <label for="website" class="form-label">Сайт</label>
                    <input type="text" class="form-control" id="website" name="website"
                           value="{{ old('website', $partner->website) }}">
                    @error('website')
                    <p class="text-danger">{{ $message }}</p>
                    @enderror
                </div>
            </div>
            {{-- Реквизиты --}}
            <div class="col-12 col-lg-5 user-data-wrap ">
                <h4 id="requisites">Реквизиты</h4>
                <div id="bankFields">
                    <div class="mb-3">
                        <label for="bank_name" class="form-label">Наименование банка</label>
                        <input type="text" class="form-control" id="bank_name" name="bank_name"
                               value="{{ old('bank_name', $partner->bank_name) }}">
                        @error('bank_name')
                        <p class="text-danger">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label for="bank_bik" class="form-label">БИК</label>
                        <input type="text" class="form-control" id="bank_bik" name="bank_bik"
                               value="{{ old('bank_bik', $partner->bank_bik) }}">
                        @error('bank_bik')
                        <p class="text-danger">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label for="bank_account" class="form-label">Расчетный счет</label>
                        <input type="text" class="form-control" id="bank_account" name="bank_account"
                               value="{{ old('bank_account', $partner->bank_account) }}">
                        @error('bank_account')
                        <p class="text-danger">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
            </div>
            {{-- Кнопка отправки формы --}}

            <hr>


            <div class="col-12 col-lg-5 user-data-wrap mb-3">
                <button type="submit" class="btn btn-primary width-170 mb-3">Обновить данные</button>
            </div>
        </div>
    </form>



@section('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
//Обновление партнера
            function updatePartnerData() {
                $('#partnerUpdateForm').on('submit', function (e) {
                    e.preventDefault(); // Останавливаем стандартную отправку формы

                    let form = $(this);
                            {{--let formData = form.serialize(); // Сериализуем все поля (включая @csrf и @method)--}}
                    let formData = $(this).serialize();

                    $.ajax({
                        url: form.attr('action'),  // берем URL из action
                        type: 'PATCH',            // метод запроса
                        data: formData,
                        success: function (response) {
                            console.log('1');
                            showSuccessModal("Редактирование организации", "Данные организации успешно обновлены.", 1);
                        },
                        // error: function (xhr) {
                        //     if (xhr.status === 422) {
                        //         let errors = xhr.responseJSON.errors;
                        //         console.log(errors);
                        //     } else {
                        //         $('#errorModal').modal('show');
                        //     }
                        // }
                        error: function (response) {
                            let errorMessage = 'Произошла ошибка при сохранении данных.';
                            if (response.responseJSON && response.responseJSON.message) {
                                errorMessage = response.responseJSON.message; // Используем сообщение с сервера, если оно есть
                            }
                            $('#error-modal-message').text(errorMessage); // Устанавливаем сообщение ошибки
                            $('#errorModal').modal('show');    // Показываем модалку ошибки
                        }
                    });
                });
            }

            //Изменение полей формы органзиациии в зависимости от еее типа
            function toggleFields() {
                const businessType = $('#business_type').val();

                // При выборе "physical_person" скрываем поля tax_id и registration_number,
                // меняем заголовок поля title на "ФИО"
                if (businessType === 'physical_person') {
                    $('#tax_id_wrapper').hide();
                    $('#kpp_wrapper').hide();
                    $('#registration_number_wrapper').hide();
                    $('#requisites').hide();

                } else {
                    // Для остальных типов показываем поля tax_id и registration_number,
                    // и меняем заголовок поля title на "Наименование"
                    $('#tax_id_wrapper').show();
                    $('#kpp_wrapper').show();
                    $('#registration_number_wrapper').show();
                    $('#requisites').show();


                    console.log(businessType);
                    console.log($('#kpp_wrapper'));

                    // Меняем заголовок поля регистрации
                    if (businessType === 'company' || businessType === 'non_commercial_organization') {
                        $('#label-registration_number').text('ОГРН');
                        $('#kpp_wrapper').show();

                    } else if (businessType === 'individual_entrepreneur') {
                        $('#label-registration_number').text('ОГРНИП');
                        $('#kpp_wrapper').hide();
                    }
                }

                // Поля банка (показываем только для company, non_commercial_organization, individual_entrepreneur)
                if (businessType === 'physical_person') {
                    $('#bankFields').hide();

                } else {
                    $('#bankFields').show();

                }
            }

            // По изменению селекта — снова вызываем
            $('#business_type').change(function () {
                toggleFields();
            });

            updatePartnerData();
            toggleFields();
        });
    </script>
@endsection

@endcan