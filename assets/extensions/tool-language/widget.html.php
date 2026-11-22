<?php
use Qwiki\Core\Translator;

$languages = Translator::getAvailableLanguages();
if (empty($languages)) {
    return;
}
$activeLang = Translator::getActiveLang();
$ctx = preg_replace('/[^a-z0-9_-]/i', '', (string)($toolbarContext ?? 'header'));
$selectId = 'qwiki-lang-select-' . ($ctx !== '' ? $ctx : 'header');
$ariaLabel = __('lang.select_label', 'Select interface language');
?>
<div class="qwiki-lang-wrap" data-context="<?= htmlspecialchars($ctx) ?>">
    <span class="qwiki-lang-icon" aria-hidden="true">🌐</span>
    <span class="qwiki-lang-short" aria-hidden="true"><?= htmlspecialchars(strtoupper((string)$activeLang)) ?></span>
    <span class="qwiki-lang-caret" aria-hidden="true">▾</span>
    <select
        id="<?= htmlspecialchars($selectId) ?>"
        class="qwiki-lang-select"
        aria-label="<?= htmlspecialchars($ariaLabel) ?>"
        title="<?= htmlspecialchars($ariaLabel) ?>"
    >
        <?php foreach ($languages as $code => $meta): ?>
            <option value="<?= htmlspecialchars((string)$code) ?>" <?= ($code === $activeLang) ? 'selected' : '' ?>>
                <?= htmlspecialchars((string)($meta['label'] ?? $code)) ?>
            </option>
        <?php endforeach; ?>
    </select>
</div>
