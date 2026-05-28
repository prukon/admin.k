@if(!empty($showFamilyStudentSwitcher) && isset($familyStudents) && $familyStudents->count() > 1)
    <div class="family-student-switcher px-3 pb-2">
        <form method="post" action="{{ route('cabinet.active-student.switch') }}" class="mb-0">
            @csrf
            <label for="family-active-student" class="form-label text-light small mb-1">Просмотр данных ученика</label>
            <select name="student_user_id"
                    id="family-active-student"
                    class="form-select form-select-sm"
                    onchange="this.form.submit()">
                @foreach($familyStudents as $studentOption)
                    <option value="{{ $studentOption->id }}"
                        @selected(isset($activeStudent) && (int) $activeStudent->id === (int) $studentOption->id)>
                        {{ $studentOption->full_name ?: ('Ученик #' . $studentOption->id) }}
                    </option>
                @endforeach
            </select>
        </form>
    </div>
@endif
