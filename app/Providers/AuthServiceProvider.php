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
///
///
        //        // Страница "Консоль"
//        Gate::define('dashboard-view', function (User $user) {
//            return $user->hasPermission('dashboard.view');
//        });

        // Страница "Консоль"
        Gate::define('dashboard-view', function (User $user) {
            return $user->hasPermission('dashboard.view');
        });

        //        // Отчёты
//        Gate::define('reports', function (User $user) {
//            return $user->hasPermission('reports');
//        });

        // Отчёты
        Gate::define('reports-view', function (User $user) {
            return $user->hasPermission('reports.view');
        });

        //        // Мои платежи
//        Gate::define('my-payments', function (User $user) {
//            return $user->hasPermission('my_payments');
//        });

        // Мои платежи
        Gate::define('myPayments-view', function (User $user) {
            return $user->hasPermission('myPayments.view ');
        });

        //        // Установка цен
//        Gate::define('set-prices', function (User $user) {
//            return $user->hasPermission('set_prices');
//        });


        // Установка цен
        Gate::define('setPrices-view', function (User $user) {
            return $user->hasPermission('setPrices.view');
        });

        //        // Журнал расписания
//        Gate::define('schedule-journal', function (User $user) {
//            return $user->hasPermission('schedule_journal');
//        });
//

        // Журнал расписания
        Gate::define('schedule-view', function (User $user) {
            return $user->hasPermission('schedule.view');
        });

        //        // Управление пользователями
//        Gate::define('manage-users', function (User $user) {
//            return $user->hasPermission('manage_users');
//        });


        // Управление пользователями
        Gate::define('users-view', function (User $user) {
            return $user->hasPermission('users.view');
        });

        //        // Управление группами
//        Gate::define('manage-groups', function (User $user) {
//            return $user->hasPermission('manage_groups');
//        });
//
        // Управление группами
        Gate::define('groups-view', function (User $user) {
            return $user->hasPermission('groups.view');
        });

        //        // Страница "Партнеры"
//        Gate::define('partner-view', function (User $user) {
//            return $user->hasPermission('partner.view');
//        });
//

        // Страница "Партнеры"
        Gate::define('partner-view', function (User $user) {
            return $user->hasPermission('partner.view');
        });

        //        // Общие настройки
//        Gate::define('general-settings', function (User $user) {
//            return $user->hasPermission('general_settings');
//        });

        // Общие настройки
        Gate::define('settings-view', function (User $user) {
            return $user->hasPermission('settings.view');
        });

        //        // Страница "Учетная запись -> Личные данные"
//        Gate::define('account-user-view', function (User $user) {
//            return $user->hasPermission('account.user.view');
//        });

        // Страница "Учетная запись -> Личные данные"
        Gate::define('account-user-view', function (User $user) {
            return $user->hasPermission('account.user.view');
        });

        //        // Страница "Учетная запись -> организация"
//        Gate::define('partner-company', function (User $user) {
//            return $user->hasPermission('partner_company');
//        });

        // Страница "Учетная запись -> организация"
        Gate::define('account-partner-view', function (User $user) {
            return $user->hasPermission('account.partner.view');
        });

        //        // Страница "Лиды"
//        Gate::define('leads-view', function (User $user) {
//            return $user->hasPermission('leads.view');
//        });

        // Страница "Лиды"
        Gate::define('leads-view', function (User $user) {
            return $user->hasPermission('leads.view');
        });

        //        // Оплата сервиса
//        Gate::define('service-payment', function (User $user) {
//            return $user->hasPermission('service_payment');
//        });


        // Оплата сервиса
        Gate::define('servicePayments-view', function (User $user) {
            return $user->hasPermission('servicePayments.view');
        });

///

////

//


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

        // Управление ролями
        Gate::define('manage-roles', function (User $user) {
            return $user->hasPermission('manage_roles');
        });

        // История изменений
        Gate::define('change-history', function (User $user) {
            return $user->hasPermission('change_history');
        });


        // Изменение активности пользователя
//        Gate::define('changing-user-activity', function (User $user) {
//            return $user->hasPermission('changing_user_activity');
//        });

//        Изменение роли пользователя
//        Gate::define('changing-user-rules', function (User $user) {
//            return $user->hasPermission('changing_user_rules');
//        });


        // Фильтр учеников в консоли
        Gate::define('student-filter-console', function (User $user) {
            return $user->hasPermission('student_filter_console');
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

        // Изменение своего партнера
//        Gate::define('changing-partner', function (User $user) {
//            return $user->hasPermission('changing_partner');
//        });
    }
}
