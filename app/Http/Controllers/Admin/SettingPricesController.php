<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\AdminBaseController;
use App\Http\Requests\Admin\SetManualUserPricePaidRequest;
use App\Http\Requests\Admin\SetPriceAllTeamsRequest;
use App\Http\Requests\Admin\SetPriceAllUsersRequest;
use App\Http\Requests\Admin\SetTeamPriceRequest;
use App\Http\Requests\Team\FilterRequest;
use App\Enums\AuditEvent;
use App\Models\LessonPackage;
use App\Models\Partner;
use App\Models\Setting;
use App\Models\Team;
use App\Models\TeamPrice;
use App\Models\UserPrice;
use App\Models\User;
use App\Models\Weekday;
use App\Models\UserCustomPayment;
use App\Services\Audit\AuditContext;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Yajra\DataTables\DataTables;
use Illuminate\Support\Str;
use App\Support\BuildsLogTable;
use App\Support\LessonPackagePostpayPermission;
use App\Services\PartnerContext;
use App\Http\Requests\Admin\SaveUserYearPricesRequest;
use App\Http\Requests\Admin\UserYearPricesRequest;
use App\Services\TeamUserSyncService;
use App\Support\UserPriceTeamMembership;
use App\Http\Requests\Admin\UserCustomPaymentStoreRequest;
use App\Http\Requests\Admin\UserCustomPaymentUpdateRequest;
use App\Http\Requests\Admin\SetManualUserCustomPaymentPaidRequest;
use App\Services\Postpay\PostpayUsersPriceSync;
use App\Services\Pricing\UserPercentDiscount;
use App\Services\SettingPrices\UsersPriceLessonPackageSync;
use App\Services\SettingPrices\UsersPriceLessonPackageSyncException;
use App\Support\Money;
use Illuminate\Support\Carbon as SupportCarbon;
use Illuminate\Validation\ValidationException;

class SettingPricesController extends AdminBaseController
{
    use BuildsLogTable;

    public function __construct(
        PartnerContext $partnerContext,
        private readonly AuditLogger $auditLogger,
        private readonly PostpayUsersPriceSync $postpaySync,
        private readonly UsersPriceLessonPackageSync $usersPriceLessonPackageSync,
    ) {
        parent::__construct($partnerContext);
    }

    /**
     * Команды текущего партнёра в нужном порядке.
     */
    protected function getPartnerTeamsOrdered()
    {
        return $this->scopeByPartner(Team::query())
            ->whereNull('deleted_at')
            ->orderBy('order_by', 'asc')
            ->get();
    }

    /**
     * Русское название месяца по номеру.
     */
    protected function ruMonthName(int $month): string
    {
        $names = [
            1  => 'Январь',
            2  => 'Февраль',
            3  => 'Март',
            4  => 'Апрель',
            5  => 'Май',
            6  => 'Июнь',
            7  => 'Июль',
            8  => 'Август',
            9  => 'Сентябрь',
            10 => 'Октябрь',
            11 => 'Ноябрь',
            12 => 'Декабрь',
        ];

        return $names[$month] ?? '';
    }

    /**
     * Текущий месяц для партнёра:
     * 1) session('prices_month')
     * 2) settings по партнёру (key = prices_last_month)
     * 3) текущий месяц
     */
    protected function getCurrentMonthString(int $partnerId): string
    {
        Carbon::setLocale('ru');

        $sessionMonth = session('prices_month');
        if ($sessionMonth) {
            return $sessionMonth;
        }

        try {
            $dbMonth = Setting::where('partner_id', $partnerId)
                ->where('key', 'prices_last_month')
                ->value('value');

            if ($dbMonth) {
                return $dbMonth;
            }
        } catch (\Throwable $e) {
            Log::warning('Не удалось прочитать месяц цен из settings', [
                'partner_id' => $partnerId,
                'error'      => $e->getMessage(),
            ]);
        }

        return Str::ucfirst(Carbon::now()->translatedFormat('F Y'));
    }

    /**
     * Запомнить месяц в сессии и в settings для конкретного партнёра.
     */
    protected function rememberCurrentMonthString(int $partnerId, string $monthString): void
    {
        session(['prices_month' => $monthString]);

        if (!trim($monthString)) {
            return;
        }

        try {
            Setting::updateOrCreate(
                [
                    'partner_id' => $partnerId,
                    'key'        => 'prices_last_month',
                ],
                [
                    'value' => $monthString,
                ]
            );
        } catch (\Throwable $e) {
            Log::warning('Не удалось сохранить месяц цен в settings', [
                'partner_id'   => $partnerId,
                'month_string' => $monthString,
                'error'        => $e->getMessage(),
            ]);
        }
    }

    /**
     * Гарантируем наличие TeamPrice на этот месяц для каждой команды.
     */
    protected function ensureTeamPricesForMonth($teams, string $monthDate): void
    {
        foreach ($teams as $team) {
            TeamPrice::firstOrCreate(
                ['team_id' => $team->id, 'new_month' => $monthDate],
                ['price_cents' => 0]
            );
        }
    }

    /**
     * Цены за месяц, ключ — team_id, с изоляцией по партнёру.
     */
    protected function getTeamPricesForMonth(int $partnerId, string $monthDate)
    {
        return TeamPrice::where('new_month', $monthDate)
            ->whereHas('team', function ($q) use ($partnerId) {
                $q->where('partner_id', $partnerId)
                    ->whereNull('deleted_at');
            })
            ->get()
            ->keyBy('team_id');
    }

    /**
     * Старая обёртка-страница (layout admin.settingPrices).
     */
    public function index(FilterRequest $request)
    {
        $partnerId = $this->requirePartnerId();

        $allTeams = $this->getPartnerTeamsOrdered();

        $monthString = $this->getCurrentMonthString($partnerId);
        $monthDate   = $this->formatedDate($monthString);

        $this->ensureTeamPricesForMonth($allTeams, $monthDate);

        $teamPrices = $this->getTeamPricesForMonth($partnerId, $monthDate);

        return view('admin.settingPrices', compact('allTeams', 'monthString', 'teamPrices'));
    }

    public function monthly(FilterRequest $request)
    {
        $partnerId = $this->requirePartnerId();

        $allTeams = $this->getPartnerTeamsOrdered();

        $monthString = $this->getCurrentMonthString($partnerId);
        $monthDate   = $this->formatedDate($monthString);

        $this->ensureTeamPricesForMonth($allTeams, $monthDate);

        $teamPrices = $this->getTeamPricesForMonth($partnerId, $monthDate);

        return view(
            'admin.SettingPrices.index',
            [
                'activeTab'       => 'monthly',
                'teamPrices'      => $teamPrices,
                'allTeams'        => $allTeams,
                'monthString'     => $monthString,
                'lessonPackages'  => $this->lessonPackagesForPartnerSelect($partnerId),
            ]
        );
    }

    public function users()
    {
        $partnerId = $this->requirePartnerId();

        // Команды нужны для фильтра в левой колонке (селект "Все группы / Группа N")
        $allTeams = $this->getPartnerTeamsOrdered();

        // Месяц + цены по группам — пока оставляем, вдруг пригодится во вью
        $monthString = $this->getCurrentMonthString($partnerId);
        $monthDate   = $this->formatedDate($monthString);
        $this->ensureTeamPricesForMonth($allTeams, $monthDate);
        $teamPrices = $this->getTeamPricesForMonth($partnerId, $monthDate);

        $users = $this->usersForPricesUsersTab($partnerId, $allTeams);

        return view(
            'admin.SettingPrices.index',
            [
                'activeTab'   => 'users',
                'teamPrices'  => $teamPrices,
                'allTeams'    => $allTeams,
                'monthString' => $monthString,
                'users'       => $users,
            ]
        );
    }

    /**
     * Ученики для вкладки «по ученикам»: текущие участники групп + бывшие
     * с users_prices.price &gt; 0 хотя бы за один месяц (для фильтра по группе).
     *
     * @param  \Illuminate\Support\Collection<int, Team>|iterable<int, Team>  $allTeams
     * @return \Illuminate\Support\Collection<int, User>
     */
    protected function usersForPricesUsersTab(int $partnerId, $allTeams)
    {
        $teamIds = collect($allTeams)->pluck('id')->map(static fn ($id) => (int) $id)->all();
        $teamTitles = collect($allTeams)->pluck('title', 'id');

        $historicalUserIds = [];
        if ($teamIds !== []) {
            $historicalUserIds = UserPrice::query()
                ->where('price_cents', '>', 0)
                ->whereIn('team_id', $teamIds)
                ->distinct()
                ->pluck('user_id')
                ->map(static fn ($id) => (int) $id)
                ->all();
        }

        $users = User::with(['teams' => fn ($q) => $q->where('teams.partner_id', $partnerId)->whereNull('teams.deleted_at')])
            ->where('partner_id', $partnerId)
            ->where('is_enabled', 1)
            ->where(function ($q) use ($partnerId, $historicalUserIds) {
                $q->whereHas(
                    'teams',
                    fn ($tq) => $tq->where('teams.partner_id', $partnerId)->whereNull('teams.deleted_at')
                );
                if ($historicalUserIds !== []) {
                    $q->orWhereIn('users.id', $historicalUserIds);
                }
            })
            ->orderBy('lastname')
            ->orderBy('name')
            ->get();

        $userIds = $users->pluck('id')->map(static fn ($id) => (int) $id)->all();

        $priceTeamsByUser = collect();
        if ($userIds !== [] && $teamIds !== []) {
            $priceTeamsByUser = UserPrice::query()
                ->select('user_id', 'team_id')
                ->where('price_cents', '>', 0)
                ->whereIn('user_id', $userIds)
                ->whereIn('team_id', $teamIds)
                ->distinct()
                ->get()
                ->groupBy('user_id')
                ->map(static function ($rows) {
                    return $rows->pluck('team_id')->map(static fn ($id) => (int) $id)->unique()->values()->all();
                });
        }

        foreach ($users as $user) {
            $currentIds = $user->teams
                ->pluck('id')
                ->map(static fn ($id) => (int) $id)
                ->all();
            $priceTeamIds = $priceTeamsByUser->get($user->id, []);
            $formerIds = array_values(array_diff($priceTeamIds, $currentIds));

            $formerTeams = [];
            foreach ($formerIds as $fid) {
                $title = $teamTitles->get($fid) ?? $teamTitles->get((string) $fid);
                $formerTeams[] = [
                    'id' => $fid,
                    'title' => (string) ($title ?? ('#' . $fid)),
                ];
            }

            $user->setAttribute('former_team_ids', $formerIds);
            $user->setAttribute('former_teams', $formerTeams);
        }

        return $users;
    }

    public function customPayments()
    {
        $partnerId = $this->requirePartnerId();
        if (!request()->user()?->can('setPrices.customPayments.view')) {
            abort(403);
        }

        // Команды/месяц тут не используются, но index.blade.php ожидает общий формат — пробрасываем пустое/минимальное.
        $allTeams = $this->getPartnerTeamsOrdered();
        $monthString = $this->getCurrentMonthString($partnerId);
        $monthDate = $this->formatedDate($monthString);
        $this->ensureTeamPricesForMonth($allTeams, $monthDate);
        $teamPrices = $this->getTeamPricesForMonth($partnerId, $monthDate);

        $users = User::query()
            ->where('partner_id', $partnerId)
            ->where('is_enabled', 1)
            ->orderBy('lastname')
            ->orderBy('name')
            ->get(['id', 'name', 'lastname']);

        return view('admin.SettingPrices.index', [
            'activeTab' => 'custom_payments',
            'teamPrices' => $teamPrices,
            'allTeams' => $allTeams,
            'monthString' => $monthString,
            'users' => $users,
        ]);
    }

    public function paymentNotifications()
    {
        $partnerId = $this->requirePartnerId();
        if (! request()->user()?->can('setPrices.paymentNotifications.manage')) {
            abort(403);
        }

        $allTeams = $this->getPartnerTeamsOrdered();
        $monthString = $this->getCurrentMonthString($partnerId);
        $monthDate = $this->formatedDate($monthString);
        $this->ensureTeamPricesForMonth($allTeams, $monthDate);
        $teamPrices = $this->getTeamPricesForMonth($partnerId, $monthDate);

        return view('admin.SettingPrices.index', [
            'activeTab' => 'payment_notifications',
            'teamPrices' => $teamPrices,
            'allTeams' => $allTeams,
            'monthString' => $monthString,
            'paymentNotificationTestEmail' => (string) (request()->user()?->email ?? ''),
        ]);
    }

    public function customPaymentsData(Request $request)
    {
        $partnerId = $this->requirePartnerId();
        if (!$request->user()?->can('setPrices.customPayments.view')) {
            abort(403);
        }

        $q = UserCustomPayment::query()
            ->where('user_custom_payment.partner_id', $partnerId)
            ->join('users', 'users.id', '=', 'user_custom_payment.user_id')
            ->leftJoin('teams', function ($join) use ($partnerId) {
                $join->on('teams.id', '=', 'user_custom_payment.team_id')
                    ->where('teams.partner_id', '=', $partnerId)
                    ->whereNull('teams.deleted_at');
            })
            ->select([
                'user_custom_payment.id',
                'user_custom_payment.user_id',
                'user_custom_payment.team_id',
                'user_custom_payment.date_start',
                'user_custom_payment.date_end',
                DB::raw('ROUND(user_custom_payment.amount_cents / 100, 2) as amount'),
                'user_custom_payment.note',
                'user_custom_payment.is_paid',
                'user_custom_payment.is_manual_paid',
                'user_custom_payment.manual_paid_note',
                DB::raw("TRIM(CONCAT(COALESCE(users.lastname,''),' ',COALESCE(users.name,''))) as user_name"),
                DB::raw("CASE WHEN user_custom_payment.is_manual_paid IS NULL THEN user_custom_payment.is_paid ELSE user_custom_payment.is_manual_paid END as effective_is_paid"),
                DB::raw("COALESCE(NULLIF(TRIM(teams.title), ''), '') as team_title"),
            ]);

        return DataTables::of($q)
            ->addColumn('period', function ($row) {
                $start = $row->date_start ? SupportCarbon::parse((string) $row->date_start)->format('Y-m-d') : '';
                $end = $row->date_end ? SupportCarbon::parse((string) $row->date_end)->format('Y-m-d') : '';
                if ($start === '' && $end === '') {
                    return '—';
                }
                if ($start !== '' && $end !== '') {
                    return $start.' — '.$end;
                }

                return $start !== '' ? $start : $end;
            })
            ->addColumn('team_label', function ($row) {
                $title = trim((string) ($row->team_title ?? ''));

                return $title !== '' ? $title : '—';
            })
            ->addColumn('status_label', function ($row) {
                return (bool) $row->effective_is_paid ? 'Оплачено' : 'Не оплачено';
            })
            ->addColumn('effective_is_paid', function ($row) {
                return (bool) $row->effective_is_paid;
            })
            ->addColumn('is_manual_paid', function ($row) {
                return $row->is_manual_paid;
            })
            ->addColumn('manual_paid_note', function ($row) {
                return (string) ($row->manual_paid_note ?? '');
            })
            ->make(true);
    }

    /**
     * Select2: поиск учеников текущего партнёра (для дополнительных платежей).
     */
    public function customPaymentsUsersSearch(Request $request)
    {
        $partnerId = $this->requirePartnerId();
        if (!$request->user()?->can('setPrices.customPayments.view')) {
            abort(403);
        }
        $q = trim((string) $request->query('q', ''));

        $users = User::query()
            ->where('partner_id', $partnerId)
            ->where('is_enabled', 1)
            ->withSystemRoleUser()
            ->when($q !== '', function ($query) use ($q) {
                $like = '%'.$q.'%';
                $query->whereRaw("CONCAT_WS(' ', lastname, name) LIKE ?", [$like]);
            })
            ->orderBy('lastname')
            ->orderBy('name')
            ->limit(30)
            ->get(['id', 'name', 'lastname']);

        $results = $users->map(function ($u) {
            $text = trim(($u->lastname ?? '').' '.($u->name ?? ''));
            return [
                'id' => (int) $u->id,
                'text' => $text !== '' ? $text : ('#'.$u->id),
            ];
        })->values();

        return response()->json([
            'results' => $results,
        ]);
    }

    /**
     * Группы ученика для формы дополнительного платежа.
     */
    public function customPaymentsTeamsForUser(Request $request)
    {
        $partnerId = $this->requirePartnerId();
        if (! $request->user()?->can('setPrices.customPayments.view')) {
            abort(403);
        }

        $userId = (int) $request->query('user_id', 0);
        if ($userId <= 0) {
            return response()->json(['results' => []]);
        }

        $user = User::query()
            ->whereKey($userId)
            ->where('partner_id', $partnerId)
            ->withSystemRoleUser()
            ->first();

        if (! $user) {
            return response()->json(['results' => []]);
        }

        $teams = app(\App\Services\Payments\PayableTeamResolver::class)
            ->studentTeams($user, $partnerId)
            ->map(fn ($team) => [
                'id' => (int) $team->id,
                'text' => (string) $team->title,
            ])
            ->values();

        return response()->json(['results' => $teams]);
    }

    public function storeCustomPayment(UserCustomPaymentStoreRequest $request)
    {
        $partnerId = $this->requirePartnerId();
        if (!request()->user()?->can('setPrices.customPayments.view')) {
            abort(403);
        }

        $data = $request->validated();

        $row = UserCustomPayment::create([
            'partner_id' => $partnerId,
            'user_id' => (int) $data['user_id'],
            'team_id' => (int) $data['team_id'],
            'date_start' => $data['date_start'] ?? null,
            'date_end' => $data['date_end'] ?? null,
            'amount_cents' => Money::toCentsOrFail($data['amount']),
            'note' => $data['note'] ?? null,
            'is_paid' => false,
        ]);

        $row->refresh();
        $row->setAttribute('amount', (float) Money::fromCents((int) ($row->amount_cents ?? 0)));

        return response()->json([
            'success' => true,
            'custom_payment' => $row,
        ]);
    }

    public function updateCustomPayment(int $id, UserCustomPaymentUpdateRequest $request)
    {
        $partnerId = $this->requirePartnerId();
        if (! request()->user()?->can('setPrices.customPayments.view')) {
            abort(403);
        }

        $row = $request->payment();
        if (! $row || (int) $row->id !== $id || (int) $row->partner_id !== $partnerId) {
            return response()->json([
                'success' => false,
                'message' => 'Дополнительный платеж не найден или недоступен в контексте текущего партнёра.',
            ], 404);
        }

        $data = $request->validated();
        $wantPaid = (bool) $data['is_paid'];
        $wasPaid = $row->effective_is_paid;

        if ($wantPaid !== $wasPaid && ! request()->user()?->can('setPrices.manualPaid.manage')) {
            return response()->json([
                'success' => false,
                'message' => 'Нет права менять статус оплаты дополнительного платежа.',
                'errors' => [
                    'is_paid' => ['Нет права менять статус оплаты дополнительного платежа.'],
                ],
            ], 403);
        }

        $authorId = auth()->id();

        DB::transaction(function () use ($row, $data, $wantPaid, $wasPaid, $authorId) {
            $fill = [
                'note' => $data['note'] ?? null,
            ];

            if (! $wasPaid && array_key_exists('amount', $data)) {
                $fill['amount_cents'] = Money::toCentsOrFail($data['amount']);
            }

            if ($wantPaid !== $wasPaid) {
                $fill['is_manual_paid'] = $wantPaid;
                $fill['manual_paid_by'] = $authorId;
                $fill['manual_paid_at'] = now();
                $fill['manual_paid_note'] = trim((string) ($data['status_comment'] ?? ''));
            }

            $row->forceFill($fill);
            $row->save();
        });

        $row->refresh();
        $row->setAttribute('amount', (float) Money::fromCents((int) ($row->amount_cents ?? 0)));

        return response()->json([
            'success' => true,
            'custom_payment' => $row,
        ]);
    }

    public function destroyCustomPayment(int $id)
    {
        $partnerId = $this->requirePartnerId();
        if (! request()->user()?->can('setPrices.customPayments.view')) {
            abort(403);
        }

        /** @var UserCustomPayment|null $row */
        $row = UserCustomPayment::query()
            ->whereKey($id)
            ->where('partner_id', $partnerId)
            ->first();

        if (! $row) {
            return response()->json([
                'success' => false,
                'message' => 'Дополнительный платеж не найден или недоступен в контексте текущего партнёра.',
            ], 404);
        }

        if ($row->effective_is_paid) {
            return response()->json([
                'success' => false,
                'message' => 'Нельзя удалить уже оплаченный дополнительный платеж.',
                'errors' => [
                    'custom_payment' => ['Нельзя удалить уже оплаченный дополнительный платеж.'],
                ],
            ], 422);
        }

        $row->delete();

        return response()->json([
            'success' => true,
        ]);
    }

    public function setManualPaidCustomPayment(int $id, SetManualUserCustomPaymentPaidRequest $request)
    {
        $partnerId = $this->requirePartnerId();
        if (!request()->user()?->can('setPrices.customPayments.view')) {
            abort(403);
        }

        $mode = (string) $request->validated('mode');
        $comment = trim((string) $request->validated('comment'));

        /** @var UserCustomPayment|null $row */
        $row = UserCustomPayment::query()
            ->whereKey($id)
            ->where('partner_id', $partnerId)
            ->first();

        if (! $row) {
            return response()->json([
                'success' => false,
                'message' => 'Дополнительный платеж не найден или недоступен в контексте текущего партнёра.',
            ], 404);
        }

        $authorId = auth()->id();

        DB::transaction(function () use ($row, $mode, $comment, $authorId) {
            $row->forceFill([
                'is_manual_paid' => ($mode === 'paid'),
                'manual_paid_by' => $authorId,
                'manual_paid_at' => now(),
                'manual_paid_note' => $comment,
            ]);
            $row->save();
        });

        $row->refresh();
        $row->setAttribute('amount', (float) Money::fromCents((int) ($row->amount_cents ?? 0)));

        return response()->json([
            'success' => true,
            'custom_payment' => $row,
        ]);
    }

    // AJAX ПОДРОБНО. Получение списка пользователей по группе (вкладка "по месяцам")
    public function getTeamPrice(Request $request)
    {
        $data         = json_decode($request->getContent(), true);
        $selectedDate = $data['selectedDate'] ?? null;
        $teamId       = $data['teamId'] ?? null;

        $partnerId = $this->requirePartnerId();

        $team = Team::where('id', $teamId)
            ->where('partner_id', $partnerId)
            ->whereNull('deleted_at')
            ->first();

        if (!$team) {
            return response()->json([
                'success' => false,
                'message' => 'Team not found',
            ], 404);
        }

        $selectedDate = $this->formatedDate($selectedDate);

        // Все id в pivot (в т.ч. отключённые) — чтобы disabled-ученик группы
        // не попал в блок «бывших» с бейджем «не в группе».
        $memberIds = $team->students()
            ->pluck('users.id')
            ->map(static fn ($id) => (int) $id)
            ->all();

        $currentStudents = $team->students()
            ->where('is_enabled', true)
            ->orderBy('lastname', 'asc')
            ->orderBy('name', 'asc')
            ->get();

        $usersTeam  = [];
        $usersPrice = [];

        foreach ($currentStudents as $user) {
            $userPrice = UserPrice::firstOrCreate(
                [
                    'new_month' => $selectedDate,
                    'user_id'   => $user->id,
                    'team_id'   => $team->id,
                ],
                [
                    'price_cents' => 0,
                ]
            );

            $userPrice->name = $user->name;
            $userPrice->refresh();
            $userPrice->load(['user', 'lessonPackage']);
            $userPrice->setAttribute('is_former_member', false);
            $usersPrice[] = $userPrice;

            $user->setAttribute('is_former_member', false);
            $usersTeam[] = $user;
        }

        [$formerUsers, $formerPrices] = $this->formerMemberPricesForTeamMonth(
            $team,
            $partnerId,
            $selectedDate,
            $memberIds
        );

        foreach ($formerUsers as $user) {
            $usersTeam[] = $user;
        }
        foreach ($formerPrices as $userPrice) {
            $usersPrice[] = $userPrice;
        }

        $usersPrice = $this->decorateUsersPricesForMonthlyUi($usersPrice);

        $lessonPackages = $this->lessonPackagesForPartnerSelect($partnerId);

        if (count($usersTeam) > 0) {
            return response()->json([
                'success'                  => true,
                'usersTeam'                => $usersTeam,
                'usersPrice'               => $usersPrice,
                'lessonPackages'           => $lessonPackages,
                'can_manage_manual_paid'   => $request->user()->can('setPrices.manualPaid.manage'),
            ]);
        }

        return response()->json([
            'success'        => false,
            'lessonPackages' => $lessonPackages,
        ]);
    }

    /**
     * Исторические начисления за месяц у учеников, которых уже нет в pivot группы.
     * Только существующие строки с price &gt; 0; firstOrCreate не вызывается.
     *
     * @param  list<int>  $memberIds  Актуальные user_id в team_user (включая is_enabled=0).
     * @return array{0: list<User>, 1: list<UserPrice>}
     */
    protected function formerMemberPricesForTeamMonth(
        Team $team,
        int $partnerId,
        string $monthDate,
        array $memberIds
    ): array {
        $query = UserPrice::query()
            ->where('team_id', $team->id)
            ->where('new_month', $monthDate)
            ->where('price_cents', '>', 0)
            ->with(['user' => static function ($q) use ($partnerId) {
                $q->where('partner_id', $partnerId);
            }]);

        if ($memberIds !== []) {
            $query->whereNotIn('user_id', $memberIds);
        }

        $rows = $query->get()
            ->filter(static fn (UserPrice $row) => $row->user !== null)
            ->sortBy([
                static fn (UserPrice $row) => mb_strtolower((string) ($row->user->lastname ?? '')),
                static fn (UserPrice $row) => mb_strtolower((string) ($row->user->name ?? '')),
                static fn (UserPrice $row) => (int) $row->user_id,
            ])
            ->values();

        $users  = [];
        $prices = [];

        foreach ($rows as $userPrice) {
            /** @var User $user */
            $user = $userPrice->user;
            $user->setAttribute('is_former_member', true);

            $userPrice->name = $user->name;
            $userPrice->setAttribute('is_former_member', true);

            $users[]  = $user;
            $prices[] = $userPrice;
        }

        return [$users, $prices];
    }

    /**
     * Шаблоны абонементов партнёра для select на вкладке «по месяцам».
     * Без lessonPackages.type.postpay — postpay-шаблоны скрыты, кроме уже назначенных
     * (TeamPrice / UserPrice), чтобы текущие значения в UI не «терялись».
     *
     * @return list<array{id: int, name: string, price: float, schedule_type: string, is_postpay: bool}>
     */
    protected function lessonPackagesForPartnerSelect(int $partnerId): array
    {
        $canPostpay = LessonPackagePostpayPermission::userCanSelect(auth()->user());

        $query = LessonPackage::query()
            ->where('partner_id', $partnerId)
            ->orderBy('name')
            ->orderBy('id');

        if (! $canPostpay) {
            $keepIds = TeamPrice::query()
                ->whereNotNull('lesson_package_id')
                ->whereHas('team', static function ($q) use ($partnerId) {
                    $q->where('partner_id', $partnerId)->whereNull('deleted_at');
                })
                ->pluck('lesson_package_id')
                ->merge(
                    UserPrice::query()
                        ->whereNotNull('lesson_package_id')
                        ->whereHas('user', static function ($q) use ($partnerId) {
                            $q->where('partner_id', $partnerId);
                        })
                        ->pluck('lesson_package_id')
                )
                ->map(static fn ($id) => (int) $id)
                ->unique()
                ->filter(static fn (int $id) => $id > 0)
                ->values()
                ->all();

            $query->where(function ($q) use ($keepIds) {
                $q->where('schedule_type', '!=', LessonPackage::SCHEDULE_TYPE_POSTPAY);
                if ($keepIds !== []) {
                    $q->orWhereIn('id', $keepIds);
                }
            });
        }

        return $query
            ->get(['id', 'name', 'price_cents', 'schedule_type'])
            ->map(static function (LessonPackage $package): array {
                return [
                    'id' => (int) $package->id,
                    'name' => (string) $package->name,
                    'price' => $package->priceRub(),
                    'schedule_type' => (string) $package->schedule_type,
                    'is_postpay' => $package->isPostpay(),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  list<UserPrice>  $rows
     * @return list<UserPrice>
     */
    protected function decorateUsersPricesForMonthlyUi(iterable $rows): array
    {
        $out = [];
        foreach ($rows as $row) {
            if (! $row instanceof UserPrice) {
                continue;
            }

            // Динамические атрибуты (не колонки БД): syncRow/save и refresh() их портят.
            $hasFormerFlag = array_key_exists('is_former_member', $row->getAttributes());
            $formerFlag = $hasFormerFlag ? (bool) $row->getAttribute('is_former_member') : null;
            $hasDisplayName = array_key_exists('name', $row->getAttributes());
            $displayName = $hasDisplayName ? $row->getAttribute('name') : null;

            if ($hasFormerFlag) {
                $row->offsetUnset('is_former_member');
            }
            if ($hasDisplayName) {
                $row->offsetUnset('name');
            }

            if (! $row->relationLoaded('lessonPackage')) {
                $row->load('lessonPackage');
            }
            $this->postpaySync->syncRow($row);
            $row->refresh();
            if (! $row->relationLoaded('lessonPackage')) {
                $row->load('lessonPackage');
            }
            $this->postpaySync->appendVisitMeta($row);

            if ($hasFormerFlag) {
                $row->setAttribute('is_former_member', $formerFlag);
            }
            if ($hasDisplayName) {
                $row->setAttribute('name', $displayName);
            }

            // JS (settings-prices.js) работает с ценой в рублях — граница HTTP/JSON.
            $row->setAttribute('price', (float) Money::fromCents((int) $row->price_cents));

            if (! $row->relationLoaded('user')) {
                $row->load('user');
            }
            $appliedPercent = $row->discount_percent !== null ? (int) $row->discount_percent : null;
            $appliedComment = $row->discount_comment !== null ? (string) $row->discount_comment : null;
            $row->setAttribute('applied_discount_percent', $appliedPercent);
            $row->setAttribute('applied_discount_comment', $appliedComment);
            $row->setAttribute(
                'applied_discount_tooltip',
                UserPercentDiscount::tooltip($appliedPercent, $appliedComment)
            );
            $row->setAttribute(
                'user_discount_percent',
                UserPercentDiscount::percent($row->user)
            );
            $row->setAttribute(
                'user_discount_comment',
                UserPercentDiscount::comment($row->user)
            );

            $out[] = $row;
        }

        return $out;
    }

    /**
     * Ручная отметка оплаты месяца (не меняет автоматический is_paid из платежей).
     */
    public function setManualPaid(SetManualUserPricePaidRequest $request)
    {
        $partnerId = $this->requirePartnerId();

        $userId       = (int) $request->validated('user_id');
        $teamId       = (int) $request->validated('team_id');
        $selectedDate = $request->validated('selectedDate');
        $mode         = $request->validated('mode');
        $comment      = trim($request->validated('comment'));

        $monthDate = $this->formatedDate($selectedDate);

        $user = $this->findPartnerStudent($userId, $partnerId);

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Ученик не найден или недоступен в контексте текущего партнёра.',
            ], 404);
        }

        $team = $this->findPartnerTeam($teamId, $partnerId);
        if (! $team || ! UserPriceTeamMembership::studentBelongsToTeam($user, $teamId, $partnerId)) {
            return response()->json([
                'success' => false,
                'message' => 'Группа не найдена или ученик в ней не состоит.',
            ], 422);
        }

        /** @var UserPrice|null $row */
        $row = UserPrice::where('user_id', $userId)
            ->where('team_id', $teamId)
            ->where('new_month', $monthDate)
            ->first();

        if (!$row) {
            return response()->json([
                'success' => false,
                'message' => 'Нет записи цены за выбранный месяц для этого ученика.',
                'errors'  => [
                    'record' => [
                        'Сначала задайте цену за период (через «Подробно» по группе или установку цены), чтобы в базе появилась строка начисления за месяц.',
                    ],
                ],
            ], 422);
        }

        $studentLabel = $user->full_name;

        $beforeManual = $row->is_manual_paid;
        $beforeAuto   = (bool) $row->is_paid;
        $beforeEff    = $row->effective_is_paid;

        $authorId = auth()->id();

        DB::transaction(function () use ($row, $mode, $comment, $authorId, $studentLabel, $selectedDate, $monthDate, $userId, $beforeManual, $beforeAuto, $beforeEff) {

            if ($mode === 'paid') {
                $row->forceFill([
                    'is_manual_paid'  => true,
                    'manual_paid_by'  => $authorId,
                    'manual_paid_at'  => now(),
                    'manual_paid_note'=> $comment,
                ]);
            } else {
                $row->forceFill([
                    'is_manual_paid'  => false,
                    'manual_paid_by'  => $authorId,
                    'manual_paid_at'  => now(),
                    'manual_paid_note'=> $comment,
                ]);
            }

            $row->save();
            $row->refresh();

            $afterEff = $row->effective_is_paid;
            $afterMan = $row->is_manual_paid;

            $modeRu = $mode === 'paid'
                ? 'установлено «оплачено» (ручная пометка)'
                : 'установлено «не оплачено» (ручная пометка)';

            $describeManual = static function ($v): string {
                if ($v === null) {
                    return 'нет (смотрим авто is_paid)';
                }

                return $v ? 'да (ручн.: оплачено)' : 'да (ручн.: не оплачено)';
            };

            $description = sprintf(
                'Ручная отметка оплаты месяца. %s. Ученик: %s (#%d). Период: %s (%s). До: эффективно %s; авто is_paid=%s; ручной флаг: %s. После: эффективно %s; авто is_paid=%s; ручной флаг: %s. Комментарий: %s',
                $modeRu,
                $studentLabel,
                $userId,
                $selectedDate,
                $monthDate,
                $beforeEff ? 'оплачено' : 'не оплачено',
                $beforeAuto ? '1' : '0',
                $describeManual($beforeManual),
                $afterEff ? 'оплачено' : 'не оплачено',
                $row->is_paid ? '1' : '0',
                $describeManual($afterMan),
                $comment
            );

            $this->auditLogger->record(
                AuditEvent::PricingManualMonthPaid,
                AuditContext::make($description)
                    ->withUserId($userId)
                    ->withTargetReference(UserPrice::class, (int) $row->id, $studentLabel)
                    ->withCreatedAt(now())
            );
        });

        $row->refresh();
        $row->load('user');
        $row->setAttribute('price', (float) Money::fromCents((int) $row->price_cents));

        return $this->settingPricesUsersJsonOrRedirect($request, [
            'success'    => true,
            'user_price' => $row,
        ]);
    }

    // AJAX SELECT DATE. Обработчик изменения месяца (общий селект наверху)
    public function updateDate(Request $request)
    {
        $partnerId = $this->requirePartnerId();

        $request->validate([
            'month' => 'required|string|max:255',
        ]);

        $month = ucfirst($request->input('month'));

        $this->rememberCurrentMonthString($partnerId, $month);

        $formatedMonth = $this->formatedDate($month);

        $teams = $this->getPartnerTeamsOrdered();

        $this->ensureTeamPricesForMonth($teams, $formatedMonth);

        return $this->settingPricesMonthlyJsonOrRedirect($request, [
            'success' => true,
            'month'   => $month,
        ]);
    }

    // Помогает преобразовать строку "Сентябрь 2024" в YYYY-MM-01
    protected function formatedDate(string $monthString): string
    {
        $parts = explode(' ', $monthString);
        $ruMonths = [
            'январь'   => 1,
            'февраль'  => 2,
            'март'     => 3,
            'апрель'   => 4,
            'май'      => 5,
            'июнь'     => 6,
            'июль'     => 7,
            'август'   => 8,
            'сентябрь' => 9,
            'октябрь'  => 10,
            'ноябрь'   => 11,
            'декабрь'  => 12,
        ];
        $month  = mb_strtolower($parts[0] ?? '', 'UTF-8');
        $year   = $parts[1] ?? date('Y');
        $mNum   = $ruMonths[$month] ?? date('n');

        return sprintf('%04d-%02d-01', (int) $year, $mNum);
    }


    /**
     * Цена шаблона абонемента партнёра (копейки — канон, рубли — для UI/аудита) или null, если недоступен.
     *
     * @return array{package: LessonPackage, price_cents: int, price: float}|null
     */
    protected function resolvePartnerLessonPackage(int $partnerId, int $lessonPackageId): ?array
    {
        /** @var LessonPackage|null $package */
        $package = LessonPackage::query()
            ->whereKey($lessonPackageId)
            ->where('partner_id', $partnerId)
            ->first();

        if (! $package) {
            return null;
        }

        return [
            'package' => $package,
            'price_cents' => (int) $package->price_cents,
            'price' => $package->priceRub(),
        ];
    }

    /**
     * Sync users_prices ↔ ULP; бизнес-ошибки → ValidationException с полем для фронта.
     *
     * @throws ValidationException
     */
    protected function syncUserPriceLessonPackage(UserPrice $row, ?string $errorField = null): void
    {
        try {
            $this->usersPriceLessonPackageSync->syncForUserPrice(
                $row,
                auth()->id() !== null ? (int) auth()->id() : null
            );
        } catch (UsersPriceLessonPackageSyncException $e) {
            $field = $errorField ?? $e->field();
            throw ValidationException::withMessages([
                $field => [$e->getMessage()],
            ]);
        }
    }

    /**
     * Применить снимок тарифа ко всем активным ученикам группы за месяц.
     * Не трогает записи с effective_is_paid = true.
     * Для postpay цена = посещения × цена занятия (не цена шаблона как фиксированный месяц).
     *
     * @throws ValidationException
     */
    protected function applyPackageSnapshotToTeamStudents(
        Team $team,
        string $monthDate,
        int $priceCents,
        int $lessonPackageId,
        ?LessonPackage $package = null
    ): void {
        $package = $package ?? LessonPackage::query()->find($lessonPackageId);
        $isPostpay = $package && $package->isPostpay();

        $users = $team->students()
            ->where('is_enabled', 1)
            ->get();

        foreach ($users as $user) {
            $userId = (int) $user->id;
            $snap = UserPercentDiscount::snapshotFromUser($user);
            $payableCents = UserPercentDiscount::payableCentsForUser($priceCents, $user);

            /** @var UserPrice|null $userPrice */
            $userPrice = UserPrice::query()
                ->where('user_id', $userId)
                ->where('team_id', $team->id)
                ->where('new_month', $monthDate)
                ->first();

            if ($userPrice) {
                if ($userPrice->effective_is_paid) {
                    continue;
                }

                if ($isPostpay && $package) {
                    $userPrice->fill($snap);
                    $this->postpaySync->applyPackageToRow($userPrice, $package);
                    $this->syncUserPriceLessonPackage(
                        $userPrice,
                        'lesson_package_id'
                    );
                } else {
                    $userPrice->update([
                        'price_cents' => $payableCents,
                        'lesson_package_id' => $lessonPackageId,
                        'discount_percent' => $snap['discount_percent'],
                        'discount_comment' => $snap['discount_comment'],
                    ]);
                    if ($package) {
                        $userPrice->setRelation('lessonPackage', $package);
                    }
                    $this->syncUserPriceLessonPackage($userPrice, 'lesson_package_id');
                }

                continue;
            }

            if ($isPostpay && $package) {
                $created = UserPrice::create([
                    'user_id' => $userId,
                    'team_id' => $team->id,
                    'new_month' => $monthDate,
                    'price_cents' => 0,
                    'lesson_package_id' => $lessonPackageId,
                    'is_paid' => false,
                    'discount_percent' => $snap['discount_percent'],
                    'discount_comment' => $snap['discount_comment'],
                ]);
                $created->setRelation('lessonPackage', $package);
                $this->postpaySync->syncRow($created);
                $this->syncUserPriceLessonPackage($created, 'lesson_package_id');
            } else {
                $created = UserPrice::create([
                    'user_id' => $userId,
                    'team_id' => $team->id,
                    'new_month' => $monthDate,
                    'price_cents' => $payableCents,
                    'lesson_package_id' => $lessonPackageId,
                    'is_paid' => false,
                    'discount_percent' => $snap['discount_percent'],
                    'discount_comment' => $snap['discount_comment'],
                ]);
                if ($package) {
                    $created->setRelation('lessonPackage', $package);
                }
                $this->syncUserPriceLessonPackage($created, 'lesson_package_id');
            }
        }
    }

    public function setTeamPrice(SetTeamPriceRequest $request)
    {
        $partnerId = $this->requirePartnerId();
        $data = $request->validated();

        $teamId = (int) $data['teamId'];
        $lessonPackageId = (int) $data['lesson_package_id'];
        $selectedDateString = $data['selectedDate'];

        $team = Team::where('id', $teamId)
            ->where('partner_id', $partnerId)
            ->whereNull('deleted_at')
            ->first();

        if (! $team) {
            return response()->json([
                'success' => false,
                'message' => 'Группа не найдена',
            ], 404);
        }

        $resolved = $this->resolvePartnerLessonPackage($partnerId, $lessonPackageId);
        if (! $resolved) {
            return response()->json([
                'success' => false,
                'message' => 'Абонемент не найден или недоступен.',
                'errors' => [
                    'lesson_package_id' => ['Выбранный абонемент не найден или недоступен.'],
                ],
            ], 422);
        }

        $priceCents = $resolved['price_cents'];
        $price = $resolved['price'];
        $package = $resolved['package'];
        $selectedDate = $this->formatedDate($selectedDateString);

        DB::transaction(function () use ($team, $selectedDate, $priceCents, $lessonPackageId, $selectedDateString, $package) {
            TeamPrice::updateOrCreate(
                [
                    'team_id' => $team->id,
                    'new_month' => $selectedDate,
                ],
                [
                    'price_cents' => $priceCents,
                    'lesson_package_id' => $lessonPackageId,
                ]
            );

            $this->auditLogger->record(
                AuditEvent::PricingTeamApply,
                AuditContext::make(
                    'Обновлена цена: '.Money::formatRub($priceCents)." руб. Абонемент #{$lessonPackageId}. Период: {$selectedDateString}."
                )
                    ->withTargetReference('App\Models\UserPrice', (int) $team->id, $team->title)
                    ->withCreatedAt(now())
            );

            $this->applyPackageSnapshotToTeamStudents($team, $selectedDate, $priceCents, $lessonPackageId, $package);
        });

        return $this->settingPricesMonthlyJsonOrRedirect($request, [
            'success' => true,
            'teamPrice' => $price,
            'lesson_package_id' => $lessonPackageId,
            'selectedDate' => $selectedDate,
            'teamId' => $team->id,
        ]);
    }

    // AJAX ПРИМЕНИТЬ слева. Установка тарифов всем группам с выбранным абонементом
    public function setPriceAllTeams(SetPriceAllTeamsRequest $request)
    {
        $partnerId = $this->requirePartnerId();
        $data = $request->validated();

        $selectedDateString = $data['selectedDate'];
        $teamsData = $data['teamsData'] ?? [];
        $selectedDate = $this->formatedDate($selectedDateString);

        DB::transaction(function () use ($selectedDate, $selectedDateString, $teamsData, $partnerId) {
            foreach ($teamsData as $teamData) {
                $teamId = (int) ($teamData['teamId'] ?? 0);
                $lessonPackageId = (int) ($teamData['lesson_package_id'] ?? 0);
                if ($teamId <= 0 || $lessonPackageId <= 0) {
                    continue;
                }

                $team = $this->scopeByPartner(Team::select('id', 'title'))
                    ->where('id', $teamId)
                    ->whereNull('deleted_at')
                    ->first();

                if (! $team) {
                    Log::warning('setPriceAllTeams: команда не найдена или не принадлежит текущему партнёру', [
                        'teamId' => $teamId,
                        'partnerId' => $partnerId,
                    ]);
                    continue;
                }

                $resolved = $this->resolvePartnerLessonPackage($partnerId, $lessonPackageId);
                if (! $resolved) {
                    Log::warning('setPriceAllTeams: абонемент недоступен', [
                        'teamId' => $teamId,
                        'lesson_package_id' => $lessonPackageId,
                        'partnerId' => $partnerId,
                    ]);
                    continue;
                }

                $priceCents = $resolved['price_cents'];
                $package = $resolved['package'];

                TeamPrice::updateOrCreate(
                    [
                        'team_id' => $team->id,
                        'new_month' => $selectedDate,
                    ],
                    [
                        'price_cents' => $priceCents,
                        'lesson_package_id' => $lessonPackageId,
                    ]
                );

                $this->auditLogger->record(
                    AuditEvent::PricingBulkApply,
                    AuditContext::make(
                        'Обновлена цена: '.Money::formatRub($priceCents)." руб. Абонемент #{$lessonPackageId}. Период: {$selectedDateString}."
                    )
                        ->withTargetReference('App\Models\UserPrice', (int) $team->id, $team->title)
                        ->withCreatedAt(now())
                );

                $this->applyPackageSnapshotToTeamStudents($team, $selectedDate, $priceCents, $lessonPackageId, $package);
            }
        });

        return $this->settingPricesMonthlyJsonOrRedirect($request, [
            'success' => true,
        ]);
    }

    // AJAX ПРИМЕНИТЬ справа. Установка цен всем ученикам (массово по команде, вкладка "по месяцам")
    public function setPriceAllUsers2(Request $request)
    {
        $partnerId = $this->requirePartnerId();

        $data = json_decode($request->getContent(), true);

        $selectedDate = $data['selectedDate'] ?? null;
        $usersPrice   = $data['usersPrice'] ?? null;
        $teamId       = isset($data['teamId']) ? (int) $data['teamId'] : 0;

        if (is_null($usersPrice) || !is_array($usersPrice)) {
            return response()->json(['error' => 'Некорректные данные'], 400);
        }

        if ($teamId <= 0) {
            return response()->json(['error' => 'Не указана группа'], 400);
        }

        $team = $this->findPartnerTeam($teamId, $partnerId);
        if (! $team) {
            return response()->json(['error' => 'Группа не найдена'], 404);
        }

        $authorId           = auth()->id();
        $selectedDateString = $selectedDate;
        $selectedDate       = $this->formatedDate($selectedDate);

        DB::transaction(function () use ($selectedDate, $authorId, $selectedDateString, $usersPrice, $partnerId, $teamId, $team) {
            foreach ($usersPrice as $priceData) {

                $userId = $priceData['user_id'] ?? null;
                if (!$userId) {
                    continue;
                }

                $user = $this->findPartnerStudent((int) $userId, $partnerId);

                if (! $user) {
                    Log::warning('setPriceAllUsers: попытка изменить цену пользователя не своего партнёра', [
                        'user_id'   => $userId,
                        'partnerId' => $partnerId,
                    ]);
                    continue;
                }

                if (! UserPriceTeamMembership::studentBelongsToTeam($user, $teamId, $partnerId)) {
                    continue;
                }

                $userPriceRecord = UserPrice::where('user_id', $userId)
                    ->where('team_id', $teamId)
                    ->where('new_month', $selectedDate)
                    ->where('is_paid', 0)
                    ->first();

                $newPriceCents = Money::toCents($priceData['price'] ?? null);

                if ($userPriceRecord && $newPriceCents !== null && $userPriceRecord->price_cents !== $newPriceCents) {
                    $userPriceRecord->update([
                        'price_cents' => $newPriceCents,
                    ]);

                    $userName = $priceData['user']['name'] ?? $user->name ?? 'Неизвестный пользователь';

                    $this->auditLogger->record(
                        AuditEvent::PricingStudentApply,
                        AuditContext::make("Обновлена цена: {$priceData['price']} руб. Период: {$selectedDateString}. Группа: {$team->title}.")
                            ->withUserId($userId)
                            ->withTargetReference('App\Models\UserPrice', (int) $userId, $userName)
                            ->withCreatedAt(now())
                    );
                }
            }
        });

        return response()->json([
            'success'      => true,
            'usersPrice'   => $usersPrice,
            'selectedDate' => $selectedDate,
        ]);
    }

    public function setPriceAllUsers(SetPriceAllUsersRequest $request)
    {
        $partnerId = $this->requirePartnerId();
        $data = $request->validated();

        $selectedDateString = $data['selectedDate'];
        $usersPrice = $data['usersPrice'];
        $teamId = (int) $data['teamId'];

        $team = $this->findPartnerTeam($teamId, $partnerId);
        if (! $team) {
            return response()->json(['error' => 'Группа не найдена'], 404);
        }

        $selectedDate = $this->formatedDate($selectedDateString);

        DB::transaction(function () use ($selectedDate, $selectedDateString, $usersPrice, $teamId, $team, $partnerId) {
            foreach ($usersPrice as $index => $priceData) {
                $userId = (int) ($priceData['user_id'] ?? 0);
                if ($userId <= 0) {
                    continue;
                }

                $user = $this->findPartnerStudent($userId, $partnerId);
                if (! $user || ! UserPriceTeamMembership::studentBelongsToTeam($user, $teamId, $partnerId)) {
                    continue;
                }

                /** @var UserPrice|null $userPriceRecord */
                $userPriceRecord = UserPrice::where('user_id', $userId)
                    ->where('team_id', $teamId)
                    ->where('new_month', $selectedDate)
                    ->first();

                // Нет записи — не создаём (обратная совместимость тестов и UX «Подробно» → firstOrCreate)
                if (! $userPriceRecord) {
                    continue;
                }

                if ($userPriceRecord->effective_is_paid) {
                    continue;
                }

                $packageKeyPresent = array_key_exists('lesson_package_id', $priceData);
                $newPackageId = $packageKeyPresent
                    ? ($priceData['lesson_package_id'] !== null ? (int) $priceData['lesson_package_id'] : null)
                    : ($userPriceRecord->lesson_package_id !== null ? (int) $userPriceRecord->lesson_package_id : null);

                $resolvedPackage = null;
                if ($newPackageId !== null && $newPackageId > 0) {
                    $resolvedPackage = LessonPackage::query()
                        ->whereKey($newPackageId)
                        ->where('partner_id', $partnerId)
                        ->first();
                    if (! $resolvedPackage) {
                        continue;
                    }
                }

                $ulpErrorField = 'usersPrice.'.$index.'.lesson_package_id';

                // Postpay: сумма только из журнала, ручной price из UI игнорируем.
                if ($resolvedPackage && $resolvedPackage->isPostpay()) {
                    $packageChanged = $packageKeyPresent
                        && (int) ($userPriceRecord->lesson_package_id ?? 0) !== (int) $newPackageId;
                    if ($packageChanged || (int) ($userPriceRecord->lesson_package_id ?? 0) !== (int) $newPackageId) {
                        $userPriceRecord->lesson_package_id = $newPackageId;
                        $userPriceRecord->fill(UserPercentDiscount::snapshotFromUser($user));
                        $userPriceRecord->save();
                    }
                    $userPriceRecord->setRelation('lessonPackage', $resolvedPackage);
                    $this->postpaySync->syncRow($userPriceRecord);
                    $this->syncUserPriceLessonPackage($userPriceRecord, $ulpErrorField);
                    $userPriceRecord->refresh();

                    $userName = $priceData['user']['name'] ?? $user->name ?? 'Неизвестный пользователь';
                    $this->auditLogger->record(
                        AuditEvent::PricingStudentApply,
                        AuditContext::make(
                            'Обновлена постоплата: '.Money::formatRub((int) $userPriceRecord->price_cents)." руб. Абонемент #{$newPackageId}. Период: {$selectedDateString}. Группа: {$team->title}."
                        )
                            ->withUserId($userId)
                            ->withTargetReference('App\Models\UserPrice', (int) $userId, $userName)
                            ->withCreatedAt(now())
                    );

                    continue;
                }

                $newPrice = round((float) ($priceData['price'] ?? 0), 2);
                $newPriceCents = Money::toCentsOrFail($priceData['price'] ?? 0);

                $priceChanged = (int) $userPriceRecord->price_cents !== $newPriceCents;
                $packageChanged = $packageKeyPresent
                    && (int) ($userPriceRecord->lesson_package_id ?? 0) !== (int) ($newPackageId ?? 0);

                if (! $priceChanged && ! $packageChanged) {
                    // Идемпотентный догон: создать ULP / выставить ends_at конца billing_month.
                    if ($resolvedPackage
                        && ! $resolvedPackage->isPostpay()
                        && in_array((string) $resolvedPackage->schedule_type, LessonPackage::ASSIGNMENT_SCHEDULE_TYPES, true)
                    ) {
                        $userPriceRecord->setRelation('lessonPackage', $resolvedPackage);
                        $this->syncUserPriceLessonPackage($userPriceRecord, $ulpErrorField);
                    }
                    continue;
                }

                $payload = [];
                if ($priceChanged) {
                    $payload['price_cents'] = $newPriceCents;
                }
                if ($packageKeyPresent) {
                    $payload['lesson_package_id'] = $newPackageId;
                }

                $catalogCents = $resolvedPackage
                    ? (int) $resolvedPackage->price_cents
                    : $newPriceCents;
                $snap = $resolvedPackage
                    ? UserPercentDiscount::snapshotIfMatchesCatalog($catalogCents, $newPriceCents, $user)
                    : UserPercentDiscount::emptySnapshot();
                $payload['discount_percent'] = $snap['discount_percent'];
                $payload['discount_comment'] = $snap['discount_comment'];

                if ($payload === []) {
                    continue;
                }

                $userPriceRecord->update($payload);
                if ($resolvedPackage) {
                    $userPriceRecord->setRelation('lessonPackage', $resolvedPackage);
                }
                $this->syncUserPriceLessonPackage($userPriceRecord, $ulpErrorField);

                $userName = $priceData['user']['name'] ?? $user->name ?? 'Неизвестный пользователь';
                $packageNote = $newPackageId
                    ? " Абонемент #{$newPackageId}."
                    : '';

                $this->auditLogger->record(
                    AuditEvent::PricingStudentApply,
                    AuditContext::make(
                        "Обновлена цена: {$newPrice} руб.{$packageNote} Период: {$selectedDateString}. Группа: {$team->title}."
                    )
                        ->withUserId($userId)
                        ->withTargetReference('App\Models\UserPrice', (int) $userId, $userName)
                        ->withCreatedAt(now())
                );
            }
        });

        $userIds = collect($usersPrice)
            ->pluck('user_id')
            ->map(static fn ($id) => (int) $id)
            ->filter(static fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        $freshUsersPrice = UserPrice::query()
            ->where('team_id', $teamId)
            ->where('new_month', $selectedDate)
            ->whereIn('user_id', $userIds)
            ->with('lessonPackage')
            ->get();

        // Если клиент прислал бывших в payload — в ответе всё равно помечаем флаг
        // (decorate делает refresh и без этого сотрёт динамические атрибуты).
        $memberIdSet = array_fill_keys(
            $team->students()
                ->pluck('users.id')
                ->map(static fn ($id) => (int) $id)
                ->all(),
            true
        );
        foreach ($freshUsersPrice as $row) {
            $row->setAttribute(
                'is_former_member',
                ! isset($memberIdSet[(int) $row->user_id])
            );
        }

        $freshUsersPrice = $this->decorateUsersPricesForMonthlyUi($freshUsersPrice->all());

        return $this->settingPricesMonthlyJsonOrRedirect($request, [
            'success' => true,
            'usersPrice' => $freshUsersPrice,
            'selectedDate' => $selectedDate,
            'lessonPackages' => $this->lessonPackagesForPartnerSelect($partnerId),
        ]);
    }

    /**
     * AJAX: получить цены конкретного ученика по месяцам за год (вкладка "по ученикам")
     */
    public function userYearPrices(UserYearPricesRequest $request)
    {
        $partnerId = $this->requirePartnerId();

        $data   = $request->validated();
        $userId = (int) $data['user_id'];
        $teamId = (int) $data['team_id'];
        $year   = (int) $data['year'];

        $user = $this->findPartnerEnabledStudent($userId, $partnerId);

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found',
            ], 404);
        }

        $team = $this->findPartnerTeam($teamId, $partnerId);
        if (! $team) {
            return response()->json([
                'success' => false,
                'message' => 'Группа не найдена или ученик в ней не состоит.',
            ], 422);
        }

        $isMember = UserPriceTeamMembership::studentBelongsToTeam($user, $teamId, $partnerId);
        $isFormer = ! $isMember
            && UserPriceTeamMembership::studentHasPositivePriceHistoryForTeam($userId, $teamId);

        if (! $isMember && ! $isFormer) {
            return response()->json([
                'success' => false,
                'message' => 'Группа не найдена или ученик в ней не состоит.',
            ], 422);
        }

        $prices = UserPrice::where('user_id', $userId)
            ->where('team_id', $teamId)
            ->whereYear('new_month', $year)
            ->get()
            ->keyBy('new_month');

        $months = [];
        for ($m = 1; $m <= 12; $m++) {
            $dateStr     = sprintf('%04d-%02d-01', $year, $m);
            /** @var UserPrice|null $priceRow */
            $priceRow    = $prices->get($dateStr);
            $monthLabel  = $this->ruMonthName($m);

            $months[] = [
                'month'             => $m,
                'month_label'       => $monthLabel,
                'new_month'         => $dateStr,
                'price'             => $priceRow ? (float) Money::fromCents((int) $priceRow->price_cents) : 0,
                'lesson_package_id' => $priceRow && $priceRow->lesson_package_id
                    ? (int) $priceRow->lesson_package_id
                    : null,
                'is_paid'           => $priceRow ? (bool) $priceRow->is_paid : false,
                'is_manual_paid'    => $priceRow ? $priceRow->is_manual_paid : null,
                'effective_is_paid' => $priceRow ? (bool) $priceRow->effective_is_paid : false,
                'has_price_row'     => $priceRow !== null,
                'manual_paid_note'  => $priceRow && $priceRow->manual_paid_note
                    ? (string) $priceRow->manual_paid_note
                    : null,
                'applied_discount_percent' => $priceRow && $priceRow->discount_percent !== null
                    ? (int) $priceRow->discount_percent
                    : null,
                'applied_discount_comment' => $priceRow && $priceRow->discount_comment
                    ? (string) $priceRow->discount_comment
                    : null,
                'applied_discount_tooltip' => $priceRow
                    ? UserPercentDiscount::tooltip(
                        $priceRow->discount_percent !== null ? (int) $priceRow->discount_percent : null,
                        $priceRow->discount_comment !== null ? (string) $priceRow->discount_comment : null
                    )
                    : null,
            ];
        }

        return response()->json([
            'success'                => true,
            'is_former_member'       => $isFormer,
            'can_manage_manual_paid' => $isFormer
                ? false
                : $request->user()->can('setPrices.manualPaid.manage'),
            'user'    => [
                'id'        => $user->id,
                'name'      => $user->name,
                'lastname'  => $user->lastname,
                'team_id'   => $teamId,
                'team_name' => $team->title,
                'discount_percent' => UserPercentDiscount::percent($user),
                'discount_comment' => UserPercentDiscount::comment($user),
            ],
            'year'           => $year,
            'months'         => $months,
            'lessonPackages' => $this->lessonPackagesForPartnerSelect($partnerId),
        ]);
    }

    /**
     * AJAX: сохранить цены ученика за год (вкладка "по ученикам")
     */
    public function saveUserYearPrices(SaveUserYearPricesRequest $request)
    {
        $partnerId = $this->requirePartnerId();

        $data   = $request->validated();
        $userId = (int) $data['user_id'];
        $teamId = (int) $data['team_id'];
        $year   = (int) $data['year'];
        $items  = $data['prices'];

        $user = $this->findPartnerStudent($userId, $partnerId);
        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found',
            ], 404);
        }

        $team = $this->findPartnerTeam($teamId, $partnerId);
        if (! $team || ! UserPriceTeamMembership::studentBelongsToTeam($user, $teamId, $partnerId)) {
            return response()->json([
                'success' => false,
                'message' => 'Группа не найдена или ученик в ней не состоит.',
            ], 422);
        }

        $authorId = auth()->id();

        DB::transaction(function () use ($items, $userId, $teamId, $year, $authorId, $team, $user) {
            foreach ($items as $item) {
                $newMonth = $item['new_month'];
                $price = round((float) $item['price'], 2);
                $priceCents = Money::toCentsOrFail($item['price'] ?? 0);

                // защита от рассинхрона по году
                $itemYear = (int) substr($newMonth, 0, 4);
                if ($itemYear !== (int) $year) {
                    continue;
                }

                $userPrice = UserPrice::where('user_id', $userId)
                    ->where('team_id', $teamId)
                    ->where('new_month', $newMonth)
                    ->first();

                // вытаскиваем месяц для лога
                $monthInt = (int) substr($newMonth, 5, 2);
                $monthLabel = $this->ruMonthName($monthInt) . ' ' . $year;

                $packageKeyPresent = array_key_exists('lesson_package_id', $item);
                $newPackageId = $packageKeyPresent
                    ? ($item['lesson_package_id'] !== null ? (int) $item['lesson_package_id'] : null)
                    : null;

                if ($userPrice) {
                    if ($userPrice->effective_is_paid) {
                        continue;
                    }

                    $resolvedPackageId = $packageKeyPresent
                        ? $newPackageId
                        : ($userPrice->lesson_package_id !== null ? (int) $userPrice->lesson_package_id : null);

                    $priceChanged = (int) $userPrice->price_cents !== $priceCents;
                    $packageChanged = $packageKeyPresent
                        && (int) ($userPrice->lesson_package_id ?? 0) !== (int) ($resolvedPackageId ?? 0);

                    $resolvedPackage = null;
                    if ($resolvedPackageId) {
                        $resolvedPackage = LessonPackage::query()->find($resolvedPackageId);
                    }

                    if ($resolvedPackage && $resolvedPackage->isPostpay()) {
                        if ($packageChanged || (int) ($userPrice->lesson_package_id ?? 0) !== (int) $resolvedPackageId) {
                            $userPrice->lesson_package_id = $resolvedPackageId;
                            $userPrice->fill(UserPercentDiscount::snapshotFromUser($user));
                            $userPrice->save();
                        }
                        $userPrice->setRelation('lessonPackage', $resolvedPackage);
                        $this->postpaySync->syncRow($userPrice);
                        $this->syncUserPriceLessonPackage($userPrice, 'prices.lesson_package_id');

                        $this->auditLogger->record(
                            AuditEvent::PricingStudentApply,
                            AuditContext::make(
                                'Обновлена постоплата: '.Money::formatRub((int) $userPrice->price_cents)." руб. Абонемент #{$resolvedPackageId}. Период: {$monthLabel}. Группа: {$team->title}."
                            )
                                ->withUserId($userId)
                                ->withTargetReference('App\Models\UserPrice', (int) $userPrice->id, $userPrice->user->name ?? 'Пользователь')
                                ->withCreatedAt(now())
                        );
                        continue;
                    }

                    if (! $priceChanged && ! $packageChanged) {
                        // Идемпотентный догон: создать ULP / выставить ends_at конца billing_month.
                        if ($resolvedPackage
                            && ! $resolvedPackage->isPostpay()
                            && in_array((string) $resolvedPackage->schedule_type, LessonPackage::ASSIGNMENT_SCHEDULE_TYPES, true)
                        ) {
                            $userPrice->setRelation('lessonPackage', $resolvedPackage);
                            $this->syncUserPriceLessonPackage($userPrice, 'prices.lesson_package_id');
                        }
                        continue;
                    }

                    $payload = [];
                    if ($priceChanged) {
                        $payload['price_cents'] = $priceCents;
                    }
                    if ($packageKeyPresent) {
                        $payload['lesson_package_id'] = $resolvedPackageId;
                    }

                    $catalogCents = $resolvedPackage
                        ? (int) $resolvedPackage->price_cents
                        : $priceCents;
                    $snap = $resolvedPackage
                        ? UserPercentDiscount::snapshotIfMatchesCatalog($catalogCents, $priceCents, $user)
                        : UserPercentDiscount::emptySnapshot();
                    $payload['discount_percent'] = $snap['discount_percent'];
                    $payload['discount_comment'] = $snap['discount_comment'];

                    if ($payload === []) {
                        continue;
                    }

                    $userPrice->update($payload);
                    if ($resolvedPackage) {
                        $userPrice->setRelation('lessonPackage', $resolvedPackage);
                    }
                    $this->syncUserPriceLessonPackage(
                        $userPrice,
                        'prices.lesson_package_id'
                    );

                    $packageNote = $resolvedPackageId
                        ? " Абонемент #{$resolvedPackageId}."
                        : '';

                    $this->auditLogger->record(
                        AuditEvent::PricingStudentApply,
                        AuditContext::make("Обновлена цена: {$price} руб.{$packageNote} Период: {$monthLabel}. Группа: {$team->title}.")
                            ->withUserId($userId)
                            ->withTargetReference('App\Models\UserPrice', (int) $userPrice->id, $userPrice->user->name ?? 'Пользователь')
                            ->withCreatedAt(now())
                    );
                } else {
                    $createPackage = ($packageKeyPresent && $newPackageId)
                        ? LessonPackage::query()->find($newPackageId)
                        : null;
                    $createSnap = $createPackage
                        ? UserPercentDiscount::snapshotIfMatchesCatalog(
                            (int) $createPackage->price_cents,
                            $priceCents,
                            $user
                        )
                        : UserPercentDiscount::emptySnapshot();
                    if ($createPackage && $createPackage->isPostpay()) {
                        $createSnap = UserPercentDiscount::snapshotFromUser($user);
                    }

                    $created = UserPrice::create([
                        'user_id' => $userId,
                        'team_id' => $teamId,
                        'new_month' => $newMonth,
                        'price_cents' => ($createPackage && $createPackage->isPostpay()) ? 0 : $priceCents,
                        'lesson_package_id' => $packageKeyPresent ? $newPackageId : null,
                        'is_paid' => false,
                        'discount_percent' => $createSnap['discount_percent'],
                        'discount_comment' => $createSnap['discount_comment'],
                    ]);
                    if ($createPackage && $createPackage->isPostpay()) {
                        $created->setRelation('lessonPackage', $createPackage);
                        $this->postpaySync->syncRow($created);
                    }
                    $this->syncUserPriceLessonPackage(
                        $created,
                        'prices.lesson_package_id'
                    );

                    $packageNote = ($packageKeyPresent && $newPackageId)
                        ? " Абонемент #{$newPackageId}."
                        : '';

                    $this->auditLogger->record(
                        AuditEvent::PricingStudentApply,
                        AuditContext::make("Установлена цена: {$price} руб.{$packageNote} Период: {$monthLabel}. Группа: {$team->title}.")
                            ->withUserId($userId)
                            ->withTargetReference('App\Models\UserPrice', (int) $created->id, $created->user->name ?? 'Пользователь')
                            ->withCreatedAt(now())
                    );
                }
            }
        });

        return $this->settingPricesUsersJsonOrRedirect($request, [
            'success' => true,
        ]);
    }

    // Метод для обработки DataTables запросов (логи)
    public function getLogsData(FilterRequest $request)
    {
        return $this->buildLogDataTable('pricing');
    }

    /**
     * Ученик партнёра с хотя бы одной группой в pivot team_user.
     */
    private function findPartnerStudent(int $userId, int $partnerId): ?User
    {
        $user = User::with(['teams' => fn ($q) => $q->where('teams.partner_id', $partnerId)->whereNull('teams.deleted_at')])
            ->find($userId);

        if (! $user || (int) $user->partner_id !== $partnerId) {
            return null;
        }

        if (! $user->teams()->where('teams.partner_id', $partnerId)->whereNull('teams.deleted_at')->exists()) {
            return null;
        }

        return $user;
    }

    /**
     * Активный ученик партнёра (для read-only истории цен без требования pivot).
     */
    private function findPartnerEnabledStudent(int $userId, int $partnerId): ?User
    {
        /** @var User|null $user */
        $user = User::query()
            ->whereKey($userId)
            ->where('partner_id', $partnerId)
            ->where('is_enabled', 1)
            ->first();

        return $user;
    }

    private function findPartnerTeam(int $teamId, int $partnerId): ?Team
    {
        if ($teamId <= 0) {
            return null;
        }

        return $this->scopeByPartner(Team::query())
            ->whereKey($teamId)
            ->whereNull('deleted_at')
            ->first();
    }

    /**
     * AJAX / JSON → ответ API; обычный POST (fallback без JS) → редирект на вкладку «по ученикам».
     *
     * @param  array<string, mixed>  $payload
     */
    private function settingPricesUsersJsonOrRedirect(Request $request, array $payload)
    {
        if ($request->ajax() || $request->wantsJson()) {
            return response()->json($payload);
        }

        return redirect()->route('admin.settingPrices.users');
    }

    /**
     * AJAX / JSON → ответ API; обычный POST (fallback без JS) → редирект на вкладку «по месяцам».
     *
     * @param  array<string, mixed>  $payload
     */
    private function settingPricesMonthlyJsonOrRedirect(Request $request, array $payload)
    {
        if ($request->ajax() || $request->wantsJson()) {
            return response()->json($payload);
        }

        return redirect()->route('admin.settingPrices.indexMenu');
    }
}