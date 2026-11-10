/**
 * Standalone Qwiki - Activity & Access Log Extension Client Script
 */
function initActivityLogExtension() {
    const btnOpen = document.getElementById('btn-util-activity_log');
    const modal = document.getElementById('modal-activity-log');
    if (!modal) return;

    // View Tabs
    const tabBtnStream = document.getElementById('act-tab-btn-stream');
    const tabBtnSettings = document.getElementById('act-tab-btn-settings');
    const viewStream = document.getElementById('act-view-stream');
    const viewSettings = document.getElementById('act-view-settings');

    // Status Banner
    const statusBanner = document.getElementById('act-status-banner');
    const statusText = document.getElementById('act-status-text');

    // Metrics
    const totalBadge = document.getElementById('act-total-badge');
    const metricShareHits = document.getElementById('act-metric-share-hits');
    const metricShareToday = document.getElementById('act-metric-share-today');
    const metricTopDoc = document.getElementById('act-metric-top-doc');
    const metricTopDocCount = document.getElementById('act-metric-top-doc-count');
    const metricFailedLogins = document.getElementById('act-metric-failed-logins');
    const metricStorage = document.getElementById('act-metric-storage');
    const metricTotalRecords = document.getElementById('act-metric-total-records');

    // Filter Controls
    const filterCategory = document.getElementById('act-filter-category');
    const filterStatus = document.getElementById('act-filter-status');
    const filterSince = document.getElementById('act-filter-since');
    const searchInput = document.getElementById('act-search-input');
    const btnRefresh = document.getElementById('btn-act-refresh');
    const btnExportCsv = document.getElementById('btn-act-export-csv');
    const btnPurge = document.getElementById('btn-act-purge');

    // Table & Pagination
    const tableBody = document.getElementById('act-table-body');
    const paginationInfo = document.getElementById('act-pagination-info');
    const pageDisplay = document.getElementById('act-page-display');
    const btnPrev = document.getElementById('btn-act-prev');
    const btnNext = document.getElementById('btn-act-next');

    // Settings Elements
    const settingsForm = document.getElementById('act-settings-form');
    const btnCancelSettings = document.getElementById('btn-act-cancel-settings');
    const ipModeHash = document.getElementById('act-ip-mode-hash');
    const ipModeMasked = document.getElementById('act-ip-mode-masked');
    const ipModeRaw = document.getElementById('act-ip-mode-raw');
    const retentionDaysInput = document.getElementById('act-retention-days');

    // State
    let currentPage = 1;
    let totalPages = 1;
    let currentSettings = { ipPrivacyMode: 'hash', logRetentionDays: 30 };
    let searchDebounce = null;

    function escapeHtml(str) {
        if (!str) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function showStatus(msg) {
        if (!statusBanner) return;
        statusText.textContent = msg;
        statusBanner.style.display = 'flex';
    }

    function hideStatus() {
        if (!statusBanner) return;
        statusBanner.style.display = 'none';
    }

    // Tab Switching
    if (tabBtnStream && tabBtnSettings) {
        tabBtnStream.addEventListener('click', () => {
            tabBtnStream.classList.add('active');
            tabBtnSettings.classList.remove('active');
            viewStream.style.display = 'flex';
            viewSettings.style.display = 'none';
        });

        tabBtnSettings.addEventListener('click', () => {
            tabBtnSettings.classList.add('active');
            tabBtnStream.classList.remove('active');
            viewStream.style.display = 'none';
            viewSettings.style.display = 'block';
        });

        if (btnCancelSettings) {
            btnCancelSettings.addEventListener('click', () => {
                tabBtnStream.click();
            });
        }
    }

    // Modal Trigger
    if (btnOpen) {
        btnOpen.addEventListener('click', (e) => {
            e.preventDefault();
            modal.classList.add('open');
            loadStats();
            loadLogs(1);
        });
    }

    // Modal Close
    modal.querySelectorAll('[data-close="modal-activity-log"]').forEach(btn => {
        btn.addEventListener('click', () => {
            modal.classList.remove('open');
        });
    });

    // Close on backdrop click
    modal.addEventListener('click', (e) => {
        if (e.target === modal) {
            modal.classList.remove('open');
        }
    });

    // Load Stats & Settings
    function loadStats() {
        fetch('api/admin.php?action=ext_activity_get_stats')
            .then(res => res.json())
            .then(res => {
                if (!res.success) return;
                const stats = res.stats || {};
                currentSettings = res.settings || currentSettings;

                if (metricShareHits) metricShareHits.textContent = (stats.share_hits_total || 0).toLocaleString();
                if (metricShareToday) metricShareToday.textContent = (stats.share_hits_today || 0) + ' today';
                if (metricTopDoc) metricTopDoc.textContent = stats.top_shared_doc || 'None';
                if (metricTopDocCount) metricTopDocCount.textContent = (stats.top_shared_doc_count || 0) + ' hits';
                if (metricFailedLogins) metricFailedLogins.textContent = stats.failed_logins_today || 0;
                if (metricStorage) metricStorage.textContent = stats.storage_formatted || '0 B';
                if (metricTotalRecords) metricTotalRecords.textContent = (stats.total_entries || 0).toLocaleString() + ' records';
                if (totalBadge) totalBadge.textContent = (stats.total_entries || 0).toLocaleString() + ' total events';

                // Populate settings form
                if (ipModeHash && ipModeMasked && ipModeRaw) {
                    const mode = currentSettings.ipPrivacyMode || 'hash';
                    ipModeHash.checked = (mode === 'hash');
                    ipModeMasked.checked = (mode === 'masked');
                    ipModeRaw.checked = (mode === 'raw');
                }
                if (retentionDaysInput) {
                    retentionDaysInput.value = currentSettings.logRetentionDays || 30;
                }
            })
            .catch(() => {});
    }

    // Load Logs with Filters
    function loadLogs(page = 1) {
        currentPage = page;
        showStatus('Loading activity records...');

        const params = new URLSearchParams({
            action: 'ext_activity_get_logs',
            page: currentPage,
            per_page: 50,
            category: filterCategory ? filterCategory.value : 'all',
            status: filterStatus ? filterStatus.value : '',
            since: filterSince ? filterSince.value : '',
            search: searchInput ? searchInput.value.trim() : ''
        });

        fetch('api/admin.php?' + params.toString())
            .then(res => res.json())
            .then(res => {
                hideStatus();
                if (!res.success) {
                    tableBody.innerHTML = `<tr><td colspan="6" style="text-align:center; padding:2rem; color:#ef4444;">${escapeHtml(res.error || 'Failed to load logs')}</td></tr>`;
                    return;
                }

                totalPages = res.pages || 1;
                renderTable(res.items || []);

                // Update pagination info
                const total = res.total || 0;
                const perPage = res.per_page || 50;
                const start = total === 0 ? 0 : (currentPage - 1) * perPage + 1;
                const end = Math.min(currentPage * perPage, total);

                if (paginationInfo) paginationInfo.textContent = `Showing ${start} - ${end} of ${total}`;
                if (pageDisplay) pageDisplay.textContent = `Page ${currentPage} of ${totalPages}`;

                if (btnPrev) btnPrev.disabled = (currentPage <= 1);
                if (btnNext) btnNext.disabled = (currentPage >= totalPages);
            })
            .catch(err => {
                hideStatus();
                tableBody.innerHTML = `<tr><td colspan="6" style="text-align:center; padding:2rem; color:#ef4444;">Error communicating with server: ${escapeHtml(err.message)}</td></tr>`;
            });
    }

    // Render Table Rows
    function renderTable(items) {
        if (!tableBody) return;
        if (items.length === 0) {
            tableBody.innerHTML = '<tr><td colspan="6" style="text-align: center; padding: 2.5rem; color: var(--text-muted);">No activity records match your filter criteria.</td></tr>';
            return;
        }

        let html = '';
        items.forEach((item, idx) => {
            const rowId = 'act-row-' + idx;
            const detailRowId = 'act-detail-' + idx;

            // Date
            let dateStr = item.ts || '';
            try {
                const d = new Date(item.ts);
                dateStr = d.toLocaleDateString() + ' ' + d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' });
            } catch (e) {}

            // Category Badge
            const cat = item.category || 'system';
            let catClass = 'act-badge-system';
            let catIcon = '⚙️';
            if (cat === 'share') { catClass = 'act-badge-share'; catIcon = '🔗'; }
            else if (cat === 'auth') { catClass = 'act-badge-auth'; catIcon = '🔑'; }
            else if (cat === 'content') { catClass = 'act-badge-content'; catIcon = '📝'; }

            const eventLabel = item.event || cat;

            // Target
            const details = item.details || {};
            let targetLabel = details.title || details.slug || details.file || details.target || '—';
            let subTarget = '';
            if (cat === 'share') {
                if (details.share_key) {
                    subTarget = `<span style="font-family: monospace; font-size: 0.72rem; color: var(--text-muted);">Key: ${escapeHtml(details.share_key)}</span>`;
                }
                if (details.format === 'json') {
                    subTarget += ' <span style="font-size: 0.7rem; color: #3b82f6;">[JSON]</span>';
                }
            } else if (cat === 'content' && details.file) {
                subTarget = `<span style="font-size: 0.72rem; color: var(--text-muted);">${escapeHtml(details.file)}</span>`;
            }

            // Actor & IP
            const user = item.user || 'guest';
            const ip = item.ip || '—';
            const ipType = item.ip_type ? `(${escapeHtml(item.ip_type)})` : '';

            // Status Pill
            const status = item.status || 200;
            let statusClass = 'act-status-200';
            if (status === 401) statusClass = 'act-status-401';
            else if (status === 403) statusClass = 'act-status-403';
            else if (status === 404) statusClass = 'act-status-404';

            html += `
                <tr id="${rowId}">
                    <td style="font-size: 0.78rem; color: var(--text-muted);">${escapeHtml(dateStr)}</td>
                    <td>
                        <span class="act-badge ${catClass}">
                            <span>${catIcon}</span> ${escapeHtml(eventLabel)}
                        </span>
                    </td>
                    <td>
                        <div style="font-weight: 500; font-size: 0.85rem;">${escapeHtml(targetLabel)}</div>
                        ${subTarget ? `<div>${subTarget}</div>` : ''}
                    </td>
                    <td>
                        <div class="act-actor-box">
                            <span class="act-actor-user">${escapeHtml(user)}</span>
                            <span class="act-actor-ip" title="${escapeHtml(ip)} ${ipType}">${escapeHtml(ip)}</span>
                        </div>
                    </td>
                    <td style="text-align: center;">
                        <span class="act-status-pill ${statusClass}">${escapeHtml(status)}</span>
                    </td>
                    <td style="text-align: center;">
                        <button type="button" class="act-details-btn" data-target="${detailRowId}">JSON</button>
                    </td>
                </tr>
                <tr id="${detailRowId}" class="act-details-row" style="display: none;">
                    <td colspan="6">
                        <pre class="act-details-pre">${escapeHtml(JSON.stringify(item, null, 2))}</pre>
                    </td>
                </tr>
            `;
        });

        tableBody.innerHTML = html;

        // Bind expand/collapse buttons
        tableBody.querySelectorAll('.act-details-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                const targetId = btn.getAttribute('data-target');
                const row = document.getElementById(targetId);
                if (!row) return;
                const isHidden = (row.style.display === 'none');
                row.style.display = isHidden ? 'table-row' : 'none';
                btn.style.color = isHidden ? 'var(--accent-color, #2563eb)' : '';
            });
        });
    }

    // Filter Change Listeners
    [filterCategory, filterStatus, filterSince].forEach(el => {
        if (el) {
            el.addEventListener('change', () => loadLogs(1));
        }
    });

    if (searchInput) {
        searchInput.addEventListener('input', () => {
            clearTimeout(searchDebounce);
            searchDebounce = setTimeout(() => {
                loadLogs(1);
            }, 300);
        });
    }

    if (btnRefresh) {
        btnRefresh.addEventListener('click', () => {
            loadStats();
            loadLogs(currentPage);
        });
    }

    // Pagination Listeners
    if (btnPrev) {
        btnPrev.addEventListener('click', () => {
            if (currentPage > 1) loadLogs(currentPage - 1);
        });
    }

    if (btnNext) {
        btnNext.addEventListener('click', () => {
            if (currentPage < totalPages) loadLogs(currentPage + 1);
        });
    }

    // Export CSV
    if (btnExportCsv) {
        btnExportCsv.addEventListener('click', () => {
            const params = new URLSearchParams({
                action: 'ext_activity_export_csv',
                category: filterCategory ? filterCategory.value : 'all',
                status: filterStatus ? filterStatus.value : '',
                since: filterSince ? filterSince.value : '',
                search: searchInput ? searchInput.value.trim() : ''
            });
            window.location.href = 'api/admin.php?' + params.toString();
        });
    }

    // Purge Logs
    if (btnPurge) {
        btnPurge.addEventListener('click', () => {
            const days = currentSettings.logRetentionDays || 30;
            if (!confirm(`Are you sure you want to purge log partitions older than ${days} days?\nThis action cannot be undone.`)) {
                return;
            }

            showStatus('Purging expired log records...');
            const formData = new FormData();
            formData.append('action', 'ext_activity_purge_logs');
            formData.append('keep_days', days);

            fetch('api/admin.php', { method: 'POST', body: formData })
                .then(res => res.json())
                .then(res => {
                    hideStatus();
                    alert(res.message || 'Purge completed.');
                    loadStats();
                    loadLogs(1);
                })
                .catch(err => {
                    hideStatus();
                    alert('Failed to purge logs: ' + err.message);
                });
        });
    }

    // Save Settings
    if (settingsForm) {
        settingsForm.addEventListener('submit', (e) => {
            e.preventDefault();
            const selectedMode = settingsForm.querySelector('input[name="ip_privacy_mode"]:checked')?.value || 'hash';
            const days = parseInt(retentionDaysInput.value, 10) || 30;

            showStatus('Saving logging settings...');
            const formData = new FormData();
            formData.append('action', 'ext_activity_save_settings');
            formData.append('ip_privacy_mode', selectedMode);
            formData.append('retention_days', days);

            fetch('api/admin.php', { method: 'POST', body: formData })
                .then(res => res.json())
                .then(res => {
                    hideStatus();
                    if (res.success) {
                        alert('Settings saved successfully!');
                        loadStats();
                        tabBtnStream.click();
                    } else {
                        alert(res.error || 'Failed to save settings.');
                    }
                })
                .catch(err => {
                    hideStatus();
                    alert('Error saving settings: ' + err.message);
                });
        });
    }
}

// Bootstrap
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initActivityLogExtension);
} else {
    initActivityLogExtension();
}
