<?php

namespace App\Services;

use App\Models\Partner;
use App\Models\PartnerTelegramLinkToken;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PartnerTelegramLinkService
{
    public const START_PREFIX = 'pl_';

    public function createLinkForPartner(int $partnerId, ?int $userId = null, int $ttlMinutes = 30): array
    {
        PartnerTelegramLinkToken::query()
            ->where('partner_id', $partnerId)
            ->whereNull('used_at')
            ->where('expires_at', '<', now())
            ->delete();

        $plainToken = Str::random(40);
        $startPayload = self::START_PREFIX . $plainToken;

        PartnerTelegramLinkToken::create([
            'partner_id'  => $partnerId,
            'user_id'     => $userId,
            'token'       => $plainToken,
            'expires_at'  => now()->addMinutes($ttlMinutes),
        ]);

        $botUsername = ltrim((string) config('services.telegram.bot_username'), '@');
        $url = $botUsername !== ''
            ? 'https://t.me/' . $botUsername . '?start=' . rawurlencode($startPayload)
            : null;

        return [
            'start_payload' => $startPayload,
            'url'           => $url,
            'expires_at'    => now()->addMinutes($ttlMinutes)->toIso8601String(),
        ];
    }

    public function activateFromStartPayload(string $startPayload, string $telegramChatId): ?Partner
    {
        if (!str_starts_with($startPayload, self::START_PREFIX)) {
            return null;
        }

        $plainToken = substr($startPayload, strlen(self::START_PREFIX));
        if ($plainToken === '') {
            return null;
        }

        return DB::transaction(function () use ($plainToken, $telegramChatId) {
            /** @var PartnerTelegramLinkToken|null $link */
            $link = PartnerTelegramLinkToken::query()
                ->where('token', $plainToken)
                ->lockForUpdate()
                ->first();

            if (!$link || !$link->isUsable()) {
                return null;
            }

            $partner = Partner::query()->find($link->partner_id);
            if (!$partner) {
                return null;
            }

            $partner->school_leads_telegram_chat_id = $telegramChatId;
            $partner->save();

            $link->used_at = now();
            $link->save();

            return $partner->fresh();
        });
    }

    public function disconnect(Partner $partner): void
    {
        $partner->school_leads_telegram_chat_id = null;
        $partner->save();
    }
}
