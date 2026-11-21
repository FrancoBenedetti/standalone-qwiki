/**
 * Standalone Qwiki - UI Language Switcher Extension
 * Persists user language choice in both localStorage and a cookie so PHP SSR
 * renders immediately in the chosen language with zero FOUC.
 */
(function () {
  'use strict';

  const STORAGE_KEY = 'qwiki_lang';
  const COOKIE_MAX_AGE = 31536000; // 1 year

  function persistLanguage(langCode) {
    try {
      localStorage.setItem(STORAGE_KEY, langCode);
    } catch (e) {}
    document.cookie = STORAGE_KEY + '=' + encodeURIComponent(langCode) + '; path=/; max-age=' + COOKIE_MAX_AGE + '; SameSite=Lax';
  }

  window.QwikiApp = window.QwikiApp || {};
  window.QwikiApp.language = {
    persistLanguage: persistLanguage,
    reloadPage: function () {
      window.location.reload();
    }
  };

  function initLanguageSwitcher() {
    const cfg = window.QWIKI_I18N_CONFIG || {};
    if (!cfg.enabled) return;

    const available = Array.isArray(cfg.available) ? cfg.available : ['en'];
    const selects = document.querySelectorAll('.qwiki-lang-select');
    if (!selects.length) return;

    selects.forEach(selectEl => {
      if (cfg.lang && available.includes(cfg.lang)) {
        selectEl.value = cfg.lang;
      }

      selectEl.addEventListener('change', (e) => {
        const chosen = e.target.value;
        if (!chosen || !available.includes(chosen)) return;

        // Sync any other switcher instances on the page
        selects.forEach(other => {
          if (other !== e.target) other.value = chosen;
        });

        persistLanguage(chosen);
        try {
          sessionStorage.removeItem('qwiki_lang_sync');
        } catch (err) {}
        window.QwikiApp.language.reloadPage();
      });
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initLanguageSwitcher);
  } else {
    initLanguageSwitcher();
  }
})();
