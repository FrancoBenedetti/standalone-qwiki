<?php
$extManager = isset($extManager) ? $extManager : \Qwiki\Core\ExtensionManager::getInstance();
?>
<!-- Unified Add Document Modal (Dynamic with Extensions) -->
<div class="modal-overlay" id="chapter-modal">
    <div class="modal-card" style="max-width: 700px;">
        <div class="modal-header">
            <h3><?= htmlspecialchars(__('chapter.add_title', 'Add New Document / Item')) ?></h3>
            <button class="modal-close" data-close="chapter-modal">&times;</button>
        </div>

        <div class="modal-category-selector form-group" style="margin-bottom: 1rem;">
            <label class="form-label" for="chapter-modal-category"><?= htmlspecialchars(__('chapter.target_category', 'Target Category / Folder')) ?></label>
            <select id="chapter-modal-category" class="form-control" required>
                <?php foreach ($categoryHierarchy as $cat): ?>
                    <option value="<?= htmlspecialchars($cat['id']) ?>" <?= ($currentCategoryId === $cat['id']) ? 'selected' : '' ?>>
                        <?= str_repeat('&nbsp;&nbsp;', $cat['depth']) ?><?= $cat['depth'] > 0 ? '↳ ' : '' ?><?= htmlspecialchars($cat['path']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div class="modal-tabs">
            <button type="button" class="tab-btn active" data-tab="tab-create-md">✏️ <?= htmlspecialchars(__('chapter.tab_new_md', 'New Markdown')) ?></button>
            <button type="button" class="tab-btn" data-tab="tab-upload">📁 <?= htmlspecialchars(__('chapter.tab_upload', 'Upload File (MD/PDF)')) ?></button>
            <button type="button" class="tab-btn" data-tab="tab-gdoc">🌐 <?= htmlspecialchars(__('chapter.tab_gdoc', 'Google Doc')) ?></button>
            <button type="button" class="tab-btn" data-tab="tab-link">🔗 <?= htmlspecialchars(__('chapter.tab_link', 'Web Link')) ?></button>
            <button type="button" class="tab-btn" data-tab="tab-remote">🌐 <?= htmlspecialchars(__('chapter.tab_remote', 'Remote Sharelink')) ?></button>
            <?php $extManager->renderAddDocumentTabs(); ?>
        </div>

        <div class="settings-tab-scroll">
            <!-- Tab 1: Create Markdown Online -->
            <form id="tab-create-md" class="tab-content active">
                <input type="hidden" name="bookId" value="<?= htmlspecialchars($currentCategoryId) ?>">
                <div class="form-group">
                    <label class="form-label"><?= htmlspecialchars(__('chapter.title_auto_label', 'Document Title (Auto-detected from # Heading if left blank)')) ?></label>
                    <input type="text" name="title" class="form-control" placeholder="<?= htmlspecialchars(__('chapter.title_ph', 'e.g. Architecture Overview')) ?>">
                </div>
                <div class="form-group">
                    <label class="form-label"><?= __('chapter.initial_md_html', 'Initial Markdown Content <span style="font-size: 0.85em; color: var(--text-muted); font-weight: normal;">(Or <a href="#" id="upload-md-link" style="color: var(--accent-color);">upload a .md file</a>)</span>') ?></label>
                    <input type="file" id="md-file-upload-input" accept=".md" style="display: none;">
                    <textarea name="content" id="md-content-textarea" class="form-control" style="min-height: 180px;" placeholder="<?= htmlspecialchars(__('chapter.md_textarea_ph', "# Document Title\n\nWrite your documentation here...")) ?>"></textarea>
                </div>
<?php if ($extManager->isExtensionEnabled('comments')): ?>
                <div class="form-group">
                    <label class="form-label" for="create-chapter-allow-comments"><?= htmlspecialchars(__('collab.comments', 'Comments')) ?></label>
                    <select name="allowComments" id="create-chapter-allow-comments" class="form-control">
                        <option value="inherit" selected><?= htmlspecialchars(__('collab.inherit', 'Inherit from category (Default)')) ?></option>
                        <option value="enabled"><?= htmlspecialchars(__('common.enabled', 'Enabled')) ?></option>
                        <option value="disabled"><?= htmlspecialchars(__('common.disabled', 'Disabled')) ?></option>
                    </select>
                </div>
<?php endif; ?>
<?php if ($extManager->isExtensionEnabled('talk')): ?>
                <div class="form-group">
                    <label class="form-label" for="create-chapter-allow-talk"><?= htmlspecialchars(__('collab.talk_page', 'Discussion & Talk Page')) ?></label>
                    <select name="allowTalk" id="create-chapter-allow-talk" class="form-control">
                        <option value="inherit" selected><?= htmlspecialchars(__('collab.inherit', 'Inherit from category (Default)')) ?></option>
                        <option value="enabled"><?= htmlspecialchars(__('collab.talk_enabled', 'Enabled (Always show Talk tab)')) ?></option>
                        <option value="disabled"><?= htmlspecialchars(__('collab.talk_disabled', 'Disabled (Hide Talk tab)')) ?></option>
                    </select>
                </div>
<?php endif; ?>
                <button type="submit" class="btn btn-primary" style="width: 100%;"><?= htmlspecialchars(__('chapter.create_btn', 'Create Document')) ?></button>
            </form>

            <!-- Tab 2: Upload File -->
            <form id="tab-upload" class="tab-content" enctype="multipart/form-data">
                <input type="hidden" name="bookId" value="<?= htmlspecialchars($currentCategoryId) ?>">
                <div class="form-group">
                    <label class="form-label"><?= htmlspecialchars(__('chapter.doc_title', 'Document Title')) ?></label>
                    <input type="text" name="title" class="form-control" placeholder="<?= htmlspecialchars(__('chapter.upload_title_ph', 'e.g. Specification Datasheet')) ?>" required>
                </div>
                <div class="form-group">
                    <label class="form-label"><?= htmlspecialchars(__('chapter.select_file', 'Select File (.md, or .pdf)')) ?></label>
                    <input type="file" name="document" class="form-control" accept=".md,.pdf" required>
                </div>
                <button type="submit" class="btn btn-primary" style="width: 100%;"><?= htmlspecialchars(__('chapter.upload_add_btn', 'Upload & Add')) ?></button>
            </form>

            <!-- Tab 3: Google Doc -->
            <form id="tab-gdoc" class="tab-content">
                <input type="hidden" name="bookId" value="<?= htmlspecialchars($currentCategoryId) ?>">
                <div class="form-group">
                    <label class="form-label"><?= htmlspecialchars(__('chapter.doc_title', 'Document Title')) ?></label>
                    <input type="text" name="title" class="form-control" placeholder="<?= htmlspecialchars(__('chapter.gdoc_title_ph', 'e.g. User Guide Google Doc')) ?>" required>
                </div>
                <div class="form-group">
                    <label class="form-label"><?= htmlspecialchars(__('chapter.gdoc_url', 'Published Google Doc URL')) ?></label>
                    <input type="url" name="url" class="form-control" placeholder="https://docs.google.com/document/d/e/.../pub?embedded=true" required>
                </div>
                <div class="form-group">
                    <label class="form-label"><?= htmlspecialchars(__('chapter.gdoc_edit_url', 'Google Doc Edit URL (Optional)')) ?></label>
                    <input type="url" name="editUrl" class="form-control" placeholder="https://docs.google.com/document/d/.../edit">
                </div>
                <button type="submit" class="btn btn-primary" style="width: 100%;"><?= htmlspecialchars(__('chapter.link_gdoc_btn', 'Link Google Doc')) ?></button>
            </form>

            <!-- Tab 4: Web Link -->
            <form id="tab-link" class="tab-content">
                <input type="hidden" name="bookId" value="<?= htmlspecialchars($currentCategoryId) ?>">
                <div class="form-group">
                    <label class="checkbox-label" style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer; user-select: none;">
                        <input type="checkbox" id="link-root-override">
                        <span><?= htmlspecialchars(__('chapter.link_top_level', 'Place at Top Level (Sidebar Root) instead of selected category')) ?></span>
                    </label>
                </div>
                <div class="form-group">
                    <label class="form-label"><?= htmlspecialchars(__('link.title_label', 'Link Title')) ?></label>
                    <input type="text" name="title" class="form-control" placeholder="<?= htmlspecialchars(__('chapter.link_title_ph', 'e.g. GitHub Repository, API Status')) ?>" required>
                </div>
                <div class="form-group">
                    <label class="form-label"><?= htmlspecialchars(__('link.target_url', 'Target URL')) ?></label>
                    <input type="text" name="url" class="form-control" placeholder="<?= htmlspecialchars(__('chapter.link_url_ph', 'https://example.com or /internal/path')) ?>" required>
                    <small style="color: var(--text-muted); font-size: 0.8rem; margin-top: 0.25rem; display: block;"><?= htmlspecialchars(__('link.url_help', 'External URLs open in a new tab automatically; same-domain URLs open in the same tab.')) ?></small>
                </div>
                <div class="form-group">
                    <label class="form-label"><?= htmlspecialchars(__('common.desc_optional', 'Description (Optional)')) ?></label>
                    <input type="text" name="description" class="form-control" placeholder="<?= htmlspecialchars(__('chapter.short_desc_ph', 'Short description for search')) ?>">
                </div>
                <button type="submit" class="btn btn-primary" style="width: 100%;"><?= htmlspecialchars(__('chapter.add_link_btn', 'Add Web Link')) ?></button>
            </form>

            <!-- Tab 5: Remote Sharelink Document -->
            <form id="tab-remote" class="tab-content">
                <input type="hidden" name="bookId" value="<?= htmlspecialchars($currentCategoryId) ?>">
                <div class="form-group">
                    <label class="form-label"><?= htmlspecialchars(__('chapter.remote_title_label', 'Document Title (Auto-detected from source if left blank)')) ?></label>
                    <input type="text" name="title" class="form-control" placeholder="<?= htmlspecialchars(__('chapter.remote_title_ph', 'e.g. Shared Engineering Guidelines')) ?>">
                </div>
                <div class="form-group">
                    <label class="form-label"><?= htmlspecialchars(__('chapter.remote_url_label', 'Remote Sharelink URL')) ?></label>
                    <input type="url" name="url" class="form-control" placeholder="https://origin-wiki.example.com/?share=7c8e2a1d..." required>
                    <small style="color: var(--text-muted); font-size: 0.8rem; margin-top: 0.25rem; display: block;"><?= htmlspecialchars(__('chapter.remote_url_help', 'Enter the full ?share= URL from another Qwiki. The content will be rendered natively and locked as read-only.')) ?></small>
                </div>
                <div class="form-group">
                    <label class="form-label"><?= htmlspecialchars(__('common.desc_optional', 'Description (Optional)')) ?></label>
                    <input type="text" name="description" class="form-control" placeholder="<?= htmlspecialchars(__('chapter.short_desc_ph', 'Short description for search')) ?>">
                </div>
                <button type="submit" class="btn btn-primary" style="width: 100%;"><?= htmlspecialchars(__('chapter.link_remote_btn', 'Link Remote Document')) ?></button>
            </form>

            <!-- Dynamic Extension Tabs -->
            <?php $extManager->renderAddDocumentForms($activeBook, $config, $categoryHierarchy, $currentCategoryId); ?>
        </div>
    </div>
</div>
