document.addEventListener('DOMContentLoaded', () => {
  // Theme Switcher
  const themeToggleBtn = document.getElementById('theme-toggle');

  if (themeToggleBtn) {
    themeToggleBtn.addEventListener('click', () => {
      const currentTheme = document.documentElement.getAttribute('data-theme') || 'dark';
      const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
      document.documentElement.setAttribute('data-theme', newTheme);
      localStorage.setItem('qwiki_theme', newTheme);
      document.cookie = 'qwiki_theme=' + encodeURIComponent(newTheme) + '; path=/; max-age=31536000; SameSite=Lax';
      
      if (typeof window.syncTuiEditorTheme === 'function') {
        window.syncTuiEditorTheme(newTheme);
      }
      if (typeof window.reRenderMermaidDiagrams === 'function') {
        window.reRenderMermaidDiagrams(newTheme);
      }
    });
  }

  // Restore saved sidebar width
  const savedWidth = localStorage.getItem('qwiki_sidebar_width');
  if (savedWidth) {
    document.documentElement.style.setProperty('--sidebar-width', savedWidth + 'px');
  }

  // Mobile Sidebar Toggle
  const mobileToggle = document.getElementById('mobile-toggle');
  const sidebar = document.getElementById('app-sidebar');
  const resizer = document.getElementById('sidebar-resizer');

  if (mobileToggle && sidebar) {
    mobileToggle.addEventListener('click', () => {
      sidebar.classList.toggle('open');
    });
  }

  // Sidebar Drag Resizing Engine
  if (resizer && sidebar) {
    let isResizing = false;

    resizer.addEventListener('mousedown', (e) => {
      e.preventDefault();
      isResizing = true;
      resizer.classList.add('resizing');
      document.body.style.cursor = 'col-resize';
      document.body.style.userSelect = 'none';
    });

    document.addEventListener('mousemove', (e) => {
      if (!isResizing) return;
      let newWidth = e.clientX;
      if (newWidth < 200) newWidth = 200;
      if (newWidth > 550) newWidth = 550;

      document.documentElement.style.setProperty('--sidebar-width', newWidth + 'px');
      localStorage.setItem('qwiki_sidebar_width', newWidth);
    });

    document.addEventListener('mouseup', () => {
      if (isResizing) {
        isResizing = false;
        resizer.classList.remove('resizing');
        document.body.style.cursor = '';
        document.body.style.userSelect = '';
      }
    });
  }

  // Category Accordion State Persistence & Scroll Position Management
  function getCategoryId(catItem) {
    if (!catItem) return null;
    return catItem.getAttribute('data-category-id') 
      || catItem.getAttribute('data-node-id')
      || catItem.querySelector('.nav-document-list')?.getAttribute('data-parent-node-id')
      || (catItem.classList.contains('nav-subwikis-group') ? 'subwikis' : null);
  }

  function getSavedCategoryStates() {
    try {
      return JSON.parse(sessionStorage.getItem('qwiki_category_states') || '{}');
    } catch (e) {
      return {};
    }
  }

  function saveCategoryState(catId, isCollapsed) {
    if (!catId) return;
    const states = getSavedCategoryStates();
    states[catId] = isCollapsed ? 'collapsed' : 'expanded';
    try {
      sessionStorage.setItem('qwiki_category_states', JSON.stringify(states));
    } catch (e) {}
  }

  // Restore saved category accordion states
  const savedCatStates = getSavedCategoryStates();
  document.querySelectorAll('.nav-category-item').forEach(catItem => {
    const hasActiveLink = !!catItem.querySelector('.nav-link.active');
    if (hasActiveLink) {
      catItem.classList.remove('collapsed');
      return;
    }
    const catId = getCategoryId(catItem);
    if (catId && savedCatStates[catId] !== undefined) {
      if (savedCatStates[catId] === 'expanded') {
        catItem.classList.remove('collapsed');
      } else if (savedCatStates[catId] === 'collapsed') {
        catItem.classList.add('collapsed');
      }
    }
  });

  // Category Accordion Toggle Listener
  document.querySelectorAll('.nav-category-header').forEach(header => {
    header.addEventListener('click', (e) => {
      if (e.target.closest('.btn-edit-cat-icon') || e.target.closest('.drag-handle')) return;
      const catItem = header.closest('.nav-category-item');
      if (catItem) {
        catItem.classList.toggle('collapsed');
        const catId = getCategoryId(catItem);
        if (catId) {
          saveCategoryState(catId, catItem.classList.contains('collapsed'));
        }
      }
    });
  });

  // Sidebar Scroll Position Persistence
  const sidebarNav = document.querySelector('.sidebar-nav');
  if (sidebarNav) {
    const rawSavedScroll = sessionStorage.getItem('qwiki_sidebar_scroll');
    const hasSavedScroll = rawSavedScroll !== null && !isNaN(parseInt(rawSavedScroll, 10));

    if (hasSavedScroll) {
      const targetScroll = parseInt(rawSavedScroll, 10);
      sidebarNav.scrollTop = targetScroll;
      if (typeof requestAnimationFrame === 'function') {
        requestAnimationFrame(() => {
          sidebarNav.scrollTop = targetScroll;
        });
      }
    }

    const activeLink = sidebarNav.querySelector('.nav-link.active');
    if (activeLink) {
      if (!hasSavedScroll) {
        // First visit or fresh tab: ensure active link is visible in sidebar
        if (typeof activeLink.scrollIntoView === 'function') {
          activeLink.scrollIntoView({ block: 'nearest', inline: 'nearest' });
        }
      } else {
        // If active link is outside visible area (e.g. navigated via content breadcrumb / next link), align it
        if (typeof activeLink.getBoundingClientRect === 'function' && typeof sidebarNav.getBoundingClientRect === 'function') {
          const linkRect = activeLink.getBoundingClientRect();
          const navRect = sidebarNav.getBoundingClientRect();
          const isVisible = (linkRect.top >= navRect.top && linkRect.bottom <= navRect.bottom);
          if (!isVisible && typeof activeLink.scrollIntoView === 'function') {
            activeLink.scrollIntoView({ block: 'nearest', inline: 'nearest' });
          }
        }
      }
    }

    // Persist scroll position when a sidebar link is clicked
    sidebarNav.addEventListener('click', (e) => {
      const link = e.target.closest('a');
      if (link && (!link.target || link.target === '_self')) {
        try {
          sessionStorage.setItem('qwiki_sidebar_scroll', sidebarNav.scrollTop);
        } catch (e) {}
      }
    });

    // Also persist scroll position on beforeunload (browser reload or tab refresh)
    window.addEventListener('beforeunload', () => {
      try {
        sessionStorage.setItem('qwiki_sidebar_scroll', sidebarNav.scrollTop);
      } catch (e) {}
    });
  }

  // Sidebar Filter Search (with Auto-Expand)
  const searchInput = document.getElementById('search-input');
  const sidebarSearchClear = document.getElementById('sidebar-search-clear');
  if (searchInput) {
    let searchTimeout = null;
    let abortController = null;
    let preSearchCollapsedState = null;

    if (sidebarSearchClear && searchInput.value.length > 0) {
      sidebarSearchClear.style.display = 'flex';
    }

    function clearSearch() {
      searchInput.value = '';
      if (sidebarSearchClear) sidebarSearchClear.style.display = 'none';
      clearTimeout(searchTimeout);
      if (abortController) abortController.abort();

      document.querySelectorAll('.sidebar-nav .nav-link').forEach(link => {
        link.style.display = '';
      });

      if (preSearchCollapsedState) {
        document.querySelectorAll('.nav-category-item').forEach(catItem => {
          if (preSearchCollapsedState.has(catItem)) {
            catItem.classList.toggle('collapsed', preSearchCollapsedState.get(catItem));
          }
        });
        preSearchCollapsedState = null;
      }
      searchInput.focus();
    }

    if (sidebarSearchClear) {
      sidebarSearchClear.addEventListener('click', clearSearch);
    }

    searchInput.addEventListener('keydown', (e) => {
      if (e.key === 'Escape' && searchInput.value) {
        e.preventDefault();
        clearSearch();
      }
    });

    searchInput.addEventListener('input', (e) => {
      const term = e.target.value.toLowerCase().trim();
      if (sidebarSearchClear) {
        sidebarSearchClear.style.display = e.target.value.length > 0 ? 'flex' : 'none';
      }
      clearTimeout(searchTimeout);
      if (abortController) abortController.abort();

      if (term === '') {
        document.querySelectorAll('.sidebar-nav .nav-link').forEach(link => {
          link.style.display = '';
        });
        if (preSearchCollapsedState) {
          document.querySelectorAll('.nav-category-item').forEach(catItem => {
            if (preSearchCollapsedState.has(catItem)) {
              catItem.classList.toggle('collapsed', preSearchCollapsedState.get(catItem));
            }
          });
          preSearchCollapsedState = null;
        }
        return;
      }

      if (!preSearchCollapsedState) {
        preSearchCollapsedState = new Map();
        document.querySelectorAll('.nav-category-item').forEach(catItem => {
          preSearchCollapsedState.set(catItem, catItem.classList.contains('collapsed'));
        });
      }

      searchTimeout = setTimeout(async () => {
        abortController = new AbortController();
        try {
          const res = await fetch(`api/search.php?q=${encodeURIComponent(term)}`, { signal: abortController.signal });
          const data = await res.json();
          if (data.success) {
            const matchedSlugs = data.results;
            
            document.querySelectorAll('.sidebar-nav > .nav-link').forEach(topLink => {
              const topSlug = topLink.getAttribute('data-doc-slug') || '';
              const text = topLink.textContent.toLowerCase();
              if (matchedSlugs.includes(topSlug) || text.includes(term)) {
                topLink.style.display = 'flex';
              } else {
                topLink.style.display = 'none';
              }
            });

            document.querySelectorAll('.nav-category-item').forEach(catItem => {
              let catMatch = false;
              const catTitle = (catItem.getAttribute('data-node-title') || '').toLowerCase();
              const catDesc = (catItem.getAttribute('data-node-description') || '').toLowerCase();
              const catSelfMatches = catTitle.includes(term) || catDesc.includes(term);

              catItem.querySelectorAll('.nav-link').forEach(link => {
                const linkSlug = link.getAttribute('data-doc-slug');
                const url = new URL(link.href, window.location.origin);
                const pathnameParts = url.pathname.split('/').filter(Boolean);
                const chapterSlug = linkSlug || url.searchParams.get('chapter') || url.searchParams.get('doc') || pathnameParts[pathnameParts.length - 1];
                const text = link.textContent.toLowerCase();
                
                if (catSelfMatches || matchedSlugs.includes(chapterSlug) || text.includes(term)) {
                  link.style.display = 'flex';
                  catMatch = true;
                } else {
                  link.style.display = 'none';
                }
              });
              
              if (catMatch || catSelfMatches) {
                catItem.classList.remove('collapsed');
              } else {
                catItem.classList.add('collapsed');
              }
            });
          }
        } catch (err) {
          if (err.name !== 'AbortError') {
             console.error('Search failed', err);
          }
        }
      }, 300);
    });
  }

  // Generic Modal Close handler
  document.querySelectorAll('[data-close]').forEach(btn => {
    btn.addEventListener('click', () => {
      const modalId = btn.getAttribute('data-close');
      const modal = document.getElementById(modalId);
      if (modal) modal.classList.remove('open');
    });
  });

  // User Dropdown Toggle
  const userDropdownToggle = document.getElementById('user-dropdown-toggle');
  if (userDropdownToggle) {
    const dropdownMenu = userDropdownToggle.nextElementSibling;
    userDropdownToggle.addEventListener('click', (e) => {
      e.stopPropagation();
      dropdownMenu.classList.toggle('show');
    });

    document.addEventListener('click', (e) => {
      if (!userDropdownToggle.contains(e.target) && !dropdownMenu.contains(e.target)) {
        dropdownMenu.classList.remove('show');
      }
    });

    // Close user dropdown when any dropdown item is clicked
    dropdownMenu.addEventListener('click', (e) => {
      if (e.target.closest('.dropdown-item')) {
        dropdownMenu.classList.remove('show');
      }
    });
  }

  // Trigger buttons
  const triggers = [
    { btnId: 'btn-login', modalId: 'login-modal' },
    { btnId: 'btn-add-book', modalId: 'book-modal' },
    { btnId: 'btn-add-chapter', modalId: 'chapter-modal' },
    { btnId: 'btn-users', modalId: 'users-modal' },
    { btnId: 'btn-llm-keys', modalId: 'llm-keys-modal' },
    { btnId: 'btn-settings', modalId: 'settings-modal' },
    { btnId: 'btn-subwikis', modalId: 'subwikis-modal' },
    { btnId: 'btn-edit-chapter-meta', modalId: 'edit-chapter-modal' },
    { btnId: 'btn-replace-document', modalId: 'replace-document-modal' }
  ];

  triggers.forEach(({ btnId, modalId }) => {
    const btn = document.getElementById(btnId);
    const modal = document.getElementById(modalId);
    if (btn && modal) {
      btn.addEventListener('click', () => {
        modal.classList.add('open');
        if (userDropdownToggle && userDropdownToggle.nextElementSibling) {
          userDropdownToggle.nextElementSibling.classList.remove('show');
        }
        if (modalId === 'users-modal') {
          loadUsersList();
        }
        if (modalId === 'llm-keys-modal') {
          loadLlmKeysList();
        }
        if (modalId === 'subwikis-modal') {
          loadSubwikisList();
        }
        if (modalId === 'settings-modal') {
          const btnSettings = document.getElementById('btn-settings');
          if (btnSettings) populateThemes(document.getElementById('setting-site-theme'), btnSettings.getAttribute('data-theme'));
        }
        if (modalId === 'replace-document-modal') {
          const btnReplace = document.getElementById('btn-replace-document');
          if (btnReplace) {
            const curSlug = btnReplace.getAttribute('data-slug') || '';
            const curTitle = btnReplace.getAttribute('data-title') || '';
            const slugInput = document.getElementById('replace-document-slug');
            const titleDisplay = document.getElementById('replace-document-title-display');
            if (slugInput) slugInput.value = curSlug;
            if (titleDisplay) titleDisplay.textContent = curTitle;
            const fileInput = document.getElementById('replace-document-file-input');
            if (fileInput) fileInput.value = '';
          }
        }
        if (modalId === 'edit-chapter-modal') {
          const replFileInput = document.getElementById('edit-chapter-replacement-file');
          if (replFileInput) replFileInput.value = '';
          const btnMeta = document.getElementById('btn-edit-chapter-meta');
          if (btnMeta) {
            const curSlug = btnMeta.getAttribute('data-slug') || '';
            const curTitle = btnMeta.getAttribute('data-title') || '';
            const curType = btnMeta.getAttribute('data-type') || 'markdown';
            const curUrl = btnMeta.getAttribute('data-url') || '';
            const curEditUrl = btnMeta.getAttribute('data-edit-url') || '';
            const curFile = btnMeta.getAttribute('data-file') || '';
            const curBookId = btnMeta.getAttribute('data-category-id') || btnMeta.getAttribute('data-book-id') || '';

            const slugHidden = document.getElementById('edit-chapter-slug-hidden');
            if (slugHidden) slugHidden.value = curSlug;

            const slugInput = document.getElementById('edit-chapter-slug');
            if (slugInput) slugInput.value = curSlug;

            const titleInput = document.getElementById('edit-chapter-title');
            if (titleInput) titleInput.value = curTitle;

            const typeSelect = document.getElementById('edit-chapter-type');
            if (typeSelect) typeSelect.value = curType;

            const urlInput = document.getElementById('edit-chapter-url');
            if (urlInput) urlInput.value = curUrl;

            const editUrlInput = document.getElementById('edit-chapter-editurl');
            if (editUrlInput) editUrlInput.value = curEditUrl;

            const fileInput = document.getElementById('edit-chapter-file');
            if (fileInput) fileInput.value = curFile;

            const catSelect = document.getElementById('edit-chapter-category');
            if (catSelect && curBookId) catSelect.value = curBookId;

            populateThemes(document.getElementById('edit-chapter-theme'), btnMeta.getAttribute('data-theme'));
            const descInput = document.getElementById('edit-chapter-description');
            if (descInput && btnMeta.hasAttribute('data-description')) {
              descInput.value = btnMeta.getAttribute('data-description') || '';
            }
            const imgInput = document.getElementById('edit-chapter-image');
            if (imgInput && btnMeta.hasAttribute('data-image')) {
              imgInput.value = btnMeta.getAttribute('data-image') || '';
            }
            const shareableCheckbox = document.getElementById('edit-chapter-public-shareable');
            if (shareableCheckbox) {
              shareableCheckbox.checked = btnMeta.getAttribute('data-public-shareable') !== '0';
            }
            const shareKeyInput = document.getElementById('edit-chapter-share-key');
            if (shareKeyInput) {
              shareKeyInput.value = btnMeta.getAttribute('data-share-key') || '';
            }
            const regenKeyHidden = document.getElementById('edit-chapter-regenerate-key');
            if (regenKeyHidden) {
              regenKeyHidden.value = '0';
            }

            const isDocReadOnly = btnMeta.getAttribute('data-doc-readonly') === '1';
            const isDocProtected = btnMeta.getAttribute('data-doc-protected') === '1';
            const isParentLocked = btnMeta.getAttribute('data-parent-locked') === '1';
            const isDemoMode = document.getElementById('btn-reload-demo-package') !== null;

            const docReadOnlyInput = document.getElementById('edit-chapter-readonly-input');
            const docReadOnlyHelp = document.getElementById('edit-chapter-readonly-help');

            if (docReadOnlyInput) {
              docReadOnlyInput.checked = isDocReadOnly || isDocProtected;

              if (isParentLocked) {
                docReadOnlyInput.disabled = true;
                if (docReadOnlyHelp) {
                  docReadOnlyHelp.textContent = 'This document is inside a locked category and inherits its lock.';
                }
              } else if (isDemoMode && isDocProtected) {
                docReadOnlyInput.disabled = true;
                if (docReadOnlyHelp) {
                  docReadOnlyHelp.textContent = 'Protected demo documents cannot be unlocked in demo mode.';
                }
              } else if (isDocReadOnly) {
                docReadOnlyInput.disabled = false;
                if (docReadOnlyHelp) {
                  docReadOnlyHelp.textContent = 'Uncheck to unlock this document and allow edits/deletion.';
                }
              } else {
                docReadOnlyInput.disabled = false;
                if (docReadOnlyHelp) {
                  docReadOnlyHelp.textContent = 'Protected documents cannot be edited or deleted.';
                }
              }
            }

            const saveBtn = document.querySelector('#edit-chapter-form button[type="submit"]');
            if (saveBtn) {
              saveBtn.disabled = isParentLocked || (isDemoMode && isDocProtected);
            }
          }
          
          const btnEditResetKey = document.getElementById('btn-edit-modal-reset-key');
          if (btnEditResetKey) {
            btnEditResetKey.onclick = () => {
              const regenKeyHidden = document.getElementById('edit-chapter-regenerate-key');
              const shareKeyInput = document.getElementById('edit-chapter-share-key');
              if (regenKeyHidden) regenKeyHidden.value = '1';
              if (shareKeyInput) shareKeyInput.value = '(A new key will be generated upon save)';
            };
          }
          
          const typeSelect = document.getElementById('edit-chapter-type');
          const toggleFields = () => {
            if (!typeSelect) return;
            const gdocUrlGrp = document.getElementById('group-edit-gdoc-url');
            const gdocEditGrp = document.getElementById('group-edit-gdoc-editurl');
            const fileGrp = document.getElementById('group-edit-file');
            if (typeSelect.value === 'gdoc') {
              if (gdocUrlGrp) gdocUrlGrp.style.display = 'block';
              if (gdocEditGrp) gdocEditGrp.style.display = 'block';
              if (fileGrp) fileGrp.style.display = 'none';
            } else {
              if (gdocUrlGrp) gdocUrlGrp.style.display = 'none';
              if (gdocEditGrp) gdocEditGrp.style.display = 'none';
              if (fileGrp) fileGrp.style.display = 'block';
            }
          };
          if (typeSelect) {
            typeSelect.removeEventListener('change', toggleFields);
            typeSelect.addEventListener('change', toggleFields);
            toggleFields();
          }
        }
      });
    }
  });

  // Settings Logo Upload
  const logoUpload = document.getElementById('setting-logo-upload');
  const logoUrlHidden = document.getElementById('setting-logo-url');
  if (logoUpload && logoUrlHidden) {
    logoUpload.addEventListener('change', async (e) => {
      const file = e.target.files[0];
      if (!file) return;
      const formData = new FormData();
      formData.append('action', 'upload_image');
      formData.append('image', file);
      try {
        const res = await fetch('api/admin.php', { method: 'POST', body: formData });
        const data = await res.json();
        if (data.success) {
          logoUrlHidden.value = data.url;
          alert('Logo uploaded successfully. Save settings to apply.');
        } else {
          alert('Upload failed: ' + (data.error || 'Unknown error'));
        }
      } catch (err) {
        alert('Upload request failed');
      }
    });
  }

  // Theme Management
  let availableThemes = [];
  async function fetchAvailableThemes() {
    if (availableThemes.length > 0) return availableThemes;
    try {
      const res = await fetch('api/admin.php?action=list_themes');
      const data = await res.json();
      if (data.success) {
        availableThemes = data.themes;
      }
    } catch(e) {}
    return availableThemes;
  }

  async function populateThemes(selectEl, selectedValue) {
    if (!selectEl) return;
    const themes = await fetchAvailableThemes();
    // Keep first option (Inherit / Default)
    const firstOpt = selectEl.options[0];
    selectEl.innerHTML = '';
    if (firstOpt) selectEl.appendChild(firstOpt);
    
    themes.forEach(t => {
      const opt = document.createElement('option');
      opt.value = t;
      opt.textContent = t;
      if (t === selectedValue) opt.selected = true;
      selectEl.appendChild(opt);
    });
  }

  // Load and render user list in Users Modal
  async function loadUsersList() {
    const container = document.getElementById('users-list-container');
    if (!container) return;

    try {
      const res = await fetch('api/admin.php?action=list_users');
      const data = await res.json();

      if (data.success && Array.isArray(data.users)) {
        if (data.users.length === 0) {
          container.innerHTML = '<p style="color: var(--text-muted);">No users found.</p>';
          return;
        }

        let html = '<table style="width:100%; border-collapse:collapse; text-align:left; font-size:0.9rem;">';
        html += '<thead style="border-bottom:1px solid var(--border-color); color:var(--text-muted);">';
        html += '<tr><th style="padding:0.5rem;">Username</th><th style="padding:0.5rem;">Role</th><th style="padding:0.5rem;">Email</th><th style="padding:0.5rem;">2FA</th><th style="padding:0.5rem; text-align:right;">Actions</th></tr>';
        html += '</thead><tbody>';

        data.users.forEach(u => {
          const isPrimaryAdmin = (u.username.toLowerCase() === 'admin');
          const badgeClass = (u.role === 'admin') ? 'badge-md' : 'badge-pdf';
          const emailDisplay = u.email ? `${escapeHtml(u.email)} ${u.emailVerified ? '<span style="color:#10b981; font-weight:bold;" title="Verified">✓</span>' : '<span style="color:#f59e0b; font-size:0.75rem;" title="Unverified">(unverified)</span>'}` : '<span style="color:var(--text-muted);">-</span>';
          const twoFactorDisplay = u.has2fa ? '<span class="doc-badge badge-md" style="font-size:0.75rem; padding:0.1rem 0.35rem;">🔒 Active</span>' : '<span style="color:var(--text-muted); font-size:0.8rem;">Off</span>';
          
          html += `<tr style="border-bottom:1px solid var(--border-color);">`;
          html += `<td style="padding:0.6rem; font-weight:600; color:var(--text-primary);">${escapeHtml(u.username)}</td>`;
          html += `<td style="padding:0.6rem;"><span class="doc-badge ${badgeClass}">${escapeHtml(u.role)}</span></td>`;
          html += `<td style="padding:0.6rem; font-size:0.85rem;">${emailDisplay}</td>`;
          html += `<td style="padding:0.6rem;">${twoFactorDisplay}</td>`;
          html += `<td style="padding:0.6rem; text-align:right; white-space:nowrap;">`;
          html += `<button class="btn btn-outline btn-sm btn-change-user-pwd" data-username="${escapeHtml(u.username)}" style="padding:0.2rem 0.45rem; margin-right:0.25rem;" title="Change Password">🔑 Password</button>`;
          html += `<button class="btn btn-outline btn-sm btn-admin-copy-reset" data-username="${escapeHtml(u.username)}" style="padding:0.2rem 0.45rem; margin-right:0.25rem;" title="Generate one-time reset link">🔗 Reset Link</button>`;
          if (u.has2fa) {
            html += `<button class="btn btn-outline btn-sm btn-admin-reset-2fa" data-username="${escapeHtml(u.username)}" style="padding:0.2rem 0.45rem; margin-right:0.25rem; color:#f59e0b;" title="Disable 2FA for this user">🛡️ Reset 2FA</button>`;
          }
          if (!isPrimaryAdmin) {
            html += `<button class="btn btn-outline btn-sm btn-delete-user" data-username="${escapeHtml(u.username)}" style="padding:0.2rem 0.45rem; color:#f87171;">Delete</button>`;
          }
          html += `</td></tr>`;
        });

        html += '</tbody></table>';
        container.innerHTML = html;

        // Bind change password triggers
        container.querySelectorAll('.btn-change-user-pwd').forEach(pwdBtn => {
          pwdBtn.addEventListener('click', async () => {
            const targetUser = pwdBtn.getAttribute('data-username');
            const newPassword = prompt(`Enter new password for user "${targetUser}" (min 4 characters):`);
            if (!newPassword) return;
            if (newPassword.length < 4) {
              alert('Password must be at least 4 characters long.');
              return;
            }

            const formData = new FormData();
            formData.append('action', 'update_user_password');
            formData.append('username', targetUser);
            formData.append('newPassword', newPassword);

            const pwdRes = await fetch('api/admin.php', { method: 'POST', body: formData });
            const pwdData = await pwdRes.json();
            if (pwdData.success) {
              alert(`Password for user "${targetUser}" updated successfully.`);
            } else {
              alert('Password update failed: ' + (pwdData.error || 'Unknown error'));
            }
          });
        });

        // Bind admin copy reset link triggers
        container.querySelectorAll('.btn-admin-copy-reset').forEach(btn => {
          btn.addEventListener('click', async () => {
            const targetUser = btn.getAttribute('data-username');
            btn.disabled = true;
            btn.textContent = 'Generating...';
            try {
              const formData = new FormData();
              formData.append('action', 'generate_admin_reset_link');
              formData.append('username', targetUser);
              const res = await fetch('api/admin.php', { method: 'POST', body: formData });
              const data = await res.json();
              if (data.success && data.resetUrl) {
                if (navigator.clipboard) {
                  await navigator.clipboard.writeText(data.resetUrl);
                  alert(`✅ One-time reset link copied to clipboard for user "${targetUser}":\n\n${data.resetUrl}\n\n(Link is valid for 24 hours)`);
                } else {
                  prompt(`One-time reset link for "${targetUser}" (valid for 24 hours):`, data.resetUrl);
                }
              } else {
                alert('Failed to generate reset link: ' + (data.error || 'Unknown error'));
              }
            } catch (err) {
              alert('Network request failed');
            } finally {
              btn.disabled = false;
              btn.textContent = '🔗 Reset Link';
            }
          });
        });

        // Bind admin reset 2FA triggers
        container.querySelectorAll('.btn-admin-reset-2fa').forEach(btn => {
          btn.addEventListener('click', async () => {
            const targetUser = btn.getAttribute('data-username');
            if (!confirm(`Are you sure you want to disable Two-Factor Authentication for user "${targetUser}"?\n\nThey will be able to log in using their password directly.`)) return;

            const formData = new FormData();
            formData.append('action', 'disable_2fa');
            formData.append('username', targetUser);
            const res = await fetch('api/admin.php', { method: 'POST', body: formData });
            const data = await res.json();
            if (data.success) {
              alert(`Two-Factor Authentication disabled for user "${targetUser}".`);
              loadUsersList();
            } else {
              alert('Failed to disable 2FA: ' + (data.error || 'Unknown error'));
            }
          });
        });

        // Bind delete user triggers
        container.querySelectorAll('.btn-delete-user').forEach(delBtn => {
          delBtn.addEventListener('click', async () => {
            const targetUser = delBtn.getAttribute('data-username');
            if (!confirm(`Are you sure you want to delete user "${targetUser}"?`)) return;

            const formData = new FormData();
            formData.append('action', 'delete_user');
            formData.append('username', targetUser);

            const delRes = await fetch('api/admin.php', { method: 'POST', body: formData });
            const delData = await delRes.json();
            if (delData.success) {
              loadUsersList();
            } else {
              alert('Delete failed: ' + (delData.error || 'Unknown error'));
            }
          });
        });
      } else {
        container.innerHTML = '<p style="color:#f87171;">Failed to load user list.</p>';
      }
    } catch (err) {
      container.innerHTML = '<p style="color:#f87171;">Network error loading users.</p>';
    }
  }

  function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
  }

  // Open LLM Keys Modal directly from Settings Modal
  const btnOpenLlmFromSettings = document.getElementById('btn-open-llm-from-settings');
  if (btnOpenLlmFromSettings) {
    btnOpenLlmFromSettings.addEventListener('click', () => {
      const settingsModal = document.getElementById('settings-modal');
      if (settingsModal) settingsModal.classList.remove('open');
      const llmModal = document.getElementById('llm-keys-modal');
      if (llmModal) {
        llmModal.classList.add('open');
        loadLlmKeysList();
      }
    });
  }

  // Open Gemini AI Assistant Modal directly from Settings Modal
  const btnOpenGeminiFromSettings = document.getElementById('btn-open-gemini-from-settings');
  if (btnOpenGeminiFromSettings) {
    btnOpenGeminiFromSettings.addEventListener('click', () => {
      const settingsModal = document.getElementById('settings-modal');
      if (settingsModal) settingsModal.classList.remove('open');
      if (typeof window.openGeminiAssistant === 'function') {
        window.openGeminiAssistant('settings');
      } else {
        const geminiModal = document.getElementById('modal-gemini-assistant');
        if (geminiModal) {
          geminiModal.classList.add('open');
          geminiModal.classList.add('active');
        }
      }
    });
  }

  // Open Gemini AI Assistant Modal directly from LLM Keys Modal
  const btnOpenGeminiFromLlm = document.getElementById('btn-open-gemini-from-llm');
  if (btnOpenGeminiFromLlm) {
    btnOpenGeminiFromLlm.addEventListener('click', () => {
      const llmModal = document.getElementById('llm-keys-modal');
      if (llmModal) llmModal.classList.remove('open');
      if (typeof window.openGeminiAssistant === 'function') {
        window.openGeminiAssistant('settings');
      } else {
        const geminiModal = document.getElementById('modal-gemini-assistant');
        if (geminiModal) {
          geminiModal.classList.add('open');
          geminiModal.classList.add('active');
        }
      }
    });
  }

  // Load and render LLM access keys in LLM Keys Modal
  async function loadLlmKeysList() {
    const container = document.getElementById('llm-keys-list-container');
    if (!container) return;

    try {
      const res = await fetch('api/admin.php?action=list_llm_keys');
      const data = await res.json();

      if (data.success && Array.isArray(data.keys)) {
        if (data.keys.length === 0) {
          container.innerHTML = '<p style="color: var(--text-muted); font-size: 0.88rem;">No LLM access keys generated yet. Use the form above to generate your first key.</p>';
          return;
        }

        const currentOrigin = window.location.origin;
        const currentPath = window.location.pathname.replace(/\/[^/]*$/, '/');
        const apiBaseUrl = currentOrigin + currentPath + 'api/llm.php';

        let html = '<table style="width:100%; border-collapse:collapse; text-align:left; font-size:0.88rem;">';
        html += '<thead style="border-bottom:1px solid var(--border-color); color:var(--text-muted); font-size:0.82rem;">';
        html += '<tr>';
        html += '<th style="padding:0.6rem 0.5rem;">Name / Purpose</th>';
        html += '<th style="padding:0.6rem 0.5rem;">Scope</th>';
        html += '<th style="padding:0.6rem 0.5rem;">Types</th>';
        html += '<th style="padding:0.6rem 0.5rem;">Expiration</th>';
        html += '<th style="padding:0.6rem 0.5rem;">Last Used</th>';
        html += '<th style="padding:0.6rem 0.5rem;">Status</th>';
        html += '<th style="padding:0.6rem 0.5rem; text-align:right;">Actions</th>';
        html += '</tr></thead><tbody>';

        data.keys.forEach(k => {
          const isExpired = k.isExpired || k.effectiveStatus === 'expired';
          const isRevoked = k.status === 'revoked';

          let statusBadge = '<span class="doc-badge badge-md" style="background:#10b981; color:#fff;">Active</span>';
          if (isRevoked) {
            statusBadge = '<span class="doc-badge badge-pdf" style="background:#ef4444; color:#fff;">Revoked</span>';
          } else if (isExpired) {
            statusBadge = '<span class="doc-badge badge-pdf" style="background:#f59e0b; color:#fff;">Expired</span>';
          }

          const scopeText = (!k.category || k.category === 'all') ? '<span style="color:var(--text-muted);">All Categories</span>' : `<code>${escapeHtml(k.category)}</code>`;
          const typesList = (k.allowedTypes || ['markdown']).map(t => `<span class="doc-badge badge-md" style="padding:0.1rem 0.35rem; font-size:0.75rem; text-transform:uppercase;">${escapeHtml(t)}</span>`).join(' ');

          const expText = k.expiresAt ? escapeHtml(k.expiresAt) : '<span style="color:var(--text-muted);">Never</span>';
          const lastUsedText = k.lastUsedAt ? escapeHtml(k.lastUsedAt) : '<span style="color:var(--text-muted);">Never</span>';

          const treeUrl = `${apiBaseUrl}?mode=tree&key=${encodeURIComponent(k.key)}`;

          html += `<tr style="border-bottom:1px solid var(--border-color);">`;
          html += `<td style="padding:0.6rem 0.5rem;">`;
          html += `<strong>${escapeHtml(k.name)}</strong>`;
          html += `<div style="font-family:monospace; font-size:0.75rem; color:var(--text-muted); display:flex; align-items:center; gap:0.35rem; margin-top:0.2rem;">`;
          html += `<span>${escapeHtml(k.key.substring(0, 14))}...${escapeHtml(k.key.substring(k.key.length - 4))}</span>`;
          html += `<button type="button" class="btn-copy-llm-token" data-key="${escapeHtml(k.key)}" title="Copy full key token" style="background:none; border:none; cursor:pointer; font-size:0.85rem; padding:0; color:var(--primary-color);">📋</button>`;
          html += `</div></td>`;
          html += `<td style="padding:0.6rem 0.5rem;">${scopeText}</td>`;
          html += `<td style="padding:0.6rem 0.5rem;">${typesList}</td>`;
          html += `<td style="padding:0.6rem 0.5rem; font-size:0.82rem;">${expText}</td>`;
          html += `<td style="padding:0.6rem 0.5rem; font-size:0.82rem;">${lastUsedText}</td>`;
          html += `<td style="padding:0.6rem 0.5rem;">${statusBadge}</td>`;
          html += `<td style="padding:0.6rem 0.5rem; text-align:right; white-space:nowrap;">`;
          html += `<button type="button" class="btn btn-outline btn-sm btn-copy-llm-tree-url" data-url="${escapeHtml(treeUrl)}" title="Copy Tree API URL" style="padding:0.2rem 0.45rem; margin-right:0.3rem;">🔗 Tree URL</button>`;

          if (isRevoked) {
            html += `<button type="button" class="btn btn-outline btn-sm btn-toggle-llm-key" data-id="${escapeHtml(k.id)}" data-status="active" title="Reactivate key" style="padding:0.2rem 0.45rem; margin-right:0.3rem; color:#10b981;">Reactivate</button>`;
          } else {
            html += `<button type="button" class="btn btn-outline btn-sm btn-toggle-llm-key" data-id="${escapeHtml(k.id)}" data-status="revoked" title="Revoke key" style="padding:0.2rem 0.45rem; margin-right:0.3rem; color:#f59e0b;">Revoke</button>`;
          }

          if (isExpired) {
            html += `<button type="button" class="btn btn-outline btn-sm btn-extend-llm-key" data-id="${escapeHtml(k.id)}" title="Extend expiration date" style="padding:0.2rem 0.45rem; margin-right:0.3rem;">Extend</button>`;
          }

          html += `<button type="button" class="btn btn-outline btn-sm btn-delete-llm-key" data-id="${escapeHtml(k.id)}" data-name="${escapeHtml(k.name)}" title="Delete key" style="padding:0.2rem 0.45rem; color:#ef4444;">Delete</button>`;
          html += `</td></tr>`;
        });

        html += '</tbody></table>';
        container.innerHTML = html;

        // Bind copy key token
        container.querySelectorAll('.btn-copy-llm-token').forEach(btn => {
          btn.addEventListener('click', () => {
            const rawKey = btn.getAttribute('data-key');
            if (rawKey) {
              navigator.clipboard.writeText(rawKey).then(() => {
                const origText = btn.textContent;
                btn.textContent = '✓';
                setTimeout(() => { btn.textContent = origText; }, 1500);
              });
            }
          });
        });

        // Bind copy tree URL
        container.querySelectorAll('.btn-copy-llm-tree-url').forEach(btn => {
          btn.addEventListener('click', () => {
            const url = btn.getAttribute('data-url');
            if (url) {
              navigator.clipboard.writeText(url).then(() => {
                const origText = btn.textContent;
                btn.textContent = '✓ Copied';
                setTimeout(() => { btn.textContent = origText; }, 1500);
              });
            }
          });
        });

        // Bind toggle revoke/activate
        container.querySelectorAll('.btn-toggle-llm-key').forEach(btn => {
          btn.addEventListener('click', async () => {
            const keyId = btn.getAttribute('data-id');
            const targetStatus = btn.getAttribute('data-status');
            const formData = new FormData();
            formData.append('action', 'revoke_llm_key');
            formData.append('keyId', keyId);
            formData.append('status', targetStatus);

            const res = await fetch('api/admin.php', { method: 'POST', body: formData });
            const result = await res.json();
            if (result.success) {
              loadLlmKeysList();
            } else {
              alert('Failed to update key: ' + (result.error || 'Unknown error'));
            }
          });
        });

        // Bind extend expiry
        container.querySelectorAll('.btn-extend-llm-key').forEach(btn => {
          btn.addEventListener('click', async () => {
            const keyId = btn.getAttribute('data-id');
            const newExpiry = prompt('Enter new expiration date (e.g. 2026-12-31 23:59:59) or leave blank to make non-expiring:');
            if (newExpiry === null) return;

            const formData = new FormData();
            formData.append('action', 'update_llm_key_expiry');
            formData.append('keyId', keyId);
            formData.append('expiresAt', newExpiry.trim());

            const res = await fetch('api/admin.php', { method: 'POST', body: formData });
            const result = await res.json();
            if (result.success) {
              loadLlmKeysList();
            } else {
              alert('Failed to extend key: ' + (result.error || 'Unknown error'));
            }
          });
        });

        // Bind delete key
        container.querySelectorAll('.btn-delete-llm-key').forEach(btn => {
          btn.addEventListener('click', async () => {
            const keyId = btn.getAttribute('data-id');
            const keyName = btn.getAttribute('data-name');
            if (!confirm(`Are you sure you want to permanently delete the API key "${keyName}"?`)) return;

            const formData = new FormData();
            formData.append('action', 'delete_llm_key');
            formData.append('keyId', keyId);

            const res = await fetch('api/admin.php', { method: 'POST', body: formData });
            const result = await res.json();
            if (result.success) {
              loadLlmKeysList();
            } else {
              alert('Failed to delete key: ' + (result.error || 'Unknown error'));
            }
          });
        });

      } else {
        container.innerHTML = '<p style="color:#ef4444;">Failed to load LLM access keys.</p>';
      }
    } catch (err) {
      container.innerHTML = '<p style="color:#ef4444;">Network error loading keys.</p>';
    }
  }

  // Handle add LLM key form submission
  const addLlmKeyForm = document.getElementById('add-llm-key-form');
  if (addLlmKeyForm) {
    addLlmKeyForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      const formData = new FormData(addLlmKeyForm);
      formData.append('action', 'create_llm_key');

      // Collect allowed types from checkboxes
      const checkedTypes = [];
      addLlmKeyForm.querySelectorAll('input[name="allowedTypes[]"]:checked').forEach(cb => {
        checkedTypes.push(cb.value);
      });
      formData.delete('allowedTypes[]');
      formData.append('allowedTypes', checkedTypes.join(','));

      try {
        const res = await fetch('api/admin.php', { method: 'POST', body: formData });
        const result = await res.json();

        if (result.success && result.key) {
          addLlmKeyForm.reset();
          const mdCheck = addLlmKeyForm.querySelector('input[name="allowedTypes[]"][value="markdown"]');
          if (mdCheck) mdCheck.checked = true;

          loadLlmKeysList();

          const keyVal = result.key.key;
          prompt(`New LLM Access Key created successfully!\n\nKey Token (copied to clipboard if permitted):`, keyVal);
          try {
            navigator.clipboard.writeText(keyVal);
          } catch (e) {}
        } else {
          alert('Failed to generate key: ' + (result.error || 'Unknown error'));
        }
      } catch (err) {
        alert('Network error while generating API key.');
      }
    });
  }

  // Load and render subwikis in Subwikis Modal
  async function loadSubwikisList() {
    const tbody = document.getElementById('subwiki-table-body');
    if (!tbody) return;
    tbody.innerHTML = '<tr><td colspan="3" style="padding: 1.5rem; text-align: center; color: var(--text-muted);">Loading subwikis...</td></tr>';

    try {
      const res = await fetch('api/admin.php?action=list_subwikis');
      const data = await res.json();
      if (!data.success) {
        tbody.innerHTML = `<tr><td colspan="3" style="padding: 1rem; color: var(--danger-color, #ef4444); text-align: center;">Failed to load: ${escapeHtml(data.error || 'Unknown error')}</td></tr>`;
        return;
      }

      const subwikis = data.subwikis || [];
      if (subwikis.length === 0) {
        tbody.innerHTML = '<tr><td colspan="3" style="padding: 1.5rem; text-align: center; color: var(--text-muted);">No subwikis deployed yet. Use the form below to deploy one.</td></tr>';
        return;
      }

      tbody.innerHTML = subwikis.map(sub => `
        <tr style="border-bottom: 1px solid var(--border-color);">
          <td style="padding: 0.6rem 0.75rem;">
            <strong>${escapeHtml(sub.title || sub.slug)}</strong><br>
            <span style="font-size: 0.78rem; color: var(--text-muted); font-family: monospace;">${escapeHtml(sub.slug)}</span>
          </td>
          <td style="padding: 0.6rem 0.75rem;">
            <a href="${escapeHtml(sub.url)}" target="_blank" style="color: var(--accent-color); text-decoration: underline; font-weight: 500;">/${escapeHtml(sub.slug)}/ ↗</a>
          </td>
          <td style="padding: 0.6rem 0.75rem; text-align: right;">
            <button class="btn btn-outline btn-sm btn-delete-subwiki" data-slug="${escapeHtml(sub.slug)}" style="color: var(--danger-color, #ef4444); border-color: var(--danger-color, #ef4444); font-size: 0.8rem; padding: 0.2rem 0.5rem;">Delete</button>
          </td>
        </tr>
      `).join('');

      tbody.querySelectorAll('.btn-delete-subwiki').forEach(btn => {
        btn.addEventListener('click', async () => {
          const slug = btn.getAttribute('data-slug');
          if (!confirm(`Are you sure you want to remove subwiki "${slug}"?\n\nThis will unregister the subwiki from this parent wiki.`)) {
            return;
          }
          const deleteFiles = confirm(`Do you also want to permanently delete the physical directory "/${slug}" from the server?\n\nClick OK to delete files from disk, or Cancel to only unregister.`);
          try {
            const formData = new FormData();
            formData.append('action', 'delete_subwiki');
            formData.append('slug', slug);
            if (deleteFiles) formData.append('deleteFiles', '1');

            const delRes = await fetch('api/admin.php', { method: 'POST', body: formData });
            const delData = await delRes.json();
            if (delData.success) {
              loadSubwikisList();
            } else {
              alert('Failed to delete subwiki: ' + (delData.error || 'Unknown error'));
            }
          } catch (err) {
            alert('Request failed while deleting subwiki.');
          }
        });
      });
    } catch (err) {
      tbody.innerHTML = '<tr><td colspan="3" style="padding: 1rem; color: var(--danger-color, #ef4444); text-align: center;">Network error while fetching subwikis.</td></tr>';
    }
  }

  // Real-time Slug Checking for Subwiki Deploy Form
  const newSubwikiSlugInput = document.getElementById('new-subwiki-slug');
  const subwikiSlugFeedback = document.getElementById('subwiki-slug-feedback');
  let slugCheckTimeout = null;

  if (newSubwikiSlugInput && subwikiSlugFeedback) {
    newSubwikiSlugInput.addEventListener('input', () => {
      clearTimeout(slugCheckTimeout);
      const rawVal = newSubwikiSlugInput.value.trim().toLowerCase();
      if (!rawVal) {
        subwikiSlugFeedback.textContent = '';
        return;
      }

      slugCheckTimeout = setTimeout(async () => {
        try {
          const res = await fetch(`api/admin.php?action=check_subwiki_slug&slug=${encodeURIComponent(rawVal)}&for_subwiki=1`);
          const data = await res.json();
          if (data.valid) {
            subwikiSlugFeedback.style.color = 'var(--success-color, #10b981)';
            subwikiSlugFeedback.textContent = `✓ Slug "${data.slug}" is available. Grouping: dash notation supported.`;
          } else {
            subwikiSlugFeedback.style.color = 'var(--danger-color, #ef4444)';
            subwikiSlugFeedback.textContent = `✗ ${data.error || 'Slug unavailable'}`;
          }
        } catch (e) {
          // ignore network glitch in real-time check
        }
      }, 250);
    });
  }

  // Deploy Subwiki Form Handler
  const deploySubwikiForm = document.getElementById('deploy-subwiki-form');
  if (deploySubwikiForm) {
    deploySubwikiForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      const submitBtn = document.getElementById('btn-submit-deploy-subwiki');
      const loadingEl = document.getElementById('deploy-subwiki-loading');

      submitBtn.disabled = true;
      if (loadingEl) loadingEl.style.display = 'block';

      const formData = new FormData(deploySubwikiForm);
      formData.append('action', 'deploy_subwiki');

      try {
        const res = await fetch('api/admin.php', { method: 'POST', body: formData });
        const data = await res.json();
        if (data.success) {
          alert(`Subwiki "${data.title}" deployed successfully!\n\nAccess it at: /${data.slug}/`);
          deploySubwikiForm.reset();
          if (subwikiSlugFeedback) subwikiSlugFeedback.textContent = '';
          loadSubwikisList();
        } else {
          alert('Failed to deploy subwiki: ' + (data.error || 'Unknown error'));
        }
      } catch (err) {
        alert('Request failed while deploying subwiki.');
      } finally {
        submitBtn.disabled = false;
        if (loadingEl) loadingEl.style.display = 'none';
      }
    });
  }

  // Category Edit Pencil Icons in Sidebar
  document.querySelectorAll('.btn-edit-cat-icon').forEach(btn => {
    btn.addEventListener('click', (e) => {
      e.stopPropagation();
      const bookId = btn.getAttribute('data-book-id');
      const bookTitle = btn.getAttribute('data-book-title');
      const bookDesc = btn.getAttribute('data-book-description') || '';
      const bookTheme = btn.getAttribute('data-book-theme');
      const bookVisibility = btn.getAttribute('data-book-visibility');
      const isDirectlyReadOnly = btn.getAttribute('data-book-readonly') === '1';
      const isProtected = btn.getAttribute('data-book-protected') === '1';

      const editBookIdInput = document.getElementById('edit-book-id-hidden');
      const editBookTitleInput = document.getElementById('edit-book-title-input');
      const editBookDescInput = document.getElementById('edit-book-description-input');
      const editBookThemeInput = document.getElementById('edit-book-theme-input');
      const editBookVisInput = document.getElementById('edit-book-visibility-input');
      const editBookRssUrlInput = document.getElementById('edit-book-rss-url');
      const editBookReadOnlyInput = document.getElementById('edit-book-readonly-input');
      const editBookReadOnlyHelp = document.getElementById('edit-book-readonly-help');
      const editBookProtectedNotice = document.getElementById('edit-book-protected-notice');
      const deleteBookBtn = document.getElementById('btn-delete-book');
      const editBookModal = document.getElementById('edit-book-modal');
      const isDemoMode = document.getElementById('btn-reload-demo-package') !== null;

      if (deleteBookBtn) {
        if (isProtected) {
          deleteBookBtn.style.display = 'none';
          deleteBookBtn.setAttribute('data-protected', '1');
        } else {
          deleteBookBtn.style.display = 'inline-block';
          deleteBookBtn.removeAttribute('data-protected');
        }
      }

      if (editBookProtectedNotice) {
        editBookProtectedNotice.style.display = isProtected ? 'inline-flex' : 'none';
      }

      if (editBookReadOnlyInput) {
        editBookReadOnlyInput.checked = isDirectlyReadOnly || isProtected;

        if (!isDirectlyReadOnly && isProtected) {
          // Protected because it contains protected documents
          editBookReadOnlyInput.disabled = true;
          if (editBookReadOnlyHelp) {
            editBookReadOnlyHelp.textContent = 'This category contains protected documents and is automatically locked against deletion.';
          }
        } else if (isDirectlyReadOnly) {
          if (isDemoMode) {
            editBookReadOnlyInput.disabled = true;
            if (editBookReadOnlyHelp) {
              editBookReadOnlyHelp.textContent = 'Protected demo categories cannot be unlocked in demo mode.';
            }
          } else {
            editBookReadOnlyInput.disabled = false;
            if (editBookReadOnlyHelp) {
              editBookReadOnlyHelp.textContent = 'Uncheck to unlock this category and allow deletion.';
            }
          }
        } else {
          editBookReadOnlyInput.disabled = false;
          if (editBookReadOnlyHelp) {
            editBookReadOnlyHelp.textContent = 'Protected categories and categories containing protected documents cannot be deleted.';
          }
        }
      }

      if (editBookIdInput && editBookTitleInput && editBookModal) {
        editBookIdInput.value = bookId;
        editBookTitleInput.value = bookTitle;
        if (editBookDescInput) editBookDescInput.value = bookDesc;
        if (editBookVisInput) editBookVisInput.value = bookVisibility || 'public';
        populateThemes(editBookThemeInput, bookTheme);

        if (editBookRssUrlInput) {
          const mainRssInput = document.getElementById('setting-rss-feed-url');
          if (mainRssInput && mainRssInput.value) {
            const baseUrl = mainRssInput.value;
            const categoryVal = bookId || bookTitle;
            const separator = baseUrl.includes('?') ? '&' : '?';
            editBookRssUrlInput.value = baseUrl + separator + 'category=' + encodeURIComponent(categoryVal);
          }
        }

        editBookModal.classList.add('open');
      }
    });
  });

  // Copy RSS Feed URL to Clipboard
  document.querySelectorAll('.btn-copy-rss').forEach(btn => {
    btn.addEventListener('click', async (e) => {
      e.preventDefault();
      e.stopPropagation();
      const targetId = btn.getAttribute('data-copy-target');
      const inputEl = targetId ? document.getElementById(targetId) : null;
      if (!inputEl || !inputEl.value) return;

      const textToCopy = inputEl.value;
      try {
        if (navigator.clipboard && navigator.clipboard.writeText) {
          await navigator.clipboard.writeText(textToCopy);
        } else {
          inputEl.select();
          document.execCommand('copy');
        }

        const origContent = btn.innerHTML;
        const origTitle = btn.getAttribute('title');
        btn.innerHTML = '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="#10b981" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>';
        btn.setAttribute('title', 'Copied!');
        setTimeout(() => {
          btn.innerHTML = origContent;
          if (origTitle) btn.setAttribute('title', origTitle);
        }, 2000);
      } catch (err) {
        console.error('Failed to copy text: ', err);
      }
    });
  });

  // Dynamic token update for main RSS Feed URL in Qwiki Settings
  const feedTokenInput = document.getElementById('setting-feed-token');
  const mainRssInput = document.getElementById('setting-rss-feed-url');
  if (feedTokenInput && mainRssInput) {
    const updateMainRssUrl = () => {
      try {
        const url = new URL(mainRssInput.value);
        const tokenVal = feedTokenInput.value.trim();
        if (tokenVal) {
          url.searchParams.set('token', tokenVal);
        } else {
          url.searchParams.delete('token');
        }
        mainRssInput.value = url.toString();
      } catch (e) {}
    };
    feedTokenInput.addEventListener('input', updateMainRssUrl);
    feedTokenInput.addEventListener('change', updateMainRssUrl);
    feedTokenInput.addEventListener('keyup', updateMainRssUrl);
  }

  // Delete Category
  const deleteBookBtn = document.getElementById('btn-delete-book');
  if (deleteBookBtn) {
    deleteBookBtn.addEventListener('click', async () => {
      const bookIdInput = document.getElementById('edit-book-id-hidden');
      const bookTitleInput = document.getElementById('edit-book-title-input');
      const bookId = bookIdInput ? bookIdInput.value.trim() : '';
      const bookTitle = bookTitleInput ? bookTitleInput.value.trim() : 'this category';

      if (!bookId && !bookTitle) return;

      if (deleteBookBtn.getAttribute('data-protected') === '1') {
        alert('This category is protected or contains protected documents and cannot be deleted.');
        return;
      }

      if (!confirm(`Are you sure you want to delete the category "${bookTitle}" and all its sub-folders from the wiki structure?`)) {
        return;
      }

      const formData = new FormData();
      formData.append('action', 'delete_book');
      formData.append('bookId', bookId);
      if (bookTitle) {
        formData.append('bookTitle', bookTitle);
      }

      try {
        const res = await fetch('api/admin.php?action=delete_book', { method: 'POST', body: formData });
        const data = await res.json();
        if (data.success) {
          window.location.href = 'index.php';
        } else {
          alert('Delete category failed: ' + (data.error || 'Unknown error'));
        }
      } catch (err) {
        alert('Delete category request failed');
      }
    });
  }

  // Tab Switcher inside Modals
  document.querySelectorAll('.tab-btn').forEach(tabBtn => {
    tabBtn.addEventListener('click', () => {
      const parentModal = tabBtn.closest('.modal-card');
      parentModal.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
      parentModal.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));

      tabBtn.classList.add('active');
      const targetTabId = tabBtn.getAttribute('data-tab');
      const targetContent = parentModal.querySelector('#' + targetTabId);
      if (targetContent) targetContent.classList.add('active');
    });
  });

  // Helper for Form Submissions
  async function submitAdminForm(formId, actionName, successRedirect = true, customCallback = null) {
    const form = document.getElementById(formId);
    if (!form) return;

    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      const formData = new FormData(form);
      formData.append('action', actionName);

      // Encode content fields as base64 to bypass WAFs
      if (formData.has('content')) {
        const contentStr = formData.get('content');
        formData.delete('content');
        formData.append('content_base64', btoa(unescape(encodeURIComponent(contentStr))));
      }

      try {
        const res = await fetch('api/admin.php', { method: 'POST', body: formData });
        const data = await res.json();

        if (data.success) {
          if (customCallback) {
            customCallback(data);
          } else if (successRedirect) {
            if (data.bookId && data.slug) {
              window.location.href = `${encodeURIComponent(data.bookId)}/${encodeURIComponent(data.slug)}`;
            } else {
              window.location.reload();
            }
          }
        } else if (data.conflict) {
          handleUploadConflict(form, data);
        } else {
          alert('Operation failed: ' + (data.error || 'Unknown error'));
        }
      } catch (err) {
        alert('Server request failed');
      }
    });
  }

  // Handle Upload Conflict Modal Interaction
  function handleUploadConflict(originalForm, conflictData) {
    const conflictModal = document.getElementById('upload-conflict-modal');
    if (!conflictModal) {
      alert('Upload failed: ' + (conflictData.error || 'A document with this slug already exists.'));
      return;
    }

    const titleEl = document.getElementById('conflict-existing-title');
    const slugEl = document.getElementById('conflict-existing-slug');
    const catEl = document.getElementById('conflict-existing-category');
    const newSlugInput = document.getElementById('conflict-new-slug');
    const replaceCard = document.getElementById('conflict-replace-card');
    const replaceBtn = document.getElementById('btn-conflict-replace');
    const renameBtn = document.getElementById('btn-conflict-rename');

    if (titleEl) titleEl.textContent = conflictData.existingTitle || conflictData.existingSlug;
    if (slugEl) slugEl.textContent = conflictData.existingSlug;
    if (catEl) catEl.textContent = conflictData.existingCategory || 'Root';
    if (newSlugInput) newSlugInput.value = conflictData.suggestedSlug || (conflictData.existingSlug + '-1');

    if (conflictData.isProtected) {
      if (replaceBtn) {
        replaceBtn.disabled = true;
        replaceBtn.textContent = 'Replacement Blocked (Protected)';
      }
      if (replaceCard) {
        replaceCard.style.opacity = '0.6';
        const p = replaceCard.querySelector('p');
        if (p) p.textContent = 'This document is protected and cannot be modified or replaced.';
      }
    } else {
      if (replaceBtn) {
        replaceBtn.disabled = false;
        replaceBtn.textContent = 'Replace Document Content';
      }
      if (replaceCard) {
        replaceCard.style.opacity = '1';
        const p = replaceCard.querySelector('p');
        if (p) p.textContent = 'Overwrites the content of the existing document with this uploaded file. All other settings (URL slug, category, custom themes, permissions, and sharing keys) will be preserved.';
      }
    }

    // Bind Replace Action
    if (replaceBtn) {
      replaceBtn.onclick = async () => {
        replaceBtn.disabled = true;
        replaceBtn.textContent = 'Replacing...';
        try {
          const formData = new FormData(originalForm);
          formData.append('action', 'upload_file');
          formData.append('conflictAction', 'replace');
          const res = await fetch('api/admin.php', { method: 'POST', body: formData });
          const data = await res.json();
          if (data.success) {
            conflictModal.classList.remove('open');
            if (data.bookId && data.slug) {
              window.location.href = `${encodeURIComponent(data.bookId)}/${encodeURIComponent(data.slug)}`;
            } else {
              window.location.reload();
            }
          } else {
            alert('Replace failed: ' + (data.error || 'Unknown error'));
            replaceBtn.disabled = false;
            replaceBtn.textContent = 'Replace Document Content';
          }
        } catch (err) {
          alert('Server request failed');
          replaceBtn.disabled = false;
          replaceBtn.textContent = 'Replace Document Content';
        }
      };
    }

    // Bind Rename / Copy Action
    if (renameBtn) {
      renameBtn.onclick = async () => {
        renameBtn.disabled = true;
        renameBtn.textContent = 'Uploading Copy...';
        const customSlug = newSlugInput ? newSlugInput.value.trim() : '';
        try {
          const formData = new FormData(originalForm);
          formData.append('action', 'upload_file');
          formData.append('conflictAction', 'rename');
          if (customSlug) formData.append('customSlug', customSlug);
          const res = await fetch('api/admin.php', { method: 'POST', body: formData });
          const data = await res.json();
          if (data.success) {
            conflictModal.classList.remove('open');
            if (data.bookId && data.slug) {
              window.location.href = `${encodeURIComponent(data.bookId)}/${encodeURIComponent(data.slug)}`;
            } else {
              window.location.reload();
            }
          } else {
            alert('Upload copy failed: ' + (data.error || 'Unknown error'));
            renameBtn.disabled = false;
            renameBtn.textContent = 'Upload as Copy';
          }
        } catch (err) {
          alert('Server request failed');
          renameBtn.disabled = false;
          renameBtn.textContent = 'Upload as Copy';
        }
      };
    }

    conflictModal.classList.add('open');
  }

  // --- Two-Step Login & 2FA Flow ---
  const loginForm = document.getElementById('login-form');
  const login2faForm = document.getElementById('login-2fa-form');
  if (loginForm) {
    loginForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      const formData = new FormData(loginForm);
      formData.append('action', 'login');
      const submitBtn = document.getElementById('btn-login-submit');
      if (submitBtn) { submitBtn.disabled = true; submitBtn.textContent = 'Logging in...'; }

      try {
        const res = await fetch('api/admin.php', { method: 'POST', body: formData });
        const data = await res.json();
        if (data.success) {
          if (data.require2fa) {
            // Smoothly switch view to 2FA challenge
            loginForm.style.display = 'none';
            if (login2faForm) {
              login2faForm.style.display = 'block';
              const codeInput = document.getElementById('login-2fa-code');
              if (codeInput) {
                codeInput.value = '';
                codeInput.focus();
              }
            }
            const modalTitle = document.getElementById('login-modal-title');
            if (modalTitle) modalTitle.textContent = 'Two-Factor Challenge';
          } else if (data.require2fa_setup) {
            alert('Two-Factor Authentication is enforced for your account policy. Please complete setup.');
            window.location.reload();
          } else {
            window.location.reload();
          }
        } else {
          alert('Login failed: ' + (data.error || 'Invalid credentials'));
        }
      } catch (err) {
        alert('Server request failed');
      } finally {
        if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = 'Log In'; }
      }
    });
  }

  if (login2faForm) {
    login2faForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      const codeInput = document.getElementById('login-2fa-code');
      const codeVal = codeInput ? codeInput.value.trim() : '';
      if (!codeVal) return;

      const submitBtn = document.getElementById('btn-2fa-submit');
      if (submitBtn) { submitBtn.disabled = true; submitBtn.textContent = 'Verifying...'; }

      const formData = new FormData();
      formData.append('action', 'verify_2fa');
      formData.append('code', codeVal);

      try {
        const res = await fetch('api/admin.php', { method: 'POST', body: formData });
        const data = await res.json();
        if (data.success) {
          window.location.reload();
        } else {
          alert('Verification failed: ' + (data.error || 'Invalid code'));
          if (codeInput) {
            codeInput.value = '';
            codeInput.focus();
          }
        }
      } catch (err) {
        alert('Server request failed');
      } finally {
        if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = 'Verify & Continue'; }
      }
    });

    const btnBackToLogin = document.getElementById('btn-back-to-login');
    if (btnBackToLogin) {
      btnBackToLogin.addEventListener('click', () => {
        login2faForm.style.display = 'none';
        if (loginForm) loginForm.style.display = 'block';
        const modalTitle = document.getElementById('login-modal-title');
        if (modalTitle) modalTitle.textContent = 'Account Authentication';
      });
    }

    const btnToggleRecovery = document.getElementById('btn-toggle-recovery-code');
    let usingRecoveryCode = false;
    if (btnToggleRecovery) {
      btnToggleRecovery.addEventListener('click', () => {
        usingRecoveryCode = !usingRecoveryCode;
        const codeInput = document.getElementById('login-2fa-code');
        const codeLabel = document.getElementById('login-2fa-label');
        const heading = document.getElementById('login-2fa-heading');
        const desc = document.getElementById('login-2fa-desc');

        if (usingRecoveryCode) {
          btnToggleRecovery.textContent = 'Use authenticator code';
          if (codeLabel) codeLabel.textContent = 'Emergency Recovery Code';
          if (codeInput) {
            codeInput.placeholder = 'xxxx-xxxx';
            codeInput.maxLength = 12;
            codeInput.value = '';
            codeInput.focus();
          }
          if (heading) heading.textContent = 'Recovery Code Login';
          if (desc) desc.textContent = 'Enter one of your 8 emergency backup codes.';
        } else {
          btnToggleRecovery.textContent = 'Use recovery code';
          if (codeLabel) codeLabel.textContent = 'Verification Code';
          if (codeInput) {
            codeInput.placeholder = '000000';
            codeInput.maxLength = 10;
            codeInput.value = '';
            codeInput.focus();
          }
          if (heading) heading.textContent = 'Two-Factor Authentication';
          if (desc) desc.textContent = 'Enter the 6-digit verification code from your authenticator app.';
        }
      });
    }
  }

  // --- Forgot Password Flow ---
  const btnOpenForgotPwd = document.getElementById('btn-open-forgot-pwd');
  const forgotModal = document.getElementById('forgot-password-modal');
  const loginModal = document.getElementById('login-modal');
  if (btnOpenForgotPwd && forgotModal) {
    btnOpenForgotPwd.addEventListener('click', () => {
      if (loginModal) loginModal.classList.remove('open');
      forgotModal.classList.add('open');
      const forgotInput = document.getElementById('forgot-identifier');
      if (forgotInput) {
        forgotInput.value = '';
        forgotInput.focus();
      }
      const alertBox = document.getElementById('forgot-password-alert');
      if (alertBox) alertBox.style.display = 'none';
    });
  }

  const btnBackFromForgot = document.getElementById('btn-back-from-forgot');
  if (btnBackFromForgot && forgotModal && loginModal) {
    btnBackFromForgot.addEventListener('click', () => {
      forgotModal.classList.remove('open');
      loginModal.classList.add('open');
    });
  }

  const forgotForm = document.getElementById('forgot-password-form');
  if (forgotForm) {
    forgotForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      const identifier = document.getElementById('forgot-identifier').value.trim();
      if (!identifier) return;

      const submitBtn = document.getElementById('btn-submit-forgot');
      if (submitBtn) { submitBtn.disabled = true; submitBtn.textContent = 'Sending...'; }

      const formData = new FormData();
      formData.append('action', 'forgot_password');
      formData.append('identifier', identifier);

      try {
        const res = await fetch('api/admin.php', { method: 'POST', body: formData });
        const data = await res.json();
        const alertBox = document.getElementById('forgot-password-alert');
        if (alertBox) {
          alertBox.style.display = 'block';
          alertBox.style.background = 'rgba(16,185,129,0.1)';
          alertBox.style.border = '1px solid #10b981';
          alertBox.style.color = '#047857';
          alertBox.textContent = data.message || 'If an account with that verified email exists, a reset link has been dispatched.';
        }
      } catch (err) {
        alert('Server request failed');
      } finally {
        if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = 'Send Reset Link'; }
      }
    });
  }

  // --- Standalone Password Reset Submission ---
  const standaloneResetForm = document.getElementById('standalone-reset-password-form');
  if (standaloneResetForm) {
    standaloneResetForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      const newPwd = document.getElementById('reset-new-password').value;
      const confirmPwd = document.getElementById('reset-confirm-password').value;
      const statusBox = document.getElementById('reset-password-status');

      if (newPwd.length < 4) {
        if (statusBox) {
          statusBox.style.display = 'block';
          statusBox.style.background = 'rgba(239,68,68,0.1)';
          statusBox.style.border = '1px solid #ef4444';
          statusBox.style.color = '#b91c1c';
          statusBox.textContent = 'Password must be at least 4 characters long.';
        }
        return;
      }

      if (newPwd !== confirmPwd) {
        if (statusBox) {
          statusBox.style.display = 'block';
          statusBox.style.background = 'rgba(239,68,68,0.1)';
          statusBox.style.border = '1px solid #ef4444';
          statusBox.style.color = '#b91c1c';
          statusBox.textContent = 'Passwords do not match.';
        }
        return;
      }

      const submitBtn = document.getElementById('btn-submit-reset-password');
      if (submitBtn) { submitBtn.disabled = true; submitBtn.textContent = 'Saving...'; }

      const formData = new FormData(standaloneResetForm);
      formData.append('action', 'reset_password_submit');

      try {
        const res = await fetch('api/admin.php', { method: 'POST', body: formData });
        const data = await res.json();
        if (data.success) {
          if (statusBox) {
            statusBox.style.display = 'block';
            statusBox.style.background = 'rgba(16,185,129,0.1)';
            statusBox.style.border = '1px solid #10b981';
            statusBox.style.color = '#047857';
            statusBox.textContent = '✅ Password updated successfully! Redirecting...';
          }
          setTimeout(() => {
            window.location.href = window.location.pathname;
          }, 1200);
        } else {
          if (statusBox) {
            statusBox.style.display = 'block';
            statusBox.style.background = 'rgba(239,68,68,0.1)';
            statusBox.style.border = '1px solid #ef4444';
            statusBox.style.color = '#b91c1c';
            statusBox.textContent = data.error || 'Password reset failed.';
          }
          if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = 'Save New Password & Log In'; }
        }
      } catch (err) {
        alert('Server request failed');
        if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = 'Save New Password & Log In'; }
      }
    });
  }

  // --- Account & Security / User Profile Modal ---
  const btnUserProfile = document.getElementById('btn-user-profile');
  const userProfileModal = document.getElementById('user-profile-modal');
  let currentEnrollingSecret = null;
  let currentEnrollingCodes = [];

  async function loadUserProfile() {
    try {
      const res = await fetch('api/admin.php?action=list_users');
      const data = await res.json();
      if (data.success && Array.isArray(data.users)) {
        const currentUserEl = document.querySelector('#user-profile-modal strong');
        const username = currentUserEl ? currentUserEl.textContent.trim().toLowerCase() : '';
        const user = data.users.find(u => u.username.toLowerCase() === username);

        if (user) {
          const emailInput = document.getElementById('profile-user-email');
          const emailBadge = document.getElementById('profile-email-badge');
          const btnResend = document.getElementById('btn-resend-verification');
          if (emailInput) emailInput.value = user.email || '';
          if (emailBadge) {
            if (user.email && user.emailVerified) {
              emailBadge.innerHTML = '<span style="color:#10b981; font-weight:600;">✓ Email Verified</span>';
              if (btnResend) btnResend.style.display = 'none';
            } else if (user.email) {
              emailBadge.innerHTML = '<span style="color:#f59e0b; font-weight:600;">⚠️ Unverified</span>';
              if (btnResend) btnResend.style.display = 'inline-block';
            } else {
              emailBadge.innerHTML = '<span style="color:var(--text-muted);">No email configured</span>';
              if (btnResend) btnResend.style.display = 'none';
            }
          }

          const disabledView = document.getElementById('profile-2fa-disabled-view');
          const setupView = document.getElementById('profile-2fa-setup-view');
          const activeView = document.getElementById('profile-2fa-active-view');
          const codesCard = document.getElementById('profile-2fa-recovery-codes-card');

          if (setupView) setupView.style.display = 'none';
          if (codesCard) codesCard.style.display = 'none';

          if (user.has2fa) {
            if (disabledView) disabledView.style.display = 'none';
            if (activeView) activeView.style.display = 'block';
          } else {
            if (disabledView) disabledView.style.display = 'block';
            if (activeView) activeView.style.display = 'none';
          }
        }
      }
    } catch (err) {
      console.error('Failed to load profile data:', err);
    }
  }

  if (btnUserProfile && userProfileModal) {
    btnUserProfile.addEventListener('click', () => {
      userProfileModal.classList.add('open');
      loadUserProfile();
    });
  }

  // Save profile email
  const profileEmailForm = document.getElementById('profile-email-form');
  if (profileEmailForm) {
    profileEmailForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      const emailVal = document.getElementById('profile-user-email').value.trim();
      const saveBtn = document.getElementById('btn-save-profile-email');
      if (saveBtn) { saveBtn.disabled = true; saveBtn.textContent = 'Saving...'; }

      const formData = new FormData();
      formData.append('action', 'update_user_email');
      formData.append('email', emailVal);

      try {
        const res = await fetch('api/admin.php', { method: 'POST', body: formData });
        const data = await res.json();
        if (data.success) {
          alert('Email address updated! A verification email has been dispatched.');
          loadUserProfile();
        } else {
          alert('Failed to update email: ' + (data.error || 'Unknown error'));
        }
      } catch (err) {
        alert('Network request failed');
      } finally {
        if (saveBtn) { saveBtn.disabled = false; saveBtn.textContent = 'Save Email'; }
      }
    });
  }

  // Resend verification email
  const btnResendVerify = document.getElementById('btn-resend-verification');
  if (btnResendVerify) {
    btnResendVerify.addEventListener('click', async () => {
      btnResendVerify.disabled = true;
      btnResendVerify.textContent = 'Sending...';
      const formData = new FormData();
      formData.append('action', 'resend_email_verification');
      try {
        const res = await fetch('api/admin.php', { method: 'POST', body: formData });
        const data = await res.json();
        if (data.success) {
          alert('Verification email dispatched!');
        } else {
          alert('Failed: ' + (data.error || 'Unknown error'));
        }
      } catch (err) {
        alert('Network request failed');
      } finally {
        btnResendVerify.disabled = false;
        btnResendVerify.textContent = 'Resend Verification Link';
      }
    });
  }

  // Start 2FA setup
  const btnStart2faSetup = document.getElementById('btn-start-2fa-setup');
  if (btnStart2faSetup) {
    btnStart2faSetup.addEventListener('click', async () => {
      btnStart2faSetup.disabled = true;
      btnStart2faSetup.textContent = 'Generating keys...';

      try {
        const res = await fetch('api/admin.php?action=initiate_2fa');
        const data = await res.json();
        if (data.success && data.secret && data.otpUri) {
          currentEnrollingSecret = data.secret;
          currentEnrollingCodes = data.recoveryCodes || [];

          const disabledView = document.getElementById('profile-2fa-disabled-view');
          const setupView = document.getElementById('profile-2fa-setup-view');
          if (disabledView) disabledView.style.display = 'none';
          if (setupView) setupView.style.display = 'block';

          const qrContainer = document.getElementById('profile-2fa-qr-container');
          if (qrContainer) {
            if (window.QRCode && window.QRCode.generateSvg) {
              qrContainer.innerHTML = window.QRCode.generateSvg(data.otpUri, 180);
            } else {
              qrContainer.innerHTML = '<p style="color:var(--text-muted); font-size:0.8rem;">Enter the secret key manually below.</p>';
            }
          }

          const secretFormatted = data.secret.match(/.{1,4}/g).join(' ');
          const secretEl = document.getElementById('profile-2fa-secret-text');
          if (secretEl) secretEl.textContent = secretFormatted;

          const copyBtn = document.getElementById('btn-copy-2fa-secret');
          if (copyBtn) {
            copyBtn.onclick = () => {
              if (navigator.clipboard) {
                navigator.clipboard.writeText(data.secret);
                copyBtn.textContent = '✅ Copied!';
                setTimeout(() => { copyBtn.textContent = '📋 Copy Secret Key'; }, 2000);
              }
            };
          }

          const codeInput = document.getElementById('profile-2fa-confirm-code');
          if (codeInput) {
            codeInput.value = '';
            codeInput.focus();
          }
        } else {
          alert('Failed to initiate 2FA setup: ' + (data.error || 'Unknown error'));
        }
      } catch (err) {
        alert('Network request failed');
      } finally {
        btnStart2faSetup.disabled = false;
        btnStart2faSetup.textContent = '🔐 Enable 2FA';
      }
    });
  }

  // Cancel 2FA setup
  const btnCancel2faSetup = document.getElementById('btn-cancel-2fa-setup');
  if (btnCancel2faSetup) {
    btnCancel2faSetup.addEventListener('click', () => {
      const disabledView = document.getElementById('profile-2fa-disabled-view');
      const setupView = document.getElementById('profile-2fa-setup-view');
      if (setupView) setupView.style.display = 'none';
      if (disabledView) disabledView.style.display = 'block';
    });
  }

  // Confirm 2FA setup
  const profile2faConfirmForm = document.getElementById('profile-2fa-confirm-form');
  if (profile2faConfirmForm) {
    profile2faConfirmForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      const codeVal = document.getElementById('profile-2fa-confirm-code').value.trim();
      if (!codeVal) return;

      const confirmBtn = document.getElementById('btn-confirm-2fa');
      if (confirmBtn) { confirmBtn.disabled = true; confirmBtn.textContent = 'Activating...'; }

      const formData = new FormData();
      formData.append('action', 'confirm_2fa');
      formData.append('code', codeVal);

      try {
        const res = await fetch('api/admin.php', { method: 'POST', body: formData });
        const data = await res.json();
        if (data.success) {
          const setupView = document.getElementById('profile-2fa-setup-view');
          const activeView = document.getElementById('profile-2fa-active-view');
          const codesCard = document.getElementById('profile-2fa-recovery-codes-card');

          if (setupView) setupView.style.display = 'none';
          if (activeView) activeView.style.display = 'block';
          if (codesCard) {
            codesCard.style.display = 'block';
            const listEl = document.getElementById('profile-2fa-codes-list');
            if (listEl) {
              listEl.innerHTML = currentEnrollingCodes.map(c => `<div style="padding:0.25rem 0.5rem; background:rgba(0,0,0,0.03); border-radius:4px;">${escapeHtml(c)}</div>`).join('');
            }
          }
        } else {
          alert('Confirmation failed: ' + (data.error || 'Invalid code'));
        }
      } catch (err) {
        alert('Network request failed');
      } finally {
        if (confirmBtn) { confirmBtn.disabled = false; confirmBtn.textContent = 'Activate 2FA'; }
      }
    });
  }

  // Copy recovery codes
  const btnCopyRecovery = document.getElementById('btn-copy-recovery-codes');
  if (btnCopyRecovery) {
    btnCopyRecovery.addEventListener('click', () => {
      if (currentEnrollingCodes.length > 0 && navigator.clipboard) {
        navigator.clipboard.writeText(currentEnrollingCodes.join('\n'));
        btnCopyRecovery.textContent = '✅ Copied!';
        setTimeout(() => { btnCopyRecovery.textContent = '📋 Copy All Codes'; }, 2000);
      }
    });
  }

  const btnDoneRecovery = document.getElementById('btn-done-recovery-codes');
  if (btnDoneRecovery) {
    btnDoneRecovery.addEventListener('click', () => {
      const codesCard = document.getElementById('profile-2fa-recovery-codes-card');
      if (codesCard) codesCard.style.display = 'none';
      loadUserProfile();
    });
  }

  // Disable 2FA for current user
  const btnDisableMy2fa = document.getElementById('btn-disable-my-2fa');
  if (btnDisableMy2fa) {
    btnDisableMy2fa.addEventListener('click', async () => {
      if (!confirm('Are you sure you want to disable Two-Factor Authentication on your account?')) return;
      btnDisableMy2fa.disabled = true;
      btnDisableMy2fa.textContent = 'Disabling...';

      const formData = new FormData();
      formData.append('action', 'disable_2fa');

      try {
        const res = await fetch('api/admin.php', { method: 'POST', body: formData });
        const data = await res.json();
        if (data.success) {
          alert('Two-Factor Authentication has been disabled.');
          loadUserProfile();
        } else {
          alert('Failed to disable 2FA: ' + (data.error || 'Unknown error'));
        }
      } catch (err) {
        alert('Network request failed');
      } finally {
        btnDisableMy2fa.disabled = false;
        btnDisableMy2fa.textContent = 'Disable 2FA';
      }
    });
  }

  // --- SMTP Settings Controls ---
  const chkSmtpEnabled = document.getElementById('setting-smtp-enabled');
  const smtpFieldsContainer = document.getElementById('smtp-settings-fields');
  if (chkSmtpEnabled && smtpFieldsContainer) {
    chkSmtpEnabled.addEventListener('change', () => {
      smtpFieldsContainer.style.display = chkSmtpEnabled.checked ? 'block' : 'none';
    });
  }

  const btnTestSmtp = document.getElementById('btn-test-smtp');
  if (btnTestSmtp) {
    btnTestSmtp.addEventListener('click', async () => {
      const host = document.getElementById('setting-smtp-host')?.value || '';
      const port = document.getElementById('setting-smtp-port')?.value || '587';
      const enc = document.getElementById('setting-smtp-encryption')?.value || 'tls';
      const user = document.getElementById('setting-smtp-username')?.value || '';
      const pass = document.getElementById('setting-smtp-password')?.value || '';
      const fromEmail = document.getElementById('setting-smtp-fromemail')?.value || '';
      const fromName = document.getElementById('setting-smtp-fromname')?.value || '';
      const statusEl = document.getElementById('smtp-test-status');

      if (!host) {
        alert('Please enter an SMTP Host first.');
        return;
      }

      btnTestSmtp.disabled = true;
      btnTestSmtp.textContent = 'Testing connection...';
      if (statusEl) {
        statusEl.style.color = 'var(--text-muted)';
        statusEl.textContent = 'Connecting to ' + host + ':' + port + '...';
      }

      const formData = new FormData();
      formData.append('action', 'test_smtp');
      formData.append('host', host);
      formData.append('port', port);
      formData.append('encryption', enc);
      formData.append('username', user);
      formData.append('password', pass);
      formData.append('fromEmail', fromEmail);
      formData.append('fromName', fromName);

      try {
        const res = await fetch('api/admin.php', { method: 'POST', body: formData });
        const data = await res.json();
        if (data.success) {
          if (statusEl) {
            statusEl.style.color = '#10b981';
            statusEl.textContent = '✅ SMTP Connection & Test Email dispatched successfully!';
          }
        } else {
          if (statusEl) {
            statusEl.style.color = '#ef4444';
            statusEl.textContent = '❌ Failed: ' + (data.error || 'Connection rejected');
          }
        }
      } catch (err) {
        if (statusEl) {
          statusEl.style.color = '#ef4444';
          statusEl.textContent = '❌ Network request failed.';
        }
      } finally {
        btnTestSmtp.disabled = false;
        btnTestSmtp.textContent = '📨 Test Connection & Send Test Email';
      }
    });
  }

  // Bind Form Submissions
  submitAdminForm('add-book-form', 'add_book');
  submitAdminForm('edit-book-form', 'edit_book');
  submitAdminForm('tab-create-md', 'create_markdown');
  submitAdminForm('tab-upload', 'upload_file');
  submitAdminForm('tab-gdoc', 'add_gdoc');
  submitAdminForm('tab-link', 'add_link');
  submitAdminForm('tab-remote', 'add_remote');
  submitAdminForm('edit-chapter-form', 'edit_chapter');
  submitAdminForm('edit-link-form', 'edit_chapter');
  submitAdminForm('replace-document-form', 'replace_document_file');
  submitAdminForm('settings-form', 'update_settings');

  // Reload Demo Package handler
  const btnReloadDemo = document.getElementById('btn-reload-demo-package');
  if (btnReloadDemo) {
    btnReloadDemo.addEventListener('click', async () => {
      if (!confirm('⚠️ Are you sure you want to reset the demo package to its fresh state?\n\nThis will clear all user-added pages, categories, and accounts, restoring default demo documentation.')) {
        return;
      }
      btnReloadDemo.disabled = true;
      btnReloadDemo.textContent = 'Reloading Demo...';
      try {
        const formData = new FormData();
        formData.append('action', 'reload_demo');
        const res = await fetch('api/admin.php', { method: 'POST', body: formData });
        const data = await res.json();
        if (data.success) {
          alert('✅ Demo package reloaded successfully! The page will now refresh.');
          window.location.reload();
        } else {
          alert('❌ Failed to reload demo package: ' + (data.error || 'Unknown error'));
          btnReloadDemo.disabled = false;
          btnReloadDemo.textContent = 'Reload Demo Package';
        }
      } catch (err) {
        console.error('Reload demo request failed:', err);
        alert('❌ Network error while reloading demo package.');
        btnReloadDemo.disabled = false;
        btnReloadDemo.textContent = 'Reload Demo Package';
      }
    });
  }

  // Add User form handler inside Users Modal
  const addUserForm = document.getElementById('add-user-form');
  if (addUserForm) {
    addUserForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      const formData = new FormData(addUserForm);
      formData.append('action', 'add_user');

      try {
        const res = await fetch('api/admin.php', { method: 'POST', body: formData });
        const data = await res.json();

        if (data.success) {
          addUserForm.reset();
          loadUsersList();
        } else {
          alert('Failed to add user: ' + (data.error || 'Unknown error'));
        }
      } catch (err) {
        alert('Network request failed');
      }
    });
  }

  // Admin Logout
  const logoutBtn = document.getElementById('btn-logout');
  if (logoutBtn) {
    logoutBtn.addEventListener('click', async () => {
      await fetch('api/admin.php?action=logout');
      window.location.reload();
    });
  }

  // Refresh Remote Document Cache handler
  document.addEventListener('click', async (e) => {
    const btnRefresh = e.target.closest('.btn-refresh-remote-cache');
    if (!btnRefresh) return;
    const slug = btnRefresh.getAttribute('data-slug') || '';
    const origText = btnRefresh.textContent;
    btnRefresh.disabled = true;
    btnRefresh.textContent = '🔄 Syncing...';
    try {
      const formData = new FormData();
      formData.append('action', 'refresh_remote_cache');
      formData.append('slug', slug);
      const res = await fetch('api/admin.php', { method: 'POST', body: formData });
      const data = await res.json();
      if (data.success) {
        window.location.reload();
      } else {
        alert('❌ Failed to refresh cache: ' + (data.error || 'Unknown error'));
        btnRefresh.disabled = false;
        btnRefresh.textContent = origText;
      }
    } catch (err) {
      console.error('Refresh remote cache failed:', err);
      alert('❌ Network error while refreshing remote cache.');
      btnRefresh.disabled = false;
      btnRefresh.textContent = origText;
    }
  });

  // ----------------------------------------------------
  // Inline Markdown Editor (Toast UI)
  // ----------------------------------------------------
  const btnEditMarkdown = document.getElementById('btn-edit-markdown');
  const btnCancelEdit = document.getElementById('btn-cancel-edit');
  const btnSaveInline = document.getElementById('btn-save-inline-markdown');
  const btnEditorGallery = document.getElementById('btn-editor-gallery');
  const readActions = document.getElementById('read-actions');
  const editActions = document.getElementById('edit-actions');
  const contentBody = document.getElementById('content-body');
  const editorContainer = document.getElementById('inline-editor-container');
  const rawMarkdownData = document.getElementById('raw-markdown-data');
  let tuiEditor = null;

  // Helper to detect complex HTML markup that would be stripped by Toast UI WYSIWYG mode
  function containsHtmlMarkup(md) {
    if (!md || typeof md !== 'string') return false;

    // Strip fenced code blocks (``` ... ``` and ~~~ ... ~~~) and inline code
    const stripped = md
      .replace(/^```[\s\S]*?^```/gm, '')
      .replace(/^~~~[\s\S]*?^~~~/gm, '')
      .replace(/`[^`\n]+`/g, '');

    // Check for HTML comments
    if (/<!--[\s\S]*?-->/.test(stripped)) return true;

    // Check for any HTML block tags (open or close)
    const blockTags = 'div|section|article|aside|header|footer|nav|main|style|script|iframe|video|audio|source|details|summary|canvas|svg|button|form|input|select|textarea|figure|figcaption|table|thead|tbody|tfoot|tr|td|th';
    const blockRegex = new RegExp(`</?(?:${blockTags})[\\s>/]`, 'i');
    if (blockRegex.test(stripped)) return true;

    // Check for any HTML tags with attributes (style, class, id, data-*, width, height, align, target, etc.)
    if (/<[a-z][a-z0-9]*\b[^>]*\b(?:style|class|id|data-[a-z0-9_-]+|align|width|height|target)\s*=/i.test(stripped)) {
      return true;
    }

    // Check for generic HTML tags that are not autolinks or benign line breaks (<br>, <br/>, <br />)
    const nonAutolink = stripped
      .replace(/<https?:\/\/[^>]+>/gi, '')
      .replace(/<[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}>/g, '')
      .replace(/<\/?br\s*\/?>/gi, '');
    if (/<[a-z][a-z0-9]*(?:\s+[^>]*)?>/i.test(nonAutolink)) {
      return true;
    }

    return false;
  }

  window.syncTuiEditorTheme = function(theme) {
    if (editorContainer) {
      const ui = editorContainer.querySelector('.toastui-editor-defaultUI');
      if (ui) {
        if (theme === 'dark') {
          ui.classList.add('toastui-editor-dark');
        } else {
          ui.classList.remove('toastui-editor-dark');
        }
      }
    }
  };

  if (btnEditMarkdown && editorContainer && rawMarkdownData) {
    const file = btnSaveInline ? btnSaveInline.getAttribute('data-file') : '';

    // Check lock status on page load to warn viewers
    if (window.SoftLock && file) {
      window.SoftLock.checkStatus(file).then(status => {
        if (status.locked && !status.isCurrentTab) {
          const who = status.user || 'another user';
          const where = status.isSameUser ? 'in another browser tab' : `by ${who}`;
          window.SoftLock.showNotification ? window.SoftLock.showNotification(`🔒 This document is currently being edited ${where}.`, 'warning') : null;
        }
      });
    }

    async function openMarkdownEditor(force = false) {
      if (window.SoftLock && file) {
        const lockRes = await window.SoftLock.acquire(file, force);
        if (!lockRes.success) {
          window.SoftLock.promptLockConflict(lockRes, () => {
            // User confirmed force takeover
            openMarkdownEditor(true);
          }, () => {
            // User cancelled
          });
          return;
        }
      }

      readActions.style.display = 'none';
      contentBody.style.display = 'none';
      editActions.style.display = 'flex';
      editorContainer.style.display = 'block';

      const mainContent = document.querySelector('.app-content');
      if (mainContent) {
        mainContent.classList.add('is-editing-doc');
        const header = document.querySelector('.content-header');
        if (header) {
          mainContent.style.setProperty('--edit-header-height', `${header.offsetHeight}px`);
        }
      }

      const rawContent = rawMarkdownData.value || '';
      const hasHtml = containsHtmlMarkup(rawContent);

      // Notice banner for HTML protected mode
      let htmlNotice = document.getElementById('editor-html-notice');
      if (hasHtml) {
        if (!htmlNotice) {
          htmlNotice = document.createElement('div');
          htmlNotice.id = 'editor-html-notice';
          htmlNotice.style.cssText = 'display: flex; align-items: center; justify-content: space-between; gap: 0.5rem; padding: 0.6rem 0.9rem; margin-bottom: 0.75rem; background: rgba(99, 102, 241, 0.12); border: 1px solid rgba(99, 102, 241, 0.35); border-radius: var(--radius-sm); font-size: 0.85rem; color: var(--text-secondary);';
          htmlNotice.innerHTML = `
            <div style="display: flex; align-items: center; gap: 0.5rem;">
              <span style="font-size: 1.1rem;">⚡</span>
              <span><strong>Markdown + HTML Mode (Live Preview):</strong> Custom HTML tags or styles detected. Editor locked to Markdown mode to prevent HTML sanitization.</span>
            </div>
            <span class="doc-badge badge-md" style="font-size: 0.75rem; padding: 0.2rem 0.5rem; border-radius: 4px; white-space: nowrap;">PROTECTED HTML</span>
          `;
          editorContainer.parentNode.insertBefore(htmlNotice, editorContainer);
        } else {
          htmlNotice.style.display = 'flex';
        }
      } else if (htmlNotice) {
        htmlNotice.style.display = 'none';
      }

      if (!tuiEditor) {
        const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
        
        const createGalleryToolbarButton = () => {
          const btn = document.createElement('button');
          btn.type = 'button';
          btn.className = 'toastui-editor-toolbar-icons gallery-editor-toolbar-btn';
          btn.style.backgroundImage = 'none';
          btn.style.fontSize = '15px';
          btn.style.lineHeight = '1';
          btn.style.padding = '0';
          btn.style.margin = '0';
          btn.style.display = 'inline-flex';
          btn.style.alignItems = 'center';
          btn.style.justifyContent = 'center';
          btn.style.cursor = 'pointer';
          btn.setAttribute('aria-label', 'Browse Image Gallery');
          btn.title = 'Browse and select images from gallery';
          btn.innerHTML = '🖼️';
          btn.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            if (typeof window.openGalleryModal === 'function') {
              window.openGalleryModal();
            } else {
              const utilBtn = document.getElementById('btn-util-gallery');
              if (utilBtn) utilBtn.click();
            }
          });
          return btn;
        };

        const editorConfig = {
          el: editorContainer,
          initialValue: rawContent,
          initialEditType: hasHtml ? 'markdown' : 'wysiwyg',
          previewStyle: 'vertical',
          height: '600px',
          theme: isDark ? 'dark' : '',
          usageStatistics: false,
          hideModeSwitch: hasHtml,
          toolbarItems: [
            ['heading', 'bold', 'italic', 'strike'],
            ['hr', 'quote'],
            ['ul', 'ol', 'task', 'indent', 'outdent'],
            ['table', 'image', 'link'],
            ['code', 'codeblock'],
            [
              {
                name: 'gallery',
                tooltip: 'Browse Image Gallery',
                el: createGalleryToolbarButton()
              }
            ]
          ],
          hooks: {
            addImageBlobHook: async (blob, callback) => {
              const formData = new FormData();
              formData.append('action', 'upload_image');
              formData.append('image', blob);

              try {
                const res = await fetch('api/admin.php', { method: 'POST', body: formData });
                const data = await res.json();
                if (data.success) {
                  callback(data.url, data.alt || 'image');
                } else {
                  alert('Image upload failed: ' + (data.error || 'Unknown error'));
                }
              } catch (err) {
                alert('Network request failed during image upload');
              }
            }
          }
        };

        try {
          tuiEditor = new toastui.Editor(editorConfig);
        } catch (initErr) {
          console.warn('Toast UI editor initial mode failed, falling back to Markdown mode:', initErr);
          editorConfig.initialEditType = 'markdown';
          editorConfig.hideModeSwitch = true;
          try {
            tuiEditor = new toastui.Editor(editorConfig);
          } catch (fatalErr) {
            console.error('Toast UI Editor fatal initialization error:', fatalErr);
            alert('Failed to initialize document editor: ' + fatalErr.message);
            if (htmlNotice) htmlNotice.style.display = 'none';
            editActions.style.display = 'none';
            editorContainer.style.display = 'none';
            readActions.style.display = 'flex';
            contentBody.style.display = 'block';
            return;
          }
        }
        window.tuiEditorInstance = tuiEditor;

        // Guard mode switching: if document contains HTML, block switching to WYSIWYG
        if (tuiEditor.eventEmitter) {
          const origEmit = tuiEditor.eventEmitter.emit.bind(tuiEditor.eventEmitter);
          tuiEditor.eventEmitter.emit = function(type, ...args) {
            if (type === 'needChangeMode' && args[0] === 'wysiwyg') {
              const currentContent = tuiEditor.getMarkdown();
              if (containsHtmlMarkup(currentContent)) {
                alert('⚠️ Cannot switch to WYSIWYG mode: the document contains custom HTML tags or inline styles which would be stripped. Please continue editing in Markdown mode.');
                return;
              }
            }
            return origEmit(type, ...args);
          };
        }

        // Auto-save local draft on editor changes
        tuiEditor.on('change', () => {
          if (window.SoftLock && file) {
            window.SoftLock.saveDraft(file, tuiEditor.getMarkdown());
          }
        });
      }
    }

    btnEditMarkdown.addEventListener('click', () => {
      openMarkdownEditor(false);
    });

    if (btnEditorGallery) {
      btnEditorGallery.addEventListener('click', (e) => {
        e.preventDefault();
        if (typeof window.openGalleryModal === 'function') {
          window.openGalleryModal();
        } else {
          const utilBtn = document.getElementById('btn-util-gallery');
          if (utilBtn) utilBtn.click();
        }
      });
    }

    if (window.SoftLock) {
      window.SoftLock.onEvicted((evictedFile) => {
        if (evictedFile === file && tuiEditor) {
          window.SoftLock.saveDraft(file, tuiEditor.getMarkdown());
        }
      });
    }
  }

  if (btnCancelEdit) {
    btnCancelEdit.addEventListener('click', async () => {
      const file = btnSaveInline ? btnSaveInline.getAttribute('data-file') : '';
      if (window.SoftLock && file) {
        await window.SoftLock.release(file);
        window.SoftLock.clearDraft(file);
      }
      const htmlNotice = document.getElementById('editor-html-notice');
      if (htmlNotice) htmlNotice.style.display = 'none';
      if (tuiEditor && rawMarkdownData) {
        tuiEditor.setMarkdown(rawMarkdownData.value);
      }
      const mainContent = document.querySelector('.app-content');
      if (mainContent) mainContent.classList.remove('is-editing-doc');
      editActions.style.display = 'none';
      editorContainer.style.display = 'none';
      readActions.style.display = 'flex';
      contentBody.style.display = 'block';
    });
  }

  if (btnSaveInline) {
    btnSaveInline.addEventListener('click', async () => {
      if (!tuiEditor) return;
      
      const file = btnSaveInline.getAttribute('data-file');
      const content = tuiEditor.getMarkdown();
      
      const formData = new FormData();
      formData.append('action', 'save_markdown');
      formData.append('file', file);
      formData.append('content_base64', btoa(unescape(encodeURIComponent(content))));
      if (window.SoftLock) {
        formData.append('tab_id', window.SoftLock.getTabId());
      }

      try {
        const res = await fetch('api/admin.php', { method: 'POST', body: formData });
        const data = await res.json();
        if (data.success) {
          if (window.SoftLock) {
            window.SoftLock.clearDraft(file);
          }
          if (rawMarkdownData) {
            rawMarkdownData.value = content;
          }
          window.location.reload();
        } else {
          if (data.code === 'LOCKED_BY_OTHER') {
            alert('Save Blocked: ' + (data.error || 'The document lock belongs to another session.'));
          } else {
            alert('Save failed: ' + (data.error || 'Unknown error'));
          }
        }
      } catch (err) {
        alert('Save request failed');
      }
    });

    // Window resize handler to maintain exact sticky toolbar offset
    window.addEventListener('resize', () => {
      const mainContent = document.querySelector('.app-content.is-editing-doc');
      const header = document.querySelector('.content-header');
      if (mainContent && header) {
        mainContent.style.setProperty('--edit-header-height', `${header.offsetHeight}px`);
      }
    });

    // Ctrl+S / Cmd+S shortcut inside inline Markdown editor
    document.addEventListener('keydown', (e) => {
      if ((e.ctrlKey || e.metaKey) && (e.key === 's' || e.key === 'S')) {
        if (editorContainer && editorContainer.style.display !== 'none') {
          e.preventDefault();
          if (btnSaveInline && !btnSaveInline.disabled) {
            btnSaveInline.click();
          }
        }
      }
    });
  }

  // Delete Chapter
  const deleteChapterBtn = document.getElementById('btn-delete-chapter');
  if (deleteChapterBtn) {
    deleteChapterBtn.addEventListener('click', async () => {
      if (!confirm('Are you sure you want to delete this document entry from the wiki structure?')) {
        return;
      }
      const bookId = deleteChapterBtn.getAttribute('data-book');
      const slug = deleteChapterBtn.getAttribute('data-slug');

      const formData = new FormData();
      formData.append('action', 'delete_chapter');
      formData.append('bookId', bookId);
      formData.append('slug', slug);

      try {
        const res = await fetch('api/admin.php?action=delete_chapter', { method: 'POST', body: formData });
        const data = await res.json();
        if (data.success) {
          window.location.href = `${encodeURIComponent(bookId)}`;
        } else {
          alert('Delete failed: ' + (data.error || 'Unknown error'));
        }
      } catch (err) {
        alert('Delete request failed');
      }
    });
  }

  // Delete Link
  const deleteLinkBtn = document.getElementById('btn-delete-link');
  if (deleteLinkBtn) {
    deleteLinkBtn.addEventListener('click', async () => {
      if (!confirm('Are you sure you want to delete this hyperlink?')) {
        return;
      }
      const slug = document.getElementById('edit-link-slug-hidden')?.value;
      const bookId = document.getElementById('edit-link-book-hidden')?.value || '';

      const formData = new FormData();
      formData.append('action', 'delete_chapter');
      formData.append('bookId', bookId);
      formData.append('slug', slug);

      try {
        const res = await fetch('api/admin.php?action=delete_chapter', { method: 'POST', body: formData });
        const data = await res.json();
        if (data.success) {
          window.location.reload();
        } else {
          alert('Delete failed: ' + (data.error || 'Unknown error'));
        }
      } catch (err) {
        alert('Delete request failed');
      }
    });
  }

  // Edit Link Icons in Sidebar
  document.querySelectorAll('.btn-edit-link-icon').forEach(btn => {
    btn.addEventListener('click', (e) => {
      e.preventDefault();
      e.stopPropagation();

      const modal = document.getElementById('edit-link-modal');
      if (!modal) return;

      const slug = btn.getAttribute('data-slug') || '';
      const bookId = btn.getAttribute('data-book-id') || '';
      const title = btn.getAttribute('data-title') || '';
      const url = btn.getAttribute('data-url') || '';
      const description = btn.getAttribute('data-description') || '';

      const slugInput = document.getElementById('edit-link-slug-hidden');
      const bookInput = document.getElementById('edit-link-book-hidden');
      const titleInput = document.getElementById('edit-link-title');
      const urlInput = document.getElementById('edit-link-url');
      const descInput = document.getElementById('edit-link-description');

      if (slugInput) slugInput.value = slug;
      if (bookInput) bookInput.value = bookId;
      if (titleInput) titleInput.value = title;
      if (urlInput) urlInput.value = url;
      if (descInput) descInput.value = description;

      modal.classList.add('open');
    });
  });

  // ----------------------------------------------------
  // HTML5 Drag and Drop Engine for Menu Reordering
  // ----------------------------------------------------
  let draggedElement = null;

  function clearDragHighlights() {
    document.querySelectorAll('.drag-over-above, .drag-over-below, .drag-over-inside').forEach(el => {
      el.classList.remove('drag-over-above', 'drag-over-below', 'drag-over-inside');
    });
  }

  function updateTopLinkVisuals(el) {
    if (!el || el.getAttribute('data-doc-type') !== 'link') return;
    const isTopLevel = el.parentElement && el.parentElement.classList.contains('sidebar-nav');
    const titleContainer = el.querySelector('.nav-link-title-container');

    if (isTopLevel) {
      el.classList.add('nav-top-link');
      if (titleContainer && !titleContainer.querySelector('.top-link-icon')) {
        const icon = document.createElement('span');
        icon.className = 'top-link-icon';
        icon.textContent = '🔗';
        const dragHandle = titleContainer.querySelector('.drag-handle');
        if (dragHandle) {
          dragHandle.insertAdjacentElement('afterend', icon);
          icon.insertAdjacentText('afterend', ' ');
        } else {
          titleContainer.prepend(icon);
          icon.insertAdjacentText('afterend', ' ');
        }
      }
    } else {
      el.classList.remove('nav-top-link');
      const icon = el.querySelector('.top-link-icon');
      if (icon) {
        if (icon.nextSibling && icon.nextSibling.nodeType === Node.TEXT_NODE) {
          icon.nextSibling.textContent = icon.nextSibling.textContent.replace(/^\s+/, '');
        }
        icon.remove();
      }
    }
  }

  function extractDocumentNodeFromDOM(child) {
    const docItem = {
      title: child.getAttribute('data-doc-title'),
      slug: child.getAttribute('data-doc-slug'),
      type: child.getAttribute('data-doc-type'),
      url: child.getAttribute('data-doc-url') || '',
      editUrl: child.getAttribute('data-doc-editurl') || '',
      file: child.getAttribute('data-doc-file') || ''
    };
    const docTheme = child.getAttribute('data-doc-theme');
    if (docTheme) docItem.theme = docTheme;
    const docDesc = child.getAttribute('data-doc-description');
    if (docDesc) docItem.description = docDesc;
    const docImg = child.getAttribute('data-doc-image');
    if (docImg) docItem.image = docImg;
    if (child.getAttribute('data-doc-readonly') === '1') {
      docItem.readOnly = true;
      docItem.editable = false;
    }
    return docItem;
  }

  // Extract tree array recursively from DOM structure
  function extractCategoryNodeFromDOM(catEl) {
    const nodeId = catEl.getAttribute('data-node-id');
    const nodeTitle = catEl.getAttribute('data-node-title');
    const nodeDesc = catEl.getAttribute('data-node-description');
    const nodeVis = catEl.getAttribute('data-node-visibility');
    const nodeTheme = catEl.getAttribute('data-node-theme');
    const nodeFolder = catEl.getAttribute('data-node-folder');
    const docList = catEl.querySelector(':scope > .nav-document-list');

    const items = [];

    if (docList) {
      Array.from(docList.children).forEach(child => {
        const dragType = child.getAttribute('data-drag-type');
        if (dragType === 'document') {
          items.push(extractDocumentNodeFromDOM(child));
        } else if (dragType === 'category') {
          items.push(extractCategoryNodeFromDOM(child));
        }
      });
    }

    const result = { id: nodeId, title: nodeTitle, type: 'folder' };
    if (nodeDesc) result.description = nodeDesc;
    if (nodeVis && nodeVis !== 'public') result.visibility = nodeVis;
    else if (nodeVis === 'public') result.visibility = 'public';
    if (nodeTheme) result.theme = nodeTheme;
    if (nodeFolder) result.folder = nodeFolder;
    if (catEl.getAttribute('data-category-readonly') === '1') {
      result.readOnly = true;
      result.editable = false;
    }

    if (items.length > 0) result.items = items;
    return result;
  }

  async function saveTreeStructureToBackend() {
    // Before building tree, ensure no non-link document sits directly in .sidebar-nav
    document.querySelectorAll('.sidebar-nav > [data-drag-type="document"]').forEach(topDoc => {
      if (topDoc.getAttribute('data-doc-type') !== 'link') {
        const nearbyCat = topDoc.nextElementSibling && topDoc.nextElementSibling.matches('.nav-category-item')
          ? topDoc.nextElementSibling
          : (topDoc.previousElementSibling && topDoc.previousElementSibling.matches('.nav-category-item')
            ? topDoc.previousElementSibling
            : document.querySelector('.sidebar-nav > .nav-category-item'));
        if (nearbyCat) {
          const list = nearbyCat.querySelector('.nav-document-list');
          if (list) {
            list.appendChild(topDoc);
          }
        }
      }
    });

    const tree = [];
    document.querySelectorAll('.sidebar-nav > [data-drag-type]').forEach(topEl => {
      const dragType = topEl.getAttribute('data-drag-type');
      if (dragType === 'category') {
        tree.push(extractCategoryNodeFromDOM(topEl));
      } else if (dragType === 'document') {
        const docType = topEl.getAttribute('data-doc-type');
        if (docType === 'link') {
          tree.push(extractDocumentNodeFromDOM(topEl));
        }
      }
    });

    try {
      const res = await fetch('api/admin.php?action=reorder_tree', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify({ tree })
      });
      const data = await res.json();
      if (!data.success) {
        console.error('Save failed:', data);
        alert('Failed to save reordered menu structure: ' + (data.error || 'Unknown error'));
      } else {
        if (data.updatedFiles) {
          for (const [slug, newFile] of Object.entries(data.updatedFiles)) {
            const el = document.querySelector(`[data-doc-slug="${slug}"]`);
            if (el) {
              el.setAttribute('data-doc-file', newFile);
            }
          }
        }
        console.log('Tree saved successfully:', tree);
      }
    } catch (err) {
      console.error('Fetch error:', err);
      alert('Network request failed while saving reordered menu');
    }
  }

  const draggables = document.querySelectorAll('[draggable="true"]');
  draggables.forEach(el => {
    el.addEventListener('dragstart', (e) => {
      e.stopPropagation();
      draggedElement = el;
      el.classList.add('dragging');
      e.dataTransfer.effectAllowed = 'move';
      e.dataTransfer.setData('text/plain', '');
    });

    el.addEventListener('dragend', (e) => {
      e.stopPropagation();
      if (draggedElement) draggedElement.classList.remove('dragging');
      draggedElement = null;
      clearDragHighlights();
    });

    el.addEventListener('dragover', (e) => {
      e.preventDefault();
      e.stopPropagation();
      if (!draggedElement || draggedElement === el) return;
      if (draggedElement.contains(el)) return;

      clearDragHighlights();
      const rect = el.getBoundingClientRect();
      const offsetY = e.clientY - rect.top;
      const height = rect.height;

      const draggedType = draggedElement.getAttribute('data-drag-type');
      const isDraggedLink = draggedElement.getAttribute('data-doc-type') === 'link';
      const isDraggedDoc = (draggedType === 'document' && !isDraggedLink);

      const targetType = el.getAttribute('data-drag-type');
      const isTargetTopLevel = el.parentElement && el.parentElement.classList.contains('sidebar-nav');

      // Non-link documents (chapters) CANNOT be placed at root level (.sidebar-nav)
      if (isDraggedDoc && isTargetTopLevel) {
        if (targetType === 'category') {
          // When dragging a chapter onto a top-level category, always treat it as dropping inside
          el.classList.add('drag-over-inside');
          return;
        }
        // If target is a top-level link or other root element, dropping a chapter here is invalid
        e.dataTransfer.dropEffect = 'none';
        return;
      }

      // Drag document or category onto a Category -> Drop inside if hovering in middle
      if (targetType === 'category') {
        if (offsetY > height * 0.25 && offsetY < height * 0.75) {
          el.classList.add('drag-over-inside');
          return;
        }
      }

      if (offsetY < height / 2) {
        el.classList.add('drag-over-above');
      } else {
        el.classList.add('drag-over-below');
      }
    });

    el.addEventListener('dragleave', (e) => {
      e.stopPropagation();
      el.classList.remove('drag-over-above', 'drag-over-below', 'drag-over-inside');
    });

    el.addEventListener('drop', async (e) => {
      e.preventDefault();
      e.stopPropagation();
      if (!draggedElement || draggedElement === el) return;
      if (draggedElement.contains(el)) return;

      const isAbove = el.classList.contains('drag-over-above');
      const isBelow = el.classList.contains('drag-over-below');
      const isInside = el.classList.contains('drag-over-inside');

      clearDragHighlights();

      const draggedType = draggedElement.getAttribute('data-drag-type');
      const isDraggedLink = draggedElement.getAttribute('data-doc-type') === 'link';
      const isDraggedDoc = (draggedType === 'document' && !isDraggedLink);

      const targetType = el.getAttribute('data-drag-type');
      const isTargetTopLevel = el.parentElement && el.parentElement.classList.contains('sidebar-nav');

      // Drop non-link document on a top-level item
      if (isDraggedDoc && isTargetTopLevel) {
        if (targetType === 'category') {
          const targetDocList = el.querySelector(':scope > .nav-document-list');
          if (targetDocList) {
            targetDocList.appendChild(draggedElement);
            el.classList.remove('collapsed');
            saveCategoryState(getCategoryId(el), false);
          }
          await saveTreeStructureToBackend();
        }
        return;
      }

      if (isInside && targetType === 'category') {
        const targetDocList = el.querySelector(':scope > .nav-document-list');
        if (targetDocList) {
          targetDocList.appendChild(draggedElement);
          el.classList.remove('collapsed');
          saveCategoryState(getCategoryId(el), false);
        }
      } else if (isAbove) {
        el.parentNode.insertBefore(draggedElement, el);
      } else if (isBelow) {
        el.parentNode.insertBefore(draggedElement, el.nextSibling);
      }

      // Safety check: ensure draggedElement never sits directly in .sidebar-nav if it is a non-link document
      if (isDraggedDoc && draggedElement.parentElement && draggedElement.parentElement.classList.contains('sidebar-nav')) {
        const nearbyCat = draggedElement.nextElementSibling && draggedElement.nextElementSibling.matches('.nav-category-item')
          ? draggedElement.nextElementSibling
          : (draggedElement.previousElementSibling && draggedElement.previousElementSibling.matches('.nav-category-item')
            ? draggedElement.previousElementSibling
            : document.querySelector('.sidebar-nav > .nav-category-item'));
        if (nearbyCat) {
          const list = nearbyCat.querySelector('.nav-document-list');
          if (list) {
            list.appendChild(draggedElement);
            nearbyCat.classList.remove('collapsed');
            saveCategoryState(getCategoryId(nearbyCat), false);
          }
        }
      }

      updateTopLinkVisuals(draggedElement);
      await saveTreeStructureToBackend();
    });
  });

  // Theme Editor Modal Logic
  const themeEditorModal = document.getElementById('theme-editor-modal');
  if (themeEditorModal) {
    const btnOpenThemeEditor = document.createElement('button');
    btnOpenThemeEditor.type = 'button';
    btnOpenThemeEditor.className = 'btn btn-outline';
    btnOpenThemeEditor.innerHTML = '🎨 Open Theme Editor';
    btnOpenThemeEditor.style.marginTop = '1rem';
    btnOpenThemeEditor.style.width = '100%';
    
    // Inject it into settings modal
    const settingsForm = document.getElementById('settings-form');
    if (settingsForm) {
      settingsForm.insertBefore(btnOpenThemeEditor, settingsForm.lastElementChild);
    }

    btnOpenThemeEditor.addEventListener('click', (e) => {
      e.preventDefault();
      document.getElementById('settings-modal').classList.remove('open');
      populateThemes(document.getElementById('editor-theme-selector'), '');
      themeEditorModal.classList.add('open');
    });

    const btnLoadTheme = document.getElementById('btn-load-theme');
    const editorArea = document.getElementById('theme-editor-area');
    const cssContent = document.getElementById('theme-css-content');
    const filenameInput = document.getElementById('theme-filename');
    const btnSaveTheme = document.getElementById('btn-save-theme');
    const themeSelector = document.getElementById('editor-theme-selector');

    btnLoadTheme.addEventListener('click', async () => {
      const theme = themeSelector.value;
      if (!theme) return alert('Select a theme to load');
      
      const formData = new FormData();
      formData.append('action', 'get_theme');
      formData.append('theme', theme);
      try {
        const res = await fetch('api/admin.php', { method: 'POST', body: formData });
        const data = await res.json();
        if (data.success) {
          cssContent.value = data.content;
          filenameInput.value = theme;
          editorArea.style.display = 'block';
        } else {
          alert('Failed to load theme: ' + data.error);
        }
      } catch (err) {
        alert('Network error loading theme');
      }
    });

    btnSaveTheme.addEventListener('click', async () => {
      const theme = filenameInput.value.trim();
      const content = cssContent.value;
      if (!theme.match(/^theme-[a-zA-Z0-9-]+\.css$/)) {
        return alert('Filename must start with theme- and end with .css');
      }

      const formData = new FormData();
      formData.append('action', 'save_theme');
      formData.append('theme', theme);
      formData.append('content_base64', btoa(unescape(encodeURIComponent(content))));

      try {
        const res = await fetch('api/admin.php', { method: 'POST', body: formData });
        const data = await res.json();
        if (data.success) {
          alert('Theme saved successfully!');
          availableThemes = []; // bust cache
          populateThemes(themeSelector, theme);
        } else {
          alert('Failed to save theme: ' + data.error);
        }
      } catch (err) {
        alert('Network error saving theme');
      }
    });
  }

  // Handle local markdown file upload in the Create Markdown Online tab
  const uploadMdLink = document.getElementById('upload-md-link');
  const mdFileUploadInput = document.getElementById('md-file-upload-input');
  const mdContentTextarea = document.getElementById('md-content-textarea');

  if (uploadMdLink && mdFileUploadInput && mdContentTextarea) {
    uploadMdLink.addEventListener('click', (e) => {
      e.preventDefault();
      mdFileUploadInput.click();
    });

    mdFileUploadInput.addEventListener('change', (e) => {
      const file = e.target.files[0];
      if (!file) return;

      const reader = new FileReader();
      reader.onload = (evt) => {
        mdContentTextarea.value = evt.target.result;
        
        // Auto-fill title if empty and we can find an H1 heading
        const titleInput = document.querySelector('#tab-create-md input[name="title"]');
        if (titleInput && !titleInput.value) {
          const content = evt.target.result;
          const match = content.match(/^#\s+(.+)$/m);
          if (match && match[1]) {
            titleInput.value = match[1].trim();
          } else {
            // Use filename as fallback title
            const fileNameWithoutExt = file.name.replace(/\.md$/i, '');
            titleInput.value = fileNameWithoutExt;
          }
        }
        
        // Clear input to allow uploading the same file again
        mdFileUploadInput.value = '';
      };
      reader.readAsText(file);
    });
  }

  // Update Notification Logic
  const btnSettings = document.getElementById('btn-settings');
  const btnUpdateAvailable = document.getElementById('btn-update-available');
  if (btnSettings && btnUpdateAvailable) {
    fetch('api/admin.php?action=check_updates')
      .then(res => res.json())
      .then(data => {
        if (data.success && data.has_update) {
          btnUpdateAvailable.style.display = 'inline-block';
          
          btnUpdateAvailable.addEventListener('click', () => {
            document.getElementById('update-version-text').textContent = data.version;
            const releaseNotesEl = document.getElementById('update-release-notes');
            if (releaseNotesEl) {
              releaseNotesEl.innerHTML = data.notes_html || escapeHtml(data.notes || '').replace(/\n/g, '<br>');
            }
            document.getElementById('update-zip-url').value = data.zip_url;
            document.getElementById('update-modal').classList.add('open');
          });
        }
      })
      .catch(err => console.error('Failed to check for updates', err));
      
    const updateForm = document.getElementById('update-form');
    if (updateForm) {
      updateForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const btnInstall = document.getElementById('btn-install-update');
        const loadingText = document.getElementById('update-loading-text');
        
        btnInstall.disabled = true;
        loadingText.style.display = 'block';
        
        const formData = new FormData(e.target);
        formData.append('action', 'install_update');
        
        try {
          const res = await fetch('api/admin.php', { method: 'POST', body: formData });
          const data = await res.json();
          if (data.success) {
            alert('Update installed successfully! The page will now reload.');
            window.location.reload(true);
          } else {
            alert('Failed to install update: ' + data.error);
            btnInstall.disabled = false;
            loadingText.style.display = 'none';
          }
        } catch (err) {
          alert('An error occurred while installing the update.');
          btnInstall.disabled = false;
          loadingText.style.display = 'none';
        }
      });
    }
  }

  // ----------------------------------------------------
  // Visual Diagram Rendering (Mermaid + Svgbob WASM)
  // ----------------------------------------------------
  let svgbobInitPromise = null;
  let svgbobRenderFn = null;

  async function loadSvgbob() {
    if (svgbobRenderFn) return svgbobRenderFn;
    if (!svgbobInitPromise) {
      svgbobInitPromise = (async () => {
        try {
          const wasmUrl = 'https://unpkg.com/svgbob-wasm@1.0.0/svgbob_wasm_bg.wasm';
          const res = await fetch(wasmUrl);
          if (!res.ok) throw new Error(`HTTP error ${res.status}`);
          const bytes = await res.arrayBuffer();
          const { instance } = await WebAssembly.instantiate(bytes, {});
          const wasm = instance.exports;

          const encoder = new TextEncoder();
          const decoder = new TextDecoder('utf-8', { ignoreBOM: true, fatal: true });
          let wasmVectorLen = 0;

          function getUint8Memory() {
            return new Uint8Array(wasm.memory.buffer);
          }
          function getInt32Memory() {
            return new Int32Array(wasm.memory.buffer);
          }

          function passStringToWasm(arg) {
            const buf = encoder.encode(arg);
            const ptr = wasm.__wbindgen_malloc(buf.length);
            getUint8Memory().subarray(ptr, ptr + buf.length).set(buf);
            wasmVectorLen = buf.length;
            return ptr;
          }

          svgbobRenderFn = function(ascii) {
            let r0, r1;
            try {
              const retptr = wasm.__wbindgen_add_to_stack_pointer(-16);
              const ptr0 = passStringToWasm(ascii);
              const len0 = wasmVectorLen;
              wasm.render(retptr, ptr0, len0);
              r0 = getInt32Memory()[retptr / 4 + 0];
              r1 = getInt32Memory()[retptr / 4 + 1];
              return decoder.decode(getUint8Memory().subarray(r0, r0 + r1));
            } finally {
              wasm.__wbindgen_add_to_stack_pointer(16);
              if (r0 !== undefined && r1 !== undefined) {
                wasm.__wbindgen_free(r0, r1);
              }
            }
          };

          return svgbobRenderFn;
        } catch (err) {
          console.warn('Could not initialize Svgbob WASM:', err);
          return null;
        }
      })();
    }
    return svgbobInitPromise;
  }

  const MERMAID_DIAGRAM_TYPES = [
    'flowchart', 'sequence', 'gantt', 'journey', 'class', 'state', 'er',
    'pie', 'quadrantChart', 'xyChart', 'requirement', 'mindmap', 'timeline',
    'gitGraph', 'c4', 'sankey', 'block'
  ];

  function getMermaidConfig(theme) {
    const diagramConfigs = {};
    MERMAID_DIAGRAM_TYPES.forEach(type => {
      diagramConfigs[type] = { useMaxWidth: false };
    });

    return {
      startOnLoad: false,
      theme: theme === 'light' ? 'default' : 'dark',
      securityLevel: 'loose',
      fontSize: 16,
      themeVariables: {
        fontSize: '16px'
      },
      fontFamily: 'Inter, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif',
      ...diagramConfigs
    };
  }

  function normalizeMermaidSvgs() {
    const svgs = document.querySelectorAll('.mermaid-diagram-container .mermaid svg');
    for (const svg of svgs) {
      svg.style.maxWidth = 'none';
      if (svg.getAttribute('width') === '100%') {
        const viewBox = svg.viewBox && svg.viewBox.baseVal;
        if (viewBox && viewBox.width > 0) {
          svg.setAttribute('width', viewBox.width);
        }
      }
    }
  }

  // ----------------------------------------------------
  // LaTeX / KaTeX Mathematical Symbols & Normalization
  // ----------------------------------------------------
  const LATEX_SYMBOL_MAP = {
    // Arrows
    'to': '→',
    '\\to': '→',
    'rightarrow': '→',
    '\\rightarrow': '→',
    'longrightarrow': '→',
    '\\longrightarrow': '→',
    'gets': '←',
    '\\gets': '←',
    'leftarrow': '←',
    '\\leftarrow': '←',
    'longleftarrow': '←',
    '\\longleftarrow': '←',
    'uparrow': '↑',
    '\\uparrow': '↑',
    'downarrow': '↓',
    '\\downarrow': '↓',
    'updownarrow': '↕',
    '\\updownarrow': '↕',
    'leftrightarrow': '↔',
    '\\leftrightarrow': '↔',
    'longleftrightarrow': '↔',
    '\\longleftrightarrow': '↔',
    'implies': '⇒',
    '\\implies': '⇒',
    'Rightarrow': '⇒',
    '\\Rightarrow': '⇒',
    'Leftarrow': '⇐',
    '\\Leftarrow': '⇐',
    'iff': '⇔',
    '\\iff': '⇔',
    'Leftrightarrow': '⇔',
    '\\Leftrightarrow': '⇔',
    'mapsto': '↦',
    '\\mapsto': '↦',

    // Comparison & Relations
    'le': '≤',
    '\\le': '≤',
    'leq': '≤',
    '\\leq': '≤',
    'ge': '≥',
    '\\ge': '≥',
    'geq': '≥',
    '\\geq': '≥',
    'ne': '≠',
    '\\ne': '≠',
    'neq': '≠',
    '\\neq': '≠',
    'approx': '≈',
    '\\approx': '≈',
    'equiv': '≡',
    '\\equiv': '≡',
    'sim': '∼',
    '\\sim': '∼',
    'simeq': '≃',
    '\\simeq': '≃',
    'propto': '∝',
    '\\propto': '∝',

    // Operators & Sets
    'pm': '±',
    '\\pm': '±',
    'mp': '∓',
    '\\mp': '∓',
    'times': '×',
    '\\times': '×',
    'div': '÷',
    '\\div': '÷',
    'cdot': '·',
    '\\cdot': '·',
    'circ': '∘',
    '\\circ': '∘',
    'bullet': '•',
    '\\bullet': '•',
    'infty': '∞',
    '\\infty': '∞',
    'in': '∈',
    '\\in': '∈',
    'notin': '∉',
    '\\notin': '∉',
    'subset': '⊂',
    '\\subset': '⊂',
    'supset': '⊃',
    '\\supset': '⊃',
    'subseteq': '⊆',
    '\\subseteq': '⊆',
    'supseteq': '⊇',
    '\\supseteq': '⊇',
    'cup': '∪',
    '\\cup': '∪',
    'cap': '∩',
    '\\cap': '∩',
    'empty': '∅',
    '\\empty': '∅',
    'emptyset': '∅',
    '\\emptyset': '∅',
    'forall': '∀',
    '\\forall': '∀',
    'exists': '∃',
    '\\exists': '∃',
    'nexists': '∄',
    '\\nexists': '∄',
    'partial': '∂',
    '\\partial': '∂',
    'nabla': '∇',
    '\\nabla': '∇',
    'sum': '∑',
    '\\sum': '∑',
    'prod': '∏',
    '\\prod': '∏',
    'int': '∫',
    '\\int': '∫',
    'sqrt': '√',
    '\\sqrt': '√',
    'therefore': '∴',
    '\\therefore': '∴',
    'because': '∵',
    '\\because': '∵',

    // Greek Alphabet (lowercase)
    'alpha': 'α',
    '\\alpha': 'α',
    'beta': 'β',
    '\\beta': 'β',
    'gamma': 'γ',
    '\\gamma': 'γ',
    'delta': 'δ',
    '\\delta': 'δ',
    'epsilon': 'ε',
    '\\epsilon': 'ε',
    'zeta': 'ζ',
    '\\zeta': 'ζ',
    'eta': 'η',
    '\\eta': 'η',
    'theta': 'θ',
    '\\theta': 'θ',
    'iota': 'ι',
    '\\iota': 'ι',
    'kappa': 'κ',
    '\\kappa': 'κ',
    'lambda': 'λ',
    '\\lambda': 'λ',
    'mu': 'μ',
    '\\mu': 'μ',
    'nu': 'ν',
    '\\nu': 'ν',
    'xi': 'ξ',
    '\\xi': 'ξ',
    'pi': 'π',
    '\\pi': 'π',
    'rho': 'ρ',
    '\\rho': 'ρ',
    'sigma': 'σ',
    '\\sigma': 'σ',
    'tau': 'τ',
    '\\tau': 'τ',
    'upsilon': 'υ',
    '\\upsilon': 'υ',
    'phi': 'φ',
    '\\phi': 'φ',
    'chi': 'χ',
    '\\chi': 'χ',
    'psi': 'ψ',
    '\\psi': 'ψ',
    'omega': 'ω',
    '\\omega': 'ω',

    // Greek Alphabet (uppercase)
    'Gamma': 'Γ',
    '\\Gamma': 'Γ',
    'Delta': 'Δ',
    '\\Delta': 'Δ',
    'Theta': 'Θ',
    '\\Theta': 'Θ',
    'Lambda': 'Λ',
    '\\Lambda': 'Λ',
    'Xi': 'Ξ',
    '\\Xi': 'Ξ',
    'Pi': 'Π',
    '\\Pi': 'Π',
    'Sigma': 'Σ',
    '\\Sigma': 'Σ',
    'Upsilon': 'Υ',
    '\\Upsilon': 'Υ',
    'Phi': 'Φ',
    '\\Phi': 'Φ',
    'Psi': 'Ψ',
    '\\Psi': 'Ψ',
    'Omega': 'Ω',
    '\\Omega': 'Ω',

    // Miscellaneous
    'checkmark': '✓',
    '\\checkmark': '✓',
    'dag': '†',
    '\\dag': '†',
    'ddag': '‡',
    '\\ddag': '‡',
    'star': '★',
    '\\star': '★',
    'degree': '°',
    '\\degree': '°'
  };

  function normalizeLatexSymbols(code) {
    if (!code || typeof code !== 'string') return code;

    // Split by $$...$$ blocks to avoid replacing inside complex math formulas
    const parts = code.split(/(\$\$[\s\S]*?\$\$)/g);
    for (let i = 0; i < parts.length; i += 2) {
      let seg = parts[i];
      // 1. Replace $symbol$ or $\symbol$
      seg = seg.replace(/\$(?:\\)?([a-zA-Z]+)\$/g, (match, sym) => {
        return LATEX_SYMBOL_MAP[sym] || LATEX_SYMBOL_MAP['\\' + sym] || match;
      });
      // 2. Replace \symbol when followed by boundary or non-letter
      seg = seg.replace(/\\([a-zA-Z]+)(?![a-zA-Z])/g, (match, sym) => {
        return LATEX_SYMBOL_MAP['\\' + sym] || LATEX_SYMBOL_MAP[sym] || match;
      });
      parts[i] = seg;
    }
    return parts.join('');
  }

  function renderMathExpressions(container) {
    const root = container || document.getElementById('content-body') || document.body;
    if (typeof katex === 'undefined') return;

    // 1. Render protected block equations
    const blockMaths = root.querySelectorAll('.katex-display-block[data-tex]');
    for (const el of blockMaths) {
      if (el.dataset.katexRendered === 'true') continue;
      const tex = el.getAttribute('data-tex');
      try {
        katex.render(tex, el, {
          displayMode: true,
          throwOnError: false
        });
        el.dataset.katexRendered = 'true';
      } catch (err) {
        console.warn('KaTeX block render error:', err);
      }
    }

    // 2. Render protected inline equations
    const inlineMaths = root.querySelectorAll('.katex-inline[data-tex], .katex-display-inline[data-tex]');
    for (const el of inlineMaths) {
      if (el.dataset.katexRendered === 'true') continue;
      const tex = el.getAttribute('data-tex');
      const isDisplay = el.classList.contains('katex-display-inline');
      try {
        katex.render(tex, el, {
          displayMode: isDisplay,
          throwOnError: false
        });
        el.dataset.katexRendered = 'true';
      } catch (err) {
        console.warn('KaTeX inline render error:', err);
      }
    }

    // 3. Fallback: Run auto-render on unhandled text if renderMathInElement is loaded
    if (typeof renderMathInElement === 'function') {
      try {
        renderMathInElement(root, {
          delimiters: [
            { left: '$$', right: '$$', display: true },
            { left: '\\[', right: '\\]', display: true },
            { left: '\\(', right: '\\)', display: false }
          ],
          ignoredTags: ['script', 'noscript', 'style', 'textarea', 'pre', 'code', 'option'],
          throwOnError: false
        });
      } catch (e) {
        // Fallback catch
      }
    }
  }

  window.renderMathExpressions = renderMathExpressions;
  window.normalizeLatexSymbols = normalizeLatexSymbols;

  async function renderMermaidDiagrams(theme) {
    if (typeof mermaid === 'undefined') return;

    try {
      mermaid.initialize(getMermaidConfig(theme));

      const mermaidCodeBlocks = document.querySelectorAll('.content-body pre > code.language-mermaid, .content-body pre.mermaid');
      for (const codeEl of mermaidCodeBlocks) {
        const pre = codeEl.closest('pre');
        if (!pre || pre.dataset.rendered === 'true') continue;

        const rawCode = codeEl.textContent.trim();
        const processedCode = normalizeLatexSymbols(rawCode);
        const container = document.createElement('div');
        container.className = 'mermaid-diagram-container';
        container.dataset.mermaidSrc = processedCode;
        container.dataset.rendered = 'true';

        const diagramEl = document.createElement('div');
        diagramEl.className = 'mermaid';
        diagramEl.textContent = processedCode;

        container.appendChild(diagramEl);
        pre.replaceWith(container);
      }

      const unrenderedMermaid = document.querySelectorAll('.mermaid-diagram-container .mermaid:not([data-processed="true"])');
      if (unrenderedMermaid.length > 0) {
        await mermaid.run({ nodes: unrenderedMermaid });
        normalizeMermaidSvgs();
      }
    } catch (err) {
      console.warn('Mermaid rendering error:', err);
    }
  }

  window.reRenderMermaidDiagrams = async function(theme) {
    if (typeof mermaid === 'undefined') return;
    const containers = document.querySelectorAll('.mermaid-diagram-container[data-mermaid-src]');
    if (containers.length === 0) return;

    try {
      mermaid.initialize(getMermaidConfig(theme));

      for (const container of containers) {
        const src = container.dataset.mermaidSrc;
        const processedCode = normalizeLatexSymbols(src);
        const newDiv = document.createElement('div');
        newDiv.className = 'mermaid';
        newDiv.textContent = processedCode;
        container.innerHTML = '';
        container.appendChild(newDiv);
      }

      await mermaid.run({ nodes: document.querySelectorAll('.mermaid-diagram-container .mermaid') });
      normalizeMermaidSvgs();
    } catch (e) {
      console.warn('Mermaid re-render failed:', e);
    }
  };

  async function renderSvgbobDiagrams() {
    const codeBlocks = document.querySelectorAll('.content-body pre code');
    if (codeBlocks.length === 0) return;

    for (const block of codeBlocks) {
      const pre = block.parentElement;
      if (!pre || pre.dataset.rendered === 'true') continue;

      const hasBoxChars = /[\u2500-\u257F]/.test(block.textContent);
      const isExplicitSvgbob = block.classList.contains('language-bob') ||
                               block.classList.contains('language-svgbob');
      const isGenericDiagram = (block.classList.contains('language-diagram') ||
                                block.classList.contains('language-ascii')) && !hasBoxChars;

      // Unicode box-drawing diagrams & tables are rendered as crisp monospace pre blocks
      if (hasBoxChars && !isExplicitSvgbob) {
        pre.style.lineHeight = '1.0';
        pre.style.fontFamily = '"DejaVu Sans Mono", "Liberation Mono", Menlo, Consolas, "Courier New", monospace';
        pre.style.letterSpacing = '0px';
        pre.style.fontSize = '0.84rem';
        block.style.fontFamily = 'inherit';
        block.style.letterSpacing = 'inherit';
        pre.style.padding = '1rem';
        pre.dataset.rendered = 'true';
        continue;
      }

      // ASCII art diagrams are converted to SVG vector diagrams using Svgbob WASM
      if (isExplicitSvgbob || isGenericDiagram) {
        const renderFn = await loadSvgbob();
        if (renderFn) {
          try {
            const svgOutput = renderFn(block.textContent);
            if (svgOutput && svgOutput.includes('<svg')) {
              const container = document.createElement('div');
              container.className = 'svgbob-diagram-container';
              container.dataset.rendered = 'true';
              container.innerHTML = svgOutput;
              pre.replaceWith(container);
              continue;
            }
          } catch (err) {
            console.warn('Svgbob render failed, falling back to styled pre:', err);
          }
        }

        // Fallback styling if Svgbob could not render
        pre.style.lineHeight = '1.0';
        pre.style.fontFamily = '"DejaVu Sans Mono", "Liberation Mono", Menlo, Consolas, "Courier New", monospace';
        block.style.fontFamily = 'inherit';
        pre.style.padding = '1rem';
        pre.dataset.rendered = 'true';
      }
    }
  }

  async function renderVisualDiagrams() {
    const currentTheme = document.documentElement.getAttribute('data-theme') || 'dark';
    await renderMermaidDiagrams(currentTheme);
    await renderSvgbobDiagrams();
  }

  // ----------------------------------------------------
  // Playable Video Embeds (YouTube, Vimeo, Loom, Direct Videos)
  // ----------------------------------------------------
  function parseYouTube(url) {
    try {
      const parsed = new URL(url, window.location.href);
      const host = parsed.hostname.toLowerCase().replace(/^www\./, '');
      let videoId = null;
      let startSeconds = null;

      if (host === 'youtube.com' || host === 'm.youtube.com' || host === 'music.youtube.com') {
        if (parsed.pathname === '/watch') {
          videoId = parsed.searchParams.get('v');
        } else if (parsed.pathname.startsWith('/embed/')) {
          videoId = parsed.pathname.split('/')[2];
        } else if (parsed.pathname.startsWith('/shorts/')) {
          videoId = parsed.pathname.split('/')[2];
        } else if (parsed.pathname.startsWith('/v/')) {
          videoId = parsed.pathname.split('/')[2];
        }
      } else if (host === 'youtu.be') {
        videoId = parsed.pathname.replace(/^\//, '').split('/')[0];
      }

      if (!videoId || !/^[a-zA-Z0-9_-]{11}$/.test(videoId)) {
        return null;
      }

      const tParam = parsed.searchParams.get('t') || parsed.searchParams.get('start');
      if (tParam) {
        if (/^\d+$/.test(tParam)) {
          startSeconds = parseInt(tParam, 10);
        } else {
          let total = 0;
          const hMatch = tParam.match(/(\d+)h/i);
          const mMatch = tParam.match(/(\d+)m/i);
          const sMatch = tParam.match(/(\d+)s/i);
          if (hMatch) total += parseInt(hMatch[1], 10) * 3600;
          if (mMatch) total += parseInt(mMatch[1], 10) * 60;
          if (sMatch) total += parseInt(sMatch[1], 10);
          if (total > 0) startSeconds = total;
        }
      }

      let embedUrl = `https://www.youtube-nocookie.com/embed/${videoId}?rel=0`;
      if (startSeconds) {
        embedUrl += `&start=${startSeconds}`;
      }

      return {
        type: 'youtube',
        embedUrl,
        title: 'YouTube video player'
      };
    } catch (e) {
      return null;
    }
  }

  function parseVimeo(url) {
    try {
      const parsed = new URL(url, window.location.href);
      const host = parsed.hostname.toLowerCase().replace(/^www\./, '');
      if (host === 'vimeo.com' || host === 'player.vimeo.com') {
        let videoId = null;
        if (host === 'player.vimeo.com' && parsed.pathname.startsWith('/video/')) {
          videoId = parsed.pathname.split('/')[2];
        } else {
          const match = parsed.pathname.match(/\/(?:channels\/(?:\w+\/)?|groups\/(?:[^\/]*)\/videos\/|album\/(?:\d+)\/video\/|video\/|)(\d+)/);
          if (match) {
            videoId = match[1];
          }
        }
        if (videoId && /^\d+$/.test(videoId)) {
          return {
            type: 'vimeo',
            embedUrl: `https://player.vimeo.com/video/${videoId}?dnt=1&title=0&byline=0&portrait=0`,
            title: 'Vimeo video player'
          };
        }
      }
      return null;
    } catch (e) {
      return null;
    }
  }

  function parseLoom(url) {
    try {
      const parsed = new URL(url, window.location.href);
      const host = parsed.hostname.toLowerCase().replace(/^www\./, '');
      if (host === 'loom.com') {
        let videoId = null;
        if (parsed.pathname.startsWith('/share/')) {
          videoId = parsed.pathname.split('/')[2];
        } else if (parsed.pathname.startsWith('/embed/')) {
          videoId = parsed.pathname.split('/')[2];
        }
        if (videoId && /^[a-zA-Z0-9]+$/.test(videoId)) {
          return {
            type: 'loom',
            embedUrl: `https://www.loom.com/embed/${videoId}?hide_owner=true&hide_share=true&hide_title=true&hideEmbedTopBar=true`,
            title: 'Loom video player'
          };
        }
      }
      return null;
    } catch (e) {
      return null;
    }
  }

  function parseDirectVideo(url) {
    try {
      const parsed = new URL(url, window.location.href);
      const pathname = parsed.pathname;
      const match = pathname.match(/\.([a-zA-Z0-9]+)$/);
      if (match) {
        const ext = match[1].toLowerCase();
        const mimeMap = {
          mp4: 'video/mp4',
          webm: 'video/webm',
          ogg: 'video/ogg',
          ogv: 'video/ogg',
          mov: 'video/mp4'
        };
        if (mimeMap[ext]) {
          return {
            type: 'direct',
            videoUrl: url,
            mimeType: mimeMap[ext],
            title: 'Direct video player'
          };
        }
      }
      return null;
    } catch (e) {
      return null;
    }
  }

  function detectVideo(url) {
    if (!url || typeof url !== 'string') return null;
    const trimmed = url.trim();
    return parseYouTube(trimmed) || parseVimeo(trimmed) || parseLoom(trimmed) || parseDirectVideo(trimmed);
  }

  function createVideoEmbed(targetEl, videoInfo, caption) {
    const wrapper = document.createElement('div');
    wrapper.className = 'qwiki-video-wrapper';
    wrapper.dataset.videoProcessed = 'true';

    if (videoInfo.type === 'direct') {
      const videoEl = document.createElement('video');
      videoEl.className = 'qwiki-video-player';
      videoEl.controls = true;
      videoEl.preload = 'metadata';
      videoEl.playsInline = true;

      const sourceEl = document.createElement('source');
      sourceEl.src = videoInfo.videoUrl;
      sourceEl.type = videoInfo.mimeType;
      videoEl.appendChild(sourceEl);

      const fallback = document.createElement('p');
      fallback.style.padding = '0.5rem';
      fallback.style.fontSize = '0.85rem';
      fallback.innerHTML = `Your browser does not support the video tag. <a href="${videoInfo.videoUrl}" target="_blank" rel="noopener noreferrer">Download video</a>`;
      videoEl.appendChild(fallback);

      wrapper.appendChild(videoEl);
    } else {
      const container = document.createElement('div');
      container.className = 'qwiki-video-container';

      const iframe = document.createElement('iframe');
      iframe.src = videoInfo.embedUrl;
      iframe.title = caption || videoInfo.title;
      iframe.allow = 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share';
      iframe.allowFullscreen = true;
      iframe.loading = 'lazy';

      container.appendChild(iframe);
      wrapper.appendChild(container);
    }

    if (caption && caption.trim().length > 0) {
      const capDiv = document.createElement('div');
      capDiv.className = 'qwiki-video-caption';
      capDiv.textContent = caption.trim();
      wrapper.appendChild(capDiv);
    }

    targetEl.replaceWith(wrapper);
  }

  function initVideoEmbeds(container) {
    const root = container || document.getElementById('content-body');
    if (!root) return;

    // 1. Process existing native video tags to ensure consistent styling
    const existingVideos = root.querySelectorAll('video');
    existingVideos.forEach(v => {
      if (!v.classList.contains('qwiki-video-player')) {
        v.classList.add('qwiki-video-player');
      }
    });

    // 2. Scan paragraphs for standalone video links or images
    const paragraphs = root.querySelectorAll('p');
    paragraphs.forEach(p => {
      if (p.dataset.videoProcessed === 'true') return;

      const links = p.querySelectorAll('a');
      const images = p.querySelectorAll('img');

      // Case A: Paragraph contains ONLY a single <a> link
      if (links.length === 1 && images.length === 0) {
        const a = links[0];
        const pText = p.textContent.trim();
        const aText = a.textContent.trim();

        if (pText === aText && pText.length > 0) {
          const href = a.getAttribute('href');
          const videoInfo = detectVideo(href);
          if (videoInfo) {
            let caption = null;
            if (aText !== href && !aText.startsWith('http://') && !aText.startsWith('https://')) {
              caption = aText;
            }
            createVideoEmbed(p, videoInfo, caption);
            return;
          }
        }
      }

      // Case B: Paragraph contains ONLY a single <img> tag pointing to a video file
      if (images.length === 1 && links.length === 0) {
        const img = images[0];
        const pText = p.textContent.trim();
        if (pText === '') {
          const src = img.getAttribute('src');
          const videoInfo = parseDirectVideo(src);
          if (videoInfo) {
            const caption = img.getAttribute('alt') || null;
            createVideoEmbed(p, videoInfo, caption);
            return;
          }
        }
      }
    });
  }

  window.initVideoEmbeds = initVideoEmbeds;

  // ----------------------------------------------------
  // Generate Table of Contents
  // ----------------------------------------------------
  function generateTableOfContents() {
    const contentBody = document.getElementById('content-body');
    const tocContainer = document.getElementById('app-toc');
    const tocContent = document.getElementById('toc-content');
    
    if (!contentBody || !tocContainer || !tocContent) return;
    
    const headings = Array.from(contentBody.querySelectorAll('h1, h2, h3')).filter(h => !h.closest('.modal-overlay'));
    if (headings.length === 0) return;
    
    // Show the TOC container
    tocContainer.classList.add('show');
    
    const slugify = (text) => {
      return text.toString().toLowerCase()
        .replace(/\s+/g, '-')
        .replace(/[^\w\-]+/g, '')
        .replace(/\-\-+/g, '-')
        .replace(/^-+/, '')
        .replace(/-+$/, '');
    };
    
    const rootUl = document.createElement('ul');
    let stack = [{ level: 0, el: rootUl }];
    
    headings.forEach((heading, index) => {
      const level = parseInt(heading.tagName.substring(1));
      
      // Ensure heading has an ID
      if (!heading.id) {
        let baseId = slugify(heading.textContent) || 'section-' + index;
        let id = baseId;
        let counter = 1;
        while (document.getElementById(id)) {
          id = baseId + '-' + counter;
          counter++;
        }
        heading.id = id;
      }
      
      const li = document.createElement('li');
      
      const linkContainer = document.createElement('div');
      linkContainer.className = 'toc-link-container';
      
      const toggleBtn = document.createElement('div');
      toggleBtn.className = 'toc-toggle empty';
      toggleBtn.innerHTML = '▶';
      
      const link = document.createElement('a');
      link.className = 'toc-link';
      link.href = window.location.href.split('#')[0] + '#' + heading.id;
      link.setAttribute('data-target-id', heading.id);
      link.textContent = heading.textContent;
      
      linkContainer.appendChild(toggleBtn);
      linkContainer.appendChild(link);
      li.appendChild(linkContainer);
      
      const childUl = document.createElement('ul');
      li.appendChild(childUl);
      
      // Adjust stack for nesting
      while (stack.length > 1 && stack[stack.length - 1].level >= level) {
        stack.pop();
      }
      
      // Append to parent
      const parentNode = stack[stack.length - 1];
      parentNode.el.appendChild(li);
      
      // If we added this to a parent, update parent's toggle button
      if (stack.length > 1) {
        const parentLi = parentNode.el.closest('li');
        if (parentLi) {
          const parentToggle = parentLi.querySelector('.toc-toggle');
          if (parentToggle && parentToggle.classList.contains('empty')) {
            parentToggle.classList.remove('empty');
            // Add click listener
            parentToggle.addEventListener('click', (e) => {
              e.stopPropagation();
              parentToggle.classList.toggle('expanded');
              const ulToToggle = parentLi.querySelector('ul');
              if (ulToToggle) ulToToggle.classList.toggle('expanded');
            });
          }
        }
      }
      
      // Push new parent to stack
      stack.push({ level: level, el: childUl });
    });
    
    tocContent.appendChild(rootUl);
    
    // Active link highlighting on scroll
    const observer = new IntersectionObserver((entries) => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          const id = entry.target.id;
          document.querySelectorAll('.toc-link').forEach(l => l.classList.remove('active'));
          const activeLink = document.querySelector(`.toc-link[data-target-id="${id}"]`);
          if (activeLink) {
            activeLink.classList.add('active');
            
            // Auto expand parents
            let parentLi = activeLink.closest('li').parentElement.closest('li');
            while (parentLi) {
              const toggle = parentLi.querySelector('.toc-toggle');
              const ul = parentLi.querySelector('ul');
              if (toggle && !toggle.classList.contains('expanded')) toggle.classList.add('expanded');
              if (ul && !ul.classList.contains('expanded')) ul.classList.add('expanded');
              parentLi = parentLi.parentElement.closest('li');
            }
          }
        }
      });
    }, { rootMargin: '-10% 0px -80% 0px' });
    
    headings.forEach(h => observer.observe(h));
  }

  // ----------------------------------------------------
  // In-Page Anchor Links Normalization & Navigation
  // ----------------------------------------------------
  function initAnchorLinks() {
    const contentBody = document.getElementById('content-body');
    if (!contentBody) return;

    const slugify = (text) => {
      return text.toString().toLowerCase()
        .replace(/\s+/g, '-')
        .replace(/[^\w\-]+/g, '')
        .replace(/\-\-+/g, '-')
        .replace(/^-+/, '')
        .replace(/-+$/, '');
    };

    // Ensure all headings (h1..h6) have IDs if not assigned by server
    const allHeadings = contentBody.querySelectorAll('h1, h2, h3, h4, h5, h6');
    allHeadings.forEach((heading, index) => {
      if (!heading.id) {
        let baseId = slugify(heading.textContent) || 'section-' + index;
        let id = baseId;
        let counter = 1;
        while (document.getElementById(id)) {
          id = baseId + '-' + counter;
          counter++;
        }
        heading.id = id;
      }
    });

    // Normalize in-page fragment links in content body
    // Because <base href> is defined in <head>, relative links like href="#anchor"
    // would otherwise resolve against <base href> (the site root/homepage).
    const currentBaseUrl = window.location.href.split('#')[0];
    const anchorLinks = contentBody.querySelectorAll('a[href^="#"]');
    anchorLinks.forEach(link => {
      const rawHref = link.getAttribute('href');
      if (rawHref && rawHref.startsWith('#') && rawHref.length > 1) {
        link.href = currentBaseUrl + rawHref;
      }
    });
  }

  function scrollToAnchorTarget(targetId, updateHistory = true) {
    if (!targetId) return false;
    const cleanId = decodeURIComponent(targetId.replace(/^#/, ''));
    if (!cleanId) return false;

    let targetEl = document.getElementById(cleanId);
    if (!targetEl) {
      try {
        targetEl = document.querySelector(`[name="${CSS.escape(cleanId)}"]`);
      } catch (e) {
        targetEl = null;
      }
    }

    if (targetEl) {
      targetEl.scrollIntoView({ behavior: 'smooth', block: 'start' });
      if (updateHistory) {
        history.pushState(null, '', window.location.href.split('#')[0] + '#' + encodeURIComponent(cleanId));
      }
      return true;
    }
    return false;
  }

  // Intercept click on in-page anchor links across the document
  document.addEventListener('click', (e) => {
    const link = e.target.closest('a');
    if (!link) return;

    // Ignore modified clicks (Ctrl, Cmd, Shift, Alt) or right clicks
    if (e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;

    const href = link.getAttribute('href');
    if (!href) return;

    let hash = '';
    if (href.startsWith('#') && href.length > 1) {
      hash = href;
    } else {
      try {
        const linkUrl = new URL(link.href, window.location.origin);
        const currentUrl = new URL(window.location.href);
        if (linkUrl.origin === currentUrl.origin && linkUrl.pathname === currentUrl.pathname && linkUrl.search === currentUrl.search && linkUrl.hash) {
          hash = linkUrl.hash;
        }
      } catch (err) {}
    }

    if (hash && hash.length > 1) {
      const handled = scrollToAnchorTarget(hash, true);
      if (handled) {
        e.preventDefault();
      }
    }
  });

  // Handle browser Back / Forward history navigation with hash
  window.addEventListener('hashchange', () => {
    if (window.location.hash) {
      scrollToAnchorTarget(window.location.hash, false);
    }
  });

  // Handle initial page load with hash in URL
  function handleInitialHashNavigation() {
    if (window.location.hash && window.location.hash.length > 1) {
      setTimeout(() => {
        scrollToAnchorTarget(window.location.hash, false);
      }, 150);
    }
  }

  window.initAnchorLinks = initAnchorLinks;

  // Run on page load
  initVideoEmbeds();
  renderMathExpressions();
  renderVisualDiagrams();
  initAnchorLinks();
  generateTableOfContents();
  handleInitialHashNavigation();

  // Print / Download as PDF (Delegates to HTML iframe if active to enable full pagination)
  document.querySelectorAll('#btn-print-chapter').forEach(btn => {
    btn.addEventListener('click', () => {
      const htmlFrame = document.getElementById('current-html-frame');
      if (htmlFrame && htmlFrame.contentWindow) {
        try {
          htmlFrame.contentWindow.focus();
          htmlFrame.contentWindow.print();
          return;
        } catch(e) {}
      }
      window.print();
    });
  });

  // Document Sharing & Share Modal
  const btnShareChapter = document.getElementById('btn-share-chapter');
  const shareModal = document.getElementById('share-modal');
  const shareLinkInput = document.getElementById('share-link-input');
  const shareStatusContainer = document.getElementById('share-status-container');
  const btnCopyShareLink = document.getElementById('btn-copy-share-link');
  const shareAdminTogglePublic = document.getElementById('share-admin-toggle-public');
  const btnModalRegenerateShareKey = document.getElementById('btn-modal-regenerate-share-key');

  const updateShareStatusUI = (isPublic) => {
    if (!shareStatusContainer) return;
    if (isPublic) {
      shareStatusContainer.innerHTML = '<span class="share-status-badge share-status-public">🟢 Publicly Shareable (Full Screen)</span>';
    } else {
      shareStatusContainer.innerHTML = '<span class="share-status-badge share-status-restricted">🔴 Public Sharing Disabled by Administrator</span>';
    }
    if (shareAdminTogglePublic) {
      shareAdminTogglePublic.checked = !!isPublic;
    }
  };

  const updateModalSocialShareLinks = (url, title) => {
    if (!url || url.startsWith('Generating') || url.startsWith('Failed')) return;
    const encUrl = encodeURIComponent(url);
    const encTitle = encodeURIComponent(title || document.title);
    const btnX = document.getElementById('modal-share-x');
    const btnIn = document.getElementById('modal-share-linkedin');
    const btnFb = document.getElementById('modal-share-facebook');
    const btnWa = document.getElementById('modal-share-whatsapp');
    const btnTg = document.getElementById('modal-share-telegram');
    if (btnX) btnX.href = `https://twitter.com/intent/tweet?url=${encUrl}&text=${encTitle}`;
    if (btnIn) btnIn.href = `https://www.linkedin.com/sharing/share-offsite/?url=${encUrl}`;
    if (btnFb) btnFb.href = `https://www.facebook.com/sharer/sharer.php?u=${encUrl}`;
    if (btnWa) btnWa.href = `https://api.whatsapp.com/send?text=${encTitle}%20${encUrl}`;
    if (btnTg) btnTg.href = `https://t.me/share/url?url=${encUrl}&text=${encTitle}`;
  };

  if (btnShareChapter) {
    btnShareChapter.addEventListener('click', async () => {
      const slug = btnShareChapter.getAttribute('data-slug');
      if (shareModal && slug) {
        shareModal.classList.add('open');
        const initialShareUrl = btnShareChapter.getAttribute('data-share-url');
        const isInitiallyPublic = btnShareChapter.getAttribute('data-public-shareable') !== '0';
        if (initialShareUrl && shareLinkInput) {
          shareLinkInput.value = initialShareUrl;
          updateModalSocialShareLinks(initialShareUrl, btnShareChapter.getAttribute('data-title'));
          updateShareStatusUI(isInitiallyPublic);
        } else if (shareLinkInput) {
          shareLinkInput.value = 'Generating secure share link...';
        }
        try {
          const res = await fetch(`api/admin.php?action=get_or_create_share_key&slug=${encodeURIComponent(slug)}`);
          const data = await res.json();
          if (data.success) {
            if (shareLinkInput) shareLinkInput.value = data.shareUrl;
            updateModalSocialShareLinks(data.shareUrl, btnShareChapter.getAttribute('data-title'));
            updateShareStatusUI(data.publicShareable);
            btnShareChapter.setAttribute('data-share-key', data.shareKey);
            btnShareChapter.setAttribute('data-share-url', data.shareUrl);
            btnShareChapter.setAttribute('data-public-shareable', data.publicShareable ? '1' : '0');
          } else if (!initialShareUrl) {
            if (shareLinkInput) shareLinkInput.value = 'Failed: ' + (data.error || 'Unknown error');
          }
        } catch (err) {
          console.error('Failed to get share link:', err);
          if (shareLinkInput && !initialShareUrl) {
            shareLinkInput.value = window.location.href;
            updateModalSocialShareLinks(window.location.href, btnShareChapter.getAttribute('data-title'));
          }
        }
        return;
      }

      // Fallback for unauthenticated visitors browsing public docs: native share or copy standard URL
      const shareUrl = btnShareChapter.getAttribute('data-share-url') || window.location.href;
      const shareData = {
        title: document.title,
        url: shareUrl
      };
      if (navigator.share) {
        try {
          await navigator.share(shareData);
        } catch (err) {
          if (err.name !== 'AbortError') console.error('Error sharing:', err);
        }
      } else {
        try {
          if (navigator.clipboard && navigator.clipboard.writeText) {
            await navigator.clipboard.writeText(shareData.url);
          } else {
            const input = document.createElement('input');
            input.value = shareData.url;
            document.body.appendChild(input);
            input.select();
            document.execCommand('copy');
            document.body.removeChild(input);
          }
          const origContent = btnShareChapter.innerHTML;
          const origTitle = btnShareChapter.getAttribute('title');
          btnShareChapter.innerHTML = '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="#10b981" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>';
          btnShareChapter.setAttribute('title', 'Link Copied!');
          setTimeout(() => {
            btnShareChapter.innerHTML = origContent;
            if (origTitle) btnShareChapter.setAttribute('title', origTitle);
          }, 2000);
        } catch (err) {
          console.error('Failed to copy fallback link: ', err);
        }
      }
    });
  }

  // Copy button inside Share Modal
  if (btnCopyShareLink && shareLinkInput) {
    btnCopyShareLink.addEventListener('click', async () => {
      const urlToCopy = shareLinkInput.value;
      if (!urlToCopy || urlToCopy.startsWith('Generating') || urlToCopy.startsWith('Failed')) return;
      try {
        if (navigator.clipboard && navigator.clipboard.writeText) {
          await navigator.clipboard.writeText(urlToCopy);
        } else {
          shareLinkInput.select();
          document.execCommand('copy');
        }
        const origText = btnCopyShareLink.textContent;
        btnCopyShareLink.textContent = '✅ Copied!';
        setTimeout(() => {
          btnCopyShareLink.textContent = origText;
        }, 2000);
      } catch (err) {
        console.error('Failed to copy link:', err);
      }
    });
  }

  // Slack share button inside Share Modal (Copies link and opens Slack in a new tab)
  const btnModalSlack = document.getElementById('modal-share-slack');
  if (btnModalSlack) {
    btnModalSlack.addEventListener('click', async () => {
      const urlToCopy = (shareLinkInput && shareLinkInput.value && !shareLinkInput.value.startsWith('Generating') && !shareLinkInput.value.startsWith('Failed'))
        ? shareLinkInput.value
        : (btnShareChapter ? btnShareChapter.getAttribute('data-share-url') : window.location.href);
      if (!urlToCopy) return;

      try {
        if (navigator.clipboard && navigator.clipboard.writeText) {
          await navigator.clipboard.writeText(urlToCopy);
        } else if (shareLinkInput) {
          shareLinkInput.select();
          document.execCommand('copy');
        }
      } catch (err) {
        console.error('Failed to copy share link for Slack:', err);
      }

      const origText = btnModalSlack.textContent;
      btnModalSlack.textContent = '✅ Copied for Slack!';
      setTimeout(() => {
        btnModalSlack.textContent = origText;
      }, 2000);

      window.open('https://slack.com/app_redirect', '_blank', 'noopener,noreferrer');
    });
  }

  // Admin toggle for public sharing inside Share Modal
  if (shareAdminTogglePublic && btnShareChapter) {
    shareAdminTogglePublic.addEventListener('change', async () => {
      const slug = btnShareChapter.getAttribute('data-slug');
      if (!slug) return;
      const isPublic = shareAdminTogglePublic.checked;
      const formData = new FormData();
      formData.append('action', 'update_share_settings');
      formData.append('slug', slug);
      formData.append('publicShareable', isPublic ? '1' : '0');
      try {
        const res = await fetch('api/admin.php', { method: 'POST', body: formData });
        const data = await res.json();
        if (data.success) {
          updateShareStatusUI(data.publicShareable);
          btnShareChapter.setAttribute('data-public-shareable', data.publicShareable ? '1' : '0');
        } else {
          alert('Failed to update sharing: ' + (data.error || 'Unknown error'));
          shareAdminTogglePublic.checked = !isPublic;
        }
      } catch (err) {
        console.error('Failed to update share settings:', err);
        alert('Network error updating sharing status.');
        shareAdminTogglePublic.checked = !isPublic;
      }
    });
  }

  // Admin button to regenerate share key inside Share Modal
  if (btnModalRegenerateShareKey && btnShareChapter) {
    btnModalRegenerateShareKey.addEventListener('click', async () => {
      const slug = btnShareChapter.getAttribute('data-slug');
      if (!slug) return;
      if (!confirm('Are you sure you want to reset the unique share key? Any previously distributed links will immediately stop working.')) {
        return;
      }
      const isPublic = shareAdminTogglePublic ? (shareAdminTogglePublic.checked ? 1 : 0) : 1;
      const formData = new FormData();
      formData.append('action', 'update_share_settings');
      formData.append('slug', slug);
      formData.append('publicShareable', isPublic);
      formData.append('regenerate', '1');
      try {
        const res = await fetch('api/admin.php', { method: 'POST', body: formData });
        const data = await res.json();
        if (data.success) {
          if (shareLinkInput) shareLinkInput.value = data.shareUrl;
          updateModalSocialShareLinks(data.shareUrl, btnShareChapter.getAttribute('data-title'));
          btnShareChapter.setAttribute('data-share-key', data.shareKey);
          btnShareChapter.setAttribute('data-share-url', data.shareUrl);
          updateShareStatusUI(data.publicShareable);
          alert('✅ Share key reset successfully! The new link is ready to copy.');
        } else {
          alert('Failed to reset key: ' + (data.error || 'Unknown error'));
        }
      } catch (err) {
        console.error('Failed to reset share key:', err);
        alert('Network error while resetting share key.');
      }
    });
  }

  // Social Share Dropdown in Floating Bar (Share Mode)
  const btnShareSocial = document.getElementById('btn-share-social');
  const shareSocialMenu = document.getElementById('share-social-menu');
  if (btnShareSocial && shareSocialMenu) {
    btnShareSocial.addEventListener('click', (e) => {
      e.stopPropagation();
      shareSocialMenu.classList.toggle('show');
    });

    document.addEventListener('click', (e) => {
      if (!btnShareSocial.contains(e.target) && !shareSocialMenu.contains(e.target)) {
        shareSocialMenu.classList.remove('show');
      }
    });
  }

  // Copy share link button in floating share bar
  const btnCopyBarShare = document.getElementById('btn-copy-bar-share');
  if (btnCopyBarShare) {
    btnCopyBarShare.addEventListener('click', async () => {
      const urlToCopy = btnCopyBarShare.getAttribute('data-url') || window.location.href;
      try {
        if (navigator.clipboard && navigator.clipboard.writeText) {
          await navigator.clipboard.writeText(urlToCopy);
        } else {
          const input = document.createElement('input');
          input.value = urlToCopy;
          document.body.appendChild(input);
          input.select();
          document.execCommand('copy');
          document.body.removeChild(input);
        }
        const origText = btnCopyBarShare.innerHTML;
        btnCopyBarShare.innerHTML = '✅ Link Copied!';
        setTimeout(() => {
          btnCopyBarShare.innerHTML = origText;
        }, 2000);
      } catch (err) {
        console.error('Failed to copy share link:', err);
      }
    });
  }

  // Slack share button in floating share bar (Copies link and opens Slack in a new tab)
  const btnShareSlackBar = document.getElementById('btn-share-slack-bar');
  if (btnShareSlackBar) {
    btnShareSlackBar.addEventListener('click', async () => {
      const urlToCopy = btnShareSlackBar.getAttribute('data-url') || window.location.href;
      try {
        if (navigator.clipboard && navigator.clipboard.writeText) {
          await navigator.clipboard.writeText(urlToCopy);
        } else {
          const input = document.createElement('input');
          input.value = urlToCopy;
          document.body.appendChild(input);
          input.select();
          document.execCommand('copy');
          document.body.removeChild(input);
        }
      } catch (err) {
        console.error('Failed to copy share link for Slack:', err);
      }

      const origText = btnShareSlackBar.innerHTML;
      btnShareSlackBar.innerHTML = '✅ Copied for Slack!';
      setTimeout(() => {
        btnShareSlackBar.innerHTML = origText;
      }, 2000);

      window.open('https://slack.com/app_redirect', '_blank', 'noopener,noreferrer');
    });
  }

});
