<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\AdminBaseController;
use App\Http\Requests\Admin\SetManualUserPricePaidRequest;
use App\Http\Requests\Admin\SetPriceAllUsersRequest;
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
use App\Services\PartnerContext;
use App\Http\Requests\Admin\SaveUserYearPricesRequest;
use App\Http\Requests\Admin\UserYearPricesRequest;
use App\Services\TeamUserSyncService;
use App\Support\UserPriceTeamMembership;
use App\Http\Requests\Admin\UserCustomPaymentStoreRequest;
use App\Http\Requests\Admin\SetManualUserCustomPaymentPaidRequest;
use Illuminate\Support\Carbon as SupportCarbon;

class SettingPricesController extends AdminBaseController
{
    use BuildsLogTable;

    public function __construct(
        PartnerContext $partnerContext,
        private readonly AuditLogger $auditLogger,
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
                ['price'   => 0]
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
                'activeTab'   => 'monthly',
                'teamPrices'  => $teamPrices,
                'allTeams'    => $allTeams,
                'monthString' => $monthString,
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

        // Список активных учеников текущего партнёра (с хотя бы одной группой в pivot)
        $users = User::with(['teams' => fn ($q) => $q->where('teams.partner_id', $partnerId)->whereNull('teams.deleted_at')])
            ->where('partner_id', $partnerId)
            ->where('is_enabled', 1)
            ->whereHas('teams', fn ($q) => $q->where('teams.partner_id', $partnerId)->whereNull('teams.deleted_at'))
            ->orderBy('lastname')
            ->orderBy('name')
            ->get();

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
                DB::raw('ROUND(user_custom_payment.amount) as amount'),
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
            'amount' => (string) $data['amount'],
            'note' => $data['note'] ?? null,
            'is_paid' => false,
        ]);

        $row->refresh();

        return response()->json([
            'success' => true,
            'custom_payment' => $row,
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

        $usersTeam = $team->students()
            ->where('is_enabled', true)
            ->orderBy('lastname', 'asc')
            ->orderBy('name', 'asc')
            ->get();

        $usersPrice   = [];
        $selectedDate = $this->formatedDate($selectedDate);

        foreach ($usersTeam as $user) {
            $userPrice = UserPrice::firstOrCreate(
                [
                    'new_month' => $selectedDate,
                    'user_id'   => $user->id,
                    'team_id'   => $team->id,
                ],
                [
                    'price' => 0,
                ]
            );

            $userPrice->name = $user->name;
            $userPrice->refresh();
            $userPrice->load('user');
            $usersPrice[] = $userPrice;
        }

        $lessonPackages = $this->lessonPackagesForPartnerSelect($partnerId);

        if ($usersTeam->count() > 0) {
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
     * Шаблоны абонементов партнёра для select на вкладке «по месяцам».
     *
     * @return list<array{id: int, name: string, price: float}>
     */
    protected function lessonPackagesForPartnerSelect(int $partnerId): array
    {
        return LessonPackage::query()
            ->where('partner_id', $partnerId)
            ->orderBy('name')
            ->orderBy('id')
            ->get(['id', 'name', 'price_cents'])
            ->map(static function (LessonPackage $package): array {
                return [
                    'id' => (int) $package->id,
                    'name' => (string) $package->name,
                    'price' => round(((int) $package->price_cents) / 100, 2),
                ];
            })
            ->values()
            ->all();
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

        return response()->json([
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


    public function setTeamPrice(Request $request)
    {
        $partnerId = $this->requirePartnerId();

        $data         = json_decode($request->getContent(), true);
        $teamPrice    = $data['teamPrice'] ?? null;
        $teamId       = $data['teamId'] ?? null;
        $selectedDate = $data['selectedDate'] ?? null;

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

        $authorId           = auth()->id();
        $teamTitle          = $team->title;
        $selectedDateString = $selectedDate;
        $selectedDate       = $this->formatedDate($selectedDate);

        DB::transaction(function () use ($team, $selectedDate, $teamPrice, $authorId, $teamTitle, $selectedDateString) {

            TeamPrice::updateOrCreate(
                [
                    'team_id'   => $team->id,
                    'new_month' => $selectedDate,
                ],
                [
                    'price' => $teamPrice,
                ]
            );

            $this->auditLogger->record(
                AuditEvent::PricingTeamApply,
                AuditContext::make("Обновлена цена: {$teamPrice} руб. Период: {$selectedDateString}.")
                    ->withTargetReference('App\Models\UserPrice', (int) $team->id, $teamTitle)
                    ->withCreatedAt(now())
            );

            $users = $team->students()
                ->where('is_enabled', 1)
                ->get();

            foreach ($users as $user) {
                $userPrice = UserPrice::where('user_id', $user['id'])
                    ->where('team_id', $team->id)
                    ->where('new_month', $selectedDate)
                    ->first();

                if ($userPrice) {
                    if (!$userPrice->is_paid) {
                        $userPrice->update([
                            'price' => $teamPrice,
                        ]);
                    }
                } else {
                    UserPrice::create([
                        'user_id'   => $user['id'],
                        'team_id'   => $team->id,
                        'new_month' => $selectedDate,
                        'price'     => $teamPrice,
                        'is_paid'   => false,
                    ]);
                }
            }
        });

        return response()->json([
            'success'      => true,
            'teamPrice'    => $teamPrice,
            'selectedDate' => $selectedDate,
            'teamId'       => $team->id,
        ]);
    }

    // AJAX ПРИМЕНИТЬ слева. Установка цен всем группам
    public function setPriceAllTeams(Request $request)
    {
        $partnerId = $this->requirePartnerId();

        $data         = json_decode($request->getContent(), true);
        $selectedDate = $data['selectedDate'] ?? null;
        $teamsData    = $data['teamsData'] ?? null;

        if (is_null($teamsData) || !is_array($teamsData)) {
            return response()->json(['error' => 'Invalid teams data'], 400);
        }

        $selectedDateString = $selectedDate;
        $selectedDate       = $this->formatedDate($selectedDate);
        $authorId           = auth()->id();

        DB::transaction(function () use ($selectedDate, $authorId, $selectedDateString, $teamsData, $partnerId) {
            foreach ($teamsData as $teamData) {

                $teamId = $teamData['teamId'] ?? null;
                if (!$teamId) {
                    continue;
                }

                $team = $this->scopeByPartner(Team::select('id', 'title'))
                    ->where('id', $teamId)
                    ->whereNull('deleted_at')
                    ->first();

                if (!$team) {
                    Log::warning('setPriceAllTeams: команда не найдена или не принадлежит текущему партнёру', [
                        'teamId'    => $teamId,
                        'partnerId' => $partnerId,
                    ]);
                    continue;
                }

                TeamPrice::updateOrCreate(
                    [
                        'team_id'   => $team->id,
                        'new_month' => $selectedDate,
                    ],
                    [
                        'price' => $teamData['price'],
                    ]
                );

                $this->auditLogger->record(
                    AuditEvent::PricingBulkApply,
                    AuditContext::make("Обновлена цена: {$teamData['price']} руб. Период: {$selectedDateString}.")
                        ->withTargetReference('App\Models\UserPrice', (int) $team->id, $team->title)
                        ->withCreatedAt(now())
                );

                $users = $team->students()
                    ->where('is_enabled', 1)
                    ->get();

                foreach ($users as $user) {
                    $userPrice = UserPrice::where('user_id', $user['id'])
                        ->where('team_id', $team->id)
                        ->where('new_month', $selectedDate)
                        ->first();

                    if ($userPrice) {
                        if (!$userPrice->is_paid) {
                            $userPrice->update([
                                'price' => $teamData['price'],
                            ]);
                        }
                    } else {
                        UserPrice::create([
                            'user_id'   => $user['id'],
                            'team_id'   => $team->id,
                            'new_month' => $selectedDate,
                            'price'     => $teamData['price'],
                            'is_paid'   => false,
                        ]);
                    }
                }
            }
        });

        return response()->json([
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

                if ($userPriceRecord && $userPriceRecord->price != $priceData['price']) {
                    $userPriceRecord->update([
                        'price' => $priceData['price'],
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
            foreach ($usersPrice as $priceData) {
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

                if ($userPriceRecord->is_paid) {
                    continue;
                }

                $newPrice = round((float) ($priceData['price'] ?? 0), 2);

                // lesson_package_id: если ключ не передан — не трогаем существующую ссылку (старые клиенты)
                $packageKeyPresent = array_key_exists('lesson_package_id', $priceData);
                $newPackageId = $packageKeyPresent
                    ? ($priceData['lesson_package_id'] !== null ? (int) $priceData['lesson_package_id'] : null)
                    : ($userPriceRecord->lesson_package_id !== null ? (int) $userPriceRecord->lesson_package_id : null);

                $priceChanged = abs((float) $userPriceRecord->price - $newPrice) >= 0.005;
                $packageChanged = $packageKeyPresent
                    && (int) ($userPriceRecord->lesson_package_id ?? 0) !== (int) ($newPackageId ?? 0);

                if (! $priceChanged && ! $packageChanged) {
                    continue;
                }

                $payload = [];
                if ($priceChanged) {
                    $payload['price'] = $newPrice;
                }
                if ($packageKeyPresent) {
                    $payload['lesson_package_id'] = $newPackageId;
                }

                if ($payload === []) {
                    continue;
                }

                $userPriceRecord->update($payload);

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
            ->get();

        return response()->json([
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
                'price'             => $priceRow ? (int) $priceRow->price : 0,
                'is_paid'           => $priceRow ? (bool) $priceRow->is_paid : false,
                'is_manual_paid'    => $priceRow ? $priceRow->is_manual_paid : null,
                'effective_is_paid' => $priceRow ? (bool) $priceRow->effective_is_paid : false,
                'has_price_row'     => $priceRow !== null,
                'manual_paid_note'  => $priceRow && $priceRow->manual_paid_note
                    ? (string) $priceRow->manual_paid_note
                    : null,
            ];
        }

        return response()->json([
            'success'                => true,
            'can_manage_manual_paid' => $request->user()->can('setPrices.manualPaid.manage'),
            'user'    => [
                'id'        => $user->id,
                'name'      => $user->name,
                'lastname'  => $user->lastname,
                'team_id'   => $teamId,
                'team_name' => $team->title,
            ],
            'year'   => $year,
            'months' => $months,
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

        DB::transaction(function () use ($items, $userId, $teamId, $year, $authorId, $team) {
            foreach ($items as $item) {
                $newMonth = $item['new_month'];
                $price    = (int) $item['price'];

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
                $monthInt   = (int) substr($newMonth, 5, 2);
                $monthLabel = $this->ruMonthName($monthInt) . ' ' . $year;

                if ($userPrice) {
                    if ($userPrice->effective_is_paid) {
                        continue;
                    }

                    if ((int) $userPrice->price !== $price) {
                        $userPrice->update([
                            'price' => $price,
                        ]);

                        $this->auditLogger->record(
                            AuditEvent::PricingStudentApply,
                            AuditContext::make("Обновлена цена: {$price} руб. Период: {$monthLabel}. Группа: {$team->title}.")
                                ->withUserId($userId)
                                ->withTargetReference('App\Models\UserPrice', (int) $userPrice->id, $userPrice->user->name ?? 'Пользователь')
                                ->withCreatedAt(now())
                        );
                    }
                } else {
                    $created = UserPrice::create([
                        'user_id'   => $userId,
                        'team_id'   => $teamId,
                        'new_month' => $newMonth,
                        'price'     => $price,
                        'is_paid'   => false,
                    ]);

                    $this->auditLogger->record(
                        AuditEvent::PricingStudentApply,
                        AuditContext::make("Установлена цена: {$price} руб. Период: {$monthLabel}. Группа: {$team->title}.")
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
}