<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Report;

use App\Models\User;
use App\Services\PartnerContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class PaymentReportUserCardRequest extends FormRequest
{
    private ?User $student = null;

    private bool $studentMissing = false;

    public function authorize(): bool
    {
        $actor = $this->user();
        if ($actor === null || (! $actor->can('reports.view') && ! $actor->can('schedule.view'))) {
            return false;
        }

        $routeUser = $this->route('user');
        if (! is_numeric($routeUser)) {
            $this->studentMissing = true;

            return false;
        }

        $student = User::withTrashed()->find((int) $routeUser);
        if (! $student instanceof User) {
            $this->studentMissing = true;

            return false;
        }

        $partnerId = app(PartnerContext::class)->partnerId();
        if (! $partnerId || (int) $student->partner_id !== (int) $partnerId) {
            return false;
        }

        $this->student = $student;

        return true;
    }

    public function student(): User
    {
        if (! $this->student instanceof User) {
            throw new \RuntimeException('Карточка пользователя не разрешена.');
        }

        return $this->student;
    }

    public function rules(): array
    {
        return [];
    }

    public function attributes(): array
    {
        return [
            'user' => 'пользователь',
        ];
    }

    public function messages(): array
    {
        return [
            'user.unauthorized' => 'Нет доступа к карточке этого пользователя.',
            'user.missing' => 'Пользователь не найден.',
        ];
    }

    protected function failedAuthorization(): void
    {
        if ($this->studentMissing) {
            $message = $this->messages()['user.missing'];

            throw new HttpResponseException(response()->json([
                'message' => $message,
                'errors' => ['user' => [$message]],
            ], 404));
        }

        $message = $this->messages()['user.unauthorized'];

        throw new HttpResponseException(response()->json([
            'message' => $message,
            'errors' => ['user' => [$message]],
        ], 403));
    }
}
