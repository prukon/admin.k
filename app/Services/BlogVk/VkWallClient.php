<?php

namespace App\Services\BlogVk;

use App\Services\BlogVk\Exceptions\VkApiException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class VkWallClient
{
    private const API_BASE = 'https://api.vk.com/method/';

    public function __construct(
        private readonly BlogVkSettings $settings,
    ) {
    }

    /**
     * Публикация на стену группы: текст + ссылка (превью VK подтягивает по OG страницы).
     * Загрузка фото через API не используется — токен сообщества не поддерживает photos.getWallUploadServer.
     *
     * @return array{post_id: int, owner_id: int}
     */
    public function publishPost(string $message, string $articleUrl): array
    {
        $groupId = $this->settings->groupId();
        if ($groupId <= 0) {
            throw new VkApiException('VK_GROUP_ID не задан или некорректен.');
        }

        $ownerId = -$groupId;

        $response = $this->call('wall.post', [
            'owner_id' => $ownerId,
            'from_group' => 1,
            'message' => $message,
            'attachments' => $articleUrl,
        ]);

        $postId = (int) data_get($response, 'response.post_id', 0);
        if ($postId <= 0) {
            throw new VkApiException('VK не вернул post_id.', response: $response);
        }

        return [
            'post_id' => $postId,
            'owner_id' => $ownerId,
        ];
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    private function call(string $method, array $params = []): array
    {
        $params['access_token'] = $this->settings->accessToken();
        $params['v'] = $this->settings->apiVersion();

        /** @var Response $httpResponse */
        $httpResponse = Http::timeout(30)
            ->asForm()
            ->post(self::API_BASE . $method, $params);

        if (!$httpResponse->successful()) {
            throw new VkApiException(
                'HTTP ошибка VK API (' . $method . '): ' . $httpResponse->status()
            );
        }

        $body = $httpResponse->json();
        if (!is_array($body)) {
            throw new VkApiException('Некорректный JSON от VK API (' . $method . ').');
        }

        if (isset($body['error']) && is_array($body['error'])) {
            $code = (int) ($body['error']['error_code'] ?? 0);
            $msg = (string) ($body['error']['error_msg'] ?? 'Unknown VK error');

            throw new VkApiException(
                'VK API (' . $method . '): ' . $msg,
                vkErrorCode: $code,
                response: $body,
            );
        }

        return $body;
    }
}
