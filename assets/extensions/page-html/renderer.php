<?php
/**
 * HTML Page Type Renderer
 * Variables available: $chapter, $book, $extension
 */
$baseDir = \Qwiki\Core\Config::getBaseDir();
$filePath = $baseDir . '/' . ($chapter['file'] ?? '');

if (!file_exists($filePath)) {
    echo "<div class='alert warning'>HTML file not found: " . htmlspecialchars($chapter['file'] ?? '') . "</div>";
    return;
}

$content = file_get_contents($filePath);
$fileUrl = htmlspecialchars($chapter['file'] ?? '');
?>
<div class="html-page-container">
    <div class="html-viewer-toolbar">
        <span class="text-muted" style="font-size: 0.85rem;">Interactive HTML Document</span>
        <a href="<?= $fileUrl ?>" target="_blank" class="btn btn-outline btn-sm">Open in Full Tab ↗</a>
    </div>
    <iframe class="html-viewer-frame" src="<?= $fileUrl ?>" sandbox="allow-scripts allow-same-origin allow-popups allow-forms" title="HTML Document View"></iframe>
</div>
