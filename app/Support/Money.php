<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Канон денежных сумм проекта: хранение и расчёты в копейках (int),
 * рубли — только на границе UI / внешних API (Robokassa, ЮKassa и т.п.).
 *
 * Округление до копейки — математическое (от 5 вверх по 3-му знаку дробной части рубля).
 * Скидка % — вариант А: сначала округляем сумму скидки, затем вычитаем из стоимости.
 */
final class Money
{
    /**
     * Рубли (строка/число) → копейки. Невалидное значение → null.
     * Допускаются пробелы-разделители тысяч и запятая как десятичный разделитель.
     */
    public static function toCents(string|int|float|null $rubles): ?int
    {
        if ($rubles === null) {
            return null;
        }

        if (is_int($rubles)) {
            if ($rubles < 0) {
                return null;
            }
            // Целое без точки трактуем как рубли (1500 → 150000 коп.).
            return $rubles * 100;
        }

        if (is_float($rubles)) {
            if (! is_finite($rubles) || $rubles < 0) {
                return null;
            }

            return (int) round($rubles * 100);
        }

        $v = trim(str_replace(["\xc2\xa0", ' '], '', (string) $rubles));
        $v = str_replace(',', '.', $v);
        if ($v === '') {
            return null;
        }
        if (! preg_match('/^\d+(\.\d{1,6})?$/', $v)) {
            return null;
        }

        $normalized = self::normalizeRubString($v);
        if ($normalized === null) {
            return null;
        }

        [$a, $b] = explode('.', $normalized, 2);

        return ((int) $a) * 100 + (int) $b;
    }

    /**
     * Как {@see toCents}, но при ошибке — исключение (для доверенных внутренних путей).
     */
    public static function toCentsOrFail(string|int|float|null $rubles): int
    {
        $cents = self::toCents($rubles);
        if ($cents === null) {
            throw new \InvalidArgumentException('Некорректная денежная сумма.');
        }

        return $cents;
    }

    /**
     * Копейки → строка рублей "0.00" (точка, ровно 2 знака) для API/форм провайдеров.
     */
    public static function fromCents(int $cents): string
    {
        if ($cents < 0) {
            throw new \InvalidArgumentException('Сумма в копейках не может быть отрицательной.');
        }

        $rub = intdiv($cents, 100);
        $kop = $cents % 100;

        return $rub . '.' . str_pad((string) $kop, 2, '0', STR_PAD_LEFT);
    }

    /**
     * Отображение для UI: целые рубли без копеек; `,xx` только если копейки ≠ 0.
     * Пример: 800000 → "8 000"; 800050 → "8 000,50".
     */
    public static function formatRub(int $cents, string $suffix = ''): string
    {
        if ($cents < 0) {
            throw new \InvalidArgumentException('Сумма в копейках не может быть отрицательной.');
        }

        $rub = intdiv($cents, 100);
        $kop = $cents % 100;
        $intPart = number_format($rub, 0, ',', ' ');

        if ($kop === 0) {
            $body = $intPart;
        } else {
            $body = $intPart . ',' . str_pad((string) $kop, 2, '0', STR_PAD_LEFT);
        }

        return $suffix === '' ? $body : $body . $suffix;
    }

    /**
     * Всегда 2 знака после запятой (админка выплат, сверки): "8 000,00".
     */
    public static function formatRubFixed(int $cents, string $suffix = ''): string
    {
        if ($cents < 0) {
            throw new \InvalidArgumentException('Сумма в копейках не может быть отрицательной.');
        }

        $rub = intdiv($cents, 100);
        $kop = $cents % 100;
        $body = number_format($rub, 0, ',', ' ')
            . ','
            . str_pad((string) $kop, 2, '0', STR_PAD_LEFT);

        return $suffix === '' ? $body : $body . $suffix;
    }

    /**
     * Вариант А: скидка = round(price × percent / 100) до копейки.
     *
     * @return int сумма скидки в копейках (0…priceCents)
     */
    public static function discountCents(int $priceCents, string|float|int $percent): int
    {
        if ($priceCents < 0) {
            throw new \InvalidArgumentException('Стоимость не может быть отрицательной.');
        }

        $p = self::normalizePercent($percent);
        if ($p === null || $p < 0 || $p > 100) {
            throw new \InvalidArgumentException('Процент скидки должен быть от 0 до 100.');
        }

        // Точный расчёт через целые: round(price * percent / 100).
        // percent может быть дробным (напр. 7.5) — умножаем на 10000 (percent×100) и делим на 10000*100.
        $percentScaled = (int) round($p * 100); // сотые доли процента (7.5% → 750)
        // discount = round(priceCents * percent / 100) = round(priceCents * percentScaled / 10000)
        $discount = (int) round(($priceCents * $percentScaled) / 10000);

        if ($discount < 0) {
            return 0;
        }
        if ($discount > $priceCents) {
            return $priceCents;
        }

        return $discount;
    }

    /**
     * К оплате после скидки (вариант А): price − discountCents(...).
     */
    public static function payableAfterDiscountCents(int $priceCents, string|float|int $percent): int
    {
        return $priceCents - self::discountCents($priceCents, $percent);
    }

    /**
     * Нормализация рублёвой строки до "N.NN" (математическое округление по 3-му знаку).
     * Совместимо с прежним PaymentOutSumNormalizer.
     */
    public static function normalizeRubString(string $value): ?string
    {
        $v = trim(str_replace(',', '.', $value));
        if ($v === '') {
            return null;
        }
        if (! preg_match('/^\d+(\.\d{1,6})?$/', $v)) {
            return null;
        }

        $a = $v;
        $b = '';
        if (str_contains($v, '.')) {
            [$a, $b] = explode('.', $v, 2);
        }

        $a = ltrim($a, '0');
        if ($a === '') {
            $a = '0';
        }

        $b = str_pad($b, 6, '0');
        $cents = (int) substr($b, 0, 2);
        $third = (int) substr($b, 2, 1);

        if ($third >= 5) {
            $cents++;
            if ($cents >= 100) {
                $cents = 0;
                $a = (string) ((int) $a + 1);
            }
        }

        return $a . '.' . str_pad((string) $cents, 2, '0', STR_PAD_LEFT);
    }

    private static function normalizePercent(string|float|int $percent): ?float
    {
        if (is_int($percent) || is_float($percent)) {
            return is_finite((float) $percent) ? (float) $percent : null;
        }

        $v = trim(str_replace(',', '.', (string) $percent));
        if ($v === '' || ! is_numeric($v)) {
            return null;
        }

        return (float) $v;
    }
}
