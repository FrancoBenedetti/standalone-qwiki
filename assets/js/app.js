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
        if (text.includes(term)) {
          link.style.display = 'flex';
        } else {
          link.style.display = 'none';
        }
      });
    });
  }

  // Admin Modal logic
  const loginBtn = document.getElementById('btn-login');
  const logoutBtn = document.getElementById('btn-logout');
  const loginModal = document.getElementById('login-modal');
  const loginClose = document.getElementById('login-modal-close');
  const loginForm = document.getElementById('login-form');

  if (loginBtn) {
    loginBtn.addEventListener('click', () => loginModal.classList.add('open'));
  }
  if (loginClose) {
    loginClose.addEventListener('click', () => loginModal.classList.remove('open'));
  }

  if (loginForm) {
    loginForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      const formData = new FormData(loginForm);
      formData.append('action', 'login');

      try {
        const res = await fetch('api/admin.php', { method: 'POST', body: formData });
        const data = await res.json();
        if (data.success) {
          window.location.reload();
        } else {
          alert('Login failed: ' + (data.error || 'Unknown error'));
        }
      } catch (err) {
        alert('Login request failed');
      }
    });
  }

  if (logoutBtn) {
    logoutBtn.addEventListener('click', async () => {
      await fetch('api/admin.php?action=logout');
      window.location.reload();
    });
  }

  // Markdown Editor Logic
  const editMarkdownBtn = document.getElementById('btn-edit-markdown');
  const editorModal = document.getElementById('editor-modal');
  const editorClose = document.getElementById('editor-modal-close');
  const saveMarkdownBtn = document.getElementById('btn-save-markdown');
  const markdownTextarea = document.getElementById('markdown-editor-textarea');

  if (editMarkdownBtn && editorModal) {
    editMarkdownBtn.addEventListener('click', () => {
      editorModal.classList.add('open');
    });
  }

  if (editorClose && editorModal) {
    editorClose.addEventListener('click', () => {
      editorModal.classList.remove('open');
    });
  }

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

  // Upload Document Modal
  const uploadBtn = document.getElementById('btn-upload-doc');
  const uploadModal = document.getElementById('upload-modal');
  const uploadClose = document.getElementById('upload-modal-close');
  const uploadForm = document.getElementById('upload-form');

  if (uploadBtn && uploadModal) {
    uploadBtn.addEventListener('click', () => uploadModal.classList.add('open'));
  }
  if (uploadClose && uploadModal) {
    uploadClose.addEventListener('click', () => uploadModal.classList.remove('open'));
  }

  if (uploadForm) {
    uploadForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      const formData = new FormData(uploadForm);
      formData.append('action', 'upload_file');

      try {
        const res = await fetch('api/admin.php', { method: 'POST', body: formData });
        const data = await res.json();
        if (data.success) {
          window.location.reload();
        } else {
          alert('Upload failed: ' + (data.error || 'Unknown error'));
        }
      } catch (err) {
        alert('Upload request failed');
      }
    });
  }
});
