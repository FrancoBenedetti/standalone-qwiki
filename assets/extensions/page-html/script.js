/**
 * HTML Page Extension Client Script with SunEditor Integration
 */
document.addEventListener('DOMContentLoaded', () => {
    let createEditor = null;
    let editEditor = null;

    function getSunEditorOptions(height) {
        return {
            plugins: window.SUNEDITOR ? window.SUNEDITOR.plugins : [],
            buttonList: [
                ['undo', 'redo'],
                ['blockStyle', 'fontSize'],
                ['bold', 'underline', 'italic', 'strike'],
                ['fontColor', 'backgroundColor'],
                ['align', 'list', 'table'],
                ['link', 'image'],
                ['fullScreen', 'showBlocks', 'codeView'],
                ['preview']
            ],
            width: '100%',
            height: height || '320px',
            placeholder: 'Write or design HTML content here...',
            attributesWhitelist: { all: '*' }
        };
    }

    function initCreateEditor() {
        const textarea = document.getElementById('html-content-textarea');
        if (!textarea || createEditor || typeof window.SUNEDITOR === 'undefined') return;

        createEditor = window.SUNEDITOR.create(textarea, getSunEditorOptions('280px'));
    }

    function initEditEditor() {
        const textarea = document.getElementById('edit-html-textarea');
        if (!textarea || editEditor || typeof window.SUNEDITOR === 'undefined') return;

        editEditor = window.SUNEDITOR.create(textarea, getSunEditorOptions('400px'));
    }

    // Lazy load SunEditor when Add Document modal opens
    const chapterModal = document.getElementById('chapter-modal');
    if (chapterModal) {
        const observer = new MutationObserver(() => {
            if (chapterModal.classList.contains('open') || chapterModal.style.display !== 'none') {
                setTimeout(initCreateEditor, 100);
            }
        });
        observer.observe(chapterModal, { attributes: true, attributeFilter: ['class', 'style'] });
    }

    // Tab switch listener to refresh SunEditor layout
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            if (btn.getAttribute('data-tab') === 'tab-ext-html') {
                setTimeout(() => {
                    initCreateEditor();
                }, 50);
            }
        });
    });

    // File upload reader helper
    const uploadLink = document.getElementById('upload-html-link');
    const uploadInput = document.getElementById('html-file-upload-input');
    const form = document.getElementById('tab-ext-html');

    if (uploadLink && uploadInput) {
        uploadLink.addEventListener('click', (e) => {
            e.preventDefault();
            uploadInput.click();
        });

        uploadInput.addEventListener('change', () => {
            const file = uploadInput.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = (e) => {
                    const content = e.target.result;
                    initCreateEditor();
                    if (createEditor) {
                        createEditor.setContents(content);
                    } else {
                        const textarea = document.getElementById('html-content-textarea');
                        if (textarea) textarea.value = content;
                    }
                    const titleInput = form?.querySelector('input[name="title"]');
                    if (titleInput && !titleInput.value.trim()) {
                        const nameWithoutExt = file.name.replace(/\.[^/.]+$/, '');
                        titleInput.value = nameWithoutExt.replace(/[-_]/g, ' ');
                    }
                };
                reader.readAsText(file);
            }
        });
    }

    // Create Document Form Submission
    if (form) {
        form.addEventListener('submit', (e) => {
            e.preventDefault();
            const formData = new FormData(form);
            formData.append('action', 'create_html');

            if (createEditor) {
                formData.set('content', createEditor.getContents());
            }

            const submitBtn = form.querySelector('button[type="submit"]');
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.textContent = 'Creating...';
            }

            fetch('api/admin.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    const targetUrl = `${encodeURIComponent(data.bookId)}/${encodeURIComponent(data.slug)}`;
                    window.location.href = targetUrl;
                } else {
                    alert('Error creating HTML document: ' + (data.error || 'Unknown error'));
                    if (submitBtn) {
                        submitBtn.disabled = false;
                        submitBtn.textContent = 'Create HTML Document';
                    }
                }
            })
            .catch(err => {
                console.error(err);
                alert('Request failed. Please try again.');
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.textContent = 'Create HTML Document';
                }
            });
        });
    }

    // Edit Existing HTML Document
    const editBtn = document.getElementById('btn-edit-html-doc');
    const editModal = document.getElementById('edit-html-modal');
    const editForm = document.getElementById('edit-html-form');

    if (editBtn && editModal) {
        editBtn.addEventListener('click', () => {
            const file = editBtn.getAttribute('data-file');
            const title = editBtn.getAttribute('data-title');

            const titleSpan = document.getElementById('edit-html-modal-title');
            if (titleSpan) titleSpan.textContent = title;

            const fileInput = document.getElementById('edit-html-file');
            if (fileInput) fileInput.value = file;

            editModal.classList.add('open');
            initEditEditor();

            // Fetch latest content from disk
            const formData = new FormData();
            formData.append('action', 'get_html');
            formData.append('file', file);

            fetch('api/admin.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success && data.content) {
                    if (editEditor) {
                        editEditor.setContents(data.content);
                    } else {
                        const textarea = document.getElementById('edit-html-textarea');
                        if (textarea) textarea.value = data.content;
                    }
                }
            })
            .catch(err => console.error('Failed to load HTML content:', err));
        });
    }

    // Close Edit Modal on cancel / close buttons
    document.querySelectorAll('[data-close="edit-html-modal"]').forEach(btn => {
        btn.addEventListener('click', () => {
            editModal?.classList.remove('open');
        });
    });

    // Save Edited HTML Document
    if (editForm) {
        editForm.addEventListener('submit', (e) => {
            e.preventDefault();
            const formData = new FormData(editForm);
            formData.append('action', 'save_html');

            if (editEditor) {
                formData.set('content', editEditor.getContents());
            }

            const saveBtn = document.getElementById('btn-save-html-doc');
            if (saveBtn) {
                saveBtn.disabled = true;
                saveBtn.textContent = 'Saving...';
            }

            fetch('api/admin.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    editModal.classList.remove('open');
                    const iframe = document.getElementById('current-html-frame');
                    if (iframe) {
                        const baseSrc = iframe.src.split('?')[0];
                        iframe.src = baseSrc + '?t=' + Date.now();
                    }
                    if (saveBtn) {
                        saveBtn.disabled = false;
                        saveBtn.textContent = '💾 Save Changes';
                    }
                } else {
                    alert('Failed to save HTML document: ' + (data.error || 'Unknown error'));
                    if (saveBtn) {
                        saveBtn.disabled = false;
                        saveBtn.textContent = '💾 Save Changes';
                    }
                }
            })
            .catch(err => {
                console.error(err);
                alert('Failed to save document. Please try again.');
                if (saveBtn) {
                    saveBtn.disabled = false;
                    saveBtn.textContent = '💾 Save Changes';
                }
            });
        });
    }
});
