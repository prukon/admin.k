<?php

namespace App\Http\Requests\Contracts;

use App\Services\Contracts\ContractTemplatePrefillSources;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateContractTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function getRedirectUrl(): string
    {
        $template = $this->route('template');

        return route('contract-templates.index', ['edit' => $template->id]);
    }

    public function rules(): array
    {
        return [
            'title'            => ['required', 'string', 'max:255'],
            'docx'             => ['nullable', 'file', 'extensions:docx', 'max:15360'],
            'is_archived'      => ['nullable', 'boolean'],
            'fields'           => ['nullable', 'array'],
            'fields.*.key'     => ['required_with:fields', 'string', 'max:64'],
            'fields.*.label'   => ['nullable', 'string', 'max:255'],
            'fields.*.required'=> ['nullable', 'boolean'],
            'fields.*.prefill_source' => [
                'nullable',
                'string',
                Rule::in(ContractTemplatePrefillSources::keys()),
            ],
            'fields.*.fill_sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ];
    }

    public function attributes(): array
    {
        return (new StoreContractTemplateRequest())->attributes();
    }
}
