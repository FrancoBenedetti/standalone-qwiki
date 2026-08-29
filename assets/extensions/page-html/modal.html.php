<?php
/**
 * HTML Page Creation Form Fields
 * Variables available: $activeBook, $config, $extension
 */
$books = $config['books'] ?? [];
?>
<div class="form-group">
    <label class="form-label">Target Category / Folder</label>
    <select name="bookId" class="form-control" required>
        <?php foreach ($books as $b): ?>
            <option value="<?= htmlspecialchars($b['id']) ?>" <?= ($activeBook && $activeBook['id'] === $b['id']) ? 'selected' : '' ?>>
                <?= htmlspecialchars($b['title']) ?>
            </option>
        <?php endforeach; ?>
    </select>
</div>
<div class="form-group">
    <label class="form-label">Document Title</label>
    <input type="text" name="title" class="form-control" placeholder="e.g. Interactive Dashboard" required>
</div>
<div class="form-group">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
        <label class="form-label" style="margin-bottom: 0;">HTML Content (WYSIWYG & Code View)</label>
        <span style="font-size: 0.85em; color: var(--text-muted);">
            <a href="#" id="upload-html-link" style="color: var(--primary-color);">📁 Load HTML file</a>
        </span>
    </div>
    <input type="file" id="html-file-upload-input" accept=".html,.htm" style="display: none;">
    <textarea name="content" id="html-content-textarea" class="form-control" style="min-height: 250px; display: none;" placeholder="Write or paste HTML content..."></textarea>
</div>
<button type="submit" class="btn btn-primary" style="width: 100%;">Create HTML Document</button>
