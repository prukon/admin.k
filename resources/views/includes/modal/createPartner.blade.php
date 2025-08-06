<!-- Модальное окно для создания партнёра -->
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

                        {{-- Почтовый адрес --}}
                        <div class="mb-3">
                            <label for="address" class="form-label">Почтовый адрес</label>
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

                    {{-- Реквизиты --}}
                    <div class="col-12 col-lg-6 mb-3">
                        <h4 id="requisites">Реквизиты</h4>
                        <div id="bankFields">
                            {{-- Наименование банка --}}
                            <div class="mb-3">
                                <label for="bank_name" class="form-label">Наименование банка</label>
                                <input type="text" class="form-control" id="bank_name" name="bank_name" value="{{ old('bank_name') }}">
                                <div class="text-danger error-bank_name"></div>
                            </div>
                            {{-- БИК --}}
                            <div class="mb-3">
                                <label for="bank_bik" class="form-label">БИК</label>
                                <input type="text" class="form-control" id="bank_bik" name="bank_bik" value="{{ old('bank_bik') }}">
                                <div class="text-danger error-bank_bik"></div>
                            </div>
                            {{-- Расчетный счет --}}
                            <div class="mb-3">
                                <label for="bank_account" class="form-label">Расчетный счет</label>
                                <input type="text" class="form-control" id="bank_account" name="bank_account" value="{{ old('bank_account') }}">
                                <div class="text-danger error-bank_account"></div>
                            </div>
                        </div>

                        {{-- Сортировка --}}
                        <div class="mb-3">
                            <label for="order_by" class="form-label">Сортировка</label>
                            <input type="number" name="order_by" class="form-control" id="order_by" value="{{ old('order_by', 0) }}">
                            <div class="text-danger error-order_by"></div>
                        </div>

                        {{-- Активность --}}
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

@include('includes.modal.confirmDeleteModal')
@include('includes.modal.successModal')
@include('includes.modal.errorModal')

@section('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const form = document.getElementById('partnerForm');

            form.addEventListener('submit', e => {
                e.preventDefault();

                // очищаем старые ошибки
                form.querySelectorAll('[class^="text-danger error-"]').forEach(div => div.textContent = '');
                form.querySelectorAll('.is-invalid').forEach(input => input.classList.remove('is-invalid'));

                const data = new FormData(form);
                fetch(form.action, {
                    method: 'POST',
                    body: data,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': form.querySelector('input[name="_token"]').value
                    }
                })
                    .then(res => {
                        if (res.status === 422) return res.json().then(json => { throw json.errors; });
                        if (!res.ok) throw { general: ['Ошибка сервера'] };
                        return res.json();
                    })
                    .then(json => {
                        showSuccessModal('Создание партнёра', json.message, 1);
                        // сброс формы
                        form.reset();
                        // скрываем модалку
                        const modal = bootstrap.Modal.getInstance(document.getElementById('createPartnerModal'));
                        modal.hide();
                    })
                    .catch(errors => {
                        // если errors — объект полей
                        Object.keys(errors).forEach(field => {
                            const input = form.querySelector(`[name="${field}"]`);
                            const errDiv = form.querySelector(`.error-${field}`);
                            if (input) input.classList.add('is-invalid');
                            if (errDiv) errDiv.textContent = errors[field].join(' ');
                        });
                        if (errors.general) {
                            $('#errorModal').modal('show');
                        }
                    });
            });

            // логика показа/скрытия полей и переименования лейбла «Наименование»
            function toggleFields() {
                const type = form.business_type.value;
                const isPP = type === 'physical_person';
                ['tax_id_wrapper','kpp_wrapper','registration_number_wrapper','requisites','bankFields']
                    .forEach(id => document.getElementById(id).style.display = isPP ? 'none' : '');
                document.getElementById('label-title').textContent = isPP ? 'ФИО*' : 'Наименование*';
            }

            form.business_type.addEventListener('change', toggleFields);
            toggleFields();
        });
    console.log('create partner')
    </script>

@endsection
