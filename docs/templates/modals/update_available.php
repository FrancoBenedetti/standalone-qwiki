<!-- Update Available Modal -->
<div class="modal-overlay" id="update-modal">
    <div class="modal-card" style="max-width: 700px;">
        <div class="modal-header">
            <h3>🎉 New Update Available!</h3>
            <button class="modal-close" data-close="update-modal">&times;</button>
        </div>
        <div style="margin-bottom: 1.5rem;">
            <p>A new version of Standalone Qwiki is available: <strong id="update-version-text"></strong> (Current: <?= QWIKI_VERSION ?>)</p>
            <div id="update-release-notes" class="article-content" style="background: var(--bg-color); padding: 1.25rem; border-radius: 6px; border: 1px solid var(--border-color); max-height: 350px; overflow-y: auto; margin-top: 1rem; font-size: 0.9rem; line-height: 1.6;"></div>
        </div>
        <form id="update-form">
            <input type="hidden" name="zip_url" id="update-zip-url">
            <button type="submit" class="btn btn-primary" id="btn-install-update" style="width: 100%;">Download & Install Update</button>
            <p id="update-loading-text" style="display: none; text-align: center; color: var(--accent-color); margin-top: 0.5rem;">Installing update... Please wait and do not close this page.</p>
        </form>
    </div>
</div>
