(function () {
    'use strict';

    var _t = function (key, fallback, params) {
        if (typeof window._t === 'function' && window._t !== _t) {
            return window._t(key, fallback, params);
        }
        if (params && typeof fallback === 'string') {
            return fallback.replace(/\{(\w+)\}/g, function (_, k) {
                return params[k] !== undefined ? params[k] : '{' + k + '}';
            });
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

    function formatInlineMd(line, baseUrl) {
        // Inline code
        var out = line.replace(/`([^`\n]+)`/g, '<code>$1</code>');
        // Links [label](url)
        out = out.replace(/\[([^\]]+)\]\(([^)\n]+)\)/g, function (_, label, href) {
            var safeHref = escapeHtml(sanitizeUrl(href, baseUrl));
            return '<a href="' + safeHref + '">' + label + '</a>';
        });
        // Bold & italic (single-line only to avoid cross-paragraph bleed)
        out = out.replace(/\*\*([^*\n]+)\*\*/g, '<strong>$1</strong>');
        out = out.replace(/\*([^*\n]+)\*/g, '<em>$1</em>');
        return out;
    }

    function renderMarkdownSafe(md, baseUrl) {
        var text = escapeHtml(md || '').replace(/\r\n/g, '\n');

        // Fenced code blocks
        var codeBlocks = [];
        text = text.replace(/```([a-zA-Z0-9_-]*)\n([\s\S]*?)```/g, function (_, lang, code) {
            var idx = codeBlocks.length;
            codeBlocks.push('<pre><code>' + code.trim() + '</code></pre>');
            return '\n%%CODEBLOCK_' + idx + '%%\n';
        });

        var lines = text.split('\n');
        var htmlParts = [];
        var listType = null; // 'ul' | 'ol'
        var paraLines = [];

        function flushPara() {
            if (paraLines.length > 0) {
                htmlParts.push('<p>' + paraLines.map(function (l) { return formatInlineMd(l, baseUrl); }).join('<br>') + '</p>');
                paraLines = [];
            }
        }
        function flushList() {
            if (listType) {
                htmlParts.push('</' + listType + '>');
                listType = null;
            }
        }

        for (var i = 0; i < lines.length; i++) {
            var rawLine = lines[i];
            var trimmed = rawLine.trim();

            if (trimmed === '') {
                flushPara();
                flushList();
                continue;
            }
            if (/^%%CODEBLOCK_\d+%%$/.test(trimmed)) {
                flushPara();
                flushList();
                htmlParts.push(trimmed);
                continue;
            }
            if (/^-{3,}$/.test(trimmed) || /^\*{3,}$/.test(trimmed)) {
                flushPara();
                flushList();
                htmlParts.push('<hr>');
                continue;
            }
            var hMatch = trimmed.match(/^(#{1,4})\s+(.+)$/);
            if (hMatch) {
                flushPara();
                flushList();
                var tag = hMatch[1].length >= 4 ? 'h4' : 'h3';
                htmlParts.push('<' + tag + '>' + formatInlineMd(hMatch[2], baseUrl) + '</' + tag + '>');
                continue;
            }
            var ulMatch = rawLine.match(/^\s*[-*]\s+(.+)$/);
            if (ulMatch) {
                flushPara();
                if (listType !== 'ul') {
                    flushList();
                    listType = 'ul';
                    htmlParts.push('<ul>');
                }
                htmlParts.push('<li>' + formatInlineMd(ulMatch[1], baseUrl) + '</li>');
                continue;
            }
            var olMatch = rawLine.match(/^\s*\d+\.\s+(.+)$/);
            if (olMatch) {
                flushPara();
                if (listType !== 'ol') {
                    flushList();
                    listType = 'ol';
                    htmlParts.push('<ol>');
                }
                htmlParts.push('<li>' + formatInlineMd(olMatch[1], baseUrl) + '</li>');
                continue;
            }

            flushList();
            paraLines.push(trimmed);
        }
        flushPara();
        flushList();

        var result = htmlParts.join('');
        codeBlocks.forEach(function (html, idx) {
            result = result.replace('%%CODEBLOCK_' + idx + '%%', html);
        });
        return result;
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
                summary.textContent = '🔍 ' + _t('ext_chat.tools_used', 'Used {count} wiki lookup(s)', { count: meta.toolCalls.length });
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
            } else if (meta && meta.isLoading) {
                bubble.innerHTML = '<span class="wiki-chat-typing"><span></span><span></span><span></span></span> ' + escapeHtml(text);
            } else {
                bubble.innerHTML = renderMarkdownSafe(text, baseUrl);
            }
            wrapper.appendChild(bubble);

            if (role === 'model' && !(meta && meta.isLoading)) {
                var footer = document.createElement('div');
                footer.className = 'wiki-chat-msg-footer';

                if (meta && Array.isArray(meta.sources) && meta.sources.length > 0) {
                    var sourcesDiv = document.createElement('div');
                    sourcesDiv.className = 'wiki-chat-sources';
                    meta.sources.forEach(function (src) {
                        var a = document.createElement('a');
                        a.className = 'wiki-chat-source-pill';
                        a.href = sanitizeUrl(src.url || src.slug || '#', baseUrl);
                        a.textContent = '📄 ' + (src.title || src.slug);
                        sourcesDiv.appendChild(a);
                    });
                    footer.appendChild(sourcesDiv);
                }

                if (navigator.clipboard && text) {
                    var copyBtn = document.createElement('button');
                    copyBtn.type = 'button';
                    copyBtn.className = 'wiki-chat-copy-btn';
                    copyBtn.title = _t('ext_chat.copy_btn', 'Copy response');
                    copyBtn.textContent = '📋';
                    copyBtn.addEventListener('click', function () {
                        navigator.clipboard.writeText(text).then(function () {
                            copyBtn.textContent = '✅';
                            setTimeout(function () { copyBtn.textContent = '📋'; }, 1500);
                        }).catch(function () {});
                    });
                    footer.appendChild(copyBtn);
                }

                if (footer.childNodes.length > 0) {
                    wrapper.appendChild(footer);
                }
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
                            ? '👤 ' + _t('ext_chat.key_personal', 'Personal Key')
                            : '🌐 ' + _t('ext_chat.key_site', 'Site Key');
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
                var thinkingBubble = appendMessage('model', _t('ext_chat.thinking', 'Thinking...'), { isLoading: true });

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
                            var errText = (data && data.error) ? data.error : _t('ext_chat.error_generic', 'Failed to get a response.');
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
                        appendMessage('model', '⚠️ ' + _t('ext_chat.error_network', 'Network error while contacting Wiki AI Chat.'), null);
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
                if (settingsStatus) settingsStatus.textContent = _t('ext_chat.saving', 'Saving...');
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
                            if (settingsStatus) settingsStatus.textContent = '✅ ' + _t('ext_chat.saved', 'Saved!');
                        } else {
                            if (settingsStatus) settingsStatus.textContent = '⚠️ ' + ((data && data.error) || 'Error');
                        }
                    })
                    .catch(function () {
                        if (settingsStatus) settingsStatus.textContent = '⚠️ ' + _t('ext_chat.error_network', 'Network error');
                    });
            });
        }
    });
})();
