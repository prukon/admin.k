<?php

namespace App\Services\Contracts;

use App\Models\Contract;
use App\Models\User;
use Carbon\CarbonInterface;

final class ContractInvitationEmailRenderer
{
    private const FALLBACK_PARTNER_NAME = 'KidsCRM.online';

    public function renderSubject(Contract $contract, User $student): string
    {
        $template = $this->resolveSubjectTemplate($contract);

        return $this->applyPlaceholders($template, $contract, $student, escapeForHtml: false);
    }

    public function renderBodyHtml(Contract $contract, User $student): string
    {
        $template = $this->resolveBodyTemplate($contract);
        $html = $this->applyPlaceholders($template, $contract, $student, escapeForHtml: true);

        return '<div style="font-family:system-ui,sans-serif;line-height:1.5">' . $html . '</div>';
    }

    private function resolveSubjectTemplate(Contract $contract): string
    {
        $version = $contract->templateVersion;

        return $version?->resolvedEmailSubject() ?? ContractTemplateEmailDefaults::subject();
    }

    private function resolveBodyTemplate(Contract $contract): string
    {
        $version = $contract->templateVersion;

        return $version?->resolvedEmailBodyHtml() ?? ContractTemplateEmailDefaults::bodyHtml();
    }

    private function applyPlaceholders(
        string $template,
        Contract $contract,
        User $student,
        bool $escapeForHtml,
    ): string {
        $replacements = $this->buildReplacements($contract, $student, $escapeForHtml);

        return str_replace(array_keys($replacements), array_values($replacements), $template);
    }

    /**
     * @return array<string, string>
     */
    private function buildReplacements(Contract $contract, User $student, bool $escapeForHtml): array
    {
        $contract->loadMissing('templateVersion.template.partner');

        $documentsUrl = url('/account-settings/documents');
        $childFullName = trim((string) ($student->full_name ?? ''));
        if ($childFullName === '') {
            $childFullName = trim(($student->lastname ?? '') . ' ' . ($student->name ?? ''));
        }

        $partnerTitle = trim((string) ($contract->templateVersion?->template?->partner?->title ?? ''));
        $partnerName = $partnerTitle !== '' ? $partnerTitle : self::FALLBACK_PARTNER_NAME;

        $values = [
            ContractTemplateEmailDefaults::PLACEHOLDER_DOCUMENTS_URL   => $documentsUrl,
            ContractTemplateEmailDefaults::PLACEHOLDER_CHILD_FULL_NAME => $childFullName,
            ContractTemplateEmailDefaults::PLACEHOLDER_PARTNER_NAME    => $partnerName,
            ContractTemplateEmailDefaults::PLACEHOLDER_FILL_DEADLINE   => $this->formatFillDeadline($contract->fill_expires_at),
            ContractTemplateEmailDefaults::PLACEHOLDER_CONTRACT_ID     => (string) $contract->id,
        ];

        if (!$escapeForHtml) {
            return $values;
        }

        $escaped = [];
        foreach ($values as $token => $value) {
            $escaped[$token] = e($value);
        }

        return $escaped;
    }

    private function formatFillDeadline(?CarbonInterface $expiresAt): string
    {
        $deadline = $expiresAt ?? now()->addDays(Contract::FILL_TTL_DAYS);

        return $deadline->copy()->locale('ru')->translatedFormat('j F Y');
    }
}
