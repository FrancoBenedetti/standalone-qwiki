<!-- Share Document Modal -->
<div class="modal-overlay" id="share-modal">
    <div class="modal-card" style="max-width: 520px;">
        <div class="modal-header">
            <h3>🔗 <?= htmlspecialchars(__('share.share_document', 'Share Document')) ?></h3>
            <button class="modal-close" data-close="share-modal">&times;</button>
        </div>
        <div class="modal-body" style="padding: 1rem 0;">
            <p style="color: var(--text-muted); font-size: 0.9rem; margin-bottom: 1.25rem;">
                <?= htmlspecialchars(__('share.modal_desc', 'Anyone with this link can view this document directly in full-screen reader mode. The link is protected by an unguessable unique key.')) ?>
            </p>
            <div id="share-status-container" style="margin-bottom: 1rem;"></div>
            <div class="form-group">
                <label class="form-label" for="share-link-input"><?= htmlspecialchars(__('share.fullscreen_link', 'Full-Screen Share Link')) ?></label>
                <div style="display: flex; gap: 0.5rem;">
                    <input type="text" id="share-link-input" class="form-control" readonly style="font-family: monospace; font-size: 0.85rem;" onclick="this.select()">
                    <button type="button" class="btn btn-primary" id="btn-copy-share-link" style="white-space: nowrap;">📋 <?= htmlspecialchars(__('common.copy', 'Copy')) ?></button>
                </div>
                <div class="share-social-buttons" id="modal-social-links" style="display: flex; gap: 0.5rem; margin-top: 0.75rem; flex-wrap: wrap;">
                    <a href="#" id="modal-share-x" target="_blank" rel="noopener noreferrer" class="btn btn-outline btn-sm" style="display: inline-flex; align-items: center; gap: 0.35rem; font-size: 0.8rem;" title="<?= htmlspecialchars(__('share.on_x', 'Share on X')) ?>"><?= htmlspecialchars(__('share.x_btn', '𝕏 Share')) ?></a>
                    <a href="#" id="modal-share-linkedin" target="_blank" rel="noopener noreferrer" class="btn btn-outline btn-sm" style="display: inline-flex; align-items: center; gap: 0.35rem; font-size: 0.8rem;" title="<?= htmlspecialchars(__('share.on_linkedin', 'Share on LinkedIn')) ?>">💼 LinkedIn</a>
                    <a href="#" id="modal-share-facebook" target="_blank" rel="noopener noreferrer" class="btn btn-outline btn-sm" style="display: inline-flex; align-items: center; gap: 0.35rem; font-size: 0.8rem;" title="<?= htmlspecialchars(__('share.on_facebook', 'Share on Facebook')) ?>">📘 Facebook</a>
                    <a href="#" id="modal-share-whatsapp" target="_blank" rel="noopener noreferrer" class="btn btn-outline btn-sm" style="display: inline-flex; align-items: center; gap: 0.35rem; font-size: 0.8rem;" title="<?= htmlspecialchars(__('share.on_whatsapp', 'Share on WhatsApp')) ?>">💬 WhatsApp</a>
                    <a href="#" id="modal-share-telegram" target="_blank" rel="noopener noreferrer" class="btn btn-outline btn-sm" style="display: inline-flex; align-items: center; gap: 0.35rem; font-size: 0.8rem;" title="<?= htmlspecialchars(__('share.on_telegram', 'Share on Telegram')) ?>">✈️ Telegram</a>
                    <button type="button" id="modal-share-slack" class="btn btn-outline btn-sm" style="display: inline-flex; align-items: center; gap: 0.35rem; font-size: 0.8rem;" title="<?= htmlspecialchars(__('share.slack_title', 'Copy link & open Slack in new tab')) ?>">#️⃣ Slack</button>
                </div>
            </div>
            <div id="share-qr-container" class="share-qr-container" style="display: none;">
                <div id="share-qr-preview" class="share-qr-preview" aria-label="QR Code Decal Preview"></div>
                <div class="share-qr-details">
                    <div class="share-qr-title"><?= htmlspecialchars(__('share.qr_decal_title', 'Equipment QR Decal')) ?></div>
                    <p class="share-qr-hint"><?= htmlspecialchars(__('share.qr_decal_hint', 'High-contrast Level-H QR code for chassis, control panels, and offline printouts.')) ?></p>
                    <div class="share-qr-actions">
                        <button type="button" class="btn btn-outline btn-sm" id="btn-download-share-qr">⬇️ <?= htmlspecialchars(__('share.download_svg', 'Download SVG')) ?></button>
                        <button type="button" class="btn btn-outline btn-sm" id="btn-print-single-decal">🖨️ <?= htmlspecialchars(__('share.print_decal', 'Print Decal')) ?></button>
                    </div>
                </div>
            </div>
            <?php if ($isAdmin): ?>
            <div style="margin-top: 1.5rem; padding-top: 1rem; border-top: 1px solid var(--border-color);">
                <h4 style="font-size: 0.9rem; margin-bottom: 0.75rem; color: var(--text-color);"><?= htmlspecialchars(__('share.admin_controls', 'Admin Access Controls')) ?></h4>
                <div style="display: flex; align-items: center; justify-content: space-between; gap: 1rem; flex-wrap: wrap;">
                    <label class="checkbox-label" style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer; user-select: none;">
                        <input type="checkbox" id="share-admin-toggle-public" value="1">
                        <span><?= htmlspecialchars(__('share.allow_public', 'Allow Public Sharing')) ?></span>
                    </label>
                    <div style="display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap;">
                        <a href="?qr_sheet=1" target="_blank" rel="noopener noreferrer" class="btn btn-outline btn-sm" title="<?= htmlspecialchars(__('share.print_all_decals_title', 'Open printable A4 QR decal sheet for all shareable chapters')) ?>">🏷️ <?= htmlspecialchars(__('share.print_all_decals', 'A4 Decal Sheet')) ?></a>
                        <button type="button" class="btn btn-outline btn-sm btn-danger-text" id="btn-modal-regenerate-share-key" title="<?= htmlspecialchars(__('share.reset_key_title', 'Invalidates previously distributed links')) ?>">🔄 <?= htmlspecialchars(__('share.reset_key', 'Reset Key')) ?></button>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>
