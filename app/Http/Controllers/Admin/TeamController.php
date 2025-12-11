<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Filters\TeamFilter;
use App\Http\Requests\Team\FilterRequest;
use App\Models\Team;
use App\Models\User;
use App\Models\Weekday;
use App\Servises\TeamService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Models\MyLog;
use App\Http\Requests\Team\UpdateRequest;
use App\Http\Requests\Team\StoreRequest;
use Illuminate\Http\Request;
use Yajra\DataTables\DataTables;

use App\Support\BuildsLogTable;



use App\Models\UserTableSetting;
use Illuminate\Support\Facades\Auth;


class TeamController extends Controller
{
    use BuildsLogTable;

    public function __construct(TeamService $service)
    {
        $this->service = $service;
    }






    /**
     * Страница групп — аналог users.index
     */
    public function index()
    {
        $partnerId = app('current_partner')->id;

        // как и раньше: подтягиваем дни недели для модалок
        $weekdays = Weekday::all();

        // allTeams больше не нужен, таблицу грузим через DataTables
        return view('admin.team', compact('weekdays'));
    }

    /**
     * DataTables серверный endpoint для списка групп
     */
    public function data2(Request $request)
    {
        $partnerId = app('current_partner')->id;

        // валидация входящих параметров DataTables
        $validated = $request->validate([
            'title' => 'nullable|string',
            'status' => 'nullable|string', // active / inactive

            'draw' => 'nullable|integer',
            'start' => 'nullable|integer',
            'length' => 'nullable|integer',
        ]);

        // базовый запрос
        $baseQuery = Team::query()
            ->where('teams.partner_id', $partnerId);

        // фильтр: название
        if (!empty($validated['title'])) {
            $value = $validated['title'];
            $like = '%' . $value . '%';
            $baseQuery->where('teams.title', 'like', $like);
        }

        // фильтр: статус
        if (!empty($validated['status'])) {
            if ($validated['status'] === 'active') {
                $baseQuery->where('teams.is_enabled', 1);
            } elseif ($validated['status'] === 'inactive') {
                $baseQuery->where('teams.is_enabled', 0);
            }
        }

        // количество без фильтров
        $totalRecords = Team::where('partner_id', $partnerId)->count();

        // количество c фильтрами
        $recordsFiltered = (clone $baseQuery)->count();

        // сортировка по индексам колонок DataTables
        $orderColumnIndex = $request->input('order.0.column');
        $orderDir = $request->input('order.0.dir', 'asc');

        if ($orderColumnIndex !== null) {
            switch ((int)$orderColumnIndex) {
                case 0: // #
                    $baseQuery->orderBy('teams.order_by', 'asc')
                        ->orderBy('teams.title', 'asc');
                    break;

                case 1: // order_by
                    $baseQuery->orderBy('teams.order_by', $orderDir)
                        ->orderBy('teams.title', 'asc');
                    break;

                case 2: // title
                    $baseQuery->orderBy('teams.title', $orderDir);
                    break;

                case 3: // status_label (is_enabled)
                    $baseQuery->orderBy('teams.is_enabled', $orderDir)
                        ->orderBy('teams.title', 'asc');
                    break;

                case 4: // actions
                default:
                    $baseQuery->orderBy('teams.order_by', 'asc')
                        ->orderBy('teams.title', 'asc');
                    break;
            }
        } else {
            $baseQuery->orderBy('teams.order_by', 'asc')
                ->orderBy('teams.title', 'asc');
        }

        // пагинация
        $start = $validated['start'] ?? 0;
        $length = $validated['length'] ?? 20;

        $teams = $baseQuery
            ->skip($start)
            ->take($length)
            ->get();

        // подготовка данных для таблицы
        $data = $teams->map(function (Team $team) {

            return [
                'id' => $team->id,
                'order_by'     => $team->order_by,
                'title' => $team->title,
                'status_label' => $team->is_enabled ? 'Активна' : 'Неактивна',
                'is_enabled' => (int)$team->is_enabled,
            ];

        })->toArray();

        return response()->json([
            'draw' => (int)($validated['draw'] ?? 0),
            'recordsTotal' => $totalRecords,
            'recordsFiltered' => $recordsFiltered,
            'data' => $data,
        ]);
    }

    /**
     * Вернуть настройки колонок для таблицы "teams_index"
     */
    public function getColumnsSettings2()
    {
        $userId = Auth::id();

        $settings = UserTableSetting::where('user_id', $userId)
            ->where('table_key', 'teams_index')
            ->first();

        $columns = $settings?->columns;

        if (!is_array($columns)) {
            $columns = [];
        }

        return response()->json($columns);
    }

    /**
     * Сохранить настройки колонок
     */
    public function saveColumnsSettings2(Request $request)
    {
        $userId = Auth::id();

        $data = $request->validate([
            'columns' => 'required|array',
        ]);

        $raw = $data['columns'];
        $normalized = [];

        foreach ($raw as $key => $value) {
            $bool = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($bool === null) $bool = false;
            $normalized[$key] = $bool;
        }

        UserTableSetting::updateOrCreate(
            [
                'user_id' => $userId,
                'table_key' => 'teams_index',
            ],
            [
                'columns' => $normalized,
            ]
        );

        return response()->json(['success' => true]);
    }


    public function data(Request $request)
    {
        $partnerId   = app('current_partner')->id;

        // валидация входящих параметров DataTables
        $validated = $request->validate([
            'title'  => 'nullable|string',
            'status' => 'nullable|string', // active / inactive

            'draw'   => 'nullable|integer',
            'start'  => 'nullable|integer',
            'length' => 'nullable|integer',
        ]);

        // базовый запрос
        $baseQuery = Team::query()
            ->where('teams.partner_id', $partnerId);

        // фильтр: название
        if (!empty($validated['title'])) {
            $value = $validated['title'];
            $like  = '%' . $value . '%';
            $baseQuery->where('teams.title', 'like', $like);
        }

        // фильтр: статус
        if (!empty($validated['status'])) {
            if ($validated['status'] === 'active') {
                $baseQuery->where('teams.is_enabled', 1);
            } elseif ($validated['status'] === 'inactive') {
                $baseQuery->where('teams.is_enabled', 0);
            }
        }

        // количество без фильтров
        $totalRecords = Team::where('partner_id', $partnerId)->count();

        // количество c фильтрами
        $recordsFiltered = (clone $baseQuery)->count();

        // сортировка по индексам колонок DataTables
        // 0 – #
        // 1 – order_by
        // 2 – title
        // 3 – weekdays_label (НЕ сортируем, orderable: false)
        // 4 – status_label
        // 5 – actions
        $orderColumnIndex = $request->input('order.0.column');
        $orderDir         = $request->input('order.0.dir', 'asc');

        if ($orderColumnIndex !== null) {
            switch ((int)$orderColumnIndex) {
                case 0: // #
                    $baseQuery->orderBy('teams.order_by', 'asc')
                        ->orderBy('teams.title', 'asc');
                    break;

                case 1: // order_by
                    $baseQuery->orderBy('teams.order_by', $orderDir)
                        ->orderBy('teams.title', 'asc');
                    break;

                case 2: // title
                    $baseQuery->orderBy('teams.title', $orderDir);
                    break;

                case 4: // status_label (is_enabled)
                    $baseQuery->orderBy('teams.is_enabled', $orderDir)
                        ->orderBy('teams.title', 'asc');
                    break;

                case 3: // weekdays_label — игнорируем, оставляем дефолт
                case 5: // actions
                default:
                    $baseQuery->orderBy('teams.order_by', 'asc')
                        ->orderBy('teams.title', 'asc');
                    break;
            }
        } else {
            $baseQuery->orderBy('teams.order_by', 'asc')
                ->orderBy('teams.title', 'asc');
        }

        // пагинация
        $start  = $validated['start']  ?? 0;
        $length = $validated['length'] ?? 20;

        $teams = $baseQuery
            ->with('weekdays') // чтобы собрать расписание
            ->skip($start)
            ->take($length)
            ->get();

        $data = $teams->map(function (Team $team) {

            // Собираем список дней недели (title)
            $weekdaysLabel = '';
            if ($team->relationLoaded('weekdays')) {
                $weekdaysLabel = $team->weekdays
                    ->pluck('title')
                    ->implode(', ');
            }

            return [
                'id'             => $team->id,
                'order_by'       => $team->order_by,
                'title'          => $team->title,
                'weekdays_label' => $weekdaysLabel,
                'status_label'   => $team->is_enabled ? 'Активна' : 'Неактивна',
                'is_enabled'     => (int) $team->is_enabled,
            ];
        })->toArray();

        return response()->json([
            'draw'            => (int)($validated['draw'] ?? 0),
            'recordsTotal'    => $totalRecords,
            'recordsFiltered' => $recordsFiltered,
            'data'            => $data,
        ]);
    }

    /**
     * Вернуть настройки колонок для таблицы "teams_index"
     */
    public function getColumnsSettings()
    {
        $userId = Auth::id();

        $settings = UserTableSetting::where('user_id', $userId)
            ->where('table_key', 'teams_index')
            ->first();

        $columns = $settings?->columns;

        if (!is_array($columns)) {
            $columns = [];
        }

        return response()->json($columns);
    }

    /**
     * Сохранить настройки колонок
     */
    public function saveColumnsSettings(Request $request)
    {
        $userId = Auth::id();

        $data = $request->validate([
            'columns' => 'required|array',
        ]);

        $raw = $data['columns'];
        $normalized = [];

        foreach ($raw as $key => $value) {
            $bool = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($bool === null) $bool = false;
            $normalized[$key] = $bool;
        }

        UserTableSetting::updateOrCreate(
            [
                'user_id'   => $userId,
                'table_key' => 'teams_index',
            ],
            [
                'columns' => $normalized,
            ]
        );

        return response()->json(['success' => true]);
    }



    public function store(StoreRequest $request)
    {
        $authorId = auth()->id();
        $data = $request->validated();
        $team = $this->service->storeWithLogging($data, $authorId);
        if ($request->ajax()) {
            return response()->json([
                'message' => 'Группа создана успешно',
                'team' => [
                    'id' => $team->id,
                    'title' => $team->title,
                ],
            ]);
        }
        return redirect()->route('admin.team.index');
    }

    public function edit($id)
    {
        $team = Team::with('weekdays')->findOrFail($id);
        $weekdays = Weekday::all(); // Получаем все дни недели
        return response()->json([
            'id' => $team->id,
            'title' => $team->title,
            'order_by' => $team->order_by,
            'is_enabled' => $team->is_enabled,
            'team_weekdays' => $team->weekdays, // Дни недели, связанные с командой
            'weekdays' => $weekdays // Все дни недели
        ]);
    }

    public function update2(UpdateRequest $request, $id)
    {
        $partnerId = app('current_partner')->id;

        $authorId = auth()->id();
        $data = $request->validated();

        // Попытка загрузить команду по ID
        $team = Team::with('weekdays')->find($id); // Подгружаем связанные дни недели

        // Проверка наличия команды и её ID перед обновлением
        if (!$team || !$team->id) {
            return response()->json(['error' => 'Команда не найдена или не имеет ID'], 404);
        }

        DB::transaction(function () use ($data, $authorId, $team, $partnerId) {
            // Создаём копию данных с подгруженными днями недели
            $oldData = $team->replicate();
            $oldData->setRelation('weekdays', $team->weekdays); // Подгружаем связанные данные в копию

            // Маппинг чисел в сокращения дней недели
            $weekdaysMap = [
                1 => 'пн',
                2 => 'вт',
                3 => 'ср',
                4 => 'чт',
                5 => 'пт',
                6 => 'сб',
                7 => 'вс',
            ];

            // Преобразование старых и новых дней недели в сокращения
            $oldWeekdaysFormatted = $oldData->weekdays->pluck('id')->map(function ($day) use ($weekdaysMap) {
                return $weekdaysMap[$day] ?? $day;
            })->toArray();

            $newWeekdaysFormatted = collect($data['weekdays'] ?? [])->map(function ($day) use ($weekdaysMap) {
                return $weekdaysMap[$day] ?? $day;
            })->toArray();

            // Логирование изменений
            MyLog::create([
                'type' => 3,
                'action' => 32,
                'description' => sprintf(
                    "Старые данные:
                    Название: %s, дни недели: %s, сортировка: %s, активность: %s.
                    Новые данные:
                    Название: %s, дни недели: %s, сортировка: %s, активность: %s.",
                    $oldData->title,
                    !empty($oldWeekdaysFormatted) ? implode(', ', $oldWeekdaysFormatted) : 'не указаны',
                    $oldData->order_by ?? 'не указана',
                    $oldData->is_enabled ? 'Да' : 'Нет',
                    $data['title'],
                    !empty($newWeekdaysFormatted) ? implode(', ', $newWeekdaysFormatted) : 'не указаны',
                    $data['order_by'] ?? 'не указана',
                    $data['is_enabled'] ? 'Да' : 'Нет'
                ),
                'created_at' => now(),
            ]);

            // Обновление данных с использованием TeamService
            $this->service->update($team, $data);
        });

        return response()->json(['message' => 'Группа успешно обновлена']);
    }

    public function update(UpdateRequest $request, $id)
    {
        $partnerId = app('current_partner')->id;

        $authorId = auth()->id();
        $data = $request->validated();

        // Попытка загрузить команду по ID
        $team = Team::with('weekdays')->find($id); // Подгружаем связанные дни недели

        // Проверка наличия команды и её ID перед обновлением
        if (!$team || !$team->id) {
            return response()->json(['error' => 'Команда не найдена или не имеет ID'], 404);
        }

        DB::transaction(function () use ($data, $authorId, $team, $partnerId) {
            // Создаём копию данных с подгруженными днями недели
            $oldData = $team->replicate();
            $oldData->setRelation('weekdays', $team->weekdays); // Подгружаем связанные данные в копию

            // ✅ Маппинг чисел в сокращения дней недели (оставил как есть)
            $weekdaysMap = [
                1 => 'пн',
                2 => 'вт',
                3 => 'ср',
                4 => 'чт',
                5 => 'пт',
                6 => 'сб',
                7 => 'вс',
            ];

            // ✅ Преобразование старых и новых дней недели в сокращения (оставил как было)
            $oldWeekdaysFormatted = $oldData->weekdays->pluck('id')->map(function ($day) use ($weekdaysMap) {
                return $weekdaysMap[$day] ?? $day;
            })->toArray();

            $newWeekdaysFormatted = collect($data['weekdays'] ?? [])->map(function ($day) use ($weekdaysMap) {
                return $weekdaysMap[$day] ?? $day;
            })->toArray();

            // ✅ Считаем дифф только по изменённым полям и готовим многострочное описание
            $changes = [];


// Название
            if (array_key_exists('title', $data) && $data['title'] !== $oldData->title) {
                $changes[] = "Название: \"{$oldData->title}\" → \"{$data['title']}\"";
            }

// Дни недели
            $oldSet = collect($oldWeekdaysFormatted)->values()->sort()->implode(', ');
            $newSet = collect($newWeekdaysFormatted)->values()->sort()->implode(', ');
            if ($oldSet !== $newSet) {
                $changes[] = "Дни недели: " . ($oldSet !== '' ? $oldSet : 'не указаны') . " → " . ($newSet !== '' ? $newSet : 'не указаны');
            }

// ✅ Сортировка (исправлено)
            if (array_key_exists('order_by', $data)) {
                $oldOrder = $oldData->order_by ?? 'не указана';
                $newOrder = $data['order_by'] ?? 'не указана';

                if ((string)$newOrder !== (string)$oldOrder) {
                    $changes[] = "Сортировка: {$oldOrder} → {$newOrder}";
                }
            }

// Активность
            if (array_key_exists('is_enabled', $data)) {
                $oldEnabled = $oldData->is_enabled ? 'Да' : 'Нет';
                $newEnabled = $data['is_enabled'] ? 'Да' : 'Нет';
                if ($newEnabled !== $oldEnabled) {
                    $changes[] = "Активность: {$oldEnabled} → {$newEnabled}";
                }
            }

            // ✅ Обновление данных через сервис
            $this->service->update($team, $data);

            // ✅ Пишем лог только если есть изменения
            if (!empty($changes)) {
                \App\Models\MyLog::create([
                    'type' => 3,                 // Логи по группам
                    'action' => 32,               // Обновление группы
                    'target_type' => 'App\Models\Team',
                    'target_id' => $team->id,
                    'target_label' => $team->title,
                    'description' => implode("\n", $changes), // Каждое поле — с новой строки
                    'created_at' => now(),
                ]);

            }
        });

        return response()->json(['message' => 'Группа успешно обновлена']);
    }


    public function delete(Team $team)
    {

        DB::transaction(function () use ($team) {
            // Обновляем пользователей, устанавливая team_id в null
            User::where('team_id', $team->id)->update(['team_id' => null]);

            // Мягкое удаление группы
            $team->delete();

            // Логирование
            MyLog::create([
                'type' => 3, // Лог для обновления групп
                'action' => 33,
                'target_type' => 'App\Models\Team',
                'target_id' => $team->id,
                'target_label' => $team->title,
                'description' => "Группа удалена: {$team->title}. ID: {$team->id}.",
                'created_at' => now(),
            ]);
        });

        return response()->json(['message' => 'Группа и её связь с пользователями успешно помечены как удалённые']);
    }

    public function log(FilterRequest $request)
    {
        return $this->buildLogDataTable(3);
    }
}
