
<!-- Ваш HTML-контент, включая модальные окна и форму -->

<!-- Модальное окно для создания группы -->
<div class="modal fade" id="createTeamModal" tabindex="-1" aria-labelledby="createTeamModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="createTeamModalLabel">Создание группы</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
            </div>
            <div class="modal-body">
                <form id="teamForm" class="text-start" action="{{ route('admin.team.store') }}" method="post">
                    @csrf
                    <div class="mb-3">
                        <label for="title" class="form-label">Название группы*</label>
                        <input type="text"
                               name="title"
                               class="form-control @error('title') is-invalid @enderror"
                               id="title"
                               value="{{ old('title') }}">
                        <div id="title-error" class="invalid-feedback">
                            @error('title'){{ $message }}@enderror
                        </div>
                    </div>

                    @can('groups.training_base.view')
                    <div class="mb-3">
                        <label for="training_base" class="form-label">Тренировочная база</label>
                        <input type="text"
                               name="training_base"
                               class="form-control"
                               id="training_base"
                               value="{{ old('training_base') }}">
                        <div id="training_base-error" class="invalid-feedback"></div>
                    </div>
                    @endcan

                    @can('groups.address.view')
                    <div class="mb-3">
                        <label for="address" class="form-label">Адрес</label>
                        <input type="text"
                               name="address"
                               class="form-control"
                               id="address"
                               value="{{ old('address') }}">
                        <div id="address-error" class="invalid-feedback"></div>
                    </div>
                    @endcan

                    @can('sport_types.view')
                    @if($sportTypeOptions->isNotEmpty())
                    <div class="mb-3">
                        <label for="sport_type_id" class="form-label">Вид спорта</label>
                        <select name="sport_type_id" class="form-select" id="sport_type_id">
                            <option value="">— Не выбран —</option>
                            @foreach($sportTypeOptions as $sportType)
                                <option value="{{ $sportType->id }}">{{ $sportType->name }}</option>
                            @endforeach
                        </select>
                        <div id="sport_type_id-error" class="invalid-feedback"></div>
                    </div>
                    @endif
                    @endcan

                    <div class="mb-3">
                        <label for="default_duration_minutes" class="form-label">Длительность по умолчанию (мин)</label>
                        <input type="number"
                               min="1"
                               max="600"
                               name="default_duration_minutes"
                               class="form-control"
                               id="default_duration_minutes"
                               value="{{ old('default_duration_minutes') }}">
                        <div id="default_duration_minutes-error" class="invalid-feedback"></div>
                    </div>

                    <div class="mb-3">
                        <label for="month_price" class="form-label">Стоимость в месяц</label>
                        <input type="number"
                               min="0"
                               step="1"
                               name="month_price"
                               class="form-control"
                               id="month_price"
                               value="{{ old('month_price') }}">
                        <div id="month_price-error" class="invalid-feedback"></div>
                    </div>

                    @can('schedule.view')
                    <div class="mb-3">
                        <div class="form-group">
                            <label for="weekdays">Расписание*</label>
                            <div id="weekdays">
                                @foreach($weekdays as $weekday)
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="weekday-{{ $weekday->id }}" name="weekdays[]" value="{{ $weekday->id }}">
                                        <label class="form-check-label" for="weekday-{{ $weekday->id }}">
                                            {{ $weekday->title }}
                                        </label>
                                    </div>
                                @endforeach
                            </div>
                            @error('weekdays')
                            <p class="text-danger">{{ 'Укажите дни недели' }}</p>
                            @enderror
                        </div>
                    </div>
                    @endcan

                    @can('locations.view')
                    @if($locationOptions->isNotEmpty())
                    <div class="mb-3 generic-multiselect-field">
                        <label class="form-label" for="createTeamLocationIds">Локации</label>
                        <select id="createTeamLocationIds"
                                name="location_ids[]"
                                class="form-select js-generic-multiselect-select"
                                multiple
                                data-placeholder="Выберите локации">
                            @foreach($locationOptions as $location)
                                <option value="{{ $location->id }}">{{ $location->name }}</option>
                            @endforeach
                        </select>
                        <div class="form-text">Если не выбрано ни одной локации, группа доступна во всех локациях.</div>
                        <div class="invalid-feedback d-block" data-error-for="location_ids" id="location_ids-error"></div>
                    </div>
                    @endif
                    @endcan

                    @can('trainers.view')
                    <div class="mb-3">
                        <label for="trainer_profile_id" class="form-label">Тренер</label>
                        <select name="trainer_profile_id" class="form-select" id="trainer_profile_id">
                            <option value="">Без тренера</option>
                            @foreach($trainerOptions as $trainer)
                                <option value="{{ $trainer->id }}">{{ $trainer->user?->full_name }}</option>
                            @endforeach
                        </select>
                        <div id="trainer_profile_id-error" class="invalid-feedback"></div>
                    </div>
                    @endcan

                    <div class="mb-3">
                        <label for="order_by" class="form-label">Сортировка</label>
                        <input type="text" name="order_by" placeholder="10" class="form-control" id="order_by" value="{{ old('order_by') }}">
                    </div>

                    <div class="mb-3">
                        <label for="activity">Активность</label>
                        <select name="is_enabled" class="form-control" id='activity'>
                            <option value="1">Активен</option>
                            <option value="0">Неактивен</option>
                        </select>
                    </div>

                    <div class="modal-footer-create-team">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Закрыть</button>
                        <button type="submit" class="btn btn-primary">Создать</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<!-- Модальное окно подтверждения удаления -->
{{--@include('includes.modal.confirmDeleteModal')--}}

<!-- Модальное окно успешного обновления данных -->
{{--@include('includes.modal.successModal')--}}

<!-- Модальное окно ошибки -->
{{--@include('includes.modal.errorModal')--}}


<script>
    document.addEventListener('DOMContentLoaded', function () {
        const createTeamLocationsEl = document.getElementById('createTeamLocationIds');
        const $createTeamLocationsSelect = (createTeamLocationsEl && window.jQuery)
            ? window.jQuery(createTeamLocationsEl)
            : null;

        if ($createTeamLocationsSelect && $createTeamLocationsSelect.length && window.KidsCrmGenericMultiselectSelect2) {
            KidsCrmGenericMultiselectSelect2.init($createTeamLocationsSelect, {
                dropdownParent: window.jQuery('#createTeamModal')
            });
        }

        function clearCreateTeamLocationErrors() {
            const locationIdsError = document.getElementById('location_ids-error');
            if (locationIdsError) {
                locationIdsError.textContent = '';
            }
            if ($createTeamLocationsSelect && window.KidsCrmGenericMultiselectSelect2) {
                KidsCrmGenericMultiselectSelect2.clearInvalid($createTeamLocationsSelect);
            }
        }

        function createTeam() {
            const teamForm = document.getElementById('teamForm');
            teamForm.addEventListener('submit', function (e) {
                e.preventDefault();  // Останавливаем стандартную отправку формы

                clearCreateTeamLocationErrors();

                // Собираем данные формы
                const formData = new FormData(teamForm);

                // Отправка данных с использованием AJAX
                fetch(teamForm.action, {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',  // Обозначаем, что это AJAX-запрос
                        'X-CSRF-TOKEN': document.querySelector('input[name="_token"]').value
                    }
                })
                    .then(response => {
                        return response.json().then(data => ({ ok: response.ok, status: response.status, data }));
                    })
                    .then(({ ok, status, data }) => {
                        // Сброс ошибок
                        const titleInput = document.getElementById('title');
                        const titleError = document.getElementById('title-error');
                        titleInput.classList.remove('is-invalid');
                        if (titleError) titleError.textContent = '';
                        const durationInput = document.getElementById('default_duration_minutes');
                        const durationError = document.getElementById('default_duration_minutes-error');
                        durationInput.classList.remove('is-invalid');
                        if (durationError) durationError.textContent = '';
                        const monthPriceInput = document.getElementById('month_price');
                        const monthPriceError = document.getElementById('month_price-error');
                        if (monthPriceInput) monthPriceInput.classList.remove('is-invalid');
                        if (monthPriceError) monthPriceError.textContent = '';

                        if (!ok && status === 422) {
                            const errors = data?.errors || {};
                            if (errors.title?.length) {
                                titleInput.classList.add('is-invalid');
                                if (titleError) titleError.textContent = errors.title[0];
                            }
                            if (errors.default_duration_minutes?.length) {
                                durationInput.classList.add('is-invalid');
                                if (durationError) durationError.textContent = errors.default_duration_minutes[0];
                            }
                            if (errors.month_price?.length) {
                                if (monthPriceInput) monthPriceInput.classList.add('is-invalid');
                                if (monthPriceError) monthPriceError.textContent = errors.month_price[0];
                            }
                            const trainingBaseInput = document.getElementById('training_base');
                            const trainingBaseError = document.getElementById('training_base-error');
                            if (trainingBaseInput) trainingBaseInput.classList.remove('is-invalid');
                            if (trainingBaseError) trainingBaseError.textContent = '';
                            if (errors.training_base?.length) {
                                if (trainingBaseInput) trainingBaseInput.classList.add('is-invalid');
                                if (trainingBaseError) trainingBaseError.textContent = errors.training_base[0];
                            }
                            const addressInput = document.getElementById('address');
                            const addressError = document.getElementById('address-error');
                            if (addressInput) addressInput.classList.remove('is-invalid');
                            if (addressError) addressError.textContent = '';
                            if (errors.address?.length) {
                                if (addressInput) addressInput.classList.add('is-invalid');
                                if (addressError) addressError.textContent = errors.address[0];
                            }
                            const trainerInput = document.getElementById('trainer_profile_id');
                            const trainerError = document.getElementById('trainer_profile_id-error');
                            if (trainerInput) trainerInput.classList.remove('is-invalid');
                            if (trainerError) trainerError.textContent = '';
                            if (errors.trainer_profile_id?.length) {
                                if (trainerInput) trainerInput.classList.add('is-invalid');
                                if (trainerError) trainerError.textContent = errors.trainer_profile_id[0];
                            }
                            const sportTypeInput = document.getElementById('sport_type_id');
                            const sportTypeError = document.getElementById('sport_type_id-error');
                            if (sportTypeInput) sportTypeInput.classList.remove('is-invalid');
                            if (sportTypeError) sportTypeError.textContent = '';
                            if (errors.sport_type_id?.length) {
                                if (sportTypeInput) sportTypeInput.classList.add('is-invalid');
                                if (sportTypeError) sportTypeError.textContent = errors.sport_type_id[0];
                            }
                            const locationIdsError = document.getElementById('location_ids-error');
                            const locationMessage = errors.location_ids?.[0]
                                || errors['location_ids.0']?.[0]
                                || null;
                            if (locationMessage && locationIdsError) {
                                locationIdsError.textContent = locationMessage;
                                if ($createTeamLocationsSelect && window.KidsCrmGenericMultiselectSelect2) {
                                    KidsCrmGenericMultiselectSelect2.markInvalid($createTeamLocationsSelect);
                                }
                            }
                            return;
                        }

                        if (!ok) {
                            throw new Error(`Ошибка HTTP: ${status}`);
                        }

                        if (data.message) {
                            teamForm.reset();
                            if ($createTeamLocationsSelect && window.KidsCrmGenericMultiselectSelect2) {
                                KidsCrmGenericMultiselectSelect2.reset($createTeamLocationsSelect);
                            }
                            showSuccessModal("Создание группы", "Группа успешно создана.", 1);
                        } else {
                            throw new Error('Произошла ошибка при создании группы.');
                        }
                    })
                    .catch(error => {
                        $('#errorModal').modal('show');
                    });
            });


        }
        createTeam();
    });
</script>
