<?php
use Qwiki\Core\ExtensionManager;

$extManager = ExtensionManager::getInstance();
$allExtensions = $extManager->getAllExtensions();

$pageTypeExts = [];
$utilityExts = [];
foreach ($allExtensions as $id => $ext) {
    if (($ext['category'] ?? '') === 'page_type') {
        $pageTypeExts[$id] = $ext;
    } else {
        $utilityExts[$id] = $ext;
    }
}
?>
<!-- Qwiki Settings Modal -->
<div class="modal-overlay" id="settings-modal">
    <div class="modal-card">
        <div class="modal-header">
            <h3>Qwiki Settings</h3>
            <button class="modal-close" data-close="settings-modal">&times;</button>
        </div>

        <div class="modal-tabs">
            <button type="button" class="tab-btn active" data-tab="settings-tab-general">🌐 General</button>
            <button type="button" class="tab-btn" data-tab="settings-tab-security">🔒 Access &amp; Security</button>
            <button type="button" class="tab-btn" data-tab="settings-tab-extensions">🧩 Extensions</button>
            <button type="button" class="tab-btn" data-tab="settings-tab-smtp">📧 Email (SMTP)</button>
            <button type="button" class="tab-btn" data-tab="settings-tab-integrations">📡 Feeds &amp; Integrations</button>
        </div>

        <form id="settings-form" autocomplete="off">
            <div class="settings-tab-scroll">
                <!-- Tab 1: General -->
                <div id="settings-tab-general" class="tab-content active">
                    <div class="form-group">
                        <label class="form-label" for="setting-title">Documentation Portal Title</label>
                        <input type="text" name="title" id="setting-title" class="form-control" value="<?= htmlspecialchars($config['title'] ?? '') ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="setting-logo">Logo Text (Fallback)</label>
                        <input type="text" name="logoText" id="setting-logo" class="form-control" value="<?= htmlspecialchars($config['logoText'] ?? 'QWIKI') ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="setting-logo-upload">Logo Image (Replaces Text)</label>
                        <input type="file" id="setting-logo-upload" class="form-control" accept="image/*">
                        <input type="hidden" name="logoUrl" id="setting-logo-url" value="<?= htmlspecialchars($config['logoUrl'] ?? '') ?>">
                        <?php if (!empty($config['logoUrl'])): ?>
                            <div style="margin-top: 0.5rem;">
                                <img src="<?= htmlspecialchars($config['logoUrl']) ?>" style="max-height: 40px; border-radius: 4px; background: #fff; padding: 4px;">
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="setting-site-theme">Site Default Theme</label>
                        <select name="theme" id="setting-site-theme" class="form-control theme-selector">
                            <option value="theme-default.css">theme-default.css</option>
                        </select>
                        <button type="button" class="btn btn-outline btn-sm" id="btn-open-theme-editor-direct" style="margin-top: 0.5rem; width: 100%;">🎨 Open Theme Editor</button>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="setting-default-book">Default Category</label>
                        <select name="defaultBook" id="setting-default-book" class="form-control">
                            <?php foreach ($categoryHierarchy as $cat): ?>
                                <option value="<?= htmlspecialchars($cat['id']) ?>" <?= (($config['defaultBook'] ?? '') === $cat['id']) ? 'selected' : '' ?>>
                                    <?= str_repeat('&nbsp;&nbsp;', $cat['depth']) ?><?= $cat['depth'] > 0 ? '↳ ' : '' ?><?= htmlspecialchars($cat['path']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label" style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                            <input type="checkbox" name="showDocTypesOnlyToAdmin" value="1" <?= $showDocTypesOnlyToAdmin ? 'checked' : '' ?>>
                            <span>Show Document Type Badges Only to Admin Users</span>
                        </label>
                    </div>
                    <div class="form-group">
                        <label class="form-label" style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                            <input type="checkbox" name="showPoweredBy" value="1" <?= $showPoweredBy ? 'checked' : '' ?>>
                            <span>Show "Powered by Qwiki" Badge in Sidebar</span>
                        </label>
                    </div>
                    <div class="form-group">
                        <label class="form-label" style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                            <input type="checkbox" name="showSubwikisInSidebar" value="1" <?= $showSubwikisInSidebar ? 'checked' : '' ?>>
                            <span>Show Subwikis in Left Sidebar Navigation</span>
                        </label>
                    </div>
                </div>

                <!-- Tab 2: Security & Access -->
                <div id="settings-tab-security" class="tab-content">
                    <div class="form-group">
                        <label class="form-label" for="setting-access-mode">Portal Access Mode</label>
                        <select name="requireLoginToView" id="setting-access-mode" class="form-control">
                            <option value="0" <?= empty($config['requireLoginToView']) ? 'selected' : '' ?>>Public Access (Anyone can view docs without logging in)</option>
                            <option value="1" <?= !empty($config['requireLoginToView']) ? 'selected' : '' ?>>Private Portal (Login required to view documentation)</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="setting-2fa-policy">Two-Factor Authentication (2FA) Policy</label>
                        <select name="twoFactorPolicy" id="setting-2fa-policy" class="form-control">
                            <option value="optional" <?= (($config['twoFactorPolicy'] ?? 'optional') === 'optional') ? 'selected' : '' ?>>Optional (Users can choose to enable 2FA)</option>
                            <option value="required_admins" <?= (($config['twoFactorPolicy'] ?? '') === 'required_admins') ? 'selected' : '' ?>>Enforced for Admins Only</option>
                            <option value="required_all" <?= (($config['twoFactorPolicy'] ?? '') === 'required_all') ? 'selected' : '' ?>>Enforced for All Users</option>
                            <option value="disabled" <?= (($config['twoFactorPolicy'] ?? '') === 'disabled') ? 'selected' : '' ?>>Disabled</option>
                        </select>
                        <small style="color: var(--text-muted); font-size: 0.8rem; display: block; margin-top: 0.25rem;">
                            Control whether users can or must use TOTP authenticator apps.
                        </small>
                    </div>
                    <div class="form-group" style="margin-top: 1.5rem;">
                        <label class="form-label" for="setting-share-desc">Global Social Share Description</label>
                        <textarea name="shareDescription" id="setting-share-desc" class="form-control" style="min-height: 80px;"><?= htmlspecialchars($config['shareDescription'] ?? '') ?></textarea>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="setting-share-img">Global Social Share Image URL</label>
                        <input type="url" name="shareImageUrl" id="setting-share-img" class="form-control" value="<?= htmlspecialchars($config['shareImageUrl'] ?? '') ?>" placeholder="https://example.com/banner.jpg">
                    </div>
                </div>

                <!-- Tab 3: Extensions -->
                <div id="settings-tab-extensions" class="tab-content">
                    <input type="hidden" name="extensions_configured" value="1">
                    <p style="color: var(--text-muted); font-size: 0.85rem; margin-bottom: 1.25rem; line-height: 1.4;">
                        Enable or disable extensions for this wiki. Disabling an extension stops loading its client assets and hides its menu triggers. When a page type is disabled, existing pages remain viewable in read-only mode.
                    </p>

                    <div class="ext-group-header">📄 Page Type Extensions</div>
                    <div class="ext-card-list">
                        <?php foreach ($pageTypeExts as $id => $ext): ?>
                            <div class="ext-card">
                                <div class="ext-card-info">
                                    <span class="ext-card-icon"><?= $ext['icon'] ?? '📄' ?></span>
                                    <div class="ext-card-details">
                                        <div class="ext-card-title-row">
                                            <span class="ext-card-title"><?= htmlspecialchars($ext['title'] ?? ucfirst($id)) ?></span>
                                            <span class="doc-badge badge-md" style="font-size: 0.7rem;">PAGE TYPE</span>
                                        </div>
                                        <div class="ext-card-desc">
                                            <?= htmlspecialchars($ext['description'] ?? 'Custom document creation and specialized viewer renderer.') ?>
                                        </div>
                                    </div>
                                </div>
                                <label class="switch-toggle" title="Toggle <?= htmlspecialchars($ext['title'] ?? $id) ?>">
                                    <input type="checkbox" name="extensions[<?= htmlspecialchars($id) ?>]" value="1" <?= !empty($ext['enabled']) ? 'checked' : '' ?>>
                                    <span class="switch-slider"></span>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="ext-group-header">⚡ Tools &amp; Utilities</div>
                    <div class="ext-card-list">
                        <?php foreach ($utilityExts as $id => $ext): ?>
                            <div class="ext-card">
                                <div class="ext-card-info">
                                    <span class="ext-card-icon"><?= $ext['icon'] ?? '⚡' ?></span>
                                    <div class="ext-card-details">
                                        <div class="ext-card-title-row">
                                            <span class="ext-card-title"><?= htmlspecialchars($ext['title'] ?? ucfirst($id)) ?></span>
                                            <span class="doc-badge badge-md" style="font-size: 0.7rem;">UTILITY</span>
                                        </div>
                                        <div class="ext-card-desc">
                                            <?= htmlspecialchars($ext['description'] ?? 'Workspace productivity and administrative tool.') ?>
                                        </div>
                                    </div>
                                </div>
                                <label class="switch-toggle" title="Toggle <?= htmlspecialchars($ext['title'] ?? $id) ?>">
                                    <input type="checkbox" name="extensions[<?= htmlspecialchars($id) ?>]" value="1" <?= !empty($ext['enabled']) ? 'checked' : '' ?>>
                                    <span class="switch-slider"></span>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Tab 4: Email (SMTP) -->
                <div id="settings-tab-smtp" class="tab-content">
                    <div style="margin-bottom: 1rem;">
                        <strong style="color: var(--text-primary); font-size: 0.95rem;">📧 Outbound Socket SMTP Dispatch</strong>
                        <p style="color: var(--text-muted); font-size: 0.82rem; margin: 0.25rem 0 0.5rem 0;">Configure outbound email dispatch for password resets and verification. If disabled or unconfigured, system PHP <code>mail()</code> is used.</p>
                    </div>
                    <div class="form-group">
                        <label class="checkbox-label" style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer; user-select: none;">
                            <input type="checkbox" name="smtp[enabled]" value="1" id="setting-smtp-enabled" <?= !empty($config['smtp']['enabled']) ? 'checked' : '' ?>>
                            <span>Enable Custom Socket SMTP Dispatch</span>
                        </label>
                    </div>
                    <div id="smtp-settings-fields" style="<?= empty($config['smtp']['enabled']) ? 'display: none;' : '' ?> margin-top: 0.75rem; padding: 1rem; background: var(--bg-surface, rgba(0,0,0,0.02)); border: 1px solid var(--border-color); border-radius: 6px;">
                        <div style="display: grid; grid-template-columns: 2fr 1fr 1fr; gap: 0.75rem; margin-bottom: 0.75rem;">
                            <div>
                                <label class="form-label" for="setting-smtp-host">SMTP Host</label>
                                <input type="text" name="smtp[host]" id="setting-smtp-host" class="form-control" value="<?= htmlspecialchars($config['smtp']['host'] ?? '') ?>" placeholder="e.g. smtp.example.com">
                            </div>
                            <div>
                                <label class="form-label" for="setting-smtp-port">Port</label>
                                <input type="number" name="smtp[port]" id="setting-smtp-port" class="form-control" value="<?= htmlspecialchars((string)($config['smtp']['port'] ?? 587)) ?>" placeholder="587">
                            </div>
                            <div>
                                <label class="form-label" for="setting-smtp-encryption">Encryption</label>
                                <select name="smtp[encryption]" id="setting-smtp-encryption" class="form-control">
                                    <option value="tls" <?= (($config['smtp']['encryption'] ?? 'tls') === 'tls') ? 'selected' : '' ?>>TLS / STARTTLS</option>
                                    <option value="ssl" <?= (($config['smtp']['encryption'] ?? '') === 'ssl') ? 'selected' : '' ?>>SSL (Port 465)</option>
                                    <option value="none" <?= (($config['smtp']['encryption'] ?? '') === 'none') ? 'selected' : '' ?>>None (Plain)</option>
                                </select>
                            </div>
                        </div>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; margin-bottom: 0.75rem;">
                            <div>
                                <label class="form-label" for="setting-smtp-username">SMTP Username</label>
                                <input type="text" name="smtp[username]" id="setting-smtp-username" class="form-control" value="<?= htmlspecialchars($config['smtp']['username'] ?? '') ?>" placeholder="user@example.com" autocomplete="off">
                            </div>
                            <div>
                                <label class="form-label" for="setting-smtp-password">SMTP Password</label>
                                <input type="password" name="smtp[password]" id="setting-smtp-password" class="form-control" placeholder="<?= !empty($config['smtp']['password']) ? '•••••••• (leave blank to keep unchanged)' : 'Enter SMTP password' ?>" autocomplete="new-password">
                            </div>
                        </div>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; margin-bottom: 0.75rem;">
                            <div>
                                <label class="form-label" for="setting-smtp-fromemail">Sender Email</label>
                                <input type="email" name="smtp[fromEmail]" id="setting-smtp-fromemail" class="form-control" value="<?= htmlspecialchars($config['smtp']['fromEmail'] ?? '') ?>" placeholder="noreply@yourdomain.com">
                            </div>
                            <div>
                                <label class="form-label" for="setting-smtp-fromname">Sender Name</label>
                                <input type="text" name="smtp[fromName]" id="setting-smtp-fromname" class="form-control" value="<?= htmlspecialchars($config['smtp']['fromName'] ?? 'Standalone Qwiki') ?>" placeholder="Standalone Qwiki">
                            </div>
                        </div>
                        <div style="margin-top: 0.75rem; display: flex; align-items: center; justify-content: space-between; gap: 0.75rem; flex-wrap: wrap;">
                            <button type="button" class="btn btn-outline btn-sm" id="btn-test-smtp">📨 Test Connection &amp; Send Test Email</button>
                            <span id="smtp-test-status" style="font-size: 0.82rem; color: var(--text-muted);"></span>
                        </div>
                    </div>
                </div>

                <!-- Tab 5: Feeds & Integrations -->
                <div id="settings-tab-integrations" class="tab-content">
                    <div class="form-group">
                        <label class="form-label" for="setting-feed-item-count">RSS Feed Item Count</label>
                        <input type="number" name="feedItemCount" id="setting-feed-item-count" class="form-control" value="<?= htmlspecialchars($config['feedItemCount'] ?? '10') ?>" min="1" max="100">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="setting-feed-token">RSS Feed Access Token (For Private Mode)</label>
                        <div style="display: flex; gap: 0.5rem;">
                            <input type="text" name="feedAccessToken" id="setting-feed-token" class="form-control" value="<?= htmlspecialchars($config['feedAccessToken'] ?? '') ?>" placeholder="Leave blank to disable">
                            <button type="button" class="btn btn-outline" onclick="document.getElementById('setting-feed-token').value = Math.random().toString(36).substring(2, 15) + Math.random().toString(36).substring(2, 15);">Generate</button>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">RSS Feed URL</label>
                        <div style="display: flex; gap: 0.5rem; align-items: center;">
                            <input type="text" id="setting-rss-feed-url" class="form-control" readonly value="<?= htmlspecialchars((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . $scriptDir . '/api/feed.php' . (!empty($config['feedAccessToken']) ? '?token=' . urlencode($config['feedAccessToken']) : '')) ?>" onclick="this.select();" style="flex: 1;">
                            <button type="button" class="btn btn-outline btn-copy-rss" id="btn-copy-main-rss" title="Copy RSS Feed URL" data-copy-target="setting-rss-feed-url">
                                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>
                            </button>
                        </div>
                    </div>

                    <div style="margin-top: 1.25rem; padding: 0.85rem 1rem; background: var(--bg-surface, rgba(0,0,0,0.03)); border-radius: 6px; border: 1px solid var(--border-color); font-size: 0.85rem; display: flex; align-items: center; justify-content: space-between; gap: 0.75rem;">
                        <div>
                            <strong>🤖 LLM &amp; AI Agent Access</strong>
                            <span style="color: var(--text-muted); display: block; font-size: 0.8rem; margin-top: 0.15rem;">Provide key-authenticated tree exploration &amp; document retrieval to AI models.</span>
                        </div>
                        <button type="button" class="btn btn-outline btn-sm" id="btn-open-llm-from-settings" style="white-space: nowrap;">Manage Keys</button>
                    </div>

                    <div style="margin-top: 0.75rem; padding: 0.85rem 1rem; background: var(--bg-surface, rgba(0,0,0,0.03)); border-radius: 6px; border: 1px solid var(--border-color); font-size: 0.85rem; display: flex; align-items: center; justify-content: space-between; gap: 0.75rem;">
                        <div>
                            <strong>✨ Gemini AI Assistant &amp; Settings</strong>
                            <span style="color: var(--text-muted); display: block; font-size: 0.8rem; margin-top: 0.15rem;">Configure Google Gemini API key, model selection, automated metadata &amp; summaries.</span>
                        </div>
                        <button type="button" class="btn btn-outline btn-sm" id="btn-open-gemini-from-settings" data-gemini-tab="settings" style="white-space: nowrap;">AI Settings</button>
                    </div>

                    <?php if (!empty($isSubwiki)): ?>
                        <div style="margin-top: 1.5rem; padding-top: 1.25rem; border-top: 1px solid var(--border-color);">
                            <div class="form-group">
                                <label class="form-label" for="setting-parent-title">Parent Wiki Title</label>
                                <input type="text" name="parentTitle" id="setting-parent-title" class="form-control" value="<?= htmlspecialchars($config['parentTitle'] ?? $parentTitle) ?>" placeholder="Parent Wiki Title">
                                <small style="color: var(--text-muted); font-size: 0.8rem; display: block; margin-top: 0.25rem;">
                                    Title displayed on the "← Back to..." sidebar navigation banner.
                                </small>
                            </div>
                            <div class="form-group">
                                <label class="form-label" for="setting-parent-url">Parent Wiki URL</label>
                                <input type="text" name="parentUrl" id="setting-parent-url" class="form-control" value="<?= htmlspecialchars($config['parentUrl'] ?? '../') ?>" placeholder="../">
                                <small style="color: var(--text-muted); font-size: 0.8rem; display: block; margin-top: 0.25rem;">
                                    Target URL of the parent wiki.
                                </small>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="settings-footer">
                <button type="submit" class="btn btn-primary" style="width: 100%;">Save Settings</button>
            </div>
        </form>

        <?php if (\Qwiki\Core\Config::isDemoMode() && \Qwiki\Core\DemoManager::isDemoConfigured()): ?>
            <div style="margin-top: 1rem; padding-top: 1rem; border-top: 1px solid var(--border-color, #e5e7eb); flex-shrink: 0;">
                <label class="form-label" style="color: var(--danger-color, #ef4444); font-weight: 600;">🔄 Reset Demo Package</label>
                <p style="font-size: 0.82rem; color: var(--text-muted); margin-bottom: 0.5rem;">Reload the demo package back to its fresh state. All visitor additions and changes will be wiped clean.</p>
                <button type="button" class="btn btn-outline btn-danger-text btn-sm" id="btn-reload-demo-package" style="width: 100%;">Reload Demo Package</button>
            </div>
        <?php endif; ?>
    </div>
</div>
