/**
 * HTML Page Extension Client Script with SunEditor Integration
 */
document.addEventListener('DOMContentLoaded', () => {
    let createEditor = null;
    let editEditor = null;

    function getSunEditorOptions(height) {
        return {
            buttonList: [
                ['undo', 'redo'],
                ['formatBlock', 'fontSize'],
                ['bold', 'underline', 'italic', 'strike'],
                ['fontColor', 'hiliteColor'],
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
        const toggle = document.getElementById('create-use-visual-editor');
        if (!textarea) return;

        // CDN fallback: if SunEditor failed to load, show raw textarea
        if (typeof window.SUNEDITOR === 'undefined') {
            textarea.style.display = 'block';
            if (toggle) { toggle.disabled = true; toggle.checked = false; }
            return;
        }

        if (toggle && !toggle.checked) {
            if (createEditor) {
                textarea.value = createEditor.getContents();
                createEditor.destroy();
                createEditor = null;
            }
            textarea.style.display = 'block';
            return;
        }

        if (createEditor) return;
        textarea.style.display = 'none';
        createEditor = window.SUNEDITOR.create(textarea, getSunEditorOptions('280px'));
    }

    function initEditEditor() {
        const textarea = document.getElementById('edit-html-textarea');
        const toggle = document.getElementById('edit-use-visual-editor');
        if (!textarea) return;

        // CDN fallback: if SunEditor failed to load, show raw textarea
        if (typeof window.SUNEDITOR === 'undefined') {
            textarea.style.display = 'block';
            if (toggle) { toggle.disabled = true; toggle.checked = false; }
            return;
        }

        if (toggle && !toggle.checked) {
            if (editEditor) {
                textarea.value = editEditor.getContents();
                editEditor.destroy();
                editEditor = null;
            }
            textarea.style.display = 'block';
            return;
        }

        if (editEditor) return;
        textarea.style.display = 'none';
        editEditor = window.SUNEDITOR.create(textarea, getSunEditorOptions('400px'));
    }

    // Toggle Listeners
    const createToggle = document.getElementById('create-use-visual-editor');
    if (createToggle) createToggle.addEventListener('change', initCreateEditor);

    const editToggle = document.getElementById('edit-use-visual-editor');
    if (editToggle) editToggle.addEventListener('change', initEditEditor);

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
                    
                    const hasScriptsOrStyles = /<(script|style|link)[\s>]/i.test(content);
                    const toggle = document.getElementById('create-use-visual-editor');
                    if (hasScriptsOrStyles && toggle) {
                        toggle.checked = false;
                        alert('This HTML file contains scripts, styles, or links. The Visual Editor has been disabled automatically to prevent these tags from being stripped.');
                    }
                    
                    initCreateEditor();
                    
                    const textarea = document.getElementById('html-content-textarea');
                    if (textarea) textarea.value = content;
                    
                    if (createEditor && toggle && toggle.checked) {
                        createEditor.setContents(content);
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

            let contentStr = '';
            const createToggle = document.getElementById('create-use-visual-editor');
            if (createEditor && createToggle && createToggle.checked) {
                contentStr = createEditor.getContents();
            } else {
                const textarea = document.getElementById('html-content-textarea');
                contentStr = textarea ? textarea.value : (formData.get('content') || '');
            }

            // Encode content fields as base64 to bypass Apache ModSecurity / WAF
            formData.delete('content');
            formData.append('content_base64', btoa(unescape(encodeURIComponent(contentStr))));

            const submitBtn = form.querySelector('button[type="submit"]');
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.textContent = 'Creating...';
            }

            fetch('api/admin.php', {
                method: 'POST',
                body: formData
            })
            .then(async res => {
                const text = await res.text();
                try {
                    return JSON.parse(text);
                } catch (e) {
                    if (!res.ok) {
                        throw new Error(`Server returned HTTP ${res.status}: ${res.statusText}`);
                    }
                    throw new Error('Invalid response from server');
                }
            })
            .then(data => {
                if (data.success) {
                    const base = document.querySelector('base')?.href || '/';
                    window.location.href = base + `${encodeURIComponent(data.bookId)}/${encodeURIComponent(data.slug)}`;
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
                alert('Request failed: ' + (err.message || 'Please try again.'));
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

    function openEditModal(file, title) {
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
                const hasScriptsOrStyles = /<(script|style|link)[\s>]/i.test(data.content);
                const toggle = document.getElementById('edit-use-visual-editor');
                
                if (hasScriptsOrStyles && toggle) {
                    toggle.checked = false;
                    alert('This HTML document contains scripts, styles, or links. The Visual Editor has been disabled automatically to prevent these tags from being stripped.');
                }
                
                initEditEditor();
                
                const textarea = document.getElementById('edit-html-textarea');
                if (textarea) textarea.value = data.content;
                
                if (editEditor && toggle && toggle.checked) {
                    editEditor.setContents(data.content);
                }
            }
        })
        .catch(err => console.error('Failed to load HTML content:', err));
    }

    if (editBtn && editModal) {
        editBtn.addEventListener('click', async () => {
            const file = editBtn.getAttribute('data-file');
            const title = editBtn.getAttribute('data-title');

            if (window.SoftLock && file) {
                const lockRes = await window.SoftLock.acquire(file, false);
                if (!lockRes.success) {
                    window.SoftLock.promptLockConflict(lockRes, async () => {
                        await window.SoftLock.acquire(file, true);
                        openEditModal(file, title);
                    }, () => {});
                    return;
                }
            }
            openEditModal(file, title);
        });
    }

    // Close Edit Modal on cancel / close buttons
    document.querySelectorAll('[data-close="edit-html-modal"]').forEach(btn => {
        btn.addEventListener('click', () => {
            editModal?.classList.remove('open');
            const fileInput = document.getElementById('edit-html-file');
            const file = fileInput ? fileInput.value : '';
            if (window.SoftLock && file) {
                window.SoftLock.release(file);
            }
        });
    });

    // Save Edited HTML Document
    if (editForm) {
        editForm.addEventListener('submit', (e) => {
            e.preventDefault();
            const formData = new FormData(editForm);
            formData.append('action', 'save_html');
            if (window.SoftLock) {
                formData.append('tab_id', window.SoftLock.getTabId());
            }

            let contentStr = '';
            const editToggle = document.getElementById('edit-use-visual-editor');
            if (editEditor && editToggle && editToggle.checked) {
                contentStr = editEditor.getContents();
            } else {
                const textarea = document.getElementById('edit-html-textarea');
                contentStr = textarea ? textarea.value : (formData.get('content') || '');
            }

            // Encode content fields as base64 to bypass Apache ModSecurity / WAF
            formData.delete('content');
            formData.append('content_base64', btoa(unescape(encodeURIComponent(contentStr))));

            const saveBtn = document.getElementById('btn-save-html-doc');
            if (saveBtn) {
                saveBtn.disabled = true;
                saveBtn.textContent = 'Saving...';
            }

            fetch('api/admin.php', {
                method: 'POST',
                body: formData
            })
            .then(async res => {
                const text = await res.text();
                try {
                    return JSON.parse(text);
                } catch (e) {
                    if (!res.ok) {
                        throw new Error(`Server returned HTTP ${res.status}: ${res.statusText}`);
                    }
                    throw new Error('Invalid response from server');
                }
            })
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
                    if (data.code === 'LOCKED_BY_OTHER') {
                        alert('Save Blocked: ' + (data.error || 'The document lock belongs to another session.'));
                    } else {
                        alert('Failed to save HTML document: ' + (data.error || 'Unknown error'));
                    }
                    if (saveBtn) {
                        saveBtn.disabled = false;
                        saveBtn.textContent = '💾 Save Changes';
                    }
                }
            })
            .catch(err => {
                console.error(err);
                alert('Failed to save document: ' + (err.message || 'Please try again.'));
                if (saveBtn) {
                    saveBtn.disabled = false;
                    saveBtn.textContent = '💾 Save Changes';
                }
            });
        });
    }
});
