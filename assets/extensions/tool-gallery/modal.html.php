<?php
/**
 * Image Gallery Modal Dialogs
 * 
 * Included by ExtensionManager::renderUtilityModals()
 */
?>
<!-- 1. Main Gallery Modal -->
<div class="modal-overlay" id="modal-gallery">
    <div class="modal-card gallery-modal-card">
        <div class="modal-header">
            <div style="display: flex; align-items: center; gap: 0.75rem;">
                <h3 style="margin: 0;">🖼️ Image Gallery</h3>
                <span class="doc-badge badge-md" id="gallery-count-badge">0 images</span>
            </div>
            <button class="modal-close" data-close="modal-gallery" aria-label="Close">&times;</button>
        </div>

        <!-- Gallery Toolbar -->
        <div class="gallery-toolbar">
            <div class="gallery-search-box">
                <span class="gallery-search-icon">🔍</span>
                <input type="text" id="gallery-search-input" class="form-control gallery-search-field" placeholder="Search images by name or extension...">
                <button type="button" id="gallery-search-clear" class="gallery-clear-btn" style="display: none;">&times;</button>
            </div>
            <div class="gallery-toolbar-actions">
                <input type="file" id="gallery-file-input" accept="image/*" style="display: none;">
                <button type="button" class="btn btn-primary btn-sm" id="btn-gallery-upload-trigger">
                    <span>⬆️</span> Upload Image
                </button>
                <button type="button" class="btn btn-outline btn-sm" id="btn-gallery-refresh" title="Refresh gallery">
                    🔄
                </button>
            </div>
        </div>

        <!-- Drag & Drop Overlay Zone (hidden by default) -->
        <div id="gallery-dropzone" class="gallery-dropzone">
            <div class="gallery-dropzone-content">
                <span style="font-size: 2rem;">📥</span>
                <p>Drop images here to upload to <code>uploads/images/</code></p>
            </div>
        </div>

        <!-- Gallery Body -->
        <div class="gallery-body" id="gallery-body">
            <!-- Loading Indicator -->
            <div id="gallery-loading" class="gallery-state-message">
                <div class="gallery-spinner"></div>
                <p>Loading uploaded images...</p>
            </div>

            <!-- Empty State -->
            <div id="gallery-empty" class="gallery-state-message" style="display: none;">
                <span style="font-size: 3rem; opacity: 0.6;">🖼️</span>
                <h4>No images found</h4>
                <p style="color: var(--text-muted); font-size: 0.9rem;">No images uploaded yet. Upload an image above or drag and drop files here.</p>
            </div>

            <!-- Grid of Images -->
            <div id="gallery-grid" class="gallery-grid" style="display: none;">
                <!-- Dynamically populated -->
            </div>
        </div>
    </div>
</div>

<!-- 2. Deletion Confirmation & Document Usage Warning Modal -->
<div class="modal-overlay" id="modal-gallery-delete" style="z-index: 1100;">
    <div class="modal-card gallery-delete-card">
        <div class="modal-header">
            <h3 id="gallery-delete-title">🗑️ Delete Image</h3>
            <button class="modal-close" id="btn-gallery-delete-close">&times;</button>
        </div>

        <div class="gallery-delete-body">
            <!-- Image Info Header -->
            <div class="gallery-delete-target-preview">
                <img id="gallery-delete-thumb" src="" alt="Thumbnail preview">
                <div class="gallery-delete-target-meta">
                    <strong id="gallery-delete-filename" class="gallery-truncate">filename.png</strong>
                    <span id="gallery-delete-meta-info" class="text-muted" style="font-size: 0.85rem;">0 KB</span>
                </div>
            </div>

            <!-- In-Use Warning Alert (Visible when image is referenced) -->
            <div id="gallery-delete-warning-box" class="gallery-warning-box" style="display: none;">
                <div class="gallery-warning-badge">
                    <span style="font-size: 1.25rem;">⚠️</span>
                    <div>
                        <strong id="gallery-warning-headline">Warning: This image is used in documents!</strong>
                        <p style="margin: 0.25rem 0 0 0; font-size: 0.85rem;">
                            Deleting this image will cause broken images or missing visual content in the following location(s):
                        </p>
                    </div>
                </div>

                <!-- List of referring documents -->
                <div class="gallery-used-doc-list" id="gallery-used-doc-list">
                    <!-- Populated dynamically with document titles, files, and snippets -->
                </div>
            </div>

            <!-- Standard Unused Confirmation (Visible when image is NOT referenced) -->
            <div id="gallery-delete-safe-box" class="gallery-safe-box" style="display: none;">
                <p>Are you sure you want to permanently delete this image from the server? This action cannot be undone.</p>
            </div>

            <!-- Checking State -->
            <div id="gallery-delete-checking" class="gallery-state-message" style="display: none; padding: 1rem 0;">
                <div class="gallery-spinner"></div>
                <p>Scanning documents for image references...</p>
            </div>
        </div>

        <!-- Modal Actions -->
        <div class="modal-footer" style="display: flex; gap: 0.75rem; justify-content: flex-end; margin-top: 1.5rem; border-top: 1px solid var(--border-color); padding-top: 1rem;">
            <button type="button" class="btn btn-outline btn-sm" id="btn-gallery-delete-cancel">Cancel</button>
            <button type="button" class="btn btn-danger btn-sm" id="btn-gallery-delete-confirm">Delete Image</button>
        </div>
    </div>
</div>

<!-- 3. Image Full Preview & Link Inspector Modal -->
<div class="modal-overlay" id="modal-gallery-preview" style="z-index: 1050;">
    <div class="modal-card gallery-preview-card">
        <div class="modal-header">
            <div style="display: flex; align-items: center; gap: 0.5rem; min-width: 0;">
                <span style="font-size: 1.2rem;">🔍</span>
                <h3 id="gallery-preview-title" class="gallery-truncate" style="margin: 0;">Image Preview</h3>
            </div>
            <button class="modal-close" id="btn-gallery-preview-close">&times;</button>
        </div>

        <div class="gallery-preview-body">
            <!-- Full Image Box -->
            <div class="gallery-preview-image-wrap">
                <img id="gallery-preview-img" src="" alt="Full preview">
            </div>

            <!-- Metadata & Copy Links -->
            <div class="gallery-preview-details">
                <div class="gallery-preview-meta-row">
                    <span class="gallery-meta-tag" id="gallery-preview-meta-dim">1920x1080</span>
                    <span class="gallery-meta-tag" id="gallery-preview-meta-size">145 KB</span>
                    <span class="gallery-meta-tag" id="gallery-preview-meta-date">2026-09-02</span>
                    <span class="gallery-meta-tag" id="gallery-preview-usage-tag" style="display: none;">In Use</span>
                </div>

                <div class="form-group" style="margin-top: 0.75rem;">
                    <label class="form-label" style="font-size: 0.85rem;">Alt Text / Caption</label>
                    <input type="text" id="gallery-preview-alt-input" class="form-control" placeholder="Image description">
                </div>

                <!-- Markdown Snippet -->
                <div class="form-group">
                    <label class="form-label" style="font-size: 0.85rem;">Markdown Link</label>
                    <div style="display: flex; gap: 0.5rem;">
                        <input type="text" id="gallery-preview-md-input" class="form-control gallery-code-input" readonly>
                        <button type="button" class="btn btn-outline btn-sm" id="btn-copy-preview-md">Copy MD</button>
                    </div>
                </div>

                <!-- HTML Snippet -->
                <div class="form-group">
                    <label class="form-label" style="font-size: 0.85rem;">HTML Link</label>
                    <div style="display: flex; gap: 0.5rem;">
                        <input type="text" id="gallery-preview-html-input" class="form-control gallery-code-input" readonly>
                        <button type="button" class="btn btn-outline btn-sm" id="btn-copy-preview-html">Copy HTML</button>
                    </div>
                </div>

                <!-- Action Footer -->
                <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 1rem; padding-top: 0.75rem; border-top: 1px solid var(--border-color);">
                    <button type="button" class="btn btn-outline btn-sm btn-danger-text" id="btn-preview-delete">
                        🗑️ Delete
                    </button>
                    <div style="display: flex; gap: 0.5rem;">
                        <button type="button" class="btn btn-primary btn-sm" id="btn-preview-insert-editor" style="display: none;">
                            Insert into Document
                        </button>
                        <button type="button" class="btn btn-outline btn-sm" id="btn-preview-open-newtab">
                            Open in New Tab ↗
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
