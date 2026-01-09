<?php

namespace App\Http\Controllers;

use App\Http\Filters\TeamFilter;
use App\Http\Requests\Team\FilterRequest;

use App\Models\MyLog;
use App\Models\ScheduleUser;
use App\Models\Setting;
use App\Models\Team;
use App\Models\TeamPrice;
use App\Models\TeamWeekday;
use App\Models\User;
use App\Models\UserField;
use App\Models\UserPrice;
use App\Models\Weekday;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;


use Illuminate\Support\Facades\Storage;

class DashboardController extends Controller
{

    public function index(FilterRequest $request)
    {
        $partnerId = app('current_partner')->id;
        $data = $request->validated();
        $filter = app()->make(TeamFilter::class, ['queryParams' => array_filter($data)]);
        $allUsersSelect = User::where('is_enabled', true)
            ->where('partner_id', $partnerId)
//            ->orderBy('name', 'asc')->get();
            ->orderBy('lastname', 'asc')->get();

        $allTeams = Team::where('is_enabled', true)
            ->where('partner_id', $partnerId)
            ->orderBy('order_by', 'asc')->filter($filter)->get();


        $weekdays = Weekday::all();
        $curUser = auth()->user();
        $curTeam = Team::where('id', auth()->user()->team_id)->first();

        $scheduleUser = ScheduleUser::where('user_id', $curUser->id)->get();
        $scheduleUserArray = ScheduleUser::where('user_id', $curUser->id)->get()->toArray();
        $userPriceArray = UserPrice::where('user_id', $curUser->id)->get()->toArray();

        $textForUsers = Setting::where('name', 'textForUsers')
            ->where('partner_id', $partnerId)
            ->first();

        $textForUsers = $textForUsers ? $textForUsers->text : null;

        $allFields = UserField::where('partner_id', $partnerId)->get();


        $userFields = User::with('fields')->findOrFail($curUser->id);
        $userFieldValues = $curUser->fields->pluck('pivot.value', 'id');

        return view("dashboard", compact(
            "allTeams",
            "allUsersSelect",
            "weekdays",
            "curTeam",
            "curUser",
            "scheduleUser",
            "scheduleUserArray",
            "userPriceArray",
            "textForUsers",
            "userFields",
            "userFieldValues",
            "allFields"
        ));
    }

    //AJAX Изменение юзера
    public function getUserDetails(Request $request)
    {
        $partnerId = app('current_partner')->id;
        $userId = $request->query('userId');
        $user = User::where('id', $userId)->first();
        $userTeam = Team::where('id', $user->team_id)->first();
        $userPrice = UserPrice::where('user_id', $userId)->get();
        $scheduleUser = ScheduleUser::where('user_id', $userId)->get();

        $allFields = UserField::where('partner_id', $partnerId)
            ->get();

        $userFields = User::with('fields')->findOrFail($user->id);
        $userFieldValues = $user->fields->pluck('pivot.value', 'id');

        if ($user) {

            // Форматируем дату рождения (предполагаем, что дата хранится в поле 'birthday')
            $formattedBirthday = $user->birthday ? Carbon::parse($user->birthday)->format('d.m.Y') : null;

            return response()->json([
                'success' => true,
                'user' => $user,
                'userTeam' => $userTeam,
                'userPrice' => $userPrice,
                'scheduleUser' => $scheduleUser,
                'formattedBirthday' => $formattedBirthday, // Отправляем форматированную дату рождения
                "userFields" => $userFields,
                "userFieldValues" => $userFieldValues,
                "allFields" => $allFields,

            ]);
        } else {
            return response()->json([
                'success' => false]);
        }
    }

    //AJAX Изменение команды
    public function getTeamDetails(Request $request)
    {
        $partnerId = app('current_partner')->id;
        $teamName = $request->query('teamName');
        $teamId = $request->query('teamId');
//        $userName = $request->query('userName');
//        $inputDate = $request->query('inputDate');
        $team = Team::where('id', $teamId)->first();
        $teamWeekDayId = [];

//        $user = User::where('name', $userName)->first();
        if ($teamName == 'all') {
            $usersTeam = User::where('is_enabled', 1)
                ->where('partner_id', $partnerId)
                ->orderBy('name', 'asc')
                ->get();
        } elseif ($teamName == 'withoutTeam') {
            $usersTeam = User::where('is_enabled', 1)
                ->where('team_id', null)
                ->where('partner_id', $partnerId)
                ->orderBy('lastname', 'asc')
                ->get();
        } else {
            $usersTeam = User::where('team_id', $team->id)
                ->where('is_enabled', 1)
                ->where('partner_id', $partnerId)
                ->orderBy('lastname', 'asc')
                ->get();
            foreach ($team->weekdays as $teamWeekDay) {
                $teamWeekDayId[] = $teamWeekDay->id;
            }
        }
        $userWithoutTeam = User::where('team_id', null)
            ->where('partner_id', $partnerId)->get();

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


            ]);
        } else {
            return response()->json([
                'success' => false]);
        }
    }

}