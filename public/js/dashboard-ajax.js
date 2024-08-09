document.addEventListener('DOMContentLoaded', function () {

    // User
    $('#single-select-user').change(function () {
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

    // Team
    $('#single-select-team').change(function () {
        var teamName = $(this).val();
        $.ajax({
            url: '/get-team-details',
            type: 'GET',
            data: {name: teamName},

            success: function (response) {
                if (response.success) {
                    var team = response.data;
                    var teamWeekDayId = response.teamWeekDayId;
                    // var teamTest = response.teamTest;




                    console.log(team.title);
                    console.log(teamWeekDayId);

                    var weekdays = document.querySelectorAll('.weekday-checkbox .form-check');
                    for (let i = 0; i < weekdays.length; i++) {
                        let weekday = weekdays[i];
                        let input = weekday.querySelector('input'); // Находим input внутри текущего div

                        if (input) { // Проверяем, существует ли input
                            input.checked = false; // Устанавливаем атрибут checked
                        }

                        if (teamWeekDayId.includes(i+1)) {
                            if (input) { // Проверяем, существует ли input
                                input.checked = true; // Устанавливаем атрибут checked
                            }
                        }
                    }

                    // <div className="row weekday-checkbox">
                    //     <div className="col-12 ">
                    //         @foreach($weekdays as $weekday)
                    //         <div className="form-check form-check-inline">
                    //             <input
                    //             @if($curTeam)
                    //             @foreach($curTeam->weekdays as $teamWeekday)
                    //             {{$weekday->id === $teamWeekday->id ? 'checked' : ''}}
                    //             @endforeach
                    //             @endif
                    //            {{--{{$weekday->id === $teamWeekday->id ? 'checked' : ''}}--}}
                    //             class="form-check-input" type="checkbox" id="{{$weekday->titleEn}}"
                    //             value="{{$weekday->titleEn}}">
                    //             <label className="form-check-label"
                    //                    htmlFor="{{$weekday->titleEn}}">{{$weekday->title}}</label>
                    //         </div>
                    //         @endforeach
                    //     </div>
                    // </div>

                }
            },
            error: function (xhr, status, error) {
                // console.log(error);
            }
        });
    });
});
