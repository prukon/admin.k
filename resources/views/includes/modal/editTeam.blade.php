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
                        <p class="text-danger" id="edit-title-error"></p>
                    </div>

                    <!-- Расписание -->
                    <div class="mb-3">
                        <label for="edit-weekdays" class="form-label">Расписание</label>
                        <div id="edit-weekdays"></div>
                        <p class="text-danger" id="edit-weekdays-error"></p>
                    </div>

                    <!-- Сортировка -->
                    <div class="mb-3">
                        <label for="edit-order_by" class="form-label">Сортировка</label>
                        <input type="text" name="order_by" class="form-control" id="edit-order_by">
                    </div>

                    <!-- Активность -->
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
                    $('#edit-order_by').val(response.order_by ?? '');
                    $('#edit-activity').val(response.is_enabled);

                    // Расписание: чекбоксы дней недели
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
                    // Можно дополнительно разобрать ошибки валидации:
                    // const errors = xhr.responseJSON?.errors;
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
