(function () {
    const modal = document.getElementById('ext-revisions-modal');
    if (!modal) return;

    const listEl = document.getElementById('ext-revisions-list');
    const statusEl = document.getElementById('ext-revisions-status');
    const previewTitleEl = document.getElementById('ext-revisions-preview-title');
    const previewCodeEl = document.getElementById('ext-revisions-preview-code');
    const restoreBtn = document.getElementById('ext-revisions-restore-btn');

    let currentSlug = '';
    let selectedRevId = '';

    function getActiveSlug() {
        const hiddenSlug = document.getElementById('edit-chapter-slug-hidden');
        return (hiddenSlug && hiddenSlug.value) ? hiddenSlug.value : '';
    }

    async function loadRevisions() {
        currentSlug = getActiveSlug();
        selectedRevId = '';
        restoreBtn.style.display = 'none';
        previewCodeEl.textContent = 'Select a revision on the left.';
        if (!currentSlug) {
            listEl.innerHTML = '<div style="padding:0.75rem;color:var(--text-muted);">Open a document first.</div>';
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
            if (revs.length === 0) {
                listEl.innerHTML = '<div style="padding:0.75rem;color:var(--text-muted);">No prior revisions yet.</div>';
                return;
            }
            listEl.innerHTML = '';
            revs.forEach((r) => {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'revision-item-btn';
                btn.innerHTML = '<strong>' + (r.createdAt || r.revId) + '</strong><br><small>' +
                    (r.actor || 'system') + ' • ' + (r.source || '') + '</small>';
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
                previewCodeEl.textContent = data.revision.content || '';
                restoreBtn.style.display = 'inline-block';
            } else {
                previewCodeEl.textContent = data.error || 'Unable to load revision.';
                restoreBtn.style.display = 'none';
            }
        } catch (e) {
            previewCodeEl.textContent = 'Network error.';
            restoreBtn.style.display = 'none';
        }
    }

    restoreBtn.addEventListener('click', async () => {
        if (!currentSlug || !selectedRevId) return;
        if (!confirm('Restore revision ' + selectedRevId + '? Current content will be snapshotted first.')) return;
        const fd = new FormData();
        fd.append('action', 'ext_revisions_restore');
        fd.append('slug', currentSlug);
        fd.append('revId', selectedRevId);
        const res = await fetch('api/admin.php', { method: 'POST', body: fd });
        const data = await res.json();
        if (data.success) {
            window.location.reload();
        } else {
            alert(data.error || 'Restore failed.');
        }
    });

    function openRevisionsModal() {
        const dropdown = document.getElementById('user-dropdown-menu');
        if (dropdown) dropdown.classList.remove('active');
        modal.classList.add('active');
        loadRevisions();
    }

    const btnHeader = document.getElementById('btn-util-revisions');
    if (btnHeader) {
        btnHeader.addEventListener('click', openRevisionsModal);
    }

    document.addEventListener('click', (e) => {
        if (e.target && e.target.closest && e.target.closest('#btn-open-doc-revisions')) {
            openRevisionsModal();
        }
    });

    const observer = new MutationObserver(() => {
        if (modal.classList.contains('active') || modal.style.display === 'flex') {
            loadRevisions();
        }
    });
    observer.observe(modal, { attributes: true, attributeFilter: ['class', 'style'] });
})();
