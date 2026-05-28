<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\AdminBaseController;
use App\Http\Requests\Team\FilterRequest;
use App\Models\Location;
use App\Models\Team;
use App\Models\TrainerProfile;
use App\Models\User;
use App\Models\Weekday;
use App\Services\TeamService;
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
use App\Services\PartnerContext;

class TeamController extends AdminBaseController
{
    use BuildsLogTable;

    /** @var TeamService */
    protected TeamService $service;           // ✅ НОВОЕ явное свойство

    public function __construct(TeamService $service, PartnerContext $partnerContext)
    {
        parent::__construct($partnerContext);
        $this->service = $service;
    }

    /**
     * Страница групп — аналог users.index
     */
    public function index()
    {
        $partnerId = $this->requirePartnerId();

        $weekdays = Weekday::all();
        $trainerOptions = $this->trainerOptionsForPartner($partnerId);
        $locationOptions = $this->locationOptionsForPartner($partnerId);

        return view('admin.team', compact('weekdays', 'trainerOptions', 'locationOptions'));
    }

    /**
     * DataTables серверный endpoint для списка групп (упрощённый вариант)
     */

    public function data(Request $request)
    {
        $partnerId = $this->requirePartnerId();

        // валидация входящих параметров DataTables
        $validated = $request->validate([
            'title'                => 'nullable|string',
            'status'               => 'nullable|string', // active / inactive
            'trainer_profile_id'   => 'nullable|string', // id или 'none'
            'location_id'          => 'nullable|string', // id или 'none'

            'draw'   => 'nullable|integer',
            'start'  => 'nullable|integer',
            'length' => 'nullable|integer',
        ]);

        // базовый запрос
        $baseQuery = Team::query()
            ->where('teams.partner_id', $partnerId);

        // Поиск: панель фильтров (title) или глобальный поиск DataTables (search.value)
        $titleSearch = trim((string) ($validated['title'] ?? ''));
        if ($titleSearch === '' && $request->filled('search.value')) {
            $titleSearch = trim((string) $request->input('search.value'));
        }

        if ($titleSearch !== '') {
            $like = '%' . $titleSearch . '%';
            $baseQuery->where(function ($q) use ($like, $partnerId, $titleSearch) {
                $q->where('teams.title', 'like', $like);

                if (ctype_digit($titleSearch)) {
                    $q->orWhere('teams.order_by', (int) $titleSearch);
                }

                $q->orWhereHas('trainerProfiles', function ($tq) use ($like, $partnerId) {
                    $tq->where('team_trainer.partner_id', $partnerId)
                        ->whereHas('user', function ($uq) use ($like) {
                            $uq->where('name', 'like', $like)
                                ->orWhere('lastname', 'like', $like);
                        });
                });
            });
        }

        // фильтр: статус
        if (!empty($validated['status'])) {
            if ($validated['status'] === 'active') {
                $baseQuery->where('teams.is_enabled', 1);
            } elseif ($validated['status'] === 'inactive') {
                $baseQuery->where('teams.is_enabled', 0);
            }
        }

        /** @var \App\Models\User|null $filterActor */
        $filterActor = auth()->user();
        $canViewLocations = $filterActor?->can('locations.view') ?? false;

        // фильтр: локация
        $locationFilter = $validated['location_id'] ?? null;
        if ($canViewLocations && $locationFilter !== null && $locationFilter !== '') {
            if ($locationFilter === 'none') {
                $baseQuery->whereDoesntHave('locations', function ($q) use ($partnerId) {
                    $q->where('location_team.partner_id', $partnerId);
                });
            } elseif (ctype_digit((string) $locationFilter)) {
                $locId = (int) $locationFilter;
                $baseQuery->where(function ($q) use ($partnerId, $locId) {
                    $q->whereDoesntHave('locations', function ($lq) use ($partnerId) {
                        $lq->where('location_team.partner_id', $partnerId);
                    })->orWhereHas('locations', function ($lq) use ($partnerId, $locId) {
                        $lq->where('location_team.partner_id', $partnerId)
                            ->where('locations.id', $locId);
                    });
                });
            }
        }

        // фильтр: тренер
        $trainerFilter = $validated['trainer_profile_id'] ?? null;
        if ($trainerFilter !== null && $trainerFilter !== '') {
            if ($trainerFilter === 'none') {
                $baseQuery->whereDoesntHave('trainerProfiles', function ($q) use ($partnerId) {
                    $q->where('team_trainer.partner_id', $partnerId);
                });
            } elseif (ctype_digit((string) $trainerFilter)) {
                $profileId = (int) $trainerFilter;
                $baseQuery->whereHas('trainerProfiles', function ($q) use ($partnerId, $profileId) {
                    $q->where('team_trainer.partner_id', $partnerId)
                        ->where('trainer_profiles.id', $profileId);
                });
            }
        }

        // количество без фильтров
        $totalRecords = Team::where('partner_id', $partnerId)->count();

        // количество c фильтрами
        $recordsFiltered = (clone $baseQuery)->count();

        // Сортировка по имени колонки DataTables (устойчиво при скрытых колонках)
        $orderColumnIndex = $request->input('order.0.column');
        $orderDir         = $request->input('order.0.dir', 'asc');
        $columnsDef       = $request->input('columns', []);
        $orderColumnName    = null;
        if ($orderColumnIndex !== null && isset($columnsDef[(int) $orderColumnIndex]['name'])) {
            $orderColumnName = $columnsDef[(int) $orderColumnIndex]['name'];
        }

        if ($orderColumnName !== null && $orderColumnName !== '') {
            switch ($orderColumnName) {
                case 'rownum':
                    $baseQuery->orderBy('teams.order_by', 'asc')
                        ->orderBy('teams.title', 'asc');
                    break;

                case 'order_by':
                    $baseQuery->orderBy('teams.order_by', $orderDir)
                        ->orderBy('teams.title', 'asc');
                    break;

                case 'title':
                    $baseQuery->orderBy('teams.title', $orderDir);
                    break;

                case 'locations_label':
                    $baseQuery
                        ->leftJoin('location_team', function ($join) use ($partnerId) {
                            $join->on('location_team.team_id', '=', 'teams.id')
                                ->where('location_team.partner_id', '=', $partnerId);
                        })
                        ->leftJoin('locations', 'locations.id', '=', 'location_team.location_id')
                        ->select('teams.*')
                        ->orderBy('locations.name', $orderDir)
                        ->orderBy('teams.title', 'asc');
                    break;

                case 'trainer_label':
                    $baseQuery
                        ->leftJoin('team_trainer', function ($join) use ($partnerId) {
                            $join->on('team_trainer.team_id', '=', 'teams.id')
                                ->where('team_trainer.partner_id', '=', $partnerId);
                        })
                        ->leftJoin('trainer_profiles', 'trainer_profiles.id', '=', 'team_trainer.trainer_profile_id')
                        ->leftJoin('users as trainer_users', 'trainer_users.id', '=', 'trainer_profiles.user_id')
                        ->select('teams.*')
                        ->orderBy('trainer_users.lastname', $orderDir)
                        ->orderBy('trainer_users.name', $orderDir)
                        ->orderBy('teams.title', 'asc');
                    break;

                case 'status_label':
                    $baseQuery->orderBy('teams.is_enabled', $orderDir)
                        ->orderBy('teams.title', 'asc');
                    break;

                case 'weekdays_label':
                case 'actions':
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
        $start  = $validated['start'] ?? 0;
        $length = $validated['length'] ?? 20;

        $with = ['weekdays', 'trainerProfiles.user'];
        if ($canViewLocations) {
            $with[] = 'locations';
        }

        $teams = $baseQuery
            ->with($with)
            ->skip($start)
            ->take($length)
            ->get();

        $data = $teams->map(function (Team $team) use ($canViewLocations) {
            // Собираем список дней недели (title)
            $weekdaysLabel = '';
            if ($team->relationLoaded('weekdays')) {
                $weekdaysLabel = $team->weekdays
                    ->pluck('title')
                    ->implode(', ');
            }

            $trainerLabel = '';
            if ($team->relationLoaded('trainerProfiles')) {
                $trainerProfile = $team->trainerProfiles->first();
                $trainerLabel = $trainerProfile?->user?->full_name ?? '';
            }

            $locationsLabel = '';
            if ($canViewLocations && $team->relationLoaded('locations')) {
                $locationsLabel = $team->locations->pluck('name')->implode(', ');
            }

            return [
                'id'             => $team->id,
                'order_by'       => $team->order_by,
                'title'          => $team->title,
                'trainer_label'  => $trainerLabel,
                'locations_label' => $locationsLabel,
                'weekdays_label' => $weekdaysLabel,
                'status_label'   => $team->is_enabled ? 'Активна' : 'Неактивна',
                'is_enabled'     => (int) $team->is_enabled,
            ];
        })->toArray();

        return response()->json([
            'draw'            => (int) ($validated['draw'] ?? 0),
            'recordsTotal'    => $totalRecords,
            'recordsFiltered' => $recordsFiltered,
            'data'            => $data,
        ]);
    }

    public function store(StoreRequest $request)
    {
        $authorId = auth()->id();
        $data     = $request->validated();

        if (! $request->user()->can('trainers.view')) {
            unset($data['trainer_profile_id']);
        }

        if (! $request->user()->can('locations.view')) {
            unset($data['location_ids']);
        }

        $team = $this->service->storeWithLogging($data, $authorId);

        if ($request->ajax()) {
            return response()->json([
                'message' => 'Группа создана успешно',
                'team'    => [
                    'id'    => $team->id,
                    'title' => $team->title,
                ],
            ]);
        }

        return redirect()->route('admin.team.index');
    }

    public function edit($id)
    {
        // ✅ НОВОЕ: проверка партнёра и ограничение выборки по partner_id
        $partnerId = $this->requirePartnerId();

        $with = ['weekdays', 'trainerProfiles.user'];
        if (auth()->user()?->can('locations.view')) {
            $with[] = 'locations';
        }

        $team = Team::with($with)
            ->where('partner_id', $partnerId)
            ->where('id', $id)
            ->firstOrFail();

        $weekdays = Weekday::all(); // Получаем все дни недели
        $trainerProfile = $team->trainerProfiles->first();

        $payload = [
            'id'            => $team->id,
            'title'         => $team->title,
            'type'          => $team->type,
            'default_duration_minutes' => $team->default_duration_minutes,
            'order_by'      => $team->order_by,
            'is_enabled'    => $team->is_enabled,
            'trainer_profile_id' => $trainerProfile?->id,
            'team_weekdays' => $team->weekdays, // Дни недели, связанные с командой
            'weekdays'      => $weekdays,       // Все дни недели
        ];

        if (auth()->user()?->can('locations.view')) {
            $payload['location_ids'] = $team->locations->pluck('id')->map(fn ($id) => (int) $id)->values()->all();
        }

        return response()->json($payload);
    }

    /**
     * Старый метод, оставляем на случай, если где-то ещё используется.
     * Вся логика теперь в update().
     */

    public function update(UpdateRequest $request, $id)
    {
        // ✅ НОВОЕ: проверяем контекст партнёра
        $partnerId = $this->requirePartnerId();

        $authorId = auth()->id();
        $data     = $request->validated();
        $canEditSchedule = $request->user()->can('schedule.view');

        if (! $canEditSchedule) {
            unset($data['weekdays']);
        }

        if (! $request->user()->can('trainers.view')) {
            unset($data['trainer_profile_id']);
        }

        if (! $request->user()->can('locations.view')) {
            unset($data['location_ids']);
        }

        // Попытка загрузить команду по ID и партнёру
        $team = Team::with('weekdays')
            ->where('partner_id', $partnerId)
            ->where('id', $id)
            ->first();

        // Проверка наличия команды
        if (!$team || !$team->id) {
            return response()->json(['error' => 'Команда не найдена или принадлежит другому партнёру'], 404);
        }

        DB::transaction(function () use ($data, $authorId, $team, $canEditSchedule) {
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

            // ✅ Считаем дифф только по изменённым полям и готовим многострочное описание
            $changes = [];

            // Название
            if (array_key_exists('title', $data) && $data['title'] !== $oldData->title) {
                $changes[] = "Название: \"{$oldData->title}\" → \"{$data['title']}\"";
            }

            // Тип
            if (array_key_exists('type', $data) && (string) $data['type'] !== (string) $oldData->type) {
                $oldType = ((string) $oldData->type) === 'individual' ? 'индивидуально' : 'группа';
                $newType = ((string) $data['type']) === 'individual' ? 'индивидуально' : 'группа';
                $changes[] = "Тип: {$oldType} → {$newType}";
            }

            // Длительность по умолчанию
            if (array_key_exists('default_duration_minutes', $data)) {
                $oldDur = $oldData->default_duration_minutes ?? 'не указана';
                $newDur = $data['default_duration_minutes'] ?? 'не указана';
                if ((string) $oldDur !== (string) $newDur) {
                    $changes[] = "Длительность по умолчанию (мин): {$oldDur} → {$newDur}";
                }
            }

            // Дни недели (только если пользователь может менять расписание)
            if ($canEditSchedule) {
                $newWeekdaysFormatted = collect($data['weekdays'] ?? [])->map(function ($day) use ($weekdaysMap) {
                    return $weekdaysMap[$day] ?? $day;
                })->toArray();

                $oldSet = collect($oldWeekdaysFormatted)->values()->sort()->implode(', ');
                $newSet = collect($newWeekdaysFormatted)->values()->sort()->implode(', ');
                if ($oldSet !== $newSet) {
                    $changes[] = "Дни недели: " . ($oldSet !== '' ? $oldSet : 'не указаны') . " → " . ($newSet !== '' ? $newSet : 'не указаны');
                }
            }

            // ✅ Сортировка
            if (array_key_exists('order_by', $data)) {
                $oldOrder = $oldData->order_by ?? 'не указана';
                $newOrder = $data['order_by'] ?? 'не указана';

                if ((string) $newOrder !== (string) $oldOrder) {
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
                    'type'         => 3,                 // Логи по группам
                    'action'       => 32,               // Обновление группы
                    'target_type'  => 'App\Models\Team',
                    'target_id'    => $team->id,
                    'target_label' => $team->title,
                    'description'  => implode("\n", $changes), // Каждое поле — с новой строки
                    'created_at'   => now(),
                ]);
            }
        });

        return response()->json(['message' => 'Группа успешно обновлена']);
    }

    public function delete(Team $team)
    {
        // ✅ НОВОЕ: защита от удаления чужой группы
        $partnerId = $this->requirePartnerId();

        if ((int) $team->partner_id !== $partnerId) {
            abort(403, 'Доступ запрещён.');
        }

        DB::transaction(function () use ($team) {
            // Обновляем пользователей, устанавливая team_id в null
            User::where('team_id', $team->id)->update(['team_id' => null]);

            // Мягкое удаление группы
            $team->delete();

            // Логирование
            MyLog::create([
                'type'         => 3, // Лог для обновления групп
                'action'       => 33,
                'target_type'  => 'App\Models\Team',
                'target_id'    => $team->id,
                'target_label' => $team->title,
                'description'  => "Группа удалена: {$team->title}. ID: {$team->id}.",
                'created_at'   => now(),
            ]);
        });

        return response()->json(['message' => 'Группа и её связь с пользователями успешно помечены как удалённые']);
    }

    private function trainerOptionsForPartner(int $partnerId)
    {
        return TrainerProfile::query()
            ->with('user')
            ->where('partner_id', $partnerId)
            ->where('is_enabled', true)
            ->whereHas('user', fn ($q) => $q->where('is_enabled', true))
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    private function locationOptionsForPartner(int $partnerId)
    {
        if (! auth()->user()?->can('locations.view')) {
            return collect();
        }

        return Location::query()
            ->where('partner_id', $partnerId)
            ->where('is_enabled', true)
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    public function log(FilterRequest $request)
    {
        return $this->buildLogDataTable(3);
    }
}