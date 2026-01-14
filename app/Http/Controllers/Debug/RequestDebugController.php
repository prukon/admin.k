<?php

namespace App\Http\Controllers\Debug;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class RequestDebugController extends Controller
{
    public function show(Request $request)
    {
        $rid = bin2hex(random_bytes(8));

        $headers = $this->safeHeaders($request->headers->all());

        $data = [
            'rid' => $rid,
            'ts' => now()->toIso8601String(),
            'method' => $request->method(),
            'uri' => $request->getRequestUri(),
            'full_url' => $request->fullUrl(),
            'host' => $request->getHost(),
            'scheme' => $request->getScheme(),
            'is_secure' => $request->isSecure(),

            // IP-related
            'ip' => $request->ip(),                       // what Laravel/Symfony considers client IP (depends on TrustProxies)
            'ips' => $request->ips(),                     // chain of client IPs (depends on TrustProxies)
            'remote_addr' => $request->server('REMOTE_ADDR'),
            'server_addr' => $request->server('SERVER_ADDR'),
            'server_port' => $request->server('SERVER_PORT'),

            // Forwarding headers (raw)
            'x_forwarded_for' => $request->header('X-Forwarded-For'),
            'x_real_ip' => $request->header('X-Real-IP'),
            'forwarded' => $request->header('Forwarded'),
            'x_forwarded_proto' => $request->header('X-Forwarded-Proto'),
            'x_forwarded_host' => $request->header('X-Forwarded-Host'),
            'x_forwarded_port' => $request->header('X-Forwarded-Port'),

            // Useful request context
            'user_agent' => $request->userAgent(),
            'referer' => $request->header('Referer'),
            'accept' => $request->header('Accept'),
            'content_type' => $request->header('Content-Type'),
            'content_length' => $request->header('Content-Length'),
            'debug_marker' => $request->header('X-Debug-Request'),

            // Auth context (safe)
            'auth' => [
                'user_id' => $request->user()?->id,
                'role_id' => $request->user()?->role_id,
            ],

            // Sanitized headers snapshot (to understand proxy/header flow without leaking secrets)
            'headers' => $headers,
        ];

        Log::info('request_debug', $data);

        return response()->json($data);
    }

    private function safeHeaders(array $headers): array
    {
        $redact = [
            'authorization',
            'cookie',
            'set-cookie',
            'x-csrf-token',
            'x-xsrf-token',
            'x-debug-token',
            'x-api-key',
            'x-auth-token',
        ];

        foreach ($headers as $k => $v) {
            if (in_array(strtolower((string)$k), $redact, true)) {
                $headers[$k] = ['***redacted***'];
            }
        }

        return $headers;
    }
}


