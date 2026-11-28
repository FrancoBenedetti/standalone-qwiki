<?php
require_once __DIR__ . '/../lib/Core/Config.php';
require_once __DIR__ . '/../lib/Core/Translator.php';
require_once __DIR__ . '/../lib/Core/Governance.php';

use Qwiki\Core\Governance;

if (!function_exists('__')) {
    function __($key, $default = '', $vars = []) {
        $str = $default !== '' ? $default : $key;
        foreach ($vars as $k => $v) {
            $str = str_replace('{' . $k . '}', (string)$v, $str);
        }
        return $str;
    }
}

$passed = 0;
$failed = 0;

function assert_true($cond, $label) {
    global $passed, $failed;
    if ($cond) {
        $passed++;
        echo "  [PASS] {$label}\n";
    } else {
        $failed++;
        echo "  [FAIL] {$label}\n";
    }
}

echo "=== Running Governance Unit Tests ===\n";

// 1. Default root notice
$rootCfg = ['title' => 'My Wiki', 'governance' => Governance::getDefaults()];
$resRoot = Governance::resolveNotice($rootCfg, 'docs.example.com', false, null);
assert_true($resRoot['isIndependent'] === false, 'Root wiki is not marked independent');
assert_true(strpos($resRoot['text'], 'designated editors') !== false, 'Root wiki uses standard default notice');

// 2. Independent subwiki notice with maintainerName
$subCfg = [
    'title' => 'Team Alpha Wiki',
    'governance' => [
        'responsibilityMode' => 'independent',
        'maintainerName' => 'Team Alpha',
        'contentNotice' => '',
        'abuseContact' => ''
    ]
];
$parentCfg = [
    'title' => 'Root Wiki',
    'governance' => [
        'contentNotice' => '',
        'enforceNoticeOnSubwikis' => true,
        'abuseContact' => 'abuse@example.com'
    ]
];
$resSub = Governance::resolveNotice($subCfg, 'docs.example.com', true, $parentCfg);
assert_true($resSub['isIndependent'] === true, 'Independent subwiki is marked independent');
assert_true(strpos($resSub['text'], 'Team Alpha') !== false, 'Subwiki notice includes maintainerName');
assert_true(strpos($resSub['text'], 'docs.example.com') !== false, 'Subwiki notice includes host');
assert_true($resSub['abuseContact'] === 'abuse@example.com', 'Subwiki inherits parent abuseContact when local is empty');

// 3. Root-enforced custom notice overrides subwiki custom notice
$parentEnforced = [
    'title' => 'Root Wiki',
    'governance' => [
        'contentNotice' => 'Mandatory Domain Legal Notice',
        'enforceNoticeOnSubwikis' => true,
        'abuseContact' => 'legal@example.com'
    ]
];
$subCustom = [
    'title' => 'Sub',
    'governance' => [
        'responsibilityMode' => 'independent',
        'contentNotice' => 'Subwiki trying to override',
        'abuseContact' => 'sub@example.com'
    ]
];
$resEnforced = Governance::resolveNotice($subCustom, 'example.com', true, $parentEnforced);
assert_true($resEnforced['enforcedByParent'] === true, 'enforcedByParent is true');
assert_true($resEnforced['text'] === 'Mandatory Domain Legal Notice', 'Parent enforced notice overrides subwiki notice');
assert_true($resEnforced['abuseContact'] === 'sub@example.com', 'Local abuseContact preserved when set');
assert_true($resEnforced['parentAbuseContact'] === 'legal@example.com', 'Parent abuseContact also surfaced when different');

// 4. buildReportHref sanitization
$mailtoHref = Governance::buildReportHref('abuse@example.com', 'https://example.com/page', 'Test Page', 'Test Wiki');
assert_true(strpos($mailtoHref, 'mailto:abuse@example.com?subject=') === 0, 'Email builds mailto link with subject');
$httpsHref = Governance::buildReportHref('https://example.com/report', 'https://example.com/page');
assert_true($httpsHref === 'https://example.com/report', 'HTTPS URL is preserved');
$xssHref = Governance::buildReportHref('javascript:alert(1)', 'https://example.com/page');
assert_true($xssHref === '', 'javascript: URI is rejected');
$dataHref = Governance::buildReportHref('data:text/html,<script>alert(1)</script>');
assert_true($dataHref === '', 'data: URI is rejected');

// 5. Editor terms acceptance
$cfgTerms = ['governance' => ['requireEditorTerms' => true, 'editorTermsVersion' => '2.0']];
assert_true(Governance::hasAcceptedTerms(['username' => 'alice'], $cfgTerms) === false, 'User without acceptedTermsAt fails check');
assert_true(Governance::hasAcceptedTerms(['username' => 'alice', 'acceptedTermsAt' => '2026-01-01T00:00:00Z', 'acceptedTermsVersion' => '1.0'], $cfgTerms) === false, 'Outdated terms version fails check');
assert_true(Governance::hasAcceptedTerms(['username' => 'alice', 'acceptedTermsAt' => '2026-01-01T00:00:00Z', 'acceptedTermsVersion' => '2.0'], $cfgTerms) === true, 'Matching terms version passes check');

// 6. Sanitize input enforces root-only enforceNoticeOnSubwikis
$sanSub = Governance::sanitizeInput(['enforceNoticeOnSubwikis' => true, 'responsibilityMode' => 'invalid'], true);
assert_true($sanSub['enforceNoticeOnSubwikis'] === false, 'Subwiki cannot set enforceNoticeOnSubwikis');
assert_true($sanSub['responsibilityMode'] === 'standard', 'Invalid responsibilityMode falls back to standard');

echo "\nSummary: {$passed} passed, {$failed} failed\n";
exit($failed > 0 ? 1 : 0);
