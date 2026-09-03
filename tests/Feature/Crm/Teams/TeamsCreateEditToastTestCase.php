<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Teams;

use App\Models\Team;
use Tests\Feature\Crm\CrmTestCase;
use Tests\Feature\Crm\Ui\Concerns\SuccessToastInsteadOfModalTestHelpers;

/**
 * Общий сетап для create/edit/delete группы без success-модалки и без reload страницы.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
abstract class TeamsCreateEditToastTestCase extends CrmTestCase
{
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
    protected function teamPayload(array $overrides = []): array
    {
        return array_merge([
            'title'                    => 'Группа тост '.uniqid('', true),
            'default_duration_minutes' => 60,
            'order_by'                 => 10,
            'is_enabled'               => 1,
        ], $overrides);
    }

    /** @param array<string, mixed> $attributes */
    protected function makeTeam(array $attributes = []): Team
    {
        return Team::factory()->create(array_merge([
            'partner_id'               => $this->partner->id,
            'title'                    => 'Группа для тоста',
            'default_duration_minutes' => 60,
            'order_by'                 => 10,
            'is_enabled'               => true,
        ], $attributes));
    }

    protected function actingAsGroupsViewer(): void
    {
        $this->asAdmin();
    }
}
