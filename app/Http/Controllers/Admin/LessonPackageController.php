<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AuditEvent;
use App\Http\Controllers\AdminBaseController;
use App\Http\Requests\Admin\SetManualUserLessonPackagePaidRequest;
use App\Http\Requests\Admin\StoreLessonPackageRequest;
use App\Http\Requests\Admin\StoreUserLessonPackageRequest;
use App\Http\Requests\Admin\UpdateUserLessonPackageAssignmentRequest;
use App\Http\Requests\LessonPackage\FilterRequest;
use App\Models\LessonOccurrenceStatus;
use App\Models\LessonPackage;
use App\Models\Location;
use App\Models\Partner;
use App\Services\Tinkoff\TbankTerminalConfig;
use App\Models\Team;
use App\Models\User;
use App\Models\UserLessonPackage;
use App\Models\UserTableSetting;
use App\Models\UserTeamScheduleSlot;
use App\Services\Audit\AuditContext;
use App\Services\Audit\AuditLogger;
use App\Services\LessonPackages\SchoolCalendarAssignmentEligibilityService;
use App\Services\PartnerContext;
use App\Services\TeamLocationAvailabilityService;
use App\Services\Payments\UserLessonPackagePublicPayService;
use App\Services\SchoolScheduleViewSettingsService;
use App\Services\TeamScheduleCalendarService;
use App\Services\UserLessonPackageAssignmentDeletionService;
use App\Services\UserLessonPackageCalendarPeriodService;
use App\Support\LessonPackagePostpayPermission;
use App\Support\Money;
use App\Support\PartnerLegalEntityMode;
use App\Support\BuildsLogTable;
use Carbon\CarbonImmutable;
use Database\Seeders\LessonOccurrenceStatusesSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class LessonPackageController extends AdminBaseController
{
    use BuildsLogTable;

    private const ASSIGNMENTS_TABLE_KEY = 'lesson_packages_assignments';

    private const PACKAGES_TABLE_KEY = 'lesson_packages_index';

    /** @var list<string> */
    /** @var list<string> */
    private const ASSIGNMENT_SCHEDULE_TYPES = ['fixed', 'flexible', 'no_schedule'];

    public function __construct(
        PartnerContext $partnerContext,
        private readonly SchoolCalendarAssignmentEligibilityService $schoolCalendarAssignmentEligibility,
        private readonly TeamLocationAvailabilityService $teamLocationAvailability,
        private readonly AuditLogger $auditLogger,
    ) {
        parent::__construct($partnerContext);
    }

    public function index()
    {
        return view('admin.lessonPackages.index', $this->packagesIndexViewData());
    }

    /**
     * Дубль вкладки «Абонементы» в разделе «Справочники» (/admin/directories/lesson-packages).
     * Контент и CRUD API те же, что у admin.lesson-packages.index.
     */
    public function directoriesIndex()
    {
        return view('admin.directories.lesson-packages', $this->packagesIndexViewData());
    }

    /**
     * @return array{activeTab: string, packagesHasActiveFilters: bool}
     */
    private function packagesIndexViewData(): array
    {
        $this->requirePartnerId();

        return [
            'activeTab' => 'packages',
            'packagesHasActiveFilters' => false,
        ];
    }

    public function packagesData(Request $request): JsonResponse
    {
        $partnerId = $this->requirePartnerId();

        $validated = $request->validate([
            'name' => ['nullable', 'string'],
            'schedule_type' => ['nullable', 'string'],
            'draw' => ['nullable', 'integer'],
            'start' => ['nullable', 'integer'],
            'length' => ['nullable', 'integer'],
        ]);

        $baseQuery = LessonPackage::query()
            ->where('partner_id', $partnerId);

        $search = trim((string) ($validated['name'] ?? ''));
        if ($search === '' && $request->filled('search.value')) {
            $search = trim((string) $request->input('search.value'));
        }

        if ($search !== '') {
            $like = '%' . $search . '%';
            $baseQuery->where(function ($q) use ($like, $search) {
                $q->where('name', 'like', $like);
                if (ctype_digit($search)) {
                    $q->orWhere('id', (int) $search);
                }
            });
        }

        $scheduleType = trim((string) ($validated['schedule_type'] ?? ''));
        if ($scheduleType !== '' && in_array($scheduleType, LessonPackage::SCHEDULE_TYPES, true)) {
            // Фильтр postpay в UI только при lessonPackages.type.postpay; crafted query без права — пустой результат.
            if ($scheduleType === LessonPackage::SCHEDULE_TYPE_POSTPAY
                && ! LessonPackagePostpayPermission::userCanSelect(Auth::user())) {
                $baseQuery->whereRaw('1 = 0');
            } else {
                $baseQuery->where('schedule_type', $scheduleType);
            }
        }

        $totalRecords = LessonPackage::query()->where('partner_id', $partnerId)->count();
        $recordsFiltered = (clone $baseQuery)->count();

        $orderColumnIndex = $request->input('order.0.column');
        $orderDir = $request->input('order.0.dir', 'desc') === 'asc' ? 'asc' : 'desc';
        $columnsDef = $request->input('columns', []);
        $orderColumnName = null;
        if ($orderColumnIndex !== null && isset($columnsDef[(int) $orderColumnIndex]['name'])) {
            $orderColumnName = $columnsDef[(int) $orderColumnIndex]['name'];
        }

        switch ($orderColumnName) {
            case 'name':
                $baseQuery->orderBy('name', $orderDir)->orderBy('id', 'desc');
                break;
            case 'schedule_type_label':
                $baseQuery->orderBy('schedule_type', $orderDir)->orderBy('id', 'desc');
                break;
            case 'duration_days':
                $baseQuery->orderBy('duration_days', $orderDir)->orderBy('id', 'desc');
                break;
            case 'lessons_count':
                $baseQuery->orderBy('lessons_count', $orderDir)->orderBy('id', 'desc');
                break;
            case 'price_label':
                $baseQuery->orderBy('price_cents', $orderDir)->orderBy('id', 'desc');
                break;
            case 'freeze_label':
                $baseQuery->orderBy('freeze_enabled', $orderDir)
                    ->orderBy('freeze_days', $orderDir)
                    ->orderBy('id', 'desc');
                break;
            case 'id':
                $baseQuery->orderBy('id', $orderDir);
                break;
            default:
                $baseQuery->orderBy('id', 'desc');
                break;
        }

        $start = max(0, (int) ($validated['start'] ?? 0));
        $length = (int) ($validated['length'] ?? 20);
        if ($length <= 0) {
            $length = 20;
        }
        if ($length > 100) {
            $length = 100;
        }

        $packages = (clone $baseQuery)
            ->withCount([
                'userAssignments as partner_assignments_count' => function ($q) use ($partnerId) {
                    $q->whereHas('user', function ($uq) use ($partnerId) {
                        $uq->where('partner_id', $partnerId);
                    });
                },
                'userTeamScheduleSlots as partner_linked_lessons_count' => function ($q) use ($partnerId) {
                    $q->where('partner_id', $partnerId);
                },
            ])
            ->skip($start)
            ->take($length)
            ->get();

        $data = $packages->map(function (LessonPackage $package) {
            $canDelete = (int) ($package->partner_assignments_count ?? 0) === 0
                && (int) ($package->partner_linked_lessons_count ?? 0) === 0;

            return [
                'id' => (int) $package->id,
                'name' => (string) $package->name,
                'schedule_type' => (string) $package->schedule_type,
                'schedule_type_label' => $this->scheduleTypeLabel((string) $package->schedule_type),
                'duration_days' => (int) $package->duration_days,
                'lessons_count' => (int) $package->lessons_count,
                'price_cents' => (int) $package->price_cents,
                'price_label' => number_format(((int) $package->price_cents) / 100, 2, ',', ' ') . ' ₽',
                'freeze_enabled' => (bool) $package->freeze_enabled,
                'freeze_days' => (int) $package->freeze_days,
                'freeze_label' => $package->freeze_enabled ? (string) (int) $package->freeze_days : 'нет',
                'can_delete' => $canDelete,
            ];
        })->values()->all();

        return response()->json([
            'draw' => (int) ($validated['draw'] ?? 0),
            'recordsTotal' => $totalRecords,
            'recordsFiltered' => $recordsFiltered,
            'data' => $data,
        ]);
    }

    public function packagesColumnsSettingsGet(): JsonResponse
    {
        $this->requirePartnerId();

        $settings = UserTableSetting::query()
            ->where('user_id', (int) Auth::id())
            ->where('table_key', self::PACKAGES_TABLE_KEY)
            ->first();

        $columns = $settings?->columns;
        if (! is_array($columns)) {
            $columns = [];
        }

        return response()->json($columns);
    }

    public function packagesColumnsSettingsSave(Request $request): JsonResponse
    {
        $this->requirePartnerId();

        $data = $request->validate([
            'columns' => 'required|array',
        ]);

        $normalized = [];
        foreach ((array) $data['columns'] as $key => $value) {
            $bool = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            $normalized[(string) $key] = $bool ?? false;
        }

        UserTableSetting::updateOrCreate(
            [
                'user_id' => (int) Auth::id(),
                'table_key' => self::PACKAGES_TABLE_KEY,
            ],
            [
                'columns' => $normalized,
            ]
        );

        return response()->json(['success' => true]);
    }

    public function packagesLogs(FilterRequest $request)
    {
        return $this->buildLogDataTable('lesson_package');
    }

    public function assignmentsLogs(FilterRequest $request)
    {
        return $this->buildLogDataTable('user_lesson_package');
    }

    public function schoolSchedule()
    {
        $partnerId = $this->requirePartnerId();

        $locations = Location::query()
            ->where('partner_id', $partnerId)
            ->where('is_enabled', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        $teams = Team::query()
            ->where('partner_id', $partnerId)
            ->orderBy('title')
            ->get(['id', 'title', 'location_id']);

        LessonOccurrenceStatusesSeeder::ensureForPartner($partnerId);
        $schoolCalendarOccurrenceStatuses = LessonOccurrenceStatus::query()
            ->where('partner_id', $partnerId)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['id', 'code', 'title', 'color', 'icon']);

        $schoolScheduleViewSettings = app(SchoolScheduleViewSettingsService::class)
            ->getForUserId((int) Auth::id());

        return view('admin.lessonPackages.index', [
            'activeTab' => 'school-schedule',
            'locations' => $locations,
            'teams' => $teams,
            'weekdays' => self::weekdaysMap(),
            'schoolCalendarOccurrenceStatuses' => $schoolCalendarOccurrenceStatuses,
            'schoolScheduleViewSettings' => $schoolScheduleViewSettings,
        ]);
    }

    public function schoolScheduleLogs(FilterRequest $request)
    {
        return $this->buildLogDataTable('schedule');
    }

    public function schoolScheduleWeek(Request $request, TeamScheduleCalendarService $calendarService): JsonResponse
    {
        $partnerId = $this->requirePartnerId();

        $weekParam = $request->query('week');
        $weekMonday = $weekParam
            ? CarbonImmutable::parse((string) $weekParam)->startOfWeek(Carbon::MONDAY)->startOfDay()
            : CarbonImmutable::now()->startOfWeek(Carbon::MONDAY)->startOfDay();

        $locationRaw = $request->query('location_id');
        $locationFilter = null;
        if ($locationRaw !== null && $locationRaw !== '') {
            if ((string) $locationRaw === 'none') {
                $locationFilter = 'none';
            } elseif (ctype_digit((string) $locationRaw)) {
                $locationFilter = (string) (int) $locationRaw;
            }
        }

        $occurrences = $calendarService->occurrencesForWeek($partnerId, $weekMonday, $locationFilter);

        return response()->json([
            'week_start' => $weekMonday->toDateString(),
            'occurrences' => $occurrences,
        ]);
    }

    /**
     * Фиксированные абонементы для модалки назначения с календаря.
     */
    public function schoolScheduleFixedPackages(): JsonResponse
    {
        $partnerId = $this->requirePartnerId();

        $packages = LessonPackage::query()
            ->where('partner_id', $partnerId)
            ->where('is_active', true)
            ->where('schedule_type', 'fixed')
            ->orderBy('name')
            ->get(['id', 'name', 'lessons_count', 'duration_days']);

        return response()->json([
            'packages' => $packages->map(fn (LessonPackage $p) => [
                'id' => (int) $p->id,
                'name' => (string) $p->name,
                'lessons_count' => (int) $p->lessons_count,
                'duration_days' => (int) $p->duration_days,
            ])->values(),
        ]);
    }

    /**
     * Активные гибкие назначения ученика (для привязки слота).
     */
    public function schoolScheduleFlexibleAssignments(Request $request): JsonResponse
    {
        $partnerId = $this->requirePartnerId();
        $userId = (int) $request->query('user_id', 0);

        if (! $this->schoolCalendarAssignmentEligibility->userBelongsToPartnerAndEnabled($partnerId, $userId)) {
            return response()->json(['assignments' => []]);
        }

        $rows = $this->schoolCalendarAssignmentEligibility
            ->flexibleAssignmentsQuery($partnerId)
            ->where('user_id', $userId)
            ->get();

        $assignments = $rows->map(function (UserLessonPackage $ulp) {
            return [
                'id' => (int) $ulp->id,
                'label' => $this->schoolCalendarAssignmentEligibility->formatFlexibleAssignmentLabel($ulp),
                'lessons_remaining' => (int) $ulp->lessons_remaining,
                'starts_at' => $ulp->starts_at?->format('Y-m-d'),
                'ends_at' => $ulp->ends_at?->format('Y-m-d'),
            ];
        });

        return response()->json(['assignments' => $assignments]);
    }

    /**
     * Назначения с типом «разовое занятие» (no_schedule) с положительным остатком — модалка календаря школы.
     */
    public function schoolScheduleSingleLessonAssignments(Request $request): JsonResponse
    {
        $partnerId = $this->requirePartnerId();
        $userId = (int) $request->query('user_id', 0);

        if (! $this->schoolCalendarAssignmentEligibility->userBelongsToPartnerAndEnabled($partnerId, $userId)) {
            return response()->json(['assignments' => []]);
        }

        $rows = $this->schoolCalendarAssignmentEligibility
            ->singleLessonAssignmentsQuery($partnerId)
            ->where('user_id', $userId)
            ->get();

        $assignments = $rows->map(function (UserLessonPackage $ulp) {
            return [
                'id' => (int) $ulp->id,
                'label' => $this->schoolCalendarAssignmentEligibility->formatSingleLessonAssignmentLabel($ulp),
                'lessons_remaining' => (int) $ulp->lessons_remaining,
                'starts_at' => $ulp->starts_at?->format('Y-m-d'),
                'ends_at' => $ulp->ends_at?->format('Y-m-d'),
            ];
        });

        return response()->json(['assignments' => $assignments]);
    }

    /**
     * Фиксированные назначения без периода (ещё не привязаны к календарю) — для модалки «Фикс» на расписании школы.
     */
    public function schoolScheduleFixedAssignments(Request $request): JsonResponse
    {
        $partnerId = $this->requirePartnerId();
        $userId = (int) $request->query('user_id', 0);

        if (! $this->schoolCalendarAssignmentEligibility->userBelongsToPartnerAndEnabled($partnerId, $userId)) {
            return response()->json(['assignments' => []]);
        }

        $rows = $this->schoolCalendarAssignmentEligibility
            ->fixedAssignmentsQuery($partnerId)
            ->where('user_id', $userId)
            ->get();

        $assignments = $rows->map(function (UserLessonPackage $ulp) {
            return [
                'id' => (int) $ulp->id,
                'label' => $this->schoolCalendarAssignmentEligibility->formatFixedAssignmentLabel($ulp),
                'lessons_remaining' => (int) $ulp->lessons_remaining,
            ];
        });

        return response()->json(['assignments' => $assignments]);
    }

    /**
     * Доступность действий «привязать» в модалке слота календаря школы (по данным партнёра).
     */
    public function schoolScheduleAssignmentAvailability(): JsonResponse
    {
        $partnerId = $this->requirePartnerId();

        return response()->json([
            'flexible' => $this->schoolCalendarAssignmentEligibility->hasAnyFlexible($partnerId),
            'fixed' => $this->schoolCalendarAssignmentEligibility->hasAnyFixed($partnerId),
            'single_lesson' => $this->schoolCalendarAssignmentEligibility->hasAnySingleLesson($partnerId),
        ]);
    }

    /**
     * Select2: ученики с гибким абонементом и положительным остатком занятий (модалка привязки из календаря).
     */
    public function schoolScheduleFlexibleUsersSearch(Request $request): JsonResponse
    {
        $partnerId = $this->requirePartnerId();
        $q = trim((string) $request->query('q', ''));

        $users = User::query()
            ->where('partner_id', $partnerId)
            ->where('is_enabled', 1)
            ->whereHas('lessonPackageAssignments', function ($query) use ($partnerId) {
                $this->schoolCalendarAssignmentEligibility->constrainFlexibleAssignable($query, $partnerId);
            })
            ->when($q !== '', function ($query) use ($q) {
                $like = '%'.$q.'%';
                $query->whereRaw("CONCAT_WS(' ', lastname, name) LIKE ?", [$like]);
            })
            ->orderBy('lastname')
            ->orderBy('name')
            ->limit(30)
            ->with([
                'lessonPackageAssignments' => function ($query) use ($partnerId) {
                    $this->schoolCalendarAssignmentEligibility->constrainFlexibleAssignable($query, $partnerId);
                    $query->with(['lessonPackage:id,name'])
                        ->orderByDesc('id');
                },
            ])
            ->get(['id', 'name', 'lastname']);

        $results = $users->map(function (User $u) {
            $base = trim(($u->lastname ?? '').' '.($u->name ?? ''));
            if ($base === '') {
                $base = '#'.$u->id;
            }

            $parts = [];
            foreach ($u->lessonPackageAssignments as $ulp) {
                $pkgName = $ulp->lessonPackage?->name ?? 'Абонемент';
                $parts[] = $pkgName.' — '.(int) $ulp->lessons_remaining.' з.';
            }

            $text = $parts === [] ? $base : $base.' ('.implode(', ', $parts).')';

            return [
                'id' => (int) $u->id,
                'text' => $text,
            ];
        })->values();

        return response()->json(['results' => $results]);
    }

    /**
     * Select2: ученики с разовым занятием (no_schedule) и положительным остатком — модалка календаря школы.
     */
    public function schoolScheduleSingleLessonUsersSearch(Request $request): JsonResponse
    {
        $partnerId = $this->requirePartnerId();
        $q = trim((string) $request->query('q', ''));

        $users = User::query()
            ->where('partner_id', $partnerId)
            ->where('is_enabled', 1)
            ->whereHas('lessonPackageAssignments', function ($query) use ($partnerId) {
                $this->schoolCalendarAssignmentEligibility->constrainSingleLessonAssignable($query, $partnerId);
            })
            ->when($q !== '', function ($query) use ($q) {
                $like = '%'.$q.'%';
                $query->whereRaw("CONCAT_WS(' ', lastname, name) LIKE ?", [$like]);
            })
            ->orderBy('lastname')
            ->orderBy('name')
            ->limit(30)
            ->with([
                'lessonPackageAssignments' => function ($query) use ($partnerId) {
                    $this->schoolCalendarAssignmentEligibility->constrainSingleLessonAssignable($query, $partnerId);
                    $query->with(['lessonPackage:id,name'])
                        ->orderByDesc('id');
                },
            ])
            ->get(['id', 'name', 'lastname']);

        $results = $users->map(function (User $u) {
            $base = trim(($u->lastname ?? '').' '.($u->name ?? ''));
            if ($base === '') {
                $base = '#'.$u->id;
            }

            $parts = [];
            foreach ($u->lessonPackageAssignments as $ulp) {
                $pkgName = $ulp->lessonPackage?->name ?? 'Абонемент';
                $parts[] = $pkgName.' — '.(int) $ulp->lessons_remaining.' з.';
            }

            $text = $parts === [] ? $base : $base.' ('.implode(', ', $parts).')';

            return [
                'id' => (int) $u->id,
                'text' => $text,
            ];
        })->values();

        return response()->json(['results' => $results]);
    }

    public function assignments(Request $request)
    {
        $partnerId = $this->requirePartnerId();
        $filters = $request->query();

        $packagesListQuery = LessonPackage::query()
            ->where('partner_id', $partnerId)
            ->where('is_active', 1)
            ->orderBy('name');
        if (! LessonPackagePostpayPermission::userCanSelect(Auth::user())) {
            $packagesListQuery->where('schedule_type', '!=', LessonPackage::SCHEDULE_TYPE_POSTPAY);
        }
        $packagesList = $packagesListQuery->get([
            'id', 'name', 'schedule_type', 'duration_days', 'lessons_count', 'price_cents',
        ]);

        /** @var \App\Models\User|null $authUser */
        $authUser = Auth::user();
        $canViewLocations = $authUser?->can('locations.view') ?? false;
        $activeLocations = $canViewLocations
            ? Location::query()
                ->where('partner_id', $partnerId)
                ->where('is_enabled', true)
                ->orderBy('name')
                ->get(['id', 'name'])
            : collect();

        return view('admin.lessonPackages.index', [
            'activeTab' => 'assignments',
            'packagesList' => $packagesList,
            'weekdays' => self::weekdaysMap(),
            'filters' => $filters,
            'assignmentsFilterUser' => $this->resolveAssignmentFilterUserLabel($partnerId, $filters),
            'canViewLocations' => $canViewLocations,
            'activeLocations' => $activeLocations,
            'multiLegalEntityMode' => PartnerLegalEntityMode::isMultiEntity($partnerId),
        ]);
    }

    public function assignmentsColumnsSettingsGet(): JsonResponse
    {
        $this->requirePartnerId();

        $settings = UserTableSetting::query()
            ->where('user_id', (int) Auth::id())
            ->where('table_key', self::ASSIGNMENTS_TABLE_KEY)
            ->first();

        $columns = $settings?->columns;
        if (! is_array($columns)) {
            $columns = [];
        }

        return response()->json($columns);
    }

    public function assignmentsColumnsSettingsSave(Request $request): JsonResponse
    {
        $this->requirePartnerId();

        $data = $request->validate([
            'columns' => 'required|array',
        ]);

        $normalized = [];
        foreach ((array) $data['columns'] as $key => $value) {
            $bool = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($bool === null) {
                $bool = false;
            }
            $normalized[(string) $key] = $bool;
        }

        UserTableSetting::updateOrCreate(
            [
                'user_id' => (int) Auth::id(),
                'table_key' => self::ASSIGNMENTS_TABLE_KEY,
            ],
            [
                'columns' => $normalized,
            ]
        );

        return response()->json(['success' => true]);
    }

    public function assignmentsData(Request $request): JsonResponse
    {
        $partnerId = $this->requirePartnerId();

        $request->validate([
            'draw' => ['nullable', 'integer'],
            'start' => ['nullable', 'integer', 'min:0'],
            'length' => ['nullable', 'integer', 'min:0'],
        ]);

        $draw = (int) $request->input('draw', 0);
        $start = max(0, (int) $request->input('start', 0));
        $length = (int) $request->input('length', 20);
        if ($length <= 0) {
            $length = 20;
        }
        if ($length > 100) {
            $length = 100;
        }

        $baseQuery = UserLessonPackage::query()
            ->select('user_lesson_packages.*')
            ->join('users', 'users.id', '=', 'user_lesson_packages.user_id')
            ->join('lesson_packages', 'lesson_packages.id', '=', 'user_lesson_packages.lesson_package_id')
            ->leftJoin('teams', function ($join) use ($partnerId) {
                $join->on('teams.id', '=', 'user_lesson_packages.team_id')
                    ->where('teams.partner_id', '=', $partnerId)
                    ->whereNull('teams.deleted_at');
            })
            ->where('users.partner_id', $partnerId);

        $recordsTotal = (clone $baseQuery)->count();

        $filteredQuery = clone $baseQuery;
        $this->applyAssignmentListFilters($filteredQuery, $request, $partnerId);

        $recordsFiltered = (clone $filteredQuery)->count();

        $orderColumnIndex = $request->input('order.0.column');
        $orderDir = strtolower((string) $request->input('order.0.dir', 'desc')) === 'asc' ? 'asc' : 'desc';
        /** @var array<int, array<string, mixed>> $columnsDef */
        $columnsDef = $request->input('columns', []);
        $orderColumnName = null;
        if ($orderColumnIndex !== null && isset($columnsDef[(int) $orderColumnIndex]['name'])) {
            $orderColumnName = (string) $columnsDef[(int) $orderColumnIndex]['name'];
        }

        $orderedQuery = clone $filteredQuery;

        switch ($orderColumnName) {
            case 'student':
                $orderedQuery->orderBy('users.lastname', $orderDir)
                    ->orderBy('users.name', $orderDir)
                    ->orderBy('user_lesson_packages.id', 'desc');
                break;
            case 'team':
                $orderedQuery->orderBy('teams.title', $orderDir)
                    ->orderBy('user_lesson_packages.id', 'desc');
                break;
            case 'package':
                $orderedQuery->orderBy('lesson_packages.name', $orderDir)
                    ->orderBy('user_lesson_packages.id', 'desc');
                break;
            case 'period':
                $orderedQuery->orderBy('user_lesson_packages.starts_at', $orderDir)
                    ->orderBy('user_lesson_packages.ends_at', $orderDir)
                    ->orderBy('user_lesson_packages.id', 'desc');
                break;
            case 'fee':
                $orderedQuery->orderBy('user_lesson_packages.fee_amount_cents', $orderDir)
                    ->orderBy('user_lesson_packages.id', 'desc');
                break;
            case 'paid':
                $orderedQuery->orderByRaw(
                    '(CASE WHEN user_lesson_packages.is_manual_paid IS NOT NULL THEN user_lesson_packages.is_manual_paid ELSE user_lesson_packages.is_paid END) '.$orderDir
                )->orderBy('user_lesson_packages.id', 'desc');
                break;
            case 'balance':
                $orderedQuery->orderBy('user_lesson_packages.lessons_remaining', $orderDir)
                    ->orderBy('user_lesson_packages.id', 'desc');
                break;
            case 'type':
                $orderedQuery->orderBy('lesson_packages.schedule_type', $orderDir)
                    ->orderBy('user_lesson_packages.id', 'desc');
                break;
            case 'id':
                $orderedQuery->orderBy('user_lesson_packages.id', $orderDir);
                break;
            default:
                $orderedQuery->orderBy('user_lesson_packages.id', 'desc');
                break;
        }

        /** @var \Illuminate\Database\Eloquent\Collection<int, UserLessonPackage> $rows */
        $rows = $orderedQuery
            ->clone()
            ->skip($start)
            ->take($length)
            ->with(['user:id,name,lastname,partner_id', 'lessonPackage:id,name,schedule_type', 'team:id,title,partner_id'])
            ->get();

        $ulpPublicPayTbankReady = $this->ulpAssignmentPublicPayTbankReady($partnerId);

        $data = $rows->map(fn (UserLessonPackage $a) => $this->assignmentDataTableRow($a, $ulpPublicPayTbankReady))->values()->all();

        return response()->json([
            'draw' => $draw,
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $data,
        ]);
    }

    private function ulpAssignmentPublicPayTbankReady(int $partnerId): bool
    {
        $partner = Partner::query()->find($partnerId);

        return $partner !== null
            && app(\App\Services\Payments\PaymentService::class)->isTbankAvailable($partner);
    }

    /**
     * @return array<string, mixed>
     */
    private function assignmentDataTableRow(UserLessonPackage $a, bool $ulpPublicPayTbankReady): array
    {
        $student = trim(($a->user->lastname ?? '').' '.($a->user->name ?? ''));
        if ($student === '') {
            $student = '—';
        }

        $period = '—';
        if ($a->starts_at && $a->ends_at) {
            $period = $a->starts_at->format('j.m.y').' - '.$a->ends_at->format('j.m.y');
        }

        $sched = (string) ($a->lessonPackage->schedule_type ?? '');
        $typeLabel = match ($sched) {
            'fixed' => 'Фиксированный',
            'flexible' => 'Гибкий',
            'no_schedule' => 'Разовое занятие',
            'postpay' => 'Постоплата',
            default => 'Абонемент',
        };

        $manualNote = trim((string) ($a->manual_paid_note ?? ''));
        $id = (int) $a->id;
        $feeAmountCents = (int) ($a->fee_amount_cents ?? 0);
        $payLinkAvailable = $ulpPublicPayTbankReady
            && ! $a->effective_is_paid
            && $feeAmountCents >= 1000;

        $feeDisplay = Money::formatRub($feeAmountCents).' руб';

        $teamTitle = trim((string) ($a->team?->title ?? ''));

        return [
            'id' => $id,
            'student' => $student,
            'team_label' => $teamTitle !== '' ? $teamTitle : '—',
            'package_name' => (string) ($a->lessonPackage->name ?? '—'),
            'period' => $period,
            'fee' => $feeDisplay,
            'effective_is_paid' => (bool) $a->effective_is_paid,
            'is_manual_paid' => $a->is_manual_paid,
            'manual_paid_note' => $manualNote,
            'balance' => $a->lessons_remaining.' / '.$a->lessons_total,
            'type_label' => $typeLabel,
            'pay_link_available' => $payLinkAvailable,
        ];
    }

    public function issueAssignmentPublicPayLink(UserLessonPackage $assignment, UserLessonPackagePublicPayService $service): JsonResponse
    {
        $this->authorize('lessonPackages.view');
        $this->authorize('setPrices.packageAssignments.view');
        $this->assertAssignmentBelongsToCurrentPartner($assignment);

        if ($assignment->effective_is_paid) {
            return response()->json(['message' => 'Назначение уже оплачено'], 422);
        }

        $partnerId = (int) $this->requirePartnerId();

        if (! $service->partnerTbankConfigured($partnerId)) {
            return response()->json(['message' => 'Оплата T‑Bank не подключена для этого клуба'], 422);
        }

        try {
            $service->assertAmountAllowedForSbp($partnerId, (int) $assignment->id);
        } catch (HttpException $e) {
            return response()->json(['message' => $e->getMessage()], $e->getStatusCode());
        }

        $assignment->loadMissing(['user:id,name,lastname,partner_id', 'lessonPackage:id,name']);
        $hadLink = $assignment->publicPayLink()->exists();
        $previousToken = $hadLink
            ? (string) ($assignment->publicPayLink()->value('token') ?? '')
            : '';

        $link = $service->ensureFreshLink($assignment);
        $rotated = ! $hadLink || $previousToken === '' || $previousToken !== (string) $link->token;

        $this->recordUserLessonPackageAudit(
            AuditEvent::UserLessonPackagePublicPayLinkIssued,
            $assignment,
            $rotated
                ? 'Выдана / перевыпущена ссылка на оплату назначения.'
                : 'Выдана действующая ссылка на оплату назначения.',
        );

        return response()->json([
            'url' => route('ulp.public.pay', ['token' => $link->token], true),
        ]);
    }

    public function showAssignment(UserLessonPackage $assignment): JsonResponse
    {
        $this->authorize('lessonPackages.view');
        $this->authorize('setPrices.packageAssignments.view');
        $this->assertAssignmentBelongsToCurrentPartner($assignment);

        return response()->json([
            'assignment' => $this->assignmentModalPayload($assignment),
        ]);
    }

    public function updateAssignment(
        UpdateUserLessonPackageAssignmentRequest $request,
        UserLessonPackage $assignment,
        UserLessonPackagePublicPayService $publicPayService,
        UserLessonPackageCalendarPeriodService $calendarPeriodService,
    ): JsonResponse|RedirectResponse {
        $this->assertAssignmentBelongsToCurrentPartner($assignment);

        $assignment->loadMissing(['user:id,name,lastname,partner_id', 'lessonPackage:id,name', 'team:id,title']);
        $beforeSnapshot = $this->userLessonPackageAuditSnapshot($assignment);
        $beforePaid = (bool) $assignment->effective_is_paid;

        $validated = $request->validated();
        $feeCents = Money::toCentsOrFail($validated['fee_amount']);
        $feeWasCents = (int) ($assignment->fee_amount_cents ?? 0);
        $feeChanging = $feeWasCents !== $feeCents;

        DB::transaction(function () use ($request, $assignment, $feeCents, $validated, $calendarPeriodService) {
            $assignment->refresh();

            $canManual = $request->user()->can('lessonPackages.manualPaid.manage');
            $paymentStatus = $validated['payment_status'] ?? null;
            $desiredPaid = null;
            if ($canManual && $paymentStatus !== null && $paymentStatus !== '') {
                $desiredPaid = $paymentStatus === 'paid';
            }

            $oldEffectivePaid = $assignment->effective_is_paid;
            $feeChangingInside = (int) ($assignment->fee_amount_cents ?? 0) !== $feeCents;

            $willChangePayment = $desiredPaid !== null && $desiredPaid !== $oldEffectivePaid;

            if ($willChangePayment) {
                $comment = trim((string) ($validated['payment_comment'] ?? ''));

                if ($oldEffectivePaid && ! $desiredPaid) {
                    $assignment->forceFill([
                        'is_manual_paid' => false,
                        'manual_paid_by' => auth()->id(),
                        'manual_paid_at' => now(),
                        'manual_paid_note' => $comment,
                    ]);
                    $assignment->save();
                    $assignment->refresh();

                    $assignment->fee_amount_cents = $feeCents;
                    $assignment->save();

                    $this->applyAssignmentEndsAtIfPresent($assignment, $validated, $calendarPeriodService);

                    return;
                }

                if (! $oldEffectivePaid && $desiredPaid) {
                    $assignment->fee_amount_cents = $feeCents;
                    $assignment->save();
                    $assignment->refresh();

                    $assignment->forceFill([
                        'is_manual_paid' => true,
                        'manual_paid_by' => auth()->id(),
                        'manual_paid_at' => now(),
                        'manual_paid_note' => $comment,
                    ]);
                    $assignment->save();

                    $this->applyAssignmentEndsAtIfPresent($assignment, $validated, $calendarPeriodService);

                    return;
                }
            }

            if ($assignment->effective_is_paid && $feeChangingInside) {
                abort(422, 'Нельзя менять сумму у оплаченного абонемента.');
            }

            $assignment->fee_amount_cents = $feeCents;
            $assignment->save();

            $this->applyAssignmentEndsAtIfPresent($assignment, $validated, $calendarPeriodService);
        });

        $assignment->refresh();
        $publicPayUrl = null;
        if (! $assignment->effective_is_paid) {
            if ($feeChanging) {
                $publicPayUrl = $publicPayService->resetPublicPayAfterFeeChange($assignment);
            } else {
                $publicPayService->invalidatePublicPayIfAmountMismatch($assignment);
            }
        }

        $assignment = $assignment->fresh([
            'user:id,name,lastname,partner_id',
            'lessonPackage:id,name,schedule_type,duration_days,lessons_count',
            'team:id,title',
            'manualPaidBy:id,name,lastname',
        ]);

        $afterSnapshot = $this->userLessonPackageAuditSnapshot($assignment);
        $fieldChanges = $this->diffUserLessonPackageAuditSnapshots($beforeSnapshot, $afterSnapshot);
        if ($fieldChanges !== []) {
            $this->recordUserLessonPackageAudit(
                AuditEvent::UserLessonPackageUpdated,
                $assignment,
                implode("\n", $fieldChanges),
            );
        }

        $afterPaid = (bool) $assignment->effective_is_paid;
        if ($beforePaid !== $afterPaid) {
            $paymentComment = trim((string) ($validated['payment_comment'] ?? ''));
            $paidLabel = $afterPaid ? 'Оплачен' : 'Не оплачен';
            $desc = "Статус оплаты: ".($beforePaid ? 'Оплачен' : 'Не оплачен')." → {$paidLabel}.";
            if ($paymentComment !== '') {
                $desc .= "\nКомментарий: {$paymentComment}";
            }
            $this->recordUserLessonPackageAudit(
                AuditEvent::UserLessonPackageManualPaid,
                $assignment,
                $desc,
            );
        }

        if ($publicPayUrl !== null) {
            $this->recordUserLessonPackageAudit(
                AuditEvent::UserLessonPackagePublicPayLinkIssued,
                $assignment,
                'Ссылка на оплату перевыпущена из‑за изменения суммы.',
            );
        }

        if ($request->expectsJson()) {
            $payload = [
                'success' => true,
                'assignment' => $this->assignmentModalPayload($assignment),
            ];
            if ($publicPayUrl !== null) {
                $payload['public_pay_url'] = $publicPayUrl;
                $payload['public_pay_url_rotated'] = true;
            }

            return response()->json($payload);
        }

        return redirect()
            ->route('admin.lesson-packages.assignments')
            ->with('success', 'Назначение абонемента обновлено.');
    }

    public function destroyAssignment(UserLessonPackage $assignment, UserLessonPackageAssignmentDeletionService $deletionService): JsonResponse
    {
        $this->authorize('lessonPackages.view');
        $this->authorize('setPrices.packageAssignments.view');
        $this->assertAssignmentBelongsToCurrentPartner($assignment);

        $assignment->loadMissing(['user:id,name,lastname,partner_id', 'lessonPackage:id,name', 'team:id,title']);
        $deleteDescription = 'Назначение абонемента удалено.'."\n".$this->formatUserLessonPackageSnapshotDescription($assignment);

        try {
            $deletionService->deleteOrAbort($assignment);
        } catch (HttpException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $e->getStatusCode());
        }

        $this->recordUserLessonPackageAudit(
            AuditEvent::UserLessonPackageDeleted,
            $assignment,
            $deleteDescription,
        );

        return response()->json(['success' => true]);
    }

    public function setAssignmentManualPaid(SetManualUserLessonPackagePaidRequest $request, UserLessonPackage $assignment): JsonResponse
    {
        $this->assertAssignmentBelongsToCurrentPartner($assignment);

        $mode = (string) $request->validated('mode');
        $comment = trim((string) $request->validated('comment'));
        $authorId = auth()->id();
        $beforePaid = (bool) $assignment->effective_is_paid;

        DB::transaction(function () use ($assignment, $mode, $comment, $authorId) {
            $assignment->forceFill([
                'is_manual_paid' => ($mode === 'paid'),
                'manual_paid_by' => $authorId,
                'manual_paid_at' => now(),
                'manual_paid_note' => $comment,
            ]);
            $assignment->save();
        });

        $assignment->refresh();
        $assignment->load(['user:id,name,lastname,partner_id', 'lessonPackage:id,name,schedule_type,duration_days,lessons_count', 'team:id,title', 'manualPaidBy:id,name,lastname']);

        $afterPaid = (bool) $assignment->effective_is_paid;
        if ($beforePaid !== $afterPaid) {
            $paidLabel = $afterPaid ? 'Оплачен' : 'Не оплачен';
            $desc = "Статус оплаты: ".($beforePaid ? 'Оплачен' : 'Не оплачен')." → {$paidLabel}.";
            if ($comment !== '') {
                $desc .= "\nКомментарий: {$comment}";
            }
            $this->recordUserLessonPackageAudit(
                AuditEvent::UserLessonPackageManualPaid,
                $assignment,
                $desc,
            );
        }

        return response()->json([
            'success' => true,
            'assignment' => $this->assignmentModalPayload($assignment),
        ]);
    }

    private function assertAssignmentBelongsToCurrentPartner(UserLessonPackage $assignment): void
    {
        $partnerId = $this->requirePartnerId();
        // Нужны name/lastname: иначе последующий loadMissing в payload не подгрузит связь (уже частично загружена).
        $assignment->loadMissing('user:id,name,lastname,partner_id');

        if (! $assignment->user || (int) $assignment->user->partner_id !== $partnerId) {
            abort(404);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function assignmentModalPayload(UserLessonPackage $ulp): array
    {
        if ($ulp->relationLoaded('user') && $ulp->user) {
            $keys = array_keys($ulp->user->getAttributes());
            if (! in_array('name', $keys, true) || ! in_array('lastname', $keys, true)) {
                $ulp->unsetRelation('user');
            }
        }

        $ulp->loadMissing([
            'user:id,name,lastname,partner_id',
            'lessonPackage:id,name,schedule_type,duration_days,lessons_count',
            'manualPaidBy:id,name,lastname',
        ]);

        if (! $ulp->user) {
            $userDisplay = '—';
        } else {
            $last = trim((string) ($ulp->user->lastname ?? ''));
            $first = trim((string) ($ulp->user->name ?? ''));
            $userDisplay = trim($last.' '.$first);
            if ($userDisplay === '') {
                $userDisplay = '—';
            }
        }

        $sched = (string) ($ulp->lessonPackage->schedule_type ?? '');
        $schedLabel = match ($sched) {
            'fixed' => 'Фиксированный',
            'flexible' => 'Гибкий',
            'no_schedule' => 'Разовое занятие',
            'postpay' => 'Постоплата',
            default => 'Абонемент',
        };

        $manualByDisplay = '';
        if ($ulp->manualPaidBy) {
            $manualByDisplay = trim(($ulp->manualPaidBy->lastname ?? '').' '.($ulp->manualPaidBy->name ?? ''));
        }

        $periodDisplay = '';
        if ($ulp->starts_at && $ulp->ends_at) {
            $periodDisplay = $ulp->starts_at->format('j.m.y')
                .' - '.$ulp->ends_at->format('j.m.y');
        }

        $periodEditable = $ulp->starts_at !== null && $ulp->ends_at !== null;

        return [
            'id' => (int) $ulp->id,
            'user_display' => $userDisplay,
            'lesson_package_name' => (string) ($ulp->lessonPackage->name ?? '—'),
            'period_display' => $periodDisplay,
            'period_start' => $ulp->starts_at?->format('Y-m-d'),
            'period_end' => $ulp->ends_at?->format('Y-m-d'),
            'period_editable' => $periodEditable,
            'lessons_remaining' => (int) $ulp->lessons_remaining,
            'lessons_total' => (int) $ulp->lessons_total,
            'schedule_type_label' => $schedLabel,
            'fee_amount' => (float) Money::fromCents((int) ($ulp->fee_amount_cents ?? 0)),
            'fee_editable' => ! $ulp->effective_is_paid,
            'is_paid' => (bool) $ulp->is_paid,
            'is_manual_paid' => $ulp->is_manual_paid,
            'effective_is_paid' => (bool) $ulp->effective_is_paid,
            'manual_paid_note' => $ulp->manual_paid_note,
            'manual_paid_at' => $ulp->manual_paid_at?->toIso8601String(),
            'manual_paid_by_display' => $manualByDisplay,
            'can_delete' => (int) $ulp->lessons_remaining === (int) $ulp->lessons_total,
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function applyAssignmentEndsAtIfPresent(
        UserLessonPackage $assignment,
        array $validated,
        UserLessonPackageCalendarPeriodService $calendarPeriodService,
    ): void {
        if (! array_key_exists('ends_at', $validated) || $validated['ends_at'] === null) {
            return;
        }

        $calendarPeriodService->updateEndsAt(
            $assignment,
            CarbonImmutable::createFromFormat('Y-m-d', (string) $validated['ends_at'])->startOfDay()
        );
    }

    /**
     * Select2: поиск учеников текущего партнёра для назначения абонементов.
     */
    public function assignmentUsersSearch(Request $request)
    {
        $partnerId = $this->requirePartnerId();
        $q = trim((string) $request->query('q', ''));

        $users = User::query()
            ->where('partner_id', $partnerId)
            ->where('is_enabled', 1)
            ->withSystemRoleUser()
            ->when($q !== '', function ($query) use ($q) {
                $like = '%'.$q.'%';
                $digits = preg_replace('/\D+/', '', $q);
                $query->where(function ($w) use ($like, $digits) {
                    $w->whereRaw("CONCAT_WS(' ', lastname, name) LIKE ?", [$like])
                        ->orWhereRaw("CONCAT_WS(' ', name, lastname) LIKE ?", [$like]);
                    if ($digits !== '') {
                        $w->orWhere('phone', 'like', '%'.$digits.'%');
                    }
                });
            })
            ->orderBy('lastname')
            ->orderBy('name')
            ->limit(30)
            ->get(['id', 'name', 'lastname']);

        $results = $users->map(function (User $u) {
            $text = trim(($u->lastname ?? '').' '.($u->name ?? ''));

            return [
                'id' => (int) $u->id,
                'text' => $text !== '' ? $text : ('#'.$u->id),
            ];
        })->values();

        return response()->json(['results' => $results]);
    }

    public function assignmentTeamsForUser(Request $request): JsonResponse
    {
        $partnerId = $this->requirePartnerId();
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
            ->map(fn (Team $team) => [
                'id' => (int) $team->id,
                'text' => (string) $team->title,
            ])
            ->values();

        return response()->json(['results' => $teams]);
    }

    public function storeAssignment(StoreUserLessonPackageRequest $request)
    {
        $partnerId = $this->requirePartnerId();
        $data = $request->validated();

        /** @var User|null $user */
        $user = User::query()
            ->whereKey((int) $data['user_id'])
            ->where('partner_id', $partnerId)
            ->first();

        if (! $user) {
            return back()->withErrors([
                'user_id' => 'Ученик не найден или недоступен в контексте текущего партнёра.',
            ])->withInput();
        }

        /** @var LessonPackage $package */
        $package = LessonPackage::query()
            ->where('partner_id', $partnerId)
            ->findOrFail((int) $data['lesson_package_id']);

        try {
            /** @var UserLessonPackage $assignment */
            $assignment = DB::transaction(function () use ($data, $user, $package) {
                return UserLessonPackage::query()->create([
                    'user_id' => (int) $user->id,
                    'team_id' => ! empty($data['team_id']) ? (int) $data['team_id'] : null,
                    'lesson_package_id' => (int) $package->id,
                    'starts_at' => null,
                    'ends_at' => null,
                    'lessons_total' => (int) $package->lessons_count,
                    'lessons_remaining' => (int) $package->lessons_count,
                    'fee_amount_cents' => Money::toCentsOrFail($data['fee_amount']),
                    'is_paid' => false,
                    'created_by' => auth()->id(),
                ]);
            });

            $assignment->setRelation('user', $user);
            $assignment->setRelation('lessonPackage', $package);
            if (! empty($data['team_id'])) {
                $assignment->loadMissing('team:id,title');
            }

            $this->recordUserLessonPackageAudit(
                AuditEvent::UserLessonPackageCreated,
                $assignment,
                $this->formatUserLessonPackageSnapshotDescription($assignment),
            );
        } catch (QueryException $e) {
            Log::warning('UserLessonPackage storeAssignment failed', [
                'error' => $e->getMessage(),
            ]);

            return back()->withErrors([
                'lesson_package_id' => 'Не удалось назначить абонемент. Попробуйте ещё раз.',
            ])->withInput();
        }

        return redirect()
            ->route('admin.lesson-packages.assignments')
            ->with('success', 'Абонемент назначен ученику.');
    }

    public function store(StoreLessonPackageRequest $request)
    {
        $data = $request->validated();
        $priceCents = (int) $request->input('price_cents', 0);
        $freezeDays = (int) $request->input('freeze_days', 0);

        $partnerId = $this->requirePartnerId();

        try {
            /** @var LessonPackage $package */
            $package = DB::transaction(function () use ($data, $priceCents, $freezeDays, $partnerId) {
                $freezeEnabled = (bool) ($data['freeze_enabled'] ?? false);
                $freezeDaysStored = $freezeDays;
                $autoAttendanceEnabled = (bool) ($data['auto_attendance_enabled'] ?? false);
                if ((string) $data['schedule_type'] === LessonPackage::SCHEDULE_TYPE_NO_SCHEDULE
                    || (string) $data['schedule_type'] === LessonPackage::SCHEDULE_TYPE_POSTPAY) {
                    $freezeEnabled = false;
                    $freezeDaysStored = 0;
                    $autoAttendanceEnabled = false;
                }

                return LessonPackage::create([
                    'partner_id' => $partnerId,
                    'name' => (string) $data['name'],
                    'schedule_type' => (string) $data['schedule_type'],
                    'duration_days' => (int) $data['duration_days'],
                    'lessons_count' => (int) $data['lessons_count'],
                    'price_cents' => $priceCents,
                    'freeze_enabled' => $freezeEnabled,
                    'freeze_days' => $freezeDaysStored,
                    'auto_attendance_enabled' => $autoAttendanceEnabled,
                    'is_active' => true,
                ]);
            });

            $this->auditLogger->record(
                AuditEvent::LessonPackageCreated,
                AuditContext::make($this->formatLessonPackageSnapshotDescription($package))
                    ->withTarget($package, $package->name)
                    ->withAuthorId($request->user()?->id)
                    ->withPartnerId($partnerId)
                    ->withCreatedAt(Carbon::now())
            );
        } catch (QueryException $e) {
            Log::warning('LessonPackage store failed', [
                'error' => $e->getMessage(),
            ]);

            $payload = [
                'message' => 'Не удалось сохранить абонемент.',
                'errors' => [
                    'name' => [
                        'Не удалось сохранить абонемент.',
                    ],
                ],
            ];

            if ($request->expectsJson()) {
                return response()->json($payload, 422);
            }

            return back()->withErrors($payload['errors'])->withInput();
        }

        if ($request->expectsJson()) {
            return response()->json(['success' => true]);
        }

        return $this->redirectAfterLessonPackageMutation(
            $request,
            'Абонемент успешно создан.'
        );
    }

    public function show(Request $request, LessonPackage $lessonPackage)
    {
        $this->assertLessonPackageBelongsToPartner($lessonPackage, $this->requirePartnerId());

        return response()->json([
            'success' => true,
            'lesson_package' => [
                'id' => (int) $lessonPackage->id,
                'name' => (string) $lessonPackage->name,
                'schedule_type' => (string) $lessonPackage->schedule_type,
                'duration_days' => (int) $lessonPackage->duration_days,
                'lessons_count' => (int) $lessonPackage->lessons_count,
                'price_cents' => (int) $lessonPackage->price_cents,
                'price' => (float) ($lessonPackage->price_cents / 100),
                'freeze_enabled' => (bool) $lessonPackage->freeze_enabled,
                'freeze_days' => (int) $lessonPackage->freeze_days,
                'auto_attendance_enabled' => (bool) $lessonPackage->auto_attendance_enabled,
                'is_active' => (bool) $lessonPackage->is_active,
                'time_slots' => [],
            ],
        ]);
    }

    public function update(StoreLessonPackageRequest $request, LessonPackage $lessonPackage)
    {
        $partnerId = $this->requirePartnerId();
        $this->assertLessonPackageBelongsToPartner($lessonPackage, $partnerId);

        $beforeSnapshot = $this->lessonPackageAuditSnapshot($lessonPackage);

        $data = $request->validated();
        $priceCents = (int) $request->input('price_cents', 0);
        $freezeDays = (int) $request->input('freeze_days', 0);

        try {
            DB::transaction(function () use ($lessonPackage, $data, $priceCents, $freezeDays) {
                $freezeEnabled = (bool) ($data['freeze_enabled'] ?? false);
                $freezeDaysStored = $freezeDays;
                $autoAttendanceEnabled = (bool) ($data['auto_attendance_enabled'] ?? false);
                if ((string) $data['schedule_type'] === LessonPackage::SCHEDULE_TYPE_NO_SCHEDULE
                    || (string) $data['schedule_type'] === LessonPackage::SCHEDULE_TYPE_POSTPAY) {
                    $freezeEnabled = false;
                    $freezeDaysStored = 0;
                    $autoAttendanceEnabled = false;
                }

                $lessonPackage->forceFill([
                    'name' => (string) $data['name'],
                    'schedule_type' => (string) $data['schedule_type'],
                    'duration_days' => (int) $data['duration_days'],
                    'lessons_count' => (int) $data['lessons_count'],
                    'price_cents' => $priceCents,
                    'freeze_enabled' => $freezeEnabled,
                    'freeze_days' => $freezeDaysStored,
                    'auto_attendance_enabled' => $autoAttendanceEnabled,
                ]);
                $lessonPackage->save();
            });

            $lessonPackage->refresh();
            $changes = $this->diffLessonPackageAuditSnapshots(
                $beforeSnapshot,
                $this->lessonPackageAuditSnapshot($lessonPackage),
            );

            if ($changes !== []) {
                $this->auditLogger->record(
                    AuditEvent::LessonPackageUpdated,
                    AuditContext::make(implode("\n", $changes))
                        ->withTarget($lessonPackage, $lessonPackage->name)
                        ->withAuthorId($request->user()?->id)
                        ->withPartnerId($partnerId)
                        ->withCreatedAt(Carbon::now())
                );
            }

            if ($lessonPackage->isPostpay()) {
                app(\App\Services\Postpay\PostpayUsersPriceSync::class)
                    ->resyncUnpaidForPackage((int) $lessonPackage->id);
            }
        } catch (QueryException $e) {
            Log::warning('LessonPackage update failed', [
                'lesson_package_id' => (int) $lessonPackage->id,
                'error' => $e->getMessage(),
            ]);

            $payload = [
                'message' => 'Не удалось сохранить изменения.',
                'errors' => [
                    'name' => [
                        'Не удалось сохранить изменения.',
                    ],
                ],
            ];

            if ($request->expectsJson()) {
                return response()->json($payload, 422);
            }

            return back()->withErrors($payload['errors'])->withInput();
        }

        if ($request->expectsJson()) {
            return response()->json(['success' => true]);
        }

        return $this->redirectAfterLessonPackageMutation(
            $request,
            'Абонемент успешно обновлён.'
        );
    }

    public function destroy(LessonPackage $lessonPackage): JsonResponse
    {
        $partnerId = $this->requirePartnerId();
        $this->assertLessonPackageBelongsToPartner($lessonPackage, $partnerId);

        $assignmentsExist = UserLessonPackage::query()
            ->where('lesson_package_id', $lessonPackage->id)
            ->whereHas('user', function ($q) use ($partnerId) {
                $q->where('partner_id', $partnerId);
            })
            ->exists();

        if ($assignmentsExist) {
            return response()->json([
                'message' => 'Нельзя удалить абонемент: он назначен ученикам.',
                'errors' => [
                    'lesson_package' => ['Нельзя удалить абонемент: он назначен ученикам.'],
                ],
            ], 422);
        }

        $linkedLessonsExist = UserTeamScheduleSlot::query()
            ->where('partner_id', $partnerId)
            ->whereHas('userLessonPackage', function ($q) use ($lessonPackage) {
                $q->where('lesson_package_id', $lessonPackage->id);
            })
            ->exists();

        if ($linkedLessonsExist) {
            return response()->json([
                'message' => 'Нельзя удалить абонемент: есть привязанные занятия в расписании школы.',
                'errors' => [
                    'lesson_package' => ['Нельзя удалить абонемент: есть привязанные занятия в расписании школы.'],
                ],
            ], 422);
        }

        try {
            $this->auditLogger->record(
                AuditEvent::LessonPackageDeleted,
                AuditContext::make("Абонемент удалён: {$lessonPackage->name}. ID: {$lessonPackage->id}.")
                    ->withTarget($lessonPackage, $lessonPackage->name)
                    ->withAuthorId(auth()->id())
                    ->withPartnerId($partnerId)
                    ->withCreatedAt(Carbon::now())
            );

            DB::transaction(function () use ($lessonPackage) {
                $lessonPackage->delete();
            });
        } catch (QueryException $e) {
            Log::warning('LessonPackage destroy failed', [
                'lesson_package_id' => (int) $lessonPackage->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Не удалось удалить абонемент.',
                'errors' => [
                    'lesson_package' => ['Не удалось удалить абонемент.'],
                ],
            ], 422);
        }

        return response()->json(['success' => true]);
    }

    private function redirectAfterLessonPackageMutation(Request $request, string $successMessage): RedirectResponse
    {
        $directoriesUrl = route('admin.directories.lesson-packages.index');
        $previous = (string) url()->previous();

        $routeName = str_starts_with($previous, $directoriesUrl)
            ? 'admin.directories.lesson-packages.index'
            : 'admin.lesson-packages.index';

        return redirect()
            ->route($routeName)
            ->with('success', $successMessage);
    }

    private function scheduleTypeLabel(string $scheduleType): string
    {
        return match ($scheduleType) {
            LessonPackage::SCHEDULE_TYPE_FIXED => 'Фиксированный',
            LessonPackage::SCHEDULE_TYPE_FLEXIBLE => 'Гибкий',
            LessonPackage::SCHEDULE_TYPE_NO_SCHEDULE => 'Разовое занятие',
            LessonPackage::SCHEDULE_TYPE_POSTPAY => 'Постоплата',
            default => $scheduleType,
        };
    }

    /**
     * @return array<string, string>
     */
    private function lessonPackageAuditSnapshot(LessonPackage $package): array
    {
        $priceLabel = number_format(((int) $package->price_cents) / 100, 2, ',', ' ') . ' ₽';
        $freezeLabel = $package->freeze_enabled
            ? ((int) $package->freeze_days) . ' дн.'
            : 'нет';

        return [
            'name' => (string) $package->name,
            'schedule_type' => $this->scheduleTypeLabel((string) $package->schedule_type),
            'duration_days' => (string) (int) $package->duration_days,
            'lessons_count' => (string) (int) $package->lessons_count,
            'price' => $priceLabel,
            'freeze' => $freezeLabel,
            'auto_attendance' => $package->auto_attendance_enabled ? 'Да' : 'Нет',
        ];
    }

    private function formatLessonPackageSnapshotDescription(LessonPackage $package): string
    {
        $snapshot = $this->lessonPackageAuditSnapshot($package);

        return implode("\n", [
            "Название: {$snapshot['name']}",
            "Тип: {$snapshot['schedule_type']}",
            "Срок действия (дни): {$snapshot['duration_days']}",
            "Занятий: {$snapshot['lessons_count']}",
            "Стоимость: {$snapshot['price']}",
            "Заморозка: {$snapshot['freeze']}",
            "Автосписание: {$snapshot['auto_attendance']}",
        ]);
    }

    /**
     * @param  array<string, string>  $before
     * @param  array<string, string>  $after
     * @return list<string>
     */
    private function diffLessonPackageAuditSnapshots(array $before, array $after): array
    {
        $labels = [
            'name' => 'Название',
            'schedule_type' => 'Тип',
            'duration_days' => 'Срок действия (дни)',
            'lessons_count' => 'Занятий',
            'price' => 'Стоимость',
            'freeze' => 'Заморозка',
            'auto_attendance' => 'Автосписание',
        ];

        $changes = [];
        foreach ($labels as $key => $label) {
            if (($before[$key] ?? '') !== ($after[$key] ?? '')) {
                $changes[] = "{$label}: {$before[$key]} → {$after[$key]}";
            }
        }

        return $changes;
    }

    private function recordUserLessonPackageAudit(
        AuditEvent $event,
        UserLessonPackage $assignment,
        string $description,
    ): void {
        $assignment->loadMissing(['user:id,name,lastname,partner_id', 'lessonPackage:id,name']);

        $context = AuditContext::make($description)
            ->withTarget($assignment, $this->userLessonPackageAuditLabel($assignment))
            ->withAuthorId(auth()->id())
            ->withPartnerId($this->requirePartnerId())
            ->withCreatedAt(Carbon::now());

        if ($assignment->user) {
            $context = $context->withUser($assignment->user);
        }

        $this->auditLogger->record($event, $context);
    }

    private function userLessonPackageAuditLabel(UserLessonPackage $assignment): string
    {
        $assignment->loadMissing(['user:id,name,lastname', 'lessonPackage:id,name']);

        $student = $this->userLessonPackageStudentDisplay($assignment);
        $packageName = trim((string) ($assignment->lessonPackage->name ?? ''));
        if ($packageName === '') {
            $packageName = 'Абонемент #'.(int) $assignment->lesson_package_id;
        }

        return $student.' — '.$packageName;
    }

    private function userLessonPackageStudentDisplay(UserLessonPackage $assignment): string
    {
        if (! $assignment->user) {
            return 'Ученик #'.(int) $assignment->user_id;
        }

        $display = trim(($assignment->user->lastname ?? '').' '.($assignment->user->name ?? ''));

        return $display !== '' ? $display : ('Ученик #'.(int) $assignment->user_id);
    }

    /**
     * @return array<string, string>
     */
    private function userLessonPackageAuditSnapshot(UserLessonPackage $assignment): array
    {
        $assignment->loadMissing(['lessonPackage:id,name', 'team:id,title']);

        $feeCents = (int) ($assignment->fee_amount_cents ?? 0);
        $endsAt = $assignment->ends_at
            ? $assignment->ends_at->format('d.m.Y')
            : 'не задана';
        $teamTitle = trim((string) ($assignment->team?->title ?? ''));

        return [
            'package_name' => (string) ($assignment->lessonPackage->name ?? '—'),
            'fee' => Money::formatRub($feeCents, ' ₽'),
            'ends_at' => $endsAt,
            'team' => $teamTitle !== '' ? $teamTitle : '—',
            'balance' => ((int) $assignment->lessons_remaining).' / '.((int) $assignment->lessons_total),
        ];
    }

    private function formatUserLessonPackageSnapshotDescription(UserLessonPackage $assignment): string
    {
        $snapshot = $this->userLessonPackageAuditSnapshot($assignment);
        $student = $this->userLessonPackageStudentDisplay($assignment);

        return implode("\n", [
            'Ученик: '.$student,
            'Абонемент: '.$snapshot['package_name'],
            'Стоимость: '.$snapshot['fee'],
            'Окончание: '.$snapshot['ends_at'],
            'Группа: '.$snapshot['team'],
            'Остаток: '.$snapshot['balance'],
        ]);
    }

    /**
     * @param  array<string, string>  $before
     * @param  array<string, string>  $after
     * @return list<string>
     */
    private function diffUserLessonPackageAuditSnapshots(array $before, array $after): array
    {
        $labels = [
            'package_name' => 'Абонемент',
            'fee' => 'Стоимость',
            'ends_at' => 'Окончание',
            'team' => 'Группа',
            'balance' => 'Остаток',
        ];

        $changes = [];
        foreach ($labels as $key => $label) {
            if (($before[$key] ?? '') !== ($after[$key] ?? '')) {
                $changes[] = "{$label}: {$before[$key]} → {$after[$key]}";
            }
        }

        return $changes;
    }

    private function assertLessonPackageBelongsToPartner(LessonPackage $lessonPackage, int $partnerId): void
    {
        if ((int) $lessonPackage->partner_id !== $partnerId) {
            abort(404);
        }
    }

    private static function weekdaysMap(): array
    {
        return [
            1 => 'Пн',
            2 => 'Вт',
            3 => 'Ср',
            4 => 'Чт',
            5 => 'Пт',
            6 => 'Сб',
            7 => 'Вс',
        ];
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<UserLessonPackage>  $query
     */
    private function applyAssignmentListFilters($query, Request $request, int $partnerId): void
    {
        $filterUserId = $request->query('filter_user_id');
        if ($filterUserId !== null && $filterUserId !== '' && ctype_digit((string) $filterUserId)) {
            $uid = (int) $filterUserId;
            if ($uid > 0) {
                $query->where('users.id', $uid);
            }
        }

        $scheduleType = trim((string) $request->query('filter_schedule_type', ''));
        if ($scheduleType !== '' && in_array($scheduleType, self::ASSIGNMENT_SCHEDULE_TYPES, true)) {
            $query->where('lesson_packages.schedule_type', $scheduleType);
        }

        $paymentStatus = trim((string) $request->query('filter_payment_status', ''));
        if ($paymentStatus === 'paid') {
            $query->whereRaw(
                '(CASE WHEN user_lesson_packages.is_manual_paid IS NOT NULL THEN user_lesson_packages.is_manual_paid ELSE user_lesson_packages.is_paid END) = 1'
            );
        } elseif ($paymentStatus === 'unpaid') {
            $query->whereRaw(
                '(CASE WHEN user_lesson_packages.is_manual_paid IS NOT NULL THEN user_lesson_packages.is_manual_paid ELSE user_lesson_packages.is_paid END) = 0'
            );
        }

        $lessonsRemaining = trim((string) $request->query('filter_lessons_remaining', ''));
        if ($lessonsRemaining === 'has') {
            $query->where('user_lesson_packages.lessons_remaining', '>', 0);
        } elseif ($lessonsRemaining === 'zero') {
            $query->where('user_lesson_packages.lessons_remaining', 0);
        }

        /** @var \App\Models\User|null $filterActor */
        $filterActor = Auth::user();
        if ($filterActor?->can('locations.view')) {
            $this->teamLocationAvailability->applyDebtUserTeamLocationFilter(
                $query,
                $partnerId,
                $request->query('filter_location_id')
            );
        }

        $userStatus = $this->resolveAssignmentUserStatusFilter($request);
        if ($userStatus === 'active') {
            $query->where('users.is_enabled', 1);
        } elseif ($userStatus === 'inactive') {
            $query->where('users.is_enabled', 0);
        }
    }

    /**
     * active / inactive — фильтр по users.is_enabled; null — все ученики.
     * Без параметра status в запросе по умолчанию только активные.
     */
    private function resolveAssignmentUserStatusFilter(Request $request): ?string
    {
        if (! $request->has('status')) {
            return 'active';
        }

        $status = (string) $request->query('status', '');
        if ($status === '') {
            return null;
        }

        if ($status === 'active' || $status === 'inactive') {
            return $status;
        }

        return 'active';
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{id: int, text: string}|null
     */
    private function resolveAssignmentFilterUserLabel(int $partnerId, array $filters): ?array
    {
        $raw = $filters['filter_user_id'] ?? null;
        if ($raw === null || $raw === '' || ! ctype_digit((string) $raw)) {
            return null;
        }
        $uid = (int) $raw;
        if ($uid <= 0) {
            return null;
        }

        $u = User::query()
            ->where('partner_id', $partnerId)
            ->where('id', $uid)
            ->first(['id', 'name', 'lastname']);

        if (! $u) {
            return null;
        }

        $text = trim(($u->lastname ?? '').' '.($u->name ?? ''));

        return [
            'id' => $u->id,
            'text' => $text !== '' ? $text : '—',
        ];
    }
}
