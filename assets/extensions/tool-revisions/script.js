function initRevisionsExtension() {
    const modal = document.getElementById('ext-revisions-modal');
    if (!modal) return;

    const listEl = document.getElementById('ext-revisions-list');
    const statusEl = document.getElementById('ext-revisions-status');
    const previewTitleEl = document.getElementById('ext-revisions-preview-title');
    const previewCodeEl = document.getElementById('ext-revisions-preview-code');
    const restoreBtn = document.getElementById('ext-revisions-restore-btn');
    const downloadBtn = document.getElementById('ext-revisions-download-btn');

    let currentSlug = '';
    let selectedRevId = '';
    let wasOpen = modal.classList.contains('open') || modal.classList.contains('active');

    function getActiveSlug() {
        const hiddenSlug = document.getElementById('edit-chapter-slug-hidden');
        if (hiddenSlug && hiddenSlug.value) return hiddenSlug.value;
        const btnMeta = document.getElementById('btn-edit-chapter-meta');
        if (btnMeta && btnMeta.getAttribute('data-slug')) return btnMeta.getAttribute('data-slug');
        const btnReplace = document.getElementById('btn-replace-document');
        if (btnReplace && btnReplace.getAttribute('data-slug')) return btnReplace.getAttribute('data-slug');
        const btnDel = document.getElementById('btn-delete-chapter');
        if (btnDel && btnDel.getAttribute('data-slug')) return btnDel.getAttribute('data-slug');
        return '';
    }

    async function loadRevisions() {
        currentSlug = getActiveSlug();
        selectedRevId = '';
        restoreBtn.style.display = 'none';
        if (downloadBtn) downloadBtn.style.display = 'none';
        previewTitleEl.textContent = 'Preview';
        previewCodeEl.textContent = 'Select a revision on the left.';
        if (!currentSlug) {
            statusEl.textContent = 'No active document selected.';
            listEl.replaceChildren();
            const msg = document.createElement('div');
            msg.style.padding = '0.75rem';
            msg.style.color = 'var(--text-muted)';
            msg.textContent = 'Open a document first.';
            listEl.appendChild(msg);
            return;
        }
        statusEl.textContent = 'Loading revisions for "' + currentSlug + '"...';
        try {
            const res = await fetch('api/admin.php?action=ext_revisions_list&slug=' + encodeURIComponent(currentSlug));
            const data = await res.json();
            if (!data.success) {
                statusEl.textContent = data.error || 'Failed to load revisions.';
                return;
            }
            const revs = data.revisions || [];
            statusEl.textContent = revs.length + ' revision(s) stored.';
            listEl.replaceChildren();
            if (revs.length === 0) {
                const empty = document.createElement('div');
                empty.style.padding = '0.75rem';
                empty.style.color = 'var(--text-muted)';
                empty.textContent = 'No prior revisions yet.';
                listEl.appendChild(empty);
                return;
            }
            revs.forEach((r) => {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'revision-item-btn';
                const strong = document.createElement('strong');
                strong.textContent = r.createdAt || r.revId || '';
                const br = document.createElement('br');
                const small = document.createElement('small');
                small.textContent = (r.actor || 'system') + ' • ' + (r.source || '');
                btn.appendChild(strong);
                btn.appendChild(br);
                btn.appendChild(small);
                btn.addEventListener('click', () => previewRevision(r.revId));
                listEl.appendChild(btn);
            });
        } catch (err) {
            statusEl.textContent = 'Error loading revisions.';
        }
    }

    async function previewRevision(revId) {
        selectedRevId = revId;
        previewTitleEl.textContent = 'Revision ' + revId;
        previewCodeEl.textContent = 'Loading...';
        try {
            const res = await fetch('api/admin.php?action=ext_revisions_get&slug=' + encodeURIComponent(currentSlug) + '&revId=' + encodeURIComponent(revId));
            const data = await res.json();
            if (data.success && data.revision) {
                const rev = data.revision;
                if (rev.isBinary) {
                    const sizeStr = typeof rev.sizeBytes === 'number' ? rev.sizeBytes + ' bytes' : 'unknown size';
                    const hashStr = rev.sha256 ? rev.sha256.slice(0, 16) : '';
                    previewCodeEl.textContent = `Binary file revision (${rev.type || 'binary'}, ${sizeStr}${hashStr ? ', SHA-256 ' + hashStr : ''}).\nText preview is unavailable. Click "Restore This Version" to revert to this snapshot.`;
                } else {
                    previewCodeEl.textContent = rev.content || '';
                }
                if (downloadBtn) {
                    downloadBtn.href = 'api/admin.php?action=ext_revisions_download&slug=' + encodeURIComponent(currentSlug) + '&revId=' + encodeURIComponent(revId);
                    downloadBtn.style.display = 'inline-block';
                }
                restoreBtn.style.display = 'inline-block';
            } else {
                previewCodeEl.textContent = data.error || 'Unable to load revision.';
                restoreBtn.style.display = 'none';
                if (downloadBtn) downloadBtn.style.display = 'none';
            }
        } catch (e) {
            previewCodeEl.textContent = 'Network error.';
            restoreBtn.style.display = 'none';
            if (downloadBtn) downloadBtn.style.display = 'none';
        }
    }

    restoreBtn.addEventListener('click', async () => {
        if (!currentSlug || !selectedRevId) return;
        if (!confirm('Restore revision ' + selectedRevId + '? Current content will be snapshotted first.')) return;
        const fd = new FormData();
        fd.append('action', 'ext_revisions_restore');
        fd.append('slug', currentSlug);
        fd.append('revId', selectedRevId);
        const tabId = (window.SoftLock && typeof window.SoftLock.getTabId === 'function')
            ? window.SoftLock.getTabId()
            : (sessionStorage.getItem('qwiki_tab_id') || '');
        if (tabId) {
            fd.append('tab_id', tabId);
        }
        const res = await fetch('api/admin.php', { method: 'POST', body: fd });
        const data = await res.json();
        if (data.success) {
            window.location.reload();
        } else {
            alert(data.error || 'Restore failed.');
        }
    });

    function openRevisionsModal() {
        if (window.QwikiModals && typeof window.QwikiModals.open === 'function') {
            window.QwikiModals.open('ext-revisions-modal');
        } else {
            modal.classList.add('open');
        }
        // MutationObserver handles loading on the closed -> open transition
    }

    document.addEventListener('click', (e) => {
        if (e.target && e.target.closest && e.target.closest('#btn-open-doc-revisions, #btn-chapter-revisions')) {
            openRevisionsModal();
        }
    });

    const observer = new MutationObserver(() => {
        const isOpen = modal.classList.contains('open') || modal.classList.contains('active');
        if (isOpen && !wasOpen) {
            wasOpen = true;
            loadRevisions();
        } else if (!isOpen) {
            wasOpen = false;
        }
    });
    observer.observe(modal, { attributes: true, attributeFilter: ['class', 'style'] });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initRevisionsExtension);
} else {
    initRevisionsExtension();
}
