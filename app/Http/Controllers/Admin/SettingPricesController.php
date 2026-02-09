<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Filters\TeamFilter;
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
use function Termwind\dd;
use Yajra\DataTables\DataTables;
use Illuminate\Support\Str;
use App\Support\BuildsLogTable;


class SettingPricesController extends Controller
{
    use BuildsLogTable;

    public function index(FilterRequest $request)
    {
        $partnerId = app('current_partner')->id;

        // 1) Команды партнёра, сразу в нужном порядке
        $allTeams = Team::where('partner_id', $partnerId)
            ->whereNull('deleted_at')
            ->orderBy('order_by', 'asc')
            ->get();

        // 3) Месяц
        Carbon::setLocale('ru');
        $monthString = session('prices_month', Str::ucfirst(Carbon::now()->translatedFormat('F Y')));
        $monthDate   = $this->formatedDate($monthString);

        // 5) Гарантируем наличие TeamPrice на этот месяц для каждой команды
        foreach ($allTeams as $team) {
            TeamPrice::firstOrCreate(
                ['team_id' => $team->id, 'new_month' => $monthDate],
                ['price'   => 0]
            );
        }

        // 6) Цены за месяц, ключ — team_id
        $teamPrices = TeamPrice::where('new_month', $monthDate)
            ->whereHas('team', fn($q) => $q->where('partner_id', $partnerId)->whereNull('deleted_at'))
        ->get()
        ->keyBy('team_id');

    return view('admin.settingPrices', compact('allTeams', 'monthString', 'teamPrices'));
}

    // AJAX ПОДРОБНО. Получение списка пользователей
    public function getTeamPrice2(Request $request)
    {
        // Получаем данные из тела запроса
        $data = json_decode($request->getContent(), true);
        $selectedDate = $data['selectedDate'] ?? null;
        $teamId = $data['teamId'] ?? null;
        $usersTeam = User::where('team_id', $teamId)
            ->where('is_enabled', true)
            ->orderBy('lastname', 'asc')   // сначала по фамилии
            ->orderBy('name', 'asc')       // затем по имени
            ->get();

        $usersPrice = [];
        $selectedDate = $this->formatedDate($selectedDate);
        foreach ($usersTeam as $user) {
            $userPrice = UserPrice::firstOrCreate(
                [
                    'new_month' => $selectedDate,
                    'user_id' => $user->id
                ],
                [
                    'price' => 0
                ]
            );
            $userPrice->name = $user->name;
            $userPrice->refresh();
            $userPrice->load('user'); // Загружаем отношение для каждой модели
            $usersPrice[] = $userPrice;
        }

        // Преобразуем каждую модель UserPrice в массив
        \Log::info('$usersPrice:', array_map(function ($item) {
            return $item->toArray();
        }, $usersPrice));

        if ($usersTeam) {
            return response()->json([
                'success' => true,
                'usersTeam' => $usersTeam,
                'usersPrice' => $usersPrice,
            ]);
        } else {
            return response()->json(['success' => false]);
        }
    }


    public function getTeamPrice(Request $request)
    {
        // 1) Разбираем вход
        $data         = json_decode($request->getContent(), true);
        $selectedDate = $data['selectedDate'] ?? null;
        $teamId       = $data['teamId']       ?? null;

        // 2) Текущий партнёр
        $partnerId = app('current_partner')->id;

        // 3) Проверяем, что команда принадлежит текущему партнёру
        $team = Team::where('id', $teamId)
            ->where('partner_id', $partnerId)
            ->whereNull('deleted_at')
            ->first();

        if (!$team) {
            // Чужая или удалённая команда — 404
            return response()->json([
                'success' => false,
                'message' => 'Team not found',
            ], 404);
        }

        // 4) Берём только активных пользователей этой команды
        $usersTeam = User::where('team_id', $team->id)
            ->where('is_enabled', true)
            ->orderBy('lastname', 'asc')   // сначала по фамилии
            ->orderBy('name', 'asc')       // затем по имени
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

    // AJAX SELECT DATE. Обработчик изменения даты
    public function updateDate(Request $request)
    {
        // 1) Валидация входного параметра
        $request->validate([
            'month' => 'required|string|max:255',
        ]);

        // 2) Сохраняем выбор месяца (например "Сентябрь 2024") в сессии
        $month = ucfirst($request->input('month'));
        session(['prices_month' => $month]);

        // 3) Преобразуем строку "Сентябрь 2024" в формат date для new_month
        $formatedMonth = $this->formatedDate($month);

        // 4) Получаем команды, принадлежащие текущему партнёру
        $partnerId = app('current_partner')->id;
        $teams = Team::where('partner_id', $partnerId)
            ->whereNull('deleted_at')
            ->get();

        // 5) Для каждой из этих команд создаём запись в team_prices, если её ещё нет
        foreach ($teams as $team) {
            TeamPrice::firstOrCreate(
                [
                    'team_id'   => $team->id,
                    'new_month' => $formatedMonth,
                ],
                [
                    'price' => 0,
                ]
            );
        }

        // 6) Отправляем ответ
        return response()->json([
            'success' => true,
            'month'   => $month,
        ]);
    }


//     Помогает преобразовать строку "Сентябрь 2024" в YYYY-MM-01
    protected function formatedDate(string $monthString): string
    {
        // Ваши реализации: разбить на месяц и год, собрать дату первого числа
        // Например:
        $parts = explode(' ', $monthString);
        $ruMonths = [
            'январь'=>1, 'февраль'=>2, 'март'=>3, 'апрель'=>4,
            'май'=>5, 'июнь'=>6, 'июль'=>7, 'август'=>8,
            'сентябрь'=>9, 'октябрь'=>10, 'ноябрь'=>11, 'декабрь'=>12
        ];
        $month  = mb_strtolower($parts[0], 'UTF-8');
        $year   = $parts[1] ?? date('Y');
        $mNum   = $ruMonths[$month] ?? date('n');
        // Возвращаем первый день месяца в формате YYYY-MM-DD
        return sprintf('%04d-%02d-01', (int)$year, $mNum);
    }

    //AJAX Кнопка ОК. Установка цен группе и юзерам.
    public function setTeamPrice2(Request $request)
    {
        $partnerId = app('current_partner')->id;
        // Получаем данные из тела запроса
        $data = json_decode($request->getContent(), true);
        $teamPrice = $data['teamPrice'] ?? null;
        $teamId = $data['teamId'] ?? null;
        $selectedDate = $data['selectedDate'] ?? null;
        $usersTeam = User::where('team_id', $teamId)->get();
        $authorId = auth()->id(); // Авторизованный пользователь
        $teamTitle = Team::where('id', $teamId)->first()->title;
        $selectedDateString = $selectedDate;
        $selectedDate = $this->formatedDate($selectedDate);



        DB::transaction(function () use ($teamId, $selectedDate, $teamPrice, $authorId, $teamTitle, $selectedDateString, $partnerId) {

            TeamPrice::updateOrCreate(
                [
                    'team_id' => $teamId,
                    'new_month' => $selectedDate,
                ],
                [
                    'price' => $teamPrice
                ]
            );
//            Кнопка ОК. Установка цен группе и юзерам.
            MyLog::create([
                'type' => 1,
                'action' => 13, // Изменение цен в одной группе
                'description' => "Обновлена цена: {$teamPrice} руб. Период: {$selectedDateString}.",
                'target_type'  => 'App\Models\UserPrice',
                'target_id'    => $teamId,
                'target_label' => $teamTitle,
                'created_at' => now(),
            ]);


            // Обновляем цены для пользователей
//            $users = User::where('team_id', $teamId)->get(); // Предполагается, что пользователи связаны с командами

            $users = User::where('team_id', $teamId)
                ->where('is_enabled', 1)
                ->get();


            foreach ($users as $user) {
                $userPrice = UserPrice::where('user_id', $user['id'])
                    ->where('new_month', $selectedDate)
                    ->first();

                if ($userPrice) {
                    // Если запись существует и не оплачена, обновляем её
                    if (!$userPrice->is_paid) {
                        $userPrice->update([
                            'price' => $teamPrice
                        ]);
                    }
                } else {
                    // Если записи нет, создаем новую
                    UserPrice::create([
                        'user_id' => $user['id'],
                        'new_month' => $selectedDate,
                        'price' => $teamPrice,
                        'is_paid' => false
                    ]);
                }
            }
        });

        return response()->json([
            'success' => true,
            'teamPrice' => $teamPrice,
            'selectedDate' => $selectedDate,
            'teamId' => $teamId,
        ]);
    }

    public function setTeamPrice(Request $request)
    {
        $partnerId = app('current_partner')->id;

        // 1) Разбираем вход
        $data         = json_decode($request->getContent(), true);
        $teamPrice    = $data['teamPrice']    ?? null;
        $teamId       = $data['teamId']       ?? null;
        $selectedDate = $data['selectedDate'] ?? null;

        // 2) Проверяем, что команда принадлежит текущему партнёру
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

        DB::transaction(function () use ($team, $selectedDate, $teamPrice, $authorId, $teamTitle, $selectedDateString, $partnerId) {

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

            // Обновляем цены только для активных пользователей этой команды
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

    //AJAX ПРИМЕНИТЬ слева.Установка цен всем группам
    public function setPriceAllTeams(Request $request)
    {
        // Получаем данные из тела запроса
        $data = json_decode($request->getContent(), true);
        $selectedDate = $data['selectedDate'] ?? null;
        $teamsData = $data['teamsData'] ?? null;
        if (is_null($teamsData) || !is_array($teamsData)) {
            return response()->json(['error' => 'Invalid teams data1'], 400);
        }
        $selectedDateString = $selectedDate;
        $selectedDate = $this->formatedDate($selectedDate);

        if (is_null($teamsData) || !is_array($teamsData)) {
            return response()->json(['error' => 'Invalid teams data2'], 400);
        }
        $authorId = auth()->id(); // Авторизованный пользователь

        DB::transaction(function () use ($selectedDate, $authorId, $selectedDateString, $teamsData) {
            // Перебираем массив и обновляем цены команд
            foreach ($teamsData as $teamData) {
                // Обновляем цены для групп
                $teamId = $teamData['teamId'];

                $team = \App\Models\Team::select('id', 'title')->find($teamId);

                if (!$team) {
                    \Log::warning('setPriceAllTeams: команда не найдена по teamId', ['teamId' => $teamId]);
                    continue;
                }

                if ($teamId) {
                    // Обновляем или создаем запись в таблице team_prices
                    TeamPrice::updateOrCreate(
                        [
                            'team_id' => $teamId,
                            'new_month' => $selectedDate
                        ],
                        [
                            'price' => $teamData['price']
                        ]
                    );
//                    ПРИМЕНИТЬ слева.Установка цен всем группам
                    MyLog::create([
                        'type' => 1,
                        'action' => 11, // Изменение цен во всех группах
                        'target_type'  => 'App\Models\UserPrice',
                        'target_id'    => $teamId,
                        'target_label' => $team->title,
                        'description' => "Обновлена цена: {$teamData['price']} руб. Период: {$selectedDateString}.",
                        'created_at' => now(),
                    ]);
                }

                $users = User::where('team_id', $teamId)
                    ->where('is_enabled', 1)
                    ->get();

                foreach ($users as $user) {
                    $userPrice = UserPrice::where('user_id', $user['id'])
                        ->where('new_month', $selectedDate)
                        ->first();

                    if ($userPrice) {
                        // Если запись существует и не оплачена, обновляем её
                        if (!$userPrice->is_paid) {
                            $userPrice->update([
                                'price' => $teamData['price']
                            ]);
                        }
                    } else {
                        // Если записи нет, создаем новую
                        UserPrice::create([
                            'user_id' => $user['id'],
                            'new_month' => $selectedDate,
                            'price' => $teamData['price'],
                            'is_paid' => false
                        ]);
                    }
                }

            }
        });
        return response()->json([
            'success' => true,
        ]);
    }

    //AJAX ПРИМЕНИТЬ справа.Установка цен всем ученикам
    public function setPriceAllUsers(Request $request)
    {

        // Получаем JSON-содержимое запроса и декодируем его
        $data = json_decode($request->getContent(), true);

        // Проверяем, что данные переданы корректно
        $selectedDate = $data['selectedDate'] ?? null;
        $usersPrice = $data['usersPrice'] ?? null;

        // Проверка данных
        if (is_null($usersPrice) || !is_array($usersPrice)) {
            return response()->json(['error' => 'Некорректные данные'], 400);
        }

        $authorId = auth()->id(); // Авторизованный пользователь
        $selectedDateString = $selectedDate;
        $selectedDate = $this->formatedDate($selectedDate); // Предполагаем, что эта функция существует для форматирования даты

        DB::transaction(function () use ($selectedDate, $authorId, $selectedDateString, $usersPrice) {
            foreach ($usersPrice as $priceData) {
                $userPriceRecord = UserPrice::where('user_id', $priceData['user_id'])
                    ->where('new_month', $selectedDate)
                    ->where('is_paid', 0)
                    ->first();

                if ($userPriceRecord) {
                    // Проверка, изменилось ли значение `price`
                    if ($userPriceRecord->price != $priceData['price']) {
                        $userPriceRecord->update([
                            'price' => $priceData['price']
                        ]);

                        // Получаем имя через отношение
//                        $userName = $priceData->user->name ?? 'Неизвестный пользователь'; // Защита от null
                        $userName = $priceData['user']['name'] ?? 'Неизвестный пользователь';


//                        ПРИМЕНИТЬ справа.Установка цен всем ученикам
                        MyLog::create([
                            'type' => 1,
                            'action' => 12, // Лог для обновления цены команды
                            'user_id'   => $priceData['user_id'],
                            'target_type'  => 'App\Models\UserPrice',
                            'target_id'    => $priceData['user_id'],
                            'target_label' => $userName,
                            'description' => "Обновлена цена: {$priceData['price']} руб. Период: {$selectedDateString}.",
                            'created_at' => now(),
                        ]);
                    }
                }
            }
        });

//        return response()->json(['status' => 'Цены обновлены при необходимости'], 200);

                return response()->json([
            'success' => true,
            'usersPrice' => $usersPrice,
            'selectedDate' => $selectedDate
        ]);
    }

    // Метод для обработки DataTables запросов

    public function getLogsData(FilterRequest $request)
    {
        return $this->buildLogDataTable(1);
    }

}