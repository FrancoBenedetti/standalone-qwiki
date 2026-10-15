<!-- Unified Add Document Modal (Dynamic with Extensions) -->
<div class="modal-overlay" id="chapter-modal">
    <div class="modal-card" style="max-width: 700px;">
        <div class="modal-header">
            <h3>Add New Document / Item</h3>
            <button class="modal-close" data-close="chapter-modal">&times;</button>
        </div>
        
        <div class="tab-header">
            <button class="tab-btn active" data-tab="tab-create-md">✏️ New Markdown</button>
            <button class="tab-btn" data-tab="tab-upload">📁 Upload File (MD/PDF)</button>
            <button class="tab-btn" data-tab="tab-gdoc">🌐 Google Doc</button>
            <button class="tab-btn" data-tab="tab-link">🔗 Web Link</button>
            <button class="tab-btn" data-tab="tab-remote">🌐 Remote Sharelink</button>
            <?php $extManager->renderAddDocumentTabs(); ?>
        </div>

        <!-- Tab 1: Create Markdown Online -->
        <form id="tab-create-md" class="tab-content active">
            <div class="form-group">
                <label class="form-label">Target Category / Folder</label>
                <select name="bookId" class="form-control" required>
                    <?php foreach ($categoryHierarchy as $cat): ?>
                        <option value="<?= htmlspecialchars($cat['id']) ?>" <?= ($currentCategoryId === $cat['id']) ? 'selected' : '' ?>>
                            <?= str_repeat('&nbsp;&nbsp;', $cat['depth']) ?><?= $cat['depth'] > 0 ? '↳ ' : '' ?><?= htmlspecialchars($cat['path']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Document Title (Auto-detected from # Heading if left blank)</label>
                <input type="text" name="title" class="form-control" placeholder="e.g. Architecture Overview">
            </div>
            <div class="form-group">
                <label class="form-label">Initial Markdown Content <span style="font-size: 0.85em; color: var(--text-muted); font-weight: normal;">(Or <a href="#" id="upload-md-link" style="color: var(--accent-color);">upload a .md file</a>)</span></label>
                <input type="file" id="md-file-upload-input" accept=".md" style="display: none;">
                <textarea name="content" id="md-content-textarea" class="form-control" style="min-height: 180px;" placeholder="# Document Title&#10;&#10;Write your documentation here..."></textarea>
            </div>
            <button type="submit" class="btn btn-primary" style="width: 100%;">Create Document</button>
        </form>

        <!-- Tab 2: Upload File -->
        <form id="tab-upload" class="tab-content" enctype="multipart/form-data">
            <div class="form-group">
                <label class="form-label">Target Category / Folder</label>
                <select name="bookId" class="form-control" required>
                    <?php foreach ($categoryHierarchy as $cat): ?>
                        <option value="<?= htmlspecialchars($cat['id']) ?>" <?= ($currentCategoryId === $cat['id']) ? 'selected' : '' ?>>
                            <?= str_repeat('&nbsp;&nbsp;', $cat['depth']) ?><?= $cat['depth'] > 0 ? '↳ ' : '' ?><?= htmlspecialchars($cat['path']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Document Title</label>
                <input type="text" name="title" class="form-control" placeholder="e.g. Specification Datasheet" required>
            </div>
            <div class="form-group">
                <label class="form-label">Select File (.md, or .pdf)</label>
                <input type="file" name="document" class="form-control" accept=".md,.pdf" required>
            </div>
            <button type="submit" class="btn btn-primary" style="width: 100%;">Upload & Add</button>
        </form>

        <!-- Tab 3: Google Doc -->
        <form id="tab-gdoc" class="tab-content">
            <div class="form-group">
                <label class="form-label">Target Category / Folder</label>
                <select name="bookId" class="form-control" required>
                    <?php foreach ($categoryHierarchy as $cat): ?>
                        <option value="<?= htmlspecialchars($cat['id']) ?>" <?= ($currentCategoryId === $cat['id']) ? 'selected' : '' ?>>
                            <?= str_repeat('&nbsp;&nbsp;', $cat['depth']) ?><?= $cat['depth'] > 0 ? '↳ ' : '' ?><?= htmlspecialchars($cat['path']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Document Title</label>
                <input type="text" name="title" class="form-control" placeholder="e.g. User Guide Google Doc" required>
            </div>
            <div class="form-group">
                <label class="form-label">Published Google Doc URL</label>
                <input type="url" name="url" class="form-control" placeholder="https://docs.google.com/document/d/e/.../pub?embedded=true" required>
            </div>
            <div class="form-group">
                <label class="form-label">Google Doc Edit URL (Optional)</label>
                <input type="url" name="editUrl" class="form-control" placeholder="https://docs.google.com/document/d/.../edit">
            </div>
            <button type="submit" class="btn btn-primary" style="width: 100%;">Link Google Doc</button>
        </form>

        <!-- Tab 4: Web Link -->
        <form id="tab-link" class="tab-content">
            <div class="form-group">
                <label class="form-label">Target Category / Placement</label>
                <select name="bookId" class="form-control">
                    <option value="">-- Top Level (Sidebar Root) --</option>
                    <?php foreach ($categoryHierarchy as $cat): ?>
                        <option value="<?= htmlspecialchars($cat['id']) ?>" <?= ($currentCategoryId === $cat['id']) ? 'selected' : '' ?>>
                            <?= str_repeat('&nbsp;&nbsp;', $cat['depth']) ?><?= $cat['depth'] > 0 ? '↳ ' : '' ?><?= htmlspecialchars($cat['path']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Link Title</label>
                <input type="text" name="title" class="form-control" placeholder="e.g. GitHub Repository, API Status" required>
            </div>
            <div class="form-group">
                <label class="form-label">Target URL</label>
                <input type="text" name="url" class="form-control" placeholder="https://example.com or /internal/path" required>
                <small style="color: var(--text-muted); font-size: 0.8rem; margin-top: 0.25rem; display: block;">External URLs open in a new tab automatically; same-domain URLs open in the same tab.</small>
            </div>
            <div class="form-group">
                <label class="form-label">Description (Optional)</label>
                <input type="text" name="description" class="form-control" placeholder="Short description for search">
            </div>
            <button type="submit" class="btn btn-primary" style="width: 100%;">Add Web Link</button>
        </form>

        <!-- Tab 5: Remote Sharelink Document -->
        <form id="tab-remote" class="tab-content">
            <div class="form-group">
                <label class="form-label">Target Category / Folder</label>
                <select name="bookId" class="form-control" required>
                    <?php foreach ($categoryHierarchy as $cat): ?>
                        <option value="<?= htmlspecialchars($cat['id']) ?>" <?= ($currentCategoryId === $cat['id']) ? 'selected' : '' ?>>
                            <?= str_repeat('&nbsp;&nbsp;', $cat['depth']) ?><?= $cat['depth'] > 0 ? '↳ ' : '' ?><?= htmlspecialchars($cat['path']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Document Title (Auto-detected from source if left blank)</label>
                <input type="text" name="title" class="form-control" placeholder="e.g. Shared Engineering Guidelines">
            </div>
            <div class="form-group">
                <label class="form-label">Remote Sharelink URL</label>
                <input type="url" name="url" class="form-control" placeholder="https://origin-wiki.example.com/?share=7c8e2a1d..." required>
                <small style="color: var(--text-muted); font-size: 0.8rem; margin-top: 0.25rem; display: block;">Enter the full ?share= URL from another Qwiki. The content will be rendered natively and locked as read-only.</small>
            </div>
            <div class="form-group">
                <label class="form-label">Description (Optional)</label>
                <input type="text" name="description" class="form-control" placeholder="Short description for search">
            </div>
            <button type="submit" class="btn btn-primary" style="width: 100%;">Link Remote Document</button>
        </form>

        <!-- Dynamic Extension Tabs -->
        <?php $extManager->renderAddDocumentForms($activeBook, $config, $categoryHierarchy, $currentCategoryId); ?>
    </div>
</div>
