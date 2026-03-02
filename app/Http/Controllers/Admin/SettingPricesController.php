<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\AdminBaseController;
use App\Http\Requests\Team\FilterRequest;
use App\Models\Partner;
use App\Models\Setting;
use App\Models\Team;
use App\Models\TeamPrice;
use App\Models\UserPrice;
use App\Models\User;
use App\Models\Weekday;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use App\Models\MyLog;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\DataTables;
use Illuminate\Support\Str;
use App\Support\BuildsLogTable;
use App\Services\PartnerContext;

class SettingPricesController extends AdminBaseController
{
    use BuildsLogTable;

    public function __construct(PartnerContext $partnerContext)
    {
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
            \Log::warning('Не удалось прочитать месяц цен из settings', [
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
            \Log::warning('Не удалось сохранить месяц цен в settings', [
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

        // Список активных учеников текущего партнёра
        $users = User::with('team')
            ->where('is_enabled', 1)
            ->whereHas('team', function ($q) use ($partnerId) {
                $q->where('partner_id', $partnerId)
                    ->whereNull('deleted_at');
            })
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

        $usersTeam = User::where('team_id', $team->id)
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

        \Log::info('$usersPrice:', array_map(function ($item) {
            return $item->toArray();
        }, $usersPrice));

        if ($usersTeam->count() > 0) {
            return response()->json([
                'success'    => true,
                'usersTeam'  => $usersTeam,
                'usersPrice' => $usersPrice,
            ]);
        }

        return response()->json(['success' => false]);
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

            MyLog::create([
                'type'         => 1,
                'action'       => 13, // Изменение цен в одной группе
                'description'  => "Обновлена цена: {$teamPrice} руб. Период: {$selectedDateString}.",
                'target_type'  => 'App\Models\UserPrice',
                'target_id'    => $team->id,
                'target_label' => $teamTitle,
                'created_at'   => now(),
            ]);

            $users = User::where('team_id', $team->id)
                ->where('is_enabled', 1)
                ->get();

            foreach ($users as $user) {
                $userPrice = UserPrice::where('user_id', $user['id'])
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
                    \Log::warning('setPriceAllTeams: команда не найдена или не принадлежит текущему партнёру', [
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

                MyLog::create([
                    'type'         => 1,
                    'action'       => 11, // Изменение цен во всех группах
                    'target_type'  => 'App\Models\UserPrice',
                    'target_id'    => $team->id,
                    'target_label' => $team->title,
                    'description'  => "Обновлена цена: {$teamData['price']} руб. Период: {$selectedDateString}.",
                    'created_at'   => now(),
                ]);

                $users = User::where('team_id', $team->id)
                    ->where('is_enabled', 1)
                    ->get();

                foreach ($users as $user) {
                    $userPrice = UserPrice::where('user_id', $user['id'])
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

        if (is_null($usersPrice) || !is_array($usersPrice)) {
            return response()->json(['error' => 'Некорректные данные'], 400);
        }

        $authorId           = auth()->id();
        $selectedDateString = $selectedDate;
        $selectedDate       = $this->formatedDate($selectedDate);

        DB::transaction(function () use ($selectedDate, $authorId, $selectedDateString, $usersPrice, $partnerId) {
            foreach ($usersPrice as $priceData) {

                $userId = $priceData['user_id'] ?? null;
                if (!$userId) {
                    continue;
                }

                $user = User::with('team')->find($userId);

                if (!$user || !$user->team || (int) $user->team->partner_id !== (int) $partnerId) {
                    \Log::warning('setPriceAllUsers: попытка изменить цену пользователя не своего партнёра', [
                        'user_id'   => $userId,
                        'partnerId' => $partnerId,
                    ]);
                    continue;
                }

                $userPriceRecord = UserPrice::where('user_id', $userId)
                    ->where('new_month', $selectedDate)
                    ->where('is_paid', 0)
                    ->first();

                if ($userPriceRecord && $userPriceRecord->price != $priceData['price']) {
                    $userPriceRecord->update([
                        'price' => $priceData['price'],
                    ]);

                    $userName = $priceData['user']['name'] ?? $user->name ?? 'Неизвестный пользователь';

                    MyLog::create([
                        'type'         => 1,
                        'action'       => 12,
                        'user_id'      => $userId,
                        'target_type'  => 'App\Models\UserPrice',
                        'target_id'    => $userId,
                        'target_label' => $userName,
                        'description'  => "Обновлена цена: {$priceData['price']} руб. Период: {$selectedDateString}.",
                        'created_at'   => now(),
                    ]);
                }
            }
        });

        return response()->json([
            'success'      => true,
            'usersPrice'   => $usersPrice,
            'selectedDate' => $selectedDate,
        ]);
    }

    public function setPriceAllUsers(Request $request)
{
    // Получаем JSON-содержимое запроса и декодируем его
    $data = json_decode($request->getContent(), true);

    // Проверяем, что данные переданы корректно
    $selectedDate = $data['selectedDate'] ?? null;
    $usersPrice   = $data['usersPrice']   ?? null;

    // Проверка данных
    if (is_null($usersPrice) || !is_array($usersPrice)) {
        return response()->json(['error' => 'Некорректные данные'], 400);
    }

    $authorId           = auth()->id(); // Авторизованный пользователь
    $selectedDateString = $selectedDate;
    $selectedDate       = $this->formatedDate($selectedDate); // 'Сентябрь 2024' -> '2024-09-01'

    DB::transaction(function () use ($selectedDate, $authorId, $selectedDateString, $usersPrice) {
        foreach ($usersPrice as $priceData) {
            $userId = $priceData['user_id'] ?? null;
            if (!$userId) {
                continue;
            }

            /** @var UserPrice|null $userPriceRecord */
            $userPriceRecord = UserPrice::where('user_id', $userId)
                ->where('new_month', $selectedDate)
                ->first();

            // 1) Нет записи — не создаём новую (по тесту set_price_all_users_does_not_create_new_records_or_touch_absent_users)
            if (!$userPriceRecord) {
                continue;
            }

            // 2) Если оплачено — не трогаем
            if ($userPriceRecord->is_paid) {
                continue;
            }

            $newPrice = (int)($priceData['price'] ?? 0);

            // 3) Меняем только если цена реально изменилась
            if ((int)$userPriceRecord->price === $newPrice) {
                continue;
            }

            // Обновляем цену
            $userPriceRecord->update([
                'price' => $newPrice,
            ]);

            // Имя для лога — из payload (как в тестах)
            $userName = $priceData['user']['name'] ?? 'Неизвестный пользователь';

            // ПРИМЕНИТЬ справа. Установка цен всем ученикам
            MyLog::create([
                'type'         => 1,
                'action'       => 12, // Лог для обновления цены ученика
                'user_id'      => $userId,
                'target_type'  => 'App\Models\UserPrice',
                'target_id'    => $userId,
                'target_label' => $userName,
                'description'  => "Обновлена цена: {$newPrice} руб. Период: {$selectedDateString}.",
                'created_at'   => now(),
            ]);
        }
    });

    return response()->json([
        'success'      => true,
        'usersPrice'   => $usersPrice,
        'selectedDate' => $selectedDate,
    ]);
}

    /**
     * AJAX: получить цены конкретного ученика по месяцам за год (вкладка "по ученикам")
     */
    public function userYearPrices(Request $request)
    {
        $partnerId = $this->requirePartnerId();

        $data = $request->validate([
            'user_id' => 'required|integer',
            'year'    => 'required|integer|min:2000|max:2100',
        ]);

        $userId = $data['user_id'];
        $year   = $data['year'];

        $user = User::with('team')->find($userId);

        if (!$user || !$user->team || (int) $user->team->partner_id !== (int) $partnerId) {
            return response()->json([
                'success' => false,
                'message' => 'User not found',
            ], 404);
        }

        $prices = UserPrice::where('user_id', $userId)
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
                'month'       => $m,
                'month_label' => $monthLabel,
                'new_month'   => $dateStr,
                'price'       => $priceRow ? (int) $priceRow->price : 0,
                'is_paid'     => $priceRow ? (bool) $priceRow->is_paid : false,
            ];
        }

        return response()->json([
            'success' => true,
            'user'    => [
                'id'        => $user->id,
                'name'      => $user->name,
                'lastname'  => $user->lastname,
                'team_id'   => $user->team_id,
                'team_name' => optional($user->team)->title,
            ],
            'year'   => $year,
            'months' => $months,
        ]);
    }

    /**
     * AJAX: сохранить цены ученика за год (вкладка "по ученикам")
     */
    public function saveUserYearPrices(Request $request)
    {
        $partnerId = $this->requirePartnerId();

        $data = $request->validate([
            'user_id'          => 'required|integer',
            'year'             => 'required|integer|min:2000|max:2100',
            'prices'           => 'required|array',
            'prices.*.new_month' => 'required|date_format:Y-m-d',
            'prices.*.price'     => 'required|numeric|min:0',
        ]);

        $userId = $data['user_id'];
        $year   = $data['year'];
        $items  = $data['prices'];

        $user = User::with('team')->find($userId);
        if (!$user || !$user->team || (int) $user->team->partner_id !== (int) $partnerId) {
            return response()->json([
                'success' => false,
                'message' => 'User not found',
            ], 404);
        }

        $authorId = auth()->id();

        DB::transaction(function () use ($items, $userId, $year, $authorId) {
            foreach ($items as $item) {
                $newMonth = $item['new_month'];
                $price    = (int) $item['price'];

                // защита от рассинхрона по году
                $itemYear = (int) substr($newMonth, 0, 4);
                if ($itemYear !== (int) $year) {
                    continue;
                }

                $userPrice = UserPrice::where('user_id', $userId)
                    ->where('new_month', $newMonth)
                    ->first();

                // вытаскиваем месяц для лога
                $monthInt   = (int) substr($newMonth, 5, 2);
                $monthLabel = $this->ruMonthName($monthInt) . ' ' . $year;

                if ($userPrice) {
                    if (!$userPrice->is_paid && (int) $userPrice->price !== $price) {
                        $userPrice->update([
                            'price' => $price,
                        ]);

                        MyLog::create([
                            'type'         => 1,
                            'action'       => 12,
                            'user_id'      => $userId,
                            'target_type'  => 'App\Models\UserPrice',
                            'target_id'    => $userPrice->id,
                            'target_label' => $userPrice->user->name ?? 'Пользователь',
                            'description'  => "Обновлена цена: {$price} руб. Период: {$monthLabel}.",
                            'created_at'   => now(),
                        ]);
                    }
                } else {
                    $created = UserPrice::create([
                        'user_id'   => $userId,
                        'new_month' => $newMonth,
                        'price'     => $price,
                        'is_paid'   => false,
                    ]);

                    MyLog::create([
                        'type'         => 1,
                        'action'       => 12,
                        'user_id'      => $userId,
                        'target_type'  => 'App\Models\UserPrice',
                        'target_id'    => $created->id,
                        'target_label' => $created->user->name ?? 'Пользователь',
                        'description'  => "Установлена цена: {$price} руб. Период: {$monthLabel}.",
                        'created_at'   => now(),
                    ]);
                }
            }
        });

        return response()->json([
            'success' => true,
        ]);
    }

    // Метод для обработки DataTables запросов (логи)
    public function getLogsData(FilterRequest $request)
    {
        return $this->buildLogDataTable(1);
    }
}