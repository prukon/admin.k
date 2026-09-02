<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\SchoolLeads;

/**
 * AJAX JSON-контракт модалки инструкции: 200 instruction_url, 422 errors[field],
 * кастомный номер, omit_phone, без записи в БД.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class SchoolLeadLandingInstructionPreviewAjaxContractFeatureTest extends SchoolLeadLandingInstructionPreviewTestCase
{
    public function test_ajax_omit_phone_returns_instruction_url_without_phone_query(): void
    {
        $this->actingAsLandingViewer();
        $this->widgetWithSlug();

        $expected = route('lead.instruction', ['landingSlug' => 'crm-instr-school']);

        $this->postJson($this->previewUrl(), [
            'omit_phone' => 1,
            'phone' => '+7 (911) 555-66-77',
        ], $this->ajaxHeaders())
            ->assertOk()
            ->assertJsonPath('instruction_url', $expected)
            ->assertJsonMissingPath('errors');

        $this->assertStringNotContainsString('phone=', $expected);
    }

    public function test_ajax_custom_phone_returns_instruction_url_with_normalized_query(): void
    {
        $this->actingAsLandingViewer();
        $this->widgetWithSlug();

        $expected = route('lead.instruction', [
            'landingSlug' => 'crm-instr-school',
            'phone' => '79115556677',
        ]);

        $this->postJson($this->previewUrl(), [
            'omit_phone' => 0,
            'phone' => '+7 (911) 555-66-77',
        ], $this->ajaxHeaders())
            ->assertOk()
            ->assertJsonPath('instruction_url', $expected);

        $this->assertStringContainsString('phone=79115556677', $expected);
    }

    public function test_ajax_empty_phone_when_not_omitted_returns_422_under_phone(): void
    {
        $this->actingAsLandingViewer();
        $this->widgetWithSlug();

        $this->postJson($this->previewUrl(), [
            'omit_phone' => 0,
            'phone' => '',
        ], $this->ajaxHeaders())
            ->assertStatus(422)
            ->assertJsonValidationErrors(['phone'])
            ->assertJsonPath('errors.phone.0', 'Укажите номер телефона.')
            ->assertJsonMissingValidationErrors(['omit_phone']);
    }

    public function test_ajax_invalid_phone_returns_422_under_phone(): void
    {
        $this->actingAsLandingViewer();
        $this->widgetWithSlug();

        $this->postJson($this->previewUrl(), [
            'omit_phone' => 0,
            'phone' => '12345',
        ], $this->ajaxHeaders())
            ->assertStatus(422)
            ->assertJsonValidationErrors(['phone'])
            ->assertJsonPath('errors.phone.0', 'Укажите корректный номер телефона.');
    }

    public function test_ajax_without_slug_returns_422_under_landing_slug(): void
    {
        $this->actingAsLandingViewer();
        app(\App\Services\PartnerWidgetService::class)->ensureForPartner((int) $this->partner->id)
            ->update(['landing_slug' => null]);

        $this->postJson($this->previewUrl(), [
            'omit_phone' => 1,
        ], $this->ajaxHeaders())
            ->assertStatus(422)
            ->assertJsonPath('errors.landing_slug.0', 'Сначала сохраните адрес страницы.');
    }

    public function test_ajax_preview_does_not_persist_phone_on_partner_or_admins(): void
    {
        $this->actingAsLandingViewer();
        $this->widgetWithSlug();
        $this->partner->update(['phone' => '+7 (900) 000-00-00']);
        $admin = $this->createUserWithRole('admin', $this->partner, [
            'phone' => '79110000000',
            'email' => 'preview-persist-'.uniqid('', true).'@example.test',
        ]);

        $this->postJson($this->previewUrl(), [
            'omit_phone' => 0,
            'phone' => '+7 (911) 555-66-77',
        ], $this->ajaxHeaders())->assertOk();

        $this->assertSame('+7 (900) 000-00-00', $this->partner->fresh()->phone);
        $this->assertSame('+79110000000', $admin->fresh()->phone);
    }

    public function test_ajax_eight_prefix_phone_normalizes_to_seven(): void
    {
        $this->actingAsLandingViewer();
        $this->widgetWithSlug();

        $this->postJson($this->previewUrl(), [
            'omit_phone' => 0,
            'phone' => '89115556677',
        ], $this->ajaxHeaders())
            ->assertOk()
            ->assertJsonPath(
                'instruction_url',
                route('lead.instruction', [
                    'landingSlug' => 'crm-instr-school',
                    'phone' => '79115556677',
                ])
            );
    }
}
