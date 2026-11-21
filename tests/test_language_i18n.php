<?php
/**
 * Test Suite: UI Language Switcher & Lightweight i18n Translator
 */

require_once __DIR__ . '/../lib/Core/Config.php';
require_once __DIR__ . '/../lib/Core/Auth.php';
require_once __DIR__ . '/../lib/Core/ExtensionManager.php';
require_once __DIR__ . '/../lib/Core/Translator.php';

use Qwiki\Core\Config;
use Qwiki\Core\ExtensionManager;
use Qwiki\Core\Translator;

$passed = 0;
$failed = 0;

function assert_test(bool $condition, string $label): void {
    global $passed, $failed;
    if ($condition) {
        echo "  [PASS] {$label}\n";
        $passed++;
    } else {
        echo "  [FAIL] {$label}\n";
        $failed++;
    }
}

echo "=== Running UI Language & i18n Tests ===\n\n";

$extManager = ExtensionManager::getInstance();
$configFile = Config::getBaseDir() . '/qwiki.json';
$originalConfig = file_get_contents($configFile);

try {
    // 1. Default English state
    echo "1. Default English State:\n";
    $configData = json_decode((string)$originalConfig, true);
    unset($configData['extensions']['language']);
    file_put_contents($configFile, json_encode($configData, JSON_PRETTY_PRINT));

    Translator::init($extManager, '');
    assert_test(Translator::isEnabled() === true, 'Language extension is enabled by default');
    assert_test(Translator::getActiveLang() === 'en', 'Default active language is en');
    assert_test(Translator::getToastUiLocale() === 'en-US', 'Default Toast UI locale is en-US');
    assert_test(Translator::getJsDictionary() === [], 'JS dictionary is empty for default en (zero overhead)');
    assert_test(__('auth.login', 'Login') === 'Login', 'Helper returns default English string');
    assert_test(__('nav.back_to_parent', 'Back to {title}', ['title' => 'Docs']) === 'Back to Docs', 'Helper interpolates {vars} in English');

    // 2. Afrikaans & Italian translations
    echo "\n2. Afrikaans (af) & Italian (it) Translations:\n";
    Translator::init($extManager, 'af');
    assert_test(Translator::getActiveLang() === 'af', 'Active language switches to af');
    assert_test(Translator::getToastUiLocale() === 'af', 'Toast UI locale switches to af');
    assert_test(__('auth.login', 'Login') === 'Meld Aan', 'Afrikaans translation returned for auth.login');
    assert_test(__('nav.back_to_parent', 'Back to {title}', ['title' => 'Tuis']) === 'Terug na Tuis', 'Afrikaans interpolation works');
    assert_test(__('missing.test.key', 'Fallback Value') === 'Fallback Value', 'Missing key falls back cleanly to English default');
    $afJs = Translator::getJsDictionary();
    assert_test(isset($afJs['js.log_in']) && $afJs['js.log_in'] === 'Meld Aan', 'Afrikaans JS dictionary includes js.log_in');
    assert_test(!isset($afJs['auth.login']), 'PHP-only keys are not duplicated in JS dictionary');

    Translator::init($extManager, 'it');
    assert_test(Translator::getActiveLang() === 'it', 'Active language switches to it');
    assert_test(Translator::getToastUiLocale() === 'it-IT', 'Toast UI locale switches to it-IT');
    assert_test(__('auth.login', 'Login') === 'Accedi', 'Italian translation returned for auth.login');
    assert_test(__('nav.back_to_parent', 'Back to {title}', ['title' => 'Principale']) === 'Torna a Principale', 'Italian interpolation works');
    $itJs = Translator::getJsDictionary();
    assert_test(isset($itJs['tui.Bold']) && $itJs['tui.Bold'] === 'Grassetto', 'Italian JS dictionary includes Toast UI editor keys');

    // 3. Invalid / malicious locale rejection
    echo "\n3. Security & Validation:\n";
    foreach (['../etc/passwd', 'xx', '<script>', 'en;alert(1)'] as $bad) {
        Translator::init($extManager, $bad);
        assert_test(Translator::getActiveLang() === 'en', "Invalid locale '{$bad}' safely falls back to en");
    }

    // 4. Toolbar widget & frontend assets when enabled
    echo "\n4. Enabled Widget & Asset Rendering:\n";
    Translator::init($extManager, 'af');
    ob_start();
    $extManager->renderToolbarUtilityWidgets(false, 'header');
    $widgetHtml = ob_get_clean();
    assert_test(strpos($widgetHtml, 'qwiki-lang-select') !== false, 'Toolbar renders .qwiki-lang-select for non-admin users');
    assert_test(strpos($widgetHtml, 'value="af" selected') !== false || strpos($widgetHtml, "value=\"af\" selected") !== false, 'Selected option reflects active language');
    assert_test(strpos($widgetHtml, 'Italiano') !== false && strpos($widgetHtml, 'Afrikaans') !== false, 'Native language endonyms rendered');

    $assets = $extManager->getFrontendAssets();
    $hasScript = false;
    $hasStyle = false;
    foreach ($assets['scripts'] as $s) {
        if (strpos($s, 'tool-language/script.js') !== false) $hasScript = true;
    }
    foreach ($assets['styles'] as $s) {
        if (strpos($s, 'tool-language/style.css') !== false) $hasStyle = true;
    }
    assert_test($hasScript && $hasStyle, 'Frontend assets include tool-language script and style when enabled');

    ob_start();
    $extManager->renderHeaderUtilityButtons(true, 'dropdown');
    $dropdownHtmlAf = ob_get_clean();
    assert_test(strpos($dropdownHtmlAf, 'Aktiwiteit- &amp; Toegangslogboek') !== false, 'User dropdown translates Activity & Access Log in Afrikaans');
    assert_test(strpos($dropdownHtmlAf, 'Beeldgalery') !== false, 'User dropdown translates Image Gallery in Afrikaans');
    assert_test(strpos($dropdownHtmlAf, 'Dokumentposbus') !== false, 'User dropdown translates Document Postbox in Afrikaans');

    ob_start();
    $extManager->renderAddDocumentTabs();
    $tabsHtmlAf = ob_get_clean();
    assert_test(strpos($tabsHtmlAf, 'Interaktiewe Vorm') !== false, 'Add Document modal translates Interactive Form tab in Afrikaans');
    assert_test(strpos($tabsHtmlAf, 'HTML-dokument') !== false, 'Add Document modal translates HTML Document tab in Afrikaans');

    ob_start();
    $extManager->renderUtilityModals(true);
    $modalsHtmlAf = ob_get_clean();
    assert_test(strpos($modalsHtmlAf, 'Rugsteun &amp; Uitvoer') !== false, 'Extension modals translate Backup & Export in Afrikaans');
    assert_test(strpos($modalsHtmlAf, 'Dokument-hersieningsgeskiedenis') !== false, 'Extension modals translate Revision History in Afrikaans');

    // 5. Admin disables language extension
    echo "\n5. Admin Disables Language Extension:\n";
    $configData['extensions']['language'] = false;
    file_put_contents($configFile, json_encode($configData, JSON_PRETTY_PRINT));

    Translator::init($extManager, 'af');
    assert_test(Translator::isEnabled() === false, 'Translator reports disabled when extension is disabled');
    assert_test(Translator::getActiveLang() === 'en', 'Disabled extension forces active language back to en even with af cookie');
    assert_test(Translator::getJsDictionary() === [], 'Disabled extension returns empty JS dictionary');
    assert_test(__('auth.login', 'Login') === 'Login', 'Disabled extension renders default English strings');

    ob_start();
    $extManager->renderToolbarUtilityWidgets(true, 'header');
    $disabledWidgetHtml = ob_get_clean();
    assert_test(trim($disabledWidgetHtml) === '', 'Disabled extension renders no toolbar language widget');

    $disabledAssets = $extManager->getFrontendAssets();
    $hasScriptDisabled = false;
    foreach ($disabledAssets['scripts'] as $s) {
        if (strpos($s, 'tool-language') !== false) $hasScriptDisabled = true;
    }
    assert_test(!$hasScriptDisabled, 'Disabled extension excludes tool-language assets');

} finally {
    file_put_contents($configFile, $originalConfig);
    Translator::reset();
}

echo "\nSummary: {$passed} passed, {$failed} failed\n";
exit($failed > 0 ? 1 : 0);
