<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Users;

use App\Models\User;
use Tests\Feature\Crm\CrmTestCase;
use Tests\Feature\Crm\Ui\Concerns\SuccessToastInsteadOfModalTestHelpers;
use Tests\Feature\Crm\Users\Concerns\GrantsUsersSectionPermissions;

/**
 * Общий сетап для create/edit ученика без success-модалки и без reload страницы.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
abstract class AdminUsersCreateEditToastTestCase extends CrmTestCase
{
    use GrantsUsersSectionPermissions;
    use SuccessToastInsteadOfModalTestHelpers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed'      => true,
        ]);
    }

    /** @param array<string, mixed> $overrides */
    protected function studentPayload(array $overrides = []): array
    {
        return array_merge([
            'name'       => 'Иван',
            'lastname'   => 'Тостов',
            'role_id'    => $this->studentRoleId(),
            'is_enabled' => 1,
        ], $overrides);
    }

    /** @param array<string, mixed> $attributes */
    protected function makeStudent(array $attributes = []): User
    {
        return User::factory()->create(array_merge([
            'partner_id' => $this->partner->id,
            'role_id'    => $this->studentRoleId(),
            'name'       => 'Ученик',
            'lastname'   => 'Тостов',
        ], $attributes));
    }

    protected function actingAsUsersViewer(): void
    {
        $this->asAdmin();
        $this->grantUsersView($this->user);
    }
}
