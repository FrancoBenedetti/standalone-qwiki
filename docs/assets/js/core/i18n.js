/**
 * Standalone Qwiki - Lightweight UI Translation Helper
 * Consumes only the active locale's JS dictionary injected by PHP.
 */
window.QwikiApp = window.QwikiApp || {};

window.QwikiApp.i18n = (function () {
  'use strict';

  const cfg = window.QWIKI_I18N_CONFIG || {};
  const enabled = !!cfg.enabled;
  const activeLang = (enabled && typeof cfg.lang === 'string' && cfg.lang) ? cfg.lang : 'en';
  const dict = (enabled && cfg.strings && typeof cfg.strings === 'object') ? cfg.strings : {};

  function t(key, fallback, vars) {
    let text = Object.prototype.hasOwnProperty.call(dict, key) ? dict[key] : fallback;
    if (text === undefined || text === null) {
      text = String(key);
    } else {
      text = String(text);
    }
    if (vars && typeof vars === 'object') {
      Object.keys(vars).forEach(function (k) {
        text = text.split('{' + k + '}').join(String(vars[k]));
      });
    }
    return text;
  }

  function getLang() {
    return activeLang;
  }

  function getToastUiLocale() {
    return (enabled && typeof cfg.tuiLocale === 'string' && cfg.tuiLocale) ? cfg.tuiLocale : 'en-US';
  }

  return {
    t: t,
    getLang: getLang,
    getToastUiLocale: getToastUiLocale
  };
})();

window.__t = window.QwikiApp.i18n.t;
