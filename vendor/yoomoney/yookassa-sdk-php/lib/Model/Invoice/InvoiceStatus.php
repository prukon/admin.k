<?php

/*
 * The MIT License
 *
 * Copyright (c) 2024 "YooMoney", NBСO LLC
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */

namespace YooKassa\Model\Invoice;

use YooKassa\Common\AbstractEnum;

/**
 * Класс, представляющий модель InvoiceStatus.
 *
 * Статус счета.
 *
 * Возможные значения:
 * - `pending` — счет создан и ожидает успешной оплаты;
 * - `succeeded` — счет успешно оплачен, есть связанный платеж в статусе `succeeded` (финальный и неизменяемый статус для платежей в одну стадию);
 * - `canceled` — вы отменили счет, успешный платеж по нему не поступил или был отменен (при оплате в две стадии) либо истек срок действия счета (финальный и неизменяемый статус).
 *
 * Подробнее про [жизненный цикл счета](https://yookassa.ru/developers/payment-acceptance/scenario-extensions/invoices/basics#invoice-status)
 *
 * @category Class
 * @package  YooKassa\Model
 * @author   cms@yoomoney.ru
 * @link     https://yookassa.ru/developers/api
 */
class InvoiceStatus extends AbstractEnum
{
    /** Счет создан и ожидает успешной оплаты */
    public const PENDING = 'pending';
    /** Счет успешно оплачен, есть связанный платеж в статусе ~`succeeded` */
    public const SUCCEEDED = 'succeeded';
    /** Вы отменили счет, успешный платеж по нему не поступил или был отменен */
    public const CANCELED = 'canceled';

    /**
     * Возвращает список доступных значений
     * @return string[]
     */
    protected static array $validValues = [
        self::PENDING => true,
        self::SUCCEEDED => true,
        self::CANCELED => true
    ];
}

