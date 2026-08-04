<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Postpay;

use App\Models\LessonPackage;
use App\Models\UserLessonPackage;

/**
 * Store/update шаблона postpay + запрет назначения в ULP.
 *
 * @see \Tests\Feature\Crm\LessonPackages\LessonPackagesNonAjaxSafetyNetFeatureTest
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class PostpayLessonPackageContractsFeatureTest extends PostpayTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPostpay(
            withScheduleView: false,
            withSetPricesView: false,
            withLessonPackagesView: true
        );
        $this->grantPermission('setPrices.packageAssignments.view');
    }

    public function test_store_postpay_ajax_returns_json_and_creates_template(): void
    {
        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('admin.lesson-packages.store'), [
                'name' => 'Постоплата AJAX',
                'schedule_type' => LessonPackage::SCHEDULE_TYPE_POSTPAY,
                'price' => 250,
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('lesson_packages', [
            'partner_id' => $this->partner->id,
            'name' => 'Постоплата AJAX',
            'schedule_type' => LessonPackage::SCHEDULE_TYPE_POSTPAY,
            'price_cents' => 25000,
            'duration_days' => 31,
            'lessons_count' => 1,
        ]);
    }

    public function test_store_postpay_non_ajax_redirects_and_creates_template_not_empty_200(): void
    {
        $response = $this->post(route('admin.lesson-packages.store'), [
            '_token' => csrf_token(),
            'name' => 'Постоплата non-AJAX',
            'schedule_type' => LessonPackage::SCHEDULE_TYPE_POSTPAY,
            'price' => 180,
        ]);

        $response->assertRedirect(route('admin.lesson-packages.index'));
        $this->assertNotSame(200, $response->getStatusCode());

        $this->assertDatabaseHas('lesson_packages', [
            'partner_id' => $this->partner->id,
            'name' => 'Постоплата non-AJAX',
            'schedule_type' => LessonPackage::SCHEDULE_TYPE_POSTPAY,
            'price_cents' => 18000,
        ]);
    }

    public function test_store_postpay_ajax_validation_returns_422(): void
    {
        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('admin.lesson-packages.store'), [
                'schedule_type' => LessonPackage::SCHEDULE_TYPE_POSTPAY,
            ])
            ->assertStatus(422)
            ->assertJsonStructure(['message', 'errors'])
            ->assertJsonValidationErrors(['name', 'price']);
    }

    public function test_update_postpay_non_ajax_redirects_and_updates_price(): void
    {
        $this->put(route('admin.lesson-packages.update', $this->postpayPackage), [
            '_token' => csrf_token(),
            'name' => $this->postpayPackage->name,
            'schedule_type' => LessonPackage::SCHEDULE_TYPE_POSTPAY,
            'price' => 620,
        ])
            ->assertRedirect(route('admin.lesson-packages.index'));

        $this->postpayPackage->refresh();
        $this->assertSame(62000, (int) $this->postpayPackage->price_cents);
        $this->assertSame(LessonPackage::SCHEDULE_TYPE_POSTPAY, $this->postpayPackage->schedule_type);
    }

    public function test_assignment_store_rejects_postpay_package_ajax_422(): void
    {
        $before = UserLessonPackage::query()->count();

        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('admin.lesson-packages.assignments.store'), [
                'user_id' => $this->student->id,
                'lesson_package_id' => $this->postpayPackage->id,
                'fee_amount' => '100.00',
                'team_id' => $this->team->id,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['lesson_package_id']);

        $this->assertSame($before, UserLessonPackage::query()->count());
    }

    public function test_assignment_store_rejects_postpay_package_non_ajax_redirects_with_errors(): void
    {
        $before = UserLessonPackage::query()->count();

        $this->from(route('admin.lesson-packages.assignments'))
            ->post(route('admin.lesson-packages.assignments.store'), [
                '_token' => csrf_token(),
                'user_id' => $this->student->id,
                'lesson_package_id' => $this->postpayPackage->id,
                'fee_amount' => '100.00',
                'team_id' => $this->team->id,
            ])
            ->assertStatus(302)
            ->assertSessionHasErrors(['lesson_package_id']);

        $this->assertSame($before, UserLessonPackage::query()->count());
    }
}
