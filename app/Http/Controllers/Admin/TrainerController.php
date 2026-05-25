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

        $trainers = TrainerProfile::query()
            ->with(['user', 'teams'])
            ->where('partner_id', $partnerId)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->paginate(50);

        $teamOptions = Team::query()
            ->where('partner_id', $partnerId)
            ->orderBy('order_by')
            ->orderBy('title')
            ->get(['id', 'title']);

        return view('admin.trainers.index', compact('trainers', 'teamOptions') + ['activeTab' => 'trainers']);
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
            'default_base_salary' => number_format((float) ($profile->default_base_salary ?? 0), 2, '.', ''),
            'default_rate_per_training' => number_format((float) ($profile->default_rate_per_training ?? 0), 2, '.', ''),
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

    private function normalizeSalaryDefault(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '0.00';
        }

        return number_format((float) $value, 2, '.', '');
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
