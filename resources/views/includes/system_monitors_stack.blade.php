{{-- Стек системных мониторов: пульт сверху, онлайн, Reverb снизу. --}}
@if(\App\Support\SystemMonitors::canView(auth()->user()))
    <div id="system-monitors-stack" class="system-monitors-stack">
        @include('includes.system_monitors.ops')
        @include('includes.system_monitors.online_users')
        @include('includes.chat.reverb_status')
    </div>
    <style>
        body:not(.system-monitors-on) .system-monitor {
            display: none !important;
        }
        .system-monitors-stack {
            position: fixed;
            right: 12px;
            bottom: 12px;
            z-index: 20000;
            display: flex;
            flex-direction: column;
            gap: 8px;
            align-items: flex-end;
            max-height: calc(100vh - 24px);
            pointer-events: none;
        }
    </style>
@endif
