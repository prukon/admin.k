<?php

namespace Tests\Feature\Phone;

use App\Support\RuPhone;
use Tests\Feature\Phone\Concerns\InteractsWithPhoneInput;
use Tests\TestCase;

/**
 * Централизованная маска телефона: единый partial, локальный Inputmask, отсутствие дублирующей разметки.
 */
final class PhoneInputMaskCentralizationFeatureTest extends TestCase
{
    use InteractsWithPhoneInput;

    public function test_phone_input_partial_renders_standard_tel_field_with_mask_classes(): void
    {
        $digits = '79161234567';
        $html = view('includes.fields.phone-input', [
            'name'  => 'phone',
            'id'    => 'test-phone',
            'value' => $digits,
        ])->render();

        $this->assertStringContainsString('type="tel"', $html);
        $this->assertStringContainsString('js-phone-mask', $html);
        $this->assertStringContainsString('placeholder="+7 (___) ___-__-__"', $html);
        $this->assertStringContainsString('value="' . RuPhone::formatForInput($digits) . '"', $html);
        $this->assertStringContainsString('autocomplete="tel"', $html);
    }

    public function test_phone_input_partial_supports_unmask_and_contract_fill_classes(): void
    {
        $html = view('includes.fields.phone-input', [
            'name'         => 'signer_phone',
            'value'        => '79001112233',
            'unmask'       => true,
            'contractFill' => true,
        ])->render();

        $this->assertStringContainsString('js-phone-mask-unmask', $html);
        $this->assertStringContainsString('js-contract-fill-phone', $html);
    }

    public function test_blade_sources_do_not_contain_standalone_phone_inputs_or_direct_inputmask_calls(): void
    {
        $violations = $this->findBladeViolationsForPhoneCentralization();

        $this->assertSame(
            [],
            $violations,
            "Найдены blade-файлы с неконсистентной разметкой телефона:\n" . implode("\n", $violations)
        );
    }

    public function test_all_known_phone_surfaces_use_phone_input_partial(): void
    {
        $sources = $this->bladeSourcesUsingPhoneInputPartial();

        $required = [
            'account/myDocuments.blade.php',
            'account/organizations.blade.php',
            'account/partials/contract-fill-content.blade.php',
            'account/partials/contract-fill-field.blade.php',
            'account/users.blade.php',
            'admin/trainers/index.blade.php',
            'admin/users/_parent_form.blade.php',
            'auth/phone-change-strong.blade.php',
            'auth/two-factor-phone.blade.php',
            'contracts/show.blade.php',
            'includes/modal/createPartner.blade.php',
            'includes/modal/createUser.blade.php',
            'includes/modal/editPartner.blade.php',
            'includes/modal/editUser.blade.php',
            'includes/modal/order.blade.php',
            'landing/partner-lead.blade.php',
            'tinkoff/partners/show.blade.php',
            'widget/school-lead-form.blade.php',
        ];

        foreach ($required as $path) {
            $this->assertContains($path, $sources, "Ожидался @include phone-input в {$path}");
        }
    }

    public function test_phone_inputmask_scripts_are_registered_once_in_layouts(): void
    {
        $adminLayout = (string) file_get_contents(resource_path('views/layouts/admin2.blade.php'));
        $appLayout = (string) file_get_contents(resource_path('views/layouts/app.blade.php'));
        $landingLayout = (string) file_get_contents(resource_path('views/layouts/landingPage.blade.php'));

        $this->assertStringContainsString("includes.scripts.phone-inputmask", $adminLayout);
        $this->assertStringContainsString("includes.scripts.phone-inputmask", $appLayout);
        $this->assertStringContainsString("includes.scripts.phone-inputmask", $landingLayout);
        $this->assertStringContainsString('requireJquery', $appLayout);
    }

    public function test_phone_inputmask_init_exposes_phone_input_mask_api(): void
    {
        $init = (string) file_get_contents(
            resource_path('views/includes/scripts/phone-inputmask-init.blade.php')
        );

        $this->assertStringContainsString('window.PhoneInputMask', $init);
        $this->assertStringContainsString("var PHONE_MASK = '+7 (999) 999-99-99'", $init);
        $this->assertStringContainsString('.js-phone-mask', $init);
        $this->assertStringContainsString('phone-inputmask:refresh', $init);
    }
}
