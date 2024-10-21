<!-- Modal -->
<div class="modal fade" id="createUserModal" tabindex="-1" aria-labelledby="createUserModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="createUserModalLabel">Создание пользователя</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="create-user-form" method="post" action="{{ route('admin.user.store') }}">
                    @csrf
                    <div class="mb-3">
                        <label for="name" class="form-label">Имя ученика*</label>
                        <input type="text" name="name" class="form-control" id="name">
                    </div>
                    <div class="mb-3">
                        <label for="birthday" class="form-label">Дата рождения</label>
                        <input type="date" name="birthday" class="form-control" id="birthday">
                    </div>
                    <div class="mb-3">
                        <label for="team" class="form-label">Группа</label>
                        <select name="team_id" id="team" class="form-select">
                            <option value="" selected>Без группы</option>
                            @foreach($allTeams as $team)
                                <option value="{{ $team->id }}">{{ $team->title }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="email" class="form-label">Email*</label>
                        <input type="email" name="email" class="form-control" id="email">
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label">Пароль*</label>
                        <input type="password" name="password" class="form-control" id="password">
                    </div>
                    <div class="mb-3">
                        <label for="is_enabled" class="form-label">Активность</label>
                        <select name="is_enabled" class="form-select" id="is_enabled">
                            <option value="1">Да</option>
                            <option value="0">Нет</option>
                        </select>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Закрыть</button>
                        <button type="submit" class="btn btn-primary">Создать</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<script>


    // $(document).ready(function () {
    //     $('#create-user-form').on('submit', function (e) {
    //         e.preventDefault();
    //
    //         // Очищаем предыдущие ошибки
    //         $('.is-invalid').removeClass('is-invalid');
    //         $('.invalid-feedback').remove();
    //
    //         $.ajax({
    //             url: $(this).attr('action'),
    //             method: $(this).attr('method'),
    //             data: $(this).serialize(),
    //             success: function (response) {
    //                 // Закрываем модальное окно
    //                 $('#createUserModal').modal('hide');
    //
    //                 // Очищаем форму
    //                 $('#create-user-form')[0].reset();
    //
    //                 if (response.user) {
    //                     let newUser = `
    //                     <div class="user">
    //                         <a href="/admin/user/edit/${response.user.id}" style="${response.user.is_enabled === 'Нет' ? 'color: red;' : ''}">
    //                             0. ${response.user.name} <!-- Временно 0, пересчитаем позже -->
    //                         </a>
    //                     </div>
    //                 `;
    //
    //                     // Найдем место для вставки нового пользователя по алфавиту
    //                     let inserted = false;
    //
    //                     $('.wrap-user-list .user').each(function () {
    //                         let currentUserName = $(this).text().trim().split('. ')[1]; // Получаем имя пользователя без порядкового номера
    //
    //                         if (response.user.name.localeCompare(currentUserName) < 0) {
    //                             $(this).before(newUser);
    //                             inserted = true;
    //                             return false; // Прерываем цикл
    //                         }
    //                     });
    //
    //                     // Если не нашли место, добавляем в конец
    //                     if (!inserted) {
    //                         $('.wrap-user-list').append(newUser);
    //                     }
    //
    //                     // Пересчитываем порядковые номера после вставки
    //                     $('.wrap-user-list .user').each(function (index) {
    //                         $(this).find('a').html((index + 1) + '. ' + $(this).find('a').text().split('. ')[1]);
    //                     });
    //                 }
    //
    //                 // Показываем сообщение об успехе (опционально)
    //                 alert(response.message);
    //             },
    //             error: function (response) {
    //                 // Обрабатываем ошибки валидации
    //                 let errors = response.responseJSON.errors;
    //                 if (errors) {
    //                     for (let field in errors) {
    //                         $(`#${field}`).addClass('is-invalid');
    //                         $(`#${field}`).after(`<div class="invalid-feedback">${errors[field][0]}</div>`);
    //                     }
    //                 }
    //             }
    //         });
    //     });
    // });
    // $(document).ready(function () {
    //     $('#create-user-form').on('submit', function (e) {
    //         e.preventDefault();
    //
    //         // Очищаем предыдущие ошибки
    //         $('.is-invalid').removeClass('is-invalid');
    //         $('.invalid-feedback').remove();
    //
    //         $.ajax({
    //             url: $(this).attr('action'),
    //             method: $(this).attr('method'),
    //             data: $(this).serialize(),
    //             success: function (response) {
    //                 // Закрываем модальное окно
    //                 $('#createUserModal').modal('hide');
    //
    //                 // Очищаем форму
    //                 $('#create-user-form')[0].reset();
    //
    //                 if (response.user) {
    //                     let newUser = `
    //                     <div class="user">
    //                         <a href="https://test.kidslink.ru/admin/users/${response.user.id}/edit" style="${response.user.is_enabled === 'Нет' ? 'color: red;' : ''}">
    //                             0. ${response.user.name} <!-- Временно 0, пересчитаем позже -->
    //                         </a>
    //                     </div>
    //                 `;
    //
    //                     // Найдем место для вставки нового пользователя по алфавиту
    //                     let inserted = false;
    //
    //                     $('.wrap-user-list .user').each(function () {
    //                         let currentUserName = $(this).text().trim().split('. ')[1]; // Получаем имя пользователя без порядкового номера
    //
    //                         if (response.user.name.localeCompare(currentUserName) < 0) {
    //                             $(this).before(newUser);
    //                             inserted = true;
    //                             return false; // Прерываем цикл
    //                         }
    //                     });
    //
    //                     // Если не нашли место, добавляем в конец
    //                     if (!inserted) {
    //                         $('.wrap-user-list').append(newUser);
    //                     }
    //
    //                     // Пересчитываем порядковые номера после вставки
    //                     $('.wrap-user-list .user').each(function (index) {
    //                         $(this).find('a').html((index + 1) + '. ' + $(this).find('a').text().split('. ')[1]);
    //                     });
    //                 }
    //
    //                 // Показываем сообщение об успехе (опционально)
    //                 alert(response.message);
    //             },
    //             error: function (response) {
    //                 // Обрабатываем ошибки валидации
    //                 let errors = response.responseJSON.errors;
    //                 if (errors) {
    //                     for (let field in errors) {
    //                         $(`#${field}`).addClass('is-invalid');
    //                         $(`#${field}`).after(`<div class="invalid-feedback">${errors[field][0]}</div>`);
    //                     }
    //                 }
    //             }
    //         });
    //     });
    // });
    // $(document).ready(function () {
    //     $('#create-user-form').on('submit', function (e) {
    //         e.preventDefault();
    //
    //         // Очищаем предыдущие ошибки
    //         $('.is-invalid').removeClass('is-invalid');
    //         $('.invalid-feedback').remove();
    //
    //         $.ajax({
    //             url: $(this).attr('action'),
    //             method: $(this).attr('method'),
    //             data: $(this).serialize(),
    //             success: function (response) {
    //                 // Закрываем модальное окно
    //                 $('#createUserModal').modal('hide');
    //
    //                 // Очищаем форму
    //                 $('#create-user-form')[0].reset();
    //
    //                 if (response.user) {
    //                     // Формируем HTML-код нового пользователя, без временного номера
    //                     let newUser = `
    //                     <div class="user">
    //                         <a href="https://test.kidslink.ru/admin/users/${response.user.id}/edit" style="${response.user.is_enabled === 'Нет' ? 'color: red;' : ''}">
    //                             ${response.user.name}
    //                         </a>
    //                     </div>
    //                 `;
    //
    //                     // Найдем место для вставки нового пользователя по алфавиту
    //                     let inserted = false;
    //
    //                     $('.wrap-user-list .user').each(function () {
    //                         let currentUserName = $(this).text().trim().split('. ')[1]; // Получаем имя пользователя без порядкового номера
    //
    //                         // Если имя нового пользователя по алфавиту меньше текущего, вставляем перед ним
    //                         if (response.user.name.localeCompare(currentUserName) < 0) {
    //                             $(this).before(newUser);
    //                             inserted = true;
    //                             return false; // Прерываем цикл
    //                         }
    //                     });
    //
    //                     // Если не нашли место, добавляем в конец списка
    //                     if (!inserted) {
    //                         $('.wrap-user-list').append(newUser);
    //                     }
    //
    //                     // Пересчитываем порядковые номера после вставки
    //                     $('.wrap-user-list .user').each(function (index) {
    //                         let userName = $(this).find('a').text().split('. ')[1]; // Извлекаем имя пользователя
    //                         $(this).find('a').html((index + 1) + '. ' + userName);  // Назначаем новый порядковый номер
    //                     });
    //                 }
    //
    //                 // Показываем сообщение об успехе (опционально)
    //                 alert(response.message);
    //             },
    //             error: function (response) {
    //                 // Обрабатываем ошибки валидации
    //                 let errors = response.responseJSON.errors;
    //                 if (errors) {
    //                     for (let field in errors) {
    //                         $(`#${field}`).addClass('is-invalid');
    //                         $(`#${field}`).after(`<div class="invalid-feedback">${errors[field][0]}</div>`);
    //                     }
    //                 }
    //             }
    //         });
    //     });
    // });


    $(document).ready(function () {
        $('#create-user-form').on('submit', function (e) {
            e.preventDefault();

            // Очищаем предыдущие ошибки
            $('.is-invalid').removeClass('is-invalid');
            $('.invalid-feedback').remove();

            $.ajax({
                url: $(this).attr('action'),
                method: $(this).attr('method'),
                data: $(this).serialize(),
                success: function (response) {
                    // Закрываем модальное окно
                    $('#createUserModal').modal('hide');

                    // Очищаем форму
                    $('#create-user-form')[0].reset();

                    if (response.user) {
                        // Формируем HTML-код нового пользователя
                        let newUser = `
                        <div class="user">
                            <a href="https://test.kidslink.ru/admin/users/${response.user.id}/edit" style="${response.user.is_enabled === 'Нет' ? 'color: red;' : ''}">
                                ${response.user.name}
                            </a>
                        </div>
                    `;

                        // Найдем место для вставки нового пользователя по алфавиту
                        let inserted = false;
                        let newUserElement;

                        // Используем jQuery метод .get().reverse() для обратного перебора
                        $('.wrap-user-list .user').get().reverse().forEach(function (element) {
                            if (!inserted) {  // Добавляем проверку, чтобы пользователь вставлялся только один раз
                                let currentUserName = $(element).find('a').text().trim(); // Получаем имя пользователя, включая номер

                                // Извлекаем имя пользователя, удаляя порядковый номер
                                let actualName = currentUserName.replace(/^\d+\.\s*/, ''); // Убираем порядковый номер и пробел

                                // Сравниваем имя нового пользователя с текущим пользователем
                                if (response.user.name.localeCompare(actualName) >= 0) {
                                    $(element).after(newUser); // Вставляем после текущего элемента
                                    newUserElement = $(element).next(); // Сохраняем новый элемент для дальнейших действий
                                    inserted = true; // Отмечаем, что пользователь был вставлен
                                }
                            }
                        });

                        // Если не нашли место, добавляем в начало списка (возможно это редкий случай)
                        if (!inserted) {
                            $('.wrap-user-list').prepend(newUser);
                            newUserElement = $('.wrap-user-list .user').first(); // Если в начало, то новый элемент — первый
                        }

                        // Удаляем порядковые номера
                        function removeOrdinalNumbers() {
                            const userLinks = document.querySelectorAll('.user a'); // выбираем все ссылки пользователей
                            userLinks.forEach(link => {
                                const text = link.innerHTML.trim(); // получаем текст ссылки
                                const newText = text.replace(/^\d+\.\s*/, ''); // удаляем порядковый номер в начале
                                link.innerHTML = newText; // устанавливаем новый текст ссылки
                            });
                        }

                        // Вставялем порядковые номера
                        function addOrdinalNumbersToUsers() {
                            $('.wrap-user-list .user').each(function (index) {
                                var currentText = $(this).find('a').text();
                                $(this).find('a').text((index + 1) + '. ' + currentText);
                            });
                        }

                        // Вызов функции
                        removeOrdinalNumbers();
                        addOrdinalNumbersToUsers();
                    }

                    // Показываем сообщение об успехе (опционально)
                    alert(response.message);
                },
                error: function (response) {
                    // Обрабатываем ошибки валидации
                    let errors = response.responseJSON.errors;
                    if (errors) {
                        for (let field in errors) {
                            $(`#${field}`).addClass('is-invalid');
                            $(`#${field}`).after(`<div class="invalid-feedback">${errors[field][0]}</div>`);
                        }
                    }
                }
            });
        });
    });


</script>