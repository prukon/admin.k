<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\SettingPricesRequirePackageForPositivePrice;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

final class SettingPricesRequirePackageForPositivePriceTest extends TestCase
{
    public function test_rejects_new_positive_price_without_package(): void
    {
        $v = Validator::make(['x' => 1], ['x' => 'integer']);
        SettingPricesRequirePackageForPositivePrice::rejectIfMissingPackage(
            $v,
            1500,
            true,
            null,
            null,
            null,
            'usersPrice.0.lesson_package_id'
        );

        $this->assertTrue($v->errors()->has('usersPrice.0.lesson_package_id'));
        $this->assertSame(
            SettingPricesRequirePackageForPositivePrice::MESSAGE,
            $v->errors()->first('usersPrice.0.lesson_package_id')
        );
    }

    public function test_rejects_changing_legacy_price_without_package(): void
    {
        $v = Validator::make(['x' => 1], ['x' => 'integer']);
        SettingPricesRequirePackageForPositivePrice::rejectIfMissingPackage(
            $v,
            2000,
            true,
            null,
            150000,
            null,
            'usersPrice.0.lesson_package_id'
        );

        $this->assertTrue($v->errors()->has('usersPrice.0.lesson_package_id'));
        $this->assertSame(
            SettingPricesRequirePackageForPositivePrice::MESSAGE,
            $v->errors()->first('usersPrice.0.lesson_package_id')
        );
    }

    public function test_allows_positive_price_when_package_is_present(): void
    {
        $v = Validator::make(['x' => 1], ['x' => 'integer']);
        SettingPricesRequirePackageForPositivePrice::rejectIfMissingPackage(
            $v,
            4500,
            true,
            12,
            0,
            null,
            'usersPrice.0.lesson_package_id'
        );

        $this->assertFalse($v->errors()->has('usersPrice.0.lesson_package_id'));
    }

    public function test_allows_unchanged_legacy_price_without_package(): void
    {
        $v = Validator::make(['x' => 1], ['x' => 'integer']);
        SettingPricesRequirePackageForPositivePrice::rejectIfMissingPackage(
            $v,
            16800,
            true,
            null,
            1680000,
            null,
            'prices.0.lesson_package_id'
        );

        $this->assertFalse($v->errors()->has('prices.0.lesson_package_id'));
    }

    public function test_allows_zero_price_without_package(): void
    {
        $v = Validator::make(['x' => 1], ['x' => 'integer']);
        SettingPricesRequirePackageForPositivePrice::rejectIfMissingPackage(
            $v,
            0,
            true,
            null,
            100000,
            null,
            'usersPrice.0.lesson_package_id'
        );

        $this->assertFalse($v->errors()->has('usersPrice.0.lesson_package_id'));
    }

    public function test_omitted_package_key_keeps_existing_package(): void
    {
        $v = Validator::make(['x' => 1], ['x' => 'integer']);
        SettingPricesRequirePackageForPositivePrice::rejectIfMissingPackage(
            $v,
            2000,
            false,
            null,
            150000,
            9,
            'prices.0.lesson_package_id'
        );

        $this->assertFalse($v->errors()->has('prices.0.lesson_package_id'));
    }
}
