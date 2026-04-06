<?php

namespace App\Services;

use App\Models\Partner;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class PartnerSelfRegistrationService
{
    /**
     * @param  array{school_title: string, name: string, email: string, password: string}  $data
     * @return array{partner: Partner, user: User}
     */
    public function register(array $data): array
    {
        $adminRoleId = Role::query()->where('name', 'admin')->value('id');
        if (!$adminRoleId) {
            throw new RuntimeException('Role "admin" is missing.');
        }

        $email = $data['email'];
        $schoolTitle = $data['school_title'];

        $partner = null;
        $user = null;

        DB::transaction(function () use ($data, $adminRoleId, $email, $schoolTitle, &$partner, &$user) {
            $partner = Partner::create([
                'business_type'            => 'individual_entrepreneur',
                'title'                    => $schoolTitle,
                'organization_name'        => $schoolTitle,
                'email'                    => $email,
                'is_enabled'               => true,
                'order_by'                 => 0,
                'registration_verified_at' => null,
                'ceo'                      => [
                    'lastName'   => '',
                    'firstName'  => '',
                    'middleName' => '',
                    'phone'      => '',
                ],
            ]);

            $user = User::create([
                'name'       => $data['name'],
                'email'      => $email,
                'password'   => Hash::make($data['password']),
                'partner_id' => $partner->id,
                'role_id'    => (int) $adminRoleId,
                'is_enabled' => true,
            ]);
        });

        return ['partner' => $partner, 'user' => $user];
    }
}
