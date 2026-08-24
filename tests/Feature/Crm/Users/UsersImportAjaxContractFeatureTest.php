<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Users;

use App\Models\ParentProfile;
use App\Models\PartnerLegalEntity;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Tests\Feature\Crm\CrmTestCase;
use Tests\Feature\Crm\Users\Concerns\GrantsUsersSectionPermissions;
use Tests\Feature\Crm\Users\Concerns\UsersImportTestHelpers;

/**
 * AJAX-контракт импорта учеников: postJson + X-Requested-With → JSON, статусы 200/422.
 */
final class UsersImportAjaxContractFeatureTest extends CrmTestCase
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

    public function test_preview_ajax_json_contract_on_success(): void
    {
        $email = 'ajax-preview-' . uniqid('', true) . '@example.test';
        $file = $this->makeImportFile([
            $this->sampleImportRow($this->legalEntity, [
                'Фамилия ученика' => 'Ajax',
                'Имя ученика' => 'Preview',
                'Email ученика' => $email,
            ]),
        ]);

        $response = $this->postJson(route('admin.users.import.preview'), ['file' => $file], $this->importAjaxHeaders())
            ->assertOk()
            ->assertJsonPath('valid', true)
            ->assertJsonPath('message', 'Файл проверен успешно. Подтвердите импорт.')
            ->assertJsonStructure([
                'message',
                'import_token',
                'valid',
                'summary' => [
                    'total_rows',
                    'create_count',
                    'update_count',
                    'update_with_changes_count',
                    'update_unchanged_count',
                    'update_with_clears_count',
                ],
                'preview' => [
                    ['row', 'student', 'team', 'mode', 'changes', 'has_clears'],
                ],
            ]);

        $this->assertNotSame('', (string) $response->json('import_token'));
        $this->assertNotSame('', trim((string) $response->getContent()));
    }

    public function test_preview_ajax_json_contract_includes_change_item_shape_for_update(): void
    {
        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id' => $this->studentRoleId(),
            'lastname' => 'Контракт',
            'name' => 'Старый',
            'email' => 'ajax-diff-shape@example.test',
            'phone' => '+79007770007',
            'is_enabled' => true,
        ]);

        $response = $this->postJson(route('admin.users.import.preview'), [
            'file' => $this->makeImportFile([
                $this->sampleImportRow($this->legalEntity, [
                    'Фамилия ученика' => $student->lastname,
                    'Имя ученика' => 'Новый',
                    'Email ученика' => $student->email,
                    'Группа' => '',
                    'Юр. лицо' => '',
                    'Телефон ученика' => '',
                    'Активен' => 'да',
                ]),
            ]),
        ], $this->importAjaxHeaders())
            ->assertOk()
            ->assertJsonPath('valid', true)
            ->assertJsonPath('summary.update_with_changes_count', 1)
            ->assertJsonPath('summary.update_unchanged_count', 0)
            ->assertJsonPath('summary.update_with_clears_count', 1)
            ->assertJsonStructure([
                'preview' => [
                    [
                        'row',
                        'student',
                        'team',
                        'mode',
                        'email',
                        'has_clears',
                        'changes' => [
                            [
                                'field',
                                'label',
                                'from',
                                'to',
                                'kind',
                            ],
                        ],
                    ],
                ],
            ]);

        $this->assertSame('update', $response->json('preview.0.mode'));
        $this->assertTrue((bool) $response->json('preview.0.has_clears'));
        $this->assertNotSame('', trim((string) $response->getContent()));
        $this->assertNotSame(500, $response->getStatusCode());
    }

    public function test_preview_ajax_validation_failure_without_file_returns_422_json(): void
    {
        $this->postJson(route('admin.users.import.preview'), [], $this->importAjaxHeaders())
            ->assertStatus(422)
            ->assertJsonValidationErrors(['file']);
    }

    public function test_preview_ajax_business_validation_failure_returns_422_json_contract(): void
    {
        $file = $this->makeImportFile([
            $this->sampleImportRow($this->legalEntity, [
                'Группа' => 'Группа без юрлица',
                'Юр. лицо' => '',
            ]),
        ]);

        $this->postJson(route('admin.users.import.preview'), ['file' => $file], $this->importAjaxHeaders())
            ->assertStatus(422)
            ->assertJsonPath('valid', false)
            ->assertJsonStructure([
                'message',
                'valid',
                'errors' => [
                    ['row', 'field', 'message'],
                ],
            ])
            ->assertJsonFragment(['field' => 'Юр. лицо']);
    }

    public function test_commit_ajax_json_contract_on_success(): void
    {
        $email = 'ajax-commit-' . uniqid('', true) . '@example.test';
        $preview = $this->previewImportFile($this->makeImportFile([
            $this->sampleImportRow($this->legalEntity, [
                'Email ученика' => $email,
            ]),
        ]));

        $response = $this->postJson(route('admin.users.import.commit'), [
            'import_token' => $preview['import_token'],
        ], $this->importAjaxHeaders())
            ->assertOk()
            ->assertJsonStructure(['message', 'created', 'updated'])
            ->assertJsonPath('created', 1)
            ->assertJsonPath('updated', 0);

        $this->assertStringContainsString('Импорт завершён', (string) $response->json('message'));
        $this->assertNotSame('', trim((string) $response->getContent()));

        $studentRoleId = (int) Role::query()->where('name', 'user')->value('id');
        $this->assertNotNull(
            User::query()
                ->where('partner_id', $this->partner->id)
                ->where('email', $email)
                ->where('role_id', $studentRoleId)
                ->first()
        );
    }

    public function test_commit_ajax_validation_failure_without_token_returns_422_json(): void
    {
        $this->postJson(route('admin.users.import.commit'), [], $this->importAjaxHeaders())
            ->assertStatus(422)
            ->assertJsonValidationErrors(['import_token']);
    }

    public function test_commit_ajax_validation_failure_with_invalid_uuid_returns_422_json(): void
    {
        $this->postJson(route('admin.users.import.commit'), [
            'import_token' => 'not-a-uuid',
        ], $this->importAjaxHeaders())
            ->assertStatus(422)
            ->assertJsonValidationErrors(['import_token']);
    }

    public function test_commit_ajax_returns_422_when_import_session_expired(): void
    {
        $this->postJson(route('admin.users.import.commit'), [
            'import_token' => '00000000-0000-4000-8000-000000000000',
        ], $this->importAjaxHeaders())
            ->assertStatus(422)
            ->assertJsonPath('message', 'Сессия импорта истекла или не найдена. Загрузите файл повторно.');
    }

    public function test_commit_ajax_returns_422_when_token_belongs_to_another_actor(): void
    {
        $preview = $this->previewImportFile($this->makeImportFile([
            $this->sampleImportRow($this->legalEntity, [
                'Email ученика' => 'token-scope-' . uniqid('', true) . '@example.test',
            ]),
        ]));

        $other = $this->createUserWithoutPermission('users.import', $this->partner);
        $this->grantUsersView($other);
        $this->grantPermission($other, 'users.import');
        $this->actingAs($other);

        $this->postJson(route('admin.users.import.commit'), [
            'import_token' => $preview['import_token'],
        ], $this->importAjaxHeaders())
            ->assertStatus(422)
            ->assertJsonPath('message', 'Сессия импорта недоступна для текущего пользователя.');

        Cache::forget('users_import:' . $preview['import_token']);
    }

    public function test_preview_fails_when_student_email_belongs_to_foreign_partner(): void
    {
        $foreignStudent = User::factory()->create([
            'partner_id' => $this->foreignPartner->id,
            'role_id' => $this->studentRoleId(),
            'email' => 'foreign-student@example.test',
        ]);

        $file = $this->makeImportFile([
            $this->sampleImportRow($this->legalEntity, [
                'Email ученика' => $foreignStudent->email,
            ]),
        ]);

        $this->postJson(route('admin.users.import.preview'), ['file' => $file], $this->importAjaxHeaders())
            ->assertStatus(422)
            ->assertJsonPath('valid', false)
            ->assertJsonFragment(['field' => 'Email ученика']);
    }

    public function test_preview_fails_on_duplicate_student_email_in_file(): void
    {
        $email = 'dup-student-' . uniqid('', true) . '@example.test';

        $file = $this->makeImportFile([
            $this->sampleImportRow($this->legalEntity, [
                'Фамилия ученика' => 'Один',
                'Имя ученика' => 'Первый',
                'Email ученика' => $email,
            ]),
            $this->sampleImportRow($this->legalEntity, [
                'Фамилия ученика' => 'Два',
                'Имя ученика' => 'Второй',
                'Email ученика' => $email,
            ]),
        ]);

        $this->postJson(route('admin.users.import.preview'), ['file' => $file], $this->importAjaxHeaders())
            ->assertStatus(422)
            ->assertJsonPath('valid', false)
            ->assertJsonFragment(['field' => 'Email ученика']);
    }

    public function test_preview_ajax_ok_when_nameless_parent_fio_is_filled_and_empty_phone_is_not_conflict(): void
    {
        $parent = ParentProfile::query()->create([
            'partner_id' => $this->partner->id,
            'lastname' => null,
            'firstname' => null,
            'email' => 'ajax-fill-parent@example.test',
            'phone' => '79627035846',
        ]);

        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id' => $this->studentRoleId(),
            'lastname' => 'Анзароков',
            'name' => 'Идар',
            'email' => 'ajax-fill-student@example.test',
            'parent_id' => $parent->id,
            'is_enabled' => true,
        ]);

        $response = $this->postJson(route('admin.users.import.preview'), [
            'file' => $this->makeImportFile([
                $this->sampleImportRow($this->legalEntity, [
                    'Фамилия ученика' => $student->lastname,
                    'Имя ученика' => $student->name,
                    'Группа' => '',
                    'Юр. лицо' => '',
                    'Email ученика' => $student->email,
                    'Email родителя' => $parent->email,
                    'Фамилия родителя' => 'Анзароков',
                    'Имя родителя' => 'Довлет',
                    'Телефон родителя' => '',
                ]),
            ]),
        ], $this->importAjaxHeaders())
            ->assertOk()
            ->assertJsonPath('valid', true)
            ->assertJsonPath('message', 'Файл проверен успешно. Подтвердите импорт.')
            ->assertJsonPath('preview.0.mode', 'update')
            ->assertJsonPath('summary.update_with_changes_count', 1);

        $this->assertNotSame('', (string) $response->json('import_token'));
        $change = collect($response->json('preview.0.changes') ?? [])->firstWhere('field', 'parent');
        $this->assertIsArray($change);
        $this->assertSame('Родитель', $change['label']);
        $this->assertSame('changed', $change['kind']);
        $this->assertStringContainsString('Анзароков', (string) $change['to']);
        $this->assertStringContainsString('Довлет', (string) $change['to']);
        $this->assertStringContainsString('79627035846', (string) $change['to']);
        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertNotSame('', trim((string) $response->getContent()));
    }

    public function test_preview_ajax_parent_directory_conflict_returns_422_errors_on_parent_email_field(): void
    {
        ParentProfile::query()->create([
            'partner_id' => $this->partner->id,
            'lastname' => 'Иванова',
            'firstname' => 'Анна',
            'email' => 'ajax-conflict-parent@example.test',
            'phone' => '79001112233',
        ]);

        $response = $this->postJson(route('admin.users.import.preview'), [
            'file' => $this->makeImportFile([
                $this->sampleImportRow($this->legalEntity, [
                    'Email родителя' => 'ajax-conflict-parent@example.test',
                    'Фамилия родителя' => 'Петрова',
                    'Имя родителя' => 'Анна',
                ]),
            ]),
        ], $this->importAjaxHeaders());

        $response->assertStatus(422)
            ->assertJsonPath('valid', false)
            ->assertJsonPath('message', 'Импорт не выполнен: найдены ошибки в файле.')
            ->assertJsonStructure([
                'message',
                'valid',
                'errors' => [
                    ['row', 'field', 'message'],
                ],
            ])
            ->assertJsonFragment([
                'field' => 'Email родителя',
                'message' => 'Родитель с таким email уже существует, но данные в файле не совпадают со справочником.',
            ]);

        $this->assertSame('Email родителя', $response->json('errors.0.field'));
        $this->assertNotSame('', trim((string) $response->getContent()));
    }

    public function test_commit_ajax_fills_nameless_parent_and_returns_updated_count(): void
    {
        $parent = ParentProfile::query()->create([
            'partner_id' => $this->partner->id,
            'lastname' => null,
            'firstname' => null,
            'email' => 'ajax-commit-fill@example.test',
            'phone' => '79627035846',
        ]);

        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id' => $this->studentRoleId(),
            'email' => 'ajax-commit-fill-student@example.test',
            'parent_id' => $parent->id,
            'is_enabled' => true,
        ]);

        $preview = $this->previewImportFile($this->makeImportFile([
            $this->sampleImportRow($this->legalEntity, [
                'Фамилия ученика' => $student->lastname,
                'Имя ученика' => $student->name,
                'Группа' => '',
                'Юр. лицо' => '',
                'Email ученика' => $student->email,
                'Email родителя' => $parent->email,
                'Фамилия родителя' => 'Анзароков',
                'Имя родителя' => 'Довлет',
                'Телефон родителя' => '',
            ]),
        ]));

        $this->postJson(route('admin.users.import.commit'), [
            'import_token' => $preview['import_token'],
        ], $this->importAjaxHeaders())
            ->assertOk()
            ->assertJsonPath('updated', 1)
            ->assertJsonStructure(['message', 'created', 'updated']);

        $parent->refresh();
        $this->assertSame('Анзароков', $parent->lastname);
        $this->assertSame('Довлет', $parent->firstname);
        $this->assertSame('79627035846', $parent->phone);
    }
}
