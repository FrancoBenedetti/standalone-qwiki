<?php
/**
 * Form Search Extractor
 * Variables available in scope: $item, $baseDir (or $chapter)
 */
use Qwiki\Core\Config;

$baseDir = $baseDir ?? Config::getBaseDir();
$item = $chapter ?? $item ?? [];
$file = $item['file'] ?? '';

if (empty($file)) {
    return '';
}

$filePath = Config::safePath($baseDir, $file);
if (!$filePath || !file_exists($filePath)) {
    return '';
}

$data = @json_decode(file_get_contents($filePath), true);
if (!is_array($data)) {
    return '';
}

$words = [];
if (!empty($data['title'])) {
    $words[] = $data['title'];
}
if (!empty($data['description'])) {
    $words[] = $data['description'];
}

if (!empty($data['fields']) && is_array($data['fields'])) {
    foreach ($data['fields'] as $f) {
        if (!empty($f['label'])) {
            $words[] = $f['label'];
        }
        if (!empty($f['help'])) {
            $words[] = $f['help'];
        }
        if (!empty($f['options']) && is_array($f['options'])) {
            $words[] = implode(' ', $f['options']);
        }
    }
}

return implode(' ', $words);
