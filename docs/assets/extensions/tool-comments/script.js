/**
 * Standalone Qwiki - Chapter Comments Extension Client Script
 */
(function () {
  'use strict';

  function initComments() {
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

    // Check if comments container already mounted
    if (document.getElementById('qwiki-comments-section')) return;

    // Fetch comments & permission status from backend API
    const getUrl = `api/admin.php?action=ext_comments_get&book=${encodeURIComponent(book)}&slug=${encodeURIComponent(slug)}`;

    fetch(getUrl, {
      method: 'GET',
      headers: { 'Accept': 'application/json' }
    })
    .then(res => {
      if (res.status === 401) {
        // Guest user: comments are restricted to signed-in users
        return null;
      }
      return res.json();
    })
    .then(data => {
      if (!data || !data.success || !data.commentsEnabled) {
        return;
      }
      renderCommentsUI(contentBody, book, slug, data);
    })
    .catch(err => {
      console.warn('[Qwiki Comments] Error checking comments:', err);
    });
  }

  function getInitials(name) {
    if (!name) return '?';
    const parts = name.trim().split(/[\s_-]+/);
    if (parts.length >= 2) {
      return (parts[0][0] + parts[1][0]).toUpperCase();
    }
    return name.substring(0, 2).toUpperCase();
  }

  function formatRelativeTime(isoString) {
    if (!isoString) return '';
    try {
      const date = new Date(isoString);
      const now = new Date();
      const diffSecs = Math.floor((now - date) / 1000);

      if (diffSecs < 60) return 'Just now';
      if (diffSecs < 3600) return `${Math.floor(diffSecs / 60)}m ago`;
      if (diffSecs < 86400) return `${Math.floor(diffSecs / 3600)}h ago`;
      if (diffSecs < 604800) return `${Math.floor(diffSecs / 86400)}d ago`;

      return date.toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });
    } catch (e) {
      return isoString;
    }
  }

  function escapeHtml(str) {
    if (!str) return '';
    return str
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function renderCommentsUI(container, book, slug, initialData) {
    let commentsState = initialData.comments || [];
    const currentUser = initialData.currentUser || { username: '', role: 'viewer', isAdmin: false };

    // Create Root Container
    const rootSection = document.createElement('section');
    rootSection.id = 'qwiki-comments-section';

    // Calculate total count (root comments + replies)
    function getTotalCount(list) {
      let count = 0;
      list.forEach(item => {
        if (!item.isDeleted) count++;
        if (item.replies && Array.isArray(item.replies)) {
          item.replies.forEach(r => {
            if (!r.isDeleted) count++;
          });
        }
      });
      return count;
    }

    // Read stored visibility preference from localStorage
    const storageKey = 'qwiki_comments_visible';
    let isVisible = localStorage.getItem(storageKey) !== 'false';

    rootSection.innerHTML = `
      <div class="comments-header-bar">
        <div class="comments-title-group">
          <h3 class="comments-title">💬 Document Comments</h3>
          <span class="comments-count-pill" id="comments-count-badge">${getTotalCount(commentsState)}</span>
        </div>
        <button type="button" class="comments-toggle-btn" id="btn-toggle-comments">
          <span id="comments-toggle-label">${isVisible ? 'Hide Comments' : 'Show Comments'}</span>
        </button>
      </div>

      <div class="comments-body-wrapper ${isVisible ? '' : 'collapsed'}" id="comments-body-wrapper">
        <div class="comment-composer-card">
          <div class="comment-composer-header">
            <span>Signed in as <strong>${escapeHtml(currentUser.username)}</strong> (${escapeHtml(currentUser.role)})</span>
            <span id="comment-char-counter">0 / 2000</span>
          </div>
          <textarea class="comment-textarea" id="main-comment-input" placeholder="Add a constructive comment, note or question in Markdown..."></textarea>
          <div class="comment-preview-pane" id="main-comment-preview"></div>
          <div class="comment-composer-footer">
            <div class="comment-formatting-help">
              <span>Supports **bold**, *italic*, <code>code</code>, lists &amp; links</span>
            </div>
            <div class="comment-actions-group">
              <button type="button" class="btn btn-outline btn-sm" id="btn-preview-main-comment">Preview</button>
              <button type="button" class="btn btn-primary btn-sm" id="btn-submit-main-comment">Post Comment</button>
            </div>
          </div>
        </div>

        <div class="comments-stream" id="comments-stream-container"></div>
      </div>
    `;

    container.appendChild(rootSection);

    const streamContainer = rootSection.querySelector('#comments-stream-container');
    const toggleBtn = rootSection.querySelector('#btn-toggle-comments');
    const toggleLabel = rootSection.querySelector('#comments-toggle-label');
    const bodyWrapper = rootSection.querySelector('#comments-body-wrapper');
    const countBadge = rootSection.querySelector('#comments-count-badge');
    const mainInput = rootSection.querySelector('#main-comment-input');
    const mainPreview = rootSection.querySelector('#main-comment-preview');
    const previewBtn = rootSection.querySelector('#btn-preview-main-comment');
    const submitBtn = rootSection.querySelector('#btn-submit-main-comment');
    const charCounter = rootSection.querySelector('#comment-char-counter');

    // Toggle Visibility handler
    toggleBtn.addEventListener('click', () => {
      isVisible = !isVisible;
      bodyWrapper.classList.toggle('collapsed', !isVisible);
      toggleLabel.textContent = isVisible ? 'Hide Comments' : 'Show Comments';
      localStorage.setItem(storageKey, isVisible ? 'true' : 'false');
    });

    // Character counter
    mainInput.addEventListener('input', () => {
      charCounter.textContent = `${mainInput.value.length} / 2000`;
    });

    // Markdown preview handler
    previewBtn.addEventListener('click', () => {
      const active = mainPreview.classList.toggle('active');
      previewBtn.textContent = active ? 'Edit' : 'Preview';
      if (active) {
        mainPreview.innerHTML = parseClientMarkdown(mainInput.value || '*No content to preview*');
      }
    });

    // Submit Root Comment
    submitBtn.addEventListener('click', () => {
      const content = mainInput.value.trim();
      if (!content) return;

      submitBtn.disabled = true;
      submitBtn.textContent = 'Posting...';

      const formData = new FormData();
      formData.append('action', 'ext_comments_add');
      formData.append('book', book);
      formData.append('slug', slug);
      formData.append('content', content);

      fetch('api/admin.php', {
        method: 'POST',
        body: formData
      })
      .then(res => res.json())
      .then(resp => {
        submitBtn.disabled = false;
        submitBtn.textContent = 'Post Comment';
        if (resp.success && resp.comment) {
          mainInput.value = '';
          charCounter.textContent = '0 / 2000';
          mainPreview.classList.remove('active');
          previewBtn.textContent = 'Preview';

          // Add to state and re-render
          commentsState.unshift(resp.comment);
          refreshStream();
        } else {
          alert(resp.error || 'Failed to post comment.');
        }
      })
      .catch(err => {
        submitBtn.disabled = false;
        submitBtn.textContent = 'Post Comment';
        alert('Network error posting comment.');
      });
    });

    function refreshStream() {
      countBadge.textContent = getTotalCount(commentsState);
      renderStream(streamContainer, commentsState, currentUser, book, slug, refreshStream);
    }

    refreshStream();
  }

  function renderStream(container, comments, currentUser, book, slug, onUpdate) {
    if (!comments || comments.length === 0) {
      container.innerHTML = `
        <div class="comment-empty-state">
          <p>No comments on this chapter yet. Be the first to start the discussion!</p>
        </div>
      `;
      return;
    }

    container.innerHTML = '';

    comments.forEach(comment => {
      const card = document.createElement('div');
      card.className = 'comment-card';
      card.id = `comment-card-${comment.id}`;

      const isAuthor = (currentUser.username && comment.author === currentUser.username);
      const canManage = isAuthor || currentUser.isAdmin;

      const initials = getInitials(comment.author);
      const relTime = formatRelativeTime(comment.createdAt);
      const editedNotice = comment.updatedAt ? `<span class="comment-edited-tag" title="Edited on ${escapeHtml(comment.updatedAt)}">(edited)</span>` : '';

      card.innerHTML = `
        <div class="comment-meta-row">
          <div class="comment-author-info">
            <div class="comment-avatar">${escapeHtml(initials)}</div>
            <span class="comment-author-name">${escapeHtml(comment.author)}</span>
            <span class="comment-role-badge role-${escapeHtml(comment.role || 'viewer')}">${escapeHtml(comment.role || 'viewer')}</span>
            <span class="comment-timestamp" title="${escapeHtml(comment.createdAt || '')}">${escapeHtml(relTime)}${editedNotice}</span>
          </div>
        </div>

        <div class="comment-content-body" id="body-${comment.id}">
          ${comment.renderedHtml || escapeHtml(comment.content)}
        </div>

        ${!comment.isDeleted ? `
          <div class="comment-card-footer">
            <button type="button" class="comment-action-link" data-action="reply" data-id="${comment.id}">Reply</button>
            ${canManage ? `
              <button type="button" class="comment-action-link" data-action="edit" data-id="${comment.id}">Edit</button>
              <button type="button" class="comment-action-link danger-action" data-action="delete" data-id="${comment.id}">Delete</button>
            ` : ''}
          </div>
        ` : ''}

        <div id="reply-box-${comment.id}" style="display: none;"></div>
        <div id="edit-box-${comment.id}" style="display: none;"></div>

        <div class="comment-replies-list" id="replies-list-${comment.id}" style="${(comment.replies && comment.replies.length > 0) ? '' : 'display: none;'}"></div>
      `;

      // Render Replies
      const repliesList = card.querySelector(`#replies-list-${comment.id}`);
      if (comment.replies && Array.isArray(comment.replies)) {
        comment.replies.forEach(reply => {
          const replyCard = document.createElement('div');
          replyCard.className = 'comment-reply-card';
          replyCard.id = `comment-card-${reply.id}`;

          const isReplyAuthor = (currentUser.username && reply.author === currentUser.username);
          const canManageReply = isReplyAuthor || currentUser.isAdmin;
          const replyInitials = getInitials(reply.author);
          const replyRelTime = formatRelativeTime(reply.createdAt);
          const replyEditedNotice = reply.updatedAt ? `<span class="comment-edited-tag" title="Edited on ${escapeHtml(reply.updatedAt)}">(edited)</span>` : '';

          replyCard.innerHTML = `
            <div class="comment-meta-row">
              <div class="comment-author-info">
                <div class="comment-avatar" style="width: 26px; height: 26px; font-size: 0.75rem;">${escapeHtml(replyInitials)}</div>
                <span class="comment-author-name" style="font-size: 0.88rem;">${escapeHtml(reply.author)}</span>
                <span class="comment-role-badge role-${escapeHtml(reply.role || 'viewer')}">${escapeHtml(reply.role || 'viewer')}</span>
                <span class="comment-timestamp" title="${escapeHtml(reply.createdAt || '')}">${escapeHtml(replyRelTime)}${replyEditedNotice}</span>
              </div>
            </div>

            <div class="comment-content-body" id="body-${reply.id}">
              ${reply.renderedHtml || escapeHtml(reply.content)}
            </div>

            ${!reply.isDeleted && canManageReply ? `
              <div class="comment-card-footer">
                <button type="button" class="comment-action-link" data-action="edit-reply" data-id="${reply.id}" data-parent="${comment.id}">Edit</button>
                <button type="button" class="comment-action-link danger-action" data-action="delete-reply" data-id="${reply.id}">Delete</button>
              </div>
            ` : ''}

            <div id="edit-box-${reply.id}" style="display: none;"></div>
          `;

          // Bind edit/delete on reply
          const editBtn = replyCard.querySelector(`[data-action="edit-reply"]`);
          if (editBtn) {
            editBtn.addEventListener('click', () => {
              showInlineEditor(replyCard.querySelector(`#edit-box-${reply.id}`), reply, book, slug, onUpdate);
            });
          }

          const delBtn = replyCard.querySelector(`[data-action="delete-reply"]`);
          if (delBtn) {
            delBtn.addEventListener('click', () => {
              handleDelete(reply.id, book, slug, onUpdate);
            });
          }

          repliesList.appendChild(replyCard);
        });
      }

      // Bind Reply Action
      const replyBtn = card.querySelector(`[data-action="reply"]`);
      if (replyBtn) {
        replyBtn.addEventListener('click', () => {
          showInlineReplyBox(card.querySelector(`#reply-box-${comment.id}`), comment.id, book, slug, onUpdate);
        });
      }

      // Bind Edit Action
      const editBtn = card.querySelector(`[data-action="edit"]`);
      if (editBtn) {
        editBtn.addEventListener('click', () => {
          showInlineEditor(card.querySelector(`#edit-box-${comment.id}`), comment, book, slug, onUpdate);
        });
      }

      // Bind Delete Action
      const delBtn = card.querySelector(`[data-action="delete"]`);
      if (delBtn) {
        delBtn.addEventListener('click', () => {
          handleDelete(comment.id, book, slug, onUpdate);
        });
      }

      container.appendChild(card);
    });
  }

  function showInlineReplyBox(container, parentId, book, slug, onUpdate) {
    if (container.style.display !== 'none' && container.innerHTML !== '') {
      container.style.display = 'none';
      return;
    }

    container.style.display = 'block';
    container.innerHTML = `
      <div class="inline-reply-box">
        <textarea class="comment-textarea" placeholder="Write a direct reply..." style="min-height: 70px;"></textarea>
        <div class="inline-actions">
          <button type="button" class="btn btn-outline btn-sm btn-cancel-reply">Cancel</button>
          <button type="button" class="btn btn-primary btn-sm btn-submit-reply">Post Reply</button>
        </div>
      </div>
    `;

    const textarea = container.querySelector('textarea');
    textarea.focus();

    container.querySelector('.btn-cancel-reply').addEventListener('click', () => {
      container.style.display = 'none';
    });

    container.querySelector('.btn-submit-reply').addEventListener('click', () => {
      const content = textarea.value.trim();
      if (!content) return;

      const submitBtn = container.querySelector('.btn-submit-reply');
      submitBtn.disabled = true;
      submitBtn.textContent = 'Posting...';

      const formData = new FormData();
      formData.append('action', 'ext_comments_add');
      formData.append('book', book);
      formData.append('slug', slug);
      formData.append('parentId', parentId);
      formData.append('content', content);

      fetch('api/admin.php', { method: 'POST', body: formData })
      .then(res => res.json())
      .then(resp => {
        if (resp.success) {
          container.style.display = 'none';
          // Reload comments from server
          fetchCommentsAndRefresh(book, slug, onUpdate);
        } else {
          submitBtn.disabled = false;
          submitBtn.textContent = 'Post Reply';
          alert(resp.error || 'Failed to post reply.');
        }
      })
      .catch(() => {
        submitBtn.disabled = false;
        submitBtn.textContent = 'Post Reply';
        alert('Network error posting reply.');
      });
    });
  }

  function showInlineEditor(container, comment, book, slug, onUpdate) {
    if (container.style.display !== 'none' && container.innerHTML !== '') {
      container.style.display = 'none';
      return;
    }

    container.style.display = 'block';
    container.innerHTML = `
      <div class="inline-edit-box">
        <textarea class="comment-textarea" style="min-height: 80px;">${escapeHtml(comment.content || '')}</textarea>
        <div class="inline-actions">
          <button type="button" class="btn btn-outline btn-sm btn-cancel-edit">Cancel</button>
          <button type="button" class="btn btn-primary btn-sm btn-save-edit">Save Changes</button>
        </div>
      </div>
    `;

    const textarea = container.querySelector('textarea');
    textarea.focus();

    container.querySelector('.btn-cancel-edit').addEventListener('click', () => {
      container.style.display = 'none';
    });

    container.querySelector('.btn-save-edit').addEventListener('click', () => {
      const newContent = textarea.value.trim();
      if (!newContent) return;

      const saveBtn = container.querySelector('.btn-save-edit');
      saveBtn.disabled = true;
      saveBtn.textContent = 'Saving...';

      const formData = new FormData();
      formData.append('action', 'ext_comments_edit');
      formData.append('book', book);
      formData.append('slug', slug);
      formData.append('commentId', comment.id);
      formData.append('content', newContent);

      fetch('api/admin.php', { method: 'POST', body: formData })
      .then(res => res.json())
      .then(resp => {
        if (resp.success) {
          container.style.display = 'none';
          fetchCommentsAndRefresh(book, slug, onUpdate);
        } else {
          saveBtn.disabled = false;
          saveBtn.textContent = 'Save Changes';
          alert(resp.error || 'Failed to save comment.');
        }
      })
      .catch(() => {
        saveBtn.disabled = false;
        saveBtn.textContent = 'Save Changes';
        alert('Network error saving comment.');
      });
    });
  }

  function handleDelete(commentId, book, slug, onUpdate) {
    if (!confirm('Are you sure you want to delete this comment?')) {
      return;
    }

    const formData = new FormData();
    formData.append('action', 'ext_comments_delete');
    formData.append('book', book);
    formData.append('slug', slug);
    formData.append('commentId', commentId);

    fetch('api/admin.php', { method: 'POST', body: formData })
    .then(res => res.json())
    .then(resp => {
      if (resp.success) {
        fetchCommentsAndRefresh(book, slug, onUpdate);
      } else {
        alert(resp.error || 'Failed to delete comment.');
      }
    })
    .catch(() => {
      alert('Network error deleting comment.');
    });
  }

  function fetchCommentsAndRefresh(book, slug, onUpdate) {
    fetch(`api/admin.php?action=ext_comments_get&book=${encodeURIComponent(book)}&slug=${encodeURIComponent(slug)}`)
    .then(res => res.json())
    .then(resp => {
      if (resp.success) {
        const streamContainer = document.getElementById('comments-stream-container');
        const countBadge = document.getElementById('comments-count-badge');
        if (streamContainer) {
          let count = 0;
          (resp.comments || []).forEach(c => {
            if (!c.isDeleted) count++;
            if (c.replies) c.replies.forEach(r => { if (!r.isDeleted) count++; });
          });
          if (countBadge) countBadge.textContent = count;
          renderStream(streamContainer, resp.comments || [], resp.currentUser, book, slug, onUpdate);
        }
      }
    });
  }

  // Lightweight preview converter for composer
  function parseClientMarkdown(text) {
    let html = escapeHtml(text);
    // Bold
    html = html.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
    // Italic
    html = html.replace(/\*(.*?)\*/g, '<em>$1</em>');
    // Inline code
    html = html.replace(/`([^`]+)`/g, '<code>$1</code>');
    // Line breaks
    html = html.replace(/\n/g, '<br>');
    return html;
  }

  // Initialize on DOM ready
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initComments);
  } else {
    initComments();
  }
})();
