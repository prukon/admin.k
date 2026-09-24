<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\User;
use Tests\TestCase;

/**
 * full_name остаётся «фамилия имя». Отчество — только в fullNameWithPatronymic().
 */
final class UserFullNameWithPatronymicTest extends TestCase
{
    public function test_full_name_stays_lastname_and_name(): void
    {
        $user = new User([
            'lastname'   => 'Иванов',
            'name'       => 'Иван',
            'middlename' => 'Иванович',
        ]);

        $this->assertSame('Иванов Иван', $user->full_name);
        $this->assertNotContains('full_name_with_patronymic', $user->getAppends());
        $this->assertNotContains('fullNameWithPatronymic', $user->getAppends());
    }

    public function test_full_name_with_patronymic_joins_non_empty_parts(): void
    {
        $user = new User([
            'lastname'   => '  Иванов ',
            'name'       => 'Иван',
            'middlename' => ' Иванович ',
        ]);

        $this->assertSame('Иванов Иван Иванович', $user->fullNameWithPatronymic());

        $withoutMiddle = new User([
            'lastname'   => 'Петров',
            'name'       => 'Пётр',
            'middlename' => '   ',
        ]);

        $this->assertSame('Петров Пётр', $withoutMiddle->full_name);
        $this->assertSame('Петров Пётр', $withoutMiddle->fullNameWithPatronymic());

        $onlyMiddle = new User([
            'lastname'   => null,
            'name'       => '',
            'middlename' => 'Сергеевич',
        ]);

        $this->assertSame('', $onlyMiddle->full_name);
        $this->assertSame('Сергеевич', $onlyMiddle->fullNameWithPatronymic());
    }
}
