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
    public function getUserDetails2(Request $request)
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
                'success' => false
            ]);
        }
    }
    public function getUserDetails(Request $request)
    {
        $partnerId = app('current_partner')->id;
        $userId = $request->query('userId');

        // ИЗМЕНЕНИЕ: базовая проверка входного параметра
        if (!$userId) {
            return response()->json([
                'success' => false,
            ]);
        }

        // ИЗМЕНЕНИЕ: учитываем партнёра при поиске юзера
        $user = User::where('id', $userId)
            ->where('partner_id', $partnerId)
            ->first();

        // ИЗМЕНЕНИЕ: если пользователь не найден или не принадлежит текущему партнёру — не отдаём данные
        if (!$user) {
            return response()->json([
                'success' => false,
            ]);
        }

        // ИЗМЕНЕНИЕ: команда юзера тоже проверяется по partner_id
        $userTeam = $user->team_id
            ? Team::where('id', $user->team_id)
                ->where('partner_id', $partnerId)
                ->first()
            : null;

        $userPrice = UserPrice::where('user_id', $user->id)->get();
        $scheduleUser = ScheduleUser::where('user_id', $user->id)->get();

        $allFields = UserField::where('partner_id', $partnerId)
            ->get();

        $userFields = User::with('fields')->findOrFail($user->id);
        $userFieldValues = $user->fields->pluck('pivot.value', 'id');

        // (как и было) форматируем дату рождения
        $formattedBirthday = $user->birthday
            ? Carbon::parse($user->birthday)->format('d.m.Y')
            : null;

        return response()->json([
            'success'           => true,
            'user'              => $user,
            'userTeam'          => $userTeam,
            'userPrice'         => $userPrice,
            'scheduleUser'      => $scheduleUser,
            'formattedBirthday' => $formattedBirthday,
            'userFields'        => $userFields,
            'userFieldValues'   => $userFieldValues,
            'allFields'         => $allFields,
        ]);
    }

    //AJAX Изменение команды
    public function getTeamDetails2(Request $request)
    {
        $partnerId = app('current_partner')->id;
        $teamName = $request->query('teamName');
        $teamId = $request->query('teamId');
        $team = Team::where('id', $teamId)->first();
        $teamWeekDayId = [];

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
                'success' => false
            ]);
        }
    }
    public function getTeamDetails(Request $request)
    {
        $partnerId = app('current_partner')->id;
        $teamName = $request->query('teamName');
        $teamId = $request->query('teamId');

        $team = null;
        $teamWeekDayId = [];
        $usersTeam = collect();

        if ($teamName === 'all') {
            // как и раньше: все включённые юзеры текущего партнёра
            $usersTeam = User::where('is_enabled', 1)
                ->where('partner_id', $partnerId)
                ->orderBy('name', 'asc')
                ->get();
        } elseif ($teamName === 'withoutTeam') {
            // как и раньше: юзеры без команды у текущего партнёра
            $usersTeam = User::where('is_enabled', 1)
                ->whereNull('team_id')
                ->where('partner_id', $partnerId)
                ->orderBy('lastname', 'asc')
                ->get();
        } else {
            // ИЗМЕНЕНИЕ: без teamId и спец-значений teamName — считаем кейс невалидным
            if (!$teamId) {
                return response()->json([
                    'success' => false,
                ]);
            }

            // ИЗМЕНЕНИЕ: ищем команду только у текущего партнёра
            $team = Team::where('id', $teamId)
                ->where('partner_id', $partnerId)
                ->first();

            // ИЗМЕНЕНИЕ: если команда не найдена или принадлежит другому партнёру — не отдаём данные
            if (!$team) {
                return response()->json([
                    'success' => false,
                ]);
            }

            $usersTeam = User::where('team_id', $team->id)
                ->where('is_enabled', 1)
                ->where('partner_id', $partnerId)
                ->orderBy('lastname', 'asc')
                ->get();

            foreach ($team->weekdays as $teamWeekDay) {
                $teamWeekDayId[] = $teamWeekDay->id;
            }
        }

        $userWithoutTeam = User::whereNull('team_id')
            ->where('partner_id', $partnerId)
            ->get();

        if (empty($teamWeekDayId)) {
            $teamWeekDayId = null;
        }

        // ИЗМЕНЕНИЕ: тут больше нет странной проверки if ($usersTeam) — всегда success: true,
        // если не сработали ранние return'ы с success:false
        return response()->json([
            'success'        => true,
            'team'           => $team,
            'teamWeekDayId'  => $teamWeekDayId,
            'usersTeam'      => $usersTeam,
            'userWithoutTeam'=> $userWithoutTeam,
        ]);
    }
}
