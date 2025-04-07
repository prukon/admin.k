        <div class="tab-content" id="myTabContent">
            <!-- Контент вкладки "Права пользователей" -->
            <div class="tab-pane fade {{ $activeTab == 'rule' ? 'show active' : '' }}" id="profile" role="tabpanel">
                <div class="container-fluid">
                    <h4 class="pt-3 text-start">Права пользователей</h4>

                    <div class="table-responsive">
                        <hr>
                        <div class="col-12 d-flex justify-content-between align-items-center mb-3">
                            <button id="new-role" type="button" class="btn btn-primary new-role width-170"
                                    data-bs-toggle="modal"
                                    data-bs-target="#createRoleModal">
                                Настройки
                            </button>

                            <div class="wrap-icon btn" data-bs-toggle="modal" data-bs-target="#historyModal">
                                <i class="fa-solid fa-clock-rotate-left logs"></i>
                            </div>
                        </div>
                        <hr class="mb-3">

                        <table class="table table-bordered align-middle">
                            <thead>
                            <tr>
                                <th>Описание функционала</th>
                                @foreach($roles as $role)
                                    <th class="text-center">{{ $role->label ?? $role->name }}</th>
                                @endforeach
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($permissions as $permission)
                                <tr>
                                    {{--<td>--}}
                                        {{--{{ $permission->description ?? $permission->name }}--}}
                                    {{--</td>--}}

                                    <td>
                                        {{ $permission->description ?? $permission->name }}
                                        @if(auth()->user()?->role?->name === 'superadmin' && !$permission->is_visible)
                                            <span class="badge bg-warning text-dark ms-2">Невидимое</span>
                                        @endif
                                    </td>



                                @foreach($roles as $role)
                                        @php
                                            // Проверяем, есть ли у роли данное право
                                            $hasPermission = $role->permissions->contains($permission->id);
                                        @endphp
                                        <td class="text-center">
                                            <input type="checkbox"
                                                   class="permission-checkbox"
                                                   data-role-id="{{ $role->id }}"
                                                   data-permission-id="{{ $permission->id }}"
                                                   @checked($hasPermission)
                                            />
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>

                </div>
            </div>
        </div>

    <!-- Модальное окно создания и управления ролями -->
    <div class="modal fade" id="createRoleModal" tabindex="-1" aria-labelledby="createRoleModalLabel"
         aria-hidden="true">
        <!-- Уменьшили ширину, убрав modal-lg -->
        <div class="modal-dialog">
            <div class="modal-content">

                <div class="modal-header">
                    <h5 class="modal-title" id="createRoleModalLabel">Управление ролями</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
                </div>

                <div class="modal-body">

                    <!-- Форма создания новой роли (в 1 строку) -->
                    <form id="createRoleForm" class="d-flex align-items-center mb-3 gap-2">
                        @csrf
                        <label for="roleName" class="form-label mb-0">Название роли</label>
                        <input type="text" class="form-control" id="roleName" name="name" required style="width:auto;">
                        <button type="submit" class="btn btn-success">Создать роль</button>
                    </form>

                    <hr/>

                    <!-- Список ролей -->
                    <div class="table-responsive">
                        <table class="table table-hover align-middle" id="rolesTable">
                            <thead>
                            <tr>
                                <!-- Меняем "ID" на "№" -->
                                <th>№</th>
                                <th>Название</th>
                                <!-- Удалили "Системная?" столбец -->
                                <th>Удаление</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($roles as $index => $role)
                                <tr data-role-id="{{ $role->id }}">
                                    <!-- Выводим порядковый номер, начиная с 1 -->
                                    <td>{{ $index + 1 }}</td>
                                    <td>{{ $role->label ?? $role->name }}</td>
                                    <td>
                                        @if(!$role->is_sistem)
                                            <button class="btn btn-danger btn-sm delete-role"
                                                    data-role-id="{{ $role->id }}">
                                                Удалить
                                            </button>
                                        @else
                                            <small class="text-muted">Системная роль</small>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>

                </div><!-- modal-body -->

            </div>
        </div>
    </div>
    <!-- Конец модального окна -->

    <script>
        $(document).ready(function () {
            // Изменение прав
            $('.permission-checkbox').on('change', function () {
                let roleId = $(this).data('role-id');
                let permissionId = $(this).data('permission-id');
                let isChecked = $(this).is(':checked');

                $.ajax({
                    url: "{{ route('admin.setting.rule.toggle') }}",
                    type: "POST",
                    data: {
                        _token: "{{ csrf_token() }}",
                        role_id: roleId,
                        permission_id: permissionId,
                        value: isChecked ? 'true' : 'false'
                    },
                    success: (response) => {
                        if (response.success) {
                            $(this).closest('td').addClass('table-success');
                            setTimeout(() => {
                                $(this).closest('td').removeClass('table-success');
                            }, 2000);
                        } else {
                            alert('Ошибка при обновлении прав!');
                        }
                    },
                    error: () => {
                        alert('Ошибка при соединении!');
                    }
                });
            });

            // Форма создания новой роли
            $('#createRoleForm').on('submit', function (e) {
                e.preventDefault();
                let formData = $(this).serialize();

                $.ajax({
                    url: "{{ route('admin.setting.role.create') }}",
                    type: "POST",
                    data: formData,
                    success: (response) => {
                        if (response.success) {
                            showSuccessModal("Создание роли", "Роль успешно создана.", 0);

                            // Добавим новую роль в таблицу
                            let role = response.role;
                            // Найдём текущий размер таблицы
                            let rowCount = $('#rolesTable tbody tr').length;
                            // Прибавим 1 (чтобы новые шли в конец)
                            let newIndex = rowCount + 1;

                            let row = `
                                <tr data-role-id="${role.id}">
                                    <td>${newIndex}</td>
                                    <td>${role.label ?? role.name}</td>
                                    <td>
                                        ${
                                role.is_sistem == 1
                                    ? '<small class="text-muted">Системная</small>'
                                    : '<button class="btn btn-danger btn-sm delete-role" data-role-id="' + role.id + '">Удалить</button>'
                                }
                                    </td>
                                </tr>
                            `;
                            $('#rolesTable tbody').append(row);
                            // Очистим поле ввода
                            $('#roleName').val('');
                        } else {
                            $('#errorModal').modal('show');
                            $('#error-message').text(response.message || 'Ошибка при создании роли!');
                        }
                    },
                    error: (xhr) => {
                        if (xhr.responseJSON && xhr.responseJSON.errors) {
                            // Валидационные ошибки
                            let errors = xhr.responseJSON.errors;
                            alert(Object.values(errors).join("\n"));
                        } else {
                            $('#errorModal').modal('show');
                            $('#error-message').text(response.message || 'Ошибка при соединении!');
                        }
                    }
                });
            });

            // Удаление роли
            $(document).on('click', '.delete-role', function () {
                let roleId = $(this).data('role-id');

                showConfirmDeleteModal(
                    "Удаление роли",
                    "Вы уверены, что хотите удалить роль?",
                    function () {

                        $.ajax({
                            url: "{{ route('admin.setting.role.delete') }}",
                            type: "DELETE",
                            data: {
                                _token: "{{ csrf_token() }}",
                                role_id: roleId
                            },
                            success: (response) => {
                                if (response.success) {
                                    showSuccessModal("Удаление роли", "Роль успешно удалена.", 0);

                                    // Удаляем строку из таблицы
                                    $('#rolesTable tr[data-role-id="' + roleId + '"]').remove();
                                } else {
                                    $('#errorModal').modal('show');
                                    $('#error-message').text(response.message || 'Ошибка при удалении роли!');
                                }
                            },
                            error: () => {
                                $('#errorModal').modal('show');
                                $('#error-message').text(response.message || 'Ошибка при соединении!');
                            }
                        });
                    }
                );
            });

            // Логи (ваша функция)
            showLogModal("{{ route('logs.data.rule') }}");
        });
    </script>
{{--@endsection--}}
