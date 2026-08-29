/**
 * AI Visuals & Chart Generator Client Script
 */
document.addEventListener('DOMContentLoaded', () => {
    const btnOpen = document.getElementById('btn-util-ai_visuals');
    const modal = document.getElementById('modal-ai-visuals');
    const form = document.getElementById('form-ai-visuals');
    const previewSection = document.getElementById('ai-visual-preview-section');
    const previewContainer = document.getElementById('ai-visual-preview-container');
    const snippetInput = document.getElementById('ai-visual-markdown-snippet');
    const btnCopy = document.getElementById('btn-copy-ai-snippet');
    const btnInsert = document.getElementById('btn-insert-ai-visual');
    const generateBtn = document.getElementById('btn-generate-ai-visual');

    if (btnOpen && modal) {
        btnOpen.addEventListener('click', (e) => {
            e.preventDefault();
            modal.classList.add('open');
            // Check if toast editor or edit mode is active
            const editSection = document.getElementById('markdown-editor-wrapper');
            if (editSection && editSection.style.display !== 'none' && btnInsert) {
                btnInsert.style.display = 'inline-block';
            } else if (btnInsert) {
                btnInsert.style.display = 'none';
            }
        });
    }

    // Modal close triggers
    document.querySelectorAll('[data-close="modal-ai-visuals"]').forEach(btn => {
        btn.addEventListener('click', () => {
            modal?.classList.remove('open');
        });
    });

    if (form) {
        form.addEventListener('submit', (e) => {
            e.preventDefault();
            const formData = new FormData(form);
            formData.append('action', 'ext_ai_generate_visual');

            if (generateBtn) {
                generateBtn.disabled = true;
                generateBtn.textContent = 'Generating Visual...';
            }

            fetch('api/admin.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    if (previewSection) previewSection.style.display = 'block';
                    if (previewContainer) {
                        if (data.previewSvg) {
                            previewContainer.innerHTML = data.previewSvg;
                        } else if (data.url) {
                            previewContainer.innerHTML = `<img src="${data.url}" style="max-width:100%; height:auto;" alt="Generated Visual">`;
                        }
                    }
                    if (snippetInput) {
                        snippetInput.value = data.markdown || `![](${data.url})`;
                    }
                } else {
                    alert('Failed to generate visual: ' + (data.error || 'Unknown error'));
                }
            })
            .catch(err => {
                console.error(err);
                alert('Request failed. Please check network/server logs.');
            })
            .finally(() => {
                if (generateBtn) {
                    generateBtn.disabled = false;
                    generateBtn.textContent = 'Generate & Save';
                }
            });
        });
    }

    if (btnCopy && snippetInput) {
        btnCopy.addEventListener('click', () => {
            snippetInput.select();
            navigator.clipboard.writeText(snippetInput.value).then(() => {
                const orig = btnCopy.textContent;
                btnCopy.textContent = 'Copied! ✓';
                setTimeout(() => { btnCopy.textContent = orig; }, 2000);
            });
        });
    }

    if (btnInsert && snippetInput) {
        btnInsert.addEventListener('click', () => {
            const markdownToInsert = snippetInput.value + '\n';
            if (window.toastEditorInstance) {
                window.toastEditorInstance.insertText(markdownToInsert);
                if (modal) modal.classList.remove('open');
            } else {
                const textarea = document.getElementById('md-content-textarea');
                if (textarea) {
                    textarea.value += '\n' + markdownToInsert;
                    if (modal) modal.classList.remove('open');
                } else {
                    alert('Copied to clipboard instead!');
                    navigator.clipboard.writeText(markdownToInsert);
                }
            }
        });
    }
});
