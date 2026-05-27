<?php

namespace App\Services\BlogVk;

use App\Services\BlogVk\Exceptions\VkApiException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class VkWallClient
{
    private const API_BASE = 'https://api.vk.com/method/';

    public function __construct(
        private readonly BlogVkSettings $settings,
    ) {
    }

    /**
     * @return array{post_id: int, owner_id: int}
     */
    public function publishPost(string $message, string $articleUrl, string $coverPathOnPublicDisk): array
    {
        $groupId = $this->settings->groupId();
        if ($groupId <= 0) {
            throw new VkApiException('VK_GROUP_ID не задан или некорректен.');
        }

        $absoluteCoverPath = Storage::disk('public')->path($coverPathOnPublicDisk);
        if (!is_file($absoluteCoverPath)) {
            throw new VkApiException('Файл обложки не найден: ' . $coverPathOnPublicDisk);
        }

        $photo = $this->uploadWallPhoto($groupId, $absoluteCoverPath);
        $ownerId = -$groupId;
        $photoAttachment = sprintf('photo%d_%d', $photo['owner_id'], $photo['id']);

        $attachments = implode(',', array_filter([
            $photoAttachment,
            $articleUrl,
        ]));

        $response = $this->call('wall.post', [
            'owner_id' => $ownerId,
            'from_group' => 1,
            'message' => $message,
            'attachments' => $attachments,
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
     * @return array{owner_id: int, id: int}
     */
    private function uploadWallPhoto(int $groupId, string $absoluteFilePath): array
    {
        $uploadServer = $this->call('photos.getWallUploadServer', [
            'group_id' => $groupId,
        ]);

        $uploadUrl = (string) data_get($uploadServer, 'response.upload_url', '');
        if ($uploadUrl === '') {
            throw new VkApiException('VK не вернул upload_url.', response: $uploadServer);
        }

        $uploadResponse = Http::timeout(60)
            ->attach('photo', file_get_contents($absoluteFilePath), basename($absoluteFilePath))
            ->post($uploadUrl);

        if (!$uploadResponse->successful()) {
            throw new VkApiException(
                'Ошибка загрузки фото на сервер VK: HTTP ' . $uploadResponse->status()
            );
        }

        $uploadPayload = $uploadResponse->json();
        if (!is_array($uploadPayload)) {
            throw new VkApiException('Некорректный ответ сервера загрузки VK.');
        }

        $saved = $this->call('photos.saveWallPhoto', [
            'group_id' => $groupId,
            'photo' => (string) ($uploadPayload['photo'] ?? ''),
            'server' => (int) ($uploadPayload['server'] ?? 0),
            'hash' => (string) ($uploadPayload['hash'] ?? ''),
        ]);

        $photo = data_get($saved, 'response.0');
        if (!is_array($photo)) {
            throw new VkApiException('VK не сохранил фото на стене.', response: $saved);
        }

        $ownerId = (int) ($photo['owner_id'] ?? 0);
        $id = (int) ($photo['id'] ?? 0);
        if ($ownerId === 0 || $id === 0) {
            throw new VkApiException('VK вернул некорректные данные фото.', response: $saved);
        }

        return ['owner_id' => $ownerId, 'id' => $id];
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
