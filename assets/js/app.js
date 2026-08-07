document.addEventListener('DOMContentLoaded', () => {
  // Theme Switcher
  const themeToggleBtn = document.getElementById('theme-toggle');
  const savedTheme = localStorage.getItem('qwiki_theme') || 'dark';
  document.documentElement.setAttribute('data-theme', savedTheme);

  if (themeToggleBtn) {
    themeToggleBtn.addEventListener('click', () => {
      const currentTheme = document.documentElement.getAttribute('data-theme');
      const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
      document.documentElement.setAttribute('data-theme', newTheme);
      localStorage.setItem('qwiki_theme', newTheme);
      
      if (typeof window.syncTuiEditorTheme === 'function') {
        window.syncTuiEditorTheme(newTheme);
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

  // Category Accordion Toggle
  document.querySelectorAll('.nav-category-header').forEach(header => {
    header.addEventListener('click', (e) => {
      if (e.target.closest('.btn-edit-cat-icon') || e.target.closest('.drag-handle')) return;
      const catItem = header.closest('.nav-category-item');
      if (catItem) {
        catItem.classList.toggle('collapsed');
      }
    });
  });

  // Sidebar Filter Search (with Auto-Expand)
  const searchInput = document.getElementById('search-input');
  if (searchInput) {
    let searchTimeout = null;
    let abortController = null;
    
    searchInput.addEventListener('input', (e) => {
      const term = e.target.value.toLowerCase().trim();
      clearTimeout(searchTimeout);
      if (abortController) abortController.abort();

      if (term === '') {
        document.querySelectorAll('.nav-category-item').forEach(catItem => {
          catItem.querySelectorAll('.nav-link').forEach(link => {
            link.style.display = 'flex';
          });
        });
        return;
      }

      searchTimeout = setTimeout(async () => {
        abortController = new AbortController();
        try {
          const res = await fetch(`api/search.php?q=${encodeURIComponent(term)}`, { signal: abortController.signal });
          const data = await res.json();
          if (data.success) {
            const matchedSlugs = data.results;
            
            document.querySelectorAll('.nav-category-item').forEach(catItem => {
              let catMatch = false;
              catItem.querySelectorAll('.nav-link').forEach(link => {
                const url = new URL(link.href, window.location.origin);
                const chapterSlug = url.searchParams.get('chapter') || url.searchParams.get('doc');
                const text = link.textContent.toLowerCase();
                
                if (matchedSlugs.includes(chapterSlug) || text.includes(term)) {
                  link.style.display = 'flex';
                  catMatch = true;
                } else {
                  link.style.display = 'none';
                }
              });
              
              if (catMatch) {
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

  // Trigger buttons
  const triggers = [
    { btnId: 'btn-login', modalId: 'login-modal' },
    { btnId: 'btn-add-book', modalId: 'book-modal' },
    { btnId: 'btn-add-chapter', modalId: 'chapter-modal' },
    { btnId: 'btn-users', modalId: 'users-modal' },
    { btnId: 'btn-settings', modalId: 'settings-modal' },
    { btnId: 'btn-edit-chapter-meta', modalId: 'edit-chapter-modal' }
  ];

  triggers.forEach(({ btnId, modalId }) => {
    const btn = document.getElementById(btnId);
    const modal = document.getElementById(modalId);
    if (btn && modal) {
      btn.addEventListener('click', () => {
        modal.classList.add('open');
        if (modalId === 'users-modal') {
          loadUsersList();
        }
        if (modalId === 'settings-modal') {
          const btnSettings = document.getElementById('btn-settings');
          if (btnSettings) populateThemes(document.getElementById('setting-site-theme'), btnSettings.getAttribute('data-theme'));
        }
        if (modalId === 'edit-chapter-modal') {
          const btnMeta = document.getElementById('btn-edit-chapter-meta');
          if (btnMeta) populateThemes(document.getElementById('edit-chapter-theme'), btnMeta.getAttribute('data-theme'));
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
        html += '<tr><th style="padding:0.5rem;">Username</th><th style="padding:0.5rem;">Role</th><th style="padding:0.5rem; text-align:right;">Actions</th></tr>';
        html += '</thead><tbody>';

        data.users.forEach(u => {
          const isPrimaryAdmin = (u.username.toLowerCase() === 'admin');
          const badgeClass = (u.role === 'admin') ? 'badge-md' : 'badge-pdf';
          
          html += `<tr style="border-bottom:1px solid var(--border-color);">`;
          html += `<td style="padding:0.6rem; font-weight:600; color:var(--text-primary);">${escapeHtml(u.username)}</td>`;
          html += `<td style="padding:0.6rem;"><span class="doc-badge ${badgeClass}">${escapeHtml(u.role)}</span></td>`;
          html += `<td style="padding:0.6rem; text-align:right;">`;
          if (!isPrimaryAdmin) {
            html += `<button class="btn btn-outline btn-sm btn-delete-user" data-username="${escapeHtml(u.username)}" style="padding:0.2rem 0.5rem; color:#f87171;">Delete</button>`;
          } else {
            html += `<span style="font-size:0.8rem; color:var(--text-muted);">System Admin</span>`;
          }
          html += `</td></tr>`;
        });

        html += '</tbody></table>';
        container.innerHTML = html;

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

  // Category Edit Pencil Icons in Sidebar
  document.querySelectorAll('.btn-edit-cat-icon').forEach(btn => {
    btn.addEventListener('click', (e) => {
      e.stopPropagation();
      const bookId = btn.getAttribute('data-book-id');
      const bookTitle = btn.getAttribute('data-book-title');
      const bookTheme = btn.getAttribute('data-book-theme');
      const bookVisibility = btn.getAttribute('data-book-visibility');

      const editBookIdInput = document.getElementById('edit-book-id-hidden');
      const editBookTitleInput = document.getElementById('edit-book-title-input');
      const editBookThemeInput = document.getElementById('edit-book-theme-input');
      const editBookVisInput = document.getElementById('edit-book-visibility-input');
      const editBookModal = document.getElementById('edit-book-modal');

      if (editBookIdInput && editBookTitleInput && editBookModal) {
        editBookIdInput.value = bookId;
        editBookTitleInput.value = bookTitle;
        if (editBookVisInput) editBookVisInput.value = bookVisibility || 'public';
        populateThemes(editBookThemeInput, bookTheme);
        editBookModal.classList.add('open');
      }
    });
  });

  // Delete Category
  const deleteBookBtn = document.getElementById('btn-delete-book');
  if (deleteBookBtn) {
    deleteBookBtn.addEventListener('click', async () => {
      const bookIdInput = document.getElementById('edit-book-id-hidden');
      const bookTitleInput = document.getElementById('edit-book-title-input');
      const bookId = bookIdInput ? bookIdInput.value : '';
      const bookTitle = bookTitleInput ? bookTitleInput.value : 'this category';

      if (!bookId) return;

      if (!confirm(`Are you sure you want to delete the category "${bookTitle}" and all its sub-folders from the wiki structure?`)) {
        return;
      }

      const formData = new FormData();
      formData.append('action', 'delete_book');
      formData.append('bookId', bookId);

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

      try {
        const res = await fetch('api/admin.php', { method: 'POST', body: formData });
        const data = await res.json();

        if (data.success) {
          if (customCallback) {
            customCallback(data);
          } else if (successRedirect) {
            if (data.bookId && data.slug) {
              window.location.href = `index.php?book=${encodeURIComponent(data.bookId)}&chapter=${encodeURIComponent(data.slug)}`;
            } else {
              window.location.reload();
            }
          }
        } else {
          alert('Operation failed: ' + (data.error || 'Unknown error'));
        }
      } catch (err) {
        alert('Server request failed');
      }
    });
  }

  // Bind Form Submissions
  submitAdminForm('login-form', 'login');
  submitAdminForm('add-book-form', 'add_book');
  submitAdminForm('edit-book-form', 'edit_book');
  submitAdminForm('tab-create-md', 'create_markdown');
  submitAdminForm('tab-upload', 'upload_file');
  submitAdminForm('tab-gdoc', 'add_gdoc');
  submitAdminForm('edit-chapter-form', 'edit_chapter');
  submitAdminForm('settings-form', 'update_settings');

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

  // ----------------------------------------------------
  // Inline Markdown Editor (Toast UI)
  // ----------------------------------------------------
  const btnEditMarkdown = document.getElementById('btn-edit-markdown');
  const btnCancelEdit = document.getElementById('btn-cancel-edit');
  const btnSaveInline = document.getElementById('btn-save-inline-markdown');
  const readActions = document.getElementById('read-actions');
  const editActions = document.getElementById('edit-actions');
  const contentBody = document.getElementById('content-body');
  const editorContainer = document.getElementById('inline-editor-container');
  const rawMarkdownData = document.getElementById('raw-markdown-data');
  let tuiEditor = null;

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
    btnEditMarkdown.addEventListener('click', () => {
      readActions.style.display = 'none';
      contentBody.style.display = 'none';
      editActions.style.display = 'flex';
      editorContainer.style.display = 'block';

      if (!tuiEditor) {
        const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
        
        tuiEditor = new toastui.Editor({
          el: editorContainer,
          initialValue: rawMarkdownData.value,
          initialEditType: 'wysiwyg',
          previewStyle: 'vertical',
          height: '600px',
          theme: isDark ? 'dark' : '',
          usageStatistics: false,
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
        });
      }
    });
  }

  if (btnCancelEdit) {
    btnCancelEdit.addEventListener('click', () => {
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
      formData.append('content', content);

      try {
        const res = await fetch('api/admin.php', { method: 'POST', body: formData });
        const data = await res.json();
        if (data.success) {
          window.location.reload();
        } else {
          alert('Save failed: ' + (data.error || 'Unknown error'));
        }
      } catch (err) {
        alert('Save request failed');
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
          window.location.href = `index.php?book=${encodeURIComponent(bookId)}`;
        } else {
          alert('Delete failed: ' + (data.error || 'Unknown error'));
        }
      } catch (err) {
        alert('Delete request failed');
      }
    });
  }

  // ----------------------------------------------------
  // HTML5 Drag and Drop Engine for Menu Reordering
  // ----------------------------------------------------
  let draggedElement = null;

  function clearDragHighlights() {
    document.querySelectorAll('.drag-over-above, .drag-over-below, .drag-over-inside').forEach(el => {
      el.classList.remove('drag-over-above', 'drag-over-below', 'drag-over-inside');
    });
  }

  // Extract tree array recursively from DOM structure
  function extractCategoryNodeFromDOM(catEl) {
    const nodeId = catEl.getAttribute('data-node-id');
    const nodeTitle = catEl.getAttribute('data-node-title');
    const docList = catEl.querySelector(':scope > .nav-document-list');

    const items = [];

    if (docList) {
      Array.from(docList.children).forEach(child => {
        const dragType = child.getAttribute('data-drag-type');
        if (dragType === 'document') {
          items.push({
            title: child.getAttribute('data-doc-title'),
            slug: child.getAttribute('data-doc-slug'),
            type: child.getAttribute('data-doc-type'),
            url: child.getAttribute('data-doc-url') || '',
            editUrl: child.getAttribute('data-doc-editurl') || '',
            file: child.getAttribute('data-doc-file') || ''
          });
        } else if (dragType === 'category') {
          items.push(extractCategoryNodeFromDOM(child));
        }
      });
    }

    const result = { id: nodeId, title: nodeTitle, type: 'folder' };
    if (items.length > 0) result.items = items;
    return result;
  }

  async function saveTreeStructureToBackend() {
    const tree = [];
    document.querySelectorAll('.sidebar-nav > .nav-category-item[data-drag-type="category"]').forEach(topCatEl => {
      tree.push(extractCategoryNodeFromDOM(topCatEl));
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
      const targetType = el.getAttribute('data-drag-type');

      // Drag document or category onto a Category -> Drop inside
      if (targetType === 'category') {
        if (offsetY > height * 0.25 && offsetY < height * 0.75) {
          el.classList.add('drag-over-inside');
          return;
        }
      }

      const isTopLevelCategory = el.parentNode && el.parentNode.classList.contains('sidebar-nav');
      if (isTopLevelCategory && draggedType === 'document') {
        el.classList.add('drag-over-inside');
        return;
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

      if (isInside && el.getAttribute('data-drag-type') === 'category') {
        const targetDocList = el.querySelector(':scope > .nav-document-list');
        if (targetDocList) {
          targetDocList.appendChild(draggedElement);
          el.classList.remove('collapsed');
        }
      } else if (isAbove) {
        el.parentNode.insertBefore(draggedElement, el);
      } else if (isBelow) {
        el.parentNode.insertBefore(draggedElement, el.nextSibling);
      }

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
      formData.append('content', content);

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
            let notesHtml = data.notes.replace(/\n/g, '<br>').replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>').replace(/\*(.*?)\*/g, '<em>$1</em>').replace(/```[\s\S]*?```/g, '<pre><code>...</code></pre>');
            document.getElementById('update-release-notes').innerHTML = notesHtml;
            document.getElementById('update-zip-url').value = data.zip_url;
            document.getElementById('update-modal').classList.add('active');
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
});
