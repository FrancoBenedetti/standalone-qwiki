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

  // Sidebar Filter Search
  const searchInput = document.getElementById('search-input');
  if (searchInput) {
    searchInput.addEventListener('input', (e) => {
      const term = e.target.value.toLowerCase();
      document.querySelectorAll('.nav-link').forEach(link => {
        const text = link.textContent.toLowerCase();
        link.style.display = text.includes(term) ? 'flex' : 'none';
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

  // Modal Open Trigger buttons
  const triggers = [
    { btnId: 'btn-login', modalId: 'login-modal' },
    { btnId: 'btn-add-book', modalId: 'book-modal' },
    { btnId: 'btn-add-chapter', modalId: 'chapter-modal' },
    { btnId: 'btn-settings', modalId: 'settings-modal' },
    { btnId: 'btn-upload-doc', modalId: 'chapter-modal' },
    { btnId: 'btn-edit-markdown', modalId: 'editor-modal' }
  ];

  triggers.forEach(({ btnId, modalId }) => {
    const btn = document.getElementById(btnId);
    const modal = document.getElementById(modalId);
    if (btn && modal) {
      btn.addEventListener('click', () => modal.classList.add('open'));
    }
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
  submitAdminForm('tab-create-md', 'create_markdown');
  submitAdminForm('tab-upload', 'upload_file');
  submitAdminForm('tab-gdoc', 'add_gdoc');
  submitAdminForm('settings-form', 'update_settings');

  // Admin Logout
  const logoutBtn = document.getElementById('btn-logout');
  if (logoutBtn) {
    logoutBtn.addEventListener('click', async () => {
      await fetch('api/admin.php?action=logout');
      window.location.reload();
    });
  }

  // Save Markdown Editor
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
      if (!confirm('Are you sure you want to delete this chapter from the wiki structure?')) {
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
});
