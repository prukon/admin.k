<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\SchoolLeads;

/**
 * Native POST модалки инструкции без X-Requested-With:
 * успех → 302 на HTML-инструкцию (не пустой 200);
 * ошибка → 302 назад с errors[field].
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class SchoolLeadLandingInstructionPreviewNonAjaxSafetyNetFeatureTest extends SchoolLeadLandingInstructionPreviewTestCase
{
    public function test_native_post_with_phone_redirects_to_instruction_and_is_not_empty_200(): void
    {
        $this->actingAsLandingViewer();
        $this->widgetWithSlug();

        $expected = route('lead.instruction', [
            'landingSlug' => 'crm-instr-school',
            'phone' => '79115556677',
        ]);

        $response = $this->from(route('admin.school-leads.landing'))
            ->post($this->previewUrl(), [
                'omit_phone' => 0,
                'phone' => '+7 (911) 555-66-77',
            ]);

        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertNotSame(200, $response->getStatusCode(), 'Native POST не должен отдавать пустой/JSON 200');
        $response->assertStatus(302);
        $response->assertRedirect($expected);
        $response->assertSessionHasNoErrors();
    }

    public function test_native_post_with_omit_phone_redirects_to_instruction_without_phone(): void
    {
        $this->actingAsLandingViewer();
        $this->widgetWithSlug();

        $expected = route('lead.instruction', ['landingSlug' => 'crm-instr-school']);

        $this->from(route('admin.school-leads.landing'))
            ->post($this->previewUrl(), [
                'omit_phone' => 1,
            ])
            ->assertStatus(302)
            ->assertRedirect($expected);

        $this->assertStringNotContainsString('phone=', $expected);
    }

    public function test_native_post_without_omit_checkbox_still_requires_phone(): void
    {
        $this->actingAsLandingViewer();
        $this->widgetWithSlug();

        $this->from(route('admin.school-leads.landing'))
            ->post($this->previewUrl(), [
                'phone' => '',
            ])
            ->assertStatus(302)
            ->assertRedirect(route('admin.school-leads.landing'))
            ->assertSessionHasErrors(['phone']);

        $this->assertSame('Укажите номер телефона.', session('errors')->first('phone'));
    }

    public function test_native_post_invalid_phone_redirects_with_phone_error_not_empty_200(): void
    {
        $this->actingAsLandingViewer();
        $this->widgetWithSlug();

        $response = $this->from(route('admin.school-leads.landing'))
            ->post($this->previewUrl(), [
                'phone' => '12345',
            ]);

        $this->assertNotSame(200, $response->getStatusCode());
        $response->assertStatus(302)
            ->assertRedirect(route('admin.school-leads.landing'))
            ->assertSessionHasErrors(['phone']);
        $this->assertSame('Укажите корректный номер телефона.', session('errors')->first('phone'));
    }

    public function test_native_post_without_slug_redirects_or_returns_422_with_landing_slug_error(): void
    {
        $this->actingAsLandingViewer();
        app(\App\Services\PartnerWidgetService::class)->ensureForPartner((int) $this->partner->id)
            ->update(['landing_slug' => null]);

        $response = $this->from(route('admin.school-leads.landing'))
            ->post($this->previewUrl(), [
                'omit_phone' => 1,
            ]);

        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertNotSame(200, $response->getStatusCode());
        $this->assertContains($response->getStatusCode(), [302, 422]);
        if ($response->getStatusCode() === 302) {
            $response->assertRedirect(route('admin.school-leads.landing'));
            $response->assertSessionHasErrors(['landing_slug']);
        } else {
            $response->assertJsonPath('errors.landing_slug.0', 'Сначала сохраните адрес страницы.');
        }
    }
}
