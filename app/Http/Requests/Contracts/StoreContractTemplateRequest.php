<?php

namespace App\Http\Requests\Contracts;

use App\Services\Contracts\ContractTemplatePrefillSources;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreContractTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title'            => ['required', 'string', 'max:255'],
            'docx'             => ['required', 'file', 'extensions:docx', 'max:15360'],
            'email_subject'    => ['nullable', 'string', 'max:255'],
            'email_body_html'  => ['nullable', 'string', 'max:50000'],
            'fields'           => ['nullable', 'array'],
            'fields.*.key'     => ['required_with:fields', 'string', 'max:64'],
            'fields.*.label'   => ['nullable', 'string', 'max:255'],
            'fields.*.required'=> ['nullable', 'boolean'],
            'fields.*.prefill_source' => [
                'nullable',
                'string',
                Rule::in(ContractTemplatePrefillSources::keys()),
            ],
        ];
    }

    public function attributes(): array
    {
        return [
            'title'           => 'Название шаблона',
            'docx'            => 'Файл DOCX',
            'email_subject'   => 'Тема письма',
            'email_body_html' => 'Текст письма',
            'fields'          => 'Поля шаблона',
            'fields.*.label'  => 'Подпись поля',
            'fields.*.prefill_source' => 'Предзаполнение',
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'Укажите название шаблона.',
            'docx.required'  => 'Загрузите DOCX-файл шаблона.',
            'docx.mimes'     => 'Шаблон должен быть в формате DOCX.',
            'docx.max'       => 'DOCX не должен превышать :max КБ.',
        ];
    }
}
