<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Users;

use App\Models\Contract;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Хелперы для создания договора со списка клиентов.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
abstract class AdminUsersContractCreateTestCase extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed'      => true,
        ]);
    }

    protected function grantPermission(User $actor, string $permissionName): void
    {
        DB::table('permission_role')->insertOrIgnore([
            'partner_id'    => $this->partner->id,
            'role_id'       => $actor->role_id,
            'permission_id' => $this->permissionId($permissionName),
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);
    }

    protected function grantUsersView(User $actor): void
    {
        $this->grantPermission($actor, 'users.view');
    }

    protected function grantContractsView(User $actor): void
    {
        $this->grantPermission($actor, 'contracts.view');
    }

    protected function studentRoleId(): int
    {
        return (int) Role::query()->where('name', 'user')->value('id');
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function createStudent(array $attributes = []): User
    {
        return User::factory()->create(array_merge([
            'partner_id' => $this->partner->id,
            'role_id'    => $this->studentRoleId(),
            'is_enabled' => 1,
            'lastname'   => 'Контрактов',
            'name'       => 'Ученик',
        ], $attributes));
    }

    protected function createContractForUser(User $user, string $status, ?\DateTimeInterface $createdAt = null): Contract
    {
        $contract = Contract::create([
            'school_id'       => $this->partner->id,
            'user_id'         => $user->id,
            'group_id'        => null,
            'source_pdf_path' => 'documents/test/contract-' . uniqid('', true) . '.pdf',
            'source_sha256'   => str_repeat('a', 64),
            'status'          => $status,
        ]);

        if ($createdAt !== null) {
            $contract->forceFill(['created_at' => $createdAt, 'updated_at' => $createdAt])->save();
        }

        return $contract->fresh();
    }

    protected function actingAsUsersViewer(bool $withContractsView = false): User
    {
        $missingPermission = $withContractsView ? 'users.view' : 'contracts.view';
        $actor = $this->createUserWithoutPermission($missingPermission, $this->partner);
        $this->actingAs($actor);
        $this->grantUsersView($actor);

        if ($withContractsView) {
            $this->grantContractsView($actor);
        }

        return $actor;
    }

    protected function fetchUsersDataRow(string $lastname): ?array
    {
        $response = $this->getJson('/admin/users/data?draw=1&start=0&length=100&name=' . urlencode($lastname));
        $response->assertOk();

        return collect($response->json('data'))->first(function (array $row) use ($lastname) {
            return str_contains((string) ($row['name'] ?? ''), $lastname);
        });
    }

    /**
     * @return array<string, string>
     */
    protected function ajaxHeaders(): array
    {
        return [
            'HTTP_ACCEPT'           => 'application/json',
            'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
        ];
    }

    protected function usersBladeSource(): string
    {
        return (string) file_get_contents(resource_path('views/admin/user.blade.php'));
    }

    protected function contractCreateModalSource(): string
    {
        return (string) file_get_contents(resource_path('views/contracts/partials/create-modal.blade.php'));
    }

    protected function extractJsFunction(string $source, string $functionName): string
    {
        $needle = 'function ' . $functionName . '(';
        $start = strpos($source, $needle);
        $this->assertNotFalse($start, 'Не найдена функция ' . $functionName);

        $brace = strpos($source, '{', $start);
        $this->assertNotFalse($brace);

        $depth = 0;
        $len = strlen($source);

        for ($i = $brace; $i < $len; $i++) {
            $ch = $source[$i];
            if ($ch === '{') {
                $depth++;
            } elseif ($ch === '}') {
                $depth--;
                if ($depth === 0) {
                    return substr($source, $start, $i - $start + 1);
                }
            }
        }

        $this->fail('Незакрытая функция ' . $functionName);
    }

    protected function runNodeScript(string $js): string
    {
        $tempFile = sys_get_temp_dir() . '/admin-users-contract-create-' . uniqid('', true) . '.js';
        file_put_contents($tempFile, $js);

        try {
            $output = [];
            $exitCode = 0;
            exec('node ' . escapeshellarg($tempFile) . ' 2>&1', $output, $exitCode);
            $this->assertSame(
                0,
                $exitCode,
                "node failed:\n" . implode("\n", $output)
            );

            return implode("\n", $output);
        } finally {
            @unlink($tempFile);
        }
    }

    /**
     * @param  array<string, mixed>  $row
     */
    protected function renderContractCellHtml(array $row): string
    {
        $fn = $this->extractJsFunction($this->usersBladeSource(), 'renderContractCell');
        $payload = json_encode($row, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        return $this->runNodeScript(
            "const KidsCrmTooltip = { escapeHtml: (s) => String(s == null ? '' : s) };\n"
            . $fn . "\n"
            . 'process.stdout.write(renderContractCell(' . $payload . '));'
        );
    }

    /**
     * @return list<array{method: string, url: string, data?: array<string, mixed>}>
     */
    protected function featureEndpoints(?User $student = null, ?Contract $contract = null): array
    {
        $student ??= $this->createStudent(['lastname' => 'Эндпоинтов']);
        $routes = [
            ['method' => 'GET', 'url' => route('admin.user1')],
            ['method' => 'GET', 'url' => '/admin/users/data?draw=1&start=0&length=10'],
            ['method' => 'GET', 'url' => route('contracts.users.search', ['q' => $student->lastname])],
            ['method' => 'GET', 'url' => route('contracts.user.group', ['user_id' => $student->id])],
            [
                'method'  => 'POST',
                'url'     => url('/client-contracts/check-balance'),
                'headers' => $this->ajaxHeaders(),
            ],
        ];

        if ($contract !== null) {
            $routes[] = ['method' => 'GET', 'url' => route('contracts.show', $contract->id)];
        }

        return $routes;
    }
}
