{{--Cropie--}}
{{--<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/croppie/2.6.5/croppie.min.css">--}}
{{--<link rel="stylesheet" href="{{ asset('css/croppie.css') }}">--}}
{{--<script src="https://cdnjs.cloudflare.com/ajax/libs/croppie/2.6.5/croppie.min.js"></script>--}}


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
                        <button type="button" class="btn btn-danger confirm-delete-modal" id="delete-team-btn" data-bs-toggle="modal" data-bs-target="#deleteConfirmationModal">Удалить</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<!-- Модальное окно подтверждения удаления -->
@include('includes.modal.confirmDeleteModal')

<!-- Модальное окно успешного обновления данных -->
@include('includes.modal.successModal')

<!-- Модальное окно ошибки -->
@include('includes.modal.errorModal')

<script>

    $(document).ready(function() {
        $('.edit-team-link').on('click', function() {
            const teamId = $(this).data('id');

            // AJAX запрос для получения данных группы
            $.ajax({
                url: `/admin/team/${teamId}/edit`,
                type: 'GET',
                success: function(response) {
                    $('#edit-team-id').val(response.id);
                    $('#edit-title').val(response.title);
                    $('#edit-order_by').val(response.order_by);
                    $('#edit-activity').val(response.is_enabled);

                    // Обновляем расписание, создаем чекбоксы для каждого дня недели
                    let weekdaysHtml = '';
                    response.weekdays.forEach(function(weekday) {
                        const isChecked = response.team_weekdays.some(teamWeekday => teamWeekday.id === weekday.id) ? 'checked' : '';
                        weekdaysHtml += `
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" id="edit-weekday-${weekday.id}"
                               name="weekdays[]"
                               value="${weekday.id}" ${isChecked}>
                        <label class="form-check-label" for="edit-weekday-${weekday.id}">
                            ${weekday.title}
                        </label>
                    </div>`;
                    });
                    $('#edit-weekdays').html(weekdaysHtml);

                    // Показываем модальное окно
                    $('#editTeamModal').modal('show');
                }
            });
        });

        // Отправка формы обновления через AJAX
        $('#update-team-btn').on('click', function() {
            const teamId = $('#edit-team-id').val();
            const formData = $('#edit-team-form').serialize();

            $.ajax({
                url: `/admin/team/${teamId}`, // маршрут обновления
                type: 'PATCH',
                data: formData,
                success: function(response) {
                    $('#editTeamModal').modal('hide');
                    location.reload();
                },
                error: function(xhr) {
                    if (xhr.responseJSON && xhr.responseJSON.errors) {
                        $('#edit-title-error').text(xhr.responseJSON.errors.title || '');
                        $('#edit-weekdays-error').text(xhr.responseJSON.errors.weekdays || '');
                    }
                }
            });
        });


        // Вызов модалки удаления
        $(document).on('click', '.confirm-delete-modal', function () {
            deleteTeam();
        });

        //Удаление группы
        function deleteTeam() {
            // Показываем модалку с текстом и передаём колбэк, который удалит пользователя
            showConfirmDeleteModal(
                "Удаление группы",
                "Вы уверены, что хотите удалить группу?",
                function() {
                // ----
                    const teamId = $('#edit-team-id').val();
                    $.ajax({
                        url: `/admin/team/${teamId}`, // маршрут удаления
                        type: 'DELETE',
                        data: {
                            _token: $('input[name="_token"]').val()
                        },
                        success: function(response) {
                            $('#deleteConfirmationModal').modal('hide');
                            $('#editTeamModal').modal('hide');
                            location.reload();
                        },
                        error: function(xhr) {
                            alert('Ошибка при удалении группы.');
                        }
                    });
                    // ----
                }
            );
        }

    });
</script>


