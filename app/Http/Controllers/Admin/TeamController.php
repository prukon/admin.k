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




class TeamController extends Controller
{
    public function __construct(TeamService $service)
    {
        $this->service = $service;
    }

    public function index(FilterRequest $request)
    {

        $data = $request->validated();
        $filter = app()->make(TeamFilter::class, ['queryParams'=> array_filter($data)]);
        $partnerId = app('current_partner')->id;

// Выбираем группы, у которых partner_id совпадает с выбранным партнёром,
// применяем дополнительные условия фильтрации, сортировку и пагинацию
        $allTeams = Team::where('partner_id', $partnerId)
            ->filter($filter)
            ->orderBy('order_by', 'asc') // сортировка по полю order_by по возрастанию
            ->paginate(10);

        $weekdays = Weekday::all();

        return view("admin/team", compact("allTeams",
            'weekdays'));
    }

    public function create(Team $team)
    {
        $allTeams = Team::All();
        $weekdays = Weekday::all();
        return view("admin.team.create", compact('team',
            "allTeams",
            'weekdays'));
    }

    public function store2(StoreRequest $request)
    {
        $authorId = auth()->id(); // Авторизованный пользователь
        $data = $request->validated();

        $team = DB::transaction(function () use ($data, $authorId) {
            // Сохранение команды
            $team = $this->service->store($data);

            $weekdaysMap = [
                1 => 'пн',
                2 => 'вт',
                3 => 'ср',
                4 => 'чт',
                5 => 'пт',
                6 => 'сб',
                7 => 'вс',
            ];

            // Проверяем, что поле 'weekdays' существует в массиве $data и форматируем
            $weekdaysFormatted = [];
            if (isset($data['weekdays']) && is_array($data['weekdays'])) {
                $weekdaysFormatted = array_map(function ($day) use ($weekdaysMap) {
                    return $weekdaysMap[$day] ?? $day; // Если дня нет в мапе, вернётся исходное значение
                }, $data['weekdays']);
            }

            // Создание лога
            MyLog::create([
                'type' => 3, // Лог для обновления групп
                'action' => 31, // Лог для создания учетной записи
                'author_id' => $authorId,
                'description' => sprintf(
                    "Название: %s, дни недели: %s, сортировка: %s, активность: %s",
                    $data['title'],
                    $weekdaysFormatted ? implode(', ', $weekdaysFormatted) : 'не указаны', // Если массив пустой, выводим сообщение, что не указаны
                    $data['order_by'] ?? 'не указана', // Если сортировка не указана, записываем "не указана"
                    $data['is_enabled'] ? 'Да' : 'Нет'
                ),
                'created_at' => now(),
            ]);

            return $team;
        });

        // Если запрос AJAX, возвращаем JSON-ответ
        if ($request->ajax()) {
            return response()->json([
                'message' => 'Группа создана успешно',
                'team' => [
                    'id' => $team->id,
                    'title' => $team->title,
                ],
            ], 200);
        }

        return redirect()->route('admin.team.index');
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

    public function update(UpdateRequest $request, $id)
    {
        $authorId = auth()->id();
        $data = $request->validated();

        // Попытка загрузить команду по ID
        $team = Team::with('weekdays')->find($id); // Подгружаем связанные дни недели

        // Проверка наличия команды и её ID перед обновлением
        if (!$team || !$team->id) {
            return response()->json(['error' => 'Команда не найдена или не имеет ID'], 404);
        }

        DB::transaction(function () use ($data, $authorId, $team) {
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
                'author_id' => $authorId,
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

    public function delete(Team $team)
    {
        $authorId = auth()->id(); // Авторизованный пользователь

        DB::transaction(function () use ($team, $authorId) {
            // Обновляем пользователей, устанавливая team_id в null
            \App\Models\User::where('team_id', $team->id)->update(['team_id' => null]);

            // Мягкое удаление группы
            $team->delete();

            // Логирование
            MyLog::create([
                'type' => 3, // Лог для обновления групп
                'action' => 33,
                'author_id' => $authorId,
                'description' => "Группа удалена: {$team->title}. ID: {$team->id}.",
                'created_at' => now(),
            ]);
        });

        return response()->json(['message' => 'Группа и её связь с пользователями успешно помечены как удалённые']);
    }

    public function log(FilterRequest $request)
    {
        $logs = MyLog::with('author')
            ->where('type', 3) // Team логи
            ->select('my_logs.*');
        return DataTables::of($logs)
            ->addColumn('author', function ($log) {
                return $log->author ? $log->author->name : 'Неизвестно';
            })
            ->editColumn('created_at', function ($log) {
                return $log->created_at->format('d.m.Y / H:i:s');
            })
            ->editColumn('action', function ($log) {
                // Логика для преобразования типа
                $typeLabels = [
                    31 => 'Создание группы',
                    32 => 'Изменение группы',
                    33 => 'Удаление группы',
                ];
                return $typeLabels[$log->action] ?? 'Неизвестный тип';
            })
            ->make(true);
    }

}
