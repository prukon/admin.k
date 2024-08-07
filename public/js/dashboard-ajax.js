document.addEventListener('DOMContentLoaded', function () {

    // $(document).ready(function() {
    $('#single-select-field').change(function() {
        var userName = $(this).val();

        $.ajax({
            url: '/get-user-details',
            type: 'GET',
            data: { name: userName },
            success: function(response) {
                if (response.success) {
                    var user = response.data;
                    // Обновите данные на странице
                    $('#user-details').html(
                        '<p>Name: ' + user.name + '</p>' +
                        '<p>Email: ' + user.email + '</p>' +
                        '<p>Created At: ' + user.created_at + '</p>'
                    );
                } else {
                    $('#user-details').html('<p>' + response.message + '</p>');
                }
            },
            error: function(xhr, status, error) {
                console.log(error);
            }
        });
    });
// });

});
