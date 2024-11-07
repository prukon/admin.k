<?php

namespace App\Http\Controllers;

use App\Http\Filters\TeamFilter;
use App\Http\Requests\Team\FilterRequest;
use App\Models\Log;
use App\Models\ScheduleUser;
use App\Models\Setting;
use App\Models\Team;
use App\Models\TeamPrice;
use App\Models\TeamWeekday;
use App\Models\User;
use App\Models\UserPrice;
use App\Models\Weekday;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;


use Illuminate\Support\Facades\Storage;

class DashboardController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */

    public function index(FilterRequest $request)
    {
        $data = $request->validated();
        $filter = app()->make(TeamFilter::class, ['queryParams' => array_filter($data)]);
        $allUsersSelect = User::where('is_enabled', true)->orderBy('name', 'asc')->get();
        $allTeams = Team::where('is_enabled', true)->orderBy('order_by', 'asc')->filter($filter)->paginate(10);
        $allUsers = User::filter($filter)->paginate(20);
        $weekdays = Weekday::all();
        $curUser = auth()->user();
        $curTeam = Team::where('id', auth()->user()->team_id)->first();
        $scheduleUser = ScheduleUser::where('user_id', $curUser->id)->get();
        $scheduleUserArray = ScheduleUser::where('user_id', $curUser->id)->get()->toArray();
        $userPriceArray = UserPrice::where('user_id', $curUser->id)->get()->toArray();

        $textForUsers = Setting::where('name', 'textForUsers')->first();
        $textForUsers = $textForUsers ? $textForUsers->text : null;


        return view("dashboard", compact(
            "allTeams",
            "allUsers",
            "allUsersSelect",
            "weekdays",
            "curTeam",
            "curUser",
            "scheduleUser",
            "scheduleUserArray",
            "userPriceArray",
            "textForUsers"));
    }

    //AJAX Изменение юзера
    public function getUserDetails(Request $request)
    {
        $userName = $request->query('userName');
        $teamName = $request->query('teamName');
        $inputDate = $request->query('inputDate');

        $user = User::where('name', $userName)->first();
        $team = Team::where('title', $teamName)->first();
        $userTeam = Team::where('id', $user->team_id)->first();
        $userPrice = UserPrice::where('user_id', $user->id)->get();
        $scheduleUser = ScheduleUser::where('user_id', $user->id)->get();

        if ($user) {

            // Форматируем дату рождения (предполагаем, что дата хранится в поле 'birthday')
            $formattedBirthday = $user->birthday ? Carbon::parse($user->birthday)->format('d.m.Y') : null;

            return response()->json([
                'success' => true,
                'user' => $user,
                'userTeam' => $userTeam,
                'userPrice' => $userPrice,
                'scheduleUser' => $scheduleUser,
                'team' => $team,
                'inputDate' => $inputDate,
                'formattedBirthday' => $formattedBirthday, // Отправляем форматированную дату рождения
            ]);
        } else {
            return response()->json([
                'success' => false]);
        }
    }

    //AJAX Изменение команды
    public function getTeamDetails(Request $request)
    {
        $teamName = $request->query('teamName');
        $userName = $request->query('userName');
        $inputDate = $request->query('inputDate');
        $team = Team::where('title', $teamName)->first();
        $teamWeekDayId = [];

        $user = User::where('name', $userName)->first();
        if ($teamName == 'all') {
            $usersTeam = User::where('is_enabled', 1)
                ->orderBy('name', 'asc')
                ->get();
        } elseif ($teamName == 'withoutTeam') {
            $usersTeam = User::where('is_enabled', 1)
                ->where('team_id', null)
                ->orderBy('name', 'asc')
                ->get();
        } else {
            $usersTeam = User::where('team_id', $team->id)->where('is_enabled', 1)
                ->orderBy('name', 'asc')
                ->get();
            foreach ($team->weekdays as $teamWeekDay) {
                $teamWeekDayId[] = $teamWeekDay->id;
            }
//            dump('team');
        }
        $userWithoutTeam = User::where('team_id', null)->get();

        if ($teamWeekDayId) {

        } else {
            $teamWeekDayId = null;
        }


        if ($usersTeam) {
            return response()->json([
                'success' => true,
                'team' => $team,
                'teamWeekDayId' => $teamWeekDayId,  //fix сделать проверку на существование
                'usersTeam' => $usersTeam,          //fix сделать проверку на существование
                'userWithoutTeam' => $userWithoutTeam,
                'user' => $user,
                'inputDate' => $inputDate,

            ]);
        } else {
            return response()->json([
                'success' => false]);
        }
    }

    //AJAX клик по УСТАНОВИТЬ
    public function setupBtn(Request $request)
    {

        $userName = $request->query('userName');
        $teamName = $request->query('teamName');
        $inputDate = $request->query('inputDate');
        $activeWeekdays = $request->query('activeWeekdays');
        $user = User::where('name', $userName)->first();
        $team = Team::where('title', $teamName)->first();

//upd

        if ($inputDate == '01.01.1970') {
            $inputDate = '01.09.2024';
        }
        $inputDate = date('Y-m-d', strtotime($inputDate));

        //Обновление команды у юзера
        function updateUserTeam($user, $team)
        {
            $user->update([
                'team_id' => $team->id
            ]);
        }

        //Обновление даты начала занятий у юзера
        function updateStartDate($user, $inputDate)
        {
            $user->update([
                'start_date' => $inputDate
            ]);
        }

        //Обновление расписания у юзера
        function updateScheduleUsers($user, $inputDate)
        {
            ScheduleUser::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'date' => $inputDate,
                ]
            );


        }

        function setSchedule($user, $activeWeekdays, $inputDate)
        {
            $startDate = Carbon::parse($inputDate);

            $endDate = Carbon::parse('2025-05-31'); //fix

            // Пробегаем через каждый день от $inputDate до 31 мая 2025 года ($endDate)
            while ($startDate->lte($endDate)) {
                // Проверяем, если текущий день недели (weekday_id) присутствует в массиве дней
                if (!empty($activeWeekdays)) {

                    foreach ($activeWeekdays as $weekday) {
                        if ($startDate->dayOfWeekIso == $weekday) {
                            // Создаем запись в таблице schedule_users
                            ScheduleUser::updateOrCreate([
                                'user_id' => $user->id,
                                'date' => $startDate->toDateString(),
                            ],
                                ['is_enabled' => 1,
                                    'is_paid' => 0,
                                    'is_hospital' => 0,
                                    'description' => null]
                            );
                        }
                    }
                }
                // Переходим к следующему дню
                $startDate->addDay();
            }
        }


        foreach ($team->weekdays as $teamWeekDay) {
            $teamWeekDayId[] = $teamWeekDay->id;
        }

        if ($inputDate && $team && $user) {
            updateStartDate($user, $inputDate);
            updateScheduleUsers($user, $inputDate);
            setSchedule($user, $activeWeekdays, $inputDate);
            updateUserTeam($user, $team);
            $scheduleUser = ScheduleUser::where('user_id', $user->id)->get();
            $userTeam = Team::where('id', $user->team_id)->first();


            return response()->json([
                'success' => true,
                'userName' => $userName,
                'inputDate' => $inputDate,
                'teamWeekDays' => $activeWeekdays,
//                'scheduleData' => $scheduleData, // Возвращаем данные для обновления календаря
                'scheduleUser' => $scheduleUser, //upd
                'userTeam' => $userTeam,


            ]);
        } else {
            return response()->json([
                'success' => false]);
        }

    }

    //AJAX Обработка контекстного меню календаря
    public function contentMenuCalendar(Request $request)
    {
        $date = $request->query('date');
        $action = $request->query('action');
        $userName = $request->query('userName');

        $user = User::where('name', $userName)->first();
        $date = date('Y-m-d', strtotime($date));

        function updateSchedule($user, $date, $action)
        {
            DB::transaction(function () use ($date, $action, $user) {

                if ($action == 'add-freeze') {
                    ScheduleUser::updateOrCreate([
                        'user_id' => $user->id,
                        'date' => $date,
                    ],
                        [
                            'is_hospital' => 1,
                        ]
                    );
                } elseif ($action == 'add-training') {
                    ScheduleUser::updateOrCreate([
                        'user_id' => $user->id,
                        'date' => $date,
                    ],
                        [
                            'is_enabled' => 1,
                        ]
                    );
                } elseif ($action == 'remove-training') {
                    ScheduleUser::updateOrCreate([
                        'user_id' => $user->id,
                        'date' => $date,
                    ],
                        [
                            'is_enabled' => 0,
                        ]
                    );
                } elseif ($action == 'remove-freeze') {
                    ScheduleUser::updateOrCreate([
                        'user_id' => $user->id,
                        'date' => $date,
                    ],
                        [
                            'is_hospital' => 0,
                        ]
                    );
                }

                $authorId = auth()->id(); // Авторизованный пользователь

                Log::create([
                    'type' => 6, // Лог для обновления расписания
                    'action' => 60, // Лог для обновления расписания
                    'author_id' => $authorId,
                    'description' => ("Пользователю " . $user->name . " было изменено индивидуальное расписание."),
                    'created_at' => now(),
                ]);
            });
        }


        updateSchedule($user, $date, $action);

        $scheduleUser = ScheduleUser::where('user_id', $user->id)->get();

        if ($action) {
            return response()->json([
                'date' => $date,
                'action' => $action,
                'user' => $user,
                'scheduleUser' => $scheduleUser,
            ]);
        } else {
            return response()->json([
                'success' => false]);
        }
    }
}