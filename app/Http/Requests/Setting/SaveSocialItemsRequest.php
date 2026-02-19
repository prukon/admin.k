<?php

namespace App\Http\Requests\Setting;

class SaveSocialItemsRequest extends SettingsJsonRequest
{
    private const URL_REGEX = '/^(\/[\\S]*|https?:\\/\\/[^\s]+)$/';

    public function rules(): array
    {
        return [
            'partner_social_links' => ['nullable', 'array'],
            'partner_social_links.*.url' => ['nullable', 'string', 'regex:' . self::URL_REGEX],
            'partner_social_links.*.is_enabled' => ['nullable', 'boolean'],
            'partner_social_links.*.sort' => ['nullable', 'integer', 'min:0', 'max:65535'],
        ];
    }

    public function attributes(): array
    {
        return [
            'partner_social_links' => 'Социальные сети',
            'partner_social_links.*.url' => 'Ссылка',
            'partner_social_links.*.is_enabled' => 'Активность',
            'partner_social_links.*.sort' => 'Порядок',
        ];
    }

    public function messages(): array
    {
        return [
            'partner_social_links.array' => 'Социальные сети должны быть массивом.',
            'partner_social_links.*.url.string' => 'Ссылка должна быть строкой.',
            'partner_social_links.*.url.regex' => 'Введите корректный URL.',
            'partner_social_links.*.is_enabled.boolean' => 'Некорректное значение чекбокса.',
            'partner_social_links.*.sort.integer' => 'Порядок должен быть числом.',
            'partner_social_links.*.sort.min' => 'Порядок не может быть меньше :min.',
            'partner_social_links.*.sort.max' => 'Порядок не может быть больше :max.',
        ];
    }
}

