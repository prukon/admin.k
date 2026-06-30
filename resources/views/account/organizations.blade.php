{{--ВКЛАДКА ОРГАНИЗАЦИЯ--}}
@can('account.partner.view')
    @can('account.partner.update')
        <form id="partnerUpdateForm"
              action="{{ route('admin.cur.partner.update', $partner->id) }}"
              method="POST">
            @csrf
            @method('PATCH')
            <div class="row text-start partner-form-wrap">
            <div class="col-12 mb-3">
                <div class="alert alert-info mb-0">
                    Реквизиты и данные юр. лица (ИНН, ОГРН, банк и т.д.) редактируются в справочнике
                    @can('legal_entities.view')
                        <a href="{{ route('admin.legal-entities.index') }}">«Юр. лица»</a>.
                    @else
                        «Юр. лица» (раздел доступен пользователям с соответствующими правами).
                    @endcan
                </div>
            </div>

            {{--Основные данные организации--}}
            <div class="col-12 col-lg-5 user-data-wrap mb-3">
                <h4>Основная информация</h4>

                {{-- Название школы / секции (бренд партнёра в CRM) --}}
                <div class="mb-3">
                    <label for="title" class="form-label">Название школы/секции*</label>
                    <input type="text" class="form-control" id="title" name="title"
                           value="{{ old('title', $partner->title) }}">
                    @error('title')
                    <p class="text-danger">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Телефон --}}
                <div class="mb-3">
                    <label for="phone" class="form-label">Телефон</label>
                    @include('includes.fields.phone-input', [
                        'name' => 'phone',
                        'id' => 'phone',
                        'value' => old('phone', $partner->phone),
                    ])
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

                {{-- Название для SMS/выписок (billingDescriptor T‑Bank) --}}
                <div class="mb-3">
                    <label for="sms_name" class="form-label">Название для SMS/выписок</label>
                    <input type="text" class="form-control" id="sms_name" name="sms_name" maxlength="14"
                           value="{{ old('sms_name', $partner->sms_name) }}"
                           placeholder="ИП IVANOV">
                    <div class="form-text">До 14 символов, латиница (A–Z, 0–9, пробел, <code>.-_</code>).</div>
                    @error('sms_name')
                    <p class="text-danger">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <hr>

                <div class="col-12 col-lg-5 user-data-wrap mb-3">
                    <button type="submit" class="btn btn-primary width-170 mb-3">Обновить данные</button>
                </div>
            </div>
        </form>
    @else
        <div class="alert alert-warning">
            У вас нет прав на изменение данных организации.
        </div>
    @endcan



@can('account.partner.update')
@section('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            $('#partnerUpdateForm').on('submit', function (e) {
                e.preventDefault();

                let form = $(this);
                let formData = form.serialize();

                $.ajax({
                    url: form.attr('action'),
                    type: 'PATCH',
                    data: formData,
                    success: function () {
                        showSuccessModal("Редактирование организации", "Данные организации успешно обновлены.", 1);
                    },
                    error: function (response) {
                        let errorMessage = 'Произошла ошибка при сохранении данных.';
                        if (response.responseJSON && response.responseJSON.message) {
                            errorMessage = response.responseJSON.message;
                        }
                        if (response.status === 422 && response.responseJSON && response.responseJSON.errors) {
                            const errors = response.responseJSON.errors;
                            const firstKey = Object.keys(errors)[0];
                            if (firstKey && errors[firstKey] && errors[firstKey][0]) {
                                errorMessage = errors[firstKey][0];
                            }
                        }
                        $('#error-modal-message').text(errorMessage);
                        $('#errorModal').modal('show');
                    }
                });
            });
        });
    </script>
@endsection
@endcan


@endcan
