<?php
require_once __DIR__ . '/WikiChatService.php';

use Qwiki\Core\Auth;
use Qwiki\Core\Config;
use Qwiki\Extension\WikiChat\WikiChatService;

$chatConfig = Config::load();
if (!WikiChatService::canCurrentUserAccessChat($chatConfig)) {
    return;
}

$activeChapter = $GLOBALS['activeChapter'] ?? null;
$activeSlug = is_array($activeChapter) ? ($activeChapter['slug'] ?? '') : '';
$activeTitle = is_array($activeChapter) ? ($activeChapter['title'] ?? '') : '';
$chatSettings = WikiChatService::getChatSettings($chatConfig);
$creds = WikiChatService::resolveLlmCredentials();
$isChatAdmin = Auth::isAdmin();
$isChatLoggedIn = Auth::isLoggedIn();
$baseUrl = rtrim(Config::getBaseUrl(), '/');
$isPersonalKey = ($creds['keySource'] ?? '') === 'user';
?>
<div id="wiki-chat-backdrop" class="wiki-chat-backdrop" aria-hidden="true"></div>
<aside id="wiki-chat-drawer"
       class="wiki-chat-drawer"
       aria-hidden="true"
       aria-label="<?= htmlspecialchars(__('ext_chat.drawer_label', 'Wiki AI Chat')) ?>"
       data-active-slug="<?= htmlspecialchars($activeSlug) ?>"
       data-active-title="<?= htmlspecialchars($activeTitle) ?>"
       data-base-url="<?= htmlspecialchars($baseUrl) ?>"
       data-is-logged-in="<?= $isChatLoggedIn ? '1' : '0' ?>"
       data-is-admin="<?= $isChatAdmin ? '1' : '0' ?>"
       data-key-source="<?= htmlspecialchars($creds['keySource'] ?? 'site') ?>">

    <div class="wiki-chat-header">
        <div class="wiki-chat-header-title">
            <span class="wiki-chat-logo">💬</span>
            <div>
                <div class="wiki-chat-title-text"><?= htmlspecialchars(__('ext_chat.drawer_title', 'Ask Wiki AI')) ?></div>
                <div class="wiki-chat-subtitle-row">
                    <?php if ($isChatLoggedIn): ?>
                        <button type="button"
                                id="wiki-chat-key-badge"
                                class="wiki-chat-key-badge btn-open-gemini-assistant"
                                data-gemini-tab="settings"
                                title="<?= htmlspecialchars(__('ext_chat.key_badge_title', 'Click to configure your personal Gemini API key')) ?>">
                            <?= $isPersonalKey ? '👤 ' . htmlspecialchars(__('ext_chat.key_personal', 'Personal Key')) : '🌐 ' . htmlspecialchars(__('ext_chat.key_site', 'Site Key')) ?>
                        </button>
                    <?php else: ?>
                        <span id="wiki-chat-key-badge" class="wiki-chat-key-badge wiki-chat-key-badge--static">
                            🌐 <?= htmlspecialchars(__('ext_chat.key_site', 'Site Key')) ?>
                        </span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="wiki-chat-header-actions">
            <button type="button" id="wiki-chat-expand-btn" class="wiki-chat-icon-btn" title="<?= htmlspecialchars(__('ext_chat.expand_btn', 'Toggle wide view')) ?>">⤢</button>
            <button type="button" id="wiki-chat-clear-btn" class="wiki-chat-icon-btn" title="<?= htmlspecialchars(__('ext_chat.clear_btn', 'Clear conversation')) ?>">🗑️</button>
            <button type="button" id="wiki-chat-close-btn" class="wiki-chat-icon-btn" aria-label="<?= htmlspecialchars(__('common.close', 'Close')) ?>">&times;</button>
        </div>
    </div>

    <?php if ($isChatAdmin): ?>
        <div class="wiki-chat-tabs" role="tablist">
            <button type="button" class="wiki-chat-tab active" data-chat-tab="chat" role="tab" aria-selected="true">
                💬 <?= htmlspecialchars(__('ext_chat.tab_chat', 'Chat')) ?>
            </button>
            <button type="button" class="wiki-chat-tab" data-chat-tab="settings" role="tab" aria-selected="false">
                ⚙️ <?= htmlspecialchars(__('ext_chat.tab_settings', 'Settings')) ?>
            </button>
        </div>
    <?php endif; ?>

    <!-- Chat Tab Panel -->
    <div id="wiki-chat-panel-chat" class="wiki-chat-panel active">
        <?php if ($activeSlug !== ''): ?>
            <div class="wiki-chat-context-bar" id="wiki-chat-active-doc-pill">
                <label class="wiki-chat-context-toggle">
                    <input type="checkbox" id="wiki-chat-include-page" <?= !empty($chatSettings['includeActiveDoc']) ? 'checked' : '' ?>>
                    <span>📄 <?= htmlspecialchars(__('ext_chat.context_prefix', 'Context:')) ?> <strong><?= htmlspecialchars($activeTitle ?: $activeSlug) ?></strong></span>
                </label>
            </div>
        <?php endif; ?>

        <div id="wiki-chat-messages" class="wiki-chat-messages">
            <div class="wiki-chat-empty-state" id="wiki-chat-empty-state">
                <div class="wiki-chat-empty-icon">🤖</div>
                <p><?= htmlspecialchars(__('ext_chat.welcome_msg', 'Ask any question about this wiki. I can read the current page and search across permitted chapters.')) ?></p>
                <div class="wiki-chat-starters">
                    <?php if ($activeSlug !== ''): ?>
                        <button type="button" class="wiki-chat-starter-chip" data-prompt="<?= htmlspecialchars(__('ext_chat.starter_summarize', 'Summarize this page')) ?>">
                            ✨ <?= htmlspecialchars(__('ext_chat.starter_summarize', 'Summarize this page')) ?>
                        </button>
                    <?php endif; ?>
                    <button type="button" class="wiki-chat-starter-chip" data-prompt="<?= htmlspecialchars(__('ext_chat.starter_topics', 'What topics are covered in this wiki?')) ?>">
                        📚 <?= htmlspecialchars(__('ext_chat.starter_topics', 'What topics are covered in this wiki?')) ?>
                    </button>
                    <button type="button" class="wiki-chat-starter-chip" data-prompt="<?= htmlspecialchars(__('ext_chat.starter_quickstart', 'How do I get started?')) ?>">
                        🚀 <?= htmlspecialchars(__('ext_chat.starter_quickstart', 'How do I get started?')) ?>
                    </button>
                </div>
            </div>
        </div>

        <form id="wiki-chat-form" class="wiki-chat-form">
            <textarea id="wiki-chat-input"
                      class="wiki-chat-input"
                      rows="2"
                      maxlength="4000"
                      placeholder="<?= htmlspecialchars(__('ext_chat.input_placeholder', 'Ask a question about the wiki... (Enter to send, Shift+Enter for newline)')) ?>"
                      required></textarea>
            <button type="submit" id="wiki-chat-send-btn" class="btn btn-primary btn-sm wiki-chat-send-btn">
                <?= htmlspecialchars(__('ext_chat.send_btn', 'Send')) ?>
            </button>
        </form>
    </div>

    <?php if ($isChatAdmin): ?>
        <!-- Admin Settings Tab Panel -->
        <div id="wiki-chat-panel-settings" class="wiki-chat-panel wiki-chat-settings-panel">
            <form id="wiki-chat-settings-form">
                <div class="form-group">
                    <label class="form-label" for="wiki-chat-access-mode"><?= htmlspecialchars(__('ext_chat.setting_access_mode', 'Who can use Wiki AI Chat?')) ?></label>
                    <select id="wiki-chat-access-mode" name="accessMode" class="form-control">
                        <option value="public" <?= $chatSettings['accessMode'] === 'public' ? 'selected' : '' ?>>
                            <?= htmlspecialchars(__('ext_chat.access_public', 'All Visitors (Anyone who can view the wiki)')) ?>
                        </option>
                        <option value="logged_in" <?= $chatSettings['accessMode'] === 'logged_in' ? 'selected' : '' ?>>
                            <?= htmlspecialchars(__('ext_chat.access_logged_in', 'Viewers & Admins Only (Signed-in users)')) ?>
                        </option>
                        <option value="admin_only" <?= $chatSettings['accessMode'] === 'admin_only' ? 'selected' : '' ?>>
                            <?= htmlspecialchars(__('ext_chat.access_admin_only', 'Admins Only')) ?>
                        </option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label" for="wiki-chat-llm-key"><?= htmlspecialchars(__('ext_chat.setting_llm_key', 'Scoped Wiki Access Key (qwk_llm_...)')) ?></label>
                    <select id="wiki-chat-llm-key" name="llmKeyId" class="form-control">
                        <option value=""><?= htmlspecialchars(__('ext_chat.llm_key_auto', 'Auto-managed (Wiki Chat Assistant — All Categories)')) ?></option>
                    </select>
                    <small style="color: var(--text-muted); display: block; margin-top: 0.35rem;">
                        <?= htmlspecialchars(__('ext_chat.setting_llm_key_help', 'Restricts which category branch and document formats the chatbot can read.')) ?>
                    </small>
                </div>

                <div class="form-group">
                    <label class="wiki-chat-checkbox-label">
                        <input type="checkbox" id="wiki-chat-default-include-doc" name="includeActiveDoc" <?= !empty($chatSettings['includeActiveDoc']) ? 'checked' : '' ?>>
                        <span><?= htmlspecialchars(__('ext_chat.setting_include_active', 'Include currently open chapter in prompt context by default')) ?></span>
                    </label>
                </div>

                <div class="form-group">
                    <label class="form-label" for="wiki-chat-system-extra"><?= htmlspecialchars(__('ext_chat.setting_extra_prompt', 'Additional System Instructions (Optional)')) ?></label>
                    <textarea id="wiki-chat-system-extra" name="systemPromptExtra" class="form-control" rows="4" maxlength="2000" placeholder="<?= htmlspecialchars(__('ext_chat.setting_extra_placeholder', 'e.g., Always respond in a concise tone and include CLI examples when available.')) ?>"><?= htmlspecialchars($chatSettings['systemPromptExtra']) ?></textarea>
                </div>

                <div style="display: flex; align-items: center; gap: 0.75rem; margin-top: 1rem;">
                    <button type="submit" class="btn btn-primary btn-sm" id="wiki-chat-save-settings-btn">
                        <?= htmlspecialchars(__('ext_chat.save_settings_btn', 'Save Chat Settings')) ?>
                    </button>
                    <span id="wiki-chat-settings-status" class="wiki-chat-settings-status"></span>
                </div>
            </form>
        </div>
    <?php endif; ?>
</aside>
