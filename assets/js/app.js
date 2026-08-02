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
    });
  }

  // Mobile Sidebar Toggle
  const mobileToggle = document.getElementById('mobile-toggle');
  const sidebar = document.getElementById('app-sidebar');

  if (mobileToggle && sidebar) {
    mobileToggle.addEventListener('click', () => {
      sidebar.classList.toggle('open');
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
    searchInput.addEventListener('input', (e) => {
      const term = e.target.value.toLowerCase().trim();
      document.querySelectorAll('.nav-category-item').forEach(catItem => {
        let catMatch = false;
        catItem.querySelectorAll('.nav-link').forEach(link => {
          const text = link.textContent.toLowerCase();
          if (term === '' || text.includes(term)) {
            link.style.display = 'flex';
            catMatch = true;
          } else {
            link.style.display = 'none';
          }
        });
        if (term !== '') {
          if (catMatch) {
            catItem.classList.remove('collapsed');
          } else {
            catItem.classList.add('collapsed');
          }
        }
      });
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
    { btnId: 'btn-settings', modalId: 'settings-modal' },
    { btnId: 'btn-edit-markdown', modalId: 'editor-modal' },
    { btnId: 'btn-edit-chapter-meta', modalId: 'edit-chapter-modal' }
  ];

  triggers.forEach(({ btnId, modalId }) => {
    const btn = document.getElementById(btnId);
    const modal = document.getElementById(modalId);
    if (btn && modal) {
      btn.addEventListener('click', () => modal.classList.add('open'));
    }
  });

  // Category Edit Pencil Icons in Sidebar
  document.querySelectorAll('.btn-edit-cat-icon').forEach(btn => {
    btn.addEventListener('click', (e) => {
      e.stopPropagation();
      const bookId = btn.getAttribute('data-book-id');
      const bookTitle = btn.getAttribute('data-book-title');

      const editBookIdInput = document.getElementById('edit-book-id-hidden');
      const editBookTitleInput = document.getElementById('edit-book-title-input');
      const editBookModal = document.getElementById('edit-book-modal');

      if (editBookIdInput && editBookTitleInput && editBookModal) {
        editBookIdInput.value = bookId;
        editBookTitleInput.value = bookTitle;
        editBookModal.classList.add('open');
      }
    });
  });

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
  async function submitAdminForm(formId, actionName, successRedirect = true) {
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
          if (successRedirect) {
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

  // Admin Logout
  const logoutBtn = document.getElementById('btn-logout');
  if (logoutBtn) {
    logoutBtn.addEventListener('click', async () => {
      await fetch('api/admin.php?action=logout');
      window.location.reload();
    });
  }

  // Save Markdown Editor Content
  const saveMarkdownBtn = document.getElementById('btn-save-markdown');
  const markdownTextarea = document.getElementById('markdown-editor-textarea');

  if (saveMarkdownBtn && markdownTextarea) {
    saveMarkdownBtn.addEventListener('click', async () => {
      const file = saveMarkdownBtn.getAttribute('data-file');
      const content = markdownTextarea.value;
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
        const res = await fetch('api/admin.php', { method: 'POST', body: formData });
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

    const chapters = [];
    const subfolders = [];

    if (docList) {
      // Extract immediate chapters
      docList.querySelectorAll(':scope > .nav-link[data-drag-type="document"]').forEach(docEl => {
        chapters.push({
          title: docEl.getAttribute('data-doc-title'),
          slug: docEl.getAttribute('data-doc-slug'),
          type: docEl.getAttribute('data-doc-type'),
          url: docEl.getAttribute('data-doc-url') || '',
          editUrl: docEl.getAttribute('data-doc-editurl') || '',
          file: docEl.getAttribute('data-doc-file') || ''
        });
      });

      // Extract immediate subfolders
      docList.querySelectorAll(':scope > .nav-category-item[data-drag-type="category"]').forEach(subCatEl => {
        subfolders.push(extractCategoryNodeFromDOM(subCatEl));
      });
    }

    const result = { id: nodeId, title: nodeTitle };
    if (chapters.length > 0) result.chapters = chapters;
    if (subfolders.length > 0) result.subfolders = subfolders;
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
        body: JSON.stringify({ tree })
      });
      const data = await res.json();
      if (!data.success) {
        alert('Failed to save reordered menu structure: ' + (data.error || 'Unknown error'));
      }
    } catch (err) {
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

      clearDragHighlights();
      const rect = el.getBoundingClientRect();
      const offsetY = e.clientY - rect.top;
      const height = rect.height;

      const draggedType = draggedElement.getAttribute('data-drag-type');
      const targetType = el.getAttribute('data-drag-type');

      // Drag document onto a Category -> Drop inside
      if (draggedType === 'document' && targetType === 'category') {
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
});
