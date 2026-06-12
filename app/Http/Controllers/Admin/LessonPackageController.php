<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\AdminBaseController;
use App\Http\Requests\Admin\SetManualUserLessonPackagePaidRequest;
use App\Http\Requests\Admin\StoreLessonPackageRequest;
use App\Http\Requests\Admin\StoreUserLessonPackageRequest;
use App\Http\Requests\Admin\UpdateUserLessonPackageAssignmentRequest;
use App\Models\LessonOccurrenceStatus;
use App\Models\LessonPackage;
use App\Models\Location;
use App\Models\Partner;
use App\Models\PaymentSystem;
use App\Models\Team;
use App\Models\User;
use App\Models\UserLessonPackage;
use App\Models\UserTeamScheduleSlot;
use App\Services\LessonPackages\SchoolCalendarAssignmentEligibilityService;
use App\Services\PartnerContext;
use App\Services\Payments\UserLessonPackagePublicPayService;
use App\Services\SchoolScheduleViewSettingsService;
use App\Services\TeamScheduleCalendarService;
use App\Services\UserLessonPackageAssignmentDeletionService;
use Carbon\CarbonImmutable;
use Database\Seeders\LessonOccurrenceStatusesSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class LessonPackageController extends AdminBaseController
{
    public function __construct(
        PartnerContext $partnerContext,
        private readonly SchoolCalendarAssignmentEligibilityService $schoolCalendarAssignmentEligibility,
    ) {
        parent::__construct($partnerContext);
    }

    public function index()
    {
        $partnerId = $this->requirePartnerId();

        $packages = LessonPackage::query()
            ->where('partner_id', $partnerId)
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
            ->orderByDesc('id')
            ->paginate(20);

        return view('admin.lessonPackages.index', [
            'activeTab' => 'packages',
            'packages' => $packages,
            'weekdays' => self::weekdaysMap(),
        ]);
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
            ->with('locations:id')
            ->orderBy('title')
            ->get(['id', 'title']);

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

    public function schoolScheduleWeek(Request $request, TeamScheduleCalendarService $calendarService): JsonResponse
    {
        $partnerId = $this->requirePartnerId();

        $weekParam = $request->query('week');
        $weekMonday = $weekParam
            ? CarbonImmutable::parse((string) $weekParam)->startOfWeek(Carbon::MONDAY)->startOfDay()
            : CarbonImmutable::now()->startOfWeek(Carbon::MONDAY)->startOfDay();

        $locationRaw = $request->query('location_id');
        $locationId = ($locationRaw !== null && $locationRaw !== '') ? (int) $locationRaw : null;
        if ($locationId === 0) {
            $locationId = null;
        }

        $occurrences = $calendarService->occurrencesForWeek($partnerId, $weekMonday, $locationId);

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
            $name = $ulp->lessonPackage?->name ?? 'Абонемент';

            return [
                'id' => (int) $ulp->id,
                'label' => $name.' №'.(int) $ulp->id.' — осталось '.(int) $ulp->lessons_remaining,
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
            $name = $ulp->lessonPackage?->name ?? 'Абонемент';

            return [
                'id' => (int) $ulp->id,
                'label' => $name.' №'.(int) $ulp->id.' — осталось '.(int) $ulp->lessons_remaining,
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
            $name = $ulp->lessonPackage?->name ?? 'Абонемент';

            return [
                'id' => (int) $ulp->id,
                'label' => $name.' №'.(int) $ulp->id.' — осталось '.(int) $ulp->lessons_remaining,
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

    public function assignments()
    {
        $partnerId = $this->requirePartnerId();

        $packagesList = LessonPackage::query()
            ->where('partner_id', $partnerId)
            ->where('is_active', 1)
            ->orderBy('name')
            ->get(['id', 'name', 'schedule_type', 'duration_days', 'lessons_count', 'price_cents']);

        return view('admin.lessonPackages.index', [
            'activeTab' => 'assignments',
            'packagesList' => $packagesList,
            'weekdays' => self::weekdaysMap(),
        ]);
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
            ->where('users.partner_id', $partnerId);

        $recordsTotal = (clone $baseQuery)->count();

        $filteredQuery = clone $baseQuery;

        $searchTerm = trim((string) $request->input('search.value', ''));
        if ($searchTerm !== '') {
            $like = '%'.addcslashes($searchTerm, '%_\\').'%';
            $filteredQuery->where(function ($q) use ($like) {
                $q->where('users.lastname', 'like', $like)
                    ->orWhere('users.name', 'like', $like)
                    ->orWhere('lesson_packages.name', 'like', $like)
                    ->orWhere('lesson_packages.schedule_type', 'like', $like)
                    ->orWhereRaw('CAST(user_lesson_packages.fee_amount AS CHAR) LIKE ?', [$like])
                    ->orWhereRaw('CAST(user_lesson_packages.lessons_remaining AS CHAR) LIKE ?', [$like])
                    ->orWhereRaw('CAST(user_lesson_packages.lessons_total AS CHAR) LIKE ?', [$like])
                    ->orWhereRaw('CAST(user_lesson_packages.starts_at AS CHAR) LIKE ?', [$like])
                    ->orWhereRaw('CAST(user_lesson_packages.ends_at AS CHAR) LIKE ?', [$like]);
            });
        }

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
                $orderedQuery->orderBy('user_lesson_packages.fee_amount', $orderDir)
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
            ->with(['user:id,name,lastname,partner_id', 'lessonPackage:id,name,schedule_type'])
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
        $partnerRow = Partner::query()->find($partnerId);
        $tbankPs = PaymentSystem::query()
            ->where('partner_id', $partnerId)
            ->where('name', 'tbank')
            ->first();

        return $tbankPs
            && $tbankPs->is_connected
            && $partnerRow
            && trim((string) ($partnerRow->tinkoff_partner_id ?? '')) !== '';
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
            default => 'Абонемент',
        };

        $manualNote = trim((string) ($a->manual_paid_note ?? ''));
        $id = (int) $a->id;
        $payLinkAvailable = $ulpPublicPayTbankReady
            && ! $a->effective_is_paid
            && (float) ($a->fee_amount ?? 0) >= 10.0;

        $feeInt = (int) round((float) ($a->fee_amount ?? 0));
        $feeDisplay = number_format($feeInt, 0, '.', ',').' руб';

        return [
            'id' => $id,
            'student' => $student,
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

        $assignment->loadMissing('user:id,partner_id');
        $link = $service->ensureFreshLink($assignment);

        return response()->json([
            'url' => route('ulp.public.pay', ['token' => $link->token], true),
        ]);
    }

    public function showAssignment(UserLessonPackage $assignment): JsonResponse
    {
        $this->authorize('lessonPackages.view');
        $this->assertAssignmentBelongsToCurrentPartner($assignment);

        return response()->json([
            'assignment' => $this->assignmentModalPayload($assignment),
        ]);
    }

    public function updateAssignment(UpdateUserLessonPackageAssignmentRequest $request, UserLessonPackage $assignment): JsonResponse
    {
        $this->assertAssignmentBelongsToCurrentPartner($assignment);

        $validated = $request->validated();
        $fee = round((float) $validated['fee_amount'], 2);

        DB::transaction(function () use ($request, $assignment, $fee, $validated) {
            $assignment->refresh();

            $canManual = $request->user()->can('lessonPackages.manualPaid.manage');
            $paymentStatus = $validated['payment_status'] ?? null;
            $desiredPaid = null;
            if ($canManual && $paymentStatus !== null && $paymentStatus !== '') {
                $desiredPaid = $paymentStatus === 'paid';
            }

            $oldEffectivePaid = $assignment->effective_is_paid;
            $feeWas = round((float) $assignment->fee_amount, 2);
            $feeChanging = abs($feeWas - $fee) > 0.00001;

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

                    $assignment->fee_amount = $fee;
                    $assignment->save();

                    return;
                }

                if (! $oldEffectivePaid && $desiredPaid) {
                    $assignment->fee_amount = $fee;
                    $assignment->save();
                    $assignment->refresh();

                    $assignment->forceFill([
                        'is_manual_paid' => true,
                        'manual_paid_by' => auth()->id(),
                        'manual_paid_at' => now(),
                        'manual_paid_note' => $comment,
                    ]);
                    $assignment->save();

                    return;
                }
            }

            if ($assignment->effective_is_paid && $feeChanging) {
                abort(422, 'Нельзя менять сумму у оплаченного абонемента.');
            }

            $assignment->fee_amount = $fee;
            $assignment->save();
        });

        return response()->json([
            'success' => true,
            'assignment' => $this->assignmentModalPayload($assignment->fresh([
                'user:id,name,lastname,partner_id',
                'lessonPackage:id,name,schedule_type,duration_days,lessons_count',
                'manualPaidBy:id,name,lastname',
            ])),
        ]);
    }

    public function destroyAssignment(UserLessonPackage $assignment, UserLessonPackageAssignmentDeletionService $deletionService): JsonResponse
    {
        $this->authorize('lessonPackages.view');
        $this->assertAssignmentBelongsToCurrentPartner($assignment);

        try {
            $deletionService->deleteOrAbort($assignment);
        } catch (HttpException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $e->getStatusCode());
        }

        return response()->json(['success' => true]);
    }

    public function setAssignmentManualPaid(SetManualUserLessonPackagePaidRequest $request, UserLessonPackage $assignment): JsonResponse
    {
        $this->assertAssignmentBelongsToCurrentPartner($assignment);

        $mode = (string) $request->validated('mode');
        $comment = trim((string) $request->validated('comment'));
        $authorId = auth()->id();

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
        $assignment->load(['user:id,name,lastname,partner_id', 'lessonPackage:id,name,schedule_type,duration_days,lessons_count', 'manualPaidBy:id,name,lastname']);

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

        return [
            'id' => (int) $ulp->id,
            'user_display' => $userDisplay,
            'lesson_package_name' => (string) ($ulp->lessonPackage->name ?? '—'),
            'period_display' => $periodDisplay,
            'period_start' => $ulp->starts_at?->format('Y-m-d'),
            'period_end' => $ulp->ends_at?->format('Y-m-d'),
            'lessons_remaining' => (int) $ulp->lessons_remaining,
            'lessons_total' => (int) $ulp->lessons_total,
            'schedule_type_label' => $schedLabel,
            'fee_amount' => (string) $ulp->fee_amount,
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
     * Select2: поиск учеников текущего партнёра для назначения абонементов.
     */
    public function assignmentUsersSearch(Request $request)
    {
        $partnerId = $this->requirePartnerId();
        $q = trim((string) $request->query('q', ''));

        $users = User::query()
            ->where('partner_id', $partnerId)
            ->where('is_enabled', 1)
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
            DB::transaction(function () use ($data, $user, $package) {
                /** @var UserLessonPackage $assignment */
                UserLessonPackage::query()->create([
                    'user_id' => (int) $user->id,
                    'lesson_package_id' => (int) $package->id,
                    'starts_at' => null,
                    'ends_at' => null,
                    'lessons_total' => (int) $package->lessons_count,
                    'lessons_remaining' => (int) $package->lessons_count,
                    'fee_amount' => round(((float) $data['fee_amount']), 2),
                    'is_paid' => false,
                    'created_by' => auth()->id(),
                ]);
            });
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
            DB::transaction(function () use ($data, $priceCents, $freezeDays, $partnerId) {
                $freezeEnabled = (bool) ($data['freeze_enabled'] ?? false);
                $freezeDaysStored = $freezeDays;
                if ((string) $data['schedule_type'] === 'no_schedule') {
                    $freezeEnabled = false;
                    $freezeDaysStored = 0;
                }

                /** @var LessonPackage $package */
                $package = LessonPackage::create([
                    'partner_id' => $partnerId,
                    'name' => (string) $data['name'],
                    'schedule_type' => (string) $data['schedule_type'],
                    'duration_days' => (int) $data['duration_days'],
                    'lessons_count' => (int) $data['lessons_count'],
                    'price_cents' => $priceCents,
                    'freeze_enabled' => $freezeEnabled,
                    'freeze_days' => $freezeDaysStored,
                    'is_active' => true,
                ]);
            });
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

        return redirect()
            ->route('admin.lesson-packages.index')
            ->with('success', 'Абонемент успешно создан.');
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
                'is_active' => (bool) $lessonPackage->is_active,
                'time_slots' => [],
            ],
        ]);
    }

    public function update(StoreLessonPackageRequest $request, LessonPackage $lessonPackage)
    {
        $this->assertLessonPackageBelongsToPartner($lessonPackage, $this->requirePartnerId());

        $data = $request->validated();
        $priceCents = (int) $request->input('price_cents', 0);
        $freezeDays = (int) $request->input('freeze_days', 0);

        try {
            DB::transaction(function () use ($lessonPackage, $data, $priceCents, $freezeDays) {
                $freezeEnabled = (bool) ($data['freeze_enabled'] ?? false);
                $freezeDaysStored = $freezeDays;
                if ((string) $data['schedule_type'] === 'no_schedule') {
                    $freezeEnabled = false;
                    $freezeDaysStored = 0;
                }

                $lessonPackage->forceFill([
                    'name' => (string) $data['name'],
                    'schedule_type' => (string) $data['schedule_type'],
                    'duration_days' => (int) $data['duration_days'],
                    'lessons_count' => (int) $data['lessons_count'],
                    'price_cents' => $priceCents,
                    'freeze_enabled' => $freezeEnabled,
                    'freeze_days' => $freezeDaysStored,
                ]);
                $lessonPackage->save();
            });
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

            return response()->json($payload, 422);
        }

        return response()->json(['success' => true]);
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
}
