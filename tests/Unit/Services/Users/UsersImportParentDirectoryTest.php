<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Users;

use App\Services\Users\Import\UsersImportParentDirectory;
use PHPUnit\Framework\TestCase;

final class UsersImportParentDirectoryTest extends TestCase
{
    public function test_empty_file_fields_do_not_conflict_with_filled_directory(): void
    {
        $stored = [
            'lastname' => null,
            'firstname' => null,
            'middlename' => null,
            'phone' => '79627035846',
        ];
        $file = [
            'lastname' => 'Анзароков',
            'firstname' => 'Довлет',
            'middlename' => null,
            'phone' => null,
        ];

        $this->assertFalse(UsersImportParentDirectory::nonEmptyFieldsConflict($stored, $file));
    }

    public function test_two_non_empty_different_lastnames_conflict(): void
    {
        $this->assertTrue(UsersImportParentDirectory::nonEmptyFieldsConflict(
            ['lastname' => 'Иванова', 'firstname' => null, 'middlename' => null, 'phone' => null],
            ['lastname' => 'Петрова', 'firstname' => null, 'middlename' => null, 'phone' => null],
        ));
    }

    public function test_same_non_empty_values_do_not_conflict(): void
    {
        $this->assertFalse(UsersImportParentDirectory::nonEmptyFieldsConflict(
            ['lastname' => 'Иванова', 'firstname' => 'Анна', 'middlename' => null, 'phone' => '79001112233'],
            ['lastname' => 'Иванова', 'firstname' => 'Анна', 'middlename' => null, 'phone' => '79001112233'],
        ));
    }

    public function test_merge_fills_only_empty_directory_fields(): void
    {
        $merged = UsersImportParentDirectory::mergeFillEmpty(
            ['lastname' => null, 'firstname' => null, 'middlename' => null, 'phone' => '79627035846'],
            ['lastname' => 'Анзароков', 'firstname' => 'Довлет', 'middlename' => 'Иванович', 'phone' => '79990000000'],
        );

        $this->assertSame('Анзароков', $merged['lastname']);
        $this->assertSame('Довлет', $merged['firstname']);
        $this->assertSame('Иванович', $merged['middlename']);
        $this->assertSame('79627035846', $merged['phone']);
    }

    public function test_complementary_rows_merge_without_conflict(): void
    {
        $first = ['lastname' => 'Иванова', 'firstname' => null, 'middlename' => null, 'phone' => null];
        $second = ['lastname' => null, 'firstname' => 'Анна', 'middlename' => null, 'phone' => '79001112233'];

        $this->assertFalse(UsersImportParentDirectory::nonEmptyFieldsConflict($first, $second));

        $merged = UsersImportParentDirectory::mergeFillEmpty($first, $second);
        $this->assertSame('Иванова', $merged['lastname']);
        $this->assertSame('Анна', $merged['firstname']);
        $this->assertSame('79001112233', $merged['phone']);
    }

    public function test_blank_strings_are_treated_as_empty(): void
    {
        $this->assertNull(UsersImportParentDirectory::blankToNull(''));
        $this->assertNull(UsersImportParentDirectory::blankToNull('   '));
        $this->assertSame('Иванов', UsersImportParentDirectory::blankToNull(' Иванов '));
    }
}
