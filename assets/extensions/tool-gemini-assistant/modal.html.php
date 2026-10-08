<?php
/**
 * Standalone Qwiki - Gemini AI Assistant Extension Modal
 * 
 * Included by ExtensionManager::renderUtilityModals()
 */
use Qwiki\Core\Config;
use Qwiki\Core\Auth;

$config = Config::load();
$books = $config['books'] ?? [];
$baseUrl = Config::getBaseUrl();

// Recursive helper to flatten documents for the select list
if (!function_exists('gemini_build_doc_options')) {
    function gemini_build_doc_options($items, $prefix = '') {
        $html = '';
        foreach ($items as $item) {
            $type = $item['type'] ?? 'markdown';
            if ($type === 'folder' && !empty($item['items'])) {
                $html .= gemini_build_doc_options($item['items'], $prefix . ($item['title'] ?? 'Folder') . ' / ');
            } elseif (!empty($item['slug'])) {
                $title = htmlspecialchars($prefix . ($item['title'] ?? $item['slug']));
                $slug = htmlspecialchars($item['slug']);
                $desc = htmlspecialchars($item['description'] ?? '');
                $img = htmlspecialchars($item['image'] ?? '');
                $tags = htmlspecialchars(json_encode($item['tags'] ?? []));
                $html .= "<option value=\"{$slug}\" data-desc=\"{$desc}\" data-image=\"{$img}\" data-tags=\"{$tags}\">{$title}</option>\n";
            }
        }
        return $html;
    }
}
?>
<div class="modal-overlay" id="modal-gemini-assistant" style="z-index: 1060;">
    <div class="modal-card gemini-modal-card" style="max-width: 950px; width: 95%;">
        <!-- Header -->
        <div class="modal-header">
            <div style="display: flex; align-items: center; gap: 0.75rem;">
                <h3 style="margin: 0; display: flex; align-items: center; gap: 0.5rem;">
                    <span>✨</span> Gemini AI Assistant
                </h3>
                <span class="doc-badge badge-md" id="gemini-status-badge" style="background: rgba(59, 130, 246, 0.15); color: #3b82f6; border: 1px solid rgba(59, 130, 246, 0.3);">Flash 2.5</span>
            </div>
            <button class="modal-close" data-close="modal-gemini-assistant" aria-label="Close">&times;</button>
        </div>

        <!-- Tab Navigation -->
        <div class="gemini-nav-tabs">
            <button type="button" class="gemini-tab-btn active" data-tab="social" id="gemini-tab-btn-social">
                📱 Social Media &amp; Metadata
            </button>
            <button type="button" class="gemini-tab-btn" data-tab="content" id="gemini-tab-btn-content">
                📝 Executive TL;DR &amp; Polish
            </button>
            <button type="button" class="gemini-tab-btn" data-tab="settings" id="gemini-tab-btn-settings">
                ⚙️ Gemini API Key &amp; Settings
            </button>
        </div>

        <!-- Global Alert Banner -->
        <div id="gemini-alert-banner" class="gemini-alert" style="display: none;"></div>

        <!-- ============================================================= -->
        <!-- TAB 1: SOCIAL MEDIA & METADATA STUDIO                         -->
        <!-- ============================================================= -->
        <div id="gemini-view-social" class="gemini-tab-view active">
            <div class="gemini-studio-topbar">
                <div style="flex: 1; min-width: 250px;">
                    <label class="form-label" for="gemini-select-doc">Target Document</label>
                    <select id="gemini-select-doc" class="form-control">
                        <option value="">-- Choose a document --</option>
                        <?php
                        foreach ($books as $b) {
                            if (!empty($b['items'])) {
                                echo gemini_build_doc_options($b['items'], ($b['title'] ?? 'Section') . ' / ');
                            }
                        }
                        ?>
                    </select>
                </div>
                <div style="display: flex; align-items: flex-end;">
                    <button type="button" class="btn btn-primary" id="gemini-btn-generate-meta" style="height: 38px; display: flex; align-items: center; gap: 0.4rem;">
                        <span>✨</span> Generate Metadata
                    </button>
                </div>
            </div>

            <div id="gemini-meta-loading" class="gemini-loading-spinner" style="display: none;">
                <div class="gemini-spinner"></div>
                <p>Generating optimized social metadata &amp; blurbs with Google Gemini...</p>
            </div>

            <!-- Studio Two-Column Grid -->
            <div id="gemini-meta-results" class="gemini-studio-grid" style="display: none;">
                <!-- Left: Form Controls -->
                <div class="gemini-form-col">
                    <div class="form-group">
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <label class="form-label" for="gemini-meta-desc">Short Description (OpenGraph &amp; Twitter)</label>
                            <span id="gemini-desc-counter" class="gemini-char-counter">0 / 160</span>
                        </div>
                        <textarea id="gemini-meta-desc" class="form-control" rows="3" placeholder="Engaging summary for search engines and social cards..."></textarea>
                        <small style="color: var(--text-muted); font-size: 0.8rem; display: block; margin-top: 0.25rem;">
                            Shown when link is shared on X/Twitter, LinkedIn, Slack, and search engines.
                        </small>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Suggested Taxonomy Tags</label>
                        <div id="gemini-meta-tags-pills" class="gemini-tag-pills"></div>
                        <div style="display: flex; gap: 0.5rem; margin-top: 0.5rem;">
                            <input type="text" id="gemini-add-tag-input" class="form-control" placeholder="Add custom tag..." style="font-size: 0.85rem;">
                            <button type="button" class="btn btn-outline btn-sm" id="gemini-btn-add-tag">Add</button>
                        </div>
                    </div>

                    <div class="form-group" id="gemini-headlines-group" style="display: none;">
                        <label class="form-label">Catchy Headline Variations</label>
                        <div id="gemini-meta-headlines-list" class="gemini-headlines-list"></div>
                    </div>

                    <div class="form-group">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.35rem;">
                            <label class="form-label" style="margin: 0;">Ready-to-Share Social Post</label>
                            <button type="button" class="btn btn-outline btn-sm" id="gemini-btn-copy-blurb">📋 Copy Post</button>
                        </div>
                        <textarea id="gemini-meta-blurb" class="form-control" rows="4" style="font-size: 0.85rem; font-family: inherit;" placeholder="Formatted social copy with bullet points and hashtags..."></textarea>
                    </div>

                    <div style="margin-top: 1rem; display: flex; flex-direction: column; gap: 0.5rem;">
                        <button type="button" class="btn btn-success" id="gemini-btn-apply-meta" style="width: 100%; display: flex; align-items: center; justify-content: center; gap: 0.4rem;" <?= !Auth::isAdmin() ? 'disabled title="Administrator permission required to apply changes to document files."' : '' ?>>
                            <span>💾</span> Save Metadata to Document
                        </button>
                        <?php if (!Auth::isAdmin()): ?>
                            <small style="color: var(--text-muted); font-size: 0.78rem; text-align: center;">🔒 Administrator access is required to modify document definitions directly. You can copy the generated description and tags above.</small>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Right: Social Preview Simulator -->
                <div class="gemini-preview-col">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                        <label class="form-label" style="margin: 0;">Live Social Card Preview</label>
                        <div class="gemini-preview-tabs">
                            <button type="button" class="gemini-preview-tab-btn active" data-platform="twitter">X / Twitter</button>
                            <button type="button" class="gemini-preview-tab-btn" data-platform="linkedin">LinkedIn</button>
                        </div>
                    </div>

                    <!-- Simulated Social Card -->
                    <div class="gemini-social-card" id="gemini-card-simulator">
                        <div class="gemini-card-image-wrap">
                            <img id="gemini-card-img" src="" alt="Social Card Preview" style="display: none;">
                            <div id="gemini-card-img-placeholder" class="gemini-card-placeholder">
                                <span>🖼️ OpenGraph Card Preview</span>
                            </div>
                        </div>
                        <div class="gemini-card-body">
                            <div class="gemini-card-domain" id="gemini-card-domain"><?= htmlspecialchars(parse_url($baseUrl, PHP_URL_HOST) ?: 'docs.example.com') ?></div>
                            <div class="gemini-card-title" id="gemini-card-title">Document Title</div>
                            <div class="gemini-card-desc" id="gemini-card-desc">Your AI-generated description will render here in real time.</div>
                        </div>
                    </div>

                    <div class="gemini-preview-tip">
                        💡 <strong>Real-time Simulator:</strong> Edits to the short description or document image reflect instantly in this social preview card.
                    </div>
                </div>
            </div>
        </div>

        <!-- ============================================================= -->
        <!-- TAB 2: EXECUTIVE TL;DR & CONTENT ASSISTANT                   -->
        <!-- ============================================================= -->
        <div id="gemini-view-content" class="gemini-tab-view" style="display: none;">
            <div class="gemini-studio-topbar">
                <div style="flex: 1; min-width: 250px;">
                    <label class="form-label" for="gemini-select-content-doc">Target Document</label>
                    <select id="gemini-select-content-doc" class="form-control">
                        <option value="">-- Choose a document --</option>
                        <?php
                        foreach ($books as $b) {
                            if (!empty($b['items'])) {
                                echo gemini_build_doc_options($b['items'], ($b['title'] ?? 'Section') . ' / ');
                            }
                        }
                        ?>
                    </select>
                </div>
                <div style="display: flex; align-items: flex-end;">
                    <button type="button" class="btn btn-primary" id="gemini-btn-generate-tldr" style="height: 38px; display: flex; align-items: center; gap: 0.4rem;">
                        <span>📋</span> Generate Executive TL;DR
                    </button>
                </div>
            </div>

            <div id="gemini-tldr-loading" class="gemini-loading-spinner" style="display: none;">
                <div class="gemini-spinner"></div>
                <p>Generating Executive TL;DR summary with Gemini...</p>
            </div>

            <div id="gemini-tldr-results" style="display: none; margin-top: 1rem;">
                <div class="form-group">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.35rem;">
                        <label class="form-label" style="margin: 0;">Markdown TL;DR Callout</label>
                        <button type="button" class="btn btn-outline btn-sm" id="gemini-btn-copy-tldr">📋 Copy Markdown</button>
                    </div>
                    <textarea id="gemini-tldr-markdown" class="form-control" rows="5" style="font-family: monospace; font-size: 0.85rem;"></textarea>
                    <small style="color: var(--text-muted); font-size: 0.8rem; display: block; margin-top: 0.25rem;">
                        Paste this callout at the very top of your document for quick onboarding.
                    </small>
                </div>
            </div>
        </div>

        <!-- ============================================================= -->
        <!-- TAB 3: SETTINGS & API KEY CONFIGURATION                      -->
        <!-- ============================================================= -->
        <div id="gemini-view-settings" class="gemini-tab-view" style="display: none;">
            <form id="gemini-settings-form">
                <div class="gemini-settings-notice">
                    <div style="font-size: 1.5rem;">🔑</div>
                    <div>
                        <strong>Bring Your Own Key (BYOK) Architecture</strong>
                        <p style="margin: 0.25rem 0 0 0; font-size: 0.85rem; color: var(--text-muted);">
                            Standalone Qwiki supports a two-tier key model: a <strong>Site Default Key</strong> for site-wide use (or professional deployments with a single paid key), and an optional <strong>Personal Key Override</strong> for signed-in users (ideal for hobby/experimental installations where users bring their own free Gemini keys to avoid overburdening the shared site key).
                            Obtain a free API key at <a href="https://aistudio.google.com/app/apikey" target="_blank" rel="noopener noreferrer" style="color: var(--accent-color); text-decoration: underline;">Google AI Studio ↗</a>.
                        </p>
                    </div>
                </div>

                <!-- Active Key Status Banner -->
                <div class="gemini-key-status-banner" id="gemini-active-key-banner" style="margin-top: 1rem;">
                    <div>
                        <div style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.04em; margin-bottom: 0.25rem;">Current Active Key For Your Session</div>
                        <div style="display: flex; align-items: center; gap: 0.5rem;">
                            <span class="gemini-tier-badge badge-active" id="gemini-active-source-badge">Checking...</span>
                            <span id="gemini-active-key-text" style="font-family: monospace; font-size: 0.88rem; color: var(--text-primary);">••••••••••••</span>
                        </div>
                    </div>
                    <div style="font-size: 0.82rem; color: var(--text-muted); text-align: right;" id="gemini-active-status-desc">
                        Resolving key configuration...
                    </div>
                </div>

                <!-- TIER 1: PERSONAL API KEY (USER OVERRIDE) -->
                <div class="gemini-key-tier-card" id="gemini-tier-card-user">
                    <div class="gemini-key-tier-header">
                        <div class="gemini-key-tier-title">
                            <span>👤</span> Personal API Key (Your BYOK Override)
                        </div>
                        <span class="gemini-tier-badge" id="gemini-badge-user">User Override</span>
                    </div>
                    <p class="gemini-key-tier-desc">
                        Stored with your account (<strong><?= htmlspecialchars(Auth::getCurrentUser()['username'] ?? 'User') ?></strong>). When set, your requests use this personal key instead of consuming the site's shared quota. Leave empty to use the Site Default Key.
                    </p>

                    <div class="form-group" style="margin-bottom: 0.85rem;">
                        <label class="form-label" for="gemini-input-user-apikey">Personal Gemini API Key</label>
                        <div style="display: flex; gap: 0.5rem;">
                            <input type="password" id="gemini-input-user-apikey" class="form-control" placeholder="AIzaSy... (leave blank to use site default)" autocomplete="off" style="font-family: monospace;">
                            <button type="button" class="btn btn-outline" id="gemini-btn-toggle-user-key" title="Toggle Visibility">👁️</button>
                            <button type="button" class="btn btn-outline" id="gemini-btn-clear-user-key" title="Clear personal key and revert to site default" style="display: none; white-space: nowrap;">Revert to Site Key</button>
                        </div>
                    </div>

                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label" for="gemini-select-user-model" style="margin-bottom: 0.35rem;">Preferred Model (Personal)</label>
                        <select id="gemini-select-user-model" class="form-control">
                            <option value="">(Inherit Site Default Model)</option>
                            <option value="gemini-2.5-flash">gemini-2.5-flash (Fast, hybrid reasoning)</option>
                            <option value="gemini-2.5-pro">gemini-2.5-pro (Advanced: Complex reasoning & code)</option>
                            <option value="gemini-2.5-flash-lite">gemini-2.5-flash-lite (Cost-efficient, high throughput)</option>
                            <option value="gemini-3.5-flash">gemini-3.5-flash (Next-generation high performance)</option>
                        </select>
                    </div>
                </div>

                <!-- TIER 2: SITE DEFAULT API KEY (SITE-WIDE) -->
                <div class="gemini-key-tier-card" id="gemini-tier-card-site">
                    <div class="gemini-key-tier-header">
                        <div class="gemini-key-tier-title">
                            <span>🌐</span> Site Default API Key (Shared / Fallback)
                        </div>
                        <span class="gemini-tier-badge" id="gemini-badge-site">Site Default</span>
                    </div>
                    <p class="gemini-key-tier-desc">
                        Global key used across this wiki for users without a personal key override. In a professional deployment, configure a single paid site-wide key here.
                    </p>

                    <?php if (Auth::isAdmin()): ?>
                        <div class="form-group" style="margin-bottom: 0.85rem;">
                            <label class="form-label" for="gemini-input-site-apikey">Site Gemini API Key</label>
                            <div style="display: flex; gap: 0.5rem;">
                                <input type="password" id="gemini-input-site-apikey" class="form-control" placeholder="AIzaSy..." autocomplete="off" style="font-family: monospace;">
                                <button type="button" class="btn btn-outline" id="gemini-btn-toggle-site-key" title="Toggle Visibility">👁️</button>
                            </div>
                            <small id="gemini-site-env-notice" style="color: #10b981; font-size: 0.8rem; margin-top: 0.25rem; display: none;">
                                🔒 Key locked via environment variable (GEMINI_API_KEY).
                            </small>
                        </div>

                        <div class="form-group" style="margin-bottom: 0;">
                            <label class="form-label" for="gemini-select-site-model" style="margin-bottom: 0.35rem;">Site Default Model</label>
                            <select id="gemini-select-site-model" class="form-control">
                                <option value="gemini-2.5-flash">gemini-2.5-flash (Recommended: Fast, hybrid reasoning, 1M context)</option>
                                <option value="gemini-2.5-pro">gemini-2.5-pro (Advanced: Complex reasoning & code synthesis)</option>
                                <option value="gemini-2.5-flash-lite">gemini-2.5-flash-lite (Cost-efficient, high throughput)</option>
                                <option value="gemini-3.5-flash">gemini-3.5-flash (Next-generation high performance)</option>
                            </select>
                        </div>
                    <?php else: ?>
                        <div style="padding: 0.75rem 1rem; background: var(--bg-surface, rgba(0,0,0,0.02)); border: 1px solid var(--border-color); border-radius: 6px; font-size: 0.85rem;">
                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                <span>Status: <strong id="gemini-viewer-site-status">Checking...</strong></span>
                                <span id="gemini-viewer-site-model" style="font-family: monospace; font-size: 0.8rem; color: var(--text-muted);">gemini-2.5-flash</span>
                            </div>
                            <small style="color: var(--text-muted); display: block; margin-top: 0.35rem;">
                                🔒 Only administrators can edit the site-wide default key. Use the Personal API Key section above to set your own key.
                            </small>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Hidden inputs for backward-compatibility aliases if queried by external scripts -->
                <input type="hidden" id="gemini-input-apikey" value="">
                <select id="gemini-select-model" style="display: none;"></select>

                <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 1.5rem; padding-top: 1rem; border-top: 1px solid var(--border-color); flex-wrap: wrap; gap: 0.75rem;">
                    <div style="display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap;">
                        <button type="button" class="btn btn-outline" id="gemini-btn-test-conn">🔌 Test Connection</button>
                        <button type="button" class="btn btn-outline btn-xs" id="gemini-btn-detect-models" style="font-size: 0.8rem; padding: 0.3rem 0.6rem;" title="Query Google API for models supported by your active key">🔍 Auto-Detect Models</button>
                        <span id="gemini-test-result" style="font-size: 0.85rem;"></span>
                    </div>
                    <button type="submit" class="btn btn-primary" id="gemini-btn-save-settings">Save API Settings</button>
                </div>
            </form>
        </div>
    </div>
</div>
