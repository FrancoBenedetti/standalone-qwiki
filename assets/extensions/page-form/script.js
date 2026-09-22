/**
 * Standalone Qwiki - Native Flat-File Form Extension Client Script
 */
document.addEventListener('DOMContentLoaded', () => {
    // ------------------------------------------------------------------------
    // Template Presets
    // ------------------------------------------------------------------------
    const TEMPLATES = {
        feedback: {
            title: 'Feedback Survey',
            description: 'Help us improve Standalone Qwiki by sharing your feedback and ideas.',
            submitButtonText: 'Submit Feedback',
            successMessage: 'Thank you! Your feedback has been successfully received.',
            requireLogin: false,
            allowMultiple: true,
            fields: [
                { id: 'full_name', label: 'Your Name', type: 'text', placeholder: 'Jane Doe', required: true, help: '' },
                { id: 'email', label: 'Email Address', type: 'email', placeholder: 'you@example.com', required: true, help: 'We will only use this to follow up if needed.' },
                { id: 'category', label: 'Category', type: 'select', options: ['Documentation', 'Feature Request', 'Bug Report', 'General Feedback'], required: true },
                { id: 'satisfaction', label: 'Overall Satisfaction', type: 'radio', options: ['Very Satisfied', 'Satisfied', 'Neutral', 'Unsatisfied'], required: false },
                { id: 'features_used', label: 'What features do you use most?', type: 'checkbox', options: ['Markdown Docs', 'Interactive HTML', 'PDF Viewer', 'Google Docs'], required: false },
                { id: 'comments', label: 'Comments / Suggestions', type: 'textarea', placeholder: 'Tell us your thoughts...', required: true, help: '' }
            ]
        },
        contact: {
            title: 'Contact Us',
            description: 'Have a question or request? Send us a message and we will respond promptly.',
            submitButtonText: 'Send Message',
            successMessage: 'Thank you for contacting us! We will get back to you shortly.',
            requireLogin: false,
            allowMultiple: true,
            fields: [
                { id: 'name', label: 'Full Name', type: 'text', placeholder: 'Jane Doe', required: true },
                { id: 'email', label: 'Email Address', type: 'email', placeholder: 'you@example.com', required: true },
                { id: 'subject', label: 'Subject', type: 'text', placeholder: 'Inquiry about...', required: true },
                { id: 'message', label: 'Message', type: 'textarea', placeholder: 'Please describe your request in detail...', required: true }
            ]
        },
        rsvp: {
            title: 'Event RSVP',
            description: 'Please confirm your attendance for our upcoming session.',
            submitButtonText: 'Submit RSVP',
            successMessage: 'Thank you! Your RSVP has been confirmed.',
            requireLogin: false,
            allowMultiple: false,
            fields: [
                { id: 'attendee_name', label: 'Attendee Name', type: 'text', placeholder: 'Jane Doe', required: true },
                { id: 'email', label: 'Work Email', type: 'email', placeholder: 'you@company.com', required: true },
                { id: 'attending', label: 'Will you attend?', type: 'radio', options: ['Yes, I will attend in person', 'Yes, attending virtually', 'No, cannot attend'], required: true },
                { id: 'dietary', label: 'Dietary Preferences', type: 'select', options: ['None', 'Vegetarian', 'Vegan', 'Gluten-Free', 'Halal / Kosher', 'Other'], required: false },
                { id: 'notes', label: 'Special Accommodations or Notes', type: 'textarea', placeholder: 'Any questions or requirements...', required: false }
            ]
        },
        blank: {
            title: '',
            description: '',
            submitButtonText: 'Submit',
            successMessage: 'Thank you! Your response has been recorded.',
            requireLogin: false,
            allowMultiple: true,
            fields: [
                { id: 'name', label: 'Your Name', type: 'text', placeholder: 'e.g. John Doe', required: true }
            ]
        }
    };

    // ------------------------------------------------------------------------
    // Helper: Field Builder Component
    // ------------------------------------------------------------------------
    function createFieldBuilder(containerEl, initialFields, onChangeCallback) {
        let fields = Array.isArray(initialFields) ? JSON.parse(JSON.stringify(initialFields)) : [];

        function render() {
            containerEl.innerHTML = '';
            if (fields.length === 0) {
                containerEl.innerHTML = '<div style="text-align:center; padding: 1.5rem; color: var(--text-muted); font-size: 0.88rem;">No fields yet. Click "+ Add Field" above to create one.</div>';
                if (onChangeCallback) onChangeCallback(fields);
                return;
            }

            fields.forEach((f, idx) => {
                const card = document.createElement('div');
                card.className = 'form-field-card';
                card.dataset.index = idx;

                const hasOptions = ['select', 'radio', 'checkbox'].includes(f.type);
                const optionsStr = Array.isArray(f.options) ? f.options.join(', ') : (f.options || '');

                card.innerHTML = `
                    <div class="form-field-header">
                        <span>Field #${idx + 1}: <strong>${escapeHtml(f.label || 'Untitled')}</strong> (${f.type})</span>
                        <div class="form-field-actions">
                            ${idx > 0 ? `<button type="button" class="btn-move-up" title="Move Up">↑</button>` : ''}
                            ${idx < fields.length - 1 ? `<button type="button" class="btn-move-down" title="Move Down">↓</button>` : ''}
                            <button type="button" class="btn-remove-field" title="Remove Field">✕</button>
                        </div>
                    </div>
                    <div class="form-field-controls">
                        <div>
                            <label style="font-size: 0.8rem; display:block; margin-bottom: 0.2rem;">Field Label</label>
                            <input type="text" class="form-control fld-label" value="${escapeHtml(f.label || '')}" placeholder="Label">
                        </div>
                        <div>
                            <label style="font-size: 0.8rem; display:block; margin-bottom: 0.2rem;">Field Type</label>
                            <select class="form-control fld-type">
                                <option value="text" ${f.type === 'text' ? 'selected' : ''}>Text (Single Line)</option>
                                <option value="email" ${f.type === 'email' ? 'selected' : ''}>Email Address</option>
                                <option value="textarea" ${f.type === 'textarea' ? 'selected' : ''}>Textarea (Multi-Line)</option>
                                <option value="select" ${f.type === 'select' ? 'selected' : ''}>Dropdown (Select)</option>
                                <option value="radio" ${f.type === 'radio' ? 'selected' : ''}>Radio Buttons</option>
                                <option value="checkbox" ${f.type === 'checkbox' ? 'selected' : ''}>Checkboxes</option>
                                <option value="tel" ${f.type === 'tel' ? 'selected' : ''}>Phone Number</option>
                                <option value="number" ${f.type === 'number' ? 'selected' : ''}>Number</option>
                                <option value="date" ${f.type === 'date' ? 'selected' : ''}>Date</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-field-controls" style="margin-top: 0.5rem;">
                        <div>
                            <label style="font-size: 0.8rem; display:block; margin-bottom: 0.2rem;">Placeholder / Hint</label>
                            <input type="text" class="form-control fld-placeholder" value="${escapeHtml(f.placeholder || '')}" placeholder="Optional placeholder">
                        </div>
                        <div style="display: flex; align-items: flex-end; padding-bottom: 0.5rem;">
                            <label style="cursor: pointer; font-size: 0.88rem; user-select: none;">
                                <input type="checkbox" class="fld-required" ${f.required ? 'checked' : ''}> Required Field
                            </label>
                        </div>
                    </div>
                    <div class="fld-options-wrap" style="margin-top: 0.5rem; display: ${hasOptions ? 'block' : 'none'};">
                        <label style="font-size: 0.8rem; display:block; margin-bottom: 0.2rem;">Options <span class="text-muted">(comma-separated)</span></label>
                        <input type="text" class="form-control fld-options" value="${escapeHtml(optionsStr)}" placeholder="e.g. Option 1, Option 2, Option 3">
                    </div>
                `;

                // Event Listeners for controls
                const labelInput = card.querySelector('.fld-label');
                const typeSelect = card.querySelector('.fld-type');
                const placeholderInput = card.querySelector('.fld-placeholder');
                const requiredCheck = card.querySelector('.fld-required');
                const optionsInput = card.querySelector('.fld-options');
                const optionsWrap = card.querySelector('.fld-options-wrap');

                labelInput.addEventListener('input', () => {
                    f.label = labelInput.value.trim();
                    if (!f.id || f.id.startsWith('field_')) {
                        f.id = slugify(f.label) || f.id;
                    }
                    if (onChangeCallback) onChangeCallback(fields);
                });

                typeSelect.addEventListener('change', () => {
                    f.type = typeSelect.value;
                    const needsOpts = ['select', 'radio', 'checkbox'].includes(f.type);
                    optionsWrap.style.display = needsOpts ? 'block' : 'none';
                    if (needsOpts && (!f.options || f.options.length === 0)) {
                        f.options = ['Option 1', 'Option 2'];
                        optionsInput.value = f.options.join(', ');
                    }
                    if (onChangeCallback) onChangeCallback(fields);
                });

                placeholderInput.addEventListener('input', () => {
                    f.placeholder = placeholderInput.value;
                    if (onChangeCallback) onChangeCallback(fields);
                });

                requiredCheck.addEventListener('change', () => {
                    f.required = requiredCheck.checked;
                    if (onChangeCallback) onChangeCallback(fields);
                });

                optionsInput.addEventListener('input', () => {
                    f.options = optionsInput.value.split(',').map(s => s.trim()).filter(s => s.length > 0);
                    if (onChangeCallback) onChangeCallback(fields);
                });

                // Move / Delete
                const btnUp = card.querySelector('.btn-move-up');
                if (btnUp) {
                    btnUp.addEventListener('click', () => {
                        const temp = fields[idx - 1];
                        fields[idx - 1] = fields[idx];
                        fields[idx] = temp;
                        render();
                    });
                }

                const btnDown = card.querySelector('.btn-move-down');
                if (btnDown) {
                    btnDown.addEventListener('click', () => {
                        const temp = fields[idx + 1];
                        fields[idx + 1] = fields[idx];
                        fields[idx] = temp;
                        render();
                    });
                }

                const btnRemove = card.querySelector('.btn-remove-field');
                btnRemove.addEventListener('click', () => {
                    fields.splice(idx, 1);
                    render();
                });

                containerEl.appendChild(card);
            });

            if (onChangeCallback) onChangeCallback(fields);
        }

        render();

        return {
            getFields: () => fields,
            setFields: (newFields) => {
                fields = Array.isArray(newFields) ? JSON.parse(JSON.stringify(newFields)) : [];
                render();
            },
            addField: () => {
                const newId = 'field_' + Math.floor(Math.random() * 10000);
                fields.push({
                    id: newId,
                    label: 'New Question',
                    type: 'text',
                    placeholder: '',
                    required: false
                });
                render();
            }
        };
    }

    function escapeHtml(str) {
        if (str === null || str === undefined) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function slugify(text) {
        return text.toString().toLowerCase()
            .replace(/\s+/g, '_')
            .replace(/[^\w\-]+/g, '')
            .replace(/\-\-+/g, '_')
            .replace(/^-+/, '')
            .replace(/-+$/, '');
    }

    // ------------------------------------------------------------------------
    // 1. "Add Document" Modal Integration
    // ------------------------------------------------------------------------
    const createFormTab = document.getElementById('tab-ext-form');
    if (createFormTab) {
        const titleInput = document.getElementById('form-create-title');
        const descInput = document.getElementById('form-create-desc');
        const templateSelect = document.getElementById('form-create-template-select');
        const submitTextInput = document.getElementById('form-create-submit-text');
        const successMsgInput = document.getElementById('form-create-success-msg');
        const requireLoginCheck = document.getElementById('form-create-require-login');
        const allowMultipleCheck = document.getElementById('form-create-allow-multiple');
        const notifyEmailInput = document.getElementById('form-create-notify-email');
        const webhookUrlInput = document.getElementById('form-create-webhook-url');
        const schemaJsonInput = document.getElementById('form-create-schema-json');
        const builderContainer = document.getElementById('form-builder-fields-create');
        const btnAddField = document.getElementById('btn-add-field-create');

        let currentFields = [];

        function syncCreateSchema() {
            const schema = {
                title: (titleInput ? titleInput.value.trim() : '') || 'Form',
                description: descInput ? descInput.value.trim() : '',
                submitButtonText: (submitTextInput ? submitTextInput.value.trim() : '') || 'Submit',
                successMessage: (successMsgInput ? successMsgInput.value.trim() : '') || 'Thank you! Your response has been recorded.',
                requireLogin: requireLoginCheck ? requireLoginCheck.checked : false,
                allowMultiple: allowMultipleCheck ? allowMultipleCheck.checked : true,
                notificationEmail: notifyEmailInput ? notifyEmailInput.value.trim() : '',
                webhookUrl: webhookUrlInput ? webhookUrlInput.value.trim() : '',
                fields: currentFields
            };
            if (schemaJsonInput) {
                schemaJsonInput.value = JSON.stringify(schema);
            }
        }

        const builder = createFieldBuilder(builderContainer, TEMPLATES.feedback.fields, (fields) => {
            currentFields = fields;
            syncCreateSchema();
        });

        // Initialize with default Feedback Survey template
        if (titleInput && !titleInput.value) titleInput.value = TEMPLATES.feedback.title;
        if (descInput && !descInput.value) descInput.value = TEMPLATES.feedback.description;
        syncCreateSchema();

        if (templateSelect) {
            templateSelect.addEventListener('change', () => {
                const tpl = TEMPLATES[templateSelect.value];
                if (tpl) {
                    if (titleInput) titleInput.value = tpl.title;
                    if (descInput) descInput.value = tpl.description;
                    if (submitTextInput) submitTextInput.value = tpl.submitButtonText || 'Submit';
                    if (successMsgInput) successMsgInput.value = tpl.successMessage || 'Thank you!';
                    if (requireLoginCheck) requireLoginCheck.checked = !!tpl.requireLogin;
                    if (allowMultipleCheck) allowMultipleCheck.checked = tpl.allowMultiple !== false;
                    builder.setFields(tpl.fields || []);
                    syncCreateSchema();
                }
            });
        }

        if (btnAddField) {
            btnAddField.addEventListener('click', () => {
                builder.addField();
            });
        }

        if (titleInput) titleInput.addEventListener('input', syncCreateSchema);
        if (descInput) descInput.addEventListener('input', syncCreateSchema);
        if (submitTextInput) submitTextInput.addEventListener('input', syncCreateSchema);
        if (successMsgInput) successMsgInput.addEventListener('input', syncCreateSchema);
        if (requireLoginCheck) requireLoginCheck.addEventListener('change', syncCreateSchema);
        if (allowMultipleCheck) allowMultipleCheck.addEventListener('change', syncCreateSchema);
        if (notifyEmailInput) notifyEmailInput.addEventListener('input', syncCreateSchema);
        if (webhookUrlInput) webhookUrlInput.addEventListener('input', syncCreateSchema);

        // Submit form creation
        createFormTab.addEventListener('submit', (e) => {
            e.preventDefault();
            syncCreateSchema();

            const submitBtn = document.getElementById('btn-submit-create-form');
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.textContent = 'Creating Form Document...';
            }

            const formData = new FormData(createFormTab);
            formData.append('action', 'create_form');

            fetch('api/admin.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    window.location.href = `${encodeURIComponent(data.bookId)}/${encodeURIComponent(data.slug)}`;
                } else {
                    alert('Error creating form: ' + (data.error || 'Unknown error'));
                    if (submitBtn) {
                        submitBtn.disabled = false;
                        submitBtn.textContent = '📝 Create Form Document';
                    }
                }
            })
            .catch(err => {
                alert('Network error while creating form: ' + err.message);
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.textContent = '📝 Create Form Document';
                }
            });
        });
    }

    // ------------------------------------------------------------------------
    // 2. Public / Reader Form Submission
    // ------------------------------------------------------------------------
    const renderedForm = document.getElementById('qwiki-rendered-form');
    if (renderedForm) {
        const feedbackArea = document.getElementById('form-feedback-area');
        const submitBtn = document.getElementById('btn-submit-qwiki-form');
        const originalBtnText = submitBtn ? submitBtn.textContent : 'Submit';

        renderedForm.addEventListener('submit', (e) => {
            e.preventDefault();

            // Client-side HTML5 validity check
            if (!renderedForm.checkValidity()) {
                renderedForm.reportValidity();
                return;
            }

            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.classList.add('btn-submitting');
                submitBtn.textContent = 'Submitting response...';
            }

            if (feedbackArea) {
                feedbackArea.style.display = 'none';
                feedbackArea.innerHTML = '';
            }

            const formData = new FormData(renderedForm);

            fetch('api/admin.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    // Success card view
                    const card = renderedForm.closest('.form-card');
                    if (card) {
                        card.innerHTML = `
                            <div class="form-success-card">
                                <div class="form-success-icon">✅</div>
                                <h2>Response Recorded</h2>
                                <p>${escapeHtml(data.message || 'Thank you! Your response has been received.')}</p>
                                <button type="button" class="btn btn-outline" id="btn-submit-another">Submit another response</button>
                            </div>
                        `;
                        const anotherBtn = card.querySelector('#btn-submit-another');
                        if (anotherBtn) {
                            anotherBtn.addEventListener('click', () => {
                                window.location.reload();
                            });
                        }
                    }

                    // Dynamically increment admin response count badge if visible
                    const countValEl = document.getElementById('form-count-val');
                    if (countValEl) {
                        const cur = parseInt(countValEl.textContent || '0', 10);
                        countValEl.textContent = (cur + 1).toString();
                    }
                } else {
                    if (feedbackArea) {
                        feedbackArea.style.display = 'block';
                        feedbackArea.className = 'form-alert form-alert-danger';
                        feedbackArea.innerHTML = `<span class="form-alert-icon">⚠️</span><div>${escapeHtml(data.error || 'Submission failed. Please check your inputs.')}</div>`;
                    }
                    if (submitBtn) {
                        submitBtn.disabled = false;
                        submitBtn.classList.remove('btn-submitting');
                        submitBtn.textContent = originalBtnText;
                    }
                }
            })
            .catch(err => {
                if (feedbackArea) {
                    feedbackArea.style.display = 'block';
                    feedbackArea.className = 'form-alert form-alert-danger';
                    feedbackArea.innerHTML = `<span class="form-alert-icon">⚠️</span><div>Network error submitting response: ${escapeHtml(err.message)}</div>`;
                }
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.classList.remove('btn-submitting');
                    submitBtn.textContent = originalBtnText;
                }
            });
        });
    }

    // ------------------------------------------------------------------------
    // 3. Admin Submissions Viewer Modal
    // ------------------------------------------------------------------------
    const btnViewSubmissions = document.getElementById('btn-view-form-submissions');
    const modalSubmissions = document.getElementById('modal-form-submissions');
    if (btnViewSubmissions && modalSubmissions) {
        const modalTitle = document.getElementById('modal-form-submissions-title');
        const closeBtn = document.getElementById('btn-close-submissions-modal');
        const refreshBtn = document.getElementById('btn-refresh-submissions');
        const clearBtn = document.getElementById('btn-clear-all-submissions');
        const searchInput = document.getElementById('submissions-search-input');
        const contentArea = document.getElementById('form-submissions-content');

        const relFile = btnViewSubmissions.dataset.file;
        let cachedData = null;

        function loadSubmissions() {
            contentArea.innerHTML = '<div style="text-align:center; padding: 2rem; color: var(--text-muted);">Loading submissions...</div>';
            
            fetch(`api/admin.php?action=ext_form_get_submissions&file=${encodeURIComponent(relFile)}`)
                .then(res => res.json())
                .then(res => {
                    if (res.success) {
                        cachedData = res;
                        renderSubmissionsTable(res.submissions || [], res.schema || {});
                    } else {
                        contentArea.innerHTML = `<div class="form-alert form-alert-danger">${escapeHtml(res.error || 'Failed to load submissions')}</div>`;
                    }
                })
                .catch(err => {
                    contentArea.innerHTML = `<div class="form-alert form-alert-danger">Network error: ${escapeHtml(err.message)}</div>`;
                });
        }

        function renderSubmissionsTable(items, schema) {
            const query = (searchInput ? searchInput.value.trim().toLowerCase() : '');
            const fields = schema.fields || [];

            const filtered = items.filter(item => {
                if (!query) return true;
                const matchTime = (item.created_at || '').toLowerCase().includes(query);
                const matchUser = (item.submitted_by || '').toLowerCase().includes(query);
                const matchData = Object.values(item.data || {}).some(val => {
                    if (Array.isArray(val)) return val.join(' ').toLowerCase().includes(query);
                    return String(val).toLowerCase().includes(query);
                });
                return matchTime || matchUser || matchData;
            });

            if (modalTitle) {
                modalTitle.textContent = `Submissions: ${schema.title || 'Form'} (${items.length} ${items.length === 1 ? 'response' : 'responses'})`;
            }

            if (filtered.length === 0) {
                contentArea.innerHTML = `<div class="form-submission-empty">${query ? 'No matching responses found for "' + escapeHtml(query) + '"' : 'No responses submitted yet.'}</div>`;
                return;
            }

            let html = `
                <table class="form-submissions-table">
                    <thead>
                        <tr>
                            <th style="width: 140px;">Date / Time</th>
                            <th style="width: 100px;">User</th>
                            ${fields.map(f => `<th>${escapeHtml(f.label || f.id)}</th>`).join('')}
                            <th style="width: 45px; text-align: center;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
            `;

            filtered.forEach(sub => {
                const subData = sub.data || {};
                html += `
                    <tr data-id="${escapeHtml(sub.id)}">
                        <td style="font-size: 0.82rem; color: var(--text-muted);">${escapeHtml(sub.created_at || '')}</td>
                        <td><span class="badge" style="font-size: 0.75rem;">${escapeHtml(sub.submitted_by || 'guest')}</span></td>
                        ${fields.map(f => {
                            const val = subData[f.id] ?? '';
                            const displayVal = Array.isArray(val) ? val.join(', ') : String(val);
                            return `<td>${escapeHtml(displayVal)}</td>`;
                        }).join('')}
                        <td style="text-align: center;">
                            <button type="button" class="btn-delete-submission" data-id="${escapeHtml(sub.id)}" title="Delete this response" style="background:none; border:none; color: #ef4444; cursor:pointer; font-size: 1rem; padding: 0.2rem;">🗑️</button>
                        </td>
                    </tr>
                `;
            });

            html += `</tbody></table>`;
            contentArea.innerHTML = html;

            // Wire delete buttons
            contentArea.querySelectorAll('.btn-delete-submission').forEach(btn => {
                btn.addEventListener('click', () => {
                    const subId = btn.dataset.id;
                    if (confirm('Are you sure you want to permanently delete this response?')) {
                        const formData = new FormData();
                        formData.append('action', 'ext_form_delete_submission');
                        formData.append('file', relFile);
                        formData.append('id', subId);

                        fetch('api/admin.php', { method: 'POST', body: formData })
                            .then(r => r.json())
                            .then(d => {
                                if (d.success) {
                                    loadSubmissions();
                                    // Update counter badge
                                    const countValEl = document.getElementById('form-count-val');
                                    if (countValEl) {
                                        const cur = parseInt(countValEl.textContent || '0', 10);
                                        if (cur > 0) countValEl.textContent = (cur - 1).toString();
                                    }
                                } else {
                                    alert('Error: ' + (d.error || 'Failed to delete'));
                                }
                            });
                    }
                });
            });
        }

        btnViewSubmissions.addEventListener('click', () => {
            modalSubmissions.style.display = 'flex';
            loadSubmissions();
        });

        if (closeBtn) {
            closeBtn.addEventListener('click', () => {
                modalSubmissions.style.display = 'none';
            });
        }

        if (refreshBtn) {
            refreshBtn.addEventListener('click', loadSubmissions);
        }

        if (searchInput) {
            searchInput.addEventListener('input', () => {
                if (cachedData) {
                    renderSubmissionsTable(cachedData.submissions || [], cachedData.schema || {});
                }
            });
        }

        if (clearBtn) {
            clearBtn.addEventListener('click', () => {
                const conf = prompt('To permanently clear ALL responses, type "CLEAR" below:');
                if (conf === 'CLEAR') {
                    const formData = new FormData();
                    formData.append('action', 'ext_form_clear_submissions');
                    formData.append('file', relFile);

                    fetch('api/admin.php', { method: 'POST', body: formData })
                        .then(r => r.json())
                        .then(d => {
                            if (d.success) {
                                loadSubmissions();
                                const countValEl = document.getElementById('form-count-val');
                                if (countValEl) countValEl.textContent = '0';
                            } else {
                                alert('Error: ' + (d.error || 'Failed to clear'));
                            }
                        });
                }
            });
        }
    }

    // ------------------------------------------------------------------------
    // 4. Admin Form Schema Editor Modal
    // ------------------------------------------------------------------------
    const btnEditSchema = document.getElementById('btn-edit-form-schema');
    const modalEditSchema = document.getElementById('modal-edit-form-schema');
    if (btnEditSchema && modalEditSchema) {
        const closeBtn = document.getElementById('btn-close-edit-schema-modal');
        const cancelBtn = document.getElementById('btn-cancel-edit-schema');
        const editForm = document.getElementById('form-edit-schema-form');
        const titleInput = document.getElementById('edit-form-title');
        const descInput = document.getElementById('edit-form-desc');
        const submitTextInput = document.getElementById('edit-form-submit-text');
        const successMsgInput = document.getElementById('edit-form-success-msg');
        const requireLoginCheck = document.getElementById('edit-form-require-login');
        const allowMultipleCheck = document.getElementById('edit-form-allow-multiple');
        const notifyEmailInput = document.getElementById('edit-form-notify-email');
        const webhookUrlInput = document.getElementById('edit-form-webhook-url');
        const builderContainer = document.getElementById('form-builder-fields-edit');
        const btnAddField = document.getElementById('btn-add-field-edit');

        const relFile = btnEditSchema.dataset.file;
        let activeEditorFields = [];

        const editBuilder = createFieldBuilder(builderContainer, [], (fields) => {
            activeEditorFields = fields;
        });

        if (btnAddField) {
            btnAddField.addEventListener('click', () => {
                editBuilder.addField();
            });
        }

        btnEditSchema.addEventListener('click', () => {
            modalEditSchema.style.display = 'flex';
            fetch(`api/admin.php?action=ext_form_get_schema&file=${encodeURIComponent(relFile)}`)
                .then(r => r.json())
                .then(res => {
                    if (res.success && res.schema) {
                        const s = res.schema;
                        if (titleInput) titleInput.value = s.title || '';
                        if (descInput) descInput.value = s.description || '';
                        if (submitTextInput) submitTextInput.value = s.submitButtonText || 'Submit';
                        if (successMsgInput) successMsgInput.value = s.successMessage || 'Thank you!';
                        if (requireLoginCheck) requireLoginCheck.checked = !!s.requireLogin;
                        if (allowMultipleCheck) allowMultipleCheck.checked = s.allowMultiple !== false;
                        if (notifyEmailInput) notifyEmailInput.value = s.notificationEmail || '';
                        if (webhookUrlInput) webhookUrlInput.value = s.webhookUrl || '';
                        editBuilder.setFields(s.fields || []);
                    }
                });
        });

        const closeModal = () => { modalEditSchema.style.display = 'none'; };
        if (closeBtn) closeBtn.addEventListener('click', closeModal);
        if (cancelBtn) cancelBtn.addEventListener('click', closeModal);

        if (editForm) {
            editForm.addEventListener('submit', (e) => {
                e.preventDefault();

                const saveBtn = document.getElementById('btn-save-edit-schema');
                if (saveBtn) {
                    saveBtn.disabled = true;
                    saveBtn.textContent = 'Saving...';
                }

                const updatedSchema = {
                    title: (titleInput ? titleInput.value.trim() : '') || 'Form',
                    description: descInput ? descInput.value.trim() : '',
                    submitButtonText: (submitTextInput ? submitTextInput.value.trim() : '') || 'Submit',
                    successMessage: (successMsgInput ? successMsgInput.value.trim() : '') || 'Thank you! Your response has been recorded.',
                    requireLogin: requireLoginCheck ? requireLoginCheck.checked : false,
                    allowMultiple: allowMultipleCheck ? allowMultipleCheck.checked : true,
                    notificationEmail: notifyEmailInput ? notifyEmailInput.value.trim() : '',
                    webhookUrl: webhookUrlInput ? webhookUrlInput.value.trim() : '',
                    fields: activeEditorFields
                };

                const formData = new FormData();
                formData.append('action', 'ext_form_save_schema');
                formData.append('file', relFile);
                formData.append('schema_json', JSON.stringify(updatedSchema));

                fetch('api/admin.php', { method: 'POST', body: formData })
                    .then(r => r.json())
                    .then(d => {
                        if (d.success) {
                            window.location.reload();
                        } else {
                            alert('Failed to save changes: ' + (d.error || 'Unknown error'));
                            if (saveBtn) {
                                saveBtn.disabled = false;
                                saveBtn.textContent = 'Save Changes';
                            }
                        }
                    })
                    .catch(err => {
                        alert('Network error: ' + err.message);
                        if (saveBtn) {
                            saveBtn.disabled = false;
                            saveBtn.textContent = 'Save Changes';
                        }
                    });
            });
        }
    }
});
