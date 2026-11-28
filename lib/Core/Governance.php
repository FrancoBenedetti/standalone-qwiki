<?php
namespace Qwiki\Core;

class Governance {

    public static function getDefaults(): array {
        return [
            'responsibilityMode' => 'standard',
            'maintainerName' => '',
            'contentNotice' => '',
            'enforceNoticeOnSubwikis' => true,
            'abuseContact' => '',
            'showPageAttribution' => false,
            'requireEditorTerms' => false,
            'editorTermsVersion' => '1.0'
        ];
    }

    /**
     * Load the parent wiki's qwiki.json when running inside a subwiki.
     */
    public static function getParentConfig(): ?array {
        if (!Config::isSubwiki()) {
            return null;
        }
        $parentConfigFile = dirname(Config::getBaseDir()) . '/qwiki.json';
        if (!file_exists($parentConfigFile)) {
            return null;
        }
        $parentData = @json_decode(file_get_contents($parentConfigFile), true);
        return is_array($parentData) ? $parentData : null;
    }

    /**
     * Resolve the effective content responsibility notice and abuse contact
     * for the current wiki or subwiki, respecting root enforcement policies.
     */
    public static function resolveNotice(
        array $config,
        ?string $host = null,
        ?bool $isSubwiki = null,
        ?array $parentConfig = null
    ): array {
        $defaults = self::getDefaults();
        $gov = array_merge($defaults, isset($config['governance']) && is_array($config['governance']) ? $config['governance'] : []);

        if ($isSubwiki === null) {
            $isSubwiki = Config::isSubwiki();
        }
        if ($host === null || $host === '') {
            $host = $_SERVER['HTTP_HOST'] ?? 'this domain';
        }
        if ($isSubwiki && $parentConfig === null) {
            $parentConfig = self::getParentConfig();
        }

        $parentGov = ($isSubwiki && is_array($parentConfig) && isset($parentConfig['governance']) && is_array($parentConfig['governance']))
            ? array_merge($defaults, $parentConfig['governance'])
            : null;

        $enforcedByParent = $isSubwiki && $parentGov !== null && !empty($parentGov['enforceNoticeOnSubwikis']);

        $mode = in_array($gov['responsibilityMode'], ['standard', 'independent', 'domain-managed'], true)
            ? $gov['responsibilityMode']
            : 'standard';

        $isIndependent = $isSubwiki && $mode === 'independent';
        $maintainerName = trim((string)$gov['maintainerName']);
        $wikiTitle = trim((string)($config['title'] ?? 'Qwiki'));

        // Resolve badge label
        if ($isIndependent) {
            $badge = __('governance.badge_independent', 'Independent Subwiki');
        } elseif ($isSubwiki && $mode === 'domain-managed') {
            $badge = __('governance.badge_domain_managed', 'Official Subwiki');
        } else {
            $badge = __('governance.badge_contributors', "Contributors' Content");
        }

        // Resolve notice text
        $customNotice = trim((string)$gov['contentNotice']);
        $parentNotice = $parentGov !== null ? trim((string)$parentGov['contentNotice']) : '';

        if ($enforcedByParent && $parentNotice !== '') {
            $text = $parentNotice;
        } elseif ($customNotice !== '') {
            $text = $customNotice;
        } elseif ($isIndependent) {
            if ($maintainerName !== '') {
                $text = __(
                    'governance.notice_independent_named',
                    'This subwiki ({wiki}) is independently managed by {maintainer}. Content is the responsibility of its contributors and does not necessarily represent {host}.',
                    ['wiki' => $wikiTitle, 'maintainer' => $maintainerName, 'host' => $host]
                );
            } else {
                $text = __(
                    'governance.notice_independent',
                    'This subwiki ({wiki}) is independently managed by its administrators and editors. Content is the responsibility of its contributors and does not necessarily represent {host}.',
                    ['wiki' => $wikiTitle, 'host' => $host]
                );
            }
        } elseif ($isSubwiki && $mode === 'domain-managed') {
            $ownerLabel = $maintainerName !== '' ? $maintainerName : $host;
            $text = __(
                'governance.notice_domain_managed',
                'This subwiki is maintained by {owner}. Views expressed by individual contributors remain their own.',
                ['owner' => $ownerLabel]
            );
        } else {
            if ($maintainerName !== '') {
                $text = __(
                    'governance.notice_standard_named',
                    'Content on this wiki is authored and maintained by {maintainer} and its designated editors.',
                    ['maintainer' => $maintainerName]
                );
            } else {
                $text = __(
                    'governance.notice_standard',
                    'Content on this wiki is authored and maintained by its designated editors. Views expressed belong to the contributors.'
                );
            }
        }

        // Resolve abuse contact (subwiki contact with parent fallback / enforcement)
        $localContact = trim((string)$gov['abuseContact']);
        $parentContact = $parentGov !== null ? trim((string)$parentGov['abuseContact']) : '';

        $abuseContact = $localContact;
        $parentAbuseContact = '';
        if ($enforcedByParent && $parentContact !== '') {
            if ($abuseContact === '') {
                $abuseContact = $parentContact;
            } elseif (strcasecmp($abuseContact, $parentContact) !== 0) {
                $parentAbuseContact = $parentContact;
            }
        } elseif ($abuseContact === '' && $parentContact !== '') {
            $abuseContact = $parentContact;
        }

        return [
            'badge' => $badge,
            'text' => $text,
            'abuseContact' => $abuseContact,
            'parentAbuseContact' => $parentAbuseContact,
            'maintainerName' => $maintainerName,
            'isIndependent' => $isIndependent,
            'enforcedByParent' => $enforcedByParent,
            'responsibilityMode' => $mode
        ];
    }

    /**
     * Build a safe href for an abuse/takedown contact (email, mailto:, or https:// URL).
     * Rejects javascript:, data:, and other unsafe schemes.
     */
    public static function buildReportHref(string $contact, string $pageUrl = '', string $pageTitle = '', string $wikiTitle = ''): string {
        $contact = trim($contact);
        if ($contact === '') {
            return '';
        }

        $subject = 'Content Report';
        if ($wikiTitle !== '') {
            $subject .= ' - ' . $wikiTitle;
        }
        if ($pageTitle !== '') {
            $subject .= ': ' . $pageTitle;
        }

        $bodyLines = ['I would like to report content on this page:'];
        if ($pageUrl !== '') {
            $bodyLines[] = 'URL: ' . $pageUrl;
        }
        if ($pageTitle !== '') {
            $bodyLines[] = 'Page: ' . $pageTitle;
        }
        $bodyLines[] = '';
        $bodyLines[] = 'Reason:';
        $body = implode("\n", $bodyLines);

        if (filter_var($contact, FILTER_VALIDATE_EMAIL)) {
            return 'mailto:' . $contact
                . '?subject=' . rawurlencode($subject)
                . '&body=' . rawurlencode($body);
        }

        if (stripos($contact, 'mailto:') === 0) {
            $emailPart = substr($contact, 7);
            $qPos = strpos($emailPart, '?');
            $addr = $qPos !== false ? substr($emailPart, 0, $qPos) : $emailPart;
            if (!filter_var(trim($addr), FILTER_VALIDATE_EMAIL)) {
                return '';
            }
            $sep = $qPos !== false ? '&' : '?';
            return $contact . $sep . 'subject=' . rawurlencode($subject) . '&body=' . rawurlencode($body);
        }

        $parsed = @parse_url($contact);
        if (is_array($parsed) && isset($parsed['scheme']) && in_array(strtolower($parsed['scheme']), ['http', 'https'], true)) {
            if (filter_var($contact, FILTER_VALIDATE_URL)) {
                return $contact;
            }
        }

        return '';
    }

    /**
     * Check whether the given user has accepted the required editor terms version.
     */
    public static function hasAcceptedTerms(array $user, array $config): bool {
        $gov = isset($config['governance']) && is_array($config['governance']) ? $config['governance'] : [];
        if (empty($gov['requireEditorTerms'])) {
            return true;
        }
        $requiredVersion = trim((string)($gov['editorTermsVersion'] ?? '1.0'));
        if ($requiredVersion === '') {
            $requiredVersion = '1.0';
        }
        $acceptedAt = trim((string)($user['acceptedTermsAt'] ?? ''));
        $acceptedVersion = trim((string)($user['acceptedTermsVersion'] ?? ''));
        return $acceptedAt !== '' && $acceptedVersion === $requiredVersion;
    }

    /**
     * Sanitize governance settings input before persisting to qwiki.json.
     */
    public static function sanitizeInput(array $input, bool $isSubwiki = false): array {
        $defaults = self::getDefaults();
        $mode = (string)($input['responsibilityMode'] ?? $defaults['responsibilityMode']);
        if (!in_array($mode, ['standard', 'independent', 'domain-managed'], true)) {
            $mode = 'standard';
        }

        $version = trim((string)($input['editorTermsVersion'] ?? $defaults['editorTermsVersion']));
        if ($version === '') {
            $version = '1.0';
        }

        $sanitized = [
            'responsibilityMode' => $mode,
            'maintainerName' => mb_substr(trim((string)($input['maintainerName'] ?? '')), 0, 120),
            'contentNotice' => mb_substr(trim((string)($input['contentNotice'] ?? '')), 0, 1000),
            'enforceNoticeOnSubwikis' => $isSubwiki ? false : !empty($input['enforceNoticeOnSubwikis']),
            'abuseContact' => mb_substr(trim((string)($input['abuseContact'] ?? '')), 0, 255),
            'showPageAttribution' => !empty($input['showPageAttribution']),
            'requireEditorTerms' => !empty($input['requireEditorTerms']),
            'editorTermsVersion' => mb_substr($version, 0, 32)
        ];

        return $sanitized;
    }
}
