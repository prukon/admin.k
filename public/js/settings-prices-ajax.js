document.addEventListener('DOMContentLoaded', function () {


    // /Получение списка пользователей
    // Находим все кнопки с классом 'detail'
    const detailButtons = document.querySelectorAll('.detail');
// Добавляем обработчик события на каждую кнопку
    for (let i = 0; i < detailButtons.length; i++) {
        let button = detailButtons[i];

        button.addEventListener('click', function () {
            document.querySelector('#right_bar .btn-setting-prices').setAttribute('disabled', 'disabled');
            // Находим родительский div (родителя с классом 'wrap-team')
            const parentDiv = this.closest('.wrap-team');
            // Выводим id родительского div в консоль
            if (parentDiv) {
                $.ajax({
                    url: '/get-team-price',
                    type: 'GET',
                    data: {teamId: parentDiv.id},

                    success: function (response) {
                        if (response.success) {
                            var usersTeam = response.usersTeam;
                            // let users = data; // Предполагается, что ответ - это массив объектов
                            let rightBar = $('.wrap-users'); // Получаем элемент с id right_bar
                            rightBar.empty(); // Очищаем список юзеров перед вставкой новых данных
                            usersTeam.forEach(function (user) {
                                let userBlock = `
                <div class="row mb-2">
                    <div class="user-name col-6">${user.name}</div>
                    <div class="user-price col-4"><input class="" type="number" value="7050"></div>
                    <div class="check col-2">
                        <span class="fa fa-check display-none green-check" style="display: inline;" aria-hidden="true"><span></span></span>
                    </div>
                </div>
            `;
                                rightBar.append(userBlock); // Добавляем каждый блок с пользователем внутрь right_bar
                                document.querySelector('#right_bar .btn-setting-prices').removeAttribute('disabled');


                            });

                        }
                    }

                });
            }

        });
    }

    // Обработчик изменения значения select2
    $('#single-select-user').on('change', function () {
        document.querySelector('#left_bar .btn-setting-prices').setAttribute('disabled', 'disabled');
        let selectedMonth = $(this).val();
        // Отправка AJAX запроса на сервер
        $.ajax({
            url: '/update-date', // Укажите правильный URL вашего маршрута
            method: 'GET',
            data: {
                month: selectedMonth,
                // _token: '{{ csrf_token() }}'
            },
            success: function (response) {
                // Здесь вы можете выполнить любые действия после успешного запроса
                document.querySelector('#left_bar .btn-setting-prices').removeAttribute('disabled');
            },
            error: function (xhr, status, error) {
                console.log('Error:', error);
            }
        });
    });

});

