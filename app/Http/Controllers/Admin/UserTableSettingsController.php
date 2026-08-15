<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UserTableSettingsSaveRequest;
use App\Models\UserTableSetting;
use Illuminate\Support\Facades\Auth;

class UserTableSettingsController extends Controller
{
    /** @var list<string> */
    private const PERMISSION_SCOPED_COLUMNS = [
        'sex' => 'users.sex',
        'comment' => 'users.comment',
    ];

    /**
     * Вернуть настройки колонок для текущего пользователя
     * для таблицы "users_index".
     *
     * GET /admin/users/table-settings
     */
    public function getColumnsSettings()
    {
        $userId = Auth::id();
        $actor = Auth::user();

        $settings = UserTableSetting::where('user_id', $userId)
            ->where('table_key', 'users_index')
            ->first();

        // 👉 ВАЖНО: возвращаем ЧИСТЫЙ массив columns или пустой объект
        $columns = $settings?->columns;

        if (!is_array($columns)) {
            $columns = [];
        }

        return response()->json($this->filterColumnsByActorPermissions($columns, $actor));
    }

    /**
     * Сохранить настройки колонок и/или «Показать N» для текущего пользователя.
     *
     * POST /admin/users/columns-settings
     */
    public function saveColumnsSettings(UserTableSettingsSaveRequest $request)
    {
        $userId = Auth::id();
        $actor = Auth::user();
        $data = $request->validated();
        $payload = [];

        if (array_key_exists('columns', $data) && is_array($data['columns'])) {
            $payload['columns'] = $this->filterColumnsByActorPermissions($data['columns'], $actor);
        }

        if (array_key_exists('page_length', $data) && $data['page_length'] !== null) {
            $payload['page_length'] = (int) $data['page_length'];
        }

        if ($payload === []) {
            return response()->json([
                'success' => true,
            ]);
        }

        UserTableSetting::updateOrCreate(
            [
                'user_id'   => $userId,
                'table_key' => 'users_index',
            ],
            $payload
        );

        return response()->json([
            'success' => true,
        ]);
    }

    /**
     * @param  array<string, mixed>  $columns
     * @return array<string, mixed>
     */
    private function filterColumnsByActorPermissions(array $columns, $actor): array
    {
        foreach (self::PERMISSION_SCOPED_COLUMNS as $columnKey => $permissionName) {
            if (!$actor || !$actor->can($permissionName)) {
                unset($columns[$columnKey]);
            }
        }

        return $columns;
    }
}
