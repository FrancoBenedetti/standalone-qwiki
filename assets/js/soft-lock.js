/**
 * Standalone Qwiki - Document Soft Lock System
 * Prevents concurrent editing collisions across users and browser tabs.
 */
(function(window) {
  'use strict';

  // 1. Ephemeral Tab UUID in sessionStorage
  const TAB_KEY = 'qwiki_tab_id';
  let tabId = sessionStorage.getItem(TAB_KEY);
  if (!tabId) {
    tabId = (typeof crypto !== 'undefined' && crypto.randomUUID)
      ? crypto.randomUUID()
      : 'tab-' + Math.random().toString(36).substring(2, 11) + '-' + Date.now();
    sessionStorage.setItem(TAB_KEY, tabId);
  }

  // 2. BroadcastChannel for zero-latency local tab communication
  let broadcast = null;
  if ('BroadcastChannel' in window) {
    try {
      broadcast = new BroadcastChannel('qwiki_doc_locks');
    } catch (e) {
      console.warn('BroadcastChannel not supported or blocked:', e);
    }
  }

  // State
  let activeFile = null;
  let heartbeatTimer = null;
  let lastUserActivity = Date.now();
  let isIdle = false;
  let onEvictedCallback = null;

  const HEARTBEAT_INTERVAL_MS = 20000; // 20 seconds
  const IDLE_TIMEOUT_MS = 15 * 60 * 1000; // 15 minutes

  // Activity tracking
  function recordActivity() {
    lastUserActivity = Date.now();
    if (isIdle && activeFile) {
      isIdle = false;
      // Re-trigger heartbeat immediately upon waking
      heartbeat();
    }
  }
  ['keydown', 'mousemove', 'mousedown', 'touchstart', 'scroll'].forEach(evt => {
    window.addEventListener(evt, recordActivity, { passive: true });
  });

  // Heartbeat function
  async function heartbeat() {
    if (!activeFile) return;

    // Check idle
    if (Date.now() - lastUserActivity > IDLE_TIMEOUT_MS) {
      isIdle = true;
      console.log('[SoftLock] Editor paused due to inactivity.');
      showNotification('Editor lock paused due to inactivity (15m). Click inside editor to resume.', 'warning');
      return;
    }

    try {
      const formData = new FormData();
      formData.append('action', 'lock_heartbeat');
      formData.append('file', activeFile);
      formData.append('tab_id', tabId);

      const res = await fetch('api/admin.php', { method: 'POST', body: formData });
      const data = await res.json();

      if (!data.success) {
        console.warn('[SoftLock] Heartbeat failed:', data);
        if (data.code === 'LOCK_TAKEN_OVER' || data.code === 'LOCK_EXPIRED' || data.code === 'LOCK_LOST') {
          handleEviction(data.lockedBy || 'another session');
        }
      }
    } catch (err) {
      console.warn('[SoftLock] Heartbeat network error:', err);
    }
  }

  function startHeartbeat() {
    stopHeartbeat();
    heartbeatTimer = setInterval(heartbeat, HEARTBEAT_INTERVAL_MS);
  }

  function stopHeartbeat() {
    if (heartbeatTimer) {
      clearInterval(heartbeatTimer);
      heartbeatTimer = null;
    }
  }

  // When another tab or user evicts this tab
  function handleEviction(lockedBy) {
    stopHeartbeat();
    const file = activeFile;
    activeFile = null;

    showNotification(
      `⚠️ Lock lost: Editing was taken over by ${lockedBy}. Your local changes have been preserved in browser storage.`,
      'danger'
    );

    if (typeof onEvictedCallback === 'function') {
      onEvictedCallback(file, lockedBy);
    }
  }

  // Teardown: release on tab unload
  function onUnload() {
    if (activeFile) {
      const payload = new URLSearchParams();
      payload.append('action', 'lock_release');
      payload.append('file', activeFile);
      payload.append('tab_id', tabId);

      if (navigator.sendBeacon) {
        navigator.sendBeacon('api/admin.php', payload);
      } else {
        fetch('api/admin.php', { method: 'POST', body: payload, keepalive: true }).catch(() => {});
      }

      if (broadcast) {
        broadcast.postMessage({ type: 'RELEASED', file: activeFile, tabId: tabId });
      }
    }
  }
  window.addEventListener('beforeunload', onUnload);
  window.addEventListener('pagehide', onUnload);

  // Resume on tab visibility
  document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'visible' && activeFile && !isIdle) {
      heartbeat();
    }
  });

  // UI Notification banner
  function showNotification(msg, type = 'info') {
    let banner = document.getElementById('qwiki-lock-banner');
    if (!banner) {
      banner = document.createElement('div');
      banner.id = 'qwiki-lock-banner';
      banner.style.cssText = 'padding: 10px 16px; margin-bottom: 1rem; border-radius: 8px; font-size: 0.9rem; font-weight: 500; display: flex; align-items: center; justify-content: space-between; gap: 12px; transition: all 0.2s ease; z-index: 100;';
      const container = document.querySelector('.article-content') || document.getElementById('content-body') || document.body;
      container.parentNode.insertBefore(banner, container);
    }

    if (type === 'danger') {
      banner.style.background = 'rgba(239, 68, 68, 0.15)';
      banner.style.color = '#ef4444';
      banner.style.border = '1px solid rgba(239, 68, 68, 0.3)';
    } else if (type === 'warning') {
      banner.style.background = 'rgba(245, 158, 11, 0.15)';
      banner.style.color = '#f59e0b';
      banner.style.border = '1px solid rgba(245, 158, 11, 0.3)';
    } else {
      banner.style.background = 'rgba(59, 130, 246, 0.15)';
      banner.style.color = '#3b82f6';
      banner.style.border = '1px solid rgba(59, 130, 246, 0.3)';
    }

    banner.innerHTML = `<span>${msg}</span><button type="button" style="background:none;border:none;color:inherit;cursor:pointer;font-size:1.2rem;line-height:1;" onclick="document.getElementById('qwiki-lock-banner').style.display='none'">&times;</button>`;
    banner.style.display = 'flex';
  }

  function hideNotification() {
    const banner = document.getElementById('qwiki-lock-banner');
    if (banner) banner.style.display = 'none';
  }

  // Public API
  const SoftLock = {
    getTabId() {
      return tabId;
    },

    getActiveFile() {
      return activeFile;
    },

    onEvicted(fn) {
      onEvictedCallback = fn;
    },

    async checkStatus(filePath) {
      if (!filePath) return { locked: false };
      try {
        const res = await fetch(`api/admin.php?action=lock_status&file=${encodeURIComponent(filePath)}&tab_id=${encodeURIComponent(tabId)}`);
        const data = await res.json();
        return data.status || { locked: false };
      } catch (e) {
        return { locked: false };
      }
    },

    async acquire(filePath, force = false) {
      if (!filePath) return { success: false, error: 'No file specified' };

      const formData = new FormData();
      formData.append('action', 'lock_acquire');
      formData.append('file', filePath);
      formData.append('tab_id', tabId);
      if (force) formData.append('force', '1');

      try {
        const res = await fetch('api/admin.php', { method: 'POST', body: formData });
        const data = await res.json();

        if (data.success) {
          activeFile = filePath;
          lastUserActivity = Date.now();
          isIdle = false;
          startHeartbeat();
          hideNotification();

          if (broadcast) {
            broadcast.postMessage({
              type: 'LOCKED',
              file: filePath,
              tabId: tabId,
              user: data.lock ? data.lock.user : ''
            });
          }
          return { success: true, lock: data.lock };
        } else {
          return {
            success: false,
            code: data.code || 'LOCKED_BY_OTHER',
            lockedBy: data.lockedBy || 'another session',
            isSameUser: !!data.isSameUser,
            expiresIn: data.expiresIn || 0,
            error: data.error
          };
        }
      } catch (err) {
        return { success: false, error: 'Network error acquiring lock' };
      }
    },

    async release(filePath, force = false) {
      const fileToRelease = filePath || activeFile;
      if (!fileToRelease) return { success: true };

      stopHeartbeat();
      activeFile = null;
      hideNotification();

      const formData = new FormData();
      formData.append('action', 'lock_release');
      formData.append('file', fileToRelease);
      formData.append('tab_id', tabId);
      if (force) formData.append('force', '1');

      try {
        await fetch('api/admin.php', { method: 'POST', body: formData });
      } catch (e) {}

      if (broadcast) {
        broadcast.postMessage({ type: 'RELEASED', file: fileToRelease, tabId: tabId });
      }

      return { success: true };
    },

    // Draft backup in localStorage
    saveDraft(filePath, content) {
      if (!filePath) return;
      try {
        localStorage.setItem('qwiki_draft_' + filePath, JSON.stringify({
          content: content,
          timestamp: Date.now()
        }));
      } catch (e) {}
    },

    getDraft(filePath) {
      if (!filePath) return null;
      try {
        const raw = localStorage.getItem('qwiki_draft_' + filePath);
        return raw ? JSON.parse(raw) : null;
      } catch (e) {
        return null;
      }
    },

    clearDraft(filePath) {
      if (!filePath) return;
      try {
        localStorage.removeItem('qwiki_draft_' + filePath);
      } catch (e) {}
    },

    // Custom Modal for Locked Notification
    promptLockConflict(lockInfo, onTakeover, onCancel) {
      const modalId = 'qwiki-lock-conflict-modal';
      let modal = document.getElementById(modalId);
      if (!modal) {
        modal = document.createElement('div');
        modal.id = modalId;
        modal.className = 'modal';
        document.body.appendChild(modal);
      }

      const who = lockInfo.isSameUser
        ? 'You have this document open for editing in another browser tab'
        : `This document is currently being edited by <strong>${escapeHtml(lockInfo.lockedBy)}</strong>`;

      modal.innerHTML = `
        <div class="modal-dialog" style="max-width: 480px;">
          <div class="modal-header">
            <h3 class="modal-title">🔒 Document Currently In Edit Mode</h3>
            <button type="button" class="btn-close" id="lock-modal-btn-close">&times;</button>
          </div>
          <div class="modal-body" style="padding: 1.25rem;">
            <p style="margin-bottom: 0.75rem; font-size: 0.95rem; line-height: 1.5;">
              ${who}.
            </p>
            <p style="color: var(--color-text-muted, #64748b); font-size: 0.85rem; margin-bottom: 1.25rem;">
              Editing simultaneously from two places can cause changes to overwrite each other. You can wait for the active session to finish, or force takeover the lock.
            </p>
            <div style="display: flex; justify-content: flex-end; gap: 0.75rem;">
              <button type="button" class="btn btn-outline" id="lock-modal-btn-cancel">Cancel</button>
              <button type="button" class="btn btn-primary" id="lock-modal-btn-takeover" style="background-color: #ef4444; border-color: #ef4444; color: #fff;">
                Take Over / Break Lock
              </button>
            </div>
          </div>
        </div>
      `;

      modal.classList.add('open');
      modal.style.display = 'flex';

      function closeModal() {
        modal.classList.remove('open');
        modal.style.display = 'none';
      }

      document.getElementById('lock-modal-btn-close').onclick = () => {
        closeModal();
        if (onCancel) onCancel();
      };
      document.getElementById('lock-modal-btn-cancel').onclick = () => {
        closeModal();
        if (onCancel) onCancel();
      };
      document.getElementById('lock-modal-btn-takeover').onclick = () => {
        closeModal();
        if (onTakeover) onTakeover();
      };
    }
  };

  // Helper
  function escapeHtml(str) {
    if (!str) return '';
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  // Cross-tab broadcast listener
  if (broadcast) {
    broadcast.onmessage = (event) => {
      const data = event.data;
      if (!data || !data.file) return;

      const currentFile = (document.getElementById('btn-save-inline-markdown')
        ? document.getElementById('btn-save-inline-markdown').getAttribute('data-file')
        : '') || '';

      if (data.file === currentFile) {
        if (data.type === 'LOCKED' && data.tabId !== tabId) {
          // If we are currently editing, we got taken over!
          if (activeFile === data.file) {
            handleEviction(data.user || 'another tab');
          } else {
            // We are reading: show banner
            showNotification(`🔒 This document is currently being edited in another tab by ${escapeHtml(data.user || 'another session')}.`, 'warning');
          }
        } else if (data.type === 'RELEASED') {
          hideNotification();
        }
      }
    };
  }

  window.SoftLock = SoftLock;
})(window);
