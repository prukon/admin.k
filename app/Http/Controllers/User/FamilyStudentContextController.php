<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Services\Users\FamilyStudentContextService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class FamilyStudentContextController extends Controller
{
    public function switch(Request $request, FamilyStudentContextService $familyContext): RedirectResponse
    {
        $validated = $request->validate([
            'student_user_id' => ['required', 'integer', 'min:1'],
        ]);

        $familyContext->setActiveStudent(
            $request->user(),
            (int) $validated['student_user_id']
        );

        return back();
    }
}
