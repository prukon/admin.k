<?php

namespace App\Http\Requests\Setting;

use App\Support\CabinetDiagnostics;
use Illuminate\Http\Exceptions\HttpResponseException;

class ToggleCabinetDiagnosticsRequest extends SettingsJsonRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && $user->can(CabinetDiagnostics::PERMISSION);
    }

    protected function prepareForValidation(): void
    {
        if (! $this->exists('cabinetDiagnostics')) {
            return;
        }

        $raw = $this->input('cabinetDiagnostics');
        $bool = filter_var($raw, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        if ($bool !== null) {
            $this->merge(['cabinetDiagnostics' => $bool]);
        }
    }

    public function rules(): array
    {
        return [
            'cabinetDiagnostics' => ['required', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'cabinetDiagnostics' => 'Оверлей статуса Reverb',
        ];
    }

    public function messages(): array
    {
        return [
            'cabinetDiagnostics.required' => 'Укажите состояние оверлея статуса Reverb.',
            'cabinetDiagnostics.boolean' => 'Некорректное значение оверлея статуса Reverb.',
        ];
    }

    protected function failedAuthorization(): void
    {
        throw new HttpResponseException(
            response()->json([
                'success' => false,
                'message' => 'Оверлей статуса Reverb доступен только суперадмину.',
            ], 403)
        );
    }
}
