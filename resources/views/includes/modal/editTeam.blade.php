<!-- Модальное окно для редактирования группы -->
<div class="modal fade" id="editTeamModal" tabindex="-1" aria-labelledby="editTeamModalLabel">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editTeamModalLabel">Редактирование группы</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="edit-team-form" class="text-start" method="post">
                    @csrf
                    @method('patch')
                    <input type="hidden" id="edit-team-id">

                    <!-- Название группы -->
                    <div class="mb-3">
                        <label for="edit-title" class="form-label">Название группы*</label>
                        <input type="text" name="title" class="form-control" id="edit-title">
                        <div class="invalid-feedback" id="edit-title-error"></div>
                    </div>

                    @can('sport_types.view')
                    @if($sportTypeOptions->isNotEmpty())
                    <div class="mb-3">
                        <label for="edit-sport-type-id" class="form-label">Вид спорта</label>
                        <select name="sport_type_id" class="form-select" id="edit-sport-type-id">
                            <option value="">— Не выбран —</option>
                            @foreach($sportTypeOptions as $sportType)
                                <option value="{{ $sportType->id }}">{{ $sportType->name }}</option>
                            @endforeach
                        </select>
                        <div class="invalid-feedback" id="edit-sport-type-id-error"></div>
                    </div>
                    @endif
                    @endcan

                    <div class="mb-3">
                        <label for="edit-default_duration_minutes" class="form-label">Длительность по умолчанию (мин)</label>
                        <input type="number" min="1" max="600" name="default_duration_minutes" class="form-control" id="edit-default_duration_minutes">
                        <div class="invalid-feedback" id="edit-default_duration_minutes-error"></div>
                    </div>

                    <div class="mb-3">
                        <label for="edit-month_price" class="form-label">Стоимость в месяц</label>
                        <input type="number" min="0" step="1" name="month_price" class="form-control" id="edit-month_price">
                        <div class="invalid-feedback" id="edit-month_price-error"></div>
                    </div>

                    @can('schedule.view')
                    <!-- Расписание -->
                    <div class="mb-3">
                        <label for="edit-weekdays" class="form-label">Расписание</label>
                        <div id="edit-weekdays"></div>
                        <p class="text-danger" id="edit-weekdays-error"></p>
                    </div>
                    @endcan

                    @can('locations.view')
                    @if($locationOptions->isNotEmpty())
                    <div class="mb-3">
                        <label class="form-label" for="edit-location-id">Объект</label>
                        <select id="edit-location-id"
                                name="location_id"
                                class="form-select">
                            <option value="">— Не выбран —</option>
                            @foreach($locationOptions as $location)
                                <option value="{{ $location->id }}">{{ $location->name }}</option>
                            @endforeach
                        </select>
                        <div class="form-text">Группа без объекта не показывается на лендинге заявки.</div>
                        <div class="invalid-feedback d-block" data-error-for="location_id" id="edit-location_id-error"></div>
                    </div>
                    @endif
                    @endcan

                    @can('trainers.view')
                    <div class="mb-3">
                        <label for="edit-trainer-profile-id" class="form-label">Тренер</label>
                        <select name="trainer_profile_id" class="form-select" id="edit-trainer-profile-id">
                            <option value="">Без тренера</option>
                            @foreach($trainerOptions as $trainer)
                                <option value="{{ $trainer->id }}">{{ $trainer->user?->full_name }}</option>
                            @endforeach
                        </select>
                        <div class="invalid-feedback" id="edit-trainer-profile-id-error"></div>
                    </div>
                    @endcan

                    <div class="mb-3">
                        <label for="edit-order_by" class="form-label">Сортировка</label>
                        <input type="text" name="order_by" class="form-control" id="edit-order_by">
                    </div>

                    <div class="mb-3">
                        <label for="edit-activity">Активность</label>
                        <select name="is_enabled" class="form-control" id="edit-activity">
                            <option value="0">Неактивен</option>
                            <option value="1">Активен</option>
                        </select>
                    </div>

                    <hr>
                    <div class="buttons-wrap mb-3">
                        <button type="button" class="btn btn-primary mr-2" id="update-team-btn">Обновить</button>
                        <button type="button"
                                class="btn btn-danger confirm-delete-modal"
                                id="delete-team-btn"
                                data-bs-toggle="modal"
                                data-bs-target="#deleteConfirmationModal">
                            Удалить
                        </button>
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
    $(document).ready(function() {
        console.log('Debug: edit-team script (jQuery) initialized');

        const canViewTeamSchedule = @json(auth()->user()->can('schedule.view'));
        const csrfToken = $('meta[name="csrf-token"]').attr('content');

        /**
         * Открытие модалки редактирования:
         * используем делегированный обработчик, чтобы работало с динамическими
         * элементами DataTables (.edit-team-link рендерится через AJAX).
         */
        $(document).on('click', '.edit-team-link', function() {
            const teamId = $(this).data('id');

            $.ajax({
                url: `/admin/team/${teamId}/edit`,
                type: 'GET',
                success: function(response) {
                    // Основные поля
                    $('#edit-team-id').val(response.id);
                    $('#edit-title').val(response.title);
                    $('#edit-default_duration_minutes').val(response.default_duration_minutes ?? '');
                    $('#edit-month_price').val(response.month_price ?? '');
                    $('#edit-order_by').val(response.order_by ?? '');
                    $('#edit-activity').val(response.is_enabled);

                    if ($('#edit-trainer-profile-id').length) {
                        $('#edit-trainer-profile-id').val(response.trainer_profile_id ?? '');
                    }

                    if ($('#edit-sport-type-id').length) {
                        $('#edit-sport-type-id').val(response.sport_type_id ?? '');
                    }

                    if ($('#edit-location-id').length) {
                        $('#edit-location-id').val(response.location_id ?? '');
                    }
                    $('#edit-location_id-error').text('');

                    // Расписание: чекбоксы дней недели
                    if (canViewTeamSchedule && $('#edit-weekdays').length) {
                        let weekdaysHtml = '';

                        if (response.weekdays && response.team_weekdays) {
                            response.weekdays.forEach(function(weekday) {
                                const isChecked = response.team_weekdays.some(function(teamWeekday) {
                                    return teamWeekday.id === weekday.id;
                                }) ? 'checked' : '';

                                weekdaysHtml += `
                                    <div class="form-check mb-2">
                                        <input class="form-check-input"
                                               type="checkbox"
                                               id="edit-weekday-${weekday.id}"
                                               name="weekdays[]"
                                               value="${weekday.id}" ${isChecked}>
                                        <label class="form-check-label" for="edit-weekday-${weekday.id}">
                                            ${weekday.title}
                                        </label>
                                    </div>
                                `;
                            });
                        }

                        $('#edit-weekdays').html(weekdaysHtml);
                    }

                    // Открываем модалку
                    $('#editTeamModal').modal('show');
                },
                error: function(xhr) {
                    console.error('Ошибка загрузки данных группы', xhr);
                    $('#errorModal').modal('show');
                }
            });
        });

        /**
         * Обновление группы
         */
        $('#update-team-btn').on('click', function() {
            const teamId = $('#edit-team-id').val();
            const formData = $('#edit-team-form').serialize();

            // Сброс ошибок
            $('#edit-title').removeClass('is-invalid');
            $('#edit-title-error').text('');
            $('#edit-default_duration_minutes').removeClass('is-invalid');
            $('#edit-default_duration_minutes-error').text('');
            $('#edit-month_price').removeClass('is-invalid');
            $('#edit-month_price-error').text('');
            $('#edit-trainer-profile-id').removeClass('is-invalid');
            $('#edit-trainer-profile-id-error').text('');
            $('#edit-sport-type-id').removeClass('is-invalid');
            $('#edit-sport-type-id-error').text('');
            $('#edit-location_id-error').text('');
            $('#edit-location-id').removeClass('is-invalid');

            $.ajax({
                url: `/admin/team/${teamId}`,
                type: 'PATCH',
                data: formData,
                success: function(response) {
                    showSuccessModal("Редактирование группы", "Группа успешно отредактирована.", 1);

                    // Обновляем таблицу DataTables, если она инициализирована
                    if ($.fn.DataTable.isDataTable('#teams-table')) {
                        $('#teams-table').DataTable().ajax.reload(null, false);
                    }
                },
                error: function(xhr) {
                    if (xhr.status === 422 && xhr.responseJSON && xhr.responseJSON.errors) {
                        const errors = xhr.responseJSON.errors;
                        if (errors.title && errors.title.length) {
                            $('#edit-title').addClass('is-invalid');
                            $('#edit-title-error').text(errors.title[0]);
                        }
                        if (errors.default_duration_minutes && errors.default_duration_minutes.length) {
                            $('#edit-default_duration_minutes').addClass('is-invalid');
                            $('#edit-default_duration_minutes-error').text(errors.default_duration_minutes[0]);
                        }
                        if (errors.month_price && errors.month_price.length) {
                            $('#edit-month_price').addClass('is-invalid');
                            $('#edit-month_price-error').text(errors.month_price[0]);
                        }
                        if (errors.trainer_profile_id && errors.trainer_profile_id.length) {
                            $('#edit-trainer-profile-id').addClass('is-invalid');
                            $('#edit-trainer-profile-id-error').text(errors.trainer_profile_id[0]);
                        }
                        if (errors.sport_type_id && errors.sport_type_id.length) {
                            $('#edit-sport-type-id').addClass('is-invalid');
                            $('#edit-sport-type-id-error').text(errors.sport_type_id[0]);
                        }
                        if (errors.location_id && errors.location_id.length) {
                            $('#edit-location-id').addClass('is-invalid');
                            $('#edit-location_id-error').text(errors.location_id[0]);
                        }
                        return;
                    }

                    $('#errorModal').modal('show');
                }
            });
        });

        /**
         * Клик по кнопке "Удалить" в модалке редактирования
         * (кнопка имеет класс .confirm-delete-modal, как и раньше)
         */
        $(document).on('click', '.confirm-delete-modal', function () {
            deleteTeam();
        });

        /**
         * Удаление группы с подтверждением
         */
        function deleteTeam() {
            showConfirmDeleteModal(
                "Удаление группы",
                "Вы уверены, что хотите удалить группу?",
                function() {
                    const teamId = $('#edit-team-id').val();

                    $.ajax({
                        url: `/admin/team/${teamId}`,
                        type: 'DELETE',
                        data: {
                            _token: csrfToken
                        },
                        success: function(response) {
                            showSuccessModal("Удаление группы", "Группа успешно удалена.", 1);

                            // Обновляем таблицу после удаления
                            if ($.fn.DataTable.isDataTable('#teams-table')) {
                                $('#teams-table').DataTable().ajax.reload(null, false);
                            }

                            $('#editTeamModal').modal('hide');
                        },
                        error: function(xhr) {
                            console.error('Ошибка удаления группы', xhr);
                            $('#errorModal').modal('show');
                        }
                    });
                }
            );
        }
    });
</script>
