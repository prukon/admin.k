<?php

namespace App\Providers;

// use Illuminate\Support\Facades\Gate;
use App\Models\User;
use App\Models\Role;
use App\Policies\AdminPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        User::class => AdminPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot()
    {
        $this->registerPolicies();

        /**
         * ✅ role_id -> roles.name (кеш в пределах запроса)
         * Нужен, чтобы убрать хардкод по ID в Gate'ах.
         */
        $roleNameById = static function (?int $roleId): ?string {
            static $cache = [];

            if (!$roleId) {
                return null;
            }

            if (!array_key_exists($roleId, $cache)) {
                $cache[$roleId] = Role::query()
                    ->whereKey($roleId)
                    ->value('name'); // string|null
            }

            return $cache[$roleId];
        };

        Gate::define('verify-phone', function (User $actor, User $target) use ($roleNameById) {
            // Сам себе — всегда можно
            if ($actor->id === $target->id) {
                return true;
            }

            // Админ/суперадмин — по roles.name (без хардкода ID)
            $roleName = $roleNameById((int)$actor->role_id);

            return in_array($roleName, ['superadmin', 'admin'], true);
        });

        // Всё подряд разрешаем суперадмину (role.name = superadmin)
        Gate::before(function (User $user, string $ability) use ($roleNameById) {
            return $roleNameById((int)$user->role_id) === 'superadmin' ? true : null;
        });

////////////////////////ГЛАВНОЕ МЕНЮ//////////////////////

        // Страница "Консоль"
        Gate::define('dashboard.view', function (User $user) {
            return $user->hasPermission('dashboard.view');
        });

        // Отчёты
        Gate::define('reports.view', function (User $user) {
            return $user->hasPermission('reports.view');
        });

        // Отчёты -> вкладка "Платежные запросы"
        Gate::define('reports.payment.intents.view', function (User $user) {
            return $user->hasPermission('reports.payment.intents.view');
        });

        // Отчёты -> вкладка "Исходящие письма"
        Gate::define('reports.emails.view', function (User $user) {
            return $user->hasPermission('reports.emails.view');
        });

        // Отчёты -> доп. значения (комиссии/нетто и т.п.)
        Gate::define('reports.additional.value.view', function (User $user) {
            return $user->hasPermission('reports.additional.value.view');
        });

        // Отчёт «Платежи» — итоги в шапке (скрытые пермишены в БД)
        Gate::define('reports.payments.totals.net_to_partner.view', function (User $user) {
            return $user->hasPermission('reports.payments.totals.net_to_partner.view');
        });
        Gate::define('reports.payments.totals.payout_amount.view', function (User $user) {
            return $user->hasPermission('reports.payments.totals.payout_amount.view');
        });
        Gate::define('reports.payments.totals.platform_commission.view', function (User $user) {
            return $user->hasPermission('reports.payments.totals.platform_commission.view');
        });

        // Мои платежи
        Gate::define('myPayments.view', function (User $user) {
            return $user->hasPermission('myPayments.view');
        });

        // Мои группы
        Gate::define('myGroup.view', function (User $user) {
            return $user->hasPermission('myGroup.view');
        });

        // Установка цен
        Gate::define('setPrices.view', function (User $user) {
            return $user->hasPermission('setPrices.view');
        });

        // Установка цен — ручная отметка «оплачен месяц» (скрытый пермишн)
        Gate::define('setPrices.manualPaid.manage', function (User $user) {
            return $user->hasPermission('setPrices.manualPaid.manage');
        });

        // Дополнительные платежи (кастомные периоды): просмотр в установке цен и на консоли
        Gate::define('setPrices.customPayments.view', function (User $user) {
            return $user->hasPermission('setPrices.customPayments.view');
        });

        // Журнал расписания
        Gate::define('schedule.view', function (User $user) {
            return $user->hasPermission('schedule.view');
        });

        // Абонементы (lesson_packages)
        Gate::define('lessonPackages.view', function (User $user) {
            return $user->hasPermission('lessonPackages.view');
        });
        Gate::define('lessonPackages.manage', function (User $user) {
            return $user->hasPermission('lessonPackages.manage');
        });

        // Управление пользователями
        Gate::define('users.view', function (User $user) {
            return $user->hasPermission('users.view');
        });

        // Управление группами
        Gate::define('groups.view', function (User $user) {
            return $user->hasPermission('groups.view');
        });

        // Страница "Партнеры"
        Gate::define('partner.view', function (User $user) {
            return $user->hasPermission('partner.view');
        }); 

        // Переключение партнёра (контекст) — строго superadmin (anti-leak)
        Gate::define('partner.switch', function (User $user) use ($roleNameById) {
            // $roleName = $roleNameById((int) $user->role_id);
            // return $roleName === 'superadmin';
            return $user->hasPermission('partner.switch');
        });

        // Страница "Договоры"
        Gate::define('contracts.view', function (User $user) {
            return $user->hasPermission('contracts.view');
        });

        // Договоры: ручная синхронизация с Подпислон (статус и подписанный файл)
        Gate::define('contracts.sync', function (User $user) {
            return $user->hasPermission('contracts.sync');
        });

        // Страница "Настройки
        Gate::define('settings.view', function (User $user) {
            return $user->hasPermission('settings.view');
        });

        // Настройки: вкл/выкл регистрации на сайте (партнёр)
        Gate::define('settings.registration.manage', function (User $user) {
            return $user->hasPermission('settings.registration.manage');
        });

        // Страница "Настройки -> Права и роли"
        Gate::define('settings.roles.view', function (User $user) {
            return $user->hasPermission('settings.roles.view');
        });

        // Страница "Настройки  -> Платежные системы"
        Gate::define('settings.paymentSystems.view', function (User $user) {
            return $user->hasPermission('settings.paymentSystems.view');
        });

        // Страница "Документация"
        Gate::define('documentations.view', function (User $user) {
            return $user->hasPermission('documentations.view');
        });


        // Страница "Настройки  -> Настройка комиссий"
        Gate::define('settings.commission', function (User $user) {
            return $user->hasPermission('settings.commission');
        });

        // Управление обязательной 2FA для админов (глобальная настройка)
        // По умолчанию доступно суперадмину (role_id=1), плюс можно выдать отдельное permission.
        Gate::define('settings.force2fa.admins', function (User $user) {
            return (int)$user->role_id === 1 || $user->hasPermission('settings.force2fa.admins');
        });

        // Настройки -> Очереди (просмотр)
        Gate::define('settings.queues.view', function (User $user) {
            return $user->hasPermission('settings.queues.view');
        });

        // Настройки -> Очереди (управление: restart и т.п.)
        Gate::define('settings.queues.manage', function (User $user) {
            return $user->hasPermission('settings.queues.manage');
        });

        // Страница "Учетная запись -> Личные данные"
        Gate::define('account.user.view', function (User $user) {
            return $user->hasPermission('account.user.view');
        });

        // Страница "Учетная запись -> организация"
        Gate::define('account.partner.view', function (User $user) {
            return $user->hasPermission('account.partner.view');
        });

        // Изменение данных организации (учетная запись)
        Gate::define('account.partner.update', function (User $user) {
            return $user->hasPermission('account.partner.update');
        });

        // Страница "Учетная запись -> Документы"
        Gate::define('account.documents.view', function (User $user) {
            return $user->hasPermission('account.documents.view');
        });

        // Страница "Сообщения"
        Gate::define('messages.view', function (User $user) {
            return $user->hasPermission('messages.view');
        });

        // Страница "Блог" (управление статьями)
        Gate::define('blog.view', function (User $user) {
            return $user->hasPermission('blog.view');
        });

        // Страница "Лиды"
        Gate::define('leads.view', function (User $user) {  
            return $user->hasPermission('leads.view');
        });

        // Оплата сервиса
        Gate::define('servicePayments.view', function (User $user) {
            return $user->hasPermission('servicePayments.view');
        });

        // Кошелек
        Gate::define('partnerWallet.view', function (User $user) {
            return $user->hasPermission('partnerWallet.view');
        });



        ////////////////////////Учетная запись //////////////////////

        // Изменение своего имени
        Gate::define('account.user.name.update', function (User $user) {
            return $user->hasPermission('account.user.name.update');
        });
 
        // Изменение своей даты рождения
        Gate::define('account.user.birthdate.update', function (User $user) {
            return $user->hasPermission('account.user.birthdate.update');
        });

        // Изменение своей группы
        Gate::define('account.user.team.update', function (User $user) {
            return $user->hasPermission('account.user.team.update');
        });

        // Изменение даты своего начала занятий
        Gate::define('account-user-startDate-update', function (User $user) {
            return $user->hasPermission('account.user.startDate.update');
        });

        // Изменение своего email
        Gate::define('account.user.email.update', function (User $user) {
            return $user->hasPermission('account.user.email.update');
        });

        // Изменение своего телефона
        Gate::define('account.user.phone.update', function (User $user) {
            return $user->hasPermission('account.user.phone.update');
        });

//////////////////////// Управление пользователями //////////////////////

        // Изменение имени
        Gate::define('users.name.update', function (User $user) {
            return $user->hasPermission('users.name.update');
        });

        // Изменение даты рождения
        Gate::define('users.birthdate.update', function (User $user) {
            return $user->hasPermission('users.birthdate.update');
        });

        // Изменение группы
        Gate::define('users.group.update', function (User $user) {
            return $user->hasPermission('users.group.update');
        });

        // Изменение даты начала занятий
        Gate::define('users.startDate.update', function (User $user) {
            return $user->hasPermission('users.startDate.update');
        });

        // Изменение email
        Gate::define('users.email.update', function (User $user) {
            return $user->hasPermission('users.email.update');
        });

        // Изменение телефона
        Gate::define('users.phone.update', function (User $user) {
            return $user->hasPermission('users.phone.update');
        });

        // Изменение роли (прав)
        Gate::define('users.role.update', function (User $user) {
            return $user->hasPermission('users.role.update');
        });

        // Изменение роли (прав)
        Gate::define('users.activity.update', function (User $user) {
            return $user->hasPermission('users.activity.update');
        });

        // Изменение пароля
        Gate::define('users.password.update', function (User $user) {
            return $user->hasPermission('users.password.update');
        });

//////////////////////// Разное  //////////////////////

        // Пример: Gate для доступа в админку
        // Gate::define('access-admin-panel', function (User $user) {
        //     return $user->hasPermission('access_admin_panel');
        // });

        // Создание пользователей (пустышка для Gate)
        // Gate::define('users.create', function (User $user) {
        //     return $user->hasPermission('users.create');
        // });

        // Удаление пользователей (пустышка для Gate)
        // Gate::define('users.delete', function (User $user) {
        //     return $user->hasPermission('users.delete');
        // });

        // История изменений (пустышка для Gate)
        // Gate::define('change.history', function (User $user) {
        //     return $user->hasPermission('change.history');
        // });

        // Оплата занятий
        Gate::define('paying.classes', function (User $user) {
            return $user->hasPermission('paying.classes');
        });

        // Оплата клубного взноса 
        Gate::define('payment.clubfee', function (User $user) {
            return $user->hasPermission('payment.clubfee');
        });

        // Настройка платежных систем (пустышка для Gate)
        // Gate::define('setting-payment-systems', function (User $user) {
        //     return $user->hasPermission('setting_payment_systems');
        // });

        Gate::define('payment.method.robokassa', function (User $user) {
            return $user->hasPermission('payment.method.robokassa');
        });

        Gate::define('payment.method.tbankCard', function (User $user) {
            return $user->hasPermission('payment.method.tbankCard');
        });

        Gate::define('payment.method.tbankSBP', function (User $user) {
            return $user->hasPermission('payment.method.tbankSBP');
        });

        // Управление интеграцией T-Bank (админские действия: sm-register, payouts, debug, админ-карточки)
        Gate::define('manage.payment.method.tbank', function (User $user) {
            return $user->hasPermission('manage.payment.method.tbank');
        });

        // Управление выплатами T-Bank (роль "бухгалтер" и суперадмин)
        Gate::define('tbank.payouts.manage', function (User $user) {
            return $user->hasPermission('tbank.payouts.manage');
        });

        // Просмотр всех логов
        Gate::define('viewing.all.logs', function (User $user) {
            return $user->hasPermission('viewing.all.logs');
        });
    }
}