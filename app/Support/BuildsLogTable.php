<?php

namespace App\Support;

use App\Models\MyLog;
use Yajra\DataTables\Facades\DataTables;

trait BuildsLogTable
{
    /**
     * Единый билдер DataTables для логов.
     * @param int|null $type Тип логов или null для всех.
     */
    protected function buildLogDataTable(?int $type)
    {
        $partnerId = app('current_partner')->id;

        $request = request();
        $logs = MyLog::query()
            ->with(['author', 'user'])
            ->where('partner_id', $partnerId)
            ->when(!is_null($type), fn($q) => $q->where('type', $type))
            ->when($request->filled('created_from'), function ($q) use ($request) {
                $q->whereDate('my_logs.created_at', '>=', (string) $request->input('created_from'));
            })
            ->when($request->filled('created_to'), function ($q) use ($request) {
                $q->whereDate('my_logs.created_at', '<=', (string) $request->input('created_to'));
            })
            ->when($request->filled('filter_action'), function ($q) use ($request) {
                $q->where('my_logs.action', (int) $request->input('filter_action'));
            })
            ->when($request->filled('filter_author'), function ($q) use ($request) {
                $term = trim((string) $request->input('filter_author'));
                if ($term !== '') {
                    $q->whereHas('author', fn($authorQ) => $authorQ->where('full_name', 'like', '%' . $term . '%'));
                }
            })
            ->when($request->filled('filter_target_label'), function ($q) use ($request) {
                $term = trim((string) $request->input('filter_target_label'));
                if ($term !== '') {
                    $q->where('my_logs.target_label', 'like', '%' . $term . '%');
                }
            })
            ->select('my_logs.*');

        $actionLabels = MyLog::actionLabels();

        return DataTables::of($logs)
            // 👤 Имя автора вместо author_id
            ->addColumn('author', function ($log) {
                return $log->author?->full_name ?? '—';
            })
            // 👤 Целевой пользователь (если есть)
            ->addColumn('user', function ($log) {
                return optional($log->user)->full_name
                    ?? optional($log->user)->name
                    ?? '—';
            })
            // 🏷 Человекочитаемый action
            ->editColumn('action', function ($log) use ($actionLabels) {
                return $actionLabels[$log->action] ?? 'Неизвестный тип (setting)';
            })
            ->editColumn('created_at', function ($log) {
                return $log->created_at
                    ? $log->created_at->format('d.m.Y / H:i:s')
                    : null;
            })
            ->editColumn('target_type', fn($log) => $log->target_type ?? '-')
            ->editColumn('target_id', fn($log) => $log->target_id ?? '-')
            ->editColumn('target_label', fn($log) => $log->target_label ?? '-')
            ->make(true);
    }
}
