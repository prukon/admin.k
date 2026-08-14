<?php

namespace App\Providers;

use App\Models\Contract;
use App\Models\MenuItem;
use App\Models\Partner;
use App\Models\PartnerAccess;
use App\Models\SchoolLead;
use App\Models\SchoolLeadStatus;
use App\Models\Setting;
use App\Models\Team;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\PartnerContext;
use App\Services\Users\FamilyStudentContextService;
use App\Services\Users\CabinetTeamAttachService;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use App\Models\PartnerLegalEntity;

use App\Services\Signatures\SignatureProvider;
use App\Services\Signatures\Providers\PodpislonProvider;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\BlogPost;
use App\Observers\BlogPostObserver;
use App\Observers\UserObserver;
use Illuminate\Support\Facades\Queue;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobFailed;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(SignatureProvider::class, function () {
            return new PodpislonProvider();
        });

        $this->app->singleton(\App\Services\Contracts\ContractPdfConverterResolver::class);

        $this->app->bind(
            \App\Services\Contracts\ContractPdfConverterInterface::class,
            fn () => app(\App\Services\Contracts\ContractPdfConverterResolver::class)->resolve()
        );

        // Контекст партнёра — один на запрос
        $this->app->singleton(PartnerContext::class, function () {
            return new PartnerContext();
        });

        $this->app->singleton(\App\Services\Schedule\TrainerSalary\TrainerSalarySchemeRegistry::class, function ($app) {
            return new \App\Services\Schedule\TrainerSalary\TrainerSalarySchemeRegistry([
                $app->make(\App\Services\Schedule\TrainerSalary\Schemes\Classic\ClassicTrainerSalaryScheme::class),
                $app->make(\App\Services\Schedule\TrainerSalary\Schemes\Kansas\KansasTrainerSalaryScheme::class),
            ]);
        });

        $this->app->singleton(\App\Services\Schedule\TrainerSalary\TrainerSalarySchemeResolver::class);

        $this->app->singleton(FamilyStudentContextService::class);

        $this->app->singleton(AuditLogger::class);

        $this->app->singleton(\App\Services\Audit\ContractAudit::class);

        // Чтобы не ломать существующий app('current_partner')->id
        $this->app->singleton('current_partner', function () {
            return app(PartnerContext::class)->partner();
        });

    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Route::bind('legalEntity', fn (string $value) => PartnerLegalEntity::query()->whereKey($value)->firstOrFail());

        if (app()->environment(['local','development','testing'])) {

//            DB::listen(function ($query) {
//                \Log::debug('SQL', [
//                    'sql'      => $query->sql,
//                    'bindings' => $query->bindings,
//                    'time_ms'  => $query->time,
//                ]);
//            });
        }

        Paginator::useBootstrap();

        BlogPost::observe(BlogPostObserver::class);

        // Получаем срок оплаты сервиса
        View::composer('*', function ($view) {
            $latestEndDate = PartnerAccess::where('is_active', 1)->max('end_date');
            $view->with('latestEndDate', $latestEndDate);
        });

        // Баланс партнера; список активных партнёров для переключателя (только при праве partner.switch)
        View::composer('layouts.admin2', function ($view) {

            $balance = 0.0;

            if (Auth::check()) {
                $partnerId = auth()->user()->partner_id ?? session('partner_id'); // замени на свою логику
                if ($partnerId) {
                    $balance = Cache::remember("partner_balance_{$partnerId}", 60, function () use ($partnerId) {
                        return ((int) (Partner::where('id', $partnerId)->value('wallet_balance_cents') ?? 0)) / 100;
                    });
                }
            }

            $partnerSwitchOptions = collect();
            $partnerSwitchActiveCount = 0;

            if (Auth::check() && Gate::allows('partner.switch')) {
                $partnerSwitchOptions = Partner::active()->orderBy('title')->get(['id', 'title']);
                $partnerSwitchActiveCount = $partnerSwitchOptions->count();
            }

            $familyContext = app(FamilyStudentContextService::class);
            $familyStudents = collect();
            $activeStudent = null;
            $showFamilyStudentSwitcher = false;
            $sidebarPanelIdentity = ['name' => '', 'email' => ''];
            $cabinetTeamAttach = null;

            if (Auth::check()) {
                $actor = Auth::user();
                $actor->loadMissing('parentProfile');
                $familyStudents = $familyContext->accessibleStudents($actor);
                $showFamilyStudentSwitcher = $familyStudents->count() > 1;
                $activeStudent = $familyContext->activeStudent($actor);
                $sidebarPanelIdentity = $familyContext->sidebarPanelIdentity($actor);

                if (Gate::allows('account.user.team.update')) {
                    $cabinetTeamAttach = app(CabinetTeamAttachService::class)
                        ->sidebarContext($actor);
                }
            }

            $view->with([
                'partnerWalletBalance' => $balance,
                'partnerSwitchOptions' => $partnerSwitchOptions,
                'partnerSwitchActiveCount' => $partnerSwitchActiveCount,
                'familyStudents' => $familyStudents,
                'activeStudent' => $activeStudent,
                'showFamilyStudentSwitcher' => $showFamilyStudentSwitcher,
                'sidebarPanelIdentity' => $sidebarPanelIdentity,
                'cabinetTeamAttach' => $cabinetTeamAttach,
            ]);
        });

        // Контент @section('content') рендерится до layout — переменные composer layout
        // в дочерних views недоступны. Composer для карандаша у «Группа:» / «Группы».
        View::composer(['dashboard', 'account.index'], function ($view) {
            $cabinetTeamAttach = null;

            if (Auth::check() && Gate::allows('account.user.team.update')) {
                $cabinetTeamAttach = app(CabinetTeamAttachService::class)
                    ->sidebarContext(Auth::user());
            }

            $view->with('cabinetTeamAttach', $cabinetTeamAttach);
        });

        /**
         * ИЗМЕНЁННЫЙ БЛОК: счётчики юзеров и групп
         * Теперь считаем в разрезе текущего партнёра (partner_id),
         * а если партнёр не определён — fallback на глобальные значения.
         */
        View::composer('includes.sidebar', function ($view) {

            $partnerId = session('current_partner')
                ?? auth()->user()?->partner_id
                ?? null;

            if ($partnerId) {
                $teamsCount = Team::where('partner_id', $partnerId)->count();
                $usersCount = User::where('partner_id', $partnerId)->count();
            } else {
                // fallback (например, супер-админ)
                $teamsCount = Team::count();
                $usersCount = User::count();
            }

            $newSchoolLeadsCount = 0;
            if ($partnerId && auth()->user()?->can('schoolLeads.view')) {
                $newSchoolLeadsCount = (int) SchoolLead::query()
                    ->where('partner_id', $partnerId)
                    ->whereNull('deleted_at')
                    ->where('school_lead_status_id', SchoolLeadStatus::systemNewId())
                    ->count();
            }

            $view->with([
                'allTeamsCount' => $teamsCount,
                'allUsersCount' => $usersCount,
                'newSchoolLeadsCount' => $newSchoolLeadsCount,
                'unsignedContractsCount' => $this->unsignedContractsCountForCurrentUser(),
            ]);
        });

        View::composer('account.index', function ($view) {
            $view->with([
                'unsignedContractsCount' => $this->unsignedContractsCountForCurrentUser(),
            ]);
        });

        // Queue health monitor:
        // - heartbeat from worker loop
        // - timestamps for last processed/failed jobs (global)
        Queue::looping(function () {
            Cache::put('queue:monitor:last_heartbeat_at', now()->timestamp, now()->addMinutes(30));
        });

        Queue::after(function (JobProcessed $event) {
            $ts = now()->timestamp;
            Cache::put('queue:monitor:last_heartbeat_at', $ts, now()->addMinutes(30));
            Setting::setInt('queue_monitor_last_success_at', $ts, null);

            Log::channel('queue')->info('Job processed', [
                'connection' => $event->connectionName,
                'queue' => $event->job->getQueue(),
                'job_name' => $event->job->resolveName(),
                'job_id' => $event->job->getJobId(),
            ]);
        });

        Queue::failing(function (JobFailed $event) {
            $ts = now()->timestamp;
            Cache::put('queue:monitor:last_heartbeat_at', $ts, now()->addMinutes(30));
            Setting::setInt('queue_monitor_last_failed_at', $ts, null);

            Log::channel('queue')->error('Job failed', [
                'connection' => $event->connectionName,
                'queue' => $event->job->getQueue(),
                'job_name' => $event->job->resolveName(),
                'job_id' => $event->job->getJobId(),
                'exception' => $event->exception->getMessage(),
            ]);
        });
    }

    /**
     * Договоры текущего пользователя, активные к действию
     * (ожидают заполнения / отправлены / открыты / формируется PDF).
     */
    private function unsignedContractsCountForCurrentUser(): int
    {
        $user = auth()->user();
        if (!$user || !$user->can('account.documents.view')) {
            return 0;
        }

        return (int) Contract::query()
            ->where('user_id', $user->id)
            ->whereIn('status', [
                Contract::STATUS_AWAITING_CLIENT_FILL,
                Contract::STATUS_SENT,
                Contract::STATUS_OPENED,
                Contract::STATUS_GENERATING_PDF,
            ])
            ->count();
    }
}