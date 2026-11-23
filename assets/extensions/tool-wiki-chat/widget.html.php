<?php
require_once __DIR__ . '/WikiChatService.php';

use Qwiki\Core\Config;
use Qwiki\Extension\WikiChat\WikiChatService;

$ctx = $toolbarContext ?? 'header';
if ($ctx === 'auth') {
    return;
}
$cfg = Config::load();
if (!WikiChatService::canCurrentUserAccessChat($cfg)) {
    return;
}
?>
<button type="button"
        class="btn btn-outline btn-sm wiki-chat-toolbar-btn"
        id="btn-open-wiki-chat"
        title="<?= htmlspecialchars(__('ext_chat.ask_wiki_title', 'Ask Wiki AI (Ctrl+Shift+K)')) ?>"
        aria-label="<?= htmlspecialchars(__('ext_chat.ask_wiki_btn', 'Ask Wiki')) ?>">
    💬 <span><?= htmlspecialchars(__('ext_chat.ask_wiki_btn', 'Ask Wiki')) ?></span>
</button>
