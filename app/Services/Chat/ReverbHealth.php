<?php

declare(strict_types=1);

namespace App\Services\Chat;

final class ReverbHealth
{
    /**
     * @return array{ok: bool, listening: bool, host: string, port: int, driver: string}
     */
    public function snapshot(): array
    {
        $host = (string) config('broadcasting.connections.reverb.options.host', '127.0.0.1');
        $port = (int) config('broadcasting.connections.reverb.options.port', 6008);
        $listening = $this->isListening($host, $port);

        return [
            'ok' => $listening,
            'listening' => $listening,
            'host' => $host,
            'port' => $port,
            'driver' => (string) config('broadcasting.default'),
        ];
    }

    private function isListening(string $host, int $port): bool
    {
        if ($host === '' || $port <= 0) {
            return false;
        }

        $errno = 0;
        $errstr = '';
        $socket = @fsockopen($host, $port, $errno, $errstr, 0.4);
        if (! is_resource($socket)) {
            return false;
        }

        fclose($socket);

        return true;
    }
}
