<!-- Share Document Modal -->
<div class="modal-overlay" id="share-modal">
    <div class="modal-card" style="max-width: 520px;">
        <div class="modal-header">
            <h3>🔗 Share Document</h3>
            <button class="modal-close" data-close="share-modal">&times;</button>
        </div>
        <div class="modal-body" style="padding: 1rem 0;">
            <p style="color: var(--text-muted); font-size: 0.9rem; margin-bottom: 1.25rem;">
                Anyone with this link can view this document directly in full-screen reader mode. The link is protected by an unguessable unique key.
            </p>
            <div id="share-status-container" style="margin-bottom: 1rem;"></div>
            <div class="form-group">
                <label class="form-label" for="share-link-input">Full-Screen Share Link</label>
                <div style="display: flex; gap: 0.5rem;">
                    <input type="text" id="share-link-input" class="form-control" readonly style="font-family: monospace; font-size: 0.85rem;" onclick="this.select()">
                    <button type="button" class="btn btn-primary" id="btn-copy-share-link" style="white-space: nowrap;">📋 Copy</button>
                </div>
                <div class="share-social-buttons" id="modal-social-links" style="display: flex; gap: 0.5rem; margin-top: 0.75rem; flex-wrap: wrap;">
                    <a href="#" id="modal-share-x" target="_blank" rel="noopener noreferrer" class="btn btn-outline btn-sm" style="display: inline-flex; align-items: center; gap: 0.35rem; font-size: 0.8rem;" title="Share on X">𝕏 Share</a>
                    <a href="#" id="modal-share-linkedin" target="_blank" rel="noopener noreferrer" class="btn btn-outline btn-sm" style="display: inline-flex; align-items: center; gap: 0.35rem; font-size: 0.8rem;" title="Share on LinkedIn">💼 LinkedIn</a>
                    <a href="#" id="modal-share-facebook" target="_blank" rel="noopener noreferrer" class="btn btn-outline btn-sm" style="display: inline-flex; align-items: center; gap: 0.35rem; font-size: 0.8rem;" title="Share on Facebook">📘 Facebook</a>
                    <a href="#" id="modal-share-whatsapp" target="_blank" rel="noopener noreferrer" class="btn btn-outline btn-sm" style="display: inline-flex; align-items: center; gap: 0.35rem; font-size: 0.8rem;" title="Share on WhatsApp">💬 WhatsApp</a>
                    <a href="#" id="modal-share-telegram" target="_blank" rel="noopener noreferrer" class="btn btn-outline btn-sm" style="display: inline-flex; align-items: center; gap: 0.35rem; font-size: 0.8rem;" title="Share on Telegram">✈️ Telegram</a>
                    <button type="button" id="modal-share-slack" class="btn btn-outline btn-sm" style="display: inline-flex; align-items: center; gap: 0.35rem; font-size: 0.8rem;" title="Copy link & open Slack in new tab">#️⃣ Slack</button>
                </div>
            </div>
            <?php if ($isAdmin): ?>
            <div style="margin-top: 1.5rem; padding-top: 1rem; border-top: 1px solid var(--border-color);">
                <h4 style="font-size: 0.9rem; margin-bottom: 0.75rem; color: var(--text-color);">Admin Access Controls</h4>
                <div style="display: flex; align-items: center; justify-content: space-between; gap: 1rem;">
                    <label class="checkbox-label" style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer; user-select: none;">
                        <input type="checkbox" id="share-admin-toggle-public" value="1">
                        <span>Allow Public Sharing</span>
                    </label>
                    <button type="button" class="btn btn-outline btn-sm btn-danger-text" id="btn-modal-regenerate-share-key" title="Invalidates previously distributed links">🔄 Reset Key</button>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>
