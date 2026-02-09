<?php

namespace Tests\Feature;

use App\Models\ContactSubmission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContactSubmissionSoftDeleteTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function contact_submission_model_soft_delete_works(): void
    {
        $submission = ContactSubmission::create([
            'name'   => 'Тест',
            'phone'  => '+7 900 000-00-00',
            'status' => 'new',
        ]);

        // sanity-check: запись реально есть
        $this->assertDatabaseHas('contact_submissions', [
            'id' => $submission->id,
        ]);

        // soft delete
        $submission->delete();

        // перечитываем из БД
        $fresh = ContactSubmission::withTrashed()->find($submission->id);

        $this->assertNotNull(
            $fresh,
            'Запись должна существовать при withTrashed().'
        );

        $this->assertNotNull(
            $fresh->deleted_at,
            'deleted_at должен быть заполнен после soft delete.'
        );
    }
}