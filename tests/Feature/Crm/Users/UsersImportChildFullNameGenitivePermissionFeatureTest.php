<?php

namespace Tests\Feature\Crm\Users;

use App\Models\User;
use App\Services\Users\Import\UsersImportColumns;
use App\Services\Users\Import\UsersSpreadsheetCells;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\Feature\Crm\CrmTestCase;
use Tests\Feature\Crm\Users\Concerns\GrantsUsersSectionPermissions;
use Tests\Feature\Crm\Users\Concerns\UsersImportTestHelpers;

/**
 * Право users.full_name_genitive для колонки импорта Excel.
 */
final class UsersImportChildFullNameGenitivePermissionFeatureTest extends CrmTestCase
{
    use GrantsUsersSectionPermissions;
    use UsersImportTestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->requirePhpSpreadsheet();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed'      => true,
        ]);

        $this->asAdmin();
        $this->grantUsersView($this->user);
        $this->grantPermission($this->user, 'users.import');
    }

    /** @return list<string> */
    private function readTemplateHeaders(string $binary): array
    {
        $tmp = tempnam(sys_get_temp_dir(), 'import-tpl-');
        file_put_contents($tmp, $binary);
        $sheet = IOFactory::load($tmp)->getActiveSheet();
        @unlink($tmp);

        $headers = [];
        for ($col = 1; $col <= 20; $col++) {
            $value = trim((string) (UsersSpreadsheetCells::getValue($sheet, $col, 1) ?? ''));
            if ($value === '') {
                break;
            }
            $headers[] = $value;
        }

        return $headers;
    }

    public function test_template_without_permission_omits_genitive_column(): void
    {
        $response = $this->get(route('admin.users.import.template'));
        $response->assertOk();

        $headers = $this->readTemplateHeaders($response->streamedContent());

        $this->assertNotContains('ФИО ученика в родительном падеже', $headers);
        $this->assertSame(
            array_values(UsersImportColumns::headerLabels(false)),
            $headers
        );
    }

    public function test_template_with_permission_includes_genitive_column(): void
    {
        $this->grantPermission($this->user, 'users.full_name_genitive');

        $response = $this->get(route('admin.users.import.template'));
        $response->assertOk();

        $headers = $this->readTemplateHeaders($response->streamedContent());

        $this->assertContains('ФИО ученика в родительном падеже', $headers);
        $this->assertSame(
            array_values(UsersImportColumns::headerLabels(true)),
            $headers
        );
    }

    public function test_import_without_permission_does_not_overwrite_genitive(): void
    {
        $legalEntity = $this->createImportLegalEntity();
        $student = User::factory()->create([
            'partner_id'         => $this->partner->id,
            'role_id'            => $this->studentRoleId(),
            'email'              => 'genitive-import@example.com',
            'full_name_genitive' => 'Сохранить Это',
        ]);

        $file = $this->makeImportFile([
            $this->sampleImportRow($legalEntity, [
                'Фамилия ученика' => $student->lastname,
                'Имя ученика' => $student->name,
                'Email ученика' => $student->email,
                'ФИО ученика в родительном падеже' => 'Новое Из Файла',
                'Группа' => '',
                'Юр. лицо' => '',
            ]),
        ]);

        $preview = $this->post(route('admin.users.import.preview'), [
            'file' => $file,
        ], ['X-Requested-With' => 'XMLHttpRequest'])->assertOk();

        $token = $preview->json('import_token');
        $this->assertNotEmpty($token);

        $this->postJson(route('admin.users.import.commit'), [
            'import_token' => $token,
        ])->assertOk();

        $this->assertSame('Сохранить Это', $student->fresh()->full_name_genitive);
    }

    public function test_import_with_permission_writes_genitive(): void
    {
        $this->grantPermission($this->user, 'users.full_name_genitive');
        $legalEntity = $this->createImportLegalEntity();

        $file = $this->makeImportFile([
            $this->sampleImportRow($legalEntity, [
                'Фамилия ученика' => 'ГенИмпорт',
                'Имя ученика' => 'Ученик',
                'ФИО ученика в родительном падеже' => 'ГенИмпорта Ученика',
                'Группа' => '',
                'Юр. лицо' => '',
            ]),
        ]);

        $preview = $this->post(route('admin.users.import.preview'), [
            'file' => $file,
        ], ['X-Requested-With' => 'XMLHttpRequest'])->assertOk();

        $this->postJson(route('admin.users.import.commit'), [
            'import_token' => $preview->json('import_token'),
        ])->assertOk();

        $student = User::query()
            ->where('partner_id', $this->partner->id)
            ->where('lastname', 'ГенИмпорт')
            ->first();

        $this->assertNotNull($student);
        $this->assertSame('ГенИмпорта Ученика', $student->full_name_genitive);
    }

    public function test_permission_is_hidden_and_absent_from_role_base_config(): void
    {
        $row = DB::table('permissions')->where('name', 'users.full_name_genitive')->first();
        $this->assertNotNull($row);
        $this->assertSame(0, (int) $row->is_visible);

        $adminBase = config('role_base_permissions.roles.admin', []);
        $this->assertIsArray($adminBase);
        $this->assertNotContains('users.full_name_genitive', $adminBase);
    }
}
