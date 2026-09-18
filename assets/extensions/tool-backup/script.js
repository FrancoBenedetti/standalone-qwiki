/**
 * Standalone Qwiki - Backup & Export Extension Client Script
 */
function initBackupExtension() {
    const btnOpen = document.getElementById('btn-util-backup');
    const modalBackup = document.getElementById('modal-backup');
    if (!modalBackup) return;

    // View Tabs
    const tabBtnFull = document.getElementById('backup-tab-btn-full');
    const tabBtnSelective = document.getElementById('backup-tab-btn-selective');
    const viewFull = document.getElementById('backup-view-full');
    const viewSelective = document.getElementById('backup-view-selective');

    // Status Banner
    const statusBanner = document.getElementById('backup-status-banner');
    const statusText = document.getElementById('backup-status-text');

    // Full Mode Elements
    const fullContentMetric = document.getElementById('backup-full-content-metric');
    const fullUploadsMetric = document.getElementById('backup-full-uploads-metric');
    const fullQwikiMetric = document.getElementById('backup-full-qwiki-metric');
    const fullUsersMetric = document.getElementById('backup-full-users-metric');
    const fullOptUsers = document.getElementById('backup-full-opt-users');
    const fullOptSubwikisContainer = document.getElementById('backup-full-opt-subwikis-container');
    const fullOptSubwikis = document.getElementById('backup-full-opt-subwikis');
    const btnDownloadFull = document.getElementById('btn-backup-download-full');
    const totalBadge = document.getElementById('backup-total-badge');

    // Selective Mode Elements
    const treeRoot = document.getElementById('backup-tree-root');
    const treeLoading = document.getElementById('backup-tree-loading');
    const treeFilter = document.getElementById('backup-tree-filter');
    const selQwikiSize = document.getElementById('backup-selective-qwiki-size');
    const selUsersSize = document.getElementById('backup-selective-users-size');
    const selectedCountEl = document.getElementById('backup-selected-files-count');
    const selectedSizeEl = document.getElementById('backup-selected-bytes-size');
    const btnDownloadSelective = document.getElementById('btn-backup-download-selective');
    const subwikisBox = document.getElementById('backup-subwikis-selective-container');
    const subwikisListEl = document.getElementById('backup-subwikis-list');

    // Presets
    const btnPresetAll = document.getElementById('btn-backup-preset-all');
    const btnPresetContent = document.getElementById('btn-backup-preset-content');
    const btnPresetMedia = document.getElementById('btn-backup-preset-media');
    const btnPresetNone = document.getElementById('btn-backup-preset-none');

    // State
    let scanData = null;
    let isScanning = false;
    let allCheckboxes = [];

    // Helper: format bytes
    function formatBytes(bytes) {
        if (!bytes || bytes <= 0) return '0 B';
        const units = ['B', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(1024));
        const val = bytes / Math.pow(1024, i);
        return val.toFixed(1) + ' ' + units[i];
    }

    // Modal Show/Hide
    window.openBackupModal = function() {
        if (!modalBackup) return;
        modalBackup.classList.add('open');
        modalBackup.classList.add('active');

        // Close user dropdown if open
        const dropdownMenu = document.querySelector('.dropdown-menu.show');
        if (dropdownMenu) {
            dropdownMenu.classList.remove('show');
        }

        if (!scanData && !isScanning) {
            fetchScanData();
        }
    };

    function closeModal() {
        modalBackup.classList.remove('open');
        modalBackup.classList.remove('active');
    }

    if (btnOpen) {
        btnOpen.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            window.openBackupModal();
        });
    }

    // Delegate clicks for any backup open triggers
    document.addEventListener('click', (e) => {
        const trigger = e.target.closest('#btn-util-backup, .btn-open-backup, [data-open="modal-backup"]');
        if (trigger) {
            e.preventDefault();
            e.stopPropagation();
            window.openBackupModal();
        }
    });

    const closeBtns = modalBackup.querySelectorAll('[data-close="modal-backup"]');
    closeBtns.forEach(btn => btn.addEventListener('click', closeModal));

    modalBackup.addEventListener('click', (e) => {
        if (e.target === modalBackup) closeModal();
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && (modalBackup.classList.contains('open') || modalBackup.classList.contains('active'))) {
            closeModal();
        }
    });

    // Tab Switching
    function switchTab(mode) {
        if (mode === 'full') {
            tabBtnFull.classList.add('active');
            tabBtnSelective.classList.remove('active');
            viewFull.style.display = 'block';
            viewSelective.style.display = 'none';
        } else {
            tabBtnFull.classList.remove('active');
            tabBtnSelective.classList.add('active');
            viewFull.style.display = 'none';
            viewSelective.style.display = 'block';
            updateSelectiveTotals();
        }
    }

    tabBtnFull.addEventListener('click', () => switchTab('full'));
    tabBtnSelective.addEventListener('click', () => switchTab('selective'));

    // Status Banner Helpers
    function showStatus(message) {
        statusText.textContent = message;
        statusBanner.style.display = 'flex';
    }

    function hideStatus() {
        statusBanner.style.display = 'none';
    }

    // API: Fetch Scan Data
    async function fetchScanData() {
        isScanning = true;
        showStatus('Scanning wiki files and calculating archive sizes...');
        totalBadge.textContent = 'Scanning...';

        try {
            const res = await fetch('api/admin.php?action=ext_backup_scan');
            const data = await res.json();

            if (!data.success) {
                alert(data.error || 'Failed to scan wiki contents');
                totalBadge.textContent = 'Error';
                hideStatus();
                isScanning = false;
                return;
            }

            scanData = data;
            renderFullMetrics(data.summary);
            renderSelectiveTree(data.tree, data.summary);
            totalBadge.textContent = `${data.summary.totalFiles} files (${data.summary.formattedTotalSize})`;
            hideStatus();
        } catch (err) {
            console.error('Scan error:', err);
            totalBadge.textContent = 'Error';
            hideStatus();
        } finally {
            isScanning = false;
        }
    }

    // Render Full Backup Metrics
    function renderFullMetrics(summary) {
        fullContentMetric.textContent = `${summary.content.filesCount} files (${summary.content.formattedSize})`;
        fullUploadsMetric.textContent = `${summary.uploads.filesCount} files (${summary.uploads.formattedSize})`;
        fullQwikiMetric.textContent = summary.qwikiJson.exists ? summary.qwikiJson.formattedSize : 'Missing';
        fullUsersMetric.textContent = summary.usersJson.exists ? summary.usersJson.formattedSize : 'None';

        selQwikiSize.textContent = summary.qwikiJson.formattedSize;
        selUsersSize.textContent = summary.usersJson.formattedSize;

        // Subwikis
        if (summary.subwikis && summary.subwikis.length > 0) {
            fullOptSubwikisContainer.style.display = 'flex';
            subwikisBox.style.display = 'block';
            renderSubwikisList(summary.subwikis);
        } else {
            fullOptSubwikisContainer.style.display = 'none';
            subwikisBox.style.display = 'none';
        }
    }

    // Render Subwikis in Selective View
    function renderSubwikisList(subwikis) {
        subwikisListEl.innerHTML = '';
        subwikis.forEach(sub => {
            const label = document.createElement('label');
            label.className = 'backup-tree-item-label';
            label.style.display = 'flex';
            label.style.marginTop = '0.35rem';
            label.innerHTML = `
                <input type="checkbox" class="backup-check-item" data-path="${sub.slug}" data-size="${sub.bytes}">
                <span class="backup-icon">🌐</span>
                <span class="backup-name"><strong>${escapeHtml(sub.title)}</strong> (<code>/${escapeHtml(sub.slug)}/</code> - ${sub.filesCount} files)</span>
                <span class="backup-size-tag">${sub.formattedSize}</span>
            `;
            subwikisListEl.appendChild(label);
        });
    }

    // Render Selective File Tree
    function renderSelectiveTree(tree, summary) {
        treeRoot.innerHTML = '';
        allCheckboxes = [];

        // 1. Content Node
        const contentNode = createTreeNode({
            name: 'content',
            path: 'content',
            type: 'folder',
            bytes: summary.content.bytes,
            formattedSize: summary.content.formattedSize,
            filesCount: summary.content.filesCount,
            children: tree.content
        }, true);
        treeRoot.appendChild(contentNode);

        // 2. Uploads Node
        const uploadsNode = createTreeNode({
            name: 'uploads',
            path: 'uploads',
            type: 'folder',
            bytes: summary.uploads.bytes,
            formattedSize: summary.uploads.formattedSize,
            filesCount: summary.uploads.filesCount,
            children: tree.uploads
        }, true);
        treeRoot.appendChild(uploadsNode);

        treeLoading.style.display = 'none';
        treeRoot.style.display = 'block';

        bindTreeEvents();
        updateSelectiveTotals();
    }

    function createTreeNode(item, isRoot = false) {
        const li = document.createElement('li');
        li.className = 'backup-tree-node';
        li.setAttribute('data-node-path', item.path);

        const isFolder = item.type === 'folder';
        const icon = isFolder ? '📁' : getFileIcon(item.ext);

        const row = document.createElement('div');
        row.className = 'backup-tree-node-row';

        // Toggle caret for folders
        const toggle = document.createElement('span');
        toggle.className = isFolder ? 'backup-tree-toggle' : 'backup-tree-toggle' ;
        toggle.textContent = isFolder ? '▼' : '';
        if (!isFolder) toggle.style.visibility = 'hidden';

        // Checkbox
        const chk = document.createElement('input');
        chk.type = 'checkbox';
        chk.className = 'backup-tree-checkbox backup-check-item';
        chk.setAttribute('data-path', item.path);
        chk.setAttribute('data-type', item.type);
        chk.setAttribute('data-size', item.bytes || 0);
        chk.checked = true; // Default checked

        // Icon
        const iconSpan = document.createElement('span');
        iconSpan.className = 'backup-tree-icon';
        iconSpan.textContent = icon;

        // Name
        const nameSpan = document.createElement('span');
        nameSpan.className = 'backup-tree-name';
        nameSpan.textContent = item.name + (isFolder ? '/' : '');

        // Size badge
        const sizeSpan = document.createElement('span');
        sizeSpan.className = 'backup-size-tag';
        sizeSpan.textContent = item.formattedSize || formatBytes(item.bytes);

        row.appendChild(toggle);
        row.appendChild(chk);
        row.appendChild(iconSpan);
        row.appendChild(nameSpan);
        row.appendChild(sizeSpan);
        li.appendChild(row);

        if (isFolder && item.children && item.children.length > 0) {
            const ul = document.createElement('ul');
            ul.className = 'backup-tree-children';
            item.children.forEach(child => {
                ul.appendChild(createTreeNode(child, false));
            });
            li.appendChild(ul);

            // Click caret to toggle
            toggle.addEventListener('click', (e) => {
                e.stopPropagation();
                toggle.classList.toggle('collapsed');
                ul.classList.toggle('collapsed');
            });
        }

        return li;
    }

    function getFileIcon(ext) {
        if (!ext) return '📄';
        switch (ext.toLowerCase()) {
            case 'md': return '📝';
            case 'html': return '🌐';
            case 'png':
            case 'jpg':
            case 'jpeg':
            case 'gif':
            case 'svg':
            case 'webp': return '🖼️';
            case 'mp4':
            case 'webm': return '🎬';
            case 'pdf': return '📕';
            case 'json': return '⚙️';
            default: return '📄';
        }
    }

    function escapeHtml(str) {
        if (!str) return '';
        return str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    // Bind events for cascading checkboxes
    function bindTreeEvents() {
        modalBackup.querySelectorAll('.backup-check-item').forEach(chk => {
            chk.addEventListener('change', (e) => {
                const isChecked = chk.checked;
                const nodeLi = chk.closest('.backup-tree-node');
                if (nodeLi) {
                    // Cascade to children
                    const childBoxes = nodeLi.querySelectorAll('.backup-tree-children .backup-check-item');
                    childBoxes.forEach(child => child.checked = isChecked);
                }
                updateSelectiveTotals();
            });
        });
    }

    // Calculate total selected files & bytes
    function updateSelectiveTotals() {
        let count = 0;
        let bytes = 0;

        // 1. Root critical files
        const rootItems = modalBackup.querySelectorAll('.backup-root-files-box .backup-check-item:checked');
        rootItems.forEach(chk => {
            const p = chk.getAttribute('data-path');
            count++;
            if (p === 'qwiki.json' && scanData) bytes += (scanData.summary.qwikiJson.bytes || 0);
            if (p === 'users.json' && scanData) bytes += (scanData.summary.usersJson.bytes || 0);
        });

        // 2. Tree leaf file checkboxes
        // Count all checked files (or files inside checked folders)
        const checkedLeafs = treeRoot.querySelectorAll('.backup-tree-checkbox[data-type="file"]:checked');
        checkedLeafs.forEach(chk => {
            count++;
            bytes += parseInt(chk.getAttribute('data-size') || 0, 10);
        });

        // 3. Subwikis
        const subwikiItems = subwikisBox.querySelectorAll('.backup-check-item:checked');
        subwikiItems.forEach(chk => {
            count++;
            bytes += parseInt(chk.getAttribute('data-size') || 0, 10);
        });

        selectedCountEl.textContent = count;
        selectedSizeEl.textContent = formatBytes(bytes);
    }

    // Presets
    if (btnPresetAll) {
        btnPresetAll.addEventListener('click', () => {
            modalBackup.querySelectorAll('.backup-check-item').forEach(chk => chk.checked = true);
            updateSelectiveTotals();
        });
    }

    if (btnPresetNone) {
        btnPresetNone.addEventListener('click', () => {
            modalBackup.querySelectorAll('.backup-check-item').forEach(chk => chk.checked = false);
            updateSelectiveTotals();
        });
    }

    if (btnPresetContent) {
        btnPresetContent.addEventListener('click', () => {
            modalBackup.querySelectorAll('.backup-check-item').forEach(chk => {
                const path = chk.getAttribute('data-path') || '';
                if (path === 'qwiki.json' || path === 'users.json' || path.startsWith('content')) {
                    chk.checked = true;
                } else {
                    chk.checked = false;
                }
            });
            updateSelectiveTotals();
        });
    }

    if (btnPresetMedia) {
        btnPresetMedia.addEventListener('click', () => {
            modalBackup.querySelectorAll('.backup-check-item').forEach(chk => {
                const path = chk.getAttribute('data-path') || '';
                chk.checked = path.startsWith('uploads');
            });
            updateSelectiveTotals();
        });
    }

    // Filter file tree
    if (treeFilter) {
        treeFilter.addEventListener('input', (e) => {
            const query = e.target.value.toLowerCase().trim();
            const nodes = treeRoot.querySelectorAll('.backup-tree-node');

            if (!query) {
                nodes.forEach(n => n.style.display = '');
                return;
            }

            nodes.forEach(n => {
                const name = (n.querySelector('.backup-tree-name')?.textContent || '').toLowerCase();
                if (name.includes(query)) {
                    n.style.display = '';
                    // Expand parents
                    let parent = n.parentElement.closest('.backup-tree-node');
                    while (parent) {
                        parent.style.display = '';
                        const ul = parent.querySelector('.backup-tree-children');
                        if (ul) ul.classList.remove('collapsed');
                        const toggle = parent.querySelector('.backup-tree-toggle');
                        if (toggle) toggle.classList.remove('collapsed');
                        parent = parent.parentElement.closest('.backup-tree-node');
                    }
                } else {
                    // Hide if not containing a match
                    const hasMatch = n.querySelector(`.backup-tree-node[style*="display: block"]`) ||
                                     n.textContent.toLowerCase().includes(query);
                    n.style.display = hasMatch ? '' : 'none';
                }
            });
        });
    }

    // Trigger Archive Download (Native Form Post)
    function triggerDownload(params) {
        showStatus('Generating ZIP archive... Your download will begin momentarily.');

        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'api/admin.php';
        form.style.display = 'none';

        // Action
        const actionInput = document.createElement('input');
        actionInput.name = 'action';
        actionInput.value = 'ext_backup_download';
        form.appendChild(actionInput);

        for (const [key, value] of Object.entries(params)) {
            const input = document.createElement('input');
            input.name = key;
            input.value = typeof value === 'object' ? JSON.stringify(value) : value;
            form.appendChild(input);
        }

        document.body.appendChild(form);
        form.submit();
        form.remove();

        // Auto-hide status banner after delay
        setTimeout(() => {
            hideStatus();
        }, 4000);
    }

    // Action: Download Full Backup
    if (btnDownloadFull) {
        btnDownloadFull.addEventListener('click', () => {
            triggerDownload({
                mode: 'full',
                include_users: fullOptUsers.checked ? '1' : '0',
                include_subwikis: fullOptSubwikis.checked ? '1' : '0'
            });
        });
    }

    // Action: Download Selective Export
    if (btnDownloadSelective) {
        btnDownloadSelective.addEventListener('click', () => {
            const selectedPaths = [];

            // 1. Root files
            modalBackup.querySelectorAll('.backup-root-files-box .backup-check-item:checked').forEach(chk => {
                const path = chk.getAttribute('data-path');
                if (path) selectedPaths.push(path);
            });

            // 2. Tree items: collect highest checked ancestor or checked files
            function collectCheckedPaths(container) {
                const topNodes = container.children;
                for (let i = 0; i < topNodes.length; i++) {
                    const node = topNodes[i];
                    if (!node.classList.contains('backup-tree-node')) continue;

                    const chk = node.querySelector(':scope > .backup-tree-node-row > .backup-tree-checkbox');
                    const childrenUl = node.querySelector(':scope > .backup-tree-children');

                    if (chk && chk.checked) {
                        // Check if all descendant checkboxes are checked
                        const allDescendants = childrenUl ? childrenUl.querySelectorAll('.backup-tree-checkbox') : [];
                        const checkedDescendants = childrenUl ? childrenUl.querySelectorAll('.backup-tree-checkbox:checked') : [];

                        if (allDescendants.length === checkedDescendants.length) {
                            // Whole directory is checked; add folder path
                            selectedPaths.push(chk.getAttribute('data-path'));
                        } else {
                            // Partial: recurse down
                            if (childrenUl) collectCheckedPaths(childrenUl);
                        }
                    } else if (childrenUl) {
                        // Unchecked folder, but may contain checked children
                        collectCheckedPaths(childrenUl);
                    }
                }
            }

            collectCheckedPaths(treeRoot);

            // 3. Subwikis
            subwikisBox.querySelectorAll('.backup-check-item:checked').forEach(chk => {
                const path = chk.getAttribute('data-path');
                if (path) selectedPaths.push(path);
            });

            if (selectedPaths.length === 0) {
                alert('Please select at least one file or folder to export.');
                return;
            }

            triggerDownload({
                mode: 'selective',
                items: selectedPaths
            });
        });
    }
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initBackupExtension);
} else {
    initBackupExtension();
}

