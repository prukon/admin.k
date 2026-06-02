<?php

namespace App\Enums;

/**
 * Канонический реестр событий аудита (my_logs).
 *
 * Новые записи заполняют {@see value} и {@see level()}.
 * Legacy type/action остаются только в старых строках и для обратной совместимости read-path.
 */
enum AuditEvent: string
{
    // --- pricing (legacy type 1) ---
    case PricingBulkApply = 'pricing.bulk_apply';
    case PricingStudentApply = 'pricing.student_apply';
    case PricingTeamApply = 'pricing.team_apply';
    case PricingManualMonthPaid = 'pricing.manual_month_paid';

    // --- user (legacy type 2) ---
    case UserCreated = 'user.created';
    case UserUpdated = 'user.updated';
    case UserAccountUpdated = 'user.account_updated';
    case UserDeleted = 'user.deleted';
    case UserPasswordChangedByAdmin = 'user.password_changed_by_admin';
    case UserPasswordChanged = 'user.password_changed';
    case UserAvatarUpdatedByAdmin = 'user.avatar_updated_by_admin';
    case UserAvatarUpdated = 'user.avatar_updated';
    case UserAvatarDeleted = 'user.avatar_deleted';
    case UserAvatarDeletedByAdmin = 'user.avatar_deleted_by_admin';
    case UserCustomFieldsUpdated = 'user.custom_fields_updated';
    case UserPhoneUpdated = 'user.phone_updated';

    // --- team (legacy type 3) ---
    case TeamCreated = 'team.created';
    case TeamUpdated = 'team.updated';
    case TeamDeleted = 'team.deleted';

    // --- auth (legacy type 4) ---
    case AuthLogin = 'auth.login';

    // --- payment ---
    case PaymentReceived = 'payment.received';
    case PaymentPayoutScheduleChanged = 'payment.payout_schedule_changed';

    // --- contract (legacy type 500) ---
    case ContractCreated = 'contract.created';
    case ContractSignRequestCreated = 'contract.sign_request_created';
    case ContractSignResentSuccess = 'contract.sign_resent_success';
    case ContractSignResentFailed = 'contract.sign_resent_failed';
    case ContractSignSentSuccess = 'contract.sign_sent_success';
    case ContractSignSentFailed = 'contract.sign_sent_failed';
    case ContractSmsOpened = 'contract.sms_opened';
    case ContractSigned = 'contract.signed';
    case ContractLegacyCreated = 'contract.legacy_created';
    case ContractLegacySmsToggleUpdated = 'contract.legacy_sms_toggle_updated';
    case ContractLegacyDeleted = 'contract.legacy_deleted';

    // --- schedule statuses & calendar (legacy type 9) ---
    case ScheduleStatusCreated = 'schedule.status_created';
    case ScheduleStatusUpdated = 'schedule.status_updated';
    case ScheduleStatusDeleted = 'schedule.status_deleted';
    case ScheduleDayUpdated = 'schedule.day_updated';
    case ScheduleUserTeamAssigned = 'schedule.user_team_assigned';
    case ScheduleUserRangeUpdated = 'schedule.user_range_updated';

    // --- schedule slots (legacy type 46) ---
    case ScheduleSlotOccurrenceSkipped = 'schedule.slot_occurrence_skipped';
    case ScheduleSlotTruncated = 'schedule.slot_truncated';
    case ScheduleSlotDeleted = 'schedule.slot_deleted';
    case ScheduleSlotSplitEdited = 'schedule.slot_split_edited';

    // --- lesson packages / school calendar (legacy type 60) ---
    case ScheduleTrialCancelled = 'schedule.trial_cancelled';
    case ScheduleSingleLessonRegistrationCancelled = 'schedule.single_lesson_registration_cancelled';

    // --- settings (legacy type 1, action 70) ---
    case SettingsUpdated = 'settings.updated';

    // --- role (legacy type 700) ---
    case RoleCreated = 'role.created';
    case RoleUpdated = 'role.updated';
    case RoleDeleted = 'role.deleted';
    case RolePermissionGranted = 'role.permission_granted';
    case RolePermissionRevoked = 'role.permission_revoked';

    // --- partner (legacy type 80) ---
    case PartnerSettingsUpdated = 'partner.settings_updated';
    case PartnerUpdated = 'partner.updated';
    case PartnerCreated = 'partner.created';
    case PartnerUpdatedBySuperadmin = 'partner.updated_by_superadmin';
    case PartnerDeleted = 'partner.deleted';

    public function label(): string
    {
        return match ($this) {
            self::PricingBulkApply => 'Изм. цен во всех группах (Применить слева)',
            self::PricingStudentApply => 'Инд. изм. цен (Применить справа)',
            self::PricingTeamApply => 'Изм. цен в одной группе (ок)',
            self::PricingManualMonthPaid => 'Ручная отметка оплаты месяца (users_prices)',

            self::UserCreated => 'Создание пользователя',
            self::UserUpdated => 'Обновление учетной записи в пользователях',
            self::UserAccountUpdated => 'Обновление учетной записи',
            self::UserDeleted => 'Удаление пользователя в пользователях',
            self::UserPasswordChangedByAdmin => 'Изменение пароля (админ)',
            self::UserPasswordChanged => 'Изменение пароля',
            self::UserAvatarUpdatedByAdmin => 'Изменение аватара (админ)',
            self::UserAvatarUpdated => 'Изменение аватара',
            self::UserAvatarDeleted => 'Удаление аватара',
            self::UserAvatarDeletedByAdmin => 'Удаление аватара (админ)',
            self::UserCustomFieldsUpdated => 'Изменение доп. полей пользователя',
            self::UserPhoneUpdated => 'Изменение номера телефона',

            self::TeamCreated => 'Создание группы',
            self::TeamUpdated => 'Изменение группы',
            self::TeamDeleted => 'Удаление группы',

            self::AuthLogin => 'Авторизация',

            self::PaymentReceived => 'Платежи',
            self::PaymentPayoutScheduleChanged => 'Перенос запланированного времени выплаты',

            self::ContractCreated => 'Договор создан',
            self::ContractSignRequestCreated => 'Создан запрос на подпись (create)',
            self::ContractSignResentSuccess => 'Повторная отправка (успешно)',
            self::ContractSignResentFailed => 'Повторная отправка (ошибка)',
            self::ContractSignSentSuccess => 'Первичная отправка (успешно)',
            self::ContractSignSentFailed => 'Первичная отправка (ошибка)',
            self::ContractSmsOpened => 'Получатель открыл СМС',
            self::ContractSigned => 'Договор подписан',
            self::ContractLegacyCreated => 'Создание договора',
            self::ContractLegacySmsToggleUpdated => 'Изменение отправки договора в SMS',
            self::ContractLegacyDeleted => 'Удаление договора',

            self::ScheduleStatusCreated => 'Создание статуса расписания',
            self::ScheduleStatusUpdated => 'Изменение статуса расписания',
            self::ScheduleStatusDeleted => 'Удаление статуса расписания',
            self::ScheduleDayUpdated => 'Изменение дня расписания ученика',
            self::ScheduleUserTeamAssigned => 'Назначение группы через расписание',
            self::ScheduleUserRangeUpdated => 'Обновление индивидуального расписания',

            self::ScheduleSlotOccurrenceSkipped => 'Пропуск даты слота расписания',
            self::ScheduleSlotTruncated => 'Усечение периода слота расписания',
            self::ScheduleSlotDeleted => 'Удаление слота расписания',
            self::ScheduleSlotSplitEdited => 'Разделение/редактирование слота расписания',

            self::ScheduleTrialCancelled => 'Отмена пробного занятия в расписании',
            self::ScheduleSingleLessonRegistrationCancelled => 'Отмена записи разового занятия в расписании',

            self::SettingsUpdated => 'Изменение настроек',

            self::RoleCreated => 'Создание роли',
            self::RoleUpdated => 'Изменение роли',
            self::RoleDeleted => 'Удаление роли',
            self::RolePermissionGranted => 'Назначение права роли',
            self::RolePermissionRevoked => 'Снятие права у роли',

            self::PartnerSettingsUpdated => 'Изменение настроек партнёра',
            self::PartnerUpdated => 'Изменение партнера',
            self::PartnerCreated => 'Создание партнера суперадмином',
            self::PartnerUpdatedBySuperadmin => 'Изменение партнера суперадмином',
            self::PartnerDeleted => 'Удаление партнера',
        };
    }

    public function level(): AuditLevel
    {
        return match ($this) {
            self::UserDeleted,
            self::UserPasswordChangedByAdmin,
            self::UserPasswordChanged,
            self::TeamDeleted,
            self::AuthLogin,
            self::RoleCreated,
            self::RoleUpdated,
            self::RoleDeleted,
            self::RolePermissionGranted,
            self::RolePermissionRevoked,
            self::PartnerSettingsUpdated,
            self::PartnerUpdated,
            self::PartnerCreated,
            self::PartnerUpdatedBySuperadmin,
            self::PartnerDeleted => AuditLevel::Security,

            self::PaymentReceived,
            self::PaymentPayoutScheduleChanged,
            self::ContractSignResentFailed,
            self::ContractSignSentFailed,
            self::ContractSmsOpened,
            self::ContractSigned => AuditLevel::Integration,

            default => AuditLevel::Info,
        };
    }

    /**
     * Доменная категория для фильтров и будущей замены legacy type.
     */
    public function category(): string
    {
        return match ($this) {
            self::PricingBulkApply,
            self::PricingStudentApply,
            self::PricingTeamApply,
            self::PricingManualMonthPaid => 'pricing',

            self::UserCreated,
            self::UserUpdated,
            self::UserAccountUpdated,
            self::UserDeleted,
            self::UserPasswordChangedByAdmin,
            self::UserPasswordChanged,
            self::UserAvatarUpdatedByAdmin,
            self::UserAvatarUpdated,
            self::UserAvatarDeleted,
            self::UserAvatarDeletedByAdmin,
            self::UserCustomFieldsUpdated,
            self::UserPhoneUpdated => 'user',

            self::TeamCreated,
            self::TeamUpdated,
            self::TeamDeleted => 'team',

            self::AuthLogin => 'auth',

            self::PaymentReceived,
            self::PaymentPayoutScheduleChanged => 'payment',

            self::ContractCreated,
            self::ContractSignRequestCreated,
            self::ContractSignResentSuccess,
            self::ContractSignResentFailed,
            self::ContractSignSentSuccess,
            self::ContractSignSentFailed,
            self::ContractSmsOpened,
            self::ContractSigned,
            self::ContractLegacyCreated,
            self::ContractLegacySmsToggleUpdated,
            self::ContractLegacyDeleted => 'contract',

            self::ScheduleStatusCreated,
            self::ScheduleStatusUpdated,
            self::ScheduleStatusDeleted,
            self::ScheduleDayUpdated,
            self::ScheduleUserTeamAssigned,
            self::ScheduleUserRangeUpdated,
            self::ScheduleSlotOccurrenceSkipped,
            self::ScheduleSlotTruncated,
            self::ScheduleSlotDeleted,
            self::ScheduleSlotSplitEdited,
            self::ScheduleTrialCancelled,
            self::ScheduleSingleLessonRegistrationCancelled => 'schedule',

            self::SettingsUpdated => 'settings',

            self::RoleCreated,
            self::RoleUpdated,
            self::RoleDeleted,
            self::RolePermissionGranted,
            self::RolePermissionRevoked => 'role',

            self::PartnerSettingsUpdated,
            self::PartnerUpdated,
            self::PartnerCreated,
            self::PartnerUpdatedBySuperadmin,
            self::PartnerDeleted => 'partner',
        };
    }

    /**
     * Legacy my_logs.type — дублируется при записи до полного отказа от type.
     */
    public function legacyType(): int
    {
        return match ($this) {
            self::PricingBulkApply,
            self::PricingStudentApply,
            self::PricingTeamApply,
            self::PricingManualMonthPaid,
            self::SettingsUpdated => 1,

            self::UserCreated,
            self::UserUpdated,
            self::UserAccountUpdated,
            self::UserDeleted,
            self::UserPasswordChangedByAdmin,
            self::UserPasswordChanged,
            self::UserAvatarUpdatedByAdmin,
            self::UserAvatarUpdated,
            self::UserAvatarDeleted,
            self::UserAvatarDeletedByAdmin,
            self::UserCustomFieldsUpdated,
            self::UserPhoneUpdated,
            self::PartnerSettingsUpdated => 2,

            self::TeamCreated,
            self::TeamUpdated,
            self::TeamDeleted => 3,

            self::AuthLogin => 4,

            self::PaymentReceived => 5,

            self::PaymentPayoutScheduleChanged => 7,

            self::ScheduleUserRangeUpdated => 6,

            self::ScheduleStatusCreated,
            self::ScheduleStatusUpdated,
            self::ScheduleStatusDeleted,
            self::ScheduleDayUpdated,
            self::ScheduleUserTeamAssigned => 9,

            self::ScheduleSlotOccurrenceSkipped,
            self::ScheduleSlotTruncated,
            self::ScheduleSlotDeleted,
            self::ScheduleSlotSplitEdited => 46,

            self::ScheduleTrialCancelled,
            self::ScheduleSingleLessonRegistrationCancelled => 60,

            self::PartnerUpdated,
            self::PartnerCreated,
            self::PartnerUpdatedBySuperadmin,
            self::PartnerDeleted => 80,

            self::ContractCreated,
            self::ContractSignRequestCreated,
            self::ContractSignResentSuccess,
            self::ContractSignResentFailed,
            self::ContractSignSentSuccess,
            self::ContractSignSentFailed,
            self::ContractSmsOpened,
            self::ContractSigned,
            self::ContractLegacyCreated,
            self::ContractLegacySmsToggleUpdated,
            self::ContractLegacyDeleted => 500,

            self::RoleCreated,
            self::RoleUpdated,
            self::RoleDeleted,
            self::RolePermissionGranted,
            self::RolePermissionRevoked => 700,
        };
    }

    /**
     * Legacy my_logs.action — дублируется при записи до полного отказа от action.
     */
    public function legacyAction(): int
    {
        return match ($this) {
            self::PricingBulkApply => 11,
            self::PricingStudentApply => 12,
            self::PricingTeamApply => 13,
            self::PricingManualMonthPaid => 14,

            self::UserCreated => 21,
            self::UserUpdated => 22,
            self::UserAccountUpdated => 23,
            self::UserDeleted => 24,
            self::UserPasswordChangedByAdmin => 25,
            self::UserPasswordChanged => 26,
            self::UserAvatarUpdatedByAdmin => 27,
            self::UserAvatarUpdated => 28,
            self::UserAvatarDeleted => 29,
            self::UserAvatarDeletedByAdmin => 299,
            self::UserCustomFieldsUpdated => 210,
            self::UserPhoneUpdated => 211,

            self::TeamCreated => 31,
            self::TeamUpdated => 32,
            self::TeamDeleted => 33,

            self::AuthLogin => 40,

            self::PaymentReceived => 50,

            self::PaymentPayoutScheduleChanged => 61,

            self::ScheduleStatusCreated => 90,
            self::ScheduleStatusUpdated => 91,
            self::ScheduleStatusDeleted => 92,
            self::ScheduleDayUpdated => 93,
            self::ScheduleUserTeamAssigned => 94,
            self::ScheduleUserRangeUpdated => 60,

            self::ScheduleSlotOccurrenceSkipped => 461,
            self::ScheduleSlotTruncated => 462,
            self::ScheduleSlotDeleted => 463,
            self::ScheduleSlotSplitEdited => 464,

            self::ScheduleTrialCancelled => 601,
            self::ScheduleSingleLessonRegistrationCancelled => 602,

            self::SettingsUpdated => 70,

            self::RoleCreated => 710,
            self::RoleUpdated => 720,
            self::RoleDeleted => 730,
            self::RolePermissionGranted => 741,
            self::RolePermissionRevoked => 742,

            self::PartnerSettingsUpdated,
            self::PartnerUpdated => 80,
            self::PartnerCreated => 81,
            self::PartnerUpdatedBySuperadmin => 82,
            self::PartnerDeleted => 83,

            self::ContractCreated => 500,
            self::ContractSignRequestCreated => 510,
            self::ContractSignResentSuccess => 511,
            self::ContractSignResentFailed => 512,
            self::ContractSignSentSuccess => 513,
            self::ContractSignSentFailed => 514,
            self::ContractSmsOpened => 519,
            self::ContractSigned => 520,
            self::ContractLegacyCreated => 900,
            self::ContractLegacySmsToggleUpdated => 901,
            self::ContractLegacyDeleted => 902,
        };
    }

    /**
     * События, скрываемые чекбоксом «Скрыть авторизации» на вкладке логов.
     */
    public function isLoginEvent(): bool
    {
        return $this === self::AuthLogin;
    }

    public static function tryFromString(?string $event): ?self
    {
        if ($event === null || $event === '') {
            return null;
        }

        return self::tryFrom($event);
    }

    /**
     * Разрешение legacy-записи без колонки event.
     */
    public static function fromLegacy(?int $type, ?int $action): ?self
    {
        if ($action === null) {
            return null;
        }

        if ($action === 80 && $type === 2) {
            return self::PartnerSettingsUpdated;
        }

        // Prod-история: type=6 — расписание; action=60 — инд. расписание ученика.
        if ($type === 6 && $action === 60) {
            return self::ScheduleUserRangeUpdated;
        }

        if ($type === 6 && $action === 61) {
            return self::PaymentPayoutScheduleChanged;
        }

        return self::fromLegacyAction($action);
    }

    /**
     * Разрешение подписи для строки my_logs (event или legacy).
     */
    public static function resolveLabel(?string $event, ?int $type, ?int $action): string
    {
        $resolved = self::tryFromString($event) ?? self::fromLegacy($type, $action);

        return $resolved?->label() ?? 'Неизвестное событие';
    }

    /**
     * Канонические event для legacy my_logs.type (модалки «История изменений»).
     *
     * @return list<string>
     */
    public static function eventValuesForLegacyType(int $legacyType): array
    {
        $values = [];

        foreach (self::cases() as $case) {
            if ($case->legacyType() === $legacyType) {
                $values[] = $case->value;
            }
        }

        return $values;
    }

    /**
     * @return list<string>
     */
    public static function eventValuesForCategory(string $category): array
    {
        $values = [];

        foreach (self::cases() as $case) {
            if ($case->category() === $category) {
                $values[] = $case->value;
            }
        }

        return $values;
    }

    /**
     * @return list<int>
     */
    public static function legacyTypesForCategory(string $category): array
    {
        $types = [];

        foreach (self::cases() as $case) {
            if ($case->category() === $category) {
                $types[] = $case->legacyType();
            }
        }

        return array_values(array_unique($types));
    }

    /**
     * @return list<int>
     */
    public static function legacyActionsForCategory(string $category): array
    {
        $actions = [];

        foreach (self::cases() as $case) {
            if ($case->category() === $category) {
                $actions[] = $case->legacyAction();
            }
        }

        return array_values(array_unique($actions));
    }

    /**
     * @return list<string>
     */
    public static function knownCategories(): array
    {
        $categories = array_map(
            static fn (self $case) => $case->category(),
            self::cases()
        );

        return array_values(array_unique($categories));
    }

    /**
     * @return array<string, string> event value => label (UI-фильтры)
     */
    public static function labelsForUi(): array
    {
        $labels = [];

        foreach (self::cases() as $case) {
            $labels[$case->value] = $case->label();
        }

        asort($labels);

        return $labels;
    }

    /**
     * @return array<int, string> legacy action => label (обратная совместимость MyLog::actionLabels)
     */
    public static function legacyActionLabels(): array
    {
        $labels = [];

        foreach (self::cases() as $case) {
            $action = $case->legacyAction();
            if (! array_key_exists($action, $labels)) {
                $labels[$action] = $case->label();
            }
        }

        ksort($labels);

        return $labels;
    }

    private static function fromLegacyAction(int $action): ?self
    {
        return match ($action) {
            11 => self::PricingBulkApply,
            12 => self::PricingStudentApply,
            13 => self::PricingTeamApply,
            14 => self::PricingManualMonthPaid,

            21 => self::UserCreated,
            22 => self::UserUpdated,
            23 => self::UserAccountUpdated,
            24 => self::UserDeleted,
            25 => self::UserPasswordChangedByAdmin,
            26 => self::UserPasswordChanged,
            27 => self::UserAvatarUpdatedByAdmin,
            28 => self::UserAvatarUpdated,
            29 => self::UserAvatarDeleted,
            299 => self::UserAvatarDeletedByAdmin,
            210 => self::UserCustomFieldsUpdated,
            211 => self::UserPhoneUpdated,

            31 => self::TeamCreated,
            32 => self::TeamUpdated,
            33 => self::TeamDeleted,

            40 => self::AuthLogin,

            50 => self::PaymentReceived,

            61 => self::PaymentPayoutScheduleChanged,

            90 => self::ScheduleStatusCreated,
            91 => self::ScheduleStatusUpdated,
            92 => self::ScheduleStatusDeleted,
            93 => self::ScheduleDayUpdated,
            94 => self::ScheduleUserTeamAssigned,
            60 => self::ScheduleUserRangeUpdated,
            95 => self::ScheduleUserRangeUpdated,

            461 => self::ScheduleSlotOccurrenceSkipped,
            462 => self::ScheduleSlotTruncated,
            463 => self::ScheduleSlotDeleted,
            464 => self::ScheduleSlotSplitEdited,

            601 => self::ScheduleTrialCancelled,
            602 => self::ScheduleSingleLessonRegistrationCancelled,

            70 => self::SettingsUpdated,

            710 => self::RoleCreated,
            720 => self::RoleUpdated,
            730 => self::RoleDeleted,
            741 => self::RolePermissionGranted,
            742 => self::RolePermissionRevoked,

            80 => self::PartnerUpdated,

            81 => self::PartnerCreated,
            82 => self::PartnerUpdatedBySuperadmin,
            83 => self::PartnerDeleted,

            500 => self::ContractCreated,
            510 => self::ContractSignRequestCreated,
            511 => self::ContractSignResentSuccess,
            512 => self::ContractSignResentFailed,
            513 => self::ContractSignSentSuccess,
            514 => self::ContractSignSentFailed,
            519 => self::ContractSmsOpened,
            520 => self::ContractSigned,

            900 => self::ContractLegacyCreated,
            901 => self::ContractLegacySmsToggleUpdated,
            902 => self::ContractLegacyDeleted,

            default => null,
        };
    }
}
