<?php
// app/Http/Controllers/PartnerRolePermissionController.php

namespace App\Http\Controllers;

use App\Models\Partner;
use App\Models\Role;
use App\Models\Permission;
use Illuminate\Http\Request;

class PartnerRolePermissionController extends Controller
{
    public function edit(Partner $partner, Role $role)
    {
        // все системные права
        $permissions = Permission::all();

        // уже назначенные
        $assigned = $role
            ->permissionsForPartner($partner->id)
            ->pluck('permissions.id')
            ->toArray();

        return view('partners.roles.permissions.edit', compact('partner', 'role', 'permissions', 'assigned'));
    }

    public function update(Request $request, Partner $partner, Role $role)
    {
        $list = $request->input('permissions', []);

        // снижаем все старые
        $role->revokeAllPermissionsForPartner($partner->id);

        // даём новые
        foreach ($list as $permId) {
            $role->givePermissionToForPartner($permId, $partner->id);
        }

        return redirect()
            ->route('partners.roles.permissions.edit', [$partner, $role])
            ->with('success', 'Права успешно обновлены');
    }
}
