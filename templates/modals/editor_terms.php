<!-- Editor Content Responsibility Terms Modal -->
<div class="modal-overlay open" id="editor-terms-modal" role="dialog" aria-modal="true" aria-labelledby="editor-terms-title">
    <div class="modal-card" style="max-width: 520px;">
        <div class="modal-header">
            <h3 id="editor-terms-title">📜 <?= htmlspecialchars(__('governance.terms_modal_title', 'Content Responsibility Agreement')) ?></h3>
        </div>
        <div style="font-size: 0.92rem; line-height: 1.55; color: var(--text-color); margin-bottom: 1.25rem;">
            <p style="margin-top: 0;">
                <?= htmlspecialchars(__('governance.terms_modal_intro', 'Before creating or editing content on this wiki, please confirm that you understand and accept responsibility for the material you publish:')) ?>
            </p>
            <ul style="padding-left: 1.25rem; margin: 0.75rem 0;">
                <li><?= htmlspecialchars(__('governance.terms_point_1', 'You are responsible for the accuracy, legality, and copyright compliance of your contributions.')) ?></li>
                <li><?= htmlspecialchars(__('governance.terms_point_2', 'Content you publish reflects your own work and not necessarily the views of the domain owner or software authors.')) ?></li>
                <li><?= htmlspecialchars(__('governance.terms_point_3', 'Your username and edit timestamps may be recorded in revision history and audit logs.')) ?></li>
            </ul>
        </div>
        <div style="display: flex; justify-content: flex-end; gap: 0.75rem;">
            <button type="button" class="btn btn-primary" id="btn-accept-editor-terms">
                <?= htmlspecialchars(__('governance.terms_accept_btn', 'I Understand & Accept')) ?>
            </button>
        </div>
    </div>
</div>
<script>
(function () {
    const btn = document.getElementById('btn-accept-editor-terms');
    const modal = document.getElementById('editor-terms-modal');
    if (!btn || !modal) return;
    btn.addEventListener('click', async () => {
        btn.disabled = true;
        try {
            const fd = new FormData();
            fd.append('action', 'accept_editor_terms');
            const res = await fetch('api/admin.php', { method: 'POST', body: fd });
            const data = await res.json();
            if (data.success) {
                modal.classList.remove('open');
                modal.remove();
            } else {
                alert(data.error || 'Failed to record acceptance.');
                btn.disabled = false;
            }
        } catch (e) {
            alert('Network error while recording acceptance.');
            btn.disabled = false;
        }
    });
})();
</script>
