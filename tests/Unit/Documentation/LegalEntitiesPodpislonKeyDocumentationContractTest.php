<?php

declare(strict_types=1);

namespace Tests\Unit\Documentation;

use PHPUnit\Framework\TestCase;

/**
 * Анонс /doc#legal-entities-podpislon-key-index совпадает с кодом:
 * ключ на юрлице, только superadmin, без фоллбэка PODPISLON_API_KEY, вебхук — секрет из env.
 */
final class LegalEntitiesPodpislonKeyDocumentationContractTest extends TestCase
{
    public function test_doc_index_announces_per_legal_entity_podpislon_api_key(): void
    {
        $html = $this->docFile('index.html');

        $this->assertStringContainsString('id="legal-entities-podpislon-key-index"', $html);
        $start = strpos($html, 'id="legal-entities-podpislon-key-index"');
        $this->assertNotFalse($start);
        $end = strpos($html, 'id="teams-create-edit-toast-index"');
        $this->assertNotFalse($end);
        $this->assertGreaterThan($start, $end);
        $chunk = substr($html, $start, $end - $start);

        $this->assertStringContainsString('PODPISLON_API_KEY', $chunk);
        $this->assertStringContainsString('PODPISLON_WEBHOOK_SECRET', $chunk);
        $this->assertStringContainsString('/admin/legal-entities', $chunk);
        $this->assertStringContainsString('superadmin', $chunk);
        $this->assertStringContainsString('type=password', $chunk);
        $this->assertStringContainsString('podpislon_api_key_set', $chunk);
        $this->assertStringContainsString("value = ''", $chunk);
        $this->assertStringContainsString('show.bs.modal', $chunk);
        $this->assertStringContainsString('REGISTERED_CRUD_LOCKED_FIELDS', $chunk);
        $this->assertStringContainsString('PodpislonCredentialsResolver', $chunk);
        $this->assertStringContainsString('legal_entity_unresolved', $chunk);
        $this->assertStringContainsString('podpislon_api_key_missing', $chunk);
        $this->assertStringContainsString('errors.podpislon_api_key', $chunk);
        $this->assertStringContainsString('не</b> <code>failed</code>', $chunk);
        $this->assertStringContainsString('contracts.legal_entity_id', $chunk);
        $this->assertStringContainsString('X-Api-Key', $chunk);
        $this->assertStringContainsString('не копируется', $chunk);
        $this->assertStringContainsString('admin-legal-entities#legal-entities-podpislon-key', $chunk);
        $this->assertStringContainsString('contracts#contracts-podpislon', $chunk);
        $this->assertStringContainsString('LegalEntitiesPodpislonApiKeyFeatureTest', $chunk);
        $this->assertStringContainsString('LegalEntitiesPodpislonApiKeyNonAjaxSafetyNetFeatureTest', $chunk);
        $this->assertStringContainsString('PodpislonCredentialsResolverTest', $chunk);
        $this->assertStringContainsString('PodpislonCredentialsHttpFeatureTest', $chunk);
        $this->assertStringContainsString('AccountContractFillPodpislonKeyFeatureTest', $chunk);
        $this->assertStringContainsString('LegalEntitiesPodpislonKeyDocumentationContractTest', $chunk);

        $this->assertStringNotContainsString('школа правит ключ', $chunk);
        $this->assertStringNotContainsString('ключ автоматически скопирован из .env', $chunk);
        $this->assertStringNotContainsString('PODPISLON_API_KEY больше нет в .env', $chunk);
        $this->assertStringNotContainsString('вебхук берёт ключ юрлица', $chunk);
    }

    public function test_related_doc_pages_link_announcement_and_do_not_claim_env_send_key(): void
    {
        $legal = $this->docFile('admin-legal-entities.html');
        $contracts = $this->docFile('contracts.html');
        $fill = $this->docFile('account-contract-fill.html');
        $templates = $this->docFile('contract-templates.html');
        $directories = $this->docFile('directories-hierarchy.html');

        $this->assertStringContainsString('id="legal-entities-podpislon-key"', $legal);
        $this->assertStringContainsString('/doc#legal-entities-podpislon-key-index', $legal);
        $this->assertStringContainsString('Глобальный <code>PODPISLON_API_KEY</code> для отправки документов не используется', $legal);
        $this->assertStringContainsString('podpislon_api_key</code> (только superadmin', $legal);

        $this->assertStringContainsString('id="contracts-podpislon"', $contracts);
        $this->assertStringContainsString('/doc#legal-entities-podpislon-key-index', $contracts);
        $this->assertStringContainsString('не из <code>PODPISLON_API_KEY</code>', $contracts);

        $this->assertStringContainsString('/doc#legal-entities-podpislon-key-index', $fill);
        $this->assertStringContainsString('errors.podpislon_api_key', $fill);

        $this->assertStringContainsString('/doc#legal-entities-podpislon-key-index', $templates);
        $this->assertStringContainsString('не плейсхолдер Word', $templates);

        $this->assertStringContainsString('/doc#legal-entities-podpislon-key-index', $directories);
        $this->assertStringContainsString('podpislon_api_key', $directories);
    }

    public function test_catalog_and_controller_title_mention_podpislon_key(): void
    {
        $index = $this->docFile('index.html');
        $controller = (string) file_get_contents(dirname(__DIR__, 3).'/app/Http/Controllers/DocumentationController.php');

        $this->assertStringContainsString('/doc#legal-entities-podpislon-key-index', $index);
        $this->assertStringContainsString('API-ключ Подпислона', $index);
        $this->assertStringContainsString('ЭП Подпислона с ключа юрлица', $index);

        $this->assertStringContainsString('API-ключ Подпислона (только superadmin, не .env)', $controller);
        $this->assertStringContainsString('ЭП Подпислона с ключа юрлица (не PODPISLON_API_KEY)', $controller);
        $this->assertStringContainsString('ключ Подпислона юрлица', $controller);
    }

    public function test_live_code_matches_documented_podpislon_key_rules(): void
    {
        $root = dirname(__DIR__, 3);

        $provider = (string) file_get_contents($root.'/app/Services/Signatures/Providers/PodpislonProvider.php');
        $this->assertStringContainsString('$this->apiKey = \'\';', $provider);
        $this->assertStringContainsString('authenticateFor(Contract $contract)', $provider);
        $this->assertStringContainsString('apiKeyForContract($contract)', $provider);
        $this->assertStringNotContainsString("config('services.podpislon.key')", $provider);

        $resolver = (string) file_get_contents($root.'/app/Services/Signatures/PodpislonCredentialsResolver.php');
        $this->assertStringContainsString('usedDefaultFallback', $resolver);
        $this->assertStringContainsString('legal_entity_unresolved', $resolver);
        $this->assertStringContainsString('podpislon_api_key_missing', $resolver);
        $this->assertStringContainsString('withTrashed()', $resolver);

        $model = (string) file_get_contents($root.'/app/Models/PartnerLegalEntity.php');
        $this->assertStringContainsString("'podpislon_api_key'", $model);
        $this->assertStringContainsString("'podpislon_api_key' => 'encrypted'", $model);

        $controller = (string) file_get_contents($root.'/app/Http/Controllers/Admin/PartnerLegalEntityController.php');
        $this->assertStringContainsString('REGISTERED_CRUD_LOCKED_FIELDS', $controller);
        $this->assertStringNotContainsString("'podpislon_api_key'", substr(
            $controller,
            (int) strpos($controller, 'REGISTERED_CRUD_LOCKED_FIELDS'),
            800
        ));
        $this->assertStringContainsString("podpislon_api_key_set", $controller);

        $fields = (string) file_get_contents($root.'/resources/views/admin/legal-entities/partials/crud-fields.blade.php');
        $this->assertStringContainsString('@if($showPodpislonApiKey)', $fields);
        $this->assertStringContainsString('type="password"', $fields);
        $this->assertStringContainsString('name="podpislon_api_key"', $fields);

        $indexJs = (string) file_get_contents($root.'/resources/views/admin/legal-entities/index.blade.php');
        $this->assertStringContainsString("podpislonKeyInput.value = ''", $indexJs);
        $this->assertStringContainsString("podpislonHint.classList.toggle('d-none', !data.podpislon_api_key_set)", $indexJs);
        $this->assertStringNotContainsString('podpislon_api_key: data.podpislon_api_key', $indexJs);

        $config = (string) file_get_contents($root.'/config/services.php');
        $this->assertStringContainsString("не из .env", $config);
        $this->assertStringContainsString("'webhook_secret' => env('PODPISLON_WEBHOOK_SECRET')", $config);

        $fillSign = (string) file_get_contents($root.'/app/Http/Controllers/AccountContractFillController.php');
        $this->assertStringContainsString("withErrors(\$result['errors']", $fillSign);
    }

    private function docFile(string $name): string
    {
        $path = dirname(__DIR__, 3).'/docs/documentation/'.$name;
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
