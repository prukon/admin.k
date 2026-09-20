<?php

namespace Tests\Feature\Crm\Contracts;

use App\Models\Contract;
use App\Models\LessonPackage;
use App\Models\User;
use App\Models\UserLessonPackage;
use App\Services\Contracts\ContractLessonPackageBinder;
use App\Support\Money;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

/**
 * Привязка шаблона абонемента к договору: lookup, обязательность, снимок, изоляция.
 */
class ContractLessonPackageBindFeatureTest extends ContractsFeatureTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['billing.contract_create_fee' => 70.00]);
        $this->partner->wallet_balance_cents = 10000;
        $this->partner->save();
        Mail::fake();
    }

    /** @test */
    public function user_packages_forbidden_without_bind_permission(): void
    {
        $student = $this->makeStudent();

        $this->getJson(route('contracts.user.packages', ['user_id' => $student->id]))
            ->assertStatus(403);
    }

    /** @test */
    public function user_packages_returns_empty_catalog_without_422(): void
    {
        $this->grantLessonPackageBindPermission();
        $student = $this->makeStudent();

        $this->getJson(route('contracts.user.packages', ['user_id' => $student->id]))
            ->assertOk()
            ->assertExactJson([
                'packages'    => [],
                'selected_id' => null,
            ]);
    }

    /** @test */
    public function user_packages_without_user_id_returns_active_catalog(): void
    {
        $this->grantLessonPackageBindPermission();
        $active = $this->makePackage(['name' => 'Активный']);
        $this->makePackage(['name' => 'Скрытый', 'is_active' => false]);

        $resp = $this->getJson(route('contracts.user.packages'))
            ->assertOk()
            ->assertJsonPath('selected_id', null);

        $ids = collect($resp->json('packages'))->pluck('id')->all();
        $this->assertContains($active->id, $ids);
        $this->assertNotContains(
            LessonPackage::query()->where('name', 'Скрытый')->value('id'),
            $ids
        );
        $this->assertFalse((bool) collect($resp->json('packages'))->firstWhere('id', $active->id)['is_assigned']);
    }

    /** @test */
    public function user_packages_marks_latest_assignment_and_prefers_it(): void
    {
        $this->grantLessonPackageBindPermission();
        $student = $this->makeStudent();
        $older = $this->makePackage(['name' => 'Старый абонемент']);
        $latest = $this->makePackage(['name' => 'Новый абонемент']);

        $this->assignPackage($student, $older, createdAt: now()->subDay());
        $this->assignPackage($student, $latest, createdAt: now());

        $resp = $this->getJson(route('contracts.user.packages', ['user_id' => $student->id]))
            ->assertOk()
            ->assertJsonPath('selected_id', $latest->id);

        $packages = $resp->json('packages');
        $this->assertSame($latest->id, $packages[0]['id']);
        $this->assertTrue($packages[0]['is_assigned']);
        $this->assertSame('Новый абонемент (установлен)', $packages[0]['label']);

        $olderRow = collect($packages)->firstWhere('id', $older->id);
        $this->assertNotNull($olderRow);
        $this->assertFalse($olderRow['is_assigned']);
        $this->assertSame('Старый абонемент', $olderRow['label']);
        $this->assertSame(1, collect($packages)->where('is_assigned', true)->count());
    }

    /** @test */
    public function user_packages_includes_inactive_latest_assignment(): void
    {
        $this->grantLessonPackageBindPermission();
        $student = $this->makeStudent();
        $inactive = $this->makePackage(['name' => 'Снятый', 'is_active' => false]);
        $this->assignPackage($student, $inactive);

        $resp = $this->getJson(route('contracts.user.packages', ['user_id' => $student->id]))
            ->assertOk()
            ->assertJsonPath('selected_id', $inactive->id);

        $this->assertSame($inactive->id, $resp->json('packages.0.id'));
        $this->assertTrue($resp->json('packages.0.is_assigned'));
    }

    /** @test */
    public function user_packages_hides_foreign_partner_catalog(): void
    {
        $this->grantLessonPackageBindPermission();
        $own = $this->makePackage(['name' => 'Свой']);
        LessonPackage::factory()->forPartner($this->foreignPartner->id)->create([
            'name'      => 'Чужой',
            'is_active' => true,
        ]);

        $ids = collect(
            $this->getJson(route('contracts.user.packages'))->assertOk()->json('packages')
        )->pluck('id')->all();

        $this->assertSame([$own->id], $ids);
    }

    /** @test */
    public function store_without_bind_permission_ignores_posted_package(): void
    {
        $student = $this->makeStudent();
        $package = $this->makePackage();
        $template = $this->createContractTemplateWithVersion();

        $this->post(route('contracts.store'), [
            'creation_mode'        => Contract::CREATION_MODE_TEMPLATE,
            'user_id'              => $student->id,
            'contract_template_id' => $template->id,
            'lesson_package_id'    => $package->id,
        ])->assertSessionHasNoErrors();

        $contract = Contract::query()->firstOrFail();
        $this->assertNull($contract->lesson_package_id);
        $this->assertNull($contract->package_snapshot);
    }

    /** @test */
    public function store_template_without_package_placeholders_allows_empty_package(): void
    {
        $this->grantLessonPackageBindPermission();
        $student = $this->makeStudent();
        $template = $this->createContractTemplateWithVersion();

        $this->post(route('contracts.store'), [
            'creation_mode'        => Contract::CREATION_MODE_TEMPLATE,
            'user_id'              => $student->id,
            'contract_template_id' => $template->id,
        ])->assertSessionHasNoErrors();

        $contract = Contract::query()->firstOrFail();
        $this->assertNull($contract->lesson_package_id);
        $this->assertNull($contract->package_snapshot);
    }

    /** @test */
    public function store_template_with_package_placeholders_requires_package(): void
    {
        $this->grantLessonPackageBindPermission();
        $student = $this->makeStudent();
        $template = $this->makePackageTemplate();

        $this->from(route('contracts.index', ['create' => 1]))
            ->post(route('contracts.store'), [
                'creation_mode'        => Contract::CREATION_MODE_TEMPLATE,
                'user_id'              => $student->id,
                'contract_template_id' => $template->id,
            ])
            ->assertRedirect(route('contracts.index', [
                'create'  => 1,
                'user_id' => $student->id,
            ]))
            ->assertSessionHasErrors('lesson_package_id');

        $this->assertSame(0, Contract::query()->count());
        $this->assertSame(10000, (int) $this->partner->fresh()->wallet_balance_cents);
    }

    /** @test */
    public function store_pdf_with_bind_permission_allows_empty_package(): void
    {
        $this->grantLessonPackageBindPermission();
        Storage::fake();
        $student = $this->makeStudent();
        $pdf = UploadedFile::fake()->create('bind.pdf', 20, 'application/pdf');

        $this->post(route('contracts.store'), [
            'creation_mode' => Contract::CREATION_MODE_PDF,
            'user_id'       => $student->id,
            'pdf'           => $pdf,
        ])->assertSessionHasNoErrors();

        $contract = Contract::query()->firstOrFail();
        $this->assertNull($contract->lesson_package_id);
        $this->assertNull($contract->package_snapshot);
    }

    /** @test */
    public function store_snapshots_catalog_fields_and_ignores_later_catalog_edits(): void
    {
        $this->grantLessonPackageBindPermission();
        $student = $this->makeStudent();
        $package = $this->makePackage([
            'name'                    => 'Старт',
            'price_cents'             => 50000,
            'lessons_per_week'        => 2,
            'lessons_per_month'       => 8,
            'lesson_duration_minutes' => 45,
            'lesson_price_cents'      => 6250,
        ]);
        $template = $this->makePackageTemplate();

        $this->post(route('contracts.store'), [
            'creation_mode'        => Contract::CREATION_MODE_TEMPLATE,
            'user_id'              => $student->id,
            'contract_template_id' => $template->id,
            'lesson_package_id'    => $package->id,
        ])->assertSessionHasNoErrors();

        $contract = Contract::query()->firstOrFail();
        $this->assertSame($package->id, (int) $contract->lesson_package_id);
        $this->assertSame('Старт', $contract->packageSnapshotName());
        $this->assertSame([
            'name'                    => 'Старт',
            'price_cents'             => 50000,
            'lessons_per_week'        => 2,
            'lessons_per_month'       => 8,
            'lesson_duration_minutes' => 45,
            'lesson_price_cents'      => 6250,
        ], $contract->package_snapshot);

        $package->update([
            'name'        => 'Переименован',
            'price_cents' => 1,
        ]);

        $values = app(ContractLessonPackageBinder::class)->placeholderValuesForContract($contract->fresh());
        $this->assertSame('Старт', $values[ContractLessonPackageBinder::KEY_NAME]);
        $this->assertSame(Money::formatRub(50000).' руб.', $values[ContractLessonPackageBinder::KEY_PRICE]);
        $this->assertSame('2', $values[ContractLessonPackageBinder::KEY_LESSONS_PER_WEEK]);
        $this->assertSame('8', $values[ContractLessonPackageBinder::KEY_LESSONS_PER_MONTH]);
        $this->assertSame('45', $values[ContractLessonPackageBinder::KEY_DURATION_MINUTES]);
        $this->assertSame(Money::formatRub(6250).' руб.', $values[ContractLessonPackageBinder::KEY_LESSON_PRICE]);
    }

    /** @test */
    public function store_rejects_foreign_and_inactive_unassigned_package(): void
    {
        $this->grantLessonPackageBindPermission();
        $student = $this->makeStudent();
        $template = $this->makePackageTemplate();
        $foreign = LessonPackage::factory()->forPartner($this->foreignPartner->id)->create([
            'is_active' => true,
        ]);
        $inactive = $this->makePackage(['is_active' => false]);

        $this->from(route('contracts.index'))
            ->post(route('contracts.store'), [
                'creation_mode'        => Contract::CREATION_MODE_TEMPLATE,
                'user_id'              => $student->id,
                'contract_template_id' => $template->id,
                'lesson_package_id'    => $foreign->id,
            ])
            ->assertSessionHasErrors('lesson_package_id');

        $this->from(route('contracts.index'))
            ->post(route('contracts.store'), [
                'creation_mode'        => Contract::CREATION_MODE_TEMPLATE,
                'user_id'              => $student->id,
                'contract_template_id' => $template->id,
                'lesson_package_id'    => $inactive->id,
            ])
            ->assertSessionHasErrors('lesson_package_id');

        $this->assertSame(0, Contract::query()->count());
    }

    /** @test */
    public function store_allows_inactive_package_if_it_is_latest_assignment(): void
    {
        $this->grantLessonPackageBindPermission();
        $student = $this->makeStudent();
        $inactive = $this->makePackage(['name' => 'Бывший', 'is_active' => false]);
        $this->assignPackage($student, $inactive);
        $template = $this->makePackageTemplate();

        $this->post(route('contracts.store'), [
            'creation_mode'        => Contract::CREATION_MODE_TEMPLATE,
            'user_id'              => $student->id,
            'contract_template_id' => $template->id,
            'lesson_package_id'    => $inactive->id,
        ])->assertSessionHasNoErrors();

        $this->assertSame($inactive->id, (int) Contract::query()->firstOrFail()->lesson_package_id);
    }

    private function makeStudent(): User
    {
        return User::factory()->create([
            'partner_id' => $this->partner->id,
            'is_enabled' => 1,
            'email'      => 'bind-'.uniqid('', true).'@example.test',
        ]);
    }

    /**
     * @param  array<string, mixed>  $attrs
     */
    private function makePackage(array $attrs = []): LessonPackage
    {
        return LessonPackage::factory()->forPartner((int) $this->partner->id)->create(array_merge([
            'name'                    => 'Каталог '.uniqid('', true),
            'is_active'               => true,
            'price_cents'             => 10000,
            'lessons_per_week'        => 2,
            'lessons_per_month'       => 8,
            'lesson_duration_minutes' => 45,
            'lesson_price_cents'      => 1250,
        ], $attrs));
    }

    private function assignPackage(User $student, LessonPackage $package, $createdAt = null): UserLessonPackage
    {
        $ulp = UserLessonPackage::query()->create([
            'user_id'            => $student->id,
            'lesson_package_id'  => $package->id,
            'starts_at'          => null,
            'ends_at'            => null,
            'lessons_total'      => 8,
            'lessons_remaining'  => 8,
            'fee_amount_cents'   => (int) $package->price_cents,
            'is_paid'            => false,
            'created_by'         => $this->user->id,
        ]);

        if ($createdAt !== null) {
            $ulp->forceFill(['created_at' => $createdAt, 'updated_at' => $createdAt])->save();
        }

        return $ulp;
    }

    private function makePackageTemplate()
    {
        return $this->createContractTemplateWithVersion([], [
            'fields_schema' => [
                ['key' => 'parent_full_name', 'label' => 'ФИО', 'required' => true],
                ['key' => 'package_name', 'label' => 'Абонемент', 'required' => false],
                ['key' => 'package_price', 'label' => 'Цена', 'required' => false],
            ],
        ]);
    }
}
