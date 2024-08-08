document.addEventListener('DOMContentLoaded', function () {

    $('#single-select-field').change(function () {
        var userName = $(this).val();

        $.ajax({
            url: '/get-user-details',
            type: 'GET',
            data: {name: userName},

            success: function (response) {
                if (response.success) {
                    var user = response.data;
                    var userTeam = response.userTeam;

                    if (userTeam) {
                        $('.personal-data-value .group').html(userTeam.title);
                    } else
                        $('.personal-data-value .group').html('-');

                    if (user.birthday) {
                        $('.personal-data-value .birthday').html(user.birthday);
                    } else $('.personal-data-value .birthday').html("-");

                    $('.personal-data-value .count-training').html(123);
                    if (user.image_crop) {
                        $('.avatar_wrapper #confirm-img').attr('src', user.image_crop).attr('alt', user.name);
                    } else {
                        $('.avatar_wrapper #confirm-img').attr('src', '/img/default.png').attr('alt', 'avatar');
                    }
                } else {
                    $('#user-details').html('<p>' + response.message + '</p>');
                }
            },
            error: function (xhr, status, error) {
                console.log(error);
            }
        });
    });
});
