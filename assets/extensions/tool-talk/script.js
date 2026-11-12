/**
 * Standalone Qwiki - Wikipedia-Style Talk Pages Extension Client Script
 */
(function () {
  'use strict';

  function initTalkPages() {
    const contentBody = document.getElementById('content-body');
    if (!contentBody) return;

    // Detect active chapter and book identifier
    const metaBtn = document.getElementById('btn-edit-chapter-meta');
    const deleteBtn = document.getElementById('btn-delete-chapter');
    const postboxBtn = document.getElementById('btn-send-to-postbox');

    const slug = (metaBtn && metaBtn.getAttribute('data-slug')) ||
                 (deleteBtn && deleteBtn.getAttribute('data-slug')) ||
                 (postboxBtn && postboxBtn.getAttribute('data-slug')) || '';

    const book = (metaBtn && (metaBtn.getAttribute('data-category-id') || metaBtn.getAttribute('data-book-id'))) ||
                 (deleteBtn && deleteBtn.getAttribute('data-book')) ||
                 (postboxBtn && postboxBtn.getAttribute('data-book')) || '';

    if (!slug) return;

    // Avoid duplicate mounting
    if (document.getElementById('qwiki-doc-tabs')) return;

    // Fetch talk page details & permission status from backend API
    const getUrl = `api/admin.php?action=ext_talk_get&book=${encodeURIComponent(book)}&slug=${encodeURIComponent(slug)}`;

    fetch(getUrl, {
      method: 'GET',
      headers: { 'Accept': 'application/json' }
    })
    .then(res => {
      if (!res.ok && res.status !== 200) {
        return null;
      }
      return res.json();
    })
    .then(data => {
      if (!data || !data.success || !data.talkEnabled) {
        return;
      }
      renderTabsAndTalkUI(contentBody, book, slug, data);
    })
    .catch(err => {
      console.warn('[Qwiki Talk Pages] Error loading talk page:', err);
    });
  }

  function renderTabsAndTalkUI(contentBody, book, slug, initialData) {
    let talkData = initialData;
    let isRawEditing = false;
    let isAddingTopic = false;
    let activeReplyTopicIndex = null;

    // 1. Create top tab bar [ Document | Discussion ]
    const tabNav = document.createElement('div');
    tabNav.id = 'qwiki-doc-tabs';
    tabNav.className = 'qwiki-doc-tabs';
    tabNav.setAttribute('role', 'tablist');

    const docBtn = document.createElement('button');
    docBtn.id = 'tab-btn-doc';
    docBtn.className = 'doc-tab-btn doc-tab-active';
    docBtn.setAttribute('role', 'tab');
    docBtn.setAttribute('aria-selected', 'true');
    docBtn.innerHTML = '📄 Document';

    const talkBtn = document.createElement('button');
    talkBtn.id = 'tab-btn-talk';
    talkBtn.className = 'doc-tab-btn';
    talkBtn.setAttribute('role', 'tab');
    talkBtn.setAttribute('aria-selected', 'false');
    talkBtn.innerHTML = `🗣️ Discussion <span class="doc-tab-badge" id="talk-topic-count">${talkData.topicsCount || 0}</span>`;

    tabNav.appendChild(docBtn);
    tabNav.appendChild(talkBtn);

    // 2. Create talk container
    const talkContainer = document.createElement('div');
    talkContainer.id = 'qwiki-talk-container';
    talkContainer.className = 'talk-container';
    talkContainer.style.display = 'none';

    // Insert tabs before #content-body, and talk container after
    contentBody.parentNode.insertBefore(tabNav, contentBody);
    contentBody.parentNode.insertBefore(talkContainer, contentBody.nextSibling);

    // Tab switching handlers
    function switchTab(activeTab) {
      if (activeTab === 'talk') {
        docBtn.classList.remove('doc-tab-active');
        docBtn.setAttribute('aria-selected', 'false');
        talkBtn.classList.add('doc-tab-active');
        talkBtn.setAttribute('aria-selected', 'true');
        contentBody.style.display = 'none';
        talkContainer.style.display = 'flex';
        renderTalkWorkspace();
      } else {
        talkBtn.classList.remove('doc-tab-active');
        talkBtn.setAttribute('aria-selected', 'false');
        docBtn.classList.add('doc-tab-active');
        docBtn.setAttribute('aria-selected', 'true');
        talkContainer.style.display = 'none';
        contentBody.style.display = '';
      }
    }

    docBtn.addEventListener('click', () => switchTab('doc'));
    talkBtn.addEventListener('click', () => switchTab('talk'));

    function reloadTalkData() {
      const getUrl = `api/admin.php?action=ext_talk_get&book=${encodeURIComponent(book)}&slug=${encodeURIComponent(slug)}`;
      return fetch(getUrl, {
        method: 'GET',
        headers: { 'Accept': 'application/json' }
      })
      .then(res => res.json())
      .then(data => {
        if (data && data.success) {
          talkData = data;
          const countBadge = document.getElementById('talk-topic-count');
          if (countBadge) {
            countBadge.textContent = talkData.topicsCount || 0;
          }
          renderTalkWorkspace();
        }
      });
    }

    function renderTalkWorkspace() {
      talkContainer.innerHTML = '';

      // Banner header
      const headerBanner = document.createElement('div');
      headerBanner.className = 'talk-header-banner';

      const headerInfo = document.createElement('div');
      headerInfo.className = 'talk-header-info';
      headerInfo.innerHTML = `
        <h2>🗣️ Editorial Discussion &amp; Debate</h2>
        <p>Collaborative talk page for drafting changes, debate, and editorial consensus.</p>
      `;

      const actionsBar = document.createElement('div');
      actionsBar.className = 'talk-actions';

      if (talkData.canPost && !isRawEditing && !isAddingTopic) {
        const btnNewTopic = document.createElement('button');
        btnNewTopic.className = 'btn btn-primary btn-sm';
        btnNewTopic.innerHTML = '+ Add Topic';
        btnNewTopic.addEventListener('click', () => {
          isAddingTopic = true;
          activeReplyTopicIndex = null;
          renderTalkWorkspace();
        });
        actionsBar.appendChild(btnNewTopic);
      }

      if (talkData.canEdit) {
        const btnToggleRaw = document.createElement('button');
        btnToggleRaw.className = 'btn btn-outline btn-sm';
        btnToggleRaw.innerHTML = isRawEditing ? 'View Formatted Topics' : '✏️ Edit Raw Talk Page';
        btnToggleRaw.addEventListener('click', () => {
          isRawEditing = !isRawEditing;
          isAddingTopic = false;
          activeReplyTopicIndex = null;
          renderTalkWorkspace();
        });
        actionsBar.appendChild(btnToggleRaw);
      }

      const btnRefresh = document.createElement('button');
      btnRefresh.className = 'btn btn-outline btn-sm';
      btnRefresh.title = 'Refresh Discussion';
      btnRefresh.innerHTML = '🔄';
      btnRefresh.addEventListener('click', () => reloadTalkData());
      actionsBar.appendChild(btnRefresh);

      headerBanner.appendChild(headerInfo);
      headerBanner.appendChild(actionsBar);
      talkContainer.appendChild(headerBanner);

      // Raw Markdown Editor Mode
      if (isRawEditing) {
        renderRawEditorView(talkContainer);
        return;
      }

      // Inline Add Topic Form Mode
      if (isAddingTopic) {
        renderAddTopicForm(talkContainer);
      }

      // Empty State
      const topics = talkData.topics || [];
      if (topics.length === 0 && !isAddingTopic) {
        const emptyState = document.createElement('div');
        emptyState.className = 'talk-empty-state';
        emptyState.innerHTML = `
          <div class="talk-empty-icon">💬</div>
          <div class="talk-empty-title">No discussion topics yet</div>
          <div class="talk-empty-desc">This talk page is currently empty. Start a conversation, propose revisions, or leave editorial notes for this chapter.</div>
        `;
        if (talkData.canPost) {
          const btnStart = document.createElement('button');
          btnStart.className = 'btn btn-primary';
          btnStart.innerHTML = '+ Start First Topic';
          btnStart.addEventListener('click', () => {
            isAddingTopic = true;
            renderTalkWorkspace();
          });
          emptyState.appendChild(btnStart);
        } else {
          const loginHint = document.createElement('p');
          loginHint.style.color = 'var(--text-muted)';
          loginHint.style.fontSize = '0.85rem';
          loginHint.innerHTML = 'Sign in to start a topic or participate in the discussion.';
          emptyState.appendChild(loginHint);
        }
        talkContainer.appendChild(emptyState);
        return;
      }

      // Render Topics List
      topics.forEach((topic) => {
        const card = document.createElement('div');
        card.className = 'talk-topic-card';

        const cardHeader = document.createElement('div');
        cardHeader.className = 'talk-topic-header';
        cardHeader.innerHTML = `<h3 class="talk-topic-title">${escapeHtml(topic.title)}</h3>`;

        const cardBody = document.createElement('div');
        cardBody.className = 'talk-topic-body';
        cardBody.innerHTML = topic.html || '<p><em>No content.</em></p>';

        const cardFooter = document.createElement('div');
        cardFooter.className = 'talk-topic-footer';

        if (talkData.canPost) {
          const btnReply = document.createElement('button');
          btnReply.className = 'btn btn-outline btn-sm';
          btnReply.innerHTML = '↩️ Reply';
          btnReply.addEventListener('click', () => {
            activeReplyTopicIndex = (activeReplyTopicIndex === topic.index) ? null : topic.index;
            isAddingTopic = false;
            renderTalkWorkspace();
          });
          cardFooter.appendChild(btnReply);
        }

        card.appendChild(cardHeader);
        card.appendChild(cardBody);
        card.appendChild(cardFooter);

        // If this topic has an active reply form open
        if (activeReplyTopicIndex === topic.index) {
          const replyFormCard = document.createElement('div');
          replyFormCard.className = 'talk-form-card';
          replyFormCard.style.margin = '1rem';
          replyFormCard.innerHTML = `
            <div class="talk-form-title">Reply to "${escapeHtml(topic.title)}"</div>
            <textarea class="form-control" id="talk-reply-input" rows="4" placeholder="Write your reply (Markdown supported)..." style="width: 100%;"></textarea>
            <div class="talk-form-actions">
              <button type="button" class="btn btn-outline btn-sm" id="btn-cancel-reply">Cancel</button>
              <button type="button" class="btn btn-primary btn-sm" id="btn-submit-reply">Post Reply</button>
            </div>
          `;
          card.appendChild(replyFormCard);

          setTimeout(() => {
            const textarea = replyFormCard.querySelector('#talk-reply-input');
            if (textarea) textarea.focus();

            const btnCancel = replyFormCard.querySelector('#btn-cancel-reply');
            btnCancel.addEventListener('click', () => {
              activeReplyTopicIndex = null;
              renderTalkWorkspace();
            });

            const btnSubmit = replyFormCard.querySelector('#btn-submit-reply');
            btnSubmit.addEventListener('click', () => {
              const replyMsg = (textarea.value || '').trim();
              if (!replyMsg) {
                alert('Please enter a reply message.');
                return;
              }
              btnSubmit.disabled = true;
              btnSubmit.textContent = 'Posting...';

              fetch('api/admin.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                  action: 'ext_talk_reply',
                  book: book,
                  slug: slug,
                  topicIndex: topic.index,
                  message: replyMsg
                })
              })
              .then(res => res.json())
              .then(resData => {
                if (resData && resData.success) {
                  activeReplyTopicIndex = null;
                  reloadTalkData();
                } else {
                  alert(resData.error || 'Failed to post reply.');
                  btnSubmit.disabled = false;
                  btnSubmit.textContent = 'Post Reply';
                }
              })
              .catch(err => {
                alert('Network error while posting reply.');
                btnSubmit.disabled = false;
                btnSubmit.textContent = 'Post Reply';
              });
            });
          }, 0);
        }

        talkContainer.appendChild(card);
      });
    }

    function renderAddTopicForm(container) {
      const formCard = document.createElement('div');
      formCard.className = 'talk-form-card';
      formCard.innerHTML = `
        <div class="talk-form-title">+ Start a New Discussion Topic</div>
        <div class="form-group" style="margin-bottom: 0.75rem;">
          <label class="form-label">Topic Subject / Title</label>
          <input type="text" id="talk-topic-title-input" class="form-control" placeholder="e.g. Proposed revision to Section 2" maxlength="120" required>
        </div>
        <div class="form-group" style="margin-bottom: 0.75rem;">
          <label class="form-label">Message (Markdown supported)</label>
          <textarea id="talk-topic-msg-input" class="form-control" rows="5" placeholder="Explain the rationale, paste proposals, or ask questions..."></textarea>
        </div>
        <div class="talk-form-actions">
          <button type="button" class="btn btn-outline" id="btn-cancel-topic">Cancel</button>
          <button type="button" class="btn btn-primary" id="btn-submit-topic">Create Topic</button>
        </div>
      `;
      container.appendChild(formCard);

      setTimeout(() => {
        const titleInput = formCard.querySelector('#talk-topic-title-input');
        const msgInput = formCard.querySelector('#talk-topic-msg-input');
        const btnCancel = formCard.querySelector('#btn-cancel-topic');
        const btnSubmit = formCard.querySelector('#btn-submit-topic');

        titleInput.focus();

        btnCancel.addEventListener('click', () => {
          isAddingTopic = false;
          renderTalkWorkspace();
        });

        btnSubmit.addEventListener('click', () => {
          const title = (titleInput.value || '').trim();
          const message = (msgInput.value || '').trim();

          if (!title) {
            alert('Please enter a topic title.');
            titleInput.focus();
            return;
          }

          if (!message) {
            alert('Please enter a topic message.');
            msgInput.focus();
            return;
          }

          btnSubmit.disabled = true;
          btnSubmit.textContent = 'Creating...';

          fetch('api/admin.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({
              action: 'ext_talk_add_topic',
              book: book,
              slug: slug,
              title: title,
              message: message
            })
          })
          .then(res => res.json())
          .then(resData => {
            if (resData && resData.success) {
              isAddingTopic = false;
              reloadTalkData();
            } else {
              alert(resData.error || 'Failed to create topic.');
              btnSubmit.disabled = false;
              btnSubmit.textContent = 'Create Topic';
            }
          })
          .catch(err => {
            alert('Network error while creating topic.');
            btnSubmit.disabled = false;
            btnSubmit.textContent = 'Create Topic';
          });
        });
      }, 0);
    }

    function renderRawEditorView(container) {
      const editorCard = document.createElement('div');
      editorCard.className = 'talk-raw-editor-card';
      editorCard.innerHTML = `
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem;">
          <h3 style="margin: 0; font-size: 1.05rem; color: var(--text-primary);">Raw Talk Page Markdown</h3>
          <small style="color: var(--text-muted);">Sections begin with <code>## Topic Name</code></small>
        </div>
        <textarea class="talk-raw-textarea" id="talk-raw-textarea">${escapeHtml(talkData.raw || '')}</textarea>
        <div class="talk-form-actions" style="margin-top: 1rem;">
          <button type="button" class="btn btn-outline" id="btn-cancel-raw">Cancel</button>
          <button type="button" class="btn btn-primary" id="btn-save-raw">Save Raw Markdown</button>
        </div>
      `;
      container.appendChild(editorCard);

      setTimeout(() => {
        const textarea = editorCard.querySelector('#talk-raw-textarea');
        const btnCancel = editorCard.querySelector('#btn-cancel-raw');
        const btnSave = editorCard.querySelector('#btn-save-raw');

        btnCancel.addEventListener('click', () => {
          isRawEditing = false;
          renderTalkWorkspace();
        });

        btnSave.addEventListener('click', () => {
          const markdown = textarea.value;
          btnSave.disabled = true;
          btnSave.textContent = 'Saving...';

          fetch('api/admin.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({
              action: 'ext_talk_save_raw',
              book: book,
              slug: slug,
              markdown: markdown
            })
          })
          .then(res => res.json())
          .then(resData => {
            if (resData && resData.success) {
              isRawEditing = false;
              reloadTalkData();
            } else {
              alert(resData.error || 'Failed to save talk page.');
              btnSave.disabled = false;
              btnSave.textContent = 'Save Raw Markdown';
            }
          })
          .catch(err => {
            alert('Network error while saving raw talk page.');
            btnSave.disabled = false;
            btnSave.textContent = 'Save Raw Markdown';
          });
        });
      }, 0);
    }
  }

  function escapeHtml(str) {
    if (!str) return '';
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initTalkPages);
  } else {
    initTalkPages();
  }
})();
