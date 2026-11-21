/**
 * Test Suite: Client-side i18n helper & Language Switcher browser persistence
 */
const fs = require('fs');
const path = require('path');
const assert = require('assert');
const { JSDOM } = require('jsdom');

console.log('=== Running Client-Side i18n & Storage Persistence Tests ===\n');

const i18nScript = fs.readFileSync(path.join(__dirname, '../assets/js/core/i18n.js'), 'utf8');
const extScript = fs.readFileSync(path.join(__dirname, '../assets/extensions/tool-language/script.js'), 'utf8');

// 1. Test i18n helper in Afrikaans mode
{
  const dom = new JSDOM('<!DOCTYPE html><html><body></body></html>', {
    url: 'http://localhost/',
    runScripts: 'outside-only'
  });

  dom.window.QWIKI_I18N_CONFIG = {
    enabled: true,
    lang: 'af',
    tuiLocale: 'af',
    available: ['en', 'af', 'it'],
    strings: {
      'js.log_in': 'Meld Aan',
      'js.trash_count': '{count} geskrapte item(s).'
    }
  };

  dom.window.eval(i18nScript);

  assert.strictEqual(dom.window.QwikiApp.i18n.getLang(), 'af');
  assert.strictEqual(dom.window.QwikiApp.i18n.getToastUiLocale(), 'af');
  assert.strictEqual(dom.window.__t('js.log_in', 'Log In'), 'Meld Aan');
  assert.strictEqual(dom.window.__t('js.trash_count', '{count} trashed item(s).', { count: 3 }), '3 geskrapte item(s).');
  assert.strictEqual(dom.window.__t('js.missing_key', 'Default English'), 'Default English');
  console.log('  [PASS] assets/js/core/i18n.js resolves active strings, vars, and English fallback');
}

// 2. Test i18n helper when extension is disabled
{
  const dom = new JSDOM('<!DOCTYPE html><html><body></body></html>', {
    url: 'http://localhost/',
    runScripts: 'outside-only'
  });

  dom.window.QWIKI_I18N_CONFIG = {
    enabled: false,
    lang: 'af',
    tuiLocale: 'af',
    available: ['en', 'af', 'it'],
    strings: { 'js.log_in': 'Meld Aan' }
  };

  dom.window.eval(i18nScript);

  assert.strictEqual(dom.window.QwikiApp.i18n.getLang(), 'en');
  assert.strictEqual(dom.window.__t('js.log_in', 'Log In'), 'Log In');
  console.log('  [PASS] assets/js/core/i18n.js enforces English fallback when extension is disabled');
}

// 3. Test language switcher browser storage persistence & multi-select sync
{
  const dom = new JSDOM(`<!DOCTYPE html><html><body>
    <select id="qwiki-lang-select-header" class="qwiki-lang-select">
      <option value="en" selected>English</option>
      <option value="af">Afrikaans</option>
      <option value="it">Italiano</option>
    </select>
    <select id="qwiki-lang-select-auth" class="qwiki-lang-select">
      <option value="en" selected>English</option>
      <option value="af">Afrikaans</option>
      <option value="it">Italiano</option>
    </select>
  </body></html>`, {
    url: 'http://localhost/',
    runScripts: 'outside-only'
  });

  dom.window.QWIKI_I18N_CONFIG = {
    enabled: true,
    lang: 'en',
    tuiLocale: 'en-US',
    available: ['en', 'af', 'it'],
    strings: {}
  };

  let reloaded = false;
  dom.window.eval(extScript);
  dom.window.QwikiApp.language.reloadPage = () => { reloaded = true; };
  dom.window.document.dispatchEvent(new dom.window.Event('DOMContentLoaded'));

  const headerSel = dom.window.document.getElementById('qwiki-lang-select-header');
  const authSel = dom.window.document.getElementById('qwiki-lang-select-auth');

  headerSel.value = 'it';
  headerSel.dispatchEvent(new dom.window.Event('change', { bubbles: true }));

  assert.strictEqual(dom.window.localStorage.getItem('qwiki_lang'), 'it', 'localStorage persists selected language');
  assert.ok(dom.window.document.cookie.includes('qwiki_lang=it'), 'cookie persists selected language');
  assert.strictEqual(authSel.value, 'it', 'secondary selector stays in sync');
  assert.strictEqual(reloaded, true, 'page reloads to apply SSR translations cleanly');
  console.log('  [PASS] tool-language/script.js persists selection in localStorage & cookie and triggers clean reload');
}

console.log('\nAll client-side i18n tests passed! 🎉');
