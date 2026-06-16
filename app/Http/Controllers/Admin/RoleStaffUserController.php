<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\AdminBaseController;
use App\Http\Controllers\Admin\Concerns\RendersUsersSectionTabs;
use App\Http\Requests\Admin\StoreRoleStaffUserRequest;
use App\Http\Requests\Admin\UpdateRoleStaffUserRequest;
use App\Models\Role;
use App\Models\User;
use App\Services\PartnerContext;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\ImageManager;

class RoleStaffUserController extends AdminBaseController
{
    use RendersUsersSectionTabs;

    /** @var list<string> */
    private const RESERVED_CUSTOM_ROLE_NAMES = [
        'superadmin',
        'user',
        'admin',
        'trainer',
    ];

    public function __construct(PartnerContext $partnerContext)
    {
        parent::__construct($partnerContext);
    }

    public function administratorsIndex()
    {
        return $this->renderIndex($this->resolveRoleByName('admin'), 'administrators');
    }

    public function customRoleIndex(string $role)
    {
        return $this->renderIndex($this->resolveCustomPartnerRole($role), 'role-' . $role);
    }

    public function administratorsData(Request $request)
    {
        return $this->data($request, $this->resolveRoleByName('admin'));
    }

    public function customRoleData(Request $request, string $role)
    {
        return $this->data($request, $this->resolveCustomPartnerRole($role));
    }

    public function administratorsShow(User $user)
    {
        return $this->showUser($user, $this->resolveRoleByName('admin'));
    }

    public function customRoleShow(string $role, User $user)
    {
        return $this->showUser($user, $this->resolveCustomPartnerRole($role));
    }

    private function showUser(User $user, Role $expectedRole)
    {
        $this->assertStaffUser($user, $expectedRole);

        return response()->json($this->userPayload($user->fresh()));
    }

    public function administratorsStore(StoreRoleStaffUserRequest $request)
    {
        return $this->store($request, $this->resolveRoleByName('admin'));
    }

    public function customRoleStore(StoreRoleStaffUserRequest $request, string $role)
    {
        return $this->store($request, $this->resolveCustomPartnerRole($role));
    }

    public function administratorsUpdate(UpdateRoleStaffUserRequest $request, User $user)
    {
        return $this->update($request, $user, $this->resolveRoleByName('admin'));
    }

    public function customRoleUpdate(UpdateRoleStaffUserRequest $request, string $role, User $user)
    {
        return $this->update($request, $user, $this->resolveCustomPartnerRole($role));
    }

    public function administratorsDestroy(User $user)
    {
        return $this->destroyUser($user, $this->resolveRoleByName('admin'));
    }

    public function customRoleDestroy(string $role, User $user)
    {
        return $this->destroyUser($user, $this->resolveCustomPartnerRole($role));
    }

    private function destroyUser(User $user, Role $expectedRole)
    {
        $this->authorizeRoleTabAccess();
        $this->assertStaffUser($user, $expectedRole);

        DB::transaction(function () use ($user) {
            $this->deleteUserAvatarFiles($user);
            $user->delete();
        });

        if (request()->ajax() || request()->expectsJson()) {
            return response()->json(['message' => 'Пользователь удалён']);
        }

        return redirect()->back();
    }

    private function renderIndex(Role $role, string $activeTab)
    {
        $this->authorizeRoleTabAccess();

        return view('admin.role_staff.index', $this->roleStaffViewData($role, $activeTab));
    }

    /**
     * @return array<string, mixed>
     */
    private function roleStaffViewData(Role $role, string $activeTab): array
    {
        return array_merge($this->usersSectionViewData($activeTab), [
            'role'                    => $role,
            'pageTitle'               => (string) $role->label,
            'tableKey'                => $this->tableKeyForRole($role),
            'canChangePassword'       => auth()->user()?->can('users.password.update') ?? false,
            'roleStaffHasActiveFilters' => false,
            'roleStaffEndpoints'      => $this->endpointsForRole($role),
        ]);
    }

    private function data(Request $request, Role $role)
    {
        $this->authorizeRoleTabAccess();
        $partnerId = $this->requirePartnerId();

        $validated = $request->validate([
            'name'   => 'nullable|string',
            'status' => 'nullable|string',

            'draw'   => 'nullable|integer',
            'start'  => 'nullable|integer',
            'length' => 'nullable|integer',
        ]);

        $nameSearch = trim((string) ($validated['name'] ?? ''));
        if ($nameSearch === '' && $request->filled('search.value')) {
            $nameSearch = trim((string) $request->input('search.value'));
        }

        $baseQuery = $this->scopeByPartner(User::query(), 'users.partner_id')
            ->where('users.role_id', $role->id);

        if ($nameSearch !== '') {
            $like = '%' . $nameSearch . '%';
            $baseQuery->where(function ($q) use ($like) {
                $q->where('users.name', 'like', $like)
                    ->orWhere('users.lastname', 'like', $like)
                    ->orWhere('users.email', 'like', $like)
                    ->orWhere('users.phone', 'like', $like);
            });
        }

        if (!empty($validated['status'])) {
            if ($validated['status'] === 'active') {
                $baseQuery->where('users.is_enabled', 1);
            } elseif ($validated['status'] === 'inactive') {
                $baseQuery->where('users.is_enabled', 0);
            }
        }

        $totalRecords = $this->scopeByPartner(User::query(), 'users.partner_id')
            ->where('users.role_id', $role->id)
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
                    $baseQuery->orderBy('users.lastname', $orderDir)->orderBy('users.name', $orderDir);
                    break;
                case 'email':
                    $baseQuery->orderBy('users.email', $orderDir);
                    break;
                case 'phone':
                    $baseQuery->orderBy('users.phone', $orderDir);
                    break;
                case 'is_enabled':
                    $baseQuery->orderBy('users.is_enabled', $orderDir);
                    break;
                default:
                    $baseQuery->orderBy('users.lastname', 'asc')->orderBy('users.name', 'asc');
                    break;
            }
        } else {
            $baseQuery->orderBy('users.lastname', 'asc')->orderBy('users.name', 'asc');
        }

        $start  = $validated['start'] ?? 0;
        $length = $validated['length'] ?? 10;

        $users = $baseQuery
            ->skip($start)
            ->take($length)
            ->get();

        $data = $users->map(function (User $user) {
            return [
                'id'           => $user->id,
                'avatar'       => $this->avatarUrl($user),
                'full_name'    => $user->full_name ?: 'Без имени',
                'email'        => $user->email ?? '',
                'phone'        => $user->phone ?? '',
                'status_label' => $user->is_enabled ? 'Активен' : 'Неактивен',
                'is_enabled'   => (int) $user->is_enabled,
            ];
        });

        return response()->json([
            'draw'            => (int) ($validated['draw'] ?? 0),
            'recordsTotal'    => $totalRecords,
            'recordsFiltered' => $recordsFiltered,
            'data'            => $data,
        ]);
    }

    private function store(StoreRoleStaffUserRequest $request, Role $role)
    {
        $this->authorizeRoleTabAccess();
        $partnerId = $this->requirePartnerId();
        $data = $request->validated();
        $enabled = (bool) ($data['is_enabled'] ?? true);

        try {
            $user = DB::transaction(function () use ($request, $data, $partnerId, $role, $enabled) {
                $user = User::create([
                    'partner_id' => $partnerId,
                    'role_id'    => $role->id,
                    'name'       => $data['name'],
                    'lastname'   => $data['lastname'],
                    'email'      => $data['email'] ?? null,
                    'phone'      => $data['phone'] ?? null,
                    'password'   => $data['password'] ?? null,
                    'is_enabled' => $enabled,
                    'team_id'    => null,
                ]);

                if ($request->hasFile('avatar')) {
                    $this->syncUserAvatar($user, $request->file('avatar'));
                }

                return $user->fresh();
            });
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'message' => 'Ошибка сохранения',
                'errors'  => [
                    'avatar' => ['Не удалось обработать изображение'],
                ],
            ], 422);
        }

        if ($request->ajax() || $request->expectsJson()) {
            return response()->json([
                'message' => 'Пользователь создан',
                'user'    => $this->userPayload($user),
            ]);
        }

        return redirect()->back();
    }

    private function update(UpdateRoleStaffUserRequest $request, User $user, Role $role)
    {
        $this->authorizeRoleTabAccess();
        $this->assertStaffUser($user, $role);

        $data = $request->validated();
        $enabled = (bool) ($data['is_enabled'] ?? false);

        $userData = [
            'name'       => $data['name'],
            'lastname'   => $data['lastname'],
            'email'      => $data['email'] ?? null,
            'phone'      => $data['phone'] ?? null,
            'is_enabled' => $enabled,
            'role_id'    => $role->id,
            'team_id'    => null,
        ];

        if (!empty($data['password'])) {
            $userData['password'] = $data['password'];
        }

        try {
            DB::transaction(function () use ($request, $user, $userData) {
                $user->update($userData);

                if ($request->boolean('remove_avatar')) {
                    $this->deleteUserAvatarFiles($user);
                    $user->update(['image' => null, 'image_crop' => null]);
                }

                if ($request->hasFile('avatar')) {
                    $this->syncUserAvatar($user->fresh(), $request->file('avatar'));
                }
            });
        } catch (\Throwable $e) {
            report($e);

            $errors = [];
            if ($request->hasFile('avatar')) {
                $errors['avatar'] = ['Не удалось обработать изображение'];
            }

            return response()->json([
                'message' => $errors ? 'Ошибка сохранения' : 'Не удалось сохранить данные пользователя',
                'errors'  => $errors,
            ], 422);
        }

        return response()->json(['message' => 'Пользователь обновлён']);
    }

    private function authorizeRoleTabAccess(): void
    {
        abort_unless($this->currentUser()?->can('users.role.update'), 403);
    }

    private function resolveRoleByName(string $name): Role
    {
        $partnerId = $this->requirePartnerId();
        $isSuperadmin = $this->isSuperAdmin();

        $role = Role::query()
            ->exceptSuperadmin()
            ->where('name', $name)
            ->when(!$isSuperadmin, fn ($q) => $q->where('is_visible', 1))
            ->where(function ($q) use ($partnerId) {
                $q->where('is_sistem', 1)
                    ->orWhereHas('partners', fn ($q2) => $q2->where('partner_role.partner_id', $partnerId));
            })
            ->first();

        if (!$role) {
            abort(404);
        }

        return $role;
    }

    private function resolveCustomPartnerRole(string $name): Role
    {
        if (in_array($name, self::RESERVED_CUSTOM_ROLE_NAMES, true)) {
            abort(404);
        }

        $partnerId = $this->requirePartnerId();
        $isSuperadmin = $this->isSuperAdmin();

        $role = Role::query()
            ->where('name', $name)
            ->where('is_sistem', 0)
            ->when(!$isSuperadmin, fn ($q) => $q->where('is_visible', 1))
            ->whereHas('partners', fn ($q) => $q->where('partner_role.partner_id', $partnerId))
            ->first();

        if (!$role) {
            abort(404);
        }

        return $role;
    }

    private function assertStaffUser(User $user, Role $expectedRole): void
    {
        $user = $this->scopeByPartner(User::query(), 'users.partner_id')
            ->whereKey($user->id)
            ->firstOrFail();

        if ((int) $user->role_id !== (int) $expectedRole->id) {
            abort(404);
        }
    }

    /**
     * @return array<string, string>
     */
    private function endpointsForRole(Role $role): array
    {
        if ($role->name === 'admin') {
            return [
                'data'             => route('admin.administrators.data'),
                'store'            => route('admin.administrators.store'),
                'show'             => route('admin.administrators.show', ['user' => '__ID__']),
                'update'           => route('admin.administrators.update', ['user' => '__ID__']),
                'destroy'          => route('admin.administrators.destroy', ['user' => '__ID__']),
                'columns_settings' => route('admin.administrators.columns-settings.get'),
                'password_update'  => route('admin.user.password.update', ['user' => '__ID__']),
            ];
        }

        return [
            'data'             => route('admin.roles.users.data', ['role' => $role->name]),
            'store'            => route('admin.roles.users.store', ['role' => $role->name]),
            'show'             => route('admin.roles.users.show', ['role' => $role->name, 'user' => '__ID__']),
            'update'           => route('admin.roles.users.update', ['role' => $role->name, 'user' => '__ID__']),
            'destroy'          => route('admin.roles.users.destroy', ['role' => $role->name, 'user' => '__ID__']),
            'columns_settings' => route('admin.roles.users.columns-settings.get', ['role' => $role->name]),
            'password_update'  => route('admin.user.password.update', ['user' => '__ID__']),
        ];
    }

    private function tableKeyForRole(Role $role): string
    {
        return 'role_staff_' . $role->name;
    }

    /**
     * @return array<string, mixed>
     */
    private function userPayload(User $user): array
    {
        return [
            'id'         => $user->id,
            'lastname'   => $user->lastname,
            'name'       => $user->name,
            'full_name'  => $user->full_name ?? '',
            'email'      => $user->email,
            'phone'      => $user->phone,
            'is_enabled' => (int) $user->is_enabled,
            'avatar_url' => $this->avatarUrl($user),
            'image'      => $user->image,
            'image_crop' => $user->image_crop,
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
            'image'      => $bigName,
            'image_crop' => $cropName,
        ]);
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
