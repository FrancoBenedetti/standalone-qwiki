<?php
/**
 * Standalone Qwiki - Document Postbox Extension Modal
 * 
 * Included by ExtensionManager::renderUtilityModals()
 */
use Qwiki\Core\Config;

$postboxToken = Config::getPostboxToken();
$baseUrl = Config::getBaseUrl();
$endpointUrl = $baseUrl . 'api/admin.php?action=ext_postbox_receive';
?>
<div class="modal-overlay" id="modal-postbox" style="z-index: 1060;">
    <div class="modal-card postbox-modal-card" style="max-width: 900px; width: 95%;">
        <!-- Modal Header -->
        <div class="modal-header">
            <div style="display: flex; align-items: center; gap: 0.75rem;">
                <h3 style="margin: 0; display: flex; align-items: center; gap: 0.5rem;">
                    <span>📬</span> Document Postbox
                </h3>
                <span class="doc-badge badge-md" id="postbox-unread-badge" style="display: none; background: #3b82f6; color: #fff;">0 Inbound</span>
            </div>
            <button class="modal-close" data-close="modal-postbox" aria-label="Close">&times;</button>
        </div>

        <!-- Tab Navigation -->
        <div class="postbox-nav-tabs">
            <button type="button" class="postbox-tab-btn active" data-tab="inbox" id="postbox-tab-btn-inbox">
                📬 Inbound Postbox <span id="postbox-tab-inbox-counter" class="postbox-badge-inline" style="display: none;">0</span>
            </button>
            <button type="button" class="postbox-tab-btn" data-tab="outbox" id="postbox-tab-btn-outbox">
                📤 Send Documents
            </button>
            <button type="button" class="postbox-tab-btn" data-tab="settings" id="postbox-tab-btn-settings">
                ⚙️ Peers & Settings
            </button>
        </div>

        <!-- Notification Banner -->
        <div id="postbox-alert-banner" class="postbox-alert" style="display: none;"></div>

        <!-- TAB 1: INBOUND INBOX -->
        <div id="postbox-view-inbox" class="postbox-tab-view">
            <div class="postbox-section-intro">
                <p>
                    Review, customize, and ingest documents sent to this wiki. Designate the target category, modify slugs or metadata, and review attached media before committing.
                </p>
            </div>

            <div id="postbox-inbox-loading" style="text-align: center; padding: 2rem;">
                <div class="postbox-spinner"></div>
                <p style="color: var(--text-muted); margin-top: 0.5rem;">Checking postbox for incoming packages...</p>
            </div>

            <div id="postbox-inbox-empty" style="display: none; text-align: center; padding: 3rem 1rem; border: 2px dashed var(--border-color); border-radius: 8px; margin: 1rem 0;">
                <div style="font-size: 2.5rem; margin-bottom: 0.5rem;">📭</div>
                <h4 style="margin: 0 0 0.5rem 0;">Your Postbox is Empty</h4>
                <p style="color: var(--text-muted); margin: 0; font-size: 0.9rem;">No documents are currently awaiting review.</p>
            </div>

            <div id="postbox-inbox-container" style="display: none;">
                <!-- Envelopes will be rendered dynamically here -->
            </div>
        </div>

        <!-- TAB 2: OUTBOUND DISPATCH -->
        <div id="postbox-view-outbox" class="postbox-tab-view" style="display: none;">
            <div class="postbox-section-intro">
                <p>
                    Package documents along with their embedded media, images, and metadata, and dispatch them to another wiki in your subwiki group, a tenant peer, or a remote domain.
                </p>
            </div>

            <form id="postbox-send-form">
                <!-- Scope Selection -->
                <div class="form-group" style="margin-bottom: 1.25rem;">
                    <label class="form-label" style="font-weight: 600;">Transfer Scope</label>
                    <div style="display: flex; gap: 1rem; flex-wrap: wrap; margin-top: 0.25rem;">
                        <label class="postbox-radio-pill">
                            <input type="radio" name="postbox_mode" value="single" checked>
                            <span>📄 Current / Specific Document</span>
                        </label>
                        <label class="postbox-radio-pill">
                            <input type="radio" name="postbox_mode" value="category">
                            <span>📁 Entire Category</span>
                        </label>
                        <label class="postbox-radio-pill">
                            <input type="radio" name="postbox_mode" value="bulk">
                            <span>📦 Bulk Selection</span>
                        </label>
                    </div>
                </div>

                <!-- Mode 1: Single Doc -->
                <div id="postbox-scope-single" class="postbox-scope-box">
                    <div class="form-group">
                        <label class="form-label" for="postbox-single-doc-select">Select Document</label>
                        <select id="postbox-single-doc-select" class="form-control">
                            <option value="">-- Choose a document --</option>
                        </select>
                    </div>
                </div>

                <!-- Mode 2: Category -->
                <div id="postbox-scope-category" class="postbox-scope-box" style="display: none;">
                    <div class="form-group">
                        <label class="form-label" for="postbox-category-select">Select Category to Transfer</label>
                        <select id="postbox-category-select" class="form-control">
                            <option value="">-- Choose a category --</option>
                        </select>
                    </div>
                </div>

                <!-- Mode 3: Bulk Selection -->
                <div id="postbox-scope-bulk" class="postbox-scope-box" style="display: none;">
                    <label class="form-label">Select Documents to Transfer</label>
                    <div id="postbox-bulk-list" class="postbox-checklist-container">
                        <!-- Populated dynamically -->
                    </div>
                </div>

                <!-- Destination Selection -->
                <div class="form-group" style="margin-top: 1.5rem;">
                    <label class="form-label" for="postbox-destination-select" style="font-weight: 600;">Recipient Destination</label>
                    <select id="postbox-destination-select" class="form-control" required>
                        <option value="">-- Select Destination Wiki --</option>
                        <optgroup label="Local Subwikis & Group" id="postbox-optgroup-local"></optgroup>
                        <optgroup label="Saved Domain Peers" id="postbox-optgroup-remote"></optgroup>
                        <optgroup label="Direct Connection">
                            <option value="custom">🌐 Custom Remote Wiki URL & Token...</option>
                        </optgroup>
                    </select>
                </div>

                <!-- Custom Remote Endpoint Details -->
                <div id="postbox-custom-dest-box" style="display: none; margin-top: 1rem; padding: 1rem; background: var(--bg-tertiary); border-radius: 6px; border: 1px solid var(--border-color);">
                    <div class="form-group" style="margin-bottom: 0.75rem;">
                        <label class="form-label" for="postbox-custom-url">Peer Wiki Base URL</label>
                        <input type="url" id="postbox-custom-url" class="form-control" placeholder="https://example.com/wiki/">
                        <small style="color: var(--text-muted);">The root web address of the target Standalone Qwiki.</small>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="postbox-custom-token">Postbox Token</label>
                        <input type="text" id="postbox-custom-token" class="form-control" placeholder="Recipient's 32-character token">
                    </div>
                </div>

                <div style="display: flex; justify-content: flex-end; gap: 0.75rem; margin-top: 1.5rem; border-top: 1px solid var(--border-color); padding-top: 1rem;">
                    <button type="button" class="btn btn-outline" data-close="modal-postbox">Cancel</button>
                    <button type="submit" id="postbox-send-btn" class="btn btn-primary" style="display: flex; align-items: center; gap: 0.5rem;">
                        <span>🚀</span> Package & Send
                    </button>
                </div>
            </form>
        </div>

        <!-- TAB 3: PEERS & SETTINGS -->
        <div id="postbox-view-settings" class="postbox-tab-view" style="display: none;">
            <div class="postbox-section-intro">
                <p>
                    Configure this wiki's postbox token for receiving documents, or save frequently used peer wikis on this domain or remote servers.
                </p>
            </div>

            <!-- Token Section -->
            <div class="postbox-card">
                <h4 style="margin: 0 0 0.5rem 0; display: flex; align-items: center; gap: 0.5rem;">
                    <span>🔑</span> My Receiving Postbox Token
                </h4>
                <p style="color: var(--text-muted); font-size: 0.85rem; margin-bottom: 0.75rem;">
                    Other wikis must provide this token to transfer documents into your postbox inbox.
                </p>
                <div style="display: flex; gap: 0.5rem; align-items: center;">
                    <input type="text" id="postbox-my-token-field" class="form-control" readonly value="<?= htmlspecialchars($postboxToken) ?>" style="font-family: monospace; font-size: 0.9rem; max-width: 400px; background: var(--bg-tertiary);">
                    <button type="button" id="postbox-copy-token-btn" class="btn btn-outline btn-sm">📋 Copy Token</button>
                    <button type="button" id="postbox-regen-token-btn" class="btn btn-outline btn-sm" style="color: #ef4444;">🔄 Regenerate</button>
                </div>
                <div style="margin-top: 0.75rem; font-size: 0.8rem; color: var(--text-muted);">
                    Webhook Ingestion Endpoint: <code><?= htmlspecialchars($endpointUrl) ?></code>
                </div>
            </div>

            <!-- Saved Peers Section -->
            <div class="postbox-card" style="margin-top: 1.5rem;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem;">
                    <h4 style="margin: 0; display: flex; align-items: center; gap: 0.5rem;">
                        <span>🌐</span> Saved Peer Wikis
                    </h4>
                    <button type="button" id="postbox-add-peer-btn" class="btn btn-outline btn-sm">+ Add Peer</button>
                </div>

                <div id="postbox-peers-list">
                    <p id="postbox-no-peers" style="color: var(--text-muted); font-size: 0.9rem; margin: 0;">
                        No remote peer wikis saved yet. Local subwikis are automatically discovered without needing configuration here.
                    </p>
                    <div id="postbox-peers-table-wrap" style="display: none;">
                        <table class="postbox-table" style="width: 100%; border-collapse: collapse; font-size: 0.9rem;">
                            <thead>
                                <tr style="border-bottom: 1px solid var(--border-color); text-align: left;">
                                    <th style="padding: 0.5rem;">Name</th>
                                    <th style="padding: 0.5rem;">Endpoint URL</th>
                                    <th style="padding: 0.5rem;">Token</th>
                                    <th style="padding: 0.5rem; text-align: right;">Action</th>
                                </tr>
                            </thead>
                            <tbody id="postbox-peers-tbody"></tbody>
                        </table>
                    </div>
                </div>

                <!-- Add Peer Form (hidden by default) -->
                <form id="postbox-new-peer-form" style="display: none; margin-top: 1rem; padding: 1rem; background: var(--bg-tertiary); border-radius: 6px; border: 1px solid var(--border-color);">
                    <h5 style="margin: 0 0 0.75rem 0;">Add Saved Peer Wiki</h5>
                    <div style="display: grid; grid-template-columns: 1fr 2fr 1.5fr auto; gap: 0.5rem; align-items: end;">
                        <div>
                            <label class="form-label" style="font-size: 0.8rem;">Peer Name</label>
                            <input type="text" id="postbox-new-peer-name" class="form-control" placeholder="Marketing Wiki" required>
                        </div>
                        <div>
                            <label class="form-label" style="font-size: 0.8rem;">Base URL</label>
                            <input type="url" id="postbox-new-peer-url" class="form-control" placeholder="https://example.com/wiki/" required>
                        </div>
                        <div>
                            <label class="form-label" style="font-size: 0.8rem;">Postbox Token</label>
                            <input type="text" id="postbox-new-peer-token" class="form-control" placeholder="Token" required>
                        </div>
                        <div style="display: flex; gap: 0.25rem;">
                            <button type="submit" class="btn btn-primary btn-sm">Save</button>
                            <button type="button" id="postbox-cancel-peer-btn" class="btn btn-outline btn-sm">Cancel</button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Desktop CLI & Tools Section -->
            <div class="postbox-card" style="margin-top: 1.5rem;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem; flex-wrap: wrap; gap: 0.5rem;">
                    <h4 style="margin: 0; display: flex; align-items: center; gap: 0.5rem;">
                        <span>🖥️</span> Desktop CLI & OS Context Menu Tools
                    </h4>
                    <a href="api/admin.php?action=ext_postbox_download_cli" class="btn btn-primary btn-sm" id="postbox-download-cli-btn" style="text-decoration: none; display: inline-flex; align-items: center; gap: 0.4rem;">
                        <span>📥</span> Download Desktop Tools Bundle (ZIP)
                    </a>
                </div>
                <p style="color: var(--text-muted); font-size: 0.85rem; margin-bottom: 0.75rem;">
                    Send single documents or entire folders from your computer (Linux, Windows, macOS) directly into this wiki's postbox inbox.
                </p>
                <div style="background: var(--bg-tertiary); padding: 0.75rem 1rem; border-radius: 6px; font-family: monospace; font-size: 0.85rem; border: 1px solid var(--border-color); overflow-x: auto;">
                    <span style="color: var(--text-muted);"># Send a single file:</span><br>
                    python3 qwiki-postbox.py send document.md<br><br>
                    <span style="color: var(--text-muted);"># Send an entire folder with category hint:</span><br>
                    python3 qwiki-postbox.py send my-project-docs/ --category "Project Specs"
                </div>
                <div style="margin-top: 0.75rem; font-size: 0.8rem; color: var(--text-muted);">
                    Bundle includes <strong>Nautilus/Nemo</strong> (Linux), <strong>Send To</strong> (Windows), and <strong>Finder Quick Action</strong> (macOS) right-click scripts.
                </div>
            </div>
        </div>
    </div>
</div>

<!-- INGESTION DIALOG MODAL (Submodal for reviewing/editing doc before commit) -->
<div class="modal-overlay" id="modal-postbox-ingest" style="z-index: 1070; display: none;">
    <div class="modal-card" style="max-width: 650px; width: 95%;">
        <div class="modal-header">
            <h3 style="margin: 0; display: flex; align-items: center; gap: 0.5rem;">
                <span>📥</span> Review & Ingest Document
            </h3>
            <button class="modal-close" data-close="modal-postbox-ingest" aria-label="Close">&times;</button>
        </div>
        <form id="postbox-ingest-form">
            <input type="hidden" id="postbox-ingest-batch-id" name="batch_id">
            <input type="hidden" id="postbox-ingest-doc-index" name="doc_index">

            <!-- Category Hint Notice if present -->
            <div id="postbox-category-hint-notice" style="display: none; padding: 0.5rem 0.75rem; background: rgba(59, 130, 246, 0.08); border-left: 3px solid #3b82f6; border-radius: 4px; font-size: 0.85rem; margin-bottom: 0.75rem;">
                <span>💡 Sender suggested category: <strong id="postbox-category-hint-name"></strong></span>
                <button type="button" id="postbox-apply-category-hint-btn" class="btn btn-outline btn-sm" style="margin-left: 0.5rem; padding: 0.1rem 0.4rem; font-size: 0.75rem;">Use This</button>
            </div>

            <!-- Target Category Selection -->
            <div class="form-group" style="margin-bottom: 1rem;">
                <label class="form-label" for="postbox-ingest-category" style="font-weight: 600;">Destination Category *</label>
                <div style="display: flex; gap: 0.5rem;">
                    <select id="postbox-ingest-category" name="target_book_id" class="form-control" required style="flex: 1;">
                        <option value="">-- Choose Category --</option>
                    </select>
                    <button type="button" id="postbox-toggle-new-category-btn" class="btn btn-outline btn-sm" title="Create New Category">+ New</button>
                </div>
            </div>

            <!-- Optional New Category Input -->
            <div id="postbox-new-category-wrap" class="form-group" style="display: none; margin-bottom: 1rem; padding: 0.75rem; background: var(--bg-tertiary); border-radius: 6px;">
                <label class="form-label" for="postbox-new-category-title" style="font-size: 0.85rem;">New Category Title</label>
                <input type="text" id="postbox-new-category-title" name="new_category_title" class="form-control" placeholder="e.g. Shared Guides">
            </div>

            <!-- Document Title -->
            <div class="form-group" style="margin-bottom: 1rem;">
                <label class="form-label" for="postbox-ingest-title" style="font-weight: 600;">Document Title *</label>
                <input type="text" id="postbox-ingest-title" name="title" class="form-control" required>
            </div>

            <!-- Document Slug -->
            <div class="form-group" style="margin-bottom: 1rem;">
                <label class="form-label" for="postbox-ingest-slug" style="font-weight: 600;">Document Slug (URL Identifier) *</label>
                <input type="text" id="postbox-ingest-slug" name="slug" class="form-control" required pattern="[a-zA-Z0-9\-_]+">
                <small style="color: var(--text-muted); font-size: 0.8rem;">If a conflict exists in the destination category, a unique suffix will be appended automatically.</small>
            </div>

            <!-- Document Type & Theme in 2 Columns -->
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                <div class="form-group">
                    <label class="form-label" for="postbox-ingest-type">Document Type</label>
                    <select id="postbox-ingest-type" name="type" class="form-control">
                        <option value="markdown">Markdown (.md)</option>
                        <option value="html">HTML Page (.html)</option>
                        <option value="pdf">PDF Document (.pdf)</option>
                        <option value="form">Interactive Form (.form.json)</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label" for="postbox-ingest-theme">Document Theme (Optional)</label>
                    <input type="text" id="postbox-ingest-theme" name="theme" class="form-control" placeholder="Inherit Category Theme">
                </div>
            </div>

            <!-- Description -->
            <div class="form-group" style="margin-bottom: 1rem;">
                <label class="form-label" for="postbox-ingest-desc">Short Description</label>
                <textarea id="postbox-ingest-desc" name="description" class="form-control" style="min-height: 60px;"></textarea>
            </div>

            <!-- Protected / Read-Only Checkbox -->
            <div class="form-group" style="margin-bottom: 1.25rem;">
                <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                    <input type="checkbox" id="postbox-ingest-readonly" name="readOnly" value="1">
                    <span><strong>Protect Document</strong> (Mark as Read-Only / locked against non-admin edits)</span>
                </label>
            </div>

            <!-- Assets Notice -->
            <div id="postbox-ingest-assets-notice" style="display: none; padding: 0.6rem 0.8rem; background: rgba(59, 130, 246, 0.08); border-left: 3px solid #3b82f6; border-radius: 4px; font-size: 0.85rem; margin-bottom: 1.25rem;">
                <span id="postbox-ingest-assets-text">Attached media will be extracted into <code>uploads/images/</code> and internal links will be remapped.</span>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 0.75rem; border-top: 1px solid var(--border-color); padding-top: 1rem;">
                <button type="button" class="btn btn-outline" data-close="modal-postbox-ingest">Cancel</button>
                <button type="submit" id="postbox-commit-ingest-btn" class="btn btn-primary" style="display: flex; align-items: center; gap: 0.5rem;">
                    <span>📥</span> Accept & Ingest Document
                </button>
            </div>
        </form>
    </div>
</div>
