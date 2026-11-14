<?php
use Qwiki\Core\Auth;
use Qwiki\Core\Config;
use Qwiki\Core\Navigation;
use Qwiki\Core\DocumentService;
use Qwiki\Extensions\Revisions\RevisionManager;

require_once __DIR__ . '/RevisionManager.php';

if (!Auth::isAdmin()) {
    return ['success' => false, 'error' => 'Unauthorized'];
}

$config = Config::load();
$baseDir = Config::getBaseDir();
$slug = trim($_REQUEST['slug'] ?? '');

if (empty($slug)) {
    return ['success' => false, 'error' => 'Document slug is required'];
}

$docIdCreated = false;
$chapter = Navigation::ensureDocIdForChapter($config['books'], $slug, $docIdCreated);
if (!$chapter) {
    return ['success' => false, 'error' => 'Document not found'];
}
if ($docIdCreated) {
    Config::save($config);
}

$docId = $chapter['docId'] ?? '';

if ($action === 'ext_revisions_list') {
    if (empty($docId)) {
        return ['success' => true, 'docId' => '', 'revisions' => []];
    }
    return [
        'success' => true,
        'docId' => $docId,
        'slug' => $slug,
        'title' => $chapter['title'] ?? $slug,
        'revisions' => RevisionManager::listRevisions($docId),
    ];
}

if ($action === 'ext_revisions_get') {
    $revId = trim($_REQUEST['revId'] ?? '');
    if (empty($docId) || empty($revId)) {
        return ['success' => false, 'error' => 'docId and revId are required'];
    }
    $rev = RevisionManager::getRevision($docId, $revId);
    if (!$rev) {
        return ['success' => false, 'error' => 'Revision not found'];
    }
    unset($rev['bakPath']);
    return ['success' => true, 'revision' => $rev];
}

if ($action === 'ext_revisions_restore') {
    if (Config::isChapterProtected($slug, $config['books'] ?? []) || Config::isChapterAncestorProtected($slug, $config['books'] ?? [])) {
        return ['success' => false, 'error' => 'This document is protected and cannot be restored.'];
    }
    $revId = trim($_POST['revId'] ?? '');
    if (empty($docId) || empty($revId)) {
        return ['success' => false, 'error' => 'docId and revId are required'];
    }
    $rev = RevisionManager::getRevision($docId, $revId);
    if (!$rev || $rev['content'] === false) {
        return ['success' => false, 'error' => 'Revision not found'];
    }
    $relFile = $chapter['file'] ?? '';
    $targetAbs = !empty($relFile) ? Config::safePath($baseDir, $relFile) : null;
    if (!$targetAbs) {
        return ['success' => false, 'error' => 'Invalid target file path'];
    }
    $actor = Auth::getCurrentUser()['username'] ?? 'admin';
    $res = DocumentService::replaceContent($chapter, $targetAbs, [
        'content' => $rev['content'],
        'oldAbsFile' => $targetAbs,
        'oldRelFile' => $relFile,
        'newRelFile' => $relFile,
        'source' => 'ui_revision_restore',
        'actor' => $actor,
        'event' => 'doc_restored',
        'extraAudit' => ['restored_revId' => $revId],
    ]);
    return !empty($res['success'])
        ? ['success' => true, 'restoredRevId' => $revId]
        : ['success' => false, 'error' => $res['error'] ?? 'Restore failed'];
}

return ['success' => false, 'error' => 'Unknown revision action'];
