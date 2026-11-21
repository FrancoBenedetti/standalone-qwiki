<?php
/**
 * HTML Page Creation Form Fields
 * Variables available: $activeBook, $config, $extension
 */
$books = $config['books'] ?? [];
?>
<div class="form-group">
    <label class="form-label"><?= htmlspecialchars(__('chapter.target_category', 'Target Category / Folder')) ?></label>
    <select name="bookId" class="form-control" required>
        <?php if (!empty($categoryHierarchy)): ?>
            <?php foreach ($categoryHierarchy as $cat): ?>
                <option value="<?= htmlspecialchars($cat['id']) ?>" <?= (($currentCategoryId ?? '') === $cat['id']) ? 'selected' : '' ?>>
                    <?= str_repeat('&nbsp;&nbsp;', $cat['depth']) ?><?= $cat['depth'] > 0 ? '↳ ' : '' ?><?= htmlspecialchars($cat['path']) ?>
                </option>
            <?php endforeach; ?>
        <?php else: ?>
            <?php foreach ($books as $b): ?>
                <?php if (($b['type'] ?? 'folder') === 'folder' && isset($b['id'])): ?>
                    <option value="<?= htmlspecialchars($b['id']) ?>" <?= ($activeBook && ($activeBook['id'] ?? '') === $b['id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($b['title']) ?>
                    </option>
                <?php endif; ?>
            <?php endforeach; ?>
        <?php endif; ?>
    </select>
</div>
<div class="form-group">
    <label class="form-label"><?= htmlspecialchars(__('chapter.doc_title', 'Document Title')) ?></label>
    <input type="text" name="title" class="form-control" placeholder="<?= htmlspecialchars(__('ext_html.title_ph', 'e.g. Interactive Dashboard')) ?>" required>
</div>
<div class="form-group">
    <label class="form-label"><?= htmlspecialchars(__('ext_html.short_desc_label', 'Short Description (for social sharing & search)')) ?></label>
    <textarea name="description" class="form-control" style="min-height: 60px;" placeholder="<?= htmlspecialchars(__('ext_html.short_desc_ph', 'Optional summary for Open Graph and search...')) ?>"></textarea>
</div>
<div class="form-group">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
        <label class="form-label" style="margin-bottom: 0;"><?= htmlspecialchars(__('ext_html.content_label', 'HTML Content')) ?></label>
        <div style="display: flex; gap: 1rem; font-size: 0.85em; color: var(--text-muted);">
            <label style="cursor: pointer;"><input type="checkbox" id="create-use-visual-editor" checked> <?= htmlspecialchars(__('ext_html.visual_editor', 'Visual Editor')) ?></label>
            <a href="#" id="upload-html-link" style="color: var(--primary-color);">📁 <?= htmlspecialchars(__('ext_html.load_file', 'Load HTML file')) ?></a>
        </div>
    </div>
    <input type="file" id="html-file-upload-input" accept=".html,.htm" style="display: none;">
    <textarea name="content" id="html-content-textarea" class="form-control" style="min-height: 400px; font-family: monospace; resize: vertical;" placeholder="<?= htmlspecialchars(__('ext_html.content_ph', 'Write or paste HTML content...')) ?>"></textarea>
</div>
<button type="submit" class="btn btn-primary" style="width: 100%;"><?= htmlspecialchars(__('ext_html.create_btn', 'Create HTML Document')) ?></button>
