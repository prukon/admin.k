document.addEventListener('DOMContentLoaded', function () {

    //    AJAX Активность регистрации
    $('#btnRegistrationActivity').on('click', function () {
        var isRegistrationActivity = document.getElementById('registrationActivity').checked;

        if (1 == 1) {
            $.ajax({
                url: '/admin/settings/registration-activity',
                type: 'GET',
                data: {
                    isRegistrationActivity: isRegistrationActivity,
                },

                success: function (response) {
                    if (response.success) {
                        var isRegistrationActivity = response.isRegistrationActivity;
                        console.log(isRegistrationActivity);
                    }
                }
            });
        }
    });

    //    AJAX Текст для юзеров
    $('#btnTextForUsers').on('click', function () {
        var textForUsers = document.getElementById('textForUsers').value;

        if (1 == 1) {
            $.ajax({
                url: '/admin/settings/text-for-users',
                type: 'GET',
                data: {
                    textForUsers: textForUsers,
                },

                success: function (response) {
                    if (response.success) {
                        var textForUsers = response.textForUsers;
                        console.log(textForUsers);
                    }
                }
            });
        }
    });

});

