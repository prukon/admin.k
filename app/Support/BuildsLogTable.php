<?php

namespace App\Support;

use App\Models\MyLog;
use App\Services\PartnerContext;
use Illuminate\Database\Eloquent\Builder;
use Yajra\DataTables\Facades\DataTables;

trait BuildsLogTable
{
    /**
     * Единый билдер DataTables для логов.
     *
     * @param  string|null  $category  Доменная категория AuditEvent (pricing, user, team…) или null для всех событий.
     */
    protected function buildLogDataTable(
        ?string $category,
        PartnerScopeMode $partnerScopeMode = PartnerScopeMode::STRICT_CURRENT,
    ) {
        $partnerContext = app(PartnerContext::class);
        $request = request();
        $includePartnerColumn = $partnerScopeMode === PartnerScopeMode::SUPERADMIN_ALL_OR_FILTER
            && $partnerContext->isSuperAdmin();

        $logs = MyLog::query()
            ->with(['author', 'user', 'partner'])
            ->when($includePartnerColumn, function ($q) {
                $q->leftJoin('partners as log_partners', 'log_partners.id', '=', 'my_logs.partner_id')
                    ->select('my_logs.*');
            })
            ->when($category !== null && $category !== '', function (Builder $q) use ($category) {
                AuditLogQueryScopes::applyCategoryScope($q, $category);
            })
            ->when($request->filled('filter_level'), function (Builder $q) use ($request) {
                AuditLogQueryScopes::applyFilterLevel($q, (string) $request->input('filter_level'));
            })
            ->when($request->filled('created_from'), function ($q) use ($request) {
                $q->whereDate('my_logs.created_at', '>=', (string) $request->input('created_from'));
            })
            ->when($request->filled('created_to'), function ($q) use ($request) {
                $q->whereDate('my_logs.created_at', '<=', (string) $request->input('created_to'));
            })
            ->when($request->filled('filter_action'), function (Builder $q) use ($request) {
                AuditLogQueryScopes::applyFilterAction($q, (string) $request->input('filter_action'));
            })
            ->when($request->has('hide_superadmin') && $request->boolean('hide_superadmin'), function ($q) {
                $q->whereDoesntHave('author', function ($authorQ) {
                    $authorQ->whereHas('role', fn ($roleQ) => $roleQ->where('roles.name', 'superadmin'));
                });
            })
            ->when($request->has('hide_authorizations') && $request->boolean('hide_authorizations'), function (Builder $q) {
                AuditLogQueryScopes::applyHideAuthorizations($q);
            })
            ->when($request->has('hide_integrations') && $request->boolean('hide_integrations'), function (Builder $q) {
                AuditLogQueryScopes::applyHideIntegrations($q);
            })
            ->when($request->filled('filter_author'), function ($q) use ($request) {
                $term = trim((string) $request->input('filter_author'));
                if ($term !== '') {
                    $like = '%' . $term . '%';
                    $q->whereHas('author', function ($authorQ) use ($like) {
                        $authorQ->where(function ($w) use ($like) {
                            $w->where('users.name', 'like', $like)
                                ->orWhere('users.lastname', 'like', $like)
                                ->orWhereRaw("TRIM(CONCAT_WS(' ', users.lastname, users.name)) LIKE ?", [$like]);
                        });
                    });
                }
            })
            ->when($request->filled('filter_target_label'), function ($q) use ($request) {
                $term = trim((string) $request->input('filter_target_label'));
                if ($term !== '') {
                    $q->where('my_logs.target_label', 'like', '%' . $term . '%');
                }
            });

        $partnerContext->applyPartnerScope(
            $logs,
            'my_logs.partner_id',
            $partnerScopeMode,
            $request->input('filter_partner_id'),
        );

        if (!$includePartnerColumn) {
            $logs->select('my_logs.*');
        }

        $dataTable = DataTables::of($logs)
            ->addColumn('author', function ($log) {
                return $log->author?->full_name ?? '—';
            })
            ->addColumn('user', function ($log) {
                return optional($log->user)->full_name
                    ?? optional($log->user)->name
                    ?? '—';
            })
            ->editColumn('action', function ($log) {
                return $log->eventLabel();
            })
            ->editColumn('created_at', function ($log) {
                return $log->created_at
                    ? $log->created_at->format('d.m.Y / H:i:s')
                    : null;
            })
            ->editColumn('target_type', fn($log) => $log->target_type ?? '-')
            ->editColumn('target_id', fn($log) => $log->target_id ?? '-')
            ->editColumn('target_label', fn($log) => $log->target_label ?? '-');

        if ($includePartnerColumn) {
            $dataTable
                ->addColumn('partner_title', function ($log) {
                    return (string) ($log->partner?->title ?? '—');
                })
                ->orderColumn('partner_title', function ($query, $order) {
                    $dir = strtolower((string) $order) === 'asc' ? 'asc' : 'desc';
                    $query->orderByRaw(
                        "CASE WHEN log_partners.title IS NULL OR log_partners.title = '' THEN 1 ELSE 0 END asc"
                    );
                    $query->orderBy('log_partners.title', $dir);
                })
                ->filterColumn('partner_title', function ($query, $keyword) {
                    $term = trim((string) $keyword);
                    if ($term !== '') {
                        $query->where('log_partners.title', 'like', '%' . $term . '%');
                    }
                });
        }

        return $dataTable->make(true);
    }
}
