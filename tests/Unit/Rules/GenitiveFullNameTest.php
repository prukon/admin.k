<?php

declare(strict_types=1);

namespace Tests\Unit\Rules;

use App\Rules\GenitiveFullName;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

final class GenitiveFullNameTest extends TestCase
{
    public function test_rejects_surname_only_two_words_and_short_three_words(): void
    {
        foreach (['Иванова', 'Ивана', 'Иванова Ивана', 'а б в', 'Ли Я Юнов'] as $value) {
            $validator = Validator::make(
                ['name' => $value],
                ['name' => ['nullable', 'string', new GenitiveFullName]],
            );

            $this->assertTrue($validator->fails(), $value);
            $this->assertSame(GenitiveFullName::MESSAGE, $validator->errors()->first('name'));
        }
    }

    public function test_accepts_three_words_including_collapsed_spaces_and_empty(): void
    {
        foreach (['Иванова Ивана Ивановича', 'Иванова  Ивана   Ивановича', 'Ли Ян Юнов', '', null] as $value) {
            $validator = Validator::make(
                ['name' => $value],
                ['name' => ['nullable', 'string', new GenitiveFullName]],
            );

            $this->assertFalse($validator->fails(), (string) $value);
        }
    }
}
