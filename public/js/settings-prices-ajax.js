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
                            let rightBar = $('.wrap-users');
                            rightBar.empty();
                            usersTeam.forEach(function (user) {
                                let userBlock = `
                <div class="row mb-2">
                    <div class="user-name col-6">${user.name}</div>
                    <div class="user-price col-4"><input class="" type="number" value="7050"></div>
                    <div class="check col-2"><span class="fa fa-check display-none green-check" aria-hidden="true"></span></div>
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

    // Обработчик изменения даты
    $('#single-select-user').on('change', function () {
        document.querySelector('#left_bar .btn-setting-prices').setAttribute('disabled', 'disabled');
        let selectedMonth = $(this).val();
        $.ajax({
            url: '/update-date',
            method: 'GET',
            data: {
                month: selectedMonth,
                // _token: '{{ csrf_token() }}'
            },
            success: function (response) {
                document.querySelector('#left_bar .btn-setting-prices').removeAttribute('disabled');
                location.reload();
            },
            error: function (xhr, status, error) {
                console.log('Error:', error);
            }
        });
    });

});

