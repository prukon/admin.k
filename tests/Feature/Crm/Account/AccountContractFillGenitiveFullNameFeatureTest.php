<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Account;

use App\Models\Contract;
use App\Rules\GenitiveFullName;
use Tests\Feature\Crm\Account\Concerns\InteractsWithAccountContractFill;
use Tests\Feature\Crm\CrmTestCase;

/**
 * ФИО в родительном в модалке fill: ≥10 символов и 3 слова, ошибка под полем.
 */
final class AccountContractFillGenitiveFullNameFeatureTest extends CrmTestCase
{
    use InteractsWithAccountContractFill;

    protected function setUp(): void
    {
        parent::setUp();

        $this->useAccountContractFillStorage();
        $this->withSession($this->accountDocumentsSession());
    }

    public function test_fill_html_shows_hint_under_both_genitive_fields(): void
    {
        $html = $this->getContractFillModalHtml($this->makeGenitiveContract());

        $this->assertSame(2, substr_count($html, GenitiveFullName::HINT));
        $this->assertStringContainsString('data-error-for="fields.parent_full_name_genitive"', $html);
        $this->assertStringContainsString('data-error-for="fields.child_full_name_genitive"', $html);
    }

    public function test_ajax_incomplete_genitive_returns_422_under_the_field(): void
    {
        $contract = $this->makeGenitiveContract();

        $response = $this->postJson(route('account.documents.generate', $contract), [
            'fields' => [
                'parent_full_name_genitive' => 'Иванова',
                'child_full_name_genitive'  => 'Иванова Ивана',
            ],
        ], $this->contractFillAjaxHeaders());

        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                'fields.parent_full_name_genitive',
                'fields.child_full_name_genitive',
            ]);

        $errors = $response->json('errors');
        $this->assertSame(GenitiveFullName::MESSAGE, $errors['fields.parent_full_name_genitive'][0] ?? null);
        $this->assertSame(GenitiveFullName::MESSAGE, $errors['fields.child_full_name_genitive'][0] ?? null);

        $contract->refresh();
        $this->assertSame(Contract::STATUS_AWAITING_CLIENT_FILL, $contract->status);
        $this->assertNull($contract->source_pdf_path);
    }

    public function test_ajax_valid_genitive_with_extra_spaces_saves_contract(): void
    {
        $contract = $this->makeGenitiveContract(required: false);

        $this->postJson(route('account.documents.generate', $contract), [
            'fields' => [
                'parent_full_name_genitive' => 'Иванова  Ивана   Ивановича',
                'child_full_name_genitive'  => '',
            ],
        ], $this->contractFillAjaxHeaders())
            ->assertOk();

        $contract->refresh();
        $this->assertSame(Contract::STATUS_DRAFT, $contract->status);
        $this->assertSame('Иванова  Ивана   Ивановича', $contract->filled_data['parent_full_name_genitive'] ?? null);
    }

    public function test_ajax_required_empty_genitive_uses_required_message(): void
    {
        $contract = $this->makeGenitiveContract(required: true);

        $response = $this->postJson(route('account.documents.generate', $contract), [
            'fields' => [
                'parent_full_name_genitive' => '',
                'child_full_name_genitive'  => 'Петрова Петра Петровича',
            ],
        ], $this->contractFillAjaxHeaders());

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['fields.parent_full_name_genitive']);

        $message = $response->json('errors')['fields.parent_full_name_genitive'][0] ?? null;
        $this->assertIsString($message);
        $this->assertStringNotContainsString('родительном падеже: фамилия', $message);
        $this->assertStringContainsString('обязательн', mb_strtolower($message));
    }

    public function test_non_ajax_incomplete_genitive_redirects_with_field_error(): void
    {
        $contract = $this->makeGenitiveContract();

        $response = $this->from(route('account.documents.index', ['fill' => $contract->id]))
            ->post(route('account.documents.generate', $contract), [
                '_token' => csrf_token(),
                'fields' => [
                    'parent_full_name_genitive' => 'Иванова Ивана',
                    'child_full_name_genitive'  => 'Петрова Петра Петровича',
                ],
            ]);

        $response->assertStatus(302);
        $response->assertSessionHasErrors(['fields.parent_full_name_genitive']);
        $this->assertSame(GenitiveFullName::MESSAGE, session('errors')?->first('fields.parent_full_name_genitive'));

        $contract->refresh();
        $this->assertSame(Contract::STATUS_AWAITING_CLIENT_FILL, $contract->status);
    }

    private function makeGenitiveContract(bool $required = false): Contract
    {
        return $this->makeAwaitingFillContract(
            [
                [
                    'key'      => 'parent_full_name_genitive',
                    'label'    => 'Родитель: ФИО в родительном падеже',
                    'required' => $required,
                ],
                [
                    'key'      => 'child_full_name_genitive',
                    'label'    => 'Ребёнок: ФИО в родительном падеже',
                    'required' => false,
                ],
            ],
            ['parent_full_name_genitive', 'child_full_name_genitive'],
        );
    }
}
