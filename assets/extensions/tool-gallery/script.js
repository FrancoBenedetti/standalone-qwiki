/**
 * Standalone Qwiki - Image Gallery Extension Client Script
 */
document.addEventListener('DOMContentLoaded', () => {
    // Top-level modal elements
    const btnOpen = document.getElementById('btn-util-gallery');
    const modalGallery = document.getElementById('modal-gallery');
    const modalDelete = document.getElementById('modal-gallery-delete');
    const modalPreview = document.getElementById('modal-gallery-preview');

    // Gallery Toolbar & Body elements
    const searchInput = document.getElementById('gallery-search-input');
    const searchClear = document.getElementById('gallery-search-clear');
    const btnRefresh = document.getElementById('btn-gallery-refresh');
    const btnUploadTrigger = document.getElementById('btn-gallery-upload-trigger');
    const fileInput = document.getElementById('gallery-file-input');
    const dropzone = document.getElementById('gallery-dropzone');
    const galleryBody = document.getElementById('gallery-body');
    const galleryGrid = document.getElementById('gallery-grid');
    const galleryLoading = document.getElementById('gallery-loading');
    const galleryEmpty = document.getElementById('gallery-empty');
    const countBadge = document.getElementById('gallery-count-badge');

    // Delete Modal elements
    const deleteClose = document.getElementById('btn-gallery-delete-close');
    const deleteCancel = document.getElementById('btn-gallery-delete-cancel');
    const deleteConfirm = document.getElementById('btn-gallery-delete-confirm');
    const deleteThumb = document.getElementById('gallery-delete-thumb');
    const deleteFilename = document.getElementById('gallery-delete-filename');
    const deleteMetaInfo = document.getElementById('gallery-delete-meta-info');
    const deleteWarningBox = document.getElementById('gallery-delete-warning-box');
    const deleteWarningHeadline = document.getElementById('gallery-warning-headline');
    const deleteUsedDocList = document.getElementById('gallery-used-doc-list');
    const deleteSafeBox = document.getElementById('gallery-delete-safe-box');
    const deleteChecking = document.getElementById('gallery-delete-checking');

    // Preview Modal elements
    const previewClose = document.getElementById('btn-gallery-preview-close');
    const previewImg = document.getElementById('gallery-preview-img');
    const previewTitle = document.getElementById('gallery-preview-title');
    const previewMetaDim = document.getElementById('gallery-preview-meta-dim');
    const previewMetaSize = document.getElementById('gallery-preview-meta-size');
    const previewMetaDate = document.getElementById('gallery-preview-meta-date');
    const previewAltInput = document.getElementById('gallery-preview-alt-input');
    const previewMdInput = document.getElementById('gallery-preview-md-input');
    const previewHtmlInput = document.getElementById('gallery-preview-html-input');
    const btnCopyPreviewMd = document.getElementById('btn-copy-preview-md');
    const btnCopyPreviewHtml = document.getElementById('btn-copy-preview-html');
    const btnPreviewDelete = document.getElementById('btn-preview-delete');
    const btnPreviewOpenNewTab = document.getElementById('btn-preview-open-newtab');
    const btnPreviewInsertEditor = document.getElementById('btn-preview-insert-editor');

    // Extension State
    let allImages = [];
    let currentFiltered = [];
    let selectedImageForDelete = null;
    let selectedImageForPreview = null;
    let isFetching = false;

    // Helper: Escape HTML
    function escapeHtml(str) {
        if (!str) return '';
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    // Helper: Clean Alt Text from filename
    function cleanAltFromFilename(filename) {
        if (!filename) return 'image';
        let base = filename.replace(/\.[^/.]+$/, ''); // remove extension
        base = base.replace(/^\d+[-_]/, ''); // remove leading timestamp like 1787364640-
        base = base.replace(/[-_]+/g, ' ').trim();
        return base || 'image';
    }

    // Helper: Clipboard Copy
    async function copyText(text, btn, successMsg = 'Copied! ✓') {
        try {
            if (navigator.clipboard && window.isSecureContext) {
                await navigator.clipboard.writeText(text);
            } else {
                const ta = document.createElement('textarea');
                ta.value = text;
                ta.style.position = 'fixed';
                ta.style.opacity = '0';
                document.body.appendChild(ta);
                ta.focus();
                ta.select();
                document.execCommand('copy');
                document.body.removeChild(ta);
            }

            if (btn) {
                const orig = btn.textContent;
                btn.textContent = successMsg;
                btn.classList.add('btn-copied');
                setTimeout(() => {
                    btn.textContent = orig;
                    btn.classList.remove('btn-copied');
                }, 2000);
            }
        } catch (err) {
            console.error('Clipboard copy failed:', err);
            prompt('Copy image link manually:', text);
        }
    }

    // Helper: Check if an active document editor is open
    function isEditorOpen() {
        const inlineEditor = document.getElementById('inline-editor-container');
        if (inlineEditor && inlineEditor.style.display !== 'none') {
            return true;
        }
        const htmlEditorArea = document.getElementById('edit-html-textarea');
        if (htmlEditorArea && htmlEditorArea.offsetParent !== null) {
            return true;
        }
        return false;
    }

    // Helper: Insert text into active document editor
    function insertIntoActiveEditor(text) {
        // 1. Toast UI Markdown editor
        const tuiEditor = window.tuiEditorInstance || window.toastEditorInstance;
        const inlineEditor = document.getElementById('inline-editor-container');
        if (tuiEditor && inlineEditor && inlineEditor.style.display !== 'none') {
            tuiEditor.insertText(text + '\n');
            modalGallery?.classList.remove('open');
            modalPreview?.classList.remove('open');
            return true;
        }

        // 2. Raw Markdown textarea
        const rawMdTextarea = document.getElementById('md-content-textarea');
        if (rawMdTextarea && rawMdTextarea.offsetParent !== null) {
            rawMdTextarea.value += '\n' + text;
            modalGallery?.classList.remove('open');
            modalPreview?.classList.remove('open');
            return true;
        }

        // 3. SunEditor (HTML Page Extension)
        const sunEditor = window.editEditor || window.createEditor;
        if (sunEditor && typeof sunEditor.insertHTML === 'function') {
            sunEditor.insertHTML(text);
            modalGallery?.classList.remove('open');
            modalPreview?.classList.remove('open');
            return true;
        }

        return false;
    }

    // Open Gallery Modal
    if (btnOpen && modalGallery) {
        btnOpen.addEventListener('click', (e) => {
            e.preventDefault();
            modalGallery.classList.add('open');
            loadGallery();
        });
    }

    // Fetch Images from Backend
    async function loadGallery() {
        if (isFetching) return;
        isFetching = true;

        if (galleryLoading) galleryLoading.style.display = 'flex';
        if (galleryEmpty) galleryEmpty.style.display = 'none';
        if (galleryGrid) galleryGrid.style.display = 'none';

        try {
            const res = await fetch('api/admin.php?action=ext_gallery_list');
            const data = await res.json();

            if (data.success && Array.isArray(data.images)) {
                allImages = data.images;
                applyFilterAndRender();
            } else {
                alert('Failed to load gallery images: ' + (data.error || 'Server error'));
                allImages = [];
                renderGrid([]);
            }
        } catch (err) {
            console.error('Error fetching gallery images:', err);
            alert('Could not fetch uploaded images. Check server connection.');
            renderGrid([]);
        } finally {
            isFetching = false;
            if (galleryLoading) galleryLoading.style.display = 'none';
        }
    }

    // Filter & Render
    function applyFilterAndRender() {
        const query = (searchInput?.value || '').trim().toLowerCase();
        if (searchClear) {
            searchClear.style.display = query.length > 0 ? 'block' : 'none';
        }

        if (!query) {
            currentFiltered = allImages;
        } else {
            currentFiltered = allImages.filter(img => {
                const nameMatch = (img.filename || '').toLowerCase().includes(query);
                const extMatch = (img.extension || '').toLowerCase().includes(query);
                const pathMatch = (img.path || '').toLowerCase().includes(query);
                return nameMatch || extMatch || pathMatch;
            });
        }

        if (countBadge) {
            countBadge.textContent = `${currentFiltered.length} of ${allImages.length} images`;
        }

        renderGrid(currentFiltered);
    }

    // Render Grid Cards
    function renderGrid(images) {
        if (!galleryGrid) return;
        galleryGrid.innerHTML = '';

        if (!images || images.length === 0) {
            if (galleryEmpty) galleryEmpty.style.display = 'flex';
            galleryGrid.style.display = 'none';
            return;
        }

        if (galleryEmpty) galleryEmpty.style.display = 'none';
        galleryGrid.style.display = 'grid';

        images.forEach(img => {
            const card = document.createElement('div');
            card.className = 'gallery-card';
            card.dataset.path = img.path;

            const altText = cleanAltFromFilename(img.filename);
            const mdSnippet = `![${altText}](${img.url})`;
            const htmlSnippet = `<img src="${img.url}" alt="${altText}">`;

            card.innerHTML = `
                <div class="gallery-card-thumb-wrap" title="Click to view full preview">
                    <img class="gallery-card-thumb" src="${escapeHtml(img.url)}" alt="${escapeHtml(altText)}" loading="lazy">
                    <div class="gallery-card-overlay">
                        <button type="button" class="btn btn-outline btn-sm gallery-overlay-btn btn-copy-md" data-copy-md="${escapeHtml(mdSnippet)}">
                            📋 Copy MD
                        </button>
                        <button type="button" class="btn btn-outline btn-sm gallery-overlay-btn btn-copy-html" data-copy-html="${escapeHtml(htmlSnippet)}">
                            📋 Copy HTML
                        </button>
                    </div>
                </div>
                <div class="gallery-card-meta">
                    <span class="gallery-card-title" title="${escapeHtml(img.filename)}">${escapeHtml(img.filename)}</span>
                    <div class="gallery-card-info">
                        <span>${escapeHtml(img.sizeFormatted)}</span>
                        <span>${escapeHtml(img.dimensions)}</span>
                    </div>
                    <div class="gallery-card-actions">
                        <button type="button" class="btn btn-outline btn-sm btn-action-copy-md" title="Copy Markdown Link: ${escapeHtml(mdSnippet)}">
                            Copy MD
                        </button>
                        <button type="button" class="btn btn-outline btn-sm btn-action-copy-html" title="Copy HTML Link: ${escapeHtml(htmlSnippet)}">
                            Copy HTML
                        </button>
                        <button type="button" class="btn btn-outline btn-sm gallery-btn-delete" title="Delete image">
                            🗑️
                        </button>
                    </div>
                </div>
            `;

            // Card click handlers
            const thumbWrap = card.querySelector('.gallery-card-thumb-wrap');
            thumbWrap.addEventListener('click', (e) => {
                // If user clicked one of the overlay copy buttons, don't open preview
                if (e.target.closest('.btn-copy-md') || e.target.closest('.btn-copy-html')) {
                    return;
                }
                openPreviewModal(img);
            });

            // Quick Copy MD (Overlay)
            const btnOverlayMd = card.querySelector('.btn-copy-md');
            btnOverlayMd.addEventListener('click', (e) => {
                e.stopPropagation();
                copyText(mdSnippet, btnOverlayMd);
            });

            // Quick Copy HTML (Overlay)
            const btnOverlayHtml = card.querySelector('.btn-copy-html');
            btnOverlayHtml.addEventListener('click', (e) => {
                e.stopPropagation();
                copyText(htmlSnippet, btnOverlayHtml);
            });

            // Action Bar Copy MD
            const btnActionMd = card.querySelector('.btn-action-copy-md');
            btnActionMd.addEventListener('click', (e) => {
                e.stopPropagation();
                copyText(mdSnippet, btnActionMd);
            });

            // Action Bar Copy HTML
            const btnActionHtml = card.querySelector('.btn-action-copy-html');
            btnActionHtml.addEventListener('click', (e) => {
                e.stopPropagation();
                copyText(htmlSnippet, btnActionHtml);
            });

            // Delete Button
            const btnDelete = card.querySelector('.gallery-btn-delete');
            btnDelete.addEventListener('click', (e) => {
                e.stopPropagation();
                startDeleteFlow(img);
            });

            galleryGrid.appendChild(card);
        });
    }

    // -------------------------------------------------------------
    // Delete Flow with Document Usage Check & Explicit Warnings
    // -------------------------------------------------------------
    async function startDeleteFlow(img) {
        selectedImageForDelete = img;

        if (!modalDelete) return;

        // Reset UI state
        if (deleteThumb) deleteThumb.src = img.url;
        if (deleteFilename) deleteFilename.textContent = img.filename;
        if (deleteMetaInfo) deleteMetaInfo.textContent = `${img.sizeFormatted} • ${img.dimensions}`;

        if (deleteWarningBox) deleteWarningBox.style.display = 'none';
        if (deleteSafeBox) deleteSafeBox.style.display = 'none';
        if (deleteUsedDocList) deleteUsedDocList.innerHTML = '';
        if (deleteChecking) deleteChecking.style.display = 'flex';
        if (deleteConfirm) {
            deleteConfirm.disabled = true;
            deleteConfirm.textContent = 'Checking Usage...';
            deleteConfirm.classList.remove('btn-danger');
            deleteConfirm.classList.add('btn-outline');
        }

        modalDelete.classList.add('open');

        try {
            const res = await fetch(`api/admin.php?action=ext_gallery_check_usage&file=${encodeURIComponent(img.path)}`);
            const data = await res.json();

            if (deleteChecking) deleteChecking.style.display = 'none';

            if (!data.success) {
                alert('Warning check failed: ' + (data.error || 'Server error'));
                modalDelete.classList.remove('open');
                return;
            }

            if (data.used && data.documents && data.documents.length > 0) {
                // Image is USED in documents! Warn the user explicitly!
                if (deleteWarningBox) deleteWarningBox.style.display = 'block';
                if (deleteSafeBox) deleteSafeBox.style.display = 'none';

                if (deleteWarningHeadline) {
                    deleteWarningHeadline.textContent = `⚠️ Explicit Warning: This image is used in ${data.count} location(s)!`;
                }

                if (deleteUsedDocList) {
                    deleteUsedDocList.innerHTML = '';
                    data.documents.forEach(doc => {
                        const item = document.createElement('div');
                        item.className = 'gallery-used-doc-item';

                        const snippetHtml = (doc.snippets && doc.snippets.length > 0)
                            ? `<div class="gallery-used-doc-snippet" title="Line ${doc.snippets[0].line}: ${escapeHtml(doc.snippets[0].text)}">Line ${doc.snippets[0].line}: ${escapeHtml(doc.snippets[0].text)}</div>`
                            : '';

                        item.innerHTML = `
                            <div class="gallery-used-doc-header">
                                <span>📄 ${escapeHtml(doc.title)}</span>
                                <span class="doc-badge badge-sm">${escapeHtml(doc.book || 'Document')}</span>
                            </div>
                            <div class="gallery-used-doc-file">${escapeHtml(doc.file)}</div>
                            ${snippetHtml}
                        `;
                        deleteUsedDocList.appendChild(item);
                    });
                }

                if (deleteConfirm) {
                    deleteConfirm.disabled = false;
                    deleteConfirm.textContent = 'Yes, Delete Anyway';
                    deleteConfirm.classList.remove('btn-outline');
                    deleteConfirm.classList.add('btn-danger');
                }
            } else {
                // Image is NOT used in any document
                if (deleteWarningBox) deleteWarningBox.style.display = 'none';
                if (deleteSafeBox) deleteSafeBox.style.display = 'block';

                if (deleteConfirm) {
                    deleteConfirm.disabled = false;
                    deleteConfirm.textContent = 'Delete Image';
                    deleteConfirm.classList.remove('btn-outline');
                    deleteConfirm.classList.add('btn-danger');
                }
            }
        } catch (err) {
            console.error('Error checking image document usage:', err);
            if (deleteChecking) deleteChecking.style.display = 'none';
            alert('Failed to check document usage. Deletion cancelled for safety.');
            modalDelete.classList.remove('open');
        }
    }

    // Execute Deletion
    if (deleteConfirm) {
        deleteConfirm.addEventListener('click', async () => {
            if (!selectedImageForDelete) return;

            deleteConfirm.disabled = true;
            deleteConfirm.textContent = 'Deleting...';

            try {
                const formData = new FormData();
                formData.append('action', 'ext_gallery_delete');
                formData.append('file', selectedImageForDelete.path);

                const res = await fetch('api/admin.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await res.json();

                if (data.success) {
                    // Close delete modal
                    modalDelete?.classList.remove('open');

                    // If preview modal was showing this image, close it
                    if (selectedImageForPreview && selectedImageForPreview.path === selectedImageForDelete.path) {
                        modalPreview?.classList.remove('open');
                        selectedImageForPreview = null;
                    }

                    // Remove from client array
                    allImages = allImages.filter(img => img.path !== selectedImageForDelete.path);
                    applyFilterAndRender();
                    selectedImageForDelete = null;
                } else {
                    alert('Deletion failed: ' + (data.error || 'Server error'));
                    deleteConfirm.disabled = false;
                    deleteConfirm.textContent = 'Delete Image';
                }
            } catch (err) {
                console.error('Error deleting image:', err);
                alert('Request failed while deleting image.');
                deleteConfirm.disabled = false;
                deleteConfirm.textContent = 'Delete Image';
            }
        });
    }

    // Cancel / Close Deletion Modal
    if (deleteClose) deleteClose.addEventListener('click', () => modalDelete?.classList.remove('open'));
    if (deleteCancel) deleteCancel.addEventListener('click', () => modalDelete?.classList.remove('open'));

    // -------------------------------------------------------------
    // Full Preview & Link Inspector Modal
    // -------------------------------------------------------------
    function openPreviewModal(img) {
        selectedImageForPreview = img;
        if (!modalPreview) return;

        if (previewImg) previewImg.src = img.url;
        if (previewTitle) previewTitle.textContent = img.filename;
        if (previewMetaDim) previewMetaDim.textContent = img.dimensions;
        if (previewMetaSize) previewMetaSize.textContent = img.sizeFormatted;
        if (previewMetaDate) previewMetaDate.textContent = img.dateFormatted;

        const defaultAlt = cleanAltFromFilename(img.filename);
        if (previewAltInput) previewAltInput.value = defaultAlt;

        updatePreviewSnippets(img.url, defaultAlt);

        // Check if editor is open on the page
        if (btnPreviewInsertEditor) {
            if (isEditorOpen()) {
                btnPreviewInsertEditor.style.display = 'inline-block';
            } else {
                btnPreviewInsertEditor.style.display = 'none';
            }
        }

        modalPreview.classList.add('open');
    }

    function updatePreviewSnippets(url, alt) {
        const cleanAlt = (alt || '').trim();
        if (previewMdInput) previewMdInput.value = `![${cleanAlt}](${url})`;
        if (previewHtmlInput) previewHtmlInput.value = `<img src="${url}" alt="${cleanAlt}">`;
    }

    if (previewAltInput) {
        previewAltInput.addEventListener('input', () => {
            if (selectedImageForPreview) {
                updatePreviewSnippets(selectedImageForPreview.url, previewAltInput.value);
            }
        });
    }

    // Preview Copy MD
    if (btnCopyPreviewMd && previewMdInput) {
        btnCopyPreviewMd.addEventListener('click', () => {
            copyText(previewMdInput.value, btnCopyPreviewMd);
        });
    }

    // Preview Copy HTML
    if (btnCopyPreviewHtml && previewHtmlInput) {
        btnCopyPreviewHtml.addEventListener('click', () => {
            copyText(previewHtmlInput.value, btnCopyPreviewHtml);
        });
    }

    // Preview Open in New Tab
    if (btnPreviewOpenNewTab) {
        btnPreviewOpenNewTab.addEventListener('click', () => {
            if (selectedImageForPreview) {
                window.open(selectedImageForPreview.url, '_blank');
            }
        });
    }

    // Preview Insert into Document Editor
    if (btnPreviewInsertEditor && previewMdInput && previewHtmlInput) {
        btnPreviewInsertEditor.addEventListener('click', () => {
            // Prefer markdown snippet if markdown editor is open, otherwise HTML
            const textToInsert = isEditorOpen() ? previewMdInput.value : previewHtmlInput.value;
            const success = insertIntoActiveEditor(textToInsert);
            if (!success) {
                copyText(textToInsert, btnPreviewInsertEditor, 'Copied! ✓');
                alert('Copied link to clipboard! Open the editor and paste where desired.');
            }
        });
    }

    // Preview Delete trigger
    if (btnPreviewDelete) {
        btnPreviewDelete.addEventListener('click', () => {
            if (selectedImageForPreview) {
                startDeleteFlow(selectedImageForPreview);
            }
        });
    }

    if (previewClose) previewClose.addEventListener('click', () => modalPreview?.classList.remove('open'));

    // -------------------------------------------------------------
    // Upload & Drag-and-Drop Handling
    // -------------------------------------------------------------
    if (btnUploadTrigger && fileInput) {
        btnUploadTrigger.addEventListener('click', () => fileInput.click());
    }

    if (fileInput) {
        fileInput.addEventListener('change', () => {
            if (fileInput.files && fileInput.files[0]) {
                handleFileUpload(fileInput.files[0]);
                fileInput.value = '';
            }
        });
    }

    async function handleFileUpload(file) {
        const formData = new FormData();
        formData.append('action', 'ext_gallery_upload');
        formData.append('image', file);

        if (btnUploadTrigger) {
            btnUploadTrigger.disabled = true;
            btnUploadTrigger.innerHTML = '<span>⏳</span> Uploading...';
        }

        try {
            const res = await fetch('api/admin.php', {
                method: 'POST',
                body: formData
            });
            const data = await res.json();

            if (data.success && data.image) {
                // Add new image to top of array
                allImages.unshift(data.image);
                applyFilterAndRender();
            } else {
                alert('Upload failed: ' + (data.error || 'Server error'));
            }
        } catch (err) {
            console.error('Upload error:', err);
            alert('Upload request failed.');
        } finally {
            if (btnUploadTrigger) {
                btnUploadTrigger.disabled = false;
                btnUploadTrigger.innerHTML = '<span>⬆️</span> Upload Image';
            }
        }
    }

    // Drag and Drop
    if (galleryBody && dropzone) {
        let dragCounter = 0;

        galleryBody.addEventListener('dragenter', (e) => {
            e.preventDefault();
            dragCounter++;
            dropzone.classList.add('active');
        });

        galleryBody.addEventListener('dragleave', (e) => {
            e.preventDefault();
            dragCounter--;
            if (dragCounter <= 0) {
                dragCounter = 0;
                dropzone.classList.remove('active');
            }
        });

        galleryBody.addEventListener('dragover', (e) => {
            e.preventDefault();
        });

        galleryBody.addEventListener('drop', (e) => {
            e.preventDefault();
            dragCounter = 0;
            dropzone.classList.remove('active');

            if (e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files.length > 0) {
                handleFileUpload(e.dataTransfer.files[0]);
            }
        });
    }

    // -------------------------------------------------------------
    // Search Filter & Refresh Listeners
    // -------------------------------------------------------------
    if (searchInput) {
        searchInput.addEventListener('input', applyFilterAndRender);
    }

    if (searchClear && searchInput) {
        searchClear.addEventListener('click', () => {
            searchInput.value = '';
            applyFilterAndRender();
            searchInput.focus();
        });
    }

    if (btnRefresh) {
        btnRefresh.addEventListener('click', () => {
            loadGallery();
        });
    }

    // Backdrop click and Escape key listeners
    [modalGallery, modalDelete, modalPreview].forEach(modal => {
        if (!modal) return;
        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                modal.classList.remove('open');
            }
        });
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            if (modalDelete?.classList.contains('open')) {
                modalDelete.classList.remove('open');
            } else if (modalPreview?.classList.contains('open')) {
                modalPreview.classList.remove('open');
            } else if (modalGallery?.classList.contains('open')) {
                modalGallery.classList.remove('open');
            }
        }
    });
});
