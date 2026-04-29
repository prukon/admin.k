<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\AdminBaseController;
use App\Http\Requests\Admin\StoreLessonPackageRequest;
use App\Http\Requests\Admin\StoreUserLessonPackageRequest;
use App\Models\LessonPackage;
use App\Models\LessonPackageTimeSlot;
use App\Models\User;
use App\Models\UserLessonPackage;
use App\Models\UserLessonPackageTimeSlot;
use App\Services\PartnerContext;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class LessonPackageController extends AdminBaseController
{
    public function __construct(PartnerContext $partnerContext)
    {
        parent::__construct($partnerContext);
    }

    public function index()
    {
        $packages = LessonPackage::query()
            ->with(['timeSlots' => function ($q) {
                $q->orderBy('weekday')->orderBy('time_start');
            }])
            ->orderByDesc('id')
            ->paginate(20);

        return view('admin.lessonPackages.index', [
            'activeTab' => 'packages',
            'packages' => $packages,
            'weekdays' => self::weekdaysMap(),
        ]);
    }

    public function assignments()
    {
        $partnerId = $this->requirePartnerId();

        $assignments = UserLessonPackage::query()
            ->with(['user:id,name,lastname,partner_id', 'lessonPackage:id,name,schedule_type,duration_days,lessons_count'])
            ->whereHas('user', function ($q) use ($partnerId) {
                $q->where('partner_id', $partnerId);
            })
            ->orderByDesc('id')
            ->paginate(20);

        $packagesList = LessonPackage::query()
            ->where('is_active', 1)
            ->orderBy('name')
            ->get(['id', 'name', 'schedule_type', 'duration_days', 'lessons_count']);

        return view('admin.lessonPackages.index', [
            'activeTab' => 'assignments',
            'assignments' => $assignments,
            'packagesList' => $packagesList,
            'weekdays' => self::weekdaysMap(),
        ]);
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
                $query->whereRaw("CONCAT_WS(' ', lastname, name) LIKE ?", [$like]);
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
        $package = LessonPackage::query()->findOrFail((int) $data['lesson_package_id']);

        $startsAt = Carbon::createFromFormat('Y-m-d', (string) $data['starts_at'])->startOfDay();
        $endsAt = (clone $startsAt)->addDays((int) $package->duration_days)->toDateString();

        $startsAtStr = $startsAt->toDateString();

        try {
            DB::transaction(function () use ($data, $user, $package, $startsAtStr, $endsAt) {
                /** @var UserLessonPackage $assignment */
                $assignment = UserLessonPackage::query()->create([
                    'user_id' => (int) $user->id,
                    'lesson_package_id' => (int) $package->id,
                    'starts_at' => $startsAtStr,
                    'ends_at' => $endsAt,
                    'lessons_total' => (int) $package->lessons_count,
                    'lessons_remaining' => (int) $package->lessons_count,
                    'created_by' => auth()->id(),
                ]);

                // Слоты назначения создаём только для flexible, и только если они переданы.
                if ((string) $package->schedule_type === 'flexible') {
                    $slots = is_array($data['time_slots'] ?? null) ? $data['time_slots'] : [];

                    foreach ($slots as $slot) {
                        $weekday = (int) ($slot['weekday'] ?? 0);
                        $start = (string) ($slot['time_start'] ?? '');
                        $end = (string) ($slot['time_end'] ?? '');

                        // допускаем пустые слоты (назначение без слотов)
                        if ($weekday <= 0 || $start === '' || $end === '') {
                            continue;
                        }

                        UserLessonPackageTimeSlot::query()->create([
                            'user_lesson_package_id' => (int) $assignment->id,
                            'weekday' => $weekday,
                            'time_start' => $start,
                            'time_end' => $end,
                        ]);
                    }
                }
            });
        } catch (QueryException $e) {
            Log::warning('UserLessonPackage storeAssignment failed', [
                'error' => $e->getMessage(),
            ]);

            return back()->withErrors([
                'time_slots' => 'Не удалось назначить абонемент. Проверьте корректность данных и отсутствие дублей в слотах.',
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

        try {
            DB::transaction(function () use ($data, $priceCents, $freezeDays) {
                /** @var LessonPackage $package */
                $package = LessonPackage::create([
                    'name' => (string) $data['name'],
                    'schedule_type' => (string) $data['schedule_type'],
                    'duration_days' => (int) $data['duration_days'],
                    'lessons_count' => (int) $data['lessons_count'],
                    'price_cents' => $priceCents,
                    'freeze_enabled' => (bool) ($data['freeze_enabled'] ?? false),
                    'freeze_days' => $freezeDays,
                    'is_active' => true,
                ]);

                if ((string) $data['schedule_type'] === 'fixed') {
                    $slots = is_array($data['time_slots'] ?? null) ? $data['time_slots'] : [];

                    foreach ($slots as $slot) {
                        LessonPackageTimeSlot::create([
                            'lesson_package_id' => $package->id,
                            'weekday' => (int) ($slot['weekday'] ?? 0),
                            'time_start' => (string) ($slot['time_start'] ?? ''),
                            'time_end' => (string) ($slot['time_end'] ?? ''),
                        ]);
                    }
                }
            });
        } catch (QueryException $e) {
            Log::warning('LessonPackage store failed', [
                'error' => $e->getMessage(),
            ]);

            $payload = [
                'message' => 'Не удалось сохранить абонемент. Проверьте, что слоты расписания не дублируются и заполнены корректно.',
                'errors' => [
                    'time_slots' => [
                        'Не удалось сохранить абонемент. Проверьте, что слоты расписания не дублируются и заполнены корректно.',
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
        $lessonPackage->load(['timeSlots' => function ($q) {
            $q->orderBy('weekday')->orderBy('time_start');
        }]);

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
                'time_slots' => $lessonPackage->timeSlots->map(function (LessonPackageTimeSlot $slot) {
                    return [
                        'weekday' => (int) $slot->weekday,
                        'time_start' => substr((string) $slot->time_start, 0, 5),
                        'time_end' => substr((string) $slot->time_end, 0, 5),
                    ];
                })->values(),
            ],
        ]);
    }

    public function update(StoreLessonPackageRequest $request, LessonPackage $lessonPackage)
    {
        $data = $request->validated();
        $priceCents = (int) $request->input('price_cents', 0);
        $freezeDays = (int) $request->input('freeze_days', 0);

        try {
            DB::transaction(function () use ($lessonPackage, $data, $priceCents, $freezeDays) {
                $lessonPackage->forceFill([
                    'name' => (string) $data['name'],
                    'schedule_type' => (string) $data['schedule_type'],
                    'duration_days' => (int) $data['duration_days'],
                    'lessons_count' => (int) $data['lessons_count'],
                    'price_cents' => $priceCents,
                    'freeze_enabled' => (bool) ($data['freeze_enabled'] ?? false),
                    'freeze_days' => $freezeDays,
                ]);
                $lessonPackage->save();

                // пересобираем слоты (простая и надёжная стратегия)
                LessonPackageTimeSlot::query()
                    ->where('lesson_package_id', $lessonPackage->id)
                    ->delete();

                if ((string) $data['schedule_type'] === 'fixed') {
                    $slots = is_array($data['time_slots'] ?? null) ? $data['time_slots'] : [];

                    foreach ($slots as $slot) {
                        LessonPackageTimeSlot::create([
                            'lesson_package_id' => $lessonPackage->id,
                            'weekday' => (int) ($slot['weekday'] ?? 0),
                            'time_start' => (string) ($slot['time_start'] ?? ''),
                            'time_end' => (string) ($slot['time_end'] ?? ''),
                        ]);
                    }
                }
            });
        } catch (QueryException $e) {
            Log::warning('LessonPackage update failed', [
                'lesson_package_id' => (int) $lessonPackage->id,
                'error' => $e->getMessage(),
            ]);

            $payload = [
                'message' => 'Не удалось сохранить изменения. Проверьте корректность данных и отсутствие дублей в слотах.',
                'errors' => [
                    'time_slots' => [
                        'Не удалось сохранить изменения. Проверьте корректность данных и отсутствие дублей в слотах.',
                    ],
                ],
            ];

            return response()->json($payload, 422);
        }

        return response()->json(['success' => true]);
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

