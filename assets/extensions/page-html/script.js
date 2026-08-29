/**
 * HTML Page Extension Client Script
 */
document.addEventListener('DOMContentLoaded', () => {
    // File upload reader helper
    const uploadLink = document.getElementById('upload-html-link');
    const uploadInput = document.getElementById('html-file-upload-input');
    const textarea = document.getElementById('html-content-textarea');
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
                    if (textarea) textarea.value = e.target.result;
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

    if (form) {
        form.addEventListener('submit', (e) => {
            e.preventDefault();
            const formData = new FormData(form);
            formData.append('action', 'create_html');

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
});
