<?php

namespace App\Services\Contracts;

/**
 * Системные тексты письма приглашения заполнить договор (если в версии шаблона email_* = null).
 */
final class ContractTemplateEmailDefaults
{
    public const PLACEHOLDER_CHILD_FULL_NAME = '{{child_full_name}}';

    public const PLACEHOLDER_PARTNER_NAME = '{{partner_name}}';

    public const PLACEHOLDER_DOCUMENTS_URL = '{{documents_url}}';

    public const PLACEHOLDER_FILL_DEADLINE = '{{fill_deadline}}';

    public const PLACEHOLDER_CONTRACT_ID = '{{contract_id}}';

    public static function subject(): string
    {
        return 'Договор для ' . self::PLACEHOLDER_CHILD_FULL_NAME . ' — в личном кабинете | KidsCRM.online';
    }

    public static function bodyHtml(): string
    {
        return '<p>Здравствуйте!</p>'
            . '<p>В личном кабинете <strong>' . self::PLACEHOLDER_PARTNER_NAME . '</strong> для <strong>'
            . self::PLACEHOLDER_CHILD_FULL_NAME . '</strong> подготовлен договор.</p>'
            . '<ol>'
            . '<li>Откройте договор в кабинете, заполните поля в простой форме — данные сами подставятся в текст договора.</li>'
            . '<li>Подпишите договор прямо в личном кабинете.</li>'
            . '</ol>'
            . '<p><a href="' . self::PLACEHOLDER_DOCUMENTS_URL . '">Открыть «Мои документы»</a></p>'
            . '<p>Пожалуйста, заполните до <strong>' . self::PLACEHOLDER_FILL_DEADLINE . '</strong>.</p>'
            . '<p style="color:#6c757d;font-size:0.9em;">Номер договора в системе: ' . self::PLACEHOLDER_CONTRACT_ID
            . ' — пригодится, если напишете нам в поддержку.</p>'
            . '<p>С уважением,<br>' . self::PLACEHOLDER_PARTNER_NAME . '</p>';
    }

    /**
     * @return list<string>
     */
    public static function placeholderTokens(): array
    {
        return [
            self::PLACEHOLDER_CHILD_FULL_NAME,
            self::PLACEHOLDER_PARTNER_NAME,
            self::PLACEHOLDER_DOCUMENTS_URL,
            self::PLACEHOLDER_FILL_DEADLINE,
            self::PLACEHOLDER_CONTRACT_ID,
        ];
    }
}
