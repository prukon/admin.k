<?php

namespace Tests\Unit\Services;

use App\Models\Contract;
use App\Models\LessonPackage;
use App\Services\Contracts\ContractLessonPackageBinder;
use App\Support\Money;
use Tests\TestCase;

class ContractLessonPackageBinderTest extends TestCase
{
    /** @test */
    public function snapshot_keeps_nullable_contract_fields_empty_instead_of_zero(): void
    {
        $package = new LessonPackage([
            'name'                    => 'Годовой',
            'price_cents'             => 150000,
            'lessons_per_week'        => 2,
            'lesson_duration_minutes' => 45,
            'lesson_price_cents'      => null,
        ]);

        $snapshot = app(ContractLessonPackageBinder::class)->snapshot($package);

        $this->assertSame('Годовой', $snapshot['name']);
        $this->assertSame(150000, $snapshot['price_cents']);
        $this->assertSame(2, $snapshot['lessons_per_week']);
        $this->assertArrayNotHasKey('lessons_per_month', $snapshot);
        $this->assertSame(45, $snapshot['lesson_duration_minutes']);
        $this->assertNull($snapshot['lesson_price_cents']);
    }

    /** @test */
    public function placeholder_values_format_money_with_rub_suffix_and_leave_nulls_blank(): void
    {
        $binder = app(ContractLessonPackageBinder::class);

        $values = $binder->placeholderValues([
            'name'                    => 'Годовой',
            'price_cents'             => 123456,
            'lessons_per_week'        => 2,
            'lesson_duration_minutes' => 45,
            'lesson_price_cents'      => 25000,
        ]);

        $this->assertSame('Годовой', $values[ContractLessonPackageBinder::KEY_NAME]);
        $this->assertSame(Money::formatRub(123456).' руб.', $values[ContractLessonPackageBinder::KEY_PRICE]);
        $this->assertSame('2', $values[ContractLessonPackageBinder::KEY_LESSONS_PER_WEEK]);
        $this->assertSame('45', $values[ContractLessonPackageBinder::KEY_DURATION_MINUTES]);
        $this->assertSame(Money::formatRub(25000).' руб.', $values[ContractLessonPackageBinder::KEY_LESSON_PRICE]);
        $this->assertStringEndsWith(' руб.', $values[ContractLessonPackageBinder::KEY_PRICE]);
        $this->assertStringEndsWith(' руб.', $values[ContractLessonPackageBinder::KEY_LESSON_PRICE]);
    }

    /** @test */
    public function placeholder_values_for_empty_snapshot_are_blank_keys(): void
    {
        $values = app(ContractLessonPackageBinder::class)->placeholderValuesForContract(new Contract());

        foreach (ContractLessonPackageBinder::placeholderKeys() as $key) {
            $this->assertArrayHasKey($key, $values);
            $this->assertSame('', $values[$key]);
        }
    }

    /** @test */
    public function option_label_marks_only_assigned_package(): void
    {
        $binder = app(ContractLessonPackageBinder::class);

        $this->assertSame(
            'Базовый (установлен)',
            $binder->optionLabel(['name' => 'Базовый', 'is_assigned' => true])
        );
        $this->assertSame(
            'Базовый',
            $binder->optionLabel(['name' => 'Базовый', 'is_assigned' => false])
        );
    }
}
