<?php

namespace App\Http\Controllers;

use App\Http\Requests\Team\FilterRequest;

use App\Models\MyLog;
use App\Models\Setting;
use App\Models\UserTeamScheduleSlot;
use App\Models\Team;
use App\Models\TeamPrice;
use App\Models\TeamWeekday;
use App\Models\User;
use App\Models\UserField;
use App\Models\UserCustomPayment;
use App\Models\UserLessonPackage;
use App\Models\LessonPackage;
use App\Models\UserPrice;
use App\Models\Weekday;
use App\Services\TeamUserSyncService;
use App\Services\Users\FamilyStudentContextService;
use App\Services\Postpay\PostpayMonth;
use App\Services\Postpay\PostpayUsersPriceSync;
use App\Support\CabinetLessonPackagePermission;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;


use Illuminate\Support\Facades\Storage;

class DashboardController extends Controller
{
    public function __construct(
        private readonly TeamUserSyncService $teamUserSync,
        private readonly PostpayUsersPriceSync $postpaySync,
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

        $scheduleUser = $this->cabinetScheduleEntries((int) $curUser->id, $partnerId);
        $scheduleUserArray = $scheduleUser;
        $userPriceArray = $this->cabinetUserPricesPayload((int) $curUser->id);

        $userAbonements = UserCustomPayment::query()
            ->where('partner_id', $partnerId)
            ->where('user_id', (int) $curUser->id)
            ->orderByDesc('date_start')
            ->orderByDesc('id')
            ->get();
        // Blade считает суммы в рублях ($a->amount) — граница HTTP/шаблонов.
        $userAbonements->each(function (UserCustomPayment $abonement) {
            $abonement->setAttribute('amount', (float) Money::fromCents((int) $abonement->amount_cents));
        });

        $actor = auth()->user();
        $userLessonPackages = UserLessonPackage::query()
            ->with(['lessonPackage:id,name,schedule_type'])
            ->where('user_id', (int) $curUser->id)
            ->whereHas('user', fn ($q) => $q->where('partner_id', $partnerId))
            ->where('fee_amount_cents', '>', 0)
            ->orderByDesc('starts_at')
            ->orderByDesc('id')
            ->get()
            ->filter(fn (UserLessonPackage $ulp) => CabinetLessonPackagePermission::userCanViewAssignment($actor, $ulp))
            ->values();
        // Blade считает суммы в рублях ($ulp->fee_amount) — граница HTTP/шаблонов.
        $userLessonPackages->each(function (UserLessonPackage $ulp) {
            $ulp->setAttribute('fee_amount', (float) Money::fromCents((int) $ulp->fee_amount_cents));
        });

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
        $userPrice = $this->cabinetUserPricesPayload((int) $userId);
        $scheduleUser = $this->cabinetScheduleEntries((int) $userId, $partnerId);

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

        $userPrice = $this->cabinetUserPricesPayload((int) $user->id);
        $scheduleUser = $this->cabinetScheduleEntries((int) $user->id, $partnerId);

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
     * Цены сезонов для кабинета: sync postpay + метаданные разблокировки оплаты.
     *
     * @return list<array<string, mixed>>
     */
    private function cabinetUserPricesPayload(int $userId): array
    {
        $rows = UserPrice::with(['team:id,title', 'lessonPackage:id,schedule_type,price_cents,partner_id'])
            ->where('user_id', $userId)
            ->get();

        $payload = [];
        foreach ($rows as $row) {
            $this->postpaySync->syncRow($row);
            $row->refresh();
            if (! $row->relationLoaded('lessonPackage')) {
                $row->load('lessonPackage');
            }
            if (! $row->relationLoaded('team')) {
                $row->load('team:id,title');
            }
            $this->postpaySync->appendVisitMeta($row);

            $item = $row->toArray();
            // Кабинет (JS/blade) работает с ценой в рублях — граница HTTP/JSON.
            $item['price'] = (float) Money::fromCents((int) $row->price_cents);
            $isPostpay = (bool) ($item['is_postpay'] ?? false);
            $month = PostpayMonth::firstDayFromDate((string) $row->new_month);
            $item['effective_is_paid'] = (bool) $row->effective_is_paid;
            $item['is_postpay'] = $isPostpay;
            if ($isPostpay) {
                if (! CabinetLessonPackagePermission::userCanViewType(auth()->user(), LessonPackage::SCHEDULE_TYPE_POSTPAY)) {
                    continue;
                }
                $item['postpay_pay_available'] = PostpayMonth::isPayAvailableNow($month);
                $item['postpay_pay_available_from'] = PostpayMonth::payAvailableFrom($month)->format('Y-m-d');
                $item['postpay_pay_available_label'] = 'Оплата будет доступна с '.PostpayMonth::payAvailableFromLabel($month);
            } else {
                if (auth()->user() === null || ! auth()->user()->can('setPrices.cabinetSeasons.view')) {
                    continue;
                }
                $item['postpay_pay_available'] = true;
                $item['postpay_pay_available_from'] = null;
                $item['postpay_pay_available_label'] = null;
            }
            $payload[] = $item;
        }

        return $payload;
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

    /**
     * Календарь консоли: дни с занятиями из user_team_schedule_slots
     * (совместимость с legacy-флагами is_enabled / is_hospital в dashboard JS).
     *
     * @return list<array{date: string, is_enabled: bool, is_hospital: bool}>
     */
    private function cabinetScheduleEntries(int $userId, int $partnerId): array
    {
        $dates = UserTeamScheduleSlot::query()
            ->where('partner_id', $partnerId)
            ->where('user_id', $userId)
            ->orderBy('starts_at')
            ->pluck('starts_at')
            ->map(fn ($d) => Carbon::parse($d)->format('Y-m-d'))
            ->unique()
            ->values();

        $entries = [];
        foreach ($dates as $date) {
            $entries[] = [
                'date' => $date,
                'is_enabled' => true,
                'is_hospital' => false,
            ];
        }

        return $entries;
    }
}
