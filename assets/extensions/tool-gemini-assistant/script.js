/**
 * Standalone Qwiki - Gemini AI Assistant Extension Frontend Script
 */
(function() {
    'use strict';

    // State
    var currentTags = [];
    var currentDocData = {
        title: '',
        slug: '',
        image: '',
        description: ''
    };

    function showBanner(msg, type) {
        var banner = document.getElementById('gemini-alert-banner');
        if (!banner) return;
        banner.className = 'gemini-alert ' + (type === 'error' ? 'gemini-alert-error' : 'gemini-alert-success');
        banner.textContent = msg;
        banner.style.display = 'block';
        setTimeout(function() {
            banner.style.display = 'none';
        }, 5000);
    }

    function updateCharCounter() {
        var descEl = document.getElementById('gemini-meta-desc');
        var counterEl = document.getElementById('gemini-desc-counter');
        if (!descEl || !counterEl) return;
        var len = descEl.value.length;
        counterEl.textContent = len + ' / 160';
        if (len > 160) {
            counterEl.style.color = '#ef4444';
        } else if (len >= 140) {
            counterEl.style.color = '#10b981';
        } else {
            counterEl.style.color = 'var(--text-muted)';
        }

        // Live preview sync
        var cardDesc = document.getElementById('gemini-card-desc');
        if (cardDesc) {
            cardDesc.textContent = descEl.value || 'Your AI-generated description will render here in real time.';
        }
    }

    function renderTagPills() {
        var container = document.getElementById('gemini-meta-tags-pills');
        if (!container) return;
        container.innerHTML = '';

        currentTags.forEach(function(tag, idx) {
            var pill = document.createElement('span');
            pill.className = 'gemini-tag-pill';
            pill.textContent = tag + ' ';

            var removeBtn = document.createElement('button');
            removeBtn.type = 'button';
            removeBtn.className = 'gemini-tag-remove';
            removeBtn.innerHTML = '&times;';
            removeBtn.title = 'Remove tag';
            removeBtn.addEventListener('click', function(e) {
                e.stopPropagation();
                currentTags.splice(idx, 1);
                renderTagPills();
            });

            pill.appendChild(removeBtn);
            container.appendChild(pill);
        });
    }

    function updateSocialCardPreview(title, image, desc) {
        var cardTitle = document.getElementById('gemini-card-title');
        var cardImg = document.getElementById('gemini-card-img');
        var cardPlaceholder = document.getElementById('gemini-card-img-placeholder');
        var cardDesc = document.getElementById('gemini-card-desc');

        if (cardTitle) cardTitle.textContent = title || 'Document Title';
        if (cardDesc) cardDesc.textContent = desc || 'Your AI-generated description will render here in real time.';

        if (cardImg && cardPlaceholder) {
            if (image) {
                cardImg.src = image;
                cardImg.style.display = 'block';
                cardPlaceholder.style.display = 'none';
            } else {
                cardImg.style.display = 'none';
                cardPlaceholder.style.display = 'flex';
            }
        }
    }

    function fetchSettings() {
        fetch('api/admin.php?action=ext_gemini_get_settings')
            .then(function(res) { return res.json(); })
            .then(function(data) {
                if (data.success) {
                    var inputKey = document.getElementById('gemini-input-apikey');
                    var modelSelect = document.getElementById('gemini-select-model');
                    var envNotice = document.getElementById('gemini-env-notice');
                    var statusBadge = document.getElementById('gemini-status-badge');

                    if (inputKey) {
                        inputKey.value = data.maskedKey || '';
                        if (data.isEnvKey) {
                            inputKey.disabled = true;
                            if (envNotice) envNotice.style.display = 'block';
                        }
                    }

                    if (modelSelect && data.model) {
                        var foundOpt = false;
                        for (var i = 0; i < modelSelect.options.length; i++) {
                            if (modelSelect.options[i].value === data.model) {
                                foundOpt = true;
                                break;
                            }
                        }
                        if (!foundOpt) {
                            var extra = document.createElement('option');
                            extra.value = data.model;
                            extra.textContent = data.model;
                            modelSelect.appendChild(extra);
                        }
                        modelSelect.value = data.model;
                    }

                    if (statusBadge) {
                        if (data.hasKey) {
                            statusBadge.textContent = data.model;
                            statusBadge.style.background = 'rgba(16, 185, 129, 0.15)';
                            statusBadge.style.color = '#10b981';
                            statusBadge.style.borderColor = 'rgba(16, 185, 129, 0.3)';
                        } else {
                            statusBadge.textContent = 'API Key Needed';
                            statusBadge.style.background = 'rgba(239, 68, 68, 0.15)';
                            statusBadge.style.color = '#ef4444';
                            statusBadge.style.borderColor = 'rgba(239, 68, 68, 0.3)';
                        }
                    }
                }
            })
            .catch(function(err) {
                console.warn('Could not fetch Gemini settings:', err);
            });
    }

    // Tab & Modal Helpers
    function switchGeminiTab(tabId) {
        var modal = document.getElementById('modal-gemini-assistant');
        if (!modal) return;

        var tabButtons = modal.querySelectorAll('.gemini-tab-btn');
        tabButtons.forEach(function(b) {
            if (b.getAttribute('data-tab') === tabId) {
                b.classList.add('active');
            } else {
                b.classList.remove('active');
            }
        });

        var views = modal.querySelectorAll('.gemini-tab-view');
        views.forEach(function(v) {
            v.style.display = 'none';
            v.classList.remove('active');
        });

        var targetView = document.getElementById('gemini-view-' + tabId);
        if (targetView) {
            targetView.style.display = 'block';
            targetView.classList.add('active');
        }

        if (tabId === 'settings') {
            fetchSettings();
        }
    }

    function openGeminiAssistant(tabId) {
        var modal = document.getElementById('modal-gemini-assistant');
        if (!modal) return;

        modal.classList.add('open');
        modal.classList.add('active');
        onModalOpened();

        // Close user dropdown if open
        var dropdownMenu = document.querySelector('.dropdown-menu.show');
        if (dropdownMenu) {
            dropdownMenu.classList.remove('show');
        }

        if (tabId) {
            switchGeminiTab(tabId);
        }
    }

    function closeGeminiAssistant() {
        var modal = document.getElementById('modal-gemini-assistant');
        if (!modal) return;
        modal.classList.remove('open');
        modal.classList.remove('active');
    }

    // Expose helpers globally for other scripts and UI triggers
    window.openGeminiAssistant = openGeminiAssistant;
    window.closeGeminiAssistant = closeGeminiAssistant;
    window.switchGeminiTab = switchGeminiTab;

    // Initialize once DOM is loaded
    function initGeminiAssistant() {
        var modal = document.getElementById('modal-gemini-assistant');
        if (!modal) return;

        // 1. Hook Header Menu Button
        var btnOpen = document.getElementById('btn-util-gemini_assistant');
        if (btnOpen) {
            btnOpen.addEventListener('click', function(e) {
                e.preventDefault();
                openGeminiAssistant();
            });
        }

        // Global delegator for any Gemini Assistant opening trigger
        document.addEventListener('click', function(e) {
            var trigger = e.target.closest('#btn-util-gemini_assistant, #btn-open-gemini-from-settings, #btn-open-gemini-from-llm, [data-open="modal-gemini-assistant"], .btn-open-gemini-assistant');
            if (trigger) {
                e.preventDefault();
                // Close parent modal if clicking from inside another modal (e.g. settings-modal or llm-keys-modal)
                var parentModal = trigger.closest('.modal-overlay');
                if (parentModal && parentModal !== modal) {
                    parentModal.classList.remove('open');
                    parentModal.classList.remove('active');
                }
                var targetTab = trigger.getAttribute('data-gemini-tab') || (trigger.id && trigger.id.indexOf('setting') !== -1 ? 'settings' : null);
                openGeminiAssistant(targetTab);
            }
        });

        // 2. Hook Modal Close Elements
        var closeButtons = modal.querySelectorAll('[data-close="modal-gemini-assistant"]');
        closeButtons.forEach(function(btn) {
            btn.addEventListener('click', function() {
                closeGeminiAssistant();
            });
        });

        modal.addEventListener('click', function(e) {
            if (e.target === modal) {
                closeGeminiAssistant();
            }
        });

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && (modal.classList.contains('open') || modal.classList.contains('active'))) {
                closeGeminiAssistant();
            }
        });

        // 3. Tab Switching
        var tabButtons = modal.querySelectorAll('.gemini-tab-btn');
        tabButtons.forEach(function(btn) {
            btn.addEventListener('click', function() {
                var tabId = btn.getAttribute('data-tab');
                switchGeminiTab(tabId);
            });
        });

        // 4. Platform Preview Toggle (Twitter vs LinkedIn)
        var previewBtns = modal.querySelectorAll('.gemini-preview-tab-btn');
        var cardSim = document.getElementById('gemini-card-simulator');
        previewBtns.forEach(function(btn) {
            btn.addEventListener('click', function() {
                previewBtns.forEach(function(b) { b.classList.remove('active'); });
                btn.classList.add('active');
                var platform = btn.getAttribute('data-platform');
                if (cardSim) {
                    if (platform === 'linkedin') {
                        cardSim.classList.add('platform-linkedin');
                    } else {
                        cardSim.classList.remove('platform-linkedin');
                    }
                }
            });
        });

        // 5. Document Select Change
        var docSelect = document.getElementById('gemini-select-doc');
        if (docSelect) {
            docSelect.addEventListener('change', function() {
                var opt = docSelect.selectedOptions[0];
                if (opt && opt.value) {
                    currentDocData.slug = opt.value;
                    currentDocData.title = opt.text.split('/').pop().trim();
                    currentDocData.description = opt.getAttribute('data-desc') || '';
                    currentDocData.image = opt.getAttribute('data-image') || '';

                    try {
                        currentTags = JSON.parse(opt.getAttribute('data-tags') || '[]');
                    } catch(e) {
                        currentTags = [];
                    }

                    var descEl = document.getElementById('gemini-meta-desc');
                    if (descEl) descEl.value = currentDocData.description;
                    updateCharCounter();
                    renderTagPills();
                    updateSocialCardPreview(currentDocData.title, currentDocData.image, currentDocData.description);
                }
            });
        }

        // Real-time character counter listener
        var descInput = document.getElementById('gemini-meta-desc');
        if (descInput) {
            descInput.addEventListener('input', updateCharCounter);
        }

        // Add Custom Tag button
        var btnAddTag = document.getElementById('gemini-btn-add-tag');
        var inputAddTag = document.getElementById('gemini-add-tag-input');
        if (btnAddTag && inputAddTag) {
            var handleAddTag = function() {
                var val = inputAddTag.value.trim();
                if (val && currentTags.indexOf(val) === -1) {
                    currentTags.push(val);
                    renderTagPills();
                    inputAddTag.value = '';
                }
            };
            btnAddTag.addEventListener('click', handleAddTag);
            inputAddTag.addEventListener('keydown', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    handleAddTag();
                }
            });
        }

        // 6. Generate Metadata Action
        var btnGenMeta = document.getElementById('gemini-btn-generate-meta');
        if (btnGenMeta) {
            btnGenMeta.addEventListener('click', function() {
                var slug = docSelect ? docSelect.value : '';
                if (!slug) {
                    alert('Please choose a target document first.');
                    return;
                }

                var loading = document.getElementById('gemini-meta-loading');
                var results = document.getElementById('gemini-meta-results');
                if (loading) loading.style.display = 'flex';
                if (results) results.style.display = 'none';

                var formData = new FormData();
                formData.append('slug', slug);
                formData.append('title', currentDocData.title);

                fetch('api/admin.php?action=ext_gemini_generate_meta', {
                    method: 'POST',
                    body: formData
                })
                .then(function(res) { return res.json(); })
                .then(function(data) {
                    if (loading) loading.style.display = 'none';
                    if (!data.success) {
                        showBanner(data.error || 'Failed to generate metadata.', 'error');
                        return;
                    }

                    if (results) results.style.display = 'grid';
                    var meta = data.metadata || {};

                    // Populate fields
                    var descEl = document.getElementById('gemini-meta-desc');
                    if (descEl) descEl.value = meta.description || '';
                    updateCharCounter();

                    if (Array.isArray(meta.tags) && meta.tags.length > 0) {
                        currentTags = meta.tags;
                        renderTagPills();
                    }

                    var blurbEl = document.getElementById('gemini-meta-blurb');
                    if (blurbEl) blurbEl.value = meta.social_blurb || '';

                    // Headlines
                    var headlinesList = document.getElementById('gemini-meta-headlines-list');
                    var headlinesGroup = document.getElementById('gemini-headlines-group');
                    if (headlinesList && headlinesGroup) {
                        if (Array.isArray(meta.headline_suggestions) && meta.headline_suggestions.length > 0) {
                            headlinesList.innerHTML = '';
                            meta.headline_suggestions.forEach(function(h) {
                                var pill = document.createElement('div');
                                pill.className = 'gemini-headline-pill';
                                pill.textContent = h;
                                pill.title = 'Click to copy headline';
                                pill.addEventListener('click', function() {
                                    navigator.clipboard.writeText(h);
                                    showBanner('Headline copied to clipboard!', 'success');
                                });
                                headlinesList.appendChild(pill);
                            });
                            headlinesGroup.style.display = 'block';
                        } else {
                            headlinesGroup.style.display = 'none';
                        }
                    }

                    updateSocialCardPreview(currentDocData.title, currentDocData.image, meta.description);
                    showBanner('Metadata generated successfully with Gemini!', 'success');
                })
                .catch(function(err) {
                    if (loading) loading.style.display = 'none';
                    showBanner('Network error calling Gemini: ' + err.message, 'error');
                });
            });
        }

        // Copy Social Blurb button
        var btnCopyBlurb = document.getElementById('gemini-btn-copy-blurb');
        if (btnCopyBlurb) {
            btnCopyBlurb.addEventListener('click', function() {
                var blurbEl = document.getElementById('gemini-meta-blurb');
                if (blurbEl && blurbEl.value) {
                    navigator.clipboard.writeText(blurbEl.value).then(function() {
                        var original = btnCopyBlurb.textContent;
                        btnCopyBlurb.textContent = '✓ Copied!';
                        setTimeout(function() { btnCopyBlurb.textContent = original; }, 2000);
                    });
                }
            });
        }

        // 7. Save Metadata to Document Action
        var btnApplyMeta = document.getElementById('gemini-btn-apply-meta');
        if (btnApplyMeta) {
            btnApplyMeta.addEventListener('click', function() {
                var slug = docSelect ? docSelect.value : '';
                var desc = document.getElementById('gemini-meta-desc') ? document.getElementById('gemini-meta-desc').value : '';

                if (!slug) {
                    alert('No document selected.');
                    return;
                }

                btnApplyMeta.disabled = true;
                btnApplyMeta.innerHTML = '<span>⏳</span> Saving...';

                var formData = new FormData();
                formData.append('slug', slug);
                formData.append('description', desc);
                formData.append('tags', JSON.stringify(currentTags));

                fetch('api/admin.php?action=ext_gemini_apply_meta', {
                    method: 'POST',
                    body: formData
                })
                .then(function(res) { return res.json(); })
                .then(function(data) {
                    btnApplyMeta.disabled = false;
                    btnApplyMeta.innerHTML = '<span>💾</span> Save Metadata to Document';

                    if (!data.success) {
                        showBanner(data.error || 'Failed to save metadata.', 'error');
                        return;
                    }

                    showBanner('Document metadata saved successfully!', 'success');

                    // Sync in-memory option
                    if (docSelect && docSelect.selectedOptions[0]) {
                        docSelect.selectedOptions[0].setAttribute('data-desc', desc);
                        docSelect.selectedOptions[0].setAttribute('data-tags', JSON.stringify(currentTags));
                    }

                    // Sync native edit modal if present
                    var nativeDesc = document.getElementById('edit-chapter-description');
                    var nativeSlug = document.getElementById('edit-chapter-slug');
                    if (nativeDesc && nativeSlug && nativeSlug.value === slug) {
                        nativeDesc.value = desc;
                    }
                })
                .catch(function(err) {
                    btnApplyMeta.disabled = false;
                    btnApplyMeta.innerHTML = '<span>💾</span> Save Metadata to Document';
                    showBanner('Network error saving metadata: ' + err.message, 'error');
                });
            });
        }

        // 8. Executive TL;DR Action
        var btnGenTldr = document.getElementById('gemini-btn-generate-tldr');
        var contentDocSelect = document.getElementById('gemini-select-content-doc');
        if (btnGenTldr && contentDocSelect) {
            btnGenTldr.addEventListener('click', function() {
                var slug = contentDocSelect.value;
                if (!slug) {
                    alert('Please select a document first.');
                    return;
                }

                var loading = document.getElementById('gemini-tldr-loading');
                var results = document.getElementById('gemini-tldr-results');
                if (loading) loading.style.display = 'flex';
                if (results) results.style.display = 'none';

                var formData = new FormData();
                formData.append('slug', slug);

                fetch('api/admin.php?action=ext_gemini_summarize', {
                    method: 'POST',
                    body: formData
                })
                .then(function(res) { return res.json(); })
                .then(function(data) {
                    if (loading) loading.style.display = 'none';
                    if (!data.success) {
                        showBanner(data.error || 'Failed to generate TL;DR.', 'error');
                        return;
                    }

                    if (results) results.style.display = 'block';
                    var tldrMarkdown = document.getElementById('gemini-tldr-markdown');
                    if (tldrMarkdown) tldrMarkdown.value = data.tldr || '';
                    showBanner('Executive TL;DR generated!', 'success');
                })
                .catch(function(err) {
                    if (loading) loading.style.display = 'none';
                    showBanner('Network error: ' + err.message, 'error');
                });
            });
        }

        // Copy TL;DR button
        var btnCopyTldr = document.getElementById('gemini-btn-copy-tldr');
        if (btnCopyTldr) {
            btnCopyTldr.addEventListener('click', function() {
                var tldr = document.getElementById('gemini-tldr-markdown');
                if (tldr && tldr.value) {
                    navigator.clipboard.writeText(tldr.value).then(function() {
                        var orig = btnCopyTldr.textContent;
                        btnCopyTldr.textContent = '✓ Copied!';
                        setTimeout(function() { btnCopyTldr.textContent = orig; }, 2000);
                    });
                }
            });
        }

        // 9. Settings Form
        var settingsForm = document.getElementById('gemini-settings-form');
        var btnToggleKey = document.getElementById('gemini-btn-toggle-key');
        var inputApiKey = document.getElementById('gemini-input-apikey');
        if (btnToggleKey && inputApiKey) {
            btnToggleKey.addEventListener('click', function() {
                inputApiKey.type = (inputApiKey.type === 'password') ? 'text' : 'password';
            });
        }

        function updateModelDropdown(availableModels, currentSelected) {
            var modelSelect = document.getElementById('gemini-select-model');
            if (!modelSelect || !Array.isArray(availableModels) || availableModels.length === 0) return;

            var chosen = currentSelected || modelSelect.value;
            modelSelect.innerHTML = '';

            availableModels.forEach(function(m) {
                var opt = document.createElement('option');
                var id = typeof m === 'object' ? (m.id || m.name) : m;
                var displayName = typeof m === 'object' ? (m.name || m.id) : m;
                opt.value = id;
                opt.textContent = displayName + (displayName !== id ? ' (' + id + ')' : '');
                modelSelect.appendChild(opt);
            });

            // Ensure current or chosen model is selected or added if missing
            var exists = false;
            for (var i = 0; i < modelSelect.options.length; i++) {
                if (modelSelect.options[i].value === chosen) {
                    exists = true;
                    break;
                }
            }
            if (!exists && chosen) {
                var extraOpt = document.createElement('option');
                extraOpt.value = chosen;
                extraOpt.textContent = chosen;
                modelSelect.appendChild(extraOpt);
            }
            if (chosen) {
                modelSelect.value = chosen;
            }
        }

        var btnDetectModels = document.getElementById('gemini-btn-detect-models');
        if (btnDetectModels) {
            btnDetectModels.addEventListener('click', function() {
                var apiKey = inputApiKey ? inputApiKey.value : '';
                var originalText = btnDetectModels.textContent;
                btnDetectModels.disabled = true;
                btnDetectModels.textContent = '⏳ Detecting...';

                var formData = new FormData();
                formData.append('apiKey', apiKey);

                fetch('api/admin.php?action=ext_gemini_list_models', {
                    method: 'POST',
                    body: formData
                })
                .then(function(res) { return res.json(); })
                .then(function(data) {
                    btnDetectModels.disabled = false;
                    btnDetectModels.textContent = originalText;

                    if (!data.success) {
                        showBanner(data.error || 'Failed to detect models.', 'error');
                        return;
                    }

                    if (Array.isArray(data.models) && data.models.length > 0) {
                        updateModelDropdown(data.models);
                        showBanner('Found ' + data.models.length + ' active models for your API key!', 'success');
                    } else {
                        showBanner('No generateContent models returned for this key.', 'warning');
                    }
                })
                .catch(function(err) {
                    btnDetectModels.disabled = false;
                    btnDetectModels.textContent = originalText;
                    showBanner('Network error detecting models: ' + err.message, 'error');
                });
            });
        }

        var btnTestConn = document.getElementById('gemini-btn-test-conn');
        if (btnTestConn) {
            btnTestConn.addEventListener('click', function() {
                var testResult = document.getElementById('gemini-test-result');
                var apiKey = inputApiKey ? inputApiKey.value : '';
                var model = document.getElementById('gemini-select-model') ? document.getElementById('gemini-select-model').value : '';

                if (testResult) {
                    testResult.textContent = 'Connecting...';
                    testResult.style.color = 'var(--text-muted)';
                }

                var formData = new FormData();
                formData.append('apiKey', apiKey);
                formData.append('model', model);

                fetch('api/admin.php?action=ext_gemini_test_connection', {
                    method: 'POST',
                    body: formData
                })
                .then(function(res) { return res.json(); })
                .then(function(data) {
                    if (testResult) {
                        if (data.success) {
                            testResult.textContent = '✓ ' + data.message;
                            testResult.style.color = '#10b981';
                        } else {
                            testResult.textContent = '✕ ' + data.error;
                            testResult.style.color = '#ef4444';
                        }
                    }

                    if (data.available_models && Array.isArray(data.available_models) && data.available_models.length > 0) {
                        updateModelDropdown(data.available_models, data.model || model);
                    } else if (data.model) {
                        var modelSelect = document.getElementById('gemini-select-model');
                        if (modelSelect) modelSelect.value = data.model;
                    }
                })
                .catch(function(err) {
                    if (testResult) {
                        testResult.textContent = '✕ Network error: ' + err.message;
                        testResult.style.color = '#ef4444';
                    }
                });
            });
        }

        if (settingsForm) {
            settingsForm.addEventListener('submit', function(e) {
                e.preventDefault();
                var apiKey = inputApiKey ? inputApiKey.value : '';
                var model = document.getElementById('gemini-select-model') ? document.getElementById('gemini-select-model').value : '';
                var btnSave = document.getElementById('gemini-btn-save-settings');

                if (btnSave) {
                    btnSave.disabled = true;
                    btnSave.textContent = 'Saving...';
                }

                var formData = new FormData();
                formData.append('apiKey', apiKey);
                formData.append('model', model);

                fetch('api/admin.php?action=ext_gemini_save_settings', {
                    method: 'POST',
                    body: formData
                })
                .then(function(res) { return res.json(); })
                .then(function(data) {
                    if (btnSave) {
                        btnSave.disabled = false;
                        btnSave.textContent = 'Save API Settings';
                    }

                    if (!data.success) {
                        showBanner(data.error || 'Failed to save settings.', 'error');
                        return;
                    }

                    showBanner('Gemini settings saved successfully!', 'success');
                    fetchSettings();
                })
                .catch(function(err) {
                    if (btnSave) {
                        btnSave.disabled = false;
                        btnSave.textContent = 'Save API Settings';
                    }
                    showBanner('Network error saving settings: ' + err.message, 'error');
                });
            });
        }

        // 10. Inject Inline Button in Native Edit Modal (#edit-chapter-modal)
        injectInlineSuggestButton();

        // Initial settings fetch to update badge
        fetchSettings();
    }

    function onModalOpened() {
        // Auto-select currently viewed chapter
        var currentSlug = '';
        var editBtn = document.getElementById('btn-edit-chapter-meta') || document.getElementById('btn-edit-chapter') || document.getElementById('btn-replace-document');
        if (editBtn) {
            currentSlug = editBtn.getAttribute('data-slug') || '';
        }
        if (!currentSlug) {
            var activeNav = document.querySelector('.nav-link.active');
            if (activeNav) {
                currentSlug = activeNav.getAttribute('data-doc-slug') || '';
            }
        }

        var docSelect = document.getElementById('gemini-select-doc');
        var contentDocSelect = document.getElementById('gemini-select-content-doc');

        if (currentSlug && docSelect) {
            docSelect.value = currentSlug;
            // Trigger change event to load details
            var evt = new Event('change');
            docSelect.dispatchEvent(evt);
        }

        if (currentSlug && contentDocSelect) {
            contentDocSelect.value = currentSlug;
        }
    }

    function injectInlineSuggestButton() {
        var descTextarea = document.getElementById('edit-chapter-description');
        if (!descTextarea) return;

        var formGroup = descTextarea.closest('.form-group');
        if (!formGroup) return;

        var label = formGroup.querySelector('label');
        if (!label || formGroup.querySelector('.gemini-inline-suggest-btn')) return;

        // Container to align label and button nicely
        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'btn btn-outline btn-sm gemini-inline-suggest-btn';
        btn.innerHTML = '<span>✨</span> AI Suggest';
        btn.title = 'Generate an engaging social description using Google Gemini';

        btn.addEventListener('click', function(e) {
            e.preventDefault();
            var slugInput = document.getElementById('edit-chapter-slug');
            var slug = slugInput ? slugInput.value : '';

            var origHtml = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<span>⏳</span> Generating...';

            var formData = new FormData();
            formData.append('slug', slug);

            fetch('api/admin.php?action=ext_gemini_generate_meta', {
                method: 'POST',
                body: formData
            })
            .then(function(res) { return res.json(); })
            .then(function(data) {
                btn.disabled = false;
                btn.innerHTML = origHtml;

                if (!data.success) {
                    alert(data.error || 'Failed to generate metadata suggestion.');
                    return;
                }

                var meta = data.metadata || {};
                if (meta.description) {
                    descTextarea.value = meta.description;
                    descTextarea.style.transition = 'box-shadow 0.3s ease';
                    descTextarea.style.boxShadow = '0 0 0 3px rgba(16, 185, 129, 0.4)';
                    setTimeout(function() {
                        descTextarea.style.boxShadow = '';
                    }, 1200);
                }
            })
            .catch(function(err) {
                btn.disabled = false;
                btn.innerHTML = origHtml;
                alert('Network error: ' + err.message);
            });
        });

        label.style.display = 'flex';
        label.style.justifyContent = 'space-between';
        label.style.alignItems = 'center';
        label.appendChild(btn);
    }

    // Run when ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initGeminiAssistant);
    } else {
        initGeminiAssistant();
    }
})();
