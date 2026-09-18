/**
 * Standalone Qwiki - Document Postbox Extension Script
 */
(function() {
    'use strict';

    let inboxData = null;
    let peersData = null;
    let activeCategories = [];

    // Helper to get admin API URL
    function getAdminApiUrl() {
        return 'api/admin.php';
    }

    // Show temporary notification in postbox modal
    function showPostboxAlert(msg, type = 'success') {
        const banner = document.getElementById('postbox-alert-banner');
        if (!banner) return;
        banner.className = 'postbox-alert ' + (type === 'success' ? 'postbox-alert-success' : 'postbox-alert-error');
        banner.innerHTML = (type === 'success' ? '✅ ' : '⚠️ ') + msg;
        banner.style.display = 'flex';
        setTimeout(() => {
            banner.style.display = 'none';
        }, 6000);
    }

    // Switch tabs inside Postbox modal
    function switchPostboxTab(tabName) {
        const tabs = ['inbox', 'outbox', 'settings'];
        tabs.forEach(t => {
            const btn = document.getElementById('postbox-tab-btn-' + t);
            const view = document.getElementById('postbox-view-' + t);
            if (btn && view) {
                if (t === tabName) {
                    btn.classList.add('active');
                    view.style.display = 'block';
                } else {
                    btn.classList.remove('active');
                    view.style.display = 'none';
                }
            }
        });

        if (tabName === 'inbox') {
            loadInbox();
        } else if (tabName === 'outbox') {
            loadPeers();
            populateOutboxDocuments();
        } else if (tabName === 'settings') {
            loadPeers();
        }
    }

    // Check unread postbox count and update badges
    async function checkPostboxCount() {
        try {
            const res = await fetch(getAdminApiUrl() + '?action=ext_postbox_count');
            if (!res.ok) return;
            const data = await res.json();
            if (data && data.success) {
                const count = parseInt(data.doc_count || 0, 10);
                updateBadgeDisplays(count);
            }
        } catch (e) {
            // Ignore if unauthenticated or network error
        }
    }

    function updateBadgeDisplays(count) {
        // Modal Header Badge
        const modalBadge = document.getElementById('postbox-unread-badge');
        if (modalBadge) {
            if (count > 0) {
                modalBadge.textContent = count + ' Inbound';
                modalBadge.style.display = 'inline-block';
            } else {
                modalBadge.style.display = 'none';
            }
        }

        // Tab Badge
        const tabBadge = document.getElementById('postbox-tab-inbox-counter');
        if (tabBadge) {
            if (count > 0) {
                tabBadge.textContent = count;
                tabBadge.style.display = 'inline-block';
            } else {
                tabBadge.style.display = 'none';
            }
        }

        // Main Top Nav Header Button Badge
        const utilBtn = document.getElementById('btn-util-postbox');
        if (utilBtn) {
            let headerBadge = utilBtn.querySelector('.postbox-header-badge');
            if (count > 0) {
                if (!headerBadge) {
                    headerBadge = document.createElement('span');
                    headerBadge.className = 'postbox-header-badge';
                    utilBtn.appendChild(headerBadge);
                }
                headerBadge.textContent = count;
                headerBadge.style.display = 'inline-block';
            } else if (headerBadge) {
                headerBadge.style.display = 'none';
            }
        }
    }

    // Load Inbox envelopes and render
    async function loadInbox() {
        const loading = document.getElementById('postbox-inbox-loading');
        const empty = document.getElementById('postbox-inbox-empty');
        const container = document.getElementById('postbox-inbox-container');

        if (loading) loading.style.display = 'block';
        if (empty) empty.style.display = 'none';
        if (container) container.style.display = 'none';

        try {
            const res = await fetch(getAdminApiUrl() + '?action=ext_postbox_inbox');
            const data = await res.json();
            if (loading) loading.style.display = 'none';

            if (!data.success) {
                showPostboxAlert(data.error || 'Failed to load postbox inbox', 'error');
                return;
            }

            inboxData = data;
            activeCategories = data.categories || [];

            const envelopes = data.envelopes || [];
            if (envelopes.length === 0) {
                if (empty) empty.style.display = 'block';
                updateBadgeDisplays(0);
                return;
            }

            renderInboxEnvelopes(envelopes, container);
            if (container) container.style.display = 'block';

            let totalDocs = 0;
            envelopes.forEach(e => totalDocs += (e.documents ? e.documents.length : 0));
            updateBadgeDisplays(totalDocs);

        } catch (e) {
            if (loading) loading.style.display = 'none';
            showPostboxAlert('Error connecting to postbox API: ' + e.message, 'error');
        }
    }

    // Render Inbound Envelopes HTML
    function renderInboxEnvelopes(envelopes, container) {
        if (!container) return;
        container.innerHTML = '';

        envelopes.forEach(env => {
            const card = document.createElement('div');
            card.className = 'postbox-batch-card';

            const senderTitle = env.sender ? (env.sender.title || 'Peer Wiki') : 'Peer Wiki';
            const dateStr = env.created_at ? new Date(env.created_at).toLocaleString() : '';

            let docItemsHtml = '';
            (env.documents || []).forEach((doc, idx) => {
                const typeLabel = (doc.type || 'markdown').toUpperCase();
                const assetStr = doc.asset_count > 0 ? `<span>🖼️ ${doc.asset_count} media</span>` : '';
                const sizeKb = Math.round((doc.content_size || 0) / 1024) + ' KB';

                docItemsHtml += `
                    <div class="postbox-doc-item">
                        <div class="postbox-doc-info">
                            <div class="postbox-doc-title">
                                <span>📄</span> ${escapeHtml(doc.title)}
                                <span class="doc-badge badge-${(doc.type || 'md').toLowerCase()}" style="font-size: 0.7rem; padding: 0.1rem 0.4rem;">${typeLabel}</span>
                                ${doc.readOnly ? '<span title="Protected" style="font-size: 0.8rem;">🔒</span>' : ''}
                            </div>
                            <div class="postbox-doc-sub">
                                <span>Slug: <code>${escapeHtml(doc.slug || '')}</code></span>
                                <span>Size: ${sizeKb}</span>
                                ${assetStr}
                            </div>
                        </div>
                        <div class="postbox-actions">
                            <button type="button" class="btn btn-primary btn-sm btn-postbox-ingest" 
                                data-batch="${escapeHtml(env.batch_id)}" 
                                data-index="${idx}"
                                data-title="${escapeHtml(doc.title)}"
                                data-slug="${escapeHtml(doc.slug)}"
                                data-type="${escapeHtml(doc.type)}"
                                data-desc="${escapeHtml(doc.description || '')}"
                                data-theme="${escapeHtml(doc.theme || '')}"
                                data-readonly="${doc.readOnly ? '1' : '0'}"
                                data-assets="${doc.asset_count}"
                                data-category-hint="${escapeHtml(doc.category_hint || env.category_hint || '')}">
                                📥 Review & Ingest
                            </button>
                            <button type="button" class="btn btn-outline btn-sm btn-postbox-reject" 
                                data-batch="${escapeHtml(env.batch_id)}" 
                                data-index="${idx}"
                                style="color: #ef4444;" title="Discard this document">
                                🗑️ Reject
                            </button>
                        </div>
                    </div>
                `;
            });

            const isDesktop = env.sender && env.sender.type === 'desktop';
            const desktopBadge = isDesktop ? ' <span class="doc-badge" style="font-size:0.7rem;background:#8b5cf6;color:#fff;padding:0.1rem 0.4rem;border-radius:4px;">🖥️ Desktop</span>' : '';

            card.innerHTML = `
                <div class="postbox-batch-header">
                    <div class="postbox-batch-sender">
                        <span>📦</span> Inbound from: <strong>${escapeHtml(senderTitle)}</strong>${desktopBadge}
                    </div>
                    <div class="postbox-batch-meta">
                        <span>Batch: <code>${escapeHtml(env.batch_id)}</code></span> &bull; 
                        <span>${escapeHtml(dateStr)}</span>
                    </div>
                </div>
                <div class="postbox-doc-list">
                    ${docItemsHtml}
                </div>
            `;

            container.appendChild(card);
        });

        // Attach action listeners
        container.querySelectorAll('.btn-postbox-ingest').forEach(btn => {
            btn.addEventListener('click', function() {
                openIngestModal(this.dataset);
            });
        });

        container.querySelectorAll('.btn-postbox-reject').forEach(btn => {
            btn.addEventListener('click', function() {
                const batchId = this.dataset.batch;
                const docIdx = this.dataset.index;
                if (confirm('Are you sure you want to reject and delete this document from the postbox?')) {
                    rejectDocument(batchId, docIdx);
                }
            });
        });
    }

    // Open Submodal for Ingestion Review
    function openIngestModal(data) {
        const modal = document.getElementById('modal-postbox-ingest');
        if (!modal) return;

        document.getElementById('postbox-ingest-batch-id').value = data.batch || '';
        document.getElementById('postbox-ingest-doc-index').value = data.index || '0';
        document.getElementById('postbox-ingest-title').value = data.title || '';
        document.getElementById('postbox-ingest-slug').value = data.slug || '';
        document.getElementById('postbox-ingest-type').value = (data.type || 'markdown').toLowerCase();
        document.getElementById('postbox-ingest-desc').value = data.desc || '';
        document.getElementById('postbox-ingest-theme').value = data.theme || '';
        document.getElementById('postbox-ingest-readonly').checked = (data.readonly === '1');

        // Category Hint Handling
        const catHintNotice = document.getElementById('postbox-category-hint-notice');
        const catHintName = document.getElementById('postbox-category-hint-name');
        const catHintBtn = document.getElementById('postbox-apply-category-hint-btn');

        const hint = (data.categoryHint || '').trim();
        if (hint && catHintNotice && catHintName) {
            catHintName.textContent = hint;
            catHintNotice.style.display = 'block';

            catHintBtn.onclick = function() {
                const catSelect = document.getElementById('postbox-ingest-category');
                let found = false;
                for (let i = 0; i < catSelect.options.length; i++) {
                    if (catSelect.options[i].value.toLowerCase() === hint.toLowerCase() ||
                        catSelect.options[i].textContent.toLowerCase().includes(hint.toLowerCase())) {
                        catSelect.selectedIndex = i;
                        found = true;
                        break;
                    }
                }
                if (!found) {
                    const wrap = document.getElementById('postbox-new-category-wrap');
                    if (wrap) wrap.style.display = 'block';
                    const newCatInput = document.getElementById('postbox-new-category-title');
                    if (newCatInput) {
                        newCatInput.value = hint;
                        newCatInput.dispatchEvent(new Event('input'));
                    }
                }
            };
        } else if (catHintNotice) {
            catHintNotice.style.display = 'none';
        }

        // Populate Categories
        const catSelect = document.getElementById('postbox-ingest-category');
        catSelect.innerHTML = '<option value="">-- Choose Category --</option>';
        activeCategories.forEach(cat => {
            const opt = document.createElement('option');
            opt.value = cat.id;
            opt.textContent = cat.title + ' (' + cat.id + ')';
            catSelect.appendChild(opt);
        });

        // Hide new category input by default
        const newCatWrap = document.getElementById('postbox-new-category-wrap');
        if (newCatWrap) newCatWrap.style.display = 'none';

        // Asset notice
        const assetNotice = document.getElementById('postbox-ingest-assets-notice');
        const assetCount = parseInt(data.assets || '0', 10);
        if (assetNotice) {
            if (assetCount > 0) {
                document.getElementById('postbox-ingest-assets-text').textContent = 
                    `This document includes ${assetCount} embedded media asset(s). They will be extracted into uploads/images/ and links updated automatically.`;
                assetNotice.style.display = 'block';
            } else {
                assetNotice.style.display = 'none';
            }
        }

        modal.style.display = 'flex';
    }

    // Reject document / batch API call
    async function rejectDocument(batchId, docIndex) {
        try {
            const formData = new FormData();
            formData.append('batch_id', batchId);
            formData.append('doc_index', docIndex);

            const res = await fetch(getAdminApiUrl() + '?action=ext_postbox_reject', {
                method: 'POST',
                body: formData
            });
            const data = await res.json();
            if (data.success) {
                showPostboxAlert('Document rejected from postbox', 'success');
                loadInbox();
            } else {
                showPostboxAlert(data.error || 'Failed to reject document', 'error');
            }
        } catch (e) {
            showPostboxAlert('Error: ' + e.message, 'error');
        }
    }

    // Ingest form submission
    async function handleIngestSubmit(e) {
        e.preventDefault();
        const form = e.target;
        const commitBtn = document.getElementById('postbox-commit-ingest-btn');
        if (commitBtn) {
            commitBtn.disabled = true;
            commitBtn.innerHTML = '<span class="postbox-spinner" style="width:16px;height:16px;border-width:2px;display:inline-block;vertical-align:middle;margin-right:4px;"></span> Ingesting...';
        }

        try {
            const formData = new FormData(form);
            const res = await fetch(getAdminApiUrl() + '?action=ext_postbox_accept', {
                method: 'POST',
                body: formData
            });
            const data = await res.json();

            if (commitBtn) {
                commitBtn.disabled = false;
                commitBtn.innerHTML = '<span>📥</span> Accept & Ingest Document';
            }

            if (data.success) {
                document.getElementById('modal-postbox-ingest').style.display = 'none';
                showPostboxAlert(data.message || 'Document ingested successfully!', 'success');
                loadInbox();
                // If current page category matches or user wants to view it, refresh navigation
                setTimeout(() => {
                    if (confirm('Document successfully ingested into ' + data.bookId + '! Would you like to open it now?')) {
                        window.location.href = (data.bookId ? data.bookId + '/' : '') + (data.slug || '');
                    }
                }, 500);
            } else {
                alert('Ingestion error: ' + (data.error || 'Unknown error'));
            }
        } catch (e) {
            if (commitBtn) {
                commitBtn.disabled = false;
                commitBtn.innerHTML = '<span>📥</span> Accept & Ingest Document';
            }
            alert('Error during document ingestion: ' + e.message);
        }
    }

    // Load peers and populate dropdowns
    async function loadPeers() {
        try {
            const res = await fetch(getAdminApiUrl() + '?action=ext_postbox_peers');
            const data = await res.json();
            if (!data.success) return;

            peersData = data;

            // Populate local peers
            const optLocal = document.getElementById('postbox-optgroup-local');
            if (optLocal) {
                optLocal.innerHTML = '';
                (data.local_peers || []).forEach(p => {
                    const opt = document.createElement('option');
                    opt.value = p.id;
                    opt.textContent = p.name;
                    optLocal.appendChild(opt);
                });
                if ((data.local_peers || []).length === 0) {
                    const opt = document.createElement('option');
                    opt.disabled = true;
                    opt.textContent = 'No local subwikis discovered';
                    optLocal.appendChild(opt);
                }
            }

            // Populate remote peers
            const optRemote = document.getElementById('postbox-optgroup-remote');
            if (optRemote) {
                optRemote.innerHTML = '';
                (data.remote_peers || []).forEach(p => {
                    const opt = document.createElement('option');
                    opt.value = 'remote:' + (p.id || '');
                    opt.textContent = p.name + ' (' + p.url + ')';
                    optRemote.appendChild(opt);
                });
                if ((data.remote_peers || []).length === 0) {
                    const opt = document.createElement('option');
                    opt.disabled = true;
                    opt.textContent = 'No remote peers configured';
                    optRemote.appendChild(opt);
                }
            }

            // Render peers in settings table
            renderPeersSettingsTable(data.remote_peers || []);

            // Populate token field in settings
            const tokenField = document.getElementById('postbox-my-token-field');
            if (tokenField && data.my_token) {
                tokenField.value = data.my_token;
            }

        } catch (e) {
            console.error('Failed to load postbox peers:', e);
        }
    }

    // Render saved peers table in settings tab
    function renderPeersSettingsTable(peers) {
        const tbody = document.getElementById('postbox-peers-tbody');
        const noPeers = document.getElementById('postbox-no-peers');
        const wrap = document.getElementById('postbox-peers-table-wrap');

        if (!tbody || !wrap || !noPeers) return;

        if (peers.length === 0) {
            noPeers.style.display = 'block';
            wrap.style.display = 'none';
            return;
        }

        noPeers.style.display = 'none';
        wrap.style.display = 'block';
        tbody.innerHTML = '';

        peers.forEach((p, idx) => {
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td style="padding: 0.5rem; font-weight: 500;">${escapeHtml(p.name)}</td>
                <td style="padding: 0.5rem;"><code>${escapeHtml(p.url)}</code></td>
                <td style="padding: 0.5rem;"><code style="font-size:0.8rem;">${escapeHtml(p.token ? p.token.substring(0, 10) + '...' : '')}</code></td>
                <td style="padding: 0.5rem; text-align: right;">
                    <button type="button" class="btn btn-outline btn-sm btn-delete-peer" data-index="${idx}" style="color: #ef4444; padding: 0.2rem 0.5rem;">Delete</button>
                </td>
            `;
            tbody.appendChild(tr);
        });

        tbody.querySelectorAll('.btn-delete-peer').forEach(btn => {
            btn.addEventListener('click', function() {
                const idx = parseInt(this.dataset.index, 10);
                if (confirm('Remove this saved peer?')) {
                    deleteSavedPeer(idx);
                }
            });
        });
    }

    // Delete a saved peer
    async function deleteSavedPeer(idx) {
        if (!peersData || !peersData.remote_peers) return;
        const peers = [...peersData.remote_peers];
        peers.splice(idx, 1);

        try {
            const formData = new FormData();
            formData.append('peers', JSON.stringify(peers));
            const res = await fetch(getAdminApiUrl() + '?action=ext_postbox_save_settings', {
                method: 'POST',
                body: formData
            });
            const data = await res.json();
            if (data.success) {
                showPostboxAlert('Peer removed successfully', 'success');
                loadPeers();
            } else {
                showPostboxAlert(data.error || 'Failed to remove peer', 'error');
            }
        } catch (e) {
            showPostboxAlert('Error: ' + e.message, 'error');
        }
    }

    // Save a new peer from settings form
    async function handleNewPeerSubmit(e) {
        e.preventDefault();
        const name = document.getElementById('postbox-new-peer-name').value.trim();
        const url = document.getElementById('postbox-new-peer-url').value.trim();
        const token = document.getElementById('postbox-new-peer-token').value.trim();

        if (!name || !url || !token) return;

        const currentPeers = (peersData && peersData.remote_peers) ? [...peersData.remote_peers] : [];
        currentPeers.push({
            id: 'peer_' + Date.now(),
            name: name,
            url: url,
            token: token
        });

        try {
            const formData = new FormData();
            formData.append('peers', JSON.stringify(currentPeers));
            const res = await fetch(getAdminApiUrl() + '?action=ext_postbox_save_settings', {
                method: 'POST',
                body: formData
            });
            const data = await res.json();
            if (data.success) {
                showPostboxAlert('Peer saved successfully', 'success');
                document.getElementById('postbox-new-peer-form').reset();
                document.getElementById('postbox-new-peer-form').style.display = 'none';
                loadPeers();
            } else {
                showPostboxAlert(data.error || 'Failed to save peer', 'error');
            }
        } catch (e) {
            showPostboxAlert('Error: ' + e.message, 'error');
        }
    }

    // Populate Outbox Document choices
    function populateOutboxDocuments() {
        const singleSelect = document.getElementById('postbox-single-doc-select');
        const catSelect = document.getElementById('postbox-category-select');
        const bulkList = document.getElementById('postbox-bulk-list');

        if (!singleSelect || !catSelect || !bulkList) return;

        // Collect all documents from current page tree (read from navigation DOM or global config)
        const docLinks = document.querySelectorAll('.sidebar-nav a[href*="/"]');
        const items = [];
        const categories = [];

        // Check sidebar categories
        document.querySelectorAll('.nav-group').forEach(group => {
            const catHeader = group.querySelector('.nav-group-title, .nav-book-title, [data-category-id]');
            const catId = group.dataset.categoryId || (catHeader ? catHeader.textContent.trim() : '');
            const catTitle = catHeader ? catHeader.textContent.trim() : catId;
            if (catId) {
                categories.push({ id: catId, title: catTitle });
            }

            group.querySelectorAll('.nav-item a, .nav-chapter a').forEach(link => {
                const href = link.getAttribute('href') || '';
                const title = link.textContent.trim();
                const slugMatch = href.split('/').filter(Boolean).pop();
                if (slugMatch) {
                    items.push({
                        title: title,
                        slug: slugMatch,
                        bookId: catId
                    });
                }
            });
        });

        // Single Doc Dropdown
        singleSelect.innerHTML = '<option value="">-- Choose a document --</option>';
        items.forEach(it => {
            const opt = document.createElement('option');
            opt.value = it.slug;
            opt.dataset.bookId = it.bookId;
            opt.textContent = it.title + ' (' + it.slug + ')';
            singleSelect.appendChild(opt);
        });

        // Try to preselect active chapter
        const activeLink = document.querySelector('.sidebar-nav .nav-item.active a, .nav-chapter.active a');
        if (activeLink) {
            const activeSlug = (activeLink.getAttribute('href') || '').split('/').filter(Boolean).pop();
            if (activeSlug) {
                singleSelect.value = activeSlug;
            }
        }

        // Category Dropdown
        catSelect.innerHTML = '<option value="">-- Choose a category --</option>';
        categories.forEach(c => {
            const opt = document.createElement('option');
            opt.value = c.id;
            opt.textContent = c.title + ' (' + c.id + ')';
            catSelect.appendChild(opt);
        });

        // Bulk Checklist
        bulkList.innerHTML = '';
        items.forEach(it => {
            const row = document.createElement('label');
            row.className = 'postbox-check-item';
            row.innerHTML = `
                <input type="checkbox" name="postbox_bulk_docs[]" value="${escapeHtml(it.slug)}" data-book="${escapeHtml(it.bookId)}">
                <span>${escapeHtml(it.title)} <small style="color:var(--text-muted)">(${escapeHtml(it.bookId)} / ${escapeHtml(it.slug)})</small></span>
            `;
            bulkList.appendChild(row);
        });
    }

    // Outbound form submission
    async function handleSendSubmit(e) {
        e.preventDefault();
        const mode = document.querySelector('input[name="postbox_mode"]:checked').value;
        const destSelect = document.getElementById('postbox-destination-select');
        const targetPeerId = destSelect.value;

        if (!targetPeerId) {
            alert('Please select a destination wiki.');
            return;
        }

        const formData = new FormData();
        formData.append('mode', mode);
        formData.append('target_peer_id', targetPeerId);

        if (targetPeerId === 'custom') {
            const customUrl = document.getElementById('postbox-custom-url').value.trim();
            const customToken = document.getElementById('postbox-custom-token').value.trim();
            if (!customUrl || !customToken) {
                alert('Please enter both the destination base URL and the recipient postbox token.');
                return;
            }
            formData.append('custom_url', customUrl);
            formData.append('custom_token', customToken);
        }

        if (mode === 'single') {
            const singleSelect = document.getElementById('postbox-single-doc-select');
            const slug = singleSelect.value;
            if (!slug) {
                alert('Please choose a document to transfer.');
                return;
            }
            const selectedOpt = singleSelect.options[singleSelect.selectedIndex];
            formData.append('slug', slug);
            formData.append('bookId', selectedOpt ? selectedOpt.dataset.bookId || '' : '');
        } else if (mode === 'category') {
            const catId = document.getElementById('postbox-category-select').value;
            if (!catId) {
                alert('Please choose a category to transfer.');
                return;
            }
            formData.append('category_id', catId);
        } else if (mode === 'bulk') {
            const checkedBoxes = document.querySelectorAll('input[name="postbox_bulk_docs[]"]:checked');
            if (checkedBoxes.length === 0) {
                alert('Please select at least one document to transfer.');
                return;
            }
            const keys = [];
            checkedBoxes.forEach(cb => {
                keys.push({ slug: cb.value, bookId: cb.dataset.book || '' });
            });
            formData.append('doc_keys', JSON.stringify(keys));
        }

        const sendBtn = document.getElementById('postbox-send-btn');
        if (sendBtn) {
            sendBtn.disabled = true;
            sendBtn.innerHTML = '<span class="postbox-spinner" style="width:16px;height:16px;border-width:2px;display:inline-block;vertical-align:middle;margin-right:4px;"></span> Sending...';
        }

        try {
            const res = await fetch(getAdminApiUrl() + '?action=ext_postbox_send', {
                method: 'POST',
                body: formData
            });
            const data = await res.json();

            if (sendBtn) {
                sendBtn.disabled = false;
                sendBtn.innerHTML = '<span>🚀</span> Package & Send';
            }

            if (data.success) {
                showPostboxAlert(data.message || 'Documents successfully sent to recipient postbox!', 'success');
                // Reset form
                document.getElementById('postbox-send-form').reset();
                document.getElementById('postbox-custom-dest-box').style.display = 'none';
            } else {
                alert('Transfer failed: ' + (data.error || 'Unknown error'));
            }
        } catch (e) {
            if (sendBtn) {
                sendBtn.disabled = false;
                sendBtn.innerHTML = '<span>🚀</span> Package & Send';
            }
            alert('Error during postbox dispatch: ' + e.message);
        }
    }

    // Helper to safely escape HTML strings
    function escapeHtml(str) {
        if (!str) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    // Setup all event handlers on DOM load
    function initPostboxExtension() {
        // 1. Check count immediately
        checkPostboxCount();

        // 2. Header utility button handler
        const btnUtilPostbox = document.getElementById('btn-util-postbox');
        if (btnUtilPostbox) {
            btnUtilPostbox.addEventListener('click', function(e) {
                e.preventDefault();
                const modal = document.getElementById('modal-postbox');
                if (modal) {
                    modal.style.display = 'flex';
                    switchPostboxTab('inbox');
                }
            });
        }

        // 3. Tab buttons
        document.querySelectorAll('.postbox-tab-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                switchPostboxTab(this.dataset.tab);
            });
        });

        // 4. Modal close buttons
        document.querySelectorAll('[data-close="modal-postbox"]').forEach(btn => {
            btn.addEventListener('click', () => {
                const m = document.getElementById('modal-postbox');
                if (m) m.style.display = 'none';
            });
        });
        document.querySelectorAll('[data-close="modal-postbox-ingest"]').forEach(btn => {
            btn.addEventListener('click', () => {
                const m = document.getElementById('modal-postbox-ingest');
                if (m) m.style.display = 'none';
            });
        });

        // 5. Transfer Scope radio toggles
        document.querySelectorAll('input[name="postbox_mode"]').forEach(radio => {
            radio.addEventListener('change', function() {
                const mode = this.value;
                document.getElementById('postbox-scope-single').style.display = (mode === 'single') ? 'block' : 'none';
                document.getElementById('postbox-scope-category').style.display = (mode === 'category') ? 'block' : 'none';
                document.getElementById('postbox-scope-bulk').style.display = (mode === 'bulk') ? 'block' : 'none';
            });
        });

        // 6. Destination selection toggle for custom URL
        const destSelect = document.getElementById('postbox-destination-select');
        if (destSelect) {
            destSelect.addEventListener('change', function() {
                const customBox = document.getElementById('postbox-custom-dest-box');
                if (customBox) {
                    customBox.style.display = (this.value === 'custom') ? 'block' : 'none';
                }
            });
        }

        // 7. Toggle new category in ingestion dialog
        const toggleNewCatBtn = document.getElementById('postbox-toggle-new-category-btn');
        if (toggleNewCatBtn) {
            toggleNewCatBtn.addEventListener('click', function() {
                const wrap = document.getElementById('postbox-new-category-wrap');
                if (wrap) {
                    const isVisible = wrap.style.display !== 'none';
                    wrap.style.display = isVisible ? 'none' : 'block';
                    if (!isVisible) {
                        document.getElementById('postbox-new-category-title').focus();
                    }
                }
            });
        }

        // When new category title is typed, automatically set select value or placeholder
        const newCatTitleInput = document.getElementById('postbox-new-category-title');
        if (newCatTitleInput) {
            newCatTitleInput.addEventListener('input', function() {
                const title = this.value.trim();
                const slug = title.toLowerCase().replace(/[^a-z0-9\-]/g, '-').replace(/-+/g, '-').replace(/^-|-$/g, '');
                const catSelect = document.getElementById('postbox-ingest-category');
                if (title && catSelect) {
                    // Check if custom option already exists
                    let customOpt = catSelect.querySelector('option[data-is-new="1"]');
                    if (!customOpt) {
                        customOpt = document.createElement('option');
                        customOpt.dataset.isNew = '1';
                        catSelect.appendChild(customOpt);
                    }
                    customOpt.value = slug || 'new-category';
                    customOpt.textContent = '✨ New: ' + title;
                    catSelect.value = customOpt.value;
                }
            });
        }

        // 8. Forms
        const sendForm = document.getElementById('postbox-send-form');
        if (sendForm) sendForm.addEventListener('submit', handleSendSubmit);

        const ingestForm = document.getElementById('postbox-ingest-form');
        if (ingestForm) ingestForm.addEventListener('submit', handleIngestSubmit);

        const newPeerForm = document.getElementById('postbox-new-peer-form');
        if (newPeerForm) newPeerForm.addEventListener('submit', handleNewPeerSubmit);

        // 9. Settings Buttons
        const addPeerBtn = document.getElementById('postbox-add-peer-btn');
        if (addPeerBtn) {
            addPeerBtn.addEventListener('click', () => {
                const f = document.getElementById('postbox-new-peer-form');
                if (f) f.style.display = f.style.display === 'none' ? 'block' : 'none';
            });
        }
        const cancelPeerBtn = document.getElementById('postbox-cancel-peer-btn');
        if (cancelPeerBtn) {
            cancelPeerBtn.addEventListener('click', () => {
                const f = document.getElementById('postbox-new-peer-form');
                if (f) f.style.display = 'none';
            });
        }

        const copyTokenBtn = document.getElementById('postbox-copy-token-btn');
        if (copyTokenBtn) {
            copyTokenBtn.addEventListener('click', () => {
                const tokenField = document.getElementById('postbox-my-token-field');
                if (tokenField) {
                    navigator.clipboard.writeText(tokenField.value).then(() => {
                        const origText = copyTokenBtn.textContent;
                        copyTokenBtn.textContent = '✅ Copied!';
                        setTimeout(() => copyTokenBtn.textContent = origText, 2000);
                    });
                }
            });
        }

        const regenTokenBtn = document.getElementById('postbox-regen-token-btn');
        if (regenTokenBtn) {
            regenTokenBtn.addEventListener('click', async () => {
                if (confirm('Regenerating the Postbox Token will invalidate the previous token for any sender wikis. Are you sure?')) {
                    try {
                        const formData = new FormData();
                        formData.append('regenerate_token', '1');
                        const res = await fetch(getAdminApiUrl() + '?action=ext_postbox_save_settings', {
                            method: 'POST',
                            body: formData
                        });
                        const data = await res.json();
                        if (data.success && data.token) {
                            document.getElementById('postbox-my-token-field').value = data.token;
                            showPostboxAlert('Postbox token regenerated successfully', 'success');
                        }
                    } catch (e) {
                        showPostboxAlert('Error: ' + e.message, 'error');
                    }
                }
            });
        }

        // 10. Quick "Send to Postbox" button on active document
        const quickSendBtn = document.getElementById('btn-send-to-postbox');
        if (quickSendBtn) {
            quickSendBtn.addEventListener('click', function(e) {
                e.preventDefault();
                const modal = document.getElementById('modal-postbox');
                if (modal) {
                    modal.style.display = 'flex';
                    switchPostboxTab('outbox');
                }
            });
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initPostboxExtension);
    } else {
        initPostboxExtension();
    }
})();
