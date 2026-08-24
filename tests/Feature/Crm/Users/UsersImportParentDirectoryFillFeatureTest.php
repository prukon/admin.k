<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Users;

use App\Models\ParentProfile;
use App\Models\PartnerLegalEntity;
use App\Models\User;
use Tests\Feature\Crm\CrmTestCase;
use Tests\Feature\Crm\Users\Concerns\GrantsUsersSectionPermissions;
use Tests\Feature\Crm\Users\Concerns\UsersImportTestHelpers;

/**
 * Дозапись пустых ФИО/телефона родителя при импорте Excel.
 *
 * UX-баг: безымянная карточка (как prod «Родитель #38») + ФИО в файле + пустой телефон
 * раньше давала 422 «данные не совпадают со справочником»; Select2 оставался «Родитель #N».
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class UsersImportParentDirectoryFillFeatureTest extends CrmTestCase
{
    use GrantsUsersSectionPermissions;
    use UsersImportTestHelpers;

    private PartnerLegalEntity $legalEntity;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);

        $this->asAdmin();
        $this->grantPermission($this->user, 'users.import');
        $this->requirePhpSpreadsheet();
        $this->legalEntity = $this->createImportLegalEntity();
    }

    /**
     * [P2] Страница → preview 200 (не 422) → commit → список/справочник/edit без «Родитель #».
     */
    public function test_nameless_parent_gets_fio_from_excel_and_select2_stops_showing_parent_number(): void
    {
        $this->withoutVite();

        $parent = $this->createNamelessParent(
            'dovlet.fill@example.test',
            '79627035846',
        );
        $student = $this->createLinkedStudent($parent, 'fill-student@example.test', 'Анзароков', 'Идар');

        $page = $this->get(route('admin.user1'))->assertOk()->getContent();
        $this->assertUsersImportModalInitialMarkup($page);

        $beforeSearch = $this->getJson(route('admin.users.parents.search', ['id' => $parent->id]))
            ->assertOk()
            ->json('results');
        $this->assertIsArray($beforeSearch);
        $this->assertCount(1, $beforeSearch);
        $this->assertSame('Родитель #'.$parent->id, $beforeSearch[0]['text']);
        $this->assertNull($beforeSearch[0]['parent_lastname']);

        $beforeRow = $this->usersDataRow($student->id);
        $this->assertSame('', (string) ($beforeRow['parent'] ?? 'missing'));

        $preview = $this->postJson(route('admin.users.import.preview'), [
            'file' => $this->namelessParentFillFile($student, $parent->email, [
                'Фамилия родителя' => 'Анзароков',
                'Имя родителя' => 'Довлет',
                'Телефон родителя' => '',
            ]),
        ], $this->importAjaxHeaders())
            ->assertOk()
            ->assertJsonPath('valid', true)
            ->assertJsonPath('summary.update_with_changes_count', 1);

        $this->assertNotSame('', (string) $preview->json('import_token'));
        $parentChange = collect($preview->json('preview.0.changes') ?? [])->firstWhere('field', 'parent');
        $this->assertIsArray($parentChange);
        $this->assertSame('changed', $parentChange['kind']);
        $this->assertStringContainsString('Анзароков', (string) $parentChange['to']);
        $this->assertStringContainsString('Довлет', (string) $parentChange['to']);
        $this->assertStringContainsString('79627035846', (string) $parentChange['to']);

        $this->postJson(route('admin.users.import.commit'), [
            'import_token' => $preview->json('import_token'),
        ], $this->importAjaxHeaders())
            ->assertOk()
            ->assertJsonPath('updated', 1);

        $parent->refresh();
        $student->refresh();
        $this->assertSame('Анзароков', $parent->lastname);
        $this->assertSame('Довлет', $parent->firstname);
        $this->assertSame('79627035846', $parent->phone);
        $this->assertSame((int) $parent->id, (int) $student->parent_id);

        $afterSearch = $this->getJson(route('admin.users.parents.search', [
            'q' => 'Анзароков',
        ]))->assertOk()->json('results');
        $hit = collect($afterSearch)->firstWhere('id', $parent->id);
        $this->assertIsArray($hit);
        $this->assertSame('Анзароков Довлет', $hit['text']);
        $this->assertStringNotContainsString('Родитель #', (string) $hit['text']);

        $afterById = $this->getJson(route('admin.users.parents.search', ['id' => $parent->id]))
            ->assertOk()
            ->json('results.0.text');
        $this->assertSame('Анзароков Довлет', $afterById);

        $afterRow = $this->usersDataRow($student->id);
        $this->assertSame('Анзароков Довлет', $afterRow['parent']);

        $this->getJson(route('admin.user.edit', $student->id), [
            'X-Requested-With' => 'XMLHttpRequest',
        ])
            ->assertOk()
            ->assertJsonPath('user.parent_id', $parent->id)
            ->assertJsonPath('user.parent_lastname', 'Анзароков')
            ->assertJsonPath('user.parent_firstname', 'Довлет')
            ->assertJsonPath('user.parent_email', 'dovlet.fill@example.test');
    }

    public function test_empty_parent_cells_do_not_clear_directory_fio_or_phone(): void
    {
        $parent = ParentProfile::query()->create([
            'partner_id' => $this->partner->id,
            'lastname' => 'Иванова',
            'firstname' => 'Анна',
            'middlename' => 'Петровна',
            'email' => 'keep-fio@example.test',
            'phone' => '79001112233',
        ]);
        $student = $this->createLinkedStudent($parent, 'keep-fio-student@example.test');

        $preview = $this->previewImportFile($this->namelessParentFillFile($student, $parent->email, [
            'Фамилия родителя' => '',
            'Имя родителя' => '',
            'Отчество родителя' => '',
            'Телефон родителя' => '',
        ]));

        $this->postJson(route('admin.users.import.commit'), [
            'import_token' => $preview['import_token'],
        ], $this->importAjaxHeaders())->assertOk();

        $parent->refresh();
        $this->assertSame('Иванова', $parent->lastname);
        $this->assertSame('Анна', $parent->firstname);
        $this->assertSame('Петровна', $parent->middlename);
        $this->assertSame('79001112233', $parent->phone);
        $this->assertSame((int) $parent->id, (int) $student->fresh()->parent_id);
    }

    public function test_new_student_row_attaches_to_nameless_parent_and_fills_fio(): void
    {
        $parent = $this->createNamelessParent('create-fill@example.test', '79005554433');

        $file = $this->makeImportFile([
            $this->sampleImportRow($this->legalEntity, [
                'Фамилия ученика' => 'Сидоров',
                'Имя ученика' => 'Пётр',
                'Группа' => '',
                'Юр. лицо' => '',
                'Email ученика' => '',
                'Email родителя' => 'create-fill@example.test',
                'Фамилия родителя' => 'Сидорова',
                'Имя родителя' => 'Мария',
                'Телефон родителя' => '',
            ]),
        ]);

        $preview = $this->previewImportFile($file);
        $this->assertSame('create', $preview['preview']['preview'][0]['mode'] ?? null);

        $this->postJson(route('admin.users.import.commit'), [
            'import_token' => $preview['import_token'],
        ], $this->importAjaxHeaders())
            ->assertOk()
            ->assertJsonPath('created', 1);

        $parent->refresh();
        $this->assertSame('Сидорова', $parent->lastname);
        $this->assertSame('Мария', $parent->firstname);
        $this->assertSame('79005554433', $parent->phone);

        $created = User::query()
            ->where('partner_id', $this->partner->id)
            ->where('lastname', 'Сидоров')
            ->where('name', 'Пётр')
            ->first();
        $this->assertNotNull($created);
        $this->assertSame((int) $parent->id, (int) $created->parent_id);
        $this->assertSame(1, ParentProfile::query()->where('email', 'create-fill@example.test')->count());
    }

    public function test_foreign_partner_nameless_parent_with_same_email_is_not_filled(): void
    {
        $foreign = $this->createNamelessParent(
            'shared-email@example.test',
            '79007770001',
            $this->foreignPartner->id,
        );

        $file = $this->makeImportFile([
            $this->sampleImportRow($this->legalEntity, [
                'Фамилия ученика' => 'Свой',
                'Имя ученика' => 'Ученик',
                'Группа' => '',
                'Юр. лицо' => '',
                'Email родителя' => 'shared-email@example.test',
                'Фамилия родителя' => 'Своя',
                'Имя родителя' => 'Карточка',
            ]),
        ]);

        $preview = $this->previewImportFile($file);
        $this->postJson(route('admin.users.import.commit'), [
            'import_token' => $preview['import_token'],
        ], $this->importAjaxHeaders())->assertOk();

        $foreign->refresh();
        $this->assertNull($foreign->lastname);
        $this->assertNull($foreign->firstname);
        $this->assertSame('79007770001', $foreign->phone);

        $own = ParentProfile::query()
            ->where('partner_id', $this->partner->id)
            ->where('email', 'shared-email@example.test')
            ->first();
        $this->assertNotNull($own);
        $this->assertNotSame((int) $foreign->id, (int) $own->id);
        $this->assertSame('Своя', $own->lastname);
        $this->assertSame('Карточка', $own->firstname);
    }

    public function test_empty_directory_phone_is_filled_from_file_without_touching_fio(): void
    {
        $parent = ParentProfile::query()->create([
            'partner_id' => $this->partner->id,
            'lastname' => 'Петрова',
            'firstname' => 'Ольга',
            'email' => 'phone-fill@example.test',
            'phone' => null,
        ]);
        $student = $this->createLinkedStudent($parent, 'phone-fill-student@example.test');

        $preview = $this->previewImportFile($this->namelessParentFillFile($student, $parent->email, [
            'Фамилия родителя' => 'Петрова',
            'Имя родителя' => 'Ольга',
            'Телефон родителя' => '79001234567',
        ]));

        $this->postJson(route('admin.users.import.commit'), [
            'import_token' => $preview['import_token'],
        ], $this->importAjaxHeaders())->assertOk();

        $parent->refresh();
        $this->assertSame('Петрова', $parent->lastname);
        $this->assertSame('Ольга', $parent->firstname);
        $this->assertSame('79001234567', $parent->phone);
    }

    public function test_other_nameless_parent_is_not_filled_when_file_email_does_not_match(): void
    {
        $other = $this->createNamelessParent('other-nameless@example.test', '79000000001');

        $file = $this->makeImportFile([
            $this->sampleImportRow($this->legalEntity, [
                'Фамилия ученика' => 'Новый',
                'Имя ученика' => 'Ребёнок',
                'Группа' => '',
                'Юр. лицо' => '',
                'Email родителя' => 'brand-new-parent@example.test',
                'Фамилия родителя' => 'Новая',
                'Имя родителя' => 'Мама',
            ]),
        ]);

        $preview = $this->previewImportFile($file);
        $this->postJson(route('admin.users.import.commit'), [
            'import_token' => $preview['import_token'],
        ], $this->importAjaxHeaders())->assertOk();

        $other->refresh();
        $this->assertNull($other->lastname);
        $this->assertNull($other->firstname);

        $created = ParentProfile::query()
            ->where('partner_id', $this->partner->id)
            ->where('email', 'brand-new-parent@example.test')
            ->first();
        $this->assertNotNull($created);
        $this->assertSame('Новая', $created->lastname);
        $this->assertSame('Мама', $created->firstname);
    }

    public function test_update_with_all_parent_cells_empty_still_unlinks_and_does_not_clear_directory(): void
    {
        $parent = ParentProfile::query()->create([
            'partner_id' => $this->partner->id,
            'lastname' => 'Останется',
            'firstname' => 'ВСправочнике',
            'email' => 'unlink-keep@example.test',
            'phone' => '79009998877',
        ]);
        $student = $this->createLinkedStudent($parent, 'unlink-student@example.test');

        $file = $this->makeImportFile([
            $this->sampleImportRow($this->legalEntity, [
                'Фамилия ученика' => $student->lastname,
                'Имя ученика' => $student->name,
                'Группа' => '',
                'Юр. лицо' => '',
                'Email ученика' => $student->email,
                'Email родителя' => '',
                'Фамилия родителя' => '',
                'Имя родителя' => '',
                'Отчество родителя' => '',
                'Телефон родителя' => '',
            ]),
        ]);

        $preview = $this->previewImportFile($file);
        $unlink = collect($preview['preview']['preview'][0]['changes'] ?? [])->firstWhere('field', 'parent');
        $this->assertIsArray($unlink);
        $this->assertSame('cleared', $unlink['kind']);

        $this->postJson(route('admin.users.import.commit'), [
            'import_token' => $preview['import_token'],
        ], $this->importAjaxHeaders())->assertOk();

        $this->assertNull($student->fresh()->parent_id);
        $parent->refresh();
        $this->assertSame('Останется', $parent->lastname);
        $this->assertSame('ВСправочнике', $parent->firstname);
        $this->assertSame('79009998877', $parent->phone);
    }

    public function test_preview_returns_422_on_parent_email_field_when_lastname_conflicts(): void
    {
        ParentProfile::query()->create([
            'partner_id' => $this->partner->id,
            'lastname' => 'Иванова',
            'firstname' => 'Анна',
            'email' => 'conflict-fill@example.test',
            'phone' => '79001110000',
        ]);

        $response = $this->postJson(route('admin.users.import.preview'), [
            'file' => $this->makeImportFile([
                $this->sampleImportRow($this->legalEntity, [
                    'Email родителя' => 'conflict-fill@example.test',
                    'Фамилия родителя' => 'Петрова',
                    'Имя родителя' => 'Анна',
                ]),
            ]),
        ], $this->importAjaxHeaders());

        $response->assertStatus(422)
            ->assertJsonPath('valid', false)
            ->assertJsonPath('message', 'Импорт не выполнен: найдены ошибки в файле.')
            ->assertJsonFragment([
                'field' => 'Email родителя',
                'message' => 'Родитель с таким email уже существует, но данные в файле не совпадают со справочником.',
            ]);

        $this->assertIsArray($response->json('errors.0'));
        $this->assertSame('Email родителя', $response->json('errors.0.field'));
        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertNotSame('', trim((string) $response->getContent()));
    }

    private function createNamelessParent(string $email, string $phone, ?int $partnerId = null): ParentProfile
    {
        return ParentProfile::query()->create([
            'partner_id' => $partnerId ?? $this->partner->id,
            'lastname' => null,
            'firstname' => null,
            'middlename' => null,
            'email' => $email,
            'phone' => $phone,
        ]);
    }

    private function createLinkedStudent(
        ParentProfile $parent,
        string $email,
        string $lastname = 'Учеников',
        string $name = 'Сын',
    ): User {
        return User::factory()->create([
            'partner_id' => $parent->partner_id,
            'role_id' => $this->studentRoleId(),
            'lastname' => $lastname,
            'name' => $name,
            'email' => $email,
            'parent_id' => $parent->id,
            'is_enabled' => true,
        ]);
    }

    /**
     * @param array<string, string|null> $parentOverrides
     */
    private function namelessParentFillFile(User $student, string $parentEmail, array $parentOverrides): \Illuminate\Http\UploadedFile
    {
        return $this->makeImportFile([
            $this->sampleImportRow($this->legalEntity, array_merge([
                'Фамилия ученика' => $student->lastname,
                'Имя ученика' => $student->name,
                'Группа' => '',
                'Юр. лицо' => '',
                'Email ученика' => $student->email,
                'Активен' => 'да',
                'Email родителя' => $parentEmail,
            ], $parentOverrides)),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function usersDataRow(int $studentId): array
    {
        $json = $this->getJson('/admin/users/data?id='.$studentId, $this->importAjaxHeaders())
            ->assertOk()
            ->json();
        $row = collect($json['data'] ?? [])->firstWhere('id', $studentId);
        $this->assertIsArray($row);

        return $row;
    }

    private function assertUsersImportModalInitialMarkup(string $html): void
    {
        $this->assertStringContainsString('id="usersImportModal"', $html);
        $this->assertStringContainsString('id="usersImportMemoAccordion"', $html);
        $this->assertStringContainsString('aria-expanded="false"', $html);
        $this->assertStringContainsString('accordion-button collapsed', $html);
        $this->assertStringContainsString('не трогают</b> справочник', $html);
        $this->assertStringContainsString('дописываются</b> в пустые поля карточки', $html);

        $filePos = strpos($html, 'id="users-import-file"');
        $errorPos = strpos($html, 'id="users-import-file-error"');
        $this->assertNotFalse($filePos);
        $this->assertNotFalse($errorPos);
        $this->assertGreaterThan($filePos, $errorPos);

        $uploadPos = strpos($html, 'id="users-import-step-upload"');
        $previewPos = strpos($html, 'id="users-import-step-preview"');
        $errorsPos = strpos($html, 'id="users-import-step-errors"');
        $successPos = strpos($html, 'id="users-import-step-success"');
        $this->assertNotFalse($uploadPos);
        $this->assertNotFalse($previewPos);
        $this->assertNotFalse($errorsPos);
        $this->assertNotFalse($successPos);
        $this->assertGreaterThan($uploadPos, $previewPos);
        $this->assertGreaterThan($previewPos, $errorsPos);
        $this->assertGreaterThan($errorsPos, $successPos);
        $this->assertStringContainsString('id="users-import-step-preview" class="d-none"', $html);
        $this->assertStringContainsString('id="users-import-step-errors" class="d-none"', $html);
        $this->assertStringContainsString('id="users-import-step-success" class="d-none"', $html);

        $this->assertMatchesRegularExpression(
            '/<button type="button"[^>]*id="users-import-check-btn"/s',
            $html
        );
        $this->assertMatchesRegularExpression(
            '/<button type="button"[^>]*id="users-import-commit-btn"/s',
            $html
        );
        $this->assertStringContainsString('d-none" id="users-import-commit-btn"', $html);
        $this->assertStringContainsString('d-none" id="users-import-reset-btn"', $html);
    }
}
