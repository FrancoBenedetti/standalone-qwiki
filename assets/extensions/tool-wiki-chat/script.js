(function () {
    'use strict';

    var t = function (key, fallback, params) {
        if (typeof window._t === 'function') {
            return window._t(key, fallback, params);
        }
        return fallback || key;
    };

    var STORAGE_KEY = 'qwiki_chat_history';

    function escapeHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function sanitizeUrl(url, baseUrl) {
        var trimmed = String(url || '').trim();
        if (/^(javascript|data|vbscript):/i.test(trimmed)) {
            return '#';
        }
        if (/^https?:\/\//i.test(trimmed) || trimmed.indexOf('/') === 0) {
            return trimmed;
        }
        if (baseUrl) {
            return baseUrl.replace(/\/+$/, '') + '/' + trimmed.replace(/^\/+/, '');
        }
        return trimmed;
    }

    function renderMarkdownSafe(md, baseUrl) {
        var text = escapeHtml(md || '');

        // Fenced code blocks
        var codeBlocks = [];
        text = text.replace(/```([a-zA-Z0-9_-]*)\n([\s\S]*?)```/g, function (_, lang, code) {
            var idx = codeBlocks.length;
            codeBlocks.push('<pre><code>' + code.trim() + '</code></pre>');
            return '%%CODEBLOCK_' + idx + '%%';
        });

        // Inline code
        text = text.replace(/`([^`\n]+)`/g, '<code>$1</code>');

        // Links [label](url)
        text = text.replace(/\[([^\]]+)\]\(([^)]+)\)/g, function (_, label, href) {
            var safeHref = escapeHtml(sanitizeUrl(href, baseUrl));
            return '<a href="' + safeHref + '">' + label + '</a>';
        });

        // Bold & italic
        text = text.replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>');
        text = text.replace(/\*([^*]+)\*/g, '<em>$1</em>');

        // Headings
        text = text.replace(/^###\s+(.+)$/gm, '<strong>$1</strong>');
        text = text.replace(/^##\s+(.+)$/gm, '<strong>$1</strong>');
        text = text.replace(/^#\s+(.+)$/gm, '<strong>$1</strong>');

        // Unordered list items
        text = text.replace(/^(?:-|\*)\s+(.+)$/gm, '<li>$1</li>');
        text = text.replace(/(<li>[\s\S]*?<\/li>)(?!\n<li>)/g, '<ul>$1</ul>');
        text = text.replace(/<\/ul>\n<ul>/g, '\n');

        // Paragraphs
        var blocks = text.split(/\n{2,}/);
        text = blocks.map(function (b) {
            b = b.trim();
            if (!b) return '';
            if (/^<(?:ul|ol|pre|p|div)/.test(b) || /^%%CODEBLOCK_\d+%%$/.test(b)) {
                return b;
            }
            return '<p>' + b.replace(/\n/g, '<br>') + '</p>';
        }).join('');

        codeBlocks.forEach(function (html, idx) {
            text = text.replace('%%CODEBLOCK_' + idx + '%%', html);
        });

        return text;
    }

    document.addEventListener('DOMContentLoaded', function () {
        var drawer = document.getElementById('wiki-chat-drawer');
        var backdrop = document.getElementById('wiki-chat-backdrop');
        var openBtn = document.getElementById('btn-open-wiki-chat');
        if (!drawer) return;

        var baseUrl = drawer.getAttribute('data-base-url') || '';
        var activeSlug = drawer.getAttribute('data-active-slug') || '';
        var isAdmin = drawer.getAttribute('data-is-admin') === '1';

        var closeBtn = document.getElementById('wiki-chat-close-btn');
        var expandBtn = document.getElementById('wiki-chat-expand-btn');
        var clearBtn = document.getElementById('wiki-chat-clear-btn');
        var messagesEl = document.getElementById('wiki-chat-messages');
        var emptyStateEl = document.getElementById('wiki-chat-empty-state');
        var formEl = document.getElementById('wiki-chat-form');
        var inputEl = document.getElementById('wiki-chat-input');
        var sendBtn = document.getElementById('wiki-chat-send-btn');
        var includePageEl = document.getElementById('wiki-chat-include-page');
        var keyBadgeEl = document.getElementById('wiki-chat-key-badge');

        var history = [];
        try {
            var saved = sessionStorage.getItem(STORAGE_KEY);
            if (saved) {
                var parsed = JSON.parse(saved);
                if (Array.isArray(parsed)) history = parsed;
            }
        } catch (e) {}

        function saveHistory() {
            try {
                sessionStorage.setItem(STORAGE_KEY, JSON.stringify(history.slice(-20)));
            } catch (e) {}
        }

        function appendMessage(role, text, meta) {
            if (emptyStateEl) emptyStateEl.style.display = 'none';
            var wrapper = document.createElement('div');
            wrapper.className = 'wiki-chat-msg wiki-chat-msg--' + (role === 'user' ? 'user' : 'model');

            if (role === 'model' && meta && Array.isArray(meta.toolCalls) && meta.toolCalls.length > 0) {
                var details = document.createElement('details');
                details.className = 'wiki-chat-tools-chip';
                var summary = document.createElement('summary');
                summary.textContent = '🔍 ' + t('ext_chat.tools_used', 'Used {count} wiki lookup(s)', { count: meta.toolCalls.length });
                details.appendChild(summary);
                var ul = document.createElement('ul');
                ul.className = 'wiki-chat-tools-list';
                meta.toolCalls.forEach(function (tc) {
                    var li = document.createElement('li');
                    li.textContent = tc.name + (tc.args && tc.args.slug ? ' (' + tc.args.slug + ')' : (tc.args && tc.args.query ? ' ("' + tc.args.query + '")' : ''));
                    ul.appendChild(li);
                });
                details.appendChild(ul);
                wrapper.appendChild(details);
            }

            var bubble = document.createElement('div');
            bubble.className = 'wiki-chat-bubble';
            if (role === 'user') {
                bubble.textContent = text;
            } else {
                bubble.innerHTML = renderMarkdownSafe(text, baseUrl);
            }
            wrapper.appendChild(bubble);

            if (role === 'model' && meta && Array.isArray(meta.sources) && meta.sources.length > 0) {
                var sourcesDiv = document.createElement('div');
                sourcesDiv.className = 'wiki-chat-sources';
                meta.sources.forEach(function (src) {
                    var a = document.createElement('a');
                    a.className = 'wiki-chat-source-pill';
                    a.href = sanitizeUrl(src.url || src.slug || '#', baseUrl);
                    a.textContent = '📄 ' + (src.title || src.slug);
                    sourcesDiv.appendChild(a);
                });
                wrapper.appendChild(sourcesDiv);
            }

            messagesEl.appendChild(wrapper);
            messagesEl.scrollTop = messagesEl.scrollHeight;
            return wrapper;
        }

        function renderSavedHistory() {
            if (!history.length) return;
            history.forEach(function (item) {
                appendMessage(item.role, item.text, item.meta || null);
            });
        }
        renderSavedHistory();

        var configLoaded = false;
        function loadConfig() {
            if (configLoaded) return;
            configLoaded = true;
            fetch((baseUrl ? baseUrl + '/' : '') + 'api/admin.php?action=ext_wiki_chat_get_config')
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (!data || !data.success) return;
                    if (keyBadgeEl) {
                        var isPersonal = data.keySource === 'user';
                        keyBadgeEl.textContent = isPersonal
                            ? '👤 ' + t('ext_chat.key_personal', 'Personal Key')
                            : '🌐 ' + t('ext_chat.key_site', 'Site Key');
                    }
                    if (isAdmin && Array.isArray(data.llmKeys)) {
                        var selectEl = document.getElementById('wiki-chat-llm-key');
                        if (selectEl) {
                            var currentVal = (data.settings && data.settings.llmKeyId) || '';
                            selectEl.innerHTML = '<option value="">' + escapeHtml(t('ext_chat.llm_key_auto', 'Auto-managed (Wiki Chat Assistant — All Categories)')) + '</option>';
                            data.llmKeys.forEach(function (k) {
                                if (k.status !== 'active') return;
                                var opt = document.createElement('option');
                                opt.value = k.id;
                                opt.textContent = k.name + (k.category ? ' (' + k.category + ')' : ' (All)');
                                if (k.id === currentVal) opt.selected = true;
                                selectEl.appendChild(opt);
                            });
                        }
                    }
                })
                .catch(function () {});
        }

        function openDrawer() {
            drawer.classList.add('open');
            drawer.setAttribute('aria-hidden', 'false');
            if (backdrop) {
                backdrop.classList.add('open');
                backdrop.setAttribute('aria-hidden', 'false');
            }
            loadConfig();
            if (inputEl) setTimeout(function () { inputEl.focus(); }, 80);
        }

        function closeDrawer() {
            drawer.classList.remove('open');
            drawer.setAttribute('aria-hidden', 'true');
            if (backdrop) {
                backdrop.classList.remove('open');
                backdrop.setAttribute('aria-hidden', 'true');
            }
        }

        if (openBtn) openBtn.addEventListener('click', openDrawer);
        if (closeBtn) closeBtn.addEventListener('click', closeDrawer);
        if (backdrop) backdrop.addEventListener('click', closeDrawer);

        if (expandBtn) {
            expandBtn.addEventListener('click', function () {
                drawer.classList.toggle('wiki-chat-drawer--wide');
            });
        }

        if (clearBtn) {
            clearBtn.addEventListener('click', function () {
                history = [];
                saveHistory();
                var msgs = messagesEl.querySelectorAll('.wiki-chat-msg');
                msgs.forEach(function (m) { m.remove(); });
                if (emptyStateEl) emptyStateEl.style.display = '';
            });
        }

        document.addEventListener('keydown', function (e) {
            if ((e.ctrlKey || e.metaKey) && e.shiftKey && e.key.toLowerCase() === 'k') {
                e.preventDefault();
                if (drawer.classList.contains('open')) closeDrawer();
                else openDrawer();
            } else if (e.key === 'Escape' && drawer.classList.contains('open')) {
                closeDrawer();
            }
        });

        // Tabs (Admin)
        var tabBtns = drawer.querySelectorAll('[data-chat-tab]');
        tabBtns.forEach(function (btn) {
            btn.addEventListener('click', function () {
                var target = btn.getAttribute('data-chat-tab');
                tabBtns.forEach(function (b) { b.classList.remove('active'); });
                btn.classList.add('active');
                var panels = drawer.querySelectorAll('.wiki-chat-panel');
                panels.forEach(function (p) { p.classList.remove('active'); });
                var panel = document.getElementById('wiki-chat-panel-' + target);
                if (panel) panel.classList.add('active');
            });
        });

        // Starter prompt chips
        var starters = drawer.querySelectorAll('.wiki-chat-starter-chip');
        starters.forEach(function (chip) {
            chip.addEventListener('click', function () {
                var p = chip.getAttribute('data-prompt') || '';
                if (p && inputEl) {
                    inputEl.value = p;
                    formEl.dispatchEvent(new Event('submit', { cancelable: true }));
                }
            });
        });

        if (inputEl) {
            inputEl.addEventListener('keydown', function (e) {
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    formEl.dispatchEvent(new Event('submit', { cancelable: true }));
                }
            });
        }

        var isSending = false;
        if (formEl) {
            formEl.addEventListener('submit', function (e) {
                e.preventDefault();
                if (isSending) return;
                var msg = (inputEl.value || '').trim();
                if (!msg) return;

                isSending = true;
                sendBtn.disabled = true;
                inputEl.value = '';

                appendMessage('user', msg, null);
                var thinkingBubble = appendMessage('model', t('ext_chat.thinking', 'Thinking...'), null);

                var payload = {
                    message: msg,
                    history: history.map(function (h) { return { role: h.role, text: h.text }; }),
                    activeSlug: activeSlug,
                    includeActiveDoc: includePageEl ? includePageEl.checked : true
                };

                history.push({ role: 'user', text: msg });
                saveHistory();

                fetch((baseUrl ? baseUrl + '/' : '') + 'api/admin.php?action=ext_wiki_chat_ask', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                })
                    .then(function (r) { return r.json(); })
                    .then(function (data) {
                        thinkingBubble.remove();
                        if (!data || !data.success) {
                            var errText = (data && data.error) ? data.error : t('ext_chat.error_generic', 'Failed to get a response.');
                            appendMessage('model', '⚠️ ' + errText, null);
                            return;
                        }
                        var meta = {
                            sources: data.sources || [],
                            toolCalls: data.tool_calls || []
                        };
                        appendMessage('model', data.reply || '', meta);
                        history.push({ role: 'model', text: data.reply || '', meta: meta });
                        saveHistory();
                    })
                    .catch(function () {
                        thinkingBubble.remove();
                        appendMessage('model', '⚠️ ' + t('ext_chat.error_network', 'Network error while contacting Wiki AI Chat.'), null);
                    })
                    .finally(function () {
                        isSending = false;
                        sendBtn.disabled = false;
                        inputEl.focus();
                    });
            });
        }

        // Admin Settings Form
        var settingsForm = document.getElementById('wiki-chat-settings-form');
        var settingsStatus = document.getElementById('wiki-chat-settings-status');
        if (settingsForm) {
            settingsForm.addEventListener('submit', function (e) {
                e.preventDefault();
                if (settingsStatus) settingsStatus.textContent = t('ext_chat.saving', 'Saving...');
                var payload = {
                    accessMode: document.getElementById('wiki-chat-access-mode').value,
                    llmKeyId: document.getElementById('wiki-chat-llm-key').value,
                    includeActiveDoc: document.getElementById('wiki-chat-default-include-doc').checked,
                    systemPromptExtra: document.getElementById('wiki-chat-system-extra').value
                };
                fetch((baseUrl ? baseUrl + '/' : '') + 'api/admin.php?action=ext_wiki_chat_save_config', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                })
                    .then(function (r) { return r.json(); })
                    .then(function (data) {
                        if (data && data.success) {
                            if (settingsStatus) settingsStatus.textContent = '✅ ' + t('ext_chat.saved', 'Saved!');
                        } else {
                            if (settingsStatus) settingsStatus.textContent = '⚠️ ' + ((data && data.error) || 'Error');
                        }
                    })
                    .catch(function () {
                        if (settingsStatus) settingsStatus.textContent = '⚠️ ' + t('ext_chat.error_network', 'Network error');
                    });
            });
        }
    });
})();
