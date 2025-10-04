<?php

namespace App\Providers;

// use Illuminate\Support\Facades\Gate;
use App\Models\User;
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

        Gate::define('verify-phone', function (User $actor, User $target) {
            return $actor->id === $target->id || in_array((int)$actor->role_id, [1, 10], true);
        });

        // Всё подряд разрешаем суперадмину (role_id = 1)
        Gate::before(function (User $user, string $ability) {
            return $user->role_id === 1 ? true : null;
        });

////////////////////////ГЛАВНОЕ МЕНЮ//////////////////////

        // Страница "Консоль"
        Gate::define('dashboard-view', function (User $user) {
            return $user->hasPermission('dashboard.view');
        });

        // Отчёты
        Gate::define('reports-view', function (User $user) {
            return $user->hasPermission('reports.view');
        });

        // Мои платежи
        Gate::define('myPayments-view', function (User $user) {
            return $user->hasPermission('myPayments.view');
        });

        // Мои группы
        Gate::define('myGroup-view', function (User $user) {
            return $user->hasPermission('myGroup.view');
        });

        // Установка цен
        Gate::define('setPrices-view', function (User $user) {
            return $user->hasPermission('setPrices.view');
        });

        // Журнал расписания
        Gate::define('schedule-view', function (User $user) {
            return $user->hasPermission('schedule.view');
        });

        // Управление пользователями
        Gate::define('users-view', function (User $user) {
            return $user->hasPermission('users.view');
        });

        // Управление группами
        Gate::define('groups-view', function (User $user) {
            return $user->hasPermission('groups.view');
        });

        // Страница "Партнеры"
        Gate::define('partner-view', function (User $user) {
            return $user->hasPermission('partner.view');
        });

             // Страница "Договоры"
        Gate::define('contracts-view', function (User $user) {
            return $user->hasPermission('contracts.view');
        });

        // Страница "Настройки
        Gate::define('settings-view', function (User $user) {
            return $user->hasPermission('settings.view');
        });

        // Страница "Настройки -> Права и роли"
        Gate::define('settings-roles-view', function (User $user) {
            return $user->hasPermission('settings.roles.view');
        });

        // Страница "Настройки  -> Платежные системы"
        Gate::define('settings-paymentSystems-view', function (User $user) {
            return $user->hasPermission('settings.paymentSystems.view');
        });

        // Страница "Учетная запись -> Личные данные"
        Gate::define('account-user-view', function (User $user) {
            return $user->hasPermission('account.user.view');
        });

        // Страница "Учетная запись -> организация"
        Gate::define('account-partner-view', function (User $user) {
            return $user->hasPermission('account.partner.view');
        });

        // Страница "Учетная запись -> Документы"
        Gate::define('account-documents-view', function (User $user) {
            return $user->hasPermission('account.documents.view');
        });


        // Страница "Сообщения"
        Gate::define('messages-view', function (User $user) {
            return $user->hasPermission('messages.view');
        });

        // Страница "Лиды"
        Gate::define('leads-view', function (User $user) {
            return $user->hasPermission('leads.view');
        });

        // Оплата сервиса
        Gate::define('servicePayments-view', function (User $user) {
            return $user->hasPermission('servicePayments.view');
        });

        // Кошелек
        Gate::define('partnerWallet-view', function (User $user) {
            return $user->hasPermission('partnerWallet.view');
        });



        ////////////////////////Учетная запись //////////////////////

        // Изменение своего имени
        Gate::define('name-editing', function (User $user) {
            return $user->hasPermission('name_editing');
        });

        // Изменение своей даты рождения
        Gate::define('account-user-birthdate-update', function (User $user) {
            return $user->hasPermission('account.user.birthdate.update');
        });

        // Изменение своей группы
        Gate::define('changing-your-group', function (User $user) {
            return $user->hasPermission('changing_your_group');
        });

        // Изменение даты своего начала занятий
        Gate::define('account-user-startDate-update', function (User $user) {
            return $user->hasPermission('account.user.startDate.update');
        });

        // Изменение своего email
        Gate::define('changing-user-email', function (User $user) {
            return $user->hasPermission('changing_user_email');
        });

        // Изменение своего телефона
        Gate::define('account-user-phone-update', function (User $user) {
            return $user->hasPermission('account.user.phone.update');
        });

//////////////////////// Управление пользователями //////////////////////

        // Изменение имени
        Gate::define('users-name-update', function (User $user) {
            return $user->hasPermission('users.name.update');
        });

        // Изменение даты рождения
        Gate::define('users-birthdate-update', function (User $user) {
            return $user->hasPermission('users.birthdate.update');
        });

        // Изменение группы
        Gate::define('users-group-update', function (User $user) {
            return $user->hasPermission('users.group.update');
        });

        // Изменение даты начала занятий
        Gate::define('users-startDate-update', function (User $user) {
            return $user->hasPermission('users.startDate.update');
        });

        // Изменение email
        Gate::define('users-email-update', function (User $user) {
            return $user->hasPermission('users.email.update');
        });

        // Изменение телефона
        Gate::define('users-phone-update', function (User $user) {
            return $user->hasPermission('users.phone.update');
        });

        // Изменение роли (прав)
        Gate::define('users-role-update', function (User $user) {
            return $user->hasPermission('users.role.update');
        });

        // Изменение роли (прав)
        Gate::define('users-activity-update', function (User $user) {
            return $user->hasPermission('users.activity.update');
        });

        // Изменение пароля
        Gate::define('users-password-update', function (User $user) {
            return $user->hasPermission('users.password.update');
        });

//////////////////////// Разное  //////////////////////

        // Пример: Gate для доступа в админку
        Gate::define('access-admin-panel', function (User $user) {
            return $user->hasPermission('access_admin_panel');
        });

        // Создание пользователей
        Gate::define('create-users', function (User $user) {
            return $user->hasPermission('create_users');
        });

        // Удаление пользователей
        Gate::define('delete-users', function (User $user) {
            return $user->hasPermission('delete_users');
        });

        // История изменений
        Gate::define('change-history', function (User $user) {
            return $user->hasPermission('change_history');
        });

        // Оплата занятий
        Gate::define('paying-classes', function (User $user) {
            return $user->hasPermission('paying_classes');
        });

        // Оплата клубного взноса
        Gate::define('payment-clubfee', function (User $user) {
            return $user->hasPermission('payment_clubfee');
        });

        // Настройка платежных систем
        Gate::define('setting-payment-systems', function (User $user) {
            return $user->hasPermission('setting_payment_systems');
        });

        // Настройка платежных систем
        Gate::define('payment-method-T-Bank', function (User $user) {
            return $user->hasPermission('payment_method_T-Bank');
        });


    }
}
