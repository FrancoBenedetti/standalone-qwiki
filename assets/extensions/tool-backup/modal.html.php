<?php
/**
 * Standalone Qwiki - Backup & Export Extension Modal
 * 
 * Included by ExtensionManager::renderUtilityModals()
 */
?>
<div class="modal-overlay" id="modal-backup" style="z-index: 1060;">
    <div class="modal-card backup-modal-card" style="max-width: 840px; width: 95%;">
        <!-- Modal Header -->
        <div class="modal-header">
            <div style="display: flex; align-items: center; gap: 0.75rem;">
                <h3 style="margin: 0; display: flex; align-items: center; gap: 0.5rem;">
                    <span>📦</span> Backup & Export
                </h3>
                <span class="doc-badge badge-md" id="backup-total-badge">Scanning wiki...</span>
            </div>
            <button class="modal-close" data-close="modal-backup" aria-label="Close">&times;</button>
        </div>

        <!-- Mode Switcher Navigation -->
        <div class="backup-mode-nav">
            <button type="button" class="backup-nav-tab active" data-tab="full" id="backup-tab-btn-full">
                ⚡ Full Backup (1-Click)
            </button>
            <button type="button" class="backup-nav-tab" data-tab="selective" id="backup-tab-btn-selective">
                🗂️ Selective Export
            </button>
        </div>

        <!-- Notification / Status Banner -->
        <div id="backup-status-banner" class="backup-status-banner" style="display: none;">
            <div class="backup-spinner"></div>
            <span id="backup-status-text">Preparing ZIP archive for download...</span>
        </div>

        <!-- 1. FULL BACKUP VIEW -->
        <div id="backup-view-full" class="backup-tab-view">
            <p class="backup-desc">
                Preserve your entire wiki in a single, portable ZIP archive containing all document content, uploaded media, navigation hierarchy, and user credentials.
            </p>

            <div class="backup-grid-summary" id="backup-full-summary-grid">
                <!-- Content Card -->
                <div class="backup-summary-card">
                    <div class="backup-card-icon">📄</div>
                    <div class="backup-card-info">
                        <div class="backup-card-title">Document Content</div>
                        <div class="backup-card-metric" id="backup-full-content-metric">Loading...</div>
                        <div class="backup-card-sub">Markdown & HTML pages (<code>content/</code>)</div>
                    </div>
                </div>

                <!-- Media Uploads Card -->
                <div class="backup-summary-card">
                    <div class="backup-card-icon">🖼️</div>
                    <div class="backup-card-info">
                        <div class="backup-card-title">Media & Uploads</div>
                        <div class="backup-card-metric" id="backup-full-uploads-metric">Loading...</div>
                        <div class="backup-card-sub">Images, attachments & files (<code>uploads/</code>)</div>
                    </div>
                </div>

                <!-- Master Config Card -->
                <div class="backup-summary-card">
                    <div class="backup-card-icon">⚙️</div>
                    <div class="backup-card-info">
                        <div class="backup-card-title">Navigation & Settings</div>
                        <div class="backup-card-metric" id="backup-full-qwiki-metric">Loading...</div>
                        <div class="backup-card-sub">Master database & trees (<code>qwiki.json</code>)</div>
                    </div>
                </div>

                <!-- User Database Card -->
                <div class="backup-summary-card">
                    <div class="backup-card-icon">👥</div>
                    <div class="backup-card-info">
                        <div class="backup-card-title">User Accounts</div>
                        <div class="backup-card-metric" id="backup-full-users-metric">Loading...</div>
                        <div class="backup-card-sub">Roles & password hashes (<code>users.json</code>)</div>
                    </div>
                </div>
            </div>

            <!-- Full Backup Options -->
            <div class="backup-options-box">
                <label class="backup-checkbox-label">
                    <input type="checkbox" id="backup-full-opt-users" checked>
                    <span><strong>Include user accounts & roles</strong> (<code>users.json</code>)</span>
                </label>
                <label class="backup-checkbox-label" id="backup-full-opt-subwikis-container" style="display: none;">
                    <input type="checkbox" id="backup-full-opt-subwikis">
                    <span><strong>Include deployed subwikis</strong> (bundle child subwiki folders)</span>
                </label>
            </div>

            <div class="backup-action-bar">
                <button type="button" class="btn btn-primary backup-download-btn" id="btn-backup-download-full">
                    <span>⬇️</span> Download Complete Backup ZIP
                </button>
            </div>
        </div>

        <!-- 2. SELECTIVE EXPORT VIEW -->
        <div id="backup-view-selective" class="backup-tab-view" style="display: none;">
            <p class="backup-desc">
                Select only the specific components, folders, or individual files you want to bundle into the archive.
            </p>

            <!-- Quick Presets Toolbar -->
            <div class="backup-selective-toolbar">
                <div class="backup-preset-group">
                    <button type="button" class="btn btn-outline btn-sm" id="btn-backup-preset-all">Select All</button>
                    <button type="button" class="btn btn-outline btn-sm" id="btn-backup-preset-content">Content Only (No Media)</button>
                    <button type="button" class="btn btn-outline btn-sm" id="btn-backup-preset-media">Media Only</button>
                    <button type="button" class="btn btn-outline btn-sm" id="btn-backup-preset-none">Clear All</button>
                </div>
                <div class="backup-tree-search">
                    <input type="text" id="backup-tree-filter" class="form-control" placeholder="Filter files or folders...">
                </div>
            </div>

            <!-- Root Critical Files Checkboxes -->
            <div class="backup-root-files-box">
                <label class="backup-tree-item-label">
                    <input type="checkbox" class="backup-check-item" data-path="qwiki.json" checked>
                    <span class="backup-icon">⚙️</span>
                    <span class="backup-name"><strong>qwiki.json</strong> (Master Navigation & Settings)</span>
                    <span class="backup-size-tag" id="backup-selective-qwiki-size"></span>
                </label>
                <label class="backup-tree-item-label">
                    <input type="checkbox" class="backup-check-item" data-path="users.json" checked>
                    <span class="backup-icon">👥</span>
                    <span class="backup-name"><strong>users.json</strong> (User Accounts & Roles)</span>
                    <span class="backup-size-tag" id="backup-selective-users-size"></span>
                </label>
            </div>

            <!-- Interactive File & Folder Tree -->
            <div class="backup-tree-wrapper">
                <div id="backup-tree-loading" class="backup-tree-empty-message">
                    <div class="backup-spinner"></div>
                    <span>Scanning wiki directory tree...</span>
                </div>
                <div id="backup-tree-root" class="backup-tree" style="display: none;"></div>
            </div>

            <!-- Subwikis Selector Box (if present) -->
            <div id="backup-subwikis-selective-container" class="backup-subwikis-box" style="display: none;">
                <div class="backup-subwikis-header">
                    <strong>🌐 Subwikis:</strong>
                </div>
                <div id="backup-subwikis-list"></div>
            </div>

            <!-- Selective Bottom Action Bar -->
            <div class="backup-selective-footer">
                <div class="backup-selection-counter">
                    Selected: <strong id="backup-selected-files-count">0</strong> files
                    (<span id="backup-selected-bytes-size">0 B</span>)
                </div>
                <button type="button" class="btn btn-primary" id="btn-backup-download-selective">
                    <span>⬇️</span> Download Selected Files
                </button>
            </div>
        </div>

    </div>
</div>
