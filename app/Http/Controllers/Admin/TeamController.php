<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\AdminBaseController;
use App\Http\Requests\Team\FilterRequest;
use App\Models\District;
use App\Models\Location;
use App\Models\SportType;
use App\Models\Team;
use App\Models\TrainerProfile;
use App\Models\User;
use App\Models\Weekday;
use App\Services\TeamService;
use App\Services\TeamUserSyncService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Enums\AuditEvent;
use App\Services\Audit\AuditContext;
use App\Services\Audit\AuditLogger;
use App\Http\Requests\Team\UpdateRequest;
use App\Http\Requests\Team\StoreRequest;
use Illuminate\Http\Request;
use Yajra\DataTables\DataTables;
use App\Support\BuildsLogTable;
use App\Models\UserTableSetting;
use Illuminate\Support\Facades\Auth;
use App\Services\PartnerContext;
use App\Support\PartnerAdminUserOptions;

class TeamController extends AdminBaseController
{
    use BuildsLogTable;

    /** @var TeamService */
    protected TeamService $service;           // ✅ НОВОЕ явное свойство

    public function __construct(
        TeamService $service,
        PartnerContext $partnerContext,
        private readonly AuditLogger $auditLogger,
    )
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
        $districtOptions = $this->districtOptionsForPartner($partnerId);
        $sportTypeOptions = $this->sportTypeOptionsForPartner($partnerId);
        $adminOptions = auth()->user()?->can('locations.view')
            ? PartnerAdminUserOptions::forPartner($partnerId)
            : collect();

        return view('admin.team', compact('weekdays', 'trainerOptions', 'locationOptions', 'districtOptions', 'sportTypeOptions', 'adminOptions'));
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
            'district_id'          => 'nullable|string', // id или 'none'
            'admin_user_id'        => 'nullable|string', // id или 'none'
            'sport_type_id'        => 'nullable|string', // id или 'none'

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
        $canViewSportTypes = $filterActor?->can('sport_types.view') ?? false;

        // фильтр: объект
        $locationFilter = $validated['location_id'] ?? null;
        if ($canViewLocations && $locationFilter !== null && $locationFilter !== '') {
            if ($locationFilter === 'none') {
                $baseQuery->whereNull('teams.location_id');
            } elseif (ctype_digit((string) $locationFilter)) {
                $baseQuery->where('teams.location_id', (int) $locationFilter);
            }
        }

        // фильтр: администратор объекта
        $adminFilter = $validated['admin_user_id'] ?? null;
        if ($canViewLocations && $adminFilter !== null && $adminFilter !== '') {
            if ($adminFilter === 'none') {
                $baseQuery->where(function ($q) {
                    $q->whereNull('teams.location_id')
                        ->orWhereHas('location', fn ($lq) => $lq->whereDoesntHave('adminUsers'));
                });
            } elseif (ctype_digit((string) $adminFilter)) {
                $adminId = (int) $adminFilter;
                $baseQuery->whereHas('location', fn ($lq) => $lq->whereHas('adminUsers', fn ($uq) => $uq->where('users.id', $adminId)));
            }
        }

        // фильтр: район объекта
        $districtFilter = $validated['district_id'] ?? null;
        if ($canViewLocations && $districtFilter !== null && $districtFilter !== '') {
            if ($districtFilter === 'none') {
                $baseQuery->where(function ($q) {
                    $q->whereNull('teams.location_id')
                        ->orWhereHas('location', fn ($lq) => $lq->whereNull('district_id'));
                });
            } elseif (ctype_digit((string) $districtFilter)) {
                $districtId = (int) $districtFilter;
                $baseQuery->whereHas('location', fn ($lq) => $lq->where('district_id', $districtId));
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

        // фильтр: вид спорта
        $sportTypeFilter = $validated['sport_type_id'] ?? null;
        if ($canViewSportTypes && $sportTypeFilter !== null && $sportTypeFilter !== '') {
            if ($sportTypeFilter === 'none') {
                $baseQuery->whereNull('teams.sport_type_id');
            } elseif (ctype_digit((string) $sportTypeFilter)) {
                $baseQuery->where('teams.sport_type_id', (int) $sportTypeFilter);
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
                        ->leftJoin('locations', 'locations.id', '=', 'teams.location_id')
                        ->select('teams.*')
                        ->orderBy('locations.name', $orderDir)
                        ->orderBy('teams.title', 'asc');
                    break;

                case 'district_name':
                    $baseQuery
                        ->leftJoin('locations as team_locations_district_sort', 'team_locations_district_sort.id', '=', 'teams.location_id')
                        ->leftJoin('districts as team_districts_sort', 'team_districts_sort.id', '=', 'team_locations_district_sort.district_id')
                        ->select('teams.*')
                        ->orderBy('team_districts_sort.name', $orderDir)
                        ->orderBy('teams.title', 'asc');
                    break;

                case 'address':
                    $baseQuery
                        ->leftJoin('locations as team_locations_address_sort', 'team_locations_address_sort.id', '=', 'teams.location_id')
                        ->select('teams.*')
                        ->orderBy('team_locations_address_sort.address', $orderDir)
                        ->orderBy('teams.title', 'asc');
                    break;

                case 'sport_type_label':
                    $baseQuery
                        ->leftJoin('sport_types', 'sport_types.id', '=', 'teams.sport_type_id')
                        ->select('teams.*')
                        ->orderBy('sport_types.name', $orderDir)
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

                case 'month_price':
                    $baseQuery->orderBy('teams.month_price', $orderDir)
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
            $with[] = 'location.district';
        }
        if ($canViewSportTypes) {
            $with[] = 'sportType';
        }

        $teams = $baseQuery
            ->with($with)
            ->skip($start)
            ->take($length)
            ->get();

        $data = $teams->map(function (Team $team) use ($canViewLocations, $canViewSportTypes) {
            // Собираем список дней недели (title)
            $weekdaysLabel = '';
            $weekdaysItems = [];
            if ($team->relationLoaded('weekdays')) {
                $weekdaysSorted = $team->weekdays->sortBy('id')->values();
                $weekdaysItems = $weekdaysSorted
                    ->map(fn (Weekday $weekday) => [
                        'id'    => $weekday->id,
                        'short' => $weekday->shortTitle(),
                    ])
                    ->all();
                $weekdaysLabel = $weekdaysSorted
                    ->map(fn (Weekday $weekday) => $weekday->shortTitle())
                    ->implode(', ');
            }

            $trainerLabel = '';
            if ($team->relationLoaded('trainerProfiles')) {
                $trainerProfile = $team->trainerProfiles->first();
                $trainerLabel = $trainerProfile?->user?->full_name ?? '';
            }

            $locationName = '';
            $districtName = '';
            $address = '';
            if ($canViewLocations && $team->relationLoaded('location') && $team->location) {
                $locationName = (string) $team->location->name;
                $districtName = (string) ($team->location->district?->name ?? '');
                $address = (string) ($team->location->address ?? '');
            }

            return [
                'id'             => $team->id,
                'order_by'       => $team->order_by,
                'title'          => $team->title,
                'sport_type_label' => ($canViewSportTypes && $team->relationLoaded('sportType'))
                    ? ($team->sportType?->name ?? '')
                    : '',
                'trainer_label'  => $trainerLabel,
                'locations_label'      => $locationName,
                'locations_label_full' => $locationName,
                'locations_names'      => $locationName !== '' ? [$locationName] : [],
                'district_name'  => $districtName,
                'address'        => $address,
                'weekdays_label'  => $weekdaysLabel,
                'weekdays_items'  => $weekdaysItems,
                'month_price'     => $team->month_price,
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
            unset($data['location_id']);
        }

        if (! $request->user()->can('sport_types.view')) {
            unset($data['sport_type_id']);
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
            $with[] = 'location';
        }
        if (auth()->user()?->can('sport_types.view')) {
            $with[] = 'sportType';
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
            'default_duration_minutes' => $team->default_duration_minutes,
            'month_price'     => $team->month_price,
            'order_by'      => $team->order_by,
            'is_enabled'    => $team->is_enabled,
            'trainer_profile_id' => $trainerProfile?->id,
            'team_weekdays' => $team->weekdays, // Дни недели, связанные с командой
            'weekdays'      => $weekdays,       // Все дни недели
        ];

        if (auth()->user()?->can('locations.view')) {
            $payload['location_id'] = $team->location_id ? (int) $team->location_id : null;
        }

        if (auth()->user()?->can('sport_types.view')) {
            $payload['sport_type_id'] = $team->sport_type_id ? (int) $team->sport_type_id : null;
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
            unset($data['location_id']);
        }

        if (! $request->user()->can('sport_types.view')) {
            unset($data['sport_type_id']);
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

        DB::transaction(function () use ($data, $authorId, $team, $canEditSchedule, $partnerId) {
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

            // Длительность по умолчанию
            if (array_key_exists('default_duration_minutes', $data)) {
                $oldDur = $oldData->default_duration_minutes ?? 'не указана';
                $newDur = $data['default_duration_minutes'] ?? 'не указана';
                if ((string) $oldDur !== (string) $newDur) {
                    $changes[] = "Длительность по умолчанию (мин): {$oldDur} → {$newDur}";
                }
            }

            // Стоимость в месяц
            if (array_key_exists('month_price', $data)) {
                $oldPrice = $oldData->month_price ?? 'не указана';
                $newPrice = $data['month_price'] ?? 'не указана';
                if ((string) $oldPrice !== (string) $newPrice) {
                    $changes[] = "Стоимость в месяц: {$oldPrice} → {$newPrice}";
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

            if (array_key_exists('sport_type_id', $data)) {
                $oldSportTypeId = $oldData->sport_type_id;
                $newSportTypeId = $data['sport_type_id'] !== null && $data['sport_type_id'] !== ''
                    ? (int) $data['sport_type_id']
                    : null;

                if ((string) ($oldSportTypeId ?? '') !== (string) ($newSportTypeId ?? '')) {
                    $sportTypeNames = SportType::query()
                        ->where('partner_id', $partnerId)
                        ->whereIn('id', array_filter([$oldSportTypeId, $newSportTypeId]))
                        ->pluck('name', 'id');

                    $oldLabel = $oldSportTypeId ? ($sportTypeNames[$oldSportTypeId] ?? "ID {$oldSportTypeId}") : 'не указан';
                    $newLabel = $newSportTypeId ? ($sportTypeNames[$newSportTypeId] ?? "ID {$newSportTypeId}") : 'не указан';
                    $changes[] = "Вид спорта: {$oldLabel} → {$newLabel}";
                }
            }

            // ✅ Обновление данных через сервис
            $this->service->update($team, $data);

            // ✅ Пишем лог только если есть изменения
            if (!empty($changes)) {
                $this->auditLogger->record(
                    AuditEvent::TeamUpdated,
                    AuditContext::make(implode("\n", $changes))
                        ->withTarget($team, $team->title)
                        ->withCreatedAt(now())
                );
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

        DB::transaction(function () use ($team, $partnerId) {
            app(TeamUserSyncService::class)->detachTeamFromAllStudents((int) $team->id, $partnerId);

            // Мягкое удаление группы
            $team->delete();

            // Логирование
            $this->auditLogger->record(
                AuditEvent::TeamDeleted,
                AuditContext::make("Группа удалена: {$team->title}. ID: {$team->id}.")
                    ->withTarget($team, $team->title)
                    ->withCreatedAt(now())
            );
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

    private function districtOptionsForPartner(int $partnerId)
    {
        if (! auth()->user()?->can('locations.view')) {
            return collect();
        }

        return District::query()
            ->where('partner_id', $partnerId)
            ->where('is_enabled', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    private function sportTypeOptionsForPartner(int $partnerId)
    {
        if (! auth()->user()?->can('sport_types.view')) {
            return collect();
        }

        return SportType::query()
            ->where('partner_id', $partnerId)
            ->where('is_enabled', true)
            ->orderBy('sort')
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    public function log(FilterRequest $request)
    {
        return $this->buildLogDataTable('team');
    }
}