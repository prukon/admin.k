<?php

declare(strict_types=1);

namespace App\Http\Requests\Tinkoff;

use App\Models\TinkoffPayment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class TinkoffDealCloseRequest extends FormRequest
{
    public function authorize(): bool
    {
        $actor = $this->user();

        return $actor !== null && $actor->can('manage.payment.method.tbank');
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'deal' => trim((string) $this->route('deal')),
        ]);
    }

    public function rules(): array
    {
        return [
            'deal' => ['required', 'string', 'max:64'],
        ];
    }

    public function attributes(): array
    {
        return [
            'deal' => 'сделка',
        ];
    }

    public function messages(): array
    {
        return [
            'deal.required' => 'Не найден платеж с таким DealId',
            'deal.string' => 'Не найден платеж с таким DealId',
            'deal.max' => 'Не найден платеж с таким DealId',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $dealId = $this->dealId();
            if ($dealId === '') {
                $validator->errors()->add('tinkoff', 'Не найден платеж с таким DealId');

                return;
            }

            $exists = TinkoffPayment::query()->where('deal_id', $dealId)->exists();
            if (! $exists) {
                $validator->errors()->add('tinkoff', 'Не найден платеж с таким DealId');
            }
        });
    }

    public function dealId(): string
    {
        return trim((string) $this->input('deal'));
    }
}
