<?php

namespace Tests\Feature\Crm\Users;

use App\Models\ParentProfile;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Разметка блока родителя в модалках create/edit на /admin/users.
 */
final class AdminUsersParentUiFeatureTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed'      => true,
        ]);

        $this->asAdmin();

        DB::table('permission_role')->insertOrIgnore([
            'partner_id'    => $this->partner->id,
            'role_id'       => $this->user->role_id,
            'permission_id' => $this->permissionId('users.view'),
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);
    }

    public function test_users_page_without_parents_shows_fio_only_without_directory_block(): void
    {
        $this->get(route('admin.user1'))
            ->assertOk()
            ->assertSee('js-student-parent-fields', false)
            ->assertSee('id="create-parent-lastname"', false)
            ->assertSee('id="edit-parent-lastname"', false)
            ->assertDontSee('id="create-parent-id"', false)
            ->assertDontSee('Родитель в справочнике', false)
            ->assertDontSee('Из справочника', false)
            ->assertSee('data-has-parent-profiles="0"', false);
    }

    public function test_users_page_with_parents_shows_segmented_directory_and_select(): void
    {
        ParentProfile::factory()->create([
            'partner_id' => $this->partner->id,
            'lastname'   => 'ЕстьВСправочнике',
            'firstname'  => 'Род',
        ]);

        $response = $this->get(route('admin.user1'))->assertOk();

        $html = $response->getContent();
        $this->assertNotFalse($html);

        $this->assertStringContainsString('data-has-parent-profiles="1"', $html);
        $this->assertStringContainsString('class="parent-mode-segmented"', $html);
        $this->assertStringContainsString('id="create-parent-id"', $html);
        $this->assertStringContainsString('id="edit-parent-id"', $html);
        $this->assertStringContainsString('Из справочника', $html);
        $this->assertStringContainsString('Новый родитель', $html);
        $this->assertStringContainsString('data-student-role-id', $html);
        $this->assertStringContainsString('syncStudentParentFieldsVisibility', $html);
        $this->assertStringContainsString('syncParentSelectLabelFromFio', $html);

        // ФИО видны и в режиме справочника (не скрыты начальным d-none).
        $this->assertMatchesRegularExpression(
            '/class="js-parent-fio-section"(?![^>]*\bd-none\b)/',
            $html
        );
        $this->assertStringNotContainsString("fioSection.toggleClass('d-none', !isNew)", $html);
    }
}
