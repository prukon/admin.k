<?php

/**
 * Форматирование суммы в копейках в рубли для вывода (шаблоны T‑Bank).
 * Глобальная функция, чтобы не объявлять в @php в Blade (Cannot redeclare при загрузке двух view).
 */
if (!function_exists('roubles')) {
    function roubles($cents): string
    {
        if ($cents === null) {
            return '—';
        }
        return number_format((int) $cents / 100, 2, ',', ' ');
    }
}

/**
 * JSON для отображения в шаблонах (payload и т.п.).
 */
if (!function_exists('pretty_json')) {
    function pretty_json($v): string
    {
        if ($v === null) {
            return '';
        }
        if (is_string($v)) {
            return $v;
        }
        return json_encode($v, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }
}
