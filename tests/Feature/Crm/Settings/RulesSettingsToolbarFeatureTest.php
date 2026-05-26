<?php

namespace Tests\Feature\Crm\Settings;

use Tests\Feature\Crm\CrmTestCase;

/**
 * Страница «Настройки → Права и роли» (/admin/settings/rules): тулбар в стиле client-contracts.
 */
final class RulesSettingsToolbarFeatureTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession(['current_partner' => $this->partner->id]);
        $this->asAdmin();
    }

    public function test_rules_page_renders_toolbar_with_settings_and_history_actions(): void
    {
        $html = $this->get(route('admin.setting.rule'))
            ->assertOk()
            ->assertViewIs('admin.setting.index')
            ->assertViewHas('activeTab', 'rule')
            ->assertViewHas(['roles', 'permissions', 'groups'])
            ->getContent();

        $this->assertStringContainsString('>Настройки</h4>', $html);
        $this->assertStringContainsString('>Права и роли</h1>', $html);
        $this->assertStringContainsString('payments-report-surface', $html);
        $this->assertStringContainsString('admin-list-toolbar', $html);
        $this->assertStringContainsString('payments-report-toolbar-actions--many', $html);
        $this->assertStringContainsString('payments-report-toolbar-action', $html);

        $this->assertStringContainsString('>Настройки</span>', $html);
        $this->assertStringContainsString('>История</span>', $html);
        $this->assertStringContainsString('data-bs-target="#createRoleModal"', $html);
        $this->assertStringContainsString('data-bs-target="#historyModal"', $html);
        $this->assertStringContainsString('fa-gear payments-report-toolbar-icon', $html);
        $this->assertStringContainsString('fa-clock-rotate-left payments-report-toolbar-icon', $html);

        $this->assertStringContainsString('id="createRoleModal"', $html);
        $this->assertStringContainsString('id="historyModal"', $html);
        $this->assertStringContainsString('id="permission-accordion"', $html);
        $this->assertStringContainsString('id="rolesTable"', $html);

        $this->assertStringContainsString(route('logs.data.rule'), $html);

        $this->assertStringNotContainsString('btn btn-primary new-role', $html);
        $this->assertStringNotContainsString('wrap-icon btn', $html);

        $settingsPos = strpos($html, '>Настройки</span>');
        $historyPos = strpos($html, '>История</span>');
        $this->assertNotFalse($settingsPos);
        $this->assertNotFalse($historyPos);
        $this->assertLessThan($historyPos, $settingsPos);
    }
}
