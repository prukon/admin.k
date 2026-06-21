<?php

namespace App\Http\Controllers;

use App\Http\Requests\Team\FilterRequest;

use App\Models\MyLog;
use App\Models\ScheduleUser;
use App\Models\Setting;
use App\Models\Team;
use App\Models\TeamPrice;
use App\Models\TeamWeekday;
use App\Models\User;
use App\Models\UserField;
use App\Models\UserCustomPayment;
use App\Models\UserLessonPackage;
use App\Models\UserPrice;
use App\Models\Weekday;
use App\Services\TeamUserSyncService;
use App\Services\Users\FamilyStudentContextService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;


use Illuminate\Support\Facades\Storage;

class DashboardController extends Controller
{
    public function __construct(
        private readonly TeamUserSyncService $teamUserSync,
    ) {
    }

    public function index(FilterRequest $request)
    {
        $partnerId = app('current_partner')->id;
        $data = $request->validated();
        $title = isset($data['title']) ? trim((string)$data['title']) : null;

        $allUsersSelect = User::where('is_enabled', true)
            ->where('partner_id', $partnerId)
            ->orderBy('lastname', 'asc')->get();

        $teamsQuery = Team::where('is_enabled', true)
            ->where('partner_id', $partnerId)
            ->orderBy('order_by', 'asc');

        if (!empty($title)) {
            $teamsQuery->where('title', 'like', '%' . $title . '%');
        }

        $allTeams = $teamsQuery->get();

        $weekdays = Weekday::all();
        $curUser = app(FamilyStudentContextService::class)->activeStudent(auth()->user());
        $curUser->load([
            'teams' => fn ($q) => $q->where('teams.partner_id', $partnerId)->whereNull('teams.deleted_at'),
        ]);
        $curTeamsLabel = $this->teamUserSync->teamTitlesLabel($curUser);
        $curTeam = $curUser->teams->first();

        $scheduleUser = ScheduleUser::where('user_id', $curUser->id)->get();
        $scheduleUserArray = ScheduleUser::where('user_id', $curUser->id)->get()->toArray();
        $userPriceArray = UserPrice::with('team:id,title')
            ->where('user_id', $curUser->id)
            ->get()
            ->toArray();

        $userAbonements = UserCustomPayment::query()
            ->where('partner_id', $partnerId)
            ->where('user_id', (int) $curUser->id)
            ->orderByDesc('date_start')
            ->orderByDesc('id')
            ->get();

        $userLessonPackages = UserLessonPackage::query()
            ->with(['lessonPackage:id,name'])
            ->where('user_id', (int) $curUser->id)
            ->whereHas('user', fn ($q) => $q->where('partner_id', $partnerId))
            ->where('fee_amount', '>', 0)
            ->orderByDesc('starts_at')
            ->orderByDesc('id')
            ->get();

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
            "curTeamsLabel",
            "curUser",
            "scheduleUser",
            "scheduleUserArray",
            "userPriceArray",
            "userAbonements",
            "userLessonPackages",
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
        if (! $user) {
            return response()->json(['success' => false]);
        }

        $user->load([
            'teams' => fn ($q) => $q->where('teams.partner_id', $partnerId)->whereNull('teams.deleted_at'),
        ]);
        $userTeam = $user->teams->first();
        $userTeamsLabel = $this->teamUserSync->teamTitlesLabel($user);
        $userPrice = UserPrice::where('user_id', $userId)->get();
        $scheduleUser = ScheduleUser::where('user_id', $userId)->get();

        $allFields = UserField::where('partner_id', $partnerId)
            ->get();

        $userFields = User::with('fields')->findOrFail($user->id);
        $userFieldValues = $user->fields->pluck('pivot.value', 'id');

        $formattedBirthday = $user->birthday ? Carbon::parse($user->birthday)->format('d.m.Y') : null;

        return response()->json([
            'success' => true,
            'user' => $user,
            'userTeam' => $userTeam,
            'userTeamsLabel' => $userTeamsLabel !== '' ? $userTeamsLabel : null,
            'userPrice' => $userPrice,
            'scheduleUser' => $scheduleUser,
            'formattedBirthday' => $formattedBirthday,
            "userFields" => $userFields,
            "userFieldValues" => $userFieldValues,
            "allFields" => $allFields,
        ]);
    }

    public function getUserDetails(Request $request)
    {
        $partnerId = app('current_partner')->id;
        $userId = $request->query('userId');

        if (!$userId) {
            return response()->json([
                'success' => false,
            ]);
        }

        $user = User::where('id', $userId)
            ->where('partner_id', $partnerId)
            ->first();

        if (!$user) {
            return response()->json([
                'success' => false,
            ]);
        }

        $user->load([
            'teams' => fn ($q) => $q->where('teams.partner_id', $partnerId)->whereNull('teams.deleted_at'),
        ]);
        $userTeam = $user->teams->first();
        $userTeamsLabel = $this->teamUserSync->teamTitlesLabel($user);

        $userPrice = UserPrice::where('user_id', $user->id)->get();
        $scheduleUser = ScheduleUser::where('user_id', $user->id)->get();

        $allFields = UserField::where('partner_id', $partnerId)
            ->get();

        $userFields = User::with('fields')->findOrFail($user->id);
        $userFieldValues = $user->fields->pluck('pivot.value', 'id');

        $formattedBirthday = $user->birthday
            ? Carbon::parse($user->birthday)->format('d.m.Y')
            : null;

        return response()->json([
            'success'           => true,
            'user'              => $user,
            'userTeam'          => $userTeam,
            'userTeamsLabel'    => $userTeamsLabel !== '' ? $userTeamsLabel : null,
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
            $usersTeam = $this->studentsWithoutTeamsQuery($partnerId)
                ->where('is_enabled', 1)
                ->orderBy('lastname', 'asc')
                ->get();
        } else {
            $usersTeam = $team
                ? $team->students()
                    ->where('users.partner_id', $partnerId)
                    ->where('is_enabled', 1)
                    ->orderBy('lastname', 'asc')
                    ->get()
                : collect();
            if ($team) {
                foreach ($team->weekdays as $teamWeekDay) {
                    $teamWeekDayId[] = $teamWeekDay->id;
                }
            }
        }
        $userWithoutTeam = $this->studentsWithoutTeamsQuery($partnerId)->get();

        if ($teamWeekDayId) {
        } else {
            $teamWeekDayId = null;
        }

        $this->loadPartnerTeamsForUsers($usersTeam, $partnerId);
        $this->loadPartnerTeamsForUsers($userWithoutTeam, $partnerId);

        if ($usersTeam) {
            return response()->json([
                'success' => true,
                'team' => $team,
                'teamWeekDayId' => $teamWeekDayId,
                'usersTeam' => $usersTeam,
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
            $usersTeam = User::where('is_enabled', 1)
                ->where('partner_id', $partnerId)
                ->orderBy('name', 'asc')
                ->get();
        } elseif ($teamName === 'withoutTeam') {
            $usersTeam = $this->studentsWithoutTeamsQuery($partnerId)
                ->where('is_enabled', 1)
                ->orderBy('lastname', 'asc')
                ->get();
        } else {
            if (!$teamId) {
                return response()->json([
                    'success' => false,
                ]);
            }

            $team = Team::where('id', $teamId)
                ->where('partner_id', $partnerId)
                ->first();

            if (!$team) {
                return response()->json([
                    'success' => false,
                ]);
            }

            $usersTeam = $team->students()
                ->where('users.partner_id', $partnerId)
                ->where('is_enabled', 1)
                ->orderBy('lastname', 'asc')
                ->get();

            foreach ($team->weekdays as $teamWeekDay) {
                $teamWeekDayId[] = $teamWeekDay->id;
            }
        }

        $userWithoutTeam = $this->studentsWithoutTeamsQuery($partnerId)->get();

        if (empty($teamWeekDayId)) {
            $teamWeekDayId = null;
        }

        $this->loadPartnerTeamsForUsers($usersTeam, $partnerId);
        $this->loadPartnerTeamsForUsers($userWithoutTeam, $partnerId);

        return response()->json([
            'success'        => true,
            'team'           => $team,
            'teamWeekDayId'  => $teamWeekDayId,
            'usersTeam'      => $usersTeam,
            'userWithoutTeam'=> $userWithoutTeam,
        ]);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, User>|iterable<int, User>  $users
     */
    private function loadPartnerTeamsForUsers(iterable $users, int $partnerId): void
    {
        if ($users instanceof \Illuminate\Support\Collection) {
            $users->load([
                'teams' => fn ($q) => $q->where('teams.partner_id', $partnerId)->select('teams.id', 'teams.title'),
            ]);

            return;
        }

        foreach ($users as $user) {
            if ($user instanceof User) {
                $user->load([
                    'teams' => fn ($q) => $q->where('teams.partner_id', $partnerId)->select('teams.id', 'teams.title'),
                ]);
            }
        }
    }

    /**
     * Ученики партнёра без групп в pivot team_user.
     */
    private function studentsWithoutTeamsQuery(int $partnerId)
    {
        return User::query()
            ->where('partner_id', $partnerId)
            ->whereDoesntHave('teams', fn ($q) => $q->where('teams.partner_id', $partnerId));
    }
}
