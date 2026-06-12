<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\AdminBaseController;
use App\Http\Requests\Admin\StoreTrainerRequest;
use App\Http\Requests\Admin\UpdateTrainerRequest;
use App\Models\Role;
use App\Models\TrainerProfile;
use App\Models\User;
use App\Models\Team;
use App\Services\PartnerContext;
use App\Services\TeamTrainerSyncService;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\ImageManager;

class TrainerController extends AdminBaseController
{
    public function __construct(
        PartnerContext $partnerContext,
        private readonly TeamTrainerSyncService $teamTrainerSync,
    ) {
        parent::__construct($partnerContext);
    }

    public function index()
    {
        $partnerId = $this->requirePartnerId();

        $teamOptions = Team::query()
            ->where('partner_id', $partnerId)
            ->orderBy('order_by')
            ->orderBy('title')
            ->get(['id', 'title']);

        return view('admin.trainers.index', compact('teamOptions') + ['activeTab' => 'trainers']);
    }

    public function data(Request $request)
    {
        $partnerId = $this->requirePartnerId();

        $validated = $request->validate([
            'name'    => 'nullable|string',
            'status'  => 'nullable|string',
            'team_id' => 'nullable|integer',

            'draw'   => 'nullable|integer',
            'start'  => 'nullable|integer',
            'length' => 'nullable|integer',
        ]);

        $nameSearch = trim((string) ($validated['name'] ?? ''));
        if ($nameSearch === '' && $request->filled('search.value')) {
            $nameSearch = trim((string) $request->input('search.value'));
        }

        $baseQuery = TrainerProfile::query()
            ->where('trainer_profiles.partner_id', $partnerId);

        if ($nameSearch !== '') {
            $like = '%' . $nameSearch . '%';
            $baseQuery->whereHas('user', function ($q) use ($like) {
                $q->where('name', 'like', $like)
                    ->orWhere('lastname', 'like', $like)
                    ->orWhere('email', 'like', $like)
                    ->orWhere('phone', 'like', $like);
            });
        }

        if (!empty($validated['status'])) {
            if ($validated['status'] === 'active') {
                $baseQuery->where('trainer_profiles.is_enabled', 1);
            } elseif ($validated['status'] === 'inactive') {
                $baseQuery->where('trainer_profiles.is_enabled', 0);
            }
        }

        if (!empty($validated['team_id'])) {
            $teamId = (int) $validated['team_id'];
            $baseQuery->whereHas('teams', function ($q) use ($teamId, $partnerId) {
                $q->where('teams.id', $teamId)
                    ->where('teams.partner_id', $partnerId);
            });
        }

        $totalRecords = TrainerProfile::query()
            ->where('partner_id', $partnerId)
            ->count();

        $recordsFiltered = (clone $baseQuery)->count();

        $orderColumnIndex = $request->input('order.0.column');
        $orderDir         = $request->input('order.0.dir', 'asc') === 'desc' ? 'desc' : 'asc';
        $columnsDef       = $request->input('columns', []);
        $orderColumnName  = null;

        if ($orderColumnIndex !== null && isset($columnsDef[(int) $orderColumnIndex]['name'])) {
            $orderColumnName = $columnsDef[(int) $orderColumnIndex]['name'];
        }

        if ($orderColumnName !== null && $orderColumnName !== '') {
            switch ($orderColumnName) {
                case 'full_name':
                    $baseQuery
                        ->join('users', 'users.id', '=', 'trainer_profiles.user_id')
                        ->select('trainer_profiles.*')
                        ->orderBy('users.lastname', $orderDir)
                        ->orderBy('users.name', $orderDir);
                    break;

                case 'email':
                    $baseQuery
                        ->join('users', 'users.id', '=', 'trainer_profiles.user_id')
                        ->select('trainer_profiles.*')
                        ->orderBy('users.email', $orderDir)
                        ->orderBy('users.lastname', $orderDir)
                        ->orderBy('users.name', $orderDir);
                    break;

                case 'default_base_salary':
                    $baseQuery->orderBy('trainer_profiles.default_base_salary', $orderDir);
                    break;

                case 'default_rate_per_training':
                    $baseQuery->orderBy('trainer_profiles.default_rate_per_training', $orderDir);
                    break;

                case 'sort_order':
                    $baseQuery->orderBy('trainer_profiles.sort_order', $orderDir);
                    break;

                case 'is_enabled':
                    $baseQuery->orderBy('trainer_profiles.is_enabled', $orderDir);
                    break;

                case 'rownum':
                case 'avatar_url':
                case 'teams_label':
                case 'actions':
                default:
                    $baseQuery->orderBy('trainer_profiles.sort_order', 'asc')
                        ->orderBy('trainer_profiles.id', 'asc');
                    break;
            }
        } else {
            $baseQuery->orderBy('trainer_profiles.sort_order', 'asc')
                ->orderBy('trainer_profiles.id', 'asc');
        }

        $baseQuery->orderBy('trainer_profiles.id', 'asc');

        $start  = $validated['start'] ?? 0;
        $length = $validated['length'] ?? 10;

        $trainers = $baseQuery
            ->with(['user', 'teams'])
            ->skip($start)
            ->take($length)
            ->get();

        $data = $trainers->map(function (TrainerProfile $profile) {
            $user = $profile->user;
            $teamTitles = $profile->teams->sortBy('title')->pluck('title')->values()->all();
            $teamsLabels = $this->formatTeamsLabels($teamTitles);

            return [
                'id'                          => $profile->id,
                'avatar_url'                  => $this->avatarUrl($user),
                'full_name'                   => $user?->full_name ?? '',
                'teams_label'                 => $teamsLabels['teams_label'],
                'teams_titles'                => $teamsLabels['teams_titles'],
                'email'                       => $user?->email ?? '',
                'default_base_salary'         => $this->formatSalaryRubles($profile->default_base_salary),
                'default_rate_per_training'   => $this->formatSalaryRubles($profile->default_rate_per_training),
                'sort_order'                  => (int) $profile->sort_order,
                'is_enabled'                  => (int) $profile->is_enabled,
                'status_label'                => $profile->is_enabled ? 'Да' : 'Нет',
            ];
        })->toArray();

        return response()->json([
            'draw'            => (int) ($validated['draw'] ?? 0),
            'recordsTotal'    => $totalRecords,
            'recordsFiltered' => $recordsFiltered,
            'data'            => $data,
        ]);
    }

    public function store(StoreTrainerRequest $request)
    {
        $partnerId = $this->requirePartnerId();
        $trainerRoleId = $this->trainerRoleId();

        $data = $request->validated();
        $profileData = [
            'partner_id' => $partnerId,
            'description' => $data['description'] ?? null,
            'is_enabled' => (bool) ($data['is_enabled'] ?? true),
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'default_base_salary' => $this->normalizeSalaryDefault($data['default_base_salary'] ?? null),
            'default_rate_per_training' => $this->normalizeSalaryDefault($data['default_rate_per_training'] ?? null),
        ];

        $enabled = (bool) ($data['is_enabled'] ?? true);
        $teamIds = $data['team_ids'] ?? [];

        try {
            $profile = DB::transaction(function () use ($request, $data, $partnerId, $trainerRoleId, $profileData, $enabled, $teamIds) {
                $user = User::create([
                    'partner_id' => $partnerId,
                    'role_id' => $trainerRoleId,
                    'name' => $data['name'],
                    'lastname' => $data['lastname'],
                    'email' => $data['email'] ?? null,
                    'phone' => $data['phone'] ?? null,
                    'password' => $data['password'] ?? null,
                    'is_enabled' => $enabled,
                    'team_id' => null,
                ]);

                $profile = TrainerProfile::create(array_merge($profileData, [
                    'user_id' => $user->id,
                ]));

                if ($request->hasFile('avatar')) {
                    $this->syncUserAvatar($user, $request->file('avatar'));
                }

                $this->teamTrainerSync->syncTeamsForTrainer($profile, $teamIds);

                return $profile->load(['user', 'teams']);
            });
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Ошибка сохранения',
                'errors' => [
                    'avatar' => ['Не удалось обработать изображение'],
                ],
            ], 422);
        }

        if ($request->ajax() || $request->expectsJson()) {
            return response()->json([
                'message' => 'Тренер создан',
                'trainer' => $this->trainerPayload($profile),
            ]);
        }

        return redirect()->route('admin.trainers.index');
    }

    public function show(TrainerProfile $trainerProfile)
    {
        $partnerId = $this->requirePartnerId();

        if ((int) $trainerProfile->partner_id !== $partnerId) {
            abort(404);
        }

        $trainerProfile->load(['user', 'teams']);

        return response()->json($this->trainerPayload($trainerProfile));
    }

    public function update(UpdateTrainerRequest $request, TrainerProfile $trainerProfile)
    {
        $partnerId = $this->requirePartnerId();

        if ((int) $trainerProfile->partner_id !== $partnerId) {
            abort(404);
        }

        $trainerProfile->load('user.role');
        $user = $trainerProfile->user;

        if (!$user) {
            return response()->json([
                'message' => 'Учётная запись тренера не найдена',
            ], 404);
        }

        $data = $request->validated();

        $enabled = (bool) ($data['is_enabled'] ?? false);

        $userData = [
            'name' => $data['name'],
            'lastname' => $data['lastname'],
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'is_enabled' => $enabled,
        ];

        if (!empty($data['password'])) {
            $userData['password'] = $data['password'];
        }

        $profileData = [
            'description' => $data['description'] ?? null,
            'is_enabled' => $enabled,
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'default_base_salary' => $this->normalizeSalaryDefault($data['default_base_salary'] ?? null),
            'default_rate_per_training' => $this->normalizeSalaryDefault($data['default_rate_per_training'] ?? null),
        ];

        $teamIds = $data['team_ids'] ?? [];
        $trainerRoleId = $this->trainerRoleId();

        try {
            DB::transaction(function () use ($request, $user, $trainerProfile, $userData, $profileData, $trainerRoleId, $teamIds) {
                if ((int) $user->role_id !== $trainerRoleId) {
                    $userData['role_id'] = $trainerRoleId;
                }

                $user->update($userData);
                $trainerProfile->update($profileData);

                if ($request->boolean('remove_avatar')) {
                    $this->deleteUserAvatarFiles($user);
                    $user->update(['image' => null, 'image_crop' => null]);
                }

                if ($request->hasFile('avatar')) {
                    $this->syncUserAvatar($user->fresh(), $request->file('avatar'));
                }

                $this->teamTrainerSync->syncTeamsForTrainer($trainerProfile, $teamIds);
            });
        } catch (\Throwable $e) {
            report($e);

            $message = 'Ошибка сохранения';
            $errors = [];

            if ($request->hasFile('avatar')) {
                $errors['avatar'] = ['Не удалось обработать изображение'];
            } else {
                $message = 'Не удалось сохранить данные тренера';
            }

            return response()->json([
                'message' => $message,
                'errors' => $errors,
            ], 422);
        }

        return response()->json(['message' => 'Тренер обновлён']);
    }

    public function destroy(TrainerProfile $trainerProfile)
    {
        $partnerId = $this->requirePartnerId();

        if ((int) $trainerProfile->partner_id !== $partnerId) {
            abort(404);
        }

        $trainerProfile->load('user');

        DB::transaction(function () use ($trainerProfile) {
            if ($trainerProfile->user) {
                $this->deleteUserAvatarFiles($trainerProfile->user);
                $trainerProfile->user->delete();
            }
            $trainerProfile->delete();
        });

        if (request()->ajax() || request()->expectsJson()) {
            return response()->json(['message' => 'Тренер удалён']);
        }

        return redirect()->route('admin.trainers.index');
    }

    private function trainerRoleId(): int
    {
        $roleId = Role::query()->where('name', 'trainer')->value('id');

        if (!$roleId) {
            abort(500, 'Системная роль «Тренер» не найдена. Выполните сидер ролей.');
        }

        return (int) $roleId;
    }

    private function trainerPayload(TrainerProfile $profile): array
    {
        $user = $profile->user;
        $profile->loadMissing('teams');
        $teamIds = $profile->teams->pluck('id')->map(fn ($id) => (int) $id)->values()->all();

        return [
            'id' => $profile->id,
            'user_id' => $user?->id,
            'lastname' => $user?->lastname,
            'name' => $user?->name,
            'full_name' => $user?->full_name ?? '',
            'email' => $user?->email,
            'phone' => $user?->phone,
            'description' => $profile->description,
            'is_enabled' => (int) $profile->is_enabled,
            'sort_order' => (int) $profile->sort_order,
            'default_base_salary' => $this->salaryRublesForForm($profile->default_base_salary),
            'default_rate_per_training' => $this->salaryRublesForForm($profile->default_rate_per_training),
            'avatar_url' => $this->avatarUrl($user),
            'image' => $user?->image,
            'image_crop' => $user?->image_crop,
            'team_ids' => $teamIds,
            'teams_label' => $profile->teams->pluck('title')->implode(', '),
        ];
    }

    private function avatarUrl(?User $user): string
    {
        if ($user?->image_crop) {
            return asset('storage/avatars/' . $user->image_crop);
        }

        return asset('img/default-avatar.png');
    }

    private function syncUserAvatar(User $user, UploadedFile $file): void
    {
        $manager = ImageManager::gd();
        $bigImage = $manager->read($file->getRealPath())->scaleDown(1600, 1600);
        $cropImage = $manager->read($file->getRealPath())->coverDown(300, 300);

        $bigName = Str::uuid()->toString() . '.jpg';
        $cropName = Str::uuid()->toString() . '.jpg';

        $this->deleteUserAvatarFiles($user);

        Storage::disk('public')->put('avatars/' . $bigName, (string) $bigImage->toJpeg(85));
        Storage::disk('public')->put('avatars/' . $cropName, (string) $cropImage->toJpeg(90));

        $user->update([
            'image' => $bigName,
            'image_crop' => $cropName,
        ]);
    }

    /**
     * @param  list<string>  $titles
     * @return array{teams_label: string, teams_label_full: string, teams_titles: list<string>}
     */
    private function formatTeamsLabels(array $titles): array
    {
        $titles = array_values(array_filter($titles, static fn ($title) => trim((string) $title) !== ''));
        $count = count($titles);

        if ($count === 0) {
            return [
                'teams_label' => '',
                'teams_label_full' => '',
                'teams_titles' => [],
            ];
        }

        $full = implode(', ', $titles);

        if ($count <= 2) {
            return [
                'teams_label' => $full,
                'teams_label_full' => $full,
                'teams_titles' => $titles,
            ];
        }

        return [
            'teams_label' => $titles[0] . ', еще ' . ($count - 1) . ' шт.',
            'teams_label_full' => $full,
            'teams_titles' => $titles,
        ];
    }

    private function formatSalaryRubles(mixed $value): string
    {
        return number_format((int) round((float) ($value ?? 0)), 0, '.', ' ') . ' руб';
    }

    private function salaryRublesForForm(mixed $value): string
    {
        return (string) (int) round((float) ($value ?? 0));
    }

    private function normalizeSalaryDefault(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '0.00';
        }

        return number_format(round((float) $value), 2, '.', '');
    }

    private function deleteUserAvatarFiles(User $user): void
    {
        if ($user->image) {
            Storage::disk('public')->delete('avatars/' . $user->image);
        }
        if ($user->image_crop) {
            Storage::disk('public')->delete('avatars/' . $user->image_crop);
        }
    }
}
