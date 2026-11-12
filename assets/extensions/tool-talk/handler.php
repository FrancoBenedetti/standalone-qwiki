<?php
/**
 * Standalone Qwiki - Talk Pages Extension Action Handler
 */

require_once __DIR__ . '/TalkService.php';
require_once dirname(dirname(dirname(__DIR__))) . '/lib/Core/Auth.php';
require_once dirname(dirname(dirname(__DIR__))) . '/lib/Core/Config.php';
require_once dirname(dirname(dirname(__DIR__))) . '/lib/Core/AuditLogger.php';
require_once dirname(dirname(dirname(__DIR__))) . '/lib/Core/ExtensionManager.php';

use Qwiki\Core\Auth;
use Qwiki\Core\Config;
use Qwiki\Core\AuditLogger;
use Qwiki\Core\ExtensionManager;
use Qwiki\Extension\Talk\TalkService;

Auth::startSession();

// Check if extension is enabled in current wiki configuration
if (!ExtensionManager::getInstance()->isExtensionEnabled('talk')) {
    if (!headers_sent()) {
        http_response_code(403);
    }
    echo json_encode(['success' => false, 'code' => 403, 'error' => 'Talk Pages extension is disabled.']);
    return;
}

$config = Config::load();
$params = array_merge($_GET, $_POST, is_array($requestData ?? null) ? $requestData : []);
$action = $action ?? $params['action'] ?? '';
$book = trim((string)($params['book'] ?? ''));
$slug = trim((string)($params['slug'] ?? ''));

$isLoggedIn = Auth::isLoggedIn();
$user = Auth::getCurrentUser();
$currentUser = $user['username'] ?? '';
$userRole = $user['role'] ?? 'viewer';
$isAdmin = Auth::isAdmin();

switch ($action) {
    case 'ext_talk_get':
        $isTalkEnabled = TalkService::isTalkEnabled($book, $slug, $config);
        $topics = TalkService::getTopics($book, $slug);
        $raw = TalkService::getRawContent($book, $slug);

        echo json_encode([
            'success' => true,
            'talkEnabled' => $isTalkEnabled,
            'topicsCount' => count($topics),
            'topics' => $topics,
            'raw' => $raw,
            'canPost' => $isLoggedIn,
            'canEdit' => $isAdmin || $userRole === 'editor',
            'currentUser' => [
                'username' => $currentUser,
                'role' => $userRole,
                'isAdmin' => $isAdmin
            ]
        ]);
        break;

    case 'ext_talk_add_topic':
        if (!$isLoggedIn) {
            if (!headers_sent()) {
                http_response_code(401);
            }
            echo json_encode(['success' => false, 'code' => 401, 'error' => 'Authentication required to post discussion topics.']);
            return;
        }

        $title = trim((string)($params['title'] ?? ''));
        $message = trim((string)($params['message'] ?? ''));

        if (mb_strlen($title) < 2 || mb_strlen($title) > 120) {
            echo json_encode(['success' => false, 'code' => 400, 'error' => 'Topic title must be between 2 and 120 characters.']);
            return;
        }

        if (mb_strlen($message) < 1 || mb_strlen($message) > 10000) {
            echo json_encode(['success' => false, 'code' => 400, 'error' => 'Message must be between 1 and 10,000 characters.']);
            return;
        }

        $result = TalkService::addTopic($book, $slug, $currentUser, $userRole, $title, $message);
        if (!empty($result['success'])) {
            AuditLogger::log('TALK', 'TOPIC_ADD', 200, [
                'book' => $book,
                'slug' => $slug,
                'title' => $title,
                'username' => $currentUser
            ]);
        }

        echo json_encode($result);
        break;

    case 'ext_talk_reply':
        if (!$isLoggedIn) {
            if (!headers_sent()) {
                http_response_code(401);
            }
            echo json_encode(['success' => false, 'code' => 401, 'error' => 'Authentication required to reply to topics.']);
            return;
        }

        $topicIndex = isset($params['topicIndex']) ? (int)$params['topicIndex'] : -1;
        $message = trim((string)($params['message'] ?? ''));

        if ($topicIndex < 0) {
            echo json_encode(['success' => false, 'code' => 400, 'error' => 'Invalid topic index.']);
            return;
        }

        if (mb_strlen($message) < 1 || mb_strlen($message) > 5000) {
            echo json_encode(['success' => false, 'code' => 400, 'error' => 'Reply message must be between 1 and 5,000 characters.']);
            return;
        }

        $result = TalkService::addReply($book, $slug, $currentUser, $userRole, $topicIndex, $message);
        if (!empty($result['success'])) {
            AuditLogger::log('TALK', 'REPLY_ADD', 200, [
                'book' => $book,
                'slug' => $slug,
                'topicIndex' => $topicIndex,
                'username' => $currentUser
            ]);
        }

        echo json_encode($result);
        break;

    case 'ext_talk_save_raw':
        if (!$isLoggedIn) {
            if (!headers_sent()) {
                http_response_code(401);
            }
            echo json_encode(['success' => false, 'code' => 401, 'error' => 'Authentication required to edit talk page.']);
            return;
        }

        if (!$isAdmin && $userRole !== 'editor') {
            if (!headers_sent()) {
                http_response_code(403);
            }
            echo json_encode(['success' => false, 'code' => 403, 'error' => 'Only editors and administrators can edit raw talk markdown.']);
            return;
        }

        $markdown = (string)($params['markdown'] ?? '');
        $saved = TalkService::saveRawContent($book, $slug, $markdown);

        if ($saved) {
            AuditLogger::log('TALK', 'RAW_SAVE', 200, [
                'book' => $book,
                'slug' => $slug,
                'username' => $currentUser,
                'bytes' => strlen($markdown)
            ]);
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to save raw talk markdown.']);
        }
        break;

    default:
        echo json_encode(['success' => false, 'error' => "Unknown action '{$action}'."]);
        break;
}
