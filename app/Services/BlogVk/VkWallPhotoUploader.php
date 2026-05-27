<?php

namespace App\Services\BlogVk;

use App\Services\BlogVk\Exceptions\VkApiException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Intervention\Image\ImageManager;

class VkWallPhotoUploader
{
    private const API_BASE = 'https://api.vk.com/method/';

    public function __construct(
        private readonly BlogVkSettings $settings,
    ) {
    }

    /**
     * Загружает файл обложки на стену группы. Требует VK_USER_TOKEN.
     *
     * @return string вложение для wall.post, например photo-123456_789
     */
    public function uploadWallPhoto(string $absolutePath): string
    {
        if (!$this->settings->canUploadPhotos()) {
            throw new VkApiException('Не задан VK_USER_TOKEN в .env (нужен для загрузки фото).');
        }

        if (!is_file($absolutePath) || !is_readable($absolutePath)) {
            throw new VkApiException('Файл обложки не найден: ' . $absolutePath);
        }

        $jpegPath = $this->prepareJpegPath($absolutePath);
        $createdTemp = $jpegPath !== $absolutePath;

        try {
            $groupId = $this->settings->groupId();

            Log::channel('queue')->info('VK photos.getWallUploadServer', [
                'group_id' => $groupId,
            ]);

            $serverBody = $this->callUser('photos.getWallUploadServer', [
                'group_id' => $groupId,
            ]);

            $uploadUrl = (string) data_get($serverBody, 'response.upload_url', '');
            if ($uploadUrl === '') {
                throw new VkApiException('VK не вернул upload_url.');
            }

            /** @var Response $uploadResponse */
            $uploadResponse = Http::timeout(60)
                ->attach('photo', file_get_contents($jpegPath), 'cover.jpg')
                ->post($uploadUrl);

            if (!$uploadResponse->successful()) {
                throw new VkApiException(
                    'Ошибка загрузки файла на сервер VK: HTTP ' . $uploadResponse->status()
                );
            }

            $uploadJson = $uploadResponse->json();
            if (!is_array($uploadJson)) {
                throw new VkApiException('Некорректный ответ сервера загрузки VK.');
            }

            $server = data_get($uploadJson, 'server');
            $photo = data_get($uploadJson, 'photo');
            $hash = data_get($uploadJson, 'hash');

            if ($server === null || $photo === null || $hash === null) {
                throw new VkApiException('VK upload: нет server/photo/hash в ответе.');
            }

            $savedBody = $this->callUser('photos.saveWallPhoto', [
                'group_id' => $groupId,
                'server' => $server,
                'photo' => is_string($photo) ? $photo : json_encode($photo),
                'hash' => $hash,
            ]);

            $photoId = (int) data_get($savedBody, 'response.0.id', 0);
            $ownerId = (int) data_get($savedBody, 'response.0.owner_id', 0);

            if ($photoId <= 0 || $ownerId === 0) {
                throw new VkApiException('VK saveWallPhoto не вернул id фото.', response: $savedBody);
            }

            $attachment = sprintf('photo%d_%d', $ownerId, $photoId);

            Log::channel('queue')->info('VK photo uploaded for wall', [
                'attachment' => $attachment,
            ]);

            return $attachment;
        } finally {
            if ($createdTemp && is_file($jpegPath)) {
                @unlink($jpegPath);
            }
        }
    }

    private function prepareJpegPath(string $absolutePath): string
    {
        $ext = strtolower(pathinfo($absolutePath, PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg', 'jpeg'], true)) {
            return $absolutePath;
        }

        $temp = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR
            . 'vk_cover_'
            . bin2hex(random_bytes(8))
            . '.jpg';

        $manager = ImageManager::gd();
        $manager->read($absolutePath)->toJpeg(90)->save($temp);

        return $temp;
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    private function callUser(string $method, array $params = []): array
    {
        $params['access_token'] = $this->settings->userAccessToken();
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

            Log::channel('queue')->error('VK API error (user token)', [
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
