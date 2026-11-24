<?php
require_once __DIR__ . '/WikiChatService.php';

use Qwiki\Core\Auth;
use Qwiki\Core\Config;
use Qwiki\Core\LlmAccess;
use Qwiki\Extension\WikiChat\WikiChatService;

$config = Config::load();
$isAdmin = Auth::isAdmin();

switch ($action) {
    case 'ext_wiki_chat_get_config':
        if (!WikiChatService::canCurrentUserAccessChat($config) && !$isAdmin) {
            if (!headers_sent()) http_response_code(403);
            return ['success' => false, 'error' => 'Unauthorized'];
        }
        $settings = WikiChatService::getChatSettings($config);
        $creds = WikiChatService::resolveLlmCredentials();
        $res = [
            'success' => true,
            'settings' => $settings,
            'hasSiteKey' => $creds['hasSiteKey'],
            'hasUserKey' => $creds['hasUserKey'],
            'keySource' => $creds['keySource'],
            'model' => $creds['model'],
            'isAdmin' => $isAdmin,
            'isLoggedIn' => Auth::isLoggedIn(),
        ];
        if ($isAdmin) {
            $keys = LlmAccess::listKeys();
            $res['llmKeys'] = array_map(function ($k) {
                return [
                    'id' => $k['id'] ?? '',
                    'name' => $k['name'] ?? '',
                    'category' => $k['category'] ?? '',
                    'status' => $k['status'] ?? 'active',
                    'allowedTypes' => $k['allowedTypes'] ?? [],
                ];
            }, $keys);
        }
        return $res;

    case 'ext_wiki_chat_save_config':
        if (!$isAdmin) {
            if (!headers_sent()) http_response_code(403);
            return ['success' => false, 'error' => 'Administrator privileges required'];
        }
        $rawBody = file_get_contents('php://input');
        $json = json_decode($rawBody ?: '', true);
        $input = is_array($json) ? $json : $requestData;

        $accessMode = trim((string)($input['accessMode'] ?? 'logged_in'));
        if (!in_array($accessMode, WikiChatService::VALID_ACCESS_MODES, true)) {
            $accessMode = 'logged_in';
        }
        $llmKeyId = trim((string)($input['llmKeyId'] ?? ''));
        $includeActiveDoc = isset($input['includeActiveDoc']) ? !empty($input['includeActiveDoc']) : true;
        $systemPromptExtra = trim((string)($input['systemPromptExtra'] ?? ''));

        $config['wikiChat'] = [
            'accessMode' => $accessMode,
            'llmKeyId' => $llmKeyId,
            'llmKeyAuto' => ($llmKeyId === ''),
            'includeActiveDoc' => $includeActiveDoc,
            'systemPromptExtra' => mb_substr($systemPromptExtra, 0, 2000),
        ];
        Config::save($config);

        return [
            'success' => true,
            'settings' => WikiChatService::getChatSettings($config),
        ];

    case 'ext_wiki_chat_ask':
        @set_time_limit(90);
        if (!WikiChatService::canCurrentUserAccessChat($config)) {
            if (!headers_sent()) http_response_code(403);
            return ['success' => false, 'error' => 'You do not have permission to use Wiki AI Chat.'];
        }
        $rawBody = file_get_contents('php://input');
        $json = json_decode($rawBody ?: '', true);
        $input = is_array($json) ? $json : $requestData;

        $message = (string)($input['message'] ?? '');
        $history = isset($input['history']) && is_array($input['history']) ? $input['history'] : [];
        $activeSlug = isset($input['activeSlug']) ? trim((string)$input['activeSlug']) : null;
        if ($activeSlug === '') $activeSlug = null;

        $settings = WikiChatService::getChatSettings($config);
        $includeActiveDoc = array_key_exists('includeActiveDoc', $input)
            ? !empty($input['includeActiveDoc'])
            : $settings['includeActiveDoc'];

        $result = WikiChatService::runChatTurn($message, $history, $activeSlug, $includeActiveDoc);
        if (empty($result['success']) && !empty($result['code']) && !headers_sent()) {
            http_response_code((int)$result['code']);
        }
        return $result;

    default:
        return ['success' => false, 'error' => 'Unknown wiki chat action'];
}
