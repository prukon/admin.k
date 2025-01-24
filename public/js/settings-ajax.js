// document.addEventListener('DOMContentLoaded', function () {
//
//     // Установка CSRF-токена для всех AJAX-запросов
//     $.ajaxSetup({
//         headers: {
//             'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
//         }
//     });
//
//     // //    AJAX Активность регистрации
//     // $('#btnRegistrationActivity').on('click', function () {
//     //     var isRegistrationActivity = document.getElementById('registrationActivity').checked;
//     //
//     //     if (1 == 1) {
//     //         $.ajax({
//     //             url: '/admin/settings/registration-activity',
//     //             type: 'GET',
//     //             data: {
//     //                 isRegistrationActivity: isRegistrationActivity,
//     //             },
//     //
//     //             success: function (response) {
//     //                 if (response.success) {
//     //                     var isRegistrationActivity = response.isRegistrationActivity;
//     //                     console.log(isRegistrationActivity);
//     //                 }
//     //             }
//     //         });
//     //     }
//     // });
//
//     // //    AJAX Текст для юзеров
//     // $('#btnTextForUsers').on('click', function () {
//     //     var textForUsers = document.getElementById('textForUsers').value;
//     //     const textForUsersTextarea = document.querySelector('.textForUsers');
//     //     textForUsersTextarea.classList.remove('animated-input');
//     //
//     //     $.ajax({
//     //         url: '/admin/settings/text-for-users',
//     //         method: 'POST',
//     //         contentType: 'application/json', // Указываем тип контента JSON
//     //
//     //         data: JSON.stringify({
//     //             textForUsers: textForUsers,
//     //         }),
//     //
//     //         success: function (response) {
//     //             if (response.success) {
//     //                 var textForUsers = response.textForUsers;
//     //
//     //                 textForUsersTextarea.classList.add('animated-input');
//     //                 console.log(1);
//     //                 console.log(textForUsersTextarea);
//     //             }
//     //         }
//     //     });
//     // });
//
// });
//
