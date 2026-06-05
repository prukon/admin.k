<?php

namespace App\Services\Contracts;

use App\Models\SchoolLead;
use Carbon\Carbon;

/**
 * Справочник рекомендуемых переменных DOCX и привязок к данным CRM.
 *
 * Один ключ на сущность: snake_case, например parent_full_name — и в Word, и в prefill_source.
 */
class ContractTemplateVariablePresets
{
    public const GROUP_PARENT  = 'parent';
    public const GROUP_CHILD   = 'child';
    public const GROUP_CONTRACT = 'contract';

    public const FILL_MODE_CRM    = 'crm';
    public const FILL_MODE_PARENT = 'parent';
    public const FILL_MODE_SYSTEM = 'system';

    /** Кастомные ключи из DOCX — после блока «Супруг(а)» (200). */
    public const FILL_SORT_DEFAULT_CUSTOM = 300;

    /**
     * Устаревшие ключи из старых DOCX → актуальный ключ в CRM.
     *
     * @return array<string, string> legacyKey => canonicalKey
     */
    public static function legacyFieldKeyMap(): array
    {
        return [
            'child_birth_year'  => ContractTemplatePrefillSources::CHILD_BIRTHDAY,
            'student_full_name' => ContractTemplatePrefillSources::CHILD_FULL_NAME,
        ];
    }

    public static function canonicalFieldKey(string $key): string
    {
        return self::legacyFieldKeyMap()[$key] ?? $key;
    }

    /**
     * Дублирует значения под старыми ключами плейсхолдеров в DOCX (до обновления Word-файла).
     *
     * @param array<string, string> $values
     * @return array<string, string>
     */
    public static function expandDocxPlaceholderValues(array $values): array
    {
        $expanded = $values;
        foreach (self::legacyFieldKeyMap() as $legacyKey => $canonicalKey) {
            if (array_key_exists($canonicalKey, $values) && !array_key_exists($legacyKey, $expanded)) {
                $expanded[$legacyKey] = $values[$canonicalKey];
            }
        }

        return $expanded;
    }

    /**
     * @return array<string, string>
     */
    public static function groupLabels(): array
    {
        return [
            self::GROUP_PARENT   => 'Родитель (заказчик)',
            self::GROUP_CHILD    => 'Ребёнок (ученик)',
            self::GROUP_CONTRACT => 'Договор',
        ];
    }

    /**
     * @return list<array{
     *     key: string,
     *     label: string,
     *     description: string,
     *     group: string,
     *     prefill_source: string|null,
     *     required_default: bool
     * }>
     */
    public static function recommended(): array
    {
        return [
            [
                'key'              => ContractTemplatePrefillSources::PARENT_FULL_NAME,
                'label'            => 'Родитель: ФИО',
                'description'      => 'Полное ФИО заказчика в тексте договора и реквизитах.',
                'group'            => self::GROUP_PARENT,
                'prefill_source'   => ContractTemplatePrefillSources::PARENT_FULL_NAME,
                'required_default' => true,
                'fill_sort_order'  => 14,
            ],
            [
                'key'              => ContractTemplatePrefillSources::PARENT_LASTNAME,
                'label'            => 'Родитель: фамилия',
                'description'      => 'Фамилия заказчика отдельным полем.',
                'group'            => self::GROUP_PARENT,
                'prefill_source'   => ContractTemplatePrefillSources::PARENT_LASTNAME,
                'required_default' => true,
                'fill_sort_order'  => 11,
            ],
            [
                'key'              => ContractTemplatePrefillSources::PARENT_FIRSTNAME,
                'label'            => 'Родитель: имя',
                'description'      => 'Имя заказчика отдельным полем.',
                'group'            => self::GROUP_PARENT,
                'prefill_source'   => ContractTemplatePrefillSources::PARENT_FIRSTNAME,
                'required_default' => true,
                'fill_sort_order'  => 12,
            ],
            [
                'key'              => ContractTemplatePrefillSources::PARENT_MIDDLENAME,
                'label'            => 'Родитель: отчество',
                'description'      => 'Отчество заказчика (если есть).',
                'group'            => self::GROUP_PARENT,
                'prefill_source'   => ContractTemplatePrefillSources::PARENT_MIDDLENAME,
                'required_default' => false,
                'fill_sort_order'  => 13,
            ],
            [
                'key'              => 'parent_passport',
                'label'            => 'Родитель: паспорт',
                'description'      => 'Серия и номер паспорта заказчика.',
                'group'            => self::GROUP_PARENT,
                'prefill_source'   => ContractTemplatePrefillSources::PARENT_PASSPORT,
                'required_default' => true,
                'fill_sort_order'  => 40,
            ],
            [
                'key'              => 'parent_passport_issued',
                'label'            => 'Родитель: паспорт, кем и когда выдан',
                'description'      => 'Орган выдачи и дата выдачи паспорта.',
                'group'            => self::GROUP_PARENT,
                'prefill_source'   => ContractTemplatePrefillSources::PARENT_PASSPORT_ISSUED,
                'required_default' => true,
                'fill_sort_order'  => 41,
            ],
            [
                'key'              => 'parent_address',
                'label'            => 'Родитель: адрес регистрации',
                'description'      => 'Адрес регистрации или проживания заказчика.',
                'group'            => self::GROUP_PARENT,
                'prefill_source'   => ContractTemplatePrefillSources::PARENT_ADDRESS,
                'required_default' => true,
                'fill_sort_order'  => 50,
            ],
            [
                'key'              => ContractTemplatePrefillSources::PARENT_PHONE,
                'label'            => 'Родитель: телефон',
                'description'      => 'Контактный телефон из профиля родителя.',
                'group'            => self::GROUP_PARENT,
                'prefill_source'   => ContractTemplatePrefillSources::PARENT_PHONE,
                'required_default' => true,
                'fill_sort_order'  => 20,
            ],
            [
                'key'              => ContractTemplatePrefillSources::PARENT_EMAIL,
                'label'            => 'Родитель: email',
                'description'      => 'Email для связи из профиля родителя.',
                'group'            => self::GROUP_PARENT,
                'prefill_source'   => ContractTemplatePrefillSources::PARENT_EMAIL,
                'required_default' => false,
                'fill_sort_order'  => 30,
            ],
            [
                'key'              => ContractTemplatePrefillSources::CHILD_FULL_NAME,
                'label'            => 'Ребёнок: ФИО',
                'description'      => 'Полное ФИО несовершеннолетнего ученика.',
                'group'            => self::GROUP_CHILD,
                'prefill_source'   => ContractTemplatePrefillSources::CHILD_FULL_NAME,
                'required_default' => true,
                'fill_sort_order'  => 14,
            ],
            [
                'key'              => ContractTemplatePrefillSources::CHILD_LASTNAME,
                'label'            => 'Ребёнок: фамилия',
                'description'      => 'Фамилия ученика (карточка users.lastname).',
                'group'            => self::GROUP_CHILD,
                'prefill_source'   => ContractTemplatePrefillSources::CHILD_LASTNAME,
                'required_default' => true,
                'fill_sort_order'  => 11,
            ],
            [
                'key'              => ContractTemplatePrefillSources::CHILD_FIRSTNAME,
                'label'            => 'Ребёнок: имя',
                'description'      => 'Имя ученика (карточка users.name).',
                'group'            => self::GROUP_CHILD,
                'prefill_source'   => ContractTemplatePrefillSources::CHILD_FIRSTNAME,
                'required_default' => true,
                'fill_sort_order'  => 12,
            ],
            [
                'key'              => ContractTemplatePrefillSources::CHILD_BIRTHDAY,
                'label'            => 'Ребёнок: дата рождения',
                'description'      => 'Дата рождения ученика (из карточки, формат дд.мм.гггг).',
                'group'            => self::GROUP_CHILD,
                'prefill_source'   => ContractTemplatePrefillSources::CHILD_BIRTHDAY,
                'required_default' => true,
                'fill_sort_order'  => 50,
            ],
            [
                'key'              => ContractTemplatePrefillSources::TEAM_TITLE,
                'label'            => 'Группа: название',
                'description'      => 'Название группы, если при создании договора выбрана группа.',
                'group'            => self::GROUP_CHILD,
                'prefill_source'   => ContractTemplatePrefillSources::TEAM_TITLE,
                'required_default' => false,
                'fill_sort_order'  => 52,
            ],
            [
                'key'              => 'contract_date',
                'label'            => 'Дата договора',
                'description'      => 'Дата заключения договора в тексте PDF.',
                'admin_hint'       => 'Подставляется автоматически при формировании PDF — текущая дата на момент генерации (дд.мм.гггг). Родитель не заполняет; предзаполнение из CRM не используется.',
                'group'            => self::GROUP_CONTRACT,
                'fill_mode'        => self::FILL_MODE_SYSTEM,
                'prefill_source'   => null,
                'required_default' => false,
            ],
            [
                'key'              => 'documents_url',
                'label'            => 'Ссылка на «Мои документы»',
                'description'      => 'URL раздела документов в личном кабинете. Подставляется в письмо и при генерации PDF, если указано в DOCX.',
                'admin_hint'       => 'Подставляется автоматически. Родитель не заполняет. Удобнее всего — в тексте email.',
                'group'            => self::GROUP_CONTRACT,
                'fill_mode'        => self::FILL_MODE_SYSTEM,
                'prefill_source'   => null,
                'required_default' => false,
            ],
            [
                'key'              => 'contract_id',
                'label'            => 'Номер договора',
                'description'      => 'ID договора в системе. Подставляется в письмо и в DOCX/PDF.',
                'admin_hint'       => 'Подставляется автоматически. Родитель не заполняет.',
                'group'            => self::GROUP_CONTRACT,
                'fill_mode'        => self::FILL_MODE_SYSTEM,
                'prefill_source'   => null,
                'required_default' => false,
            ],
            [
                'key'              => 'spouse_full_name',
                'label'            => 'Супруг(а): ФИО',
                'description'      => 'Контакт супруга для связи (приложение к договору).',
                'group'            => self::GROUP_CONTRACT,
                'prefill_source'   => null,
                'required_default' => false,
                'fill_sort_order'  => 200,
            ],
            [
                'key'              => 'spouse_phones',
                'label'            => 'Супруг(а): телефон',
                'description'      => 'Телефоны супруга для связи (приложение к договору).',
                'group'            => self::GROUP_CONTRACT,
                'prefill_source'   => null,
                'required_default' => false,
                'fill_sort_order'  => 201,
            ],
            [
                'key'              => 'trusted_person_1_fio',
                'label'            => 'Доверенное лицо 1: ФИО',
                'description'      => 'Кто может забрать ребёнка после занятий.',
                'group'            => self::GROUP_CONTRACT,
                'prefill_source'   => null,
                'required_default' => false,
                'fill_sort_order'  => 210,
            ],
            [
                'key'              => 'trusted_person_1_contacts',
                'label'            => 'Доверенное лицо 1: контакты',
                'description'      => 'Телефон и прочие контакты доверенного лица.',
                'group'            => self::GROUP_CONTRACT,
                'prefill_source'   => null,
                'required_default' => false,
                'fill_sort_order'  => 211,
            ],
        ];
    }

    /**
     * @return array<string, array{key: string, label: string, description: string, group: string, prefill_source: string|null, required_default: bool}>
     */
    public static function recommendedByKey(): array
    {
        $map = [];
        foreach (self::recommended() as $preset) {
            $map[$preset['key']] = $preset;
        }

        return $map;
    }

    public static function fillModeForKey(string $key): string
    {
        $key = self::canonicalFieldKey($key);

        if (DocxPlaceholderSupport::isSystemKey($key)) {
            return self::FILL_MODE_SYSTEM;
        }

        $preset = self::recommendedByKey()[$key] ?? null;
        if ($preset !== null) {
            $mode = $preset['fill_mode'] ?? null;
            if (is_string($mode) && $mode !== '') {
                return $mode;
            }

            if (($preset['prefill_source'] ?? null) !== null) {
                return self::FILL_MODE_CRM;
            }

            return self::FILL_MODE_PARENT;
        }

        return self::FILL_MODE_PARENT;
    }

    public static function isSystemFillField(string $key): bool
    {
        return self::fillModeForKey($key) === self::FILL_MODE_SYSTEM;
    }

    public static function adminHintForKey(string $key): ?string
    {
        $preset = self::recommendedByKey()[self::canonicalFieldKey($key)] ?? null;
        if ($preset === null) {
            return null;
        }

        $hint = trim((string) ($preset['admin_hint'] ?? ''));
        if ($hint !== '') {
            return $hint;
        }

        $description = trim((string) ($preset['description'] ?? ''));

        return $description !== '' ? $description : null;
    }

    public static function parentFormHintForKey(string $key): string
    {
        return 'Родитель заполняет это поле в форме договора. Предзаполнение из CRM недоступно.';
    }

    /**
     * @param array<int, array<string, mixed>> $schema
     * @return array<int, array<string, mixed>>
     */
    public static function schemaFieldsForParentForm(array $schema): array
    {
        $filtered = array_values(array_filter(
            $schema,
            static fn (array $field): bool => !self::isSystemFillField((string) ($field['key'] ?? '')),
        ));

        return self::expandSplitNameFieldsForParentForm($filtered);
    }

    /**
     * В форме кабинета родителя вместо одного «ФИО» показываем фамилию, имя и отчество
     * (как в профиле parents / users). Составные ключи для DOCX собираются при генерации PDF.
     *
     * @param array<int, array<string, mixed>> $schema
     * @return array<int, array<string, mixed>>
     */
    public static function expandSplitNameFieldsForParentForm(array $schema): array
    {
        $enriched = array_map(
            static fn (array $field): array => self::enrichField($field),
            $schema,
        );

        $byKey = [];
        foreach ($enriched as $field) {
            $key = self::canonicalFieldKey((string) ($field['key'] ?? ''));
            if ($key !== '') {
                $byKey[$key] = $field;
            }
        }

        $parentFullRequired = !empty($byKey[ContractTemplatePrefillSources::PARENT_FULL_NAME]['required']);
        $childFullRequired = !empty($byKey[ContractTemplatePrefillSources::CHILD_FULL_NAME]['required']);

        $needsParentParts = isset($byKey[ContractTemplatePrefillSources::PARENT_FULL_NAME])
            || isset($byKey[ContractTemplatePrefillSources::PARENT_LASTNAME])
            || isset($byKey[ContractTemplatePrefillSources::PARENT_FIRSTNAME])
            || isset($byKey[ContractTemplatePrefillSources::PARENT_MIDDLENAME]);

        $needsChildParts = isset($byKey[ContractTemplatePrefillSources::CHILD_FULL_NAME])
            || isset($byKey[ContractTemplatePrefillSources::CHILD_LASTNAME])
            || isset($byKey[ContractTemplatePrefillSources::CHILD_FIRSTNAME]);

        $out = [];
        foreach ($enriched as $field) {
            $key = self::canonicalFieldKey((string) ($field['key'] ?? ''));
            if ($needsParentParts && $key === ContractTemplatePrefillSources::PARENT_FULL_NAME) {
                continue;
            }
            if ($needsChildParts && $key === ContractTemplatePrefillSources::CHILD_FULL_NAME) {
                continue;
            }
            $out[] = $field;
        }

        $presentKeys = [];
        foreach ($out as $field) {
            $presentKeys[self::canonicalFieldKey((string) ($field['key'] ?? ''))] = true;
        }

        if ($needsParentParts) {
            foreach ([
                ContractTemplatePrefillSources::PARENT_LASTNAME,
                ContractTemplatePrefillSources::PARENT_FIRSTNAME,
                ContractTemplatePrefillSources::PARENT_MIDDLENAME,
            ] as $partKey) {
                if (!isset($presentKeys[$partKey])) {
                    $required = $partKey === ContractTemplatePrefillSources::PARENT_MIDDLENAME
                        ? false
                        : ($parentFullRequired || !empty($byKey[$partKey]['required']));
                    $out[] = self::makeSplitNameFormField($partKey, $required);
                }
            }
        }

        if ($needsChildParts) {
            foreach ([
                ContractTemplatePrefillSources::CHILD_LASTNAME,
                ContractTemplatePrefillSources::CHILD_FIRSTNAME,
            ] as $partKey) {
                if (!isset($presentKeys[$partKey])) {
                    $required = $childFullRequired || !empty($byKey[$partKey]['required']);
                    $out[] = self::makeSplitNameFormField($partKey, $required);
                }
            }
        }

        return $out;
    }

    /**
     * @param array<string, string> $prefill
     * @return array<string, string>
     */
    public static function applySplitNamePrefill(array $prefill): array
    {
        $parentFull = trim($prefill[ContractTemplatePrefillSources::PARENT_FULL_NAME] ?? '');
        if ($parentFull !== '') {
            $parts = SchoolLead::splitFullName($parentFull);
            foreach ([
                ContractTemplatePrefillSources::PARENT_LASTNAME  => 'lastname',
                ContractTemplatePrefillSources::PARENT_FIRSTNAME => 'firstname',
                ContractTemplatePrefillSources::PARENT_MIDDLENAME => 'middlename',
            ] as $targetKey => $partKey) {
                if (trim($prefill[$targetKey] ?? '') === '' && $parts[$partKey] !== '') {
                    $prefill[$targetKey] = $parts[$partKey];
                }
            }
        }

        $childFull = trim($prefill[ContractTemplatePrefillSources::CHILD_FULL_NAME] ?? '');
        if ($childFull !== '') {
            $parts = SchoolLead::splitFullName($childFull);
            if (trim($prefill[ContractTemplatePrefillSources::CHILD_LASTNAME] ?? '') === '' && $parts['lastname'] !== '') {
                $prefill[ContractTemplatePrefillSources::CHILD_LASTNAME] = $parts['lastname'];
            }
            if (trim($prefill[ContractTemplatePrefillSources::CHILD_FIRSTNAME] ?? '') === '' && $parts['firstname'] !== '') {
                $prefill[ContractTemplatePrefillSources::CHILD_FIRSTNAME] = $parts['firstname'];
            }
        }

        return $prefill;
    }

    /**
     * @param array<string, string> $values
     * @return array<string, string>
     */
    public static function composeNameFieldsForPdf(array $values): array
    {
        $parentFull = self::buildFullName(
            $values[ContractTemplatePrefillSources::PARENT_LASTNAME] ?? '',
            $values[ContractTemplatePrefillSources::PARENT_FIRSTNAME] ?? '',
            $values[ContractTemplatePrefillSources::PARENT_MIDDLENAME] ?? '',
        );
        if ($parentFull !== '') {
            $values[ContractTemplatePrefillSources::PARENT_FULL_NAME] = $parentFull;
        }

        $childFull = self::buildFullName(
            $values[ContractTemplatePrefillSources::CHILD_LASTNAME] ?? '',
            $values[ContractTemplatePrefillSources::CHILD_FIRSTNAME] ?? '',
        );
        if ($childFull !== '') {
            $values[ContractTemplatePrefillSources::CHILD_FULL_NAME] = $childFull;
        }

        return $values;
    }

    public static function buildFullName(string $lastname, string $firstname, string $middlename = ''): string
    {
        return trim(implode(' ', array_filter([
            trim($lastname),
            trim($firstname),
            trim($middlename),
        ], static fn (string $part): bool => $part !== '')));
    }

    /**
     * @return array<string, mixed>
     */
    private static function makeSplitNameFormField(string $key, bool $required): array
    {
        $field = [
            'key'      => $key,
            'required' => $required,
        ];

        $defaults = self::defaultsForKey($key);
        if ($defaults !== null) {
            $field['label'] = $defaults['label'];
            if ($defaults['prefill_source'] !== null) {
                $field['prefill_source'] = $defaults['prefill_source'];
            }
        }

        return self::enrichField($field);
    }

    /**
     * @param array{key?: string, label?: string, required?: bool, prefill_source?: string|null} $field
     * @return array{key: string, label: string, required: bool, prefill_source: string|null}
     */
    public static function enrichField(array $field): array
    {
        $originalKey = is_string($field['key'] ?? null) ? $field['key'] : '';
        $key = self::canonicalFieldKey($originalKey);
        if ($key === '') {
            return $field;
        }

        $field['key'] = $key;

        $defaults = self::defaultsForKey($key);
        if ($defaults === null) {
            return self::applyDefaultFillSortOrder($field);
        }

        $autoLabels = array_values(array_unique(array_filter([
            str_replace('_', ' ', $originalKey),
            str_replace('_', ' ', $key),
        ])));
        $currentLabel = trim((string) ($field['label'] ?? ''));

        if ($originalKey !== $key) {
            $field['label'] = $defaults['label'];
            $field['required'] = $defaults['required'];
            if ($defaults['prefill_source'] !== null) {
                $field['prefill_source'] = $defaults['prefill_source'];
            }
        } elseif ($currentLabel === '' || in_array($currentLabel, $autoLabels, true)) {
            $field['label'] = $defaults['label'];
            $field['required'] = $defaults['required'];
        }

        if (empty($field['prefill_source']) && $defaults['prefill_source'] !== null) {
            $field['prefill_source'] = $defaults['prefill_source'];
        }

        if (self::fillModeForKey($key) === self::FILL_MODE_SYSTEM) {
            $field['required'] = false;
            $field['prefill_source'] = null;
        }

        return self::applyDefaultFillSortOrder($field);
    }

    /**
     * @param array<string, mixed> $field
     * @return array<string, mixed>
     */
    public static function applyDefaultFillSortOrder(array $field): array
    {
        $key = self::canonicalFieldKey((string) ($field['key'] ?? ''));
        if ($key === '') {
            return $field;
        }

        if (isset($field['fill_sort_order']) && is_numeric($field['fill_sort_order'])) {
            $field['fill_sort_order'] = (int) $field['fill_sort_order'];

            return $field;
        }

        $preset = self::recommendedByKey()[$key] ?? null;
        if ($preset !== null && isset($preset['fill_sort_order'])) {
            $field['fill_sort_order'] = (int) $preset['fill_sort_order'];

            return $field;
        }

        $field['fill_sort_order'] = self::guessFillSortOrder($key, self::fieldGroupForKey($key));

        return $field;
    }

    /**
     * Для таблицы в модалке шаблона: сначала поля с предзаполнением из CRM, в конце — родитель и автоматика.
     *
     * @param array<int, array<string, mixed>> $schema
     * @return array<int, array<string, mixed>>
     */
    public static function sortFieldsForAdminEditor(array $schema): array
    {
        $crm = [];
        $parent = [];
        $system = [];

        foreach ($schema as $field) {
            $key = (string) ($field['key'] ?? '');
            match (self::fillModeForKey($key)) {
                self::FILL_MODE_CRM => $crm[] = $field,
                self::FILL_MODE_SYSTEM => $system[] = $field,
                default => $parent[] = $field,
            };
        }

        return array_merge($crm, $parent, $system);
    }

    /**
     * @param array<int, array<string, mixed>> $schema
     * @return array<int, array<string, mixed>>
     */
    public static function enrichSchema(array $schema): array
    {
        $enriched = array_map(
            static fn (array $field): array => self::enrichField($field),
            $schema,
        );

        return self::sortFieldsForAdminEditor($enriched);
    }

    /**
     * @return array{label: string, required: bool, prefill_source: string|null}|null
     */
    public static function defaultsForKey(string $key): ?array
    {
        $preset = self::recommendedByKey()[$key] ?? null;
        if ($preset === null) {
            return null;
        }

        return [
            'label'           => $preset['label'],
            'required'        => $preset['required_default'],
            'prefill_source'  => $preset['prefill_source'],
            'fill_sort_order' => (int) ($preset['fill_sort_order'] ?? self::FILL_SORT_DEFAULT_CUSTOM),
        ];
    }

    /**
     * @return list<array{key: string, label: string, description: string, group: string, prefill_source: string|null, required_default: bool}>
     */
    public static function recommendedForGroup(string $group): array
    {
        return array_values(array_filter(
            self::recommended(),
            static fn (array $preset): bool => $preset['group'] === $group,
        ));
    }

    public static function placeholderToken(string $key): string
    {
        return '{{' . $key . '}}';
    }

    public static function fieldGroupForKey(string $key): string
    {
        $key = self::canonicalFieldKey($key);

        $preset = self::recommendedByKey()[$key] ?? null;
        if ($preset !== null) {
            return $preset['group'] === self::GROUP_CHILD
                ? self::GROUP_CHILD
                : self::GROUP_PARENT;
        }

        if (str_starts_with($key, 'child_') || str_starts_with($key, 'student_')) {
            return self::GROUP_CHILD;
        }

        return self::GROUP_PARENT;
    }

    /**
     * Порядок полей в форме кабинета родителя (меньше — выше).
     *
     * @param array<string, mixed> $field
     */
    public static function resolveFillSortOrder(array $field, string $group): int
    {
        if (isset($field['fill_sort_order']) && is_numeric($field['fill_sort_order'])) {
            return (int) $field['fill_sort_order'];
        }

        $key = self::canonicalFieldKey((string) ($field['key'] ?? ''));
        $preset = self::recommendedByKey()[$key] ?? null;
        if ($preset !== null && isset($preset['fill_sort_order'])) {
            return (int) $preset['fill_sort_order'];
        }

        return self::guessFillSortOrder($key, $group);
    }

    public static function guessFillSortOrder(string $key, string $group): int
    {
        $key = self::canonicalFieldKey($key);

        if ($group === self::GROUP_PARENT && str_starts_with($key, 'spouse_')) {
            return match (true) {
                str_contains($key, 'full_name')
                    || preg_match('/_(lastname|firstname|middlename)$/', $key) === 1 => 200,
                str_contains($key, 'phone') || str_contains($key, 'tel') || str_contains($key, 'mobile') => 201,
                str_contains($key, 'email') || str_contains($key, 'mail') => 202,
                default => 203,
            };
        }

        if (str_starts_with($key, 'trusted_person_')) {
            return str_contains($key, 'contact') || str_contains($key, 'phone') ? 211 : 210;
        }

        if (preg_match('/_(lastname|firstname|middlename|full_name)$/', $key, $m)) {
            return match ($m[1]) {
                'lastname'   => 11,
                'firstname'  => 12,
                'middlename' => 13,
                default      => 14,
            };
        }

        if (str_contains($key, 'phone') || str_contains($key, 'tel') || str_contains($key, 'mobile')) {
            return 20;
        }

        if (str_contains($key, 'email') || str_contains($key, 'mail')) {
            return 30;
        }

        if (str_contains($key, 'passport')) {
            return str_contains($key, 'issued') ? 41 : 40;
        }

        if (str_contains($key, 'birthday') || str_contains($key, 'birth_date')) {
            return 50;
        }

        return self::FILL_SORT_DEFAULT_CUSTOM;
    }

    /**
     * @param list<array<string, mixed>> $fields
     * @return list<array<string, mixed>>
     */
    public static function sortFieldsForParentFormGroup(array $fields, string $group): array
    {
        $indexed = [];
        foreach ($fields as $index => $field) {
            $indexed[] = ['field' => $field, 'index' => $index];
        }

        usort($indexed, static function (array $a, array $b) use ($group): int {
            $orderA = self::resolveFillSortOrder($a['field'], $group);
            $orderB = self::resolveFillSortOrder($b['field'], $group);

            if ($orderA !== $orderB) {
                return $orderA <=> $orderB;
            }

            return $a['index'] <=> $b['index'];
        });

        return array_map(static fn (array $row): array => $row['field'], $indexed);
    }

    /**
     * @param array<int, array<string, mixed>> $fields
     * @return array{parent: list<array<string, mixed>>, child: list<array<string, mixed>>}
     */
    public static function groupFieldsForParentForm(array $fields): array
    {
        $grouped = [
            self::GROUP_PARENT => [],
            self::GROUP_CHILD  => [],
        ];

        foreach ($fields as $field) {
            $key = (string) ($field['key'] ?? '');
            if ($key === '') {
                continue;
            }

            $grouped[self::fieldGroupForKey($key)][] = $field;
        }

        $grouped[self::GROUP_PARENT] = self::sortFieldsForParentFormGroup(
            $grouped[self::GROUP_PARENT],
            self::GROUP_PARENT,
        );
        $grouped[self::GROUP_CHILD] = self::sortFieldsForParentFormGroup(
            $grouped[self::GROUP_CHILD],
            self::GROUP_CHILD,
        );

        return $grouped;
    }

    public static function fillFormFieldLabel(string $label, string $group): string
    {
        $label = trim($label);

        $prefixes = $group === self::GROUP_CHILD
            ? ['Ребёнок:', 'Ученик:', 'Группа:']
            : ['Родитель:'];

        foreach ($prefixes as $prefix) {
            if (str_starts_with($label, $prefix)) {
                $label = trim(mb_substr($label, mb_strlen($prefix)));
                break;
            }
        }

        return self::capitalizeFillFormLabel($label);
    }

    public static function isFillFormDateField(string $key): bool
    {
        $key = self::canonicalFieldKey($key);

        if (self::isSystemFillField($key)) {
            return false;
        }

        if ($key === ContractTemplatePrefillSources::CHILD_BIRTHDAY) {
            return true;
        }

        return str_contains($key, 'birthday')
            || str_contains($key, 'birth_date')
            || str_contains($key, 'date_of_birth');
    }

    public static function dateValueForFillInput(?string $value): string
    {
        $parsed = self::parseFillFormDate($value);

        return $parsed?->format('Y-m-d') ?? '';
    }

    public static function normalizeFillFormDateValue(?string $value): string
    {
        $parsed = self::parseFillFormDate($value);

        return $parsed?->format('d.m.Y') ?? trim((string) $value);
    }

    public static function parseFillFormDate(?string $value): ?Carbon
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        foreach (['Y-m-d', 'd.m.Y', 'd/m/Y'] as $format) {
            try {
                return Carbon::createFromFormat($format, $value)->startOfDay();
            } catch (\Throwable) {
            }
        }

        return null;
    }

    public static function capitalizeFillFormLabel(string $label): string
    {
        $label = trim($label);
        if ($label === '') {
            return $label;
        }

        return mb_strtoupper(mb_substr($label, 0, 1, 'UTF-8'), 'UTF-8')
            . mb_substr($label, 1, null, 'UTF-8');
    }
}
