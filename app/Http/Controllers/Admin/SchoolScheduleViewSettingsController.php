<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\SchoolScheduleViewSettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

final class SchoolScheduleViewSettingsController extends Controller
{
    public function __construct(
        private SchoolScheduleViewSettingsService $viewSettings
    ) {}

    /**
     * Текущие границы отображения календаря расписания школы.
     */
    public function show(): JsonResponse
    {
        $pair = $this->viewSettings->getForUserId((int) Auth::id());

        return response()->json($pair);
    }

    /**
     * Сохранить границы отображения (визуально; на слоты и записи не влияет).
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'view_start_min' => ['required', 'integer'],
            'view_end_min' => ['required', 'integer'],
        ], [
            'view_start_min.required' => 'Укажите время начала отображения.',
            'view_end_min.required' => 'Укажите время окончания отображения.',
            'view_start_min.integer' => 'Некорректное время начала.',
            'view_end_min.integer' => 'Некорректное время окончания.',
        ]);

        $validator->after(function (\Illuminate\Validation\Validator $v): void {
            $start = (int) ($v->getData()['view_start_min'] ?? 0);
            $end = (int) ($v->getData()['view_end_min'] ?? 0);
            if ($start % 30 !== 0) {
                $v->errors()->add('view_start_min', 'Интервал должен быть кратен 30 минутам.');
            }
            if ($end % 30 !== 0) {
                $v->errors()->add('view_end_min', 'Интервал должен быть кратен 30 минутам.');
            }
            if ($start < 0 || $start > 1380) {
                $v->errors()->add('view_start_min', 'Время начала вне допустимого диапазона.');
            }
            if ($end < 60 || $end > 1440) {
                $v->errors()->add('view_end_min', 'Время окончания вне допустимого диапазона.');
            }
            if ($start % 30 === 0 && $end % 30 === 0 && $end < $start + 60) {
                $v->errors()->add('view_end_min', 'Интервал отображения не менее 1 часа.');
            }
        });

        $validated = $validator->validate();

        $start = (int) $validated['view_start_min'];
        $end = (int) $validated['view_end_min'];

        $this->viewSettings->saveForUserId((int) Auth::id(), $start, $end);

        $saved = $this->viewSettings->getForUserId((int) Auth::id());

        return response()->json([
            'success' => true,
            'view_start_min' => $saved['view_start_min'],
            'view_end_min' => $saved['view_end_min'],
        ]);
    }
}
