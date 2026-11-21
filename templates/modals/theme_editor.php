<!-- Theme Editor Modal -->
<div class="modal-overlay" id="theme-editor-modal">
    <div class="modal-card" style="max-width: 800px;">
        <div class="modal-header">
            <h3>🎨 <?= htmlspecialchars(__('theme_editor.title', 'Theme Editor')) ?></h3>
            <button class="modal-close" data-close="theme-editor-modal">&times;</button>
        </div>
        <div class="form-group">
            <label class="form-label"><?= htmlspecialchars(__('theme_editor.select_label', 'Select Theme to Edit')) ?></label>
            <div style="display: flex; gap: 0.5rem;">
                <select id="editor-theme-selector" class="form-control theme-selector" style="flex: 1;">
                    <option value=""><?= htmlspecialchars(__('theme_editor.select_opt', '-- Select a Theme --')) ?></option>
                </select>
                <button class="btn btn-outline" id="btn-load-theme"><?= htmlspecialchars(__('common.load', 'Load')) ?></button>
            </div>
        </div>
        <div class="form-group" id="theme-editor-area" style="display: none;">
            <label class="form-label"><?= htmlspecialchars(__('theme_editor.css_content', 'CSS Content')) ?></label>
            <textarea id="theme-css-content" class="form-control" style="font-family: monospace; min-height: 300px;"></textarea>
            
            <label class="form-label" style="margin-top: 1rem;"><?= htmlspecialchars(__('theme_editor.save_as', 'Save As (Filename)')) ?></label>
            <div style="display: flex; gap: 0.5rem;">
                <input type="text" id="theme-filename" class="form-control" placeholder="theme-custom.css" style="flex: 1;">
                <button class="btn btn-primary" id="btn-save-theme"><?= htmlspecialchars(__('theme_editor.save_btn', 'Save Theme')) ?></button>
            </div>
            <p style="font-size: 0.8rem; color: var(--text-muted); margin-top: 0.5rem;"><?= __('theme_editor.filename_help_html', 'Must start with <code>theme-</code> and end with <code>.css</code>.') ?></p>
        </div>
    </div>
</div>
