<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Admin\Concerns\LocationFieldRules;
use Illuminate\Foundation\Http\FormRequest;

class UpdateLocationRequest extends FormRequest
{
    use LocationFieldRules;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->prepareLocationDistrictId();

        if ($this->user()?->can('locations.view')) {
            if (! $this->has('team_ids')) {
                $this->merge(['team_ids' => []]);
            } elseif (! is_array($this->input('team_ids'))) {
                $this->merge(['team_ids' => []]);
            }
        }

        if (! $this->has('admin_user_ids')) {
            $this->merge(['admin_user_ids' => []]);
        } elseif (! is_array($this->input('admin_user_ids'))) {
            $this->merge(['admin_user_ids' => []]);
        }
    }

    public function rules(): array
    {
        return $this->locationFieldRules($this->resolveLocationIdForUpdate());
    }

    public function attributes(): array
    {
        return $this->locationFieldAttributes();
    }

    public function messages(): array
    {
        return $this->locationFieldMessages();
    }
}
