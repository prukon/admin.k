<?php

namespace App\Services\BlogVk;

use App\Services\BlogVk\Exceptions\VkApiException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class VkWallClient
{
    private const API_BASE = 'https://api.vk.com/method/';

    public function __construct(
        private readonly BlogVkSettings $settings,
    ) {
    }

    /**
     * Публикация на стену группы: текст + ссылка со сниппетом (через wall.parseAttachedLink).
     * Если VK не смог собрать превью — пост только текстом со ссылкой в message.
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

        $postParams = [
            'owner_id' => $ownerId,
            'from_group' => 1,
            'message' => $message,
        ];

        $linkSnippet = $this->resolveLinkSnippet($articleUrl);
        if ($linkSnippet !== null) {
            $postParams['attachments'] = $articleUrl;
            if ($linkSnippet['link_title'] !== null) {
                $postParams['link_title'] = $linkSnippet['link_title'];
            }
            $postParams['link_photo_id'] = $linkSnippet['link_photo_id'];
        } else {
            $postParams['message'] = $this->messageWithUrl($message, $articleUrl);
            Log::info('VK wall.post: сниппет ссылки недоступен, публикация без attachments', [
                'url' => $articleUrl,
            ]);
        }

        $response = $this->call('wall.post', $postParams);

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
     * @return array{link_title: ?string, link_photo_id: string}|null
     */
    private function resolveLinkSnippet(string $articleUrl): ?array
    {
        try {
            $body = $this->call('wall.parseAttachedLink', [
                'links' => json_encode([$articleUrl], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]);
        } catch (VkApiException $e) {
            Log::warning('VK wall.parseAttachedLink failed', [
                'url' => $articleUrl,
                'error' => $e->getMessage(),
            ]);

            return null;
        }

        $item = data_get($body, 'response.data.0')
            ?? data_get($body, 'response.0');

        if (!is_array($item)) {
            return null;
        }

        $photo = data_get($item, 'link.photo') ?? data_get($item, 'photo');
        if (!is_array($photo)) {
            return null;
        }

        $photoId = (int) data_get($photo, 'id', 0);
        $photoOwnerId = (int) data_get($photo, 'owner_id', 0);
        if ($photoId <= 0 || $photoOwnerId === 0) {
            return null;
        }

        $title = trim((string) (data_get($item, 'link.title') ?? data_get($item, 'title') ?? ''));

        return [
            'link_title' => $title !== '' ? $title : null,
            'link_photo_id' => $photoOwnerId . '_' . $photoId,
        ];
    }

    private function messageWithUrl(string $message, string $articleUrl): string
    {
        if (str_contains($message, $articleUrl)) {
            return $message;
        }

        return rtrim($message) . "\n\n" . $articleUrl;
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
