<?php
/**
 * Standalone Qwiki - Chapter Comments Extension Action Handler
 */

require_once __DIR__ . '/CommentService.php';
require_once dirname(dirname(dirname(__DIR__))) . '/lib/Core/Auth.php';
require_once dirname(dirname(dirname(__DIR__))) . '/lib/Core/Config.php';
require_once dirname(dirname(dirname(__DIR__))) . '/lib/Core/AuditLogger.php';
require_once dirname(dirname(dirname(__DIR__))) . '/lib/Core/LlmAccess.php';

use Qwiki\Core\Auth;
use Qwiki\Core\Config;
use Qwiki\Core\AuditLogger;
use Qwiki\Core\LlmAccess;
use Qwiki\Extension\Comments\CommentService;

Auth::startSession();

$config = Config::load();
$params = array_merge($_GET, $_POST, is_array($requestData ?? null) ? $requestData : []);
$action = $action ?? $params['action'] ?? '';
$book = trim((string)($params['book'] ?? ''));
$slug = trim((string)($params['slug'] ?? ''));

$isLlmAuth = false;

// 1. Authentication Guard: Must be signed in (viewer or admin) OR have valid LLM Access Token
if (!Auth::isLoggedIn()) {
    $rawKey = $params['key'] ?? null;
    $val = LlmAccess::validateKey($rawKey);
    if ($val['valid']) {
        $isLlmAuth = true;
        $keyRecord = $val['key'];
        $currentUser = 'agent:' . ($keyRecord['name'] ?? 'token');
        $userRole = 'viewer';
        $isAdmin = false;

        // Check category scope if key is restricted to a category
        if (!empty($keyRecord['category']) && !empty($book) && $keyRecord['category'] !== $book) {
            if (!headers_sent()) {
                http_response_code(403);
            }
            echo json_encode([
                'success' => false,
                'code' => 403,
                'error' => 'LLM access token is not authorized for this category.'
            ]);
            return;
        }
    } else {
        if (!headers_sent()) {
            http_response_code(401);
        }
        echo json_encode([
            'success' => false,
            'code' => 401,
            'error' => 'Authentication required to view or post comments.'
        ]);
        return;
    }
} else {
    $userSession = Auth::getCurrentUser();
    if (is_array($userSession)) {
        $currentUser = (string)($userSession['username'] ?? 'anonymous');
        $userRole = (string)($userSession['role'] ?? 'viewer');
    } else {
        $currentUser = (string)($userSession ?: 'anonymous');
        $userRole = 'viewer';
    }
    $isAdmin = Auth::isAdmin();
    if ($isAdmin) {
        $userRole = 'admin';
    }
}

// 2. Reject mutation attempts by LLM tokens (read-only for discussions)
if ($isLlmAuth && in_array($action, ['ext_comments_add', 'ext_comments_edit', 'ext_comments_delete'])) {
    if (!headers_sent()) {
        http_response_code(403);
    }
    echo json_encode([
        'success' => false,
        'code' => 403,
        'error' => 'LLM access tokens are read-only for discussions.'
    ]);
    return;
}

switch ($action) {
    case 'ext_comments_get':
        if (empty($slug)) {
            echo json_encode(['success' => false, 'error' => 'Chapter slug is required.']);
            return;
        }

        $enabled = CommentService::isCommentsEnabled($book, $slug, $config);
        $data = CommentService::getComments($book, $slug);

        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }

        echo json_encode([
            'success' => true,
            'commentsEnabled' => $enabled,
            'book' => $book,
            'slug' => $slug,
            'currentUser' => [
                'username' => $currentUser,
                'role' => $userRole,
                'isAdmin' => $isAdmin
            ],
            'updatedAt' => $data['updatedAt'] ?? null,
            'comments' => $data['comments'] ?? []
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        return;

    case 'ext_comments_add':
        if (empty($slug)) {
            echo json_encode(['success' => false, 'error' => 'Chapter slug is required.']);
            return;
        }

        $enabled = CommentService::isCommentsEnabled($book, $slug, $config);
        if (!$enabled) {
            echo json_encode(['success' => false, 'error' => 'Comments are disabled for this chapter.']);
            return;
        }

        $content = (string)($params['content'] ?? '');
        $parentId = !empty($params['parentId']) ? trim((string)$params['parentId']) : null;

        $newComment = CommentService::addComment($book, $slug, $currentUser, $userRole, $content, $parentId);
        if (isset($newComment['success']) && $newComment['success'] === false) {
            echo json_encode($newComment);
            return;
        }

        if (class_exists('\Qwiki\Core\AuditLogger')) {
            AuditLogger::log('content', 'comment_add', 200, [
                'author' => $currentUser,
                'book' => $book,
                'slug' => $slug,
                'commentId' => $newComment['id'] ?? null,
                'isReply' => !empty($newComment['parentId'])
            ], $currentUser);
        }

        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }

        echo json_encode([
            'success' => true,
            'comment' => $newComment
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        return;

    case 'ext_comments_edit':
        $commentId = trim((string)($params['commentId'] ?? ''));
        $content = (string)($params['content'] ?? '');

        if (empty($slug) || empty($commentId)) {
            echo json_encode(['success' => false, 'error' => 'Chapter slug and comment ID are required.']);
            return;
        }

        $editResult = CommentService::editComment($book, $slug, $commentId, $currentUser, $isAdmin, $content);

        if (!empty($editResult['success']) && class_exists('\Qwiki\Core\AuditLogger')) {
            AuditLogger::log('content', 'comment_edit', 200, [
                'editor' => $currentUser,
                'book' => $book,
                'slug' => $slug,
                'commentId' => $commentId
            ], $currentUser);
        }

        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }

        echo json_encode($editResult, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        return;

    case 'ext_comments_delete':
        $commentId = trim((string)($params['commentId'] ?? ''));

        if (empty($slug) || empty($commentId)) {
            echo json_encode(['success' => false, 'error' => 'Chapter slug and comment ID are required.']);
            return;
        }

        $delResult = CommentService::deleteComment($book, $slug, $commentId, $currentUser, $isAdmin);

        if (!empty($delResult['success']) && class_exists('\Qwiki\Core\AuditLogger')) {
            AuditLogger::log('content', 'comment_delete', 200, [
                'actor' => $currentUser,
                'book' => $book,
                'slug' => $slug,
                'commentId' => $commentId
            ], $currentUser);
        }

        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }

        echo json_encode($delResult, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        return;

    default:
        echo json_encode(['success' => false, 'error' => 'Unknown comments action: ' . htmlspecialchars($action)]);
        return;
}
