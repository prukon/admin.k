<?php

namespace App\Http\Requests\Setting;

class SaveMenuItemsRequest extends SettingsJsonRequest
{
    private const URL_REGEX = '/^(\/[\\S]*|https?:\\/\\/[^\s]+)$/';
    private const MENU_ITEM_NAME_REGEX = '/^[\pL\pN\s]+$/u';

    protected function prepareForValidation(): void
    {
        $items = $this->input('menu_items');
        if (!is_array($items)) {
            return;
        }

        // Нормализуем пробелы, чтобы required/regex работали предсказуемо
        foreach ($items as $k => $item) {
            if (!is_array($item)) {
                continue;
            }
            if (array_key_exists('name', $item) && is_string($item['name'])) {
                $items[$k]['name'] = trim($item['name']);
            }
            if (array_key_exists('link', $item) && is_string($item['link'])) {
                $items[$k]['link'] = trim($item['link']);
            }
        }

        $this->merge(['menu_items' => $items]);
    }

    public function rules(): array
    {
        return [
            'menu_items' => ['nullable', 'array'],
            'menu_items.*.name' => ['required', 'string', 'max:20', 'regex:' . self::MENU_ITEM_NAME_REGEX],
            'menu_items.*.link' => ['required', 'string', 'regex:' . self::URL_REGEX],
            'menu_items.*.target_blank' => ['nullable', 'boolean'],

            'deleted_items' => ['nullable', 'array'],
            'deleted_items.*' => ['integer'],
        ];
    }

    public function attributes(): array
    {
        return [
            'menu_items' => 'Пункты меню',
            'menu_items.*.name' => 'Название пункта меню',
            'menu_items.*.link' => 'Ссылка',
            'menu_items.*.target_blank' => 'Открывать в новой вкладке',
            'deleted_items' => 'Удаляемые пункты',
        ];
    }

    public function messages(): array
    {
        return [
            'menu_items.array' => 'Пункты меню должны быть массивом.',

            'menu_items.*.name.required' => 'Заполните название.',
            'menu_items.*.name.string' => 'Название должно быть строкой.',
            'menu_items.*.name.max' => 'Название не может быть длиннее 20 символов.',
            'menu_items.*.name.regex' => 'Название не может содержать спецсимволы.',

            'menu_items.*.link.required' => 'Заполните ссылку.',
            'menu_items.*.link.string' => 'Ссылка должна быть строкой.',
            'menu_items.*.link.regex' => 'Введите корректный URL.',

            'menu_items.*.target_blank.boolean' => 'Некорректное значение чекбокса.',

            'deleted_items.array' => 'Удаляемые пункты должны быть массивом.',
            'deleted_items.*.integer' => 'Некорректный ID удаляемого пункта.',
        ];
    }
}

