<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\SettingPrices;

use App\Models\LessonPackage;

/**
 * Фикстура: оплаченный месяц без абонемента → предоплата.
 */
abstract class SettingPricesPaidEmptyPrepaidAttachTestCase extends SettingPricesFlexibleReplaceTestCase
{
    protected LessonPackage $noSchedule;

    protected LessonPackage $postpay;

    protected function setUp(): void
    {
        parent::setUp();

        $this->grantLessonPackageTypePermissions($this->user, ['postpay']);

        $this->noSchedule = LessonPackage::factory()->forPartner((int) $this->partner->id)->create([
            'name' => 'Разовое',
            'price_cents' => 150000,
            'schedule_type' => LessonPackage::SCHEDULE_TYPE_NO_SCHEDULE,
            'lessons_count' => 1,
            'is_active' => true,
        ]);
        $this->postpay = LessonPackage::factory()->forPartner((int) $this->partner->id)->postpay()->create([
            'name' => 'Постоплата attach',
            'price_cents' => 80000,
            'is_active' => true,
        ]);
    }
}
