<?php

declare(strict_types=1);

namespace App\Services\Schedule\TrainerSalary;

use InvalidArgumentException;

final class TrainerSalarySchemeRegistry
{
    /**
     * @param list<TrainerSalaryScheme> $schemes
     */
    public function __construct(
        private readonly array $schemes,
    ) {
    }

    public function get(string $code): TrainerSalaryScheme
    {
        foreach ($this->schemes as $scheme) {
            if ($scheme->code() === $code) {
                return $scheme;
            }
        }

        throw new InvalidArgumentException('Неизвестная схема расчёта ЗП тренеров: ' . $code);
    }

    /**
     * @return list<TrainerSalaryScheme>
     */
    public function all(): array
    {
        return $this->schemes;
    }

    /**
     * @return list<string>
     */
    public function permissionNames(): array
    {
        $names = [];
        foreach ($this->schemes as $scheme) {
            $names[] = $scheme->permissionName();
        }

        return $names;
    }
}
