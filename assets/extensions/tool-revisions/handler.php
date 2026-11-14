<?php
use Qwiki\Core\Auth;
use Qwiki\Core\Config;
use Qwiki\Core\Navigation;
use Qwiki\Core\DocumentService;
use Qwiki\Core\LockManager;
use Qwiki\Extensions\Revisions\RevisionManager;

require_once __DIR__ . '/RevisionManager.php';

$emitJson = static function (array $payload, int $statusCode = 200): void {
    if (!headers_sent() && $statusCode !== 200) {
        http_response_code($statusCode);
    }
    echo json_encode($payload, JSON_INVALID_UTF8_SUBSTITUTE);
};

if (!Auth::isAdmin()) {
    $emitJson(['success' => false, 'error' => 'Unauthorized'], 403);
    return;
}

$config = Config::load();
$baseDir = Config::getBaseDir();
$slug = trim($_REQUEST['slug'] ?? '');

if (empty($slug)) {
    $emitJson(['success' => false, 'error' => 'Document slug is required'], 400);
    return;
}

$docIdCreated = false;
$chapter = Navigation::ensureDocIdForChapter($config['books'], $slug, $docIdCreated);
if (!$chapter) {
    $emitJson(['success' => false, 'error' => 'Document not found'], 404);
    return;
}
if ($docIdCreated) {
    Config::save($config);
}

$docId = $chapter['docId'] ?? '';

if ($action === 'ext_revisions_list') {
    if (empty($docId)) {
        $emitJson(['success' => true, 'docId' => '', 'revisions' => []]);
        return;
    }
    $emitJson([
        'success' => true,
        'docId' => $docId,
        'slug' => $slug,
        'title' => $chapter['title'] ?? $slug,
        'revisions' => RevisionManager::listRevisions($docId),
    ]);
    return;
}

if ($action === 'ext_revisions_get') {
    $revId = trim($_REQUEST['revId'] ?? '');
    if (empty($docId) || empty($revId)) {
        $emitJson(['success' => false, 'error' => 'docId and revId are required'], 400);
        return;
    }
    $rev = RevisionManager::getRevision($docId, $revId);
    if (!$rev) {
        $emitJson(['success' => false, 'error' => 'Revision not found'], 404);
        return;
    }
    unset($rev['bakPath']);
    $emitJson(['success' => true, 'revision' => $rev]);
    return;
}

if ($action === 'ext_revisions_download') {
    $revId = trim($_REQUEST['revId'] ?? '');
    if (empty($docId) || empty($revId)) {
        $emitJson(['success' => false, 'error' => 'docId and revId are required'], 400);
        return;
    }
    $rev = RevisionManager::getRevision($docId, $revId);
    if (!$rev || empty($rev['bakPath']) || !is_file($rev['bakPath'])) {
        $emitJson(['success' => false, 'error' => 'Revision not found'], 404);
        return;
    }
    $ext = pathinfo($rev['file'] ?? ($chapter['file'] ?? 'document.bin'), PATHINFO_EXTENSION) ?: 'bin';
    $downloadName = preg_replace('/[^a-zA-Z0-9._-]/', '_', $slug . '_' . basename($revId) . '.' . $ext);
    if (!headers_sent()) {
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $downloadName . '"');
        header('Content-Length: ' . filesize($rev['bakPath']));
    }
    readfile($rev['bakPath']);
    return;
}

if ($action === 'ext_revisions_restore') {
    if (Config::isChapterProtected($slug, $config['books'] ?? []) || Config::isChapterAncestorProtected($slug, $config['books'] ?? [])) {
        $emitJson(['success' => false, 'error' => 'This document is protected and cannot be restored.'], 403);
        return;
    }
    $revId = trim($_POST['revId'] ?? '');
    if (empty($docId) || empty($revId)) {
        $emitJson(['success' => false, 'error' => 'docId and revId are required'], 400);
        return;
    }
    $rev = RevisionManager::getRevision($docId, $revId);
    if (!$rev || empty($rev['bakPath']) || !is_file($rev['bakPath'])) {
        $emitJson(['success' => false, 'error' => 'Revision not found'], 404);
        return;
    }
    $rawBytes = @file_get_contents($rev['bakPath']);
    if ($rawBytes === false) {
        $emitJson(['success' => false, 'error' => 'Unable to read revision snapshot'], 500);
        return;
    }
    $relFile = $chapter['file'] ?? '';
    $targetAbs = !empty($relFile) ? Config::safePath($baseDir, $relFile) : null;
    if (!$targetAbs) {
        $emitJson(['success' => false, 'error' => 'Invalid target file path'], 400);
        return;
    }
    $actor = Auth::getCurrentUser()['username'] ?? 'admin';
    $tabId = trim($_POST['tab_id'] ?? '');
    if (!empty($relFile)) {
        $lockCheck = LockManager::verifyOrRejectSave($relFile, $tabId, $actor);
        if (empty($lockCheck['allowed'])) {
            $emitJson([
                'success' => false,
                'error' => $lockCheck['error'] ?? 'Document is locked by another editor.',
                'lockedBy' => $lockCheck['lockedBy'] ?? null,
            ], 423);
            return;
        }
    }
    $res = DocumentService::replaceContent($chapter, $targetAbs, [
        'content' => $rawBytes,
        'oldAbsFile' => $targetAbs,
        'oldRelFile' => $relFile,
        'newRelFile' => $relFile,
        'source' => 'ui_revision_restore',
        'actor' => $actor,
        'event' => 'doc_restored',
        'extraAudit' => ['restored_revId' => $revId],
    ]);
    if (!empty($res['success'])) {
        $emitJson(['success' => true, 'restoredRevId' => $revId, 'changed' => !empty($res['changed'])]);
    } else {
        $emitJson(['success' => false, 'error' => $res['error'] ?? 'Restore failed'], 500);
    }
    return;
}

$emitJson(['success' => false, 'error' => 'Unknown revision action'], 400);
return;
