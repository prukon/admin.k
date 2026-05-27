<?php

namespace App\Services\BlogVk;

use App\Services\BlogVk\Exceptions\VkApiException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class VkWallClient
{
    private const API_BASE = 'https://api.vk.com/method/';

    public function __construct(
        private readonly BlogVkSettings $settings,
        private readonly VkWallPhotoUploader $photoUploader,
    ) {
    }

    /**
     * Публикация на стену группы: текст + ссылка; при VK_USER_TOKEN и обложке — фото.
     *
     * @return array{post_id: int, owner_id: int}
     */
    public function publishPost(string $message, string $articleUrl, ?string $coverStoragePath = null): array
    {
        $groupId = $this->settings->groupId();
        if ($groupId <= 0) {
            throw new VkApiException('VK_GROUP_ID не задан или некорректен.');
        }

        $ownerId = -$groupId;
        $text = $this->messageWithUrl($message, $articleUrl);

        $postParams = [
            'owner_id' => $ownerId,
            'from_group' => 1,
            'message' => $text,
        ];

        $photoAttachment = $this->tryUploadCoverAttachment($coverStoragePath);
        if ($photoAttachment !== null) {
            $postParams['attachments'] = $photoAttachment;
        }

        Log::channel('queue')->info('VK wall.post', [
            'owner_id' => $ownerId,
            'url' => $articleUrl,
            'has_photo' => $photoAttachment !== null,
            'message_length' => mb_strlen($text, 'UTF-8'),
        ]);

        $response = $this->call('wall.post', $postParams);

        $postId = (int) data_get($response, 'response.post_id', 0);
        if ($postId <= 0) {
            throw new VkApiException('VK не вернул post_id.', response: $response);
        }

        Log::channel('queue')->info('VK wall.post ok', [
            'owner_id' => $ownerId,
            'post_id' => $postId,
            'has_photo' => $photoAttachment !== null,
        ]);

        return [
            'post_id' => $postId,
            'owner_id' => $ownerId,
        ];
    }

    private function tryUploadCoverAttachment(?string $coverStoragePath): ?string
    {
        if ($coverStoragePath === null || $coverStoragePath === '') {
            return null;
        }

        if (!$this->settings->canUploadPhotos()) {
            Log::channel('queue')->info('VK: фото пропущено (нет VK_USER_TOKEN)', []);

            return null;
        }

        $absolute = Storage::disk('public')->path($coverStoragePath);
        if (!is_file($absolute)) {
            Log::channel('queue')->warning('VK: обложка не найдена на диске', [
                'path' => $coverStoragePath,
            ]);

            return null;
        }

        try {
            return $this->photoUploader->uploadWallPhoto($absolute);
        } catch (VkApiException $e) {
            Log::channel('queue')->warning('VK: загрузка фото не удалась, пост без картинки', [
                'error' => $e->getMessage(),
            ]);

            return null;
        }
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

            Log::channel('queue')->error('VK API error', [
                'method' => $method,
                'error_code' => $code,
                'error_msg' => $msg,
            ]);

            throw new VkApiException(
                'VK API (' . $method . '): ' . $msg,
                vkErrorCode: $code,
                response: $body,
            );
        }

        return $body;
    }
}
