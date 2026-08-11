<?php

namespace Database\Factories;

use App\Models\ParentProfile;
use App\Models\Partner;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ParentProfile>
 */
class ParentProfileFactory extends Factory
{
    protected $model = ParentProfile::class;

    public function definition(): array
    {
        $lastname = fake()->lastName();
        $firstname = fake()->firstName();
        // По умолчанию без отчества: иначе тесты с точным ФИО флакают (~50%).
        // Нужное отчество задавайте явно в create([...]).
        $middlename = null;

        return [
            'partner_id' => Partner::factory(),
            'lastname' => $lastname,
            'firstname' => $firstname,
            'middlename' => $middlename,
            'full_name_genitive' => fake()->boolean(80)
                ? $this->primitiveParentFullNameGenitive($lastname, $firstname, $middlename)
                : null,
            'phone' => null,
            'email' => null,
        ];
    }

    /**
     * Примитивное «склонение» для тестовых данных (не морфология).
     */
    private function primitiveParentFullNameGenitive(string $lastname, string $firstname, ?string $middlename): string
    {
        return trim(collect([
            $this->primitiveGenitiveNamePart($lastname, 'lastname'),
            $this->primitiveGenitiveNamePart($firstname, 'firstname'),
            filled($middlename) ? $this->primitiveGenitiveNamePart((string) $middlename, 'middlename') : null,
        ])->filter()->implode(' '));
    }

    /**
     * @param  'lastname'|'firstname'|'middlename'  $part
     */
    private function primitiveGenitiveNamePart(string $value, string $part): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        $lower = mb_strtolower($value);

        if ($part === 'middlename') {
            if (str_ends_with($lower, 'ич')) {
                return $value . 'а';
            }
            if (str_ends_with($lower, 'на')) {
                return mb_substr($value, 0, -1) . 'ы';
            }
        }

        $lastChar = mb_substr($lower, -1);

        return match ($lastChar) {
            'а' => mb_substr($value, 0, -1) . 'ы',
            'я' => mb_substr($value, 0, -1) . 'и',
            'й' => mb_substr($value, 0, -1) . 'я',
            default => $value . 'а',
        };
    }
}
