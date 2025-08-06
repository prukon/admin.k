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

        // Отчёты
        Gate::define('reports', function (User $user) {
            return $user->hasPermission('reports');
        });

        // Установка цен
        Gate::define('set-prices', function (User $user) {
            return $user->hasPermission('set_prices');
        });

        // Журнал расписания
        Gate::define('schedule-journal', function (User $user) {
            return $user->hasPermission('schedule_journal');
        });

        // Управление пользователями
        Gate::define('manage-users', function (User $user) {
            return $user->hasPermission('manage_users');
        });

        // Управление группами
        Gate::define('manage-groups', function (User $user) {
            return $user->hasPermission('manage_groups');
        });

        // Общие настройки
        Gate::define('general-settings', function (User $user) {
            return $user->hasPermission('general_settings');
        });

        // Управление ролями
        Gate::define('manage-roles', function (User $user) {
            return $user->hasPermission('manage_roles');
        });

        // Оплата сервиса
        Gate::define('service-payment', function (User $user) {
            return $user->hasPermission('service_payment');
        });

        // История изменений
        Gate::define('change-history', function (User $user) {
            return $user->hasPermission('change_history');
        });

        // Мои платежи
        Gate::define('my-payments', function (User $user) {
            return $user->hasPermission('my_payments');
        });

        // Вкладка организация
        Gate::define('partner-company', function (User $user) {
            return $user->hasPermission('partner_company');
        });

        // Редактирование имени
        Gate::define('name-editing', function (User $user) {
            return $user->hasPermission('name_editing');
        });

        // Изменение своей группы
        Gate::define('changing-your-group', function (User $user) {
            return $user->hasPermission('changing_your_group');
        });

        // Изменение активности пользователя
        Gate::define('changing-user-activity', function (User $user) {
            return $user->hasPermission('changing_user_activity');
        });

        // Изменение прав пользователя
        Gate::define('changing-user-rules', function (User $user) {
            return $user->hasPermission('changing_user_rules');
        });

        // Изменение email пользователя
        Gate::define('changing-user-email', function (User $user) {
            return $user->hasPermission('changing_user_email');
        });

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
        Gate::define('changing-partner', function (User $user) {
            return $user->hasPermission('changing_partner');
        });

        // Изменение своего партнера
        Gate::define('viewing-leads', function (User $user) {
            return $user->hasPermission('viewing_leads');
        });

        // Управление партнерами
        Gate::define('manage-partners', function (User $user) {
            return $user->hasPermission('manage_partners');
        });

    }
}
