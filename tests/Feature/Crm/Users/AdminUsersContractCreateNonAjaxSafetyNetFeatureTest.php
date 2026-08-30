<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Users;

use App\Models\Contract;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * P1: native POST создания договора со страницы /admin/users (Referer) —
 * 302 на карточку, запись в БД; ошибки валидации по полям, не пустой 200.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class AdminUsersContractCreateNonAjaxSafetyNetFeatureTest extends AdminUsersContractCreateTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->useWritableTestStoragePath();
        Storage::fake();
        $this->actingAsUsersViewer(withContractsView: true);
        config(['billing.contract_create_fee' => 70.00]);
        $this->partner->wallet_balance_cents = 50000;
        $this->partner->save();
    }

    public function test_store_from_clients_page_redirects_to_contract_card_and_creates_row(): void
    {
        $student = $this->createStudent(['lastname' => 'NonAjaxCreate']);
        $pdf = UploadedFile::fake()->create('from-users.pdf', 20, 'application/pdf');

        $response = $this->from(route('admin.user1'))
            ->post(route('contracts.store'), [
                'creation_mode' => Contract::CREATION_MODE_PDF,
                'user_id'       => $student->id,
                'pdf'           => $pdf,
            ]);

        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertNotSame(200, $response->getStatusCode());

        $contract = Contract::query()->firstOrFail();
        $response->assertSessionHasNoErrors()
            ->assertRedirect(route('contracts.show', $contract->id));

        $this->assertDatabaseHas('contracts', [
            'id'      => $contract->id,
            'user_id' => $student->id,
            'school_id' => $this->partner->id,
        ]);
        $this->assertSame(50000 - 7000, (int) $this->partner->fresh()->wallet_balance_cents);
    }

    public function test_store_without_pdf_redirects_with_field_error_and_does_not_create(): void
    {
        $student = $this->createStudent(['lastname' => 'БезPdfCreate']);

        $response = $this->from(route('admin.user1'))
            ->post(route('contracts.store'), [
                'creation_mode' => Contract::CREATION_MODE_PDF,
                'user_id'       => $student->id,
            ]);

        $response->assertStatus(302)
            ->assertSessionHasErrors(['pdf'])
            ->assertRedirect(route('contracts.index', [
                'create'  => 1,
                'user_id' => $student->id,
            ]));

        $this->assertSame(
            'Загрузите PDF-файл договора.',
            session('errors')->first('pdf')
        );
        $this->assertSame(0, Contract::query()->count());
        $this->assertSame(50000, (int) $this->partner->fresh()->wallet_balance_cents);
    }

    public function test_store_without_student_redirects_with_user_id_field_error(): void
    {
        $response = $this->from(route('admin.user1'))
            ->post(route('contracts.store'), [
                'creation_mode' => Contract::CREATION_MODE_PDF,
            ]);

        $response->assertStatus(302)
            ->assertSessionHasErrors(['user_id'])
            ->assertRedirect(route('contracts.index', ['create' => 1]));

        $this->assertSame('Выберите ученика.', session('errors')->first('user_id'));
        $this->assertSame(0, Contract::query()->count());
    }

    public function test_native_get_users_page_returns_html_not_empty_200(): void
    {
        $response = $this->get(route('admin.user1'));
        $response->assertOk();
        $this->assertNotSame('', trim((string) $response->getContent()));
        $this->assertStringContainsString('id="createContractModal"', $response->getContent());
        $this->assertStringContainsString('text/html', (string) $response->headers->get('Content-Type'));
    }

    public function test_native_get_users_data_returns_json_not_html_page(): void
    {
        $response = $this->get('/admin/users/data?draw=1&start=0&length=10');
        $response->assertOk();
        $this->assertIsArray($response->json('data'));
        $this->assertStringNotContainsString('<html', (string) $response->getContent());
    }

    public function test_insufficient_balance_does_not_create_contract_from_clients_page(): void
    {
        $this->partner->wallet_balance_cents = 0;
        $this->partner->save();

        $student = $this->createStudent(['lastname' => 'НетБалансаCreate']);
        $pdf = UploadedFile::fake()->create('no-balance.pdf', 20, 'application/pdf');

        $response = $this->from(route('admin.user1'))
            ->post(route('contracts.store'), [
                'creation_mode' => Contract::CREATION_MODE_PDF,
                'user_id'       => $student->id,
                'pdf'           => $pdf,
            ]);

        $this->assertNotSame(500, $response->getStatusCode());
        $response->assertStatus(302);
        $this->assertSame(0, Contract::query()->count());
    }
}
