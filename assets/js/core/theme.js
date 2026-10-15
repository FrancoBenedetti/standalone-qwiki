/**
 * Standalone Qwiki - Core Theme Controller
 * Handles light/dark mode toggling, cookie & localStorage persistence,
 * and synchronized re-rendering of diagram/editor instances.
 */
window.QwikiApp = window.QwikiApp || {};

window.QwikiApp.theme = (function() {
  'use strict';

  function setTheme(newTheme) {
    document.documentElement.setAttribute('data-theme', newTheme);
    localStorage.setItem('qwiki_theme', newTheme);
    document.cookie = 'qwiki_theme=' + encodeURIComponent(newTheme) + '; path=/; max-age=31536000; SameSite=Lax';

    if (typeof window.syncTuiEditorTheme === 'function') {
      window.syncTuiEditorTheme(newTheme);
    }
    if (typeof window.reRenderMermaidDiagrams === 'function') {
      window.reRenderMermaidDiagrams(newTheme);
    }
  }

  function getTheme() {
    return document.documentElement.getAttribute('data-theme') || localStorage.getItem('qwiki_theme') || 'dark';
  }

  function toggle() {
    const currentTheme = getTheme();
    const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
    setTheme(newTheme);
  }

  function init() {
    const themeToggleBtn = document.getElementById('theme-toggle');
    if (themeToggleBtn) {
      themeToggleBtn.addEventListener('click', toggle);
    }
  }

  return {
    init,
    setTheme,
    getTheme,
    toggle
  };
})();
