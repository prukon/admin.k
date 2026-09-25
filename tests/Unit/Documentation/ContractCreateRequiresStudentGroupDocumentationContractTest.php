<?php

declare(strict_types=1);

namespace Tests\Unit\Documentation;

use PHPUnit\Framework\TestCase;

/**
 * Анонс /doc#contract-create-requires-student-group-index: договор без группы ученика не создаётся,
 * в том числе из заявки.
 */
final class ContractCreateRequiresStudentGroupDocumentationContractTest extends TestCase
{
    public function test_doc_index_announces_group_requirement_for_admin_and_lead(): void
    {
        $html = $this->docFile('index.html');
        $start = strpos($html, 'id="contract-create-requires-student-group-index"');
        $this->assertNotFalse($start);
        $end = strpos($html, 'id="account-contract-genitive-full-name-index"');
        $this->assertNotFalse($end);
        $this->assertGreaterThan($start, $end);
        $chunk = substr($html, $start, $end - $start);

        $this->assertStringContainsString('Нельзя создать договор: у ученика нет группы.', $chunk);
        $this->assertStringContainsString('/admin/school-leads', $chunk);
        $this->assertStringContainsString('openCreateContractFromLead', $chunk);
        $this->assertStringContainsString('school_leads.team_id', $chunk);
        $this->assertStringContainsString('errors.group_id', $chunk);
        $this->assertStringContainsString('70', $chunk);
        $this->assertStringContainsString('ContractGroupPivotFeatureTest::test_store_rejects_student_without_group', $chunk);
        $this->assertStringContainsString('errors.send_contract', $chunk);
        $this->assertStringContainsString('school-leads-widget#school-lead-contract-inline', $chunk);
    }

    public function test_related_pages_describe_the_same_block(): void
    {
        $contracts = $this->docFile('contracts.html');
        $membership = $this->docFile('student-team-membership.html');
        $leads = $this->docFile('school-leads-widget.html');
        $users = $this->docFile('admin-users.html');

        foreach ([$contracts, $membership, $leads, $users] as $html) {
            $this->assertStringContainsString('Нельзя создать договор: у ученика нет группы.', $html);
            $this->assertStringContainsString('/doc#contract-create-requires-student-group-index', $html);
        }

        $this->assertStringContainsString('school_leads.team_id', $leads);
        $this->assertStringContainsString('id="school-lead-contract-inline"', $leads);

        $modal = (string) file_get_contents(dirname(__DIR__, 3).'/resources/views/contracts/partials/create-modal.blade.php');
        $this->assertStringContainsString('noStudentGroupMessage', $modal);
        $this->assertStringContainsString('contractStudentGroupCount === 0', $modal);
        $this->assertStringContainsString('ContractCreationService::NO_STUDENT_GROUP_MESSAGE', $modal);
    }

    private function docFile(string $name): string
    {
        $path = dirname(__DIR__, 3).'/docs/documentation/'.$name;
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
