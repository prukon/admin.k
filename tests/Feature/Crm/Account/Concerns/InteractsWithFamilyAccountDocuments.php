<?php

namespace Tests\Feature\Crm\Account\Concerns;

use App\Models\Contract;
use App\Models\ParentProfile;
use App\Models\Role;
use App\Models\User;
use App\Services\Contracts\ContractInvitationEmailRenderer;
use App\Services\Contracts\ContractTemplatePrefillSources;
use App\Services\Users\FamilyStudentContextService;
use Illuminate\Support\Facades\Storage;

trait InteractsWithFamilyAccountDocuments
{
    private ParentProfile $sharedParent;

    private User $brother1;

    private User $brother2;

    protected function seedFamilyStudents(): void
    {
        $roleId = (int) Role::query()->where('name', 'user')->value('id');
        $this->assertGreaterThan(0, $roleId);

        $this->sharedParent = ParentProfile::factory()->create([
            'partner_id' => $this->partner->id,
            'lastname'   => 'Иванова',
            'firstname'  => 'Мария',
            'email'      => 'mama@family.test',
        ]);
        $this->brother1 = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id'    => $roleId,
            'parent_id'  => $this->sharedParent->id,
            'lastname'   => 'Иванов',
            'name'       => 'Петя',
            'is_enabled' => true,
        ]);
        $this->brother2 = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id'    => $roleId,
            'parent_id'  => $this->sharedParent->id,
            'lastname'   => 'Иванов',
            'name'       => 'Вася',
            'is_enabled' => true,
        ]);
    }

    /**
     * @return array<string, int|bool>
     */
    protected function familyDocumentsSession(?int $activeStudentId = null): array
    {
        $session = [
            'current_partner' => $this->partner->id,
            '2fa:passed'      => true,
        ];
        if ($activeStudentId !== null) {
            $session[FamilyStudentContextService::SESSION_KEY] = $activeStudentId;
        }

        return $session;
    }

    protected function actingAsBrother1(?int $activeStudentId = null): self
    {
        $this->actingAs($this->brother1)->withSession($this->familyDocumentsSession($activeStudentId));

        return $this;
    }

    /**
     * @param array<string, mixed> $overrides
     */
    protected function makeContractFor(User $student, string $status, array $overrides = []): Contract
    {
        return Contract::create(array_merge([
            'school_id'       => $this->partner->id,
            'user_id'         => $student->id,
            'group_id'        => null,
            'creation_mode'   => Contract::CREATION_MODE_PDF,
            'source_pdf_path' => 'documents/family-' . $student->id . '.pdf',
            'source_sha256'   => str_repeat('a', 64),
            'status'          => $status,
            'provider'        => 'podpislon',
        ], $overrides));
    }

    protected function makeAwaitingFillContractFor(User $student): Contract
    {
        $contract = $this->makeAwaitingFillContract([
            ['key' => 'parent_lastname', 'label' => 'Фамилия', 'required' => true],
            ['key' => 'parent_firstname', 'label' => 'Имя', 'required' => true],
        ]);
        $contract->update(['user_id' => $student->id]);

        return $contract->fresh();
    }

    protected function makeRequiredPassportEmailContractFor(User $student): Contract
    {
        $contract = $this->makeAwaitingFillContract(
            [
                [
                    'key'            => 'parent_passport',
                    'label'          => 'Родитель: паспорт',
                    'required'       => true,
                    'prefill_source' => ContractTemplatePrefillSources::PARENT_PASSPORT,
                ],
                [
                    'key'            => 'parent_email',
                    'label'          => 'Родитель: email',
                    'required'       => true,
                    'prefill_source' => ContractTemplatePrefillSources::PARENT_EMAIL,
                ],
            ],
            ['parent_passport', 'parent_email'],
        );
        $contract->update(['user_id' => $student->id]);

        return $contract->fresh();
    }

    protected function putPdfOnDisk(string $path): string
    {
        Storage::disk()->put($path, '%PDF-1.4');

        return $path;
    }

    protected function invitationDocumentsUrl(Contract $contract, User $student): string
    {
        return app(ContractInvitationEmailRenderer::class)->documentsUrl($contract, $student);
    }

    protected function sidebarChunk(string $html): string
    {
        $sidebarStart = strpos($html, 'nav nav-pills nav-sidebar');
        $this->assertNotFalse($sidebarStart, 'Sidebar not found in response');

        return substr($html, (int) $sidebarStart, 8000);
    }
}
