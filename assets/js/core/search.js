/**
 * Standalone Qwiki - Core Search Controller
 * Handles debounced sidebar quick search, asynchronous querying against api/search.php,
 * dynamic document filtering, category auto-expansion with state restoration,
 * and cross-navigation in-document search term highlighting.
 */
window.QwikiApp = window.QwikiApp || {};

window.QwikiApp.search = (function() {
  'use strict';

  const _t = (key, fallback, vars) => (typeof window !== 'undefined' && window.__t ? window.__t(key, fallback, vars) : fallback);

  const HIGHLIGHT_STORAGE_KEY = 'qwiki_search_highlight';
  const HIGHLIGHT_TTL_MS = 30 * 60 * 1000;
  const MAX_HIGHLIGHT_MATCHES = 500;
  const SKIP_ANCESTOR_SELECTOR = [
    'script', 'style', 'noscript', 'template', 'textarea', 'input', 'select',
    'option', 'button', 'svg', 'canvas', '.katex', '.katex-inline',
    '.katex-display-block', '.katex-display-inline', '.mermaid',
    '.mermaid-diagram-container', '.svgbob-diagram-container',
    '.language-mermaid', '.search-highlight-bar', '[aria-hidden="true"]', '[hidden]'
  ].join(', ');

  let searchTimeout = null;
  let abortController = null;
  let preSearchCollapsedState = null;

  let activeMatches = [];
  let activeIndex = -1;
  let activeTerm = '';
  let matchesCapped = false;
  let usingCustomHighlights = false;
  let fallbackMarks = [];
  let highlightBarEl = null;

  function supportsCustomHighlightApi() {
    return typeof CSS !== 'undefined' && !!CSS.highlights && typeof Highlight !== 'undefined';
  }

  function getCurrentTargetPath() {
    return window.location.pathname + window.location.search;
  }

  function saveSearchState(term, targetPath) {
    try {
      window.sessionStorage.setItem(HIGHLIGHT_STORAGE_KEY, JSON.stringify({
        term,
        targetPath,
        ts: Date.now()
      }));
    } catch (_) {
      // Ignore storage failures in restricted contexts
    }
  }

  function clearSearchState() {
    try {
      window.sessionStorage.removeItem(HIGHLIGHT_STORAGE_KEY);
    } catch (_) {
      // Ignore storage failures
    }
  }

  function loadSearchStateForCurrentPage() {
    try {
      const raw = window.sessionStorage.getItem(HIGHLIGHT_STORAGE_KEY);
      if (!raw) return null;
      const parsed = JSON.parse(raw);
      if (!parsed || typeof parsed.term !== 'string' || !parsed.term.trim()) {
        clearSearchState();
        return null;
      }
      if (typeof parsed.ts !== 'number' || (Date.now() - parsed.ts) > HIGHLIGHT_TTL_MS) {
        clearSearchState();
        return null;
      }
      if (parsed.targetPath !== getCurrentTargetPath()) {
        clearSearchState();
        return null;
      }
      return parsed.term.trim();
    } catch (_) {
      clearSearchState();
      return null;
    }
  }

  function shouldSkipTextNode(node) {
    if (!node || !node.nodeValue) return true;
    const parent = node.parentElement;
    if (!parent) return true;
    if (parent.closest(SKIP_ANCESTOR_SELECTOR)) return true;
    if (parent.closest('[style*="display: none"], [style*="display:none"]')) return true;
    return false;
  }

  function collectTextNodes(root) {
    const walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT, {
      acceptNode(node) {
        return shouldSkipTextNode(node) ? NodeFilter.FILTER_REJECT : NodeFilter.FILTER_ACCEPT;
      }
    });
    const nodes = [];
    let current = walker.nextNode();
    while (current) {
      nodes.push(current);
      current = walker.nextNode();
    }
    return nodes;
  }

  function removeHighlightBar() {
    if (highlightBarEl && highlightBarEl.parentNode) {
      highlightBarEl.parentNode.removeChild(highlightBarEl);
    }
    highlightBarEl = null;
  }

  function clearHighlights() {
    if (supportsCustomHighlightApi()) {
      CSS.highlights.delete('qwiki-search');
      CSS.highlights.delete('qwiki-search-active');
    }

    if (fallbackMarks.length > 0) {
      const parentsToNormalize = new Set();
      fallbackMarks.forEach(mark => {
        const parent = mark.parentNode;
        if (!parent) return;
        parentsToNormalize.add(parent);
        parent.replaceChild(document.createTextNode(mark.textContent || ''), mark);
      });
      parentsToNormalize.forEach(parent => {
        if (typeof parent.normalize === 'function') {
          parent.normalize();
        }
      });
      fallbackMarks = [];
    }

    activeMatches = [];
    activeIndex = -1;
    activeTerm = '';
    matchesCapped = false;
    usingCustomHighlights = false;
    removeHighlightBar();
  }

  function updateHighlightBar() {
    if (activeMatches.length === 0) {
      removeHighlightBar();
      return;
    }

    if (!highlightBarEl) {
      highlightBarEl = document.createElement('div');
      highlightBarEl.className = 'search-highlight-bar';
      highlightBarEl.setAttribute('role', 'region');
      highlightBarEl.setAttribute('aria-label', _t('search.matches_aria', 'In-page search matches'));

      const countSpan = document.createElement('span');
      countSpan.className = 'search-highlight-count';
      countSpan.setAttribute('aria-live', 'polite');

      const prevBtn = document.createElement('button');
      prevBtn.type = 'button';
      prevBtn.className = 'search-highlight-btn';
      prevBtn.setAttribute('aria-label', _t('search.prev_match', 'Previous match'));
      prevBtn.title = _t('search.prev_match_title', 'Previous match (Shift+Enter)');
      prevBtn.innerHTML = '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="18 15 12 9 6 15"></polyline></svg>';
      prevBtn.addEventListener('click', () => stepMatch(-1));

      const nextBtn = document.createElement('button');
      nextBtn.type = 'button';
      nextBtn.className = 'search-highlight-btn';
      nextBtn.setAttribute('aria-label', _t('search.next_match', 'Next match'));
      nextBtn.title = _t('search.next_match_title', 'Next match (Enter)');
      nextBtn.innerHTML = '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="6 9 12 15 18 9"></polyline></svg>';
      nextBtn.addEventListener('click', () => stepMatch(1));

      const closeBtn = document.createElement('button');
      closeBtn.type = 'button';
      closeBtn.className = 'search-highlight-btn';
      closeBtn.setAttribute('aria-label', _t('search.dismiss', 'Dismiss highlights'));
      closeBtn.title = _t('search.dismiss', 'Dismiss highlights');
      closeBtn.innerHTML = '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>';
      closeBtn.addEventListener('click', () => clearHighlights());

      highlightBarEl.append(countSpan, prevBtn, nextBtn, closeBtn);
      document.body.appendChild(highlightBarEl);
    }

    const countEl = highlightBarEl.querySelector('.search-highlight-count');
    if (countEl) {
      const totalLabel = matchesCapped ? `${activeMatches.length}+` : `${activeMatches.length}`;
      countEl.textContent = `"${activeTerm}" • ${activeIndex + 1} / ${totalLabel}`;
    }
  }

  function scrollMatchIntoView(matchTarget) {
    if (!matchTarget) return;
    const rect = typeof matchTarget.getBoundingClientRect === 'function'
      ? matchTarget.getBoundingClientRect()
      : null;
    const appContent = document.querySelector('.app-content');

    if (rect && appContent && appContent.scrollHeight > appContent.clientHeight && typeof appContent.scrollTo === 'function') {
      const containerRect = appContent.getBoundingClientRect();
      const targetTop = appContent.scrollTop + (rect.top - containerRect.top) - (containerRect.height / 3);
      appContent.scrollTo({ top: Math.max(0, targetTop), behavior: 'smooth' });
      return;
    }

    const fallbackEl = matchTarget.startContainer
      ? matchTarget.startContainer.parentElement
      : matchTarget;
    if (fallbackEl && typeof fallbackEl.scrollIntoView === 'function') {
      fallbackEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
  }

  function setActiveMatch(index, shouldScroll) {
    if (activeMatches.length === 0) return;
    const total = activeMatches.length;
    const normalized = ((index % total) + total) % total;

    if (usingCustomHighlights && supportsCustomHighlightApi()) {
      CSS.highlights.delete('qwiki-search-active');
      const activeRange = activeMatches[normalized];
      const activeHighlight = new Highlight(activeRange);
      activeHighlight.priority = 1;
      CSS.highlights.set('qwiki-search-active', activeHighlight);
    } else {
      if (activeIndex >= 0 && activeMatches[activeIndex]) {
        activeMatches[activeIndex].classList.remove('qwiki-search-mark-active');
      }
      if (activeMatches[normalized]) {
        activeMatches[normalized].classList.add('qwiki-search-mark-active');
      }
    }

    activeIndex = normalized;
    updateHighlightBar();

    if (shouldScroll) {
      scrollMatchIntoView(activeMatches[activeIndex]);
    }
  }

  function stepMatch(delta) {
    if (activeMatches.length === 0) return;
    setActiveMatch(activeIndex + delta, true);
  }

  function highlightDocumentTerm(term, options) {
    const opts = options || {};
    clearHighlights();

    const trimmed = (term || '').trim();
    if (!trimmed) return 0;

    const contentBody = document.getElementById('content-body');
    if (!contentBody || contentBody.style.display === 'none') return 0;

    const lowerTerm = trimmed.toLowerCase();
    const textNodes = collectTextNodes(contentBody);
    const nodeMatches = [];
    let totalCount = 0;

    for (const textNode of textNodes) {
      const text = textNode.nodeValue || '';
      const lowerText = text.toLowerCase();
      let pos = lowerText.indexOf(lowerTerm);
      if (pos === -1) continue;

      const offsets = [];
      while (pos !== -1) {
        offsets.push([pos, pos + trimmed.length]);
        totalCount++;
        if (totalCount >= MAX_HIGHLIGHT_MATCHES) {
          matchesCapped = true;
          break;
        }
        pos = lowerText.indexOf(lowerTerm, pos + trimmed.length);
      }

      if (offsets.length > 0) {
        nodeMatches.push({ node: textNode, offsets });
      }
      if (matchesCapped) break;
    }

    if (nodeMatches.length === 0) return 0;

    activeTerm = trimmed;

    if (supportsCustomHighlightApi()) {
      usingCustomHighlights = true;
      const ranges = [];
      for (const entry of nodeMatches) {
        for (const [start, end] of entry.offsets) {
          const range = new Range();
          range.setStart(entry.node, start);
          range.setEnd(entry.node, end);
          ranges.push(range);
        }
      }
      const baseHighlight = new Highlight(...ranges);
      baseHighlight.priority = 0;
      CSS.highlights.set('qwiki-search', baseHighlight);
      activeMatches = ranges;
    } else {
      usingCustomHighlights = false;
      const marks = [];
      for (const entry of nodeMatches) {
        const text = entry.node.nodeValue || '';
        const frag = document.createDocumentFragment();
        let lastIndex = 0;
        for (const [start, end] of entry.offsets) {
          if (start > lastIndex) {
            frag.appendChild(document.createTextNode(text.slice(lastIndex, start)));
          }
          const mark = document.createElement('mark');
          mark.className = 'qwiki-search-mark';
          mark.textContent = text.slice(start, end);
          frag.appendChild(mark);
          marks.push(mark);
          lastIndex = end;
        }
        if (lastIndex < text.length) {
          frag.appendChild(document.createTextNode(text.slice(lastIndex)));
        }
        entry.node.parentNode.replaceChild(frag, entry.node);
      }
      fallbackMarks = marks;
      activeMatches = marks;
    }

    setActiveMatch(0, !!opts.scrollToFirst);
    return activeMatches.length;
  }

  function capturePreSearchCollapsedState() {
    if (!preSearchCollapsedState) {
      preSearchCollapsedState = new Map();
      document.querySelectorAll('.nav-category-item').forEach(catItem => {
        preSearchCollapsedState.set(catItem, catItem.classList.contains('collapsed'));
      });
    }
  }

  function restoreSidebarNav() {
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
  }

  async function applySidebarFilter(term) {
    if (abortController) abortController.abort();
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
  }

  function clearSearch() {
    const searchInput = document.getElementById('search-input');
    const sidebarSearchClear = document.getElementById('sidebar-search-clear');
    if (searchInput) searchInput.value = '';
    if (sidebarSearchClear) sidebarSearchClear.style.display = 'none';
    clearTimeout(searchTimeout);
    if (abortController) abortController.abort();

    clearSearchState();
    clearHighlights();
    restoreSidebarNav();
    if (searchInput) searchInput.focus();
  }

  function init() {
    const searchInput = document.getElementById('search-input');
    const sidebarSearchClear = document.getElementById('sidebar-search-clear');
    if (!searchInput) return;

    if (sidebarSearchClear && searchInput.value.length > 0) {
      sidebarSearchClear.style.display = 'flex';
    }

    if (sidebarSearchClear) {
      sidebarSearchClear.addEventListener('click', clearSearch);
    }

    const sidebarNav = document.querySelector('.sidebar-nav');
    if (sidebarNav) {
      sidebarNav.addEventListener('click', (e) => {
        const link = e.target.closest('.nav-link');
        if (!link) return;
        const rawTerm = searchInput.value.trim();
        if (rawTerm) {
          const url = new URL(link.href, window.location.origin);
          if (url.origin === window.location.origin && link.target !== '_blank') {
            saveSearchState(rawTerm, url.pathname + url.search);
          }
        } else {
          clearSearchState();
        }
      });
    }

    const btnEditMarkdown = document.getElementById('btn-edit-markdown');
    if (btnEditMarkdown) {
      btnEditMarkdown.addEventListener('click', () => clearHighlights());
    }

    searchInput.addEventListener('keydown', (e) => {
      if (e.key === 'Escape' && searchInput.value) {
        e.preventDefault();
        clearSearch();
      } else if (e.key === 'Enter' && activeMatches.length > 0) {
        e.preventDefault();
        stepMatch(e.shiftKey ? -1 : 1);
      }
    });

    searchInput.addEventListener('input', (e) => {
      const rawTerm = e.target.value.trim();
      const term = rawTerm.toLowerCase();
      if (sidebarSearchClear) {
        sidebarSearchClear.style.display = e.target.value.length > 0 ? 'flex' : 'none';
      }
      clearTimeout(searchTimeout);
      if (abortController) abortController.abort();

      if (term === '') {
        clearSearchState();
        clearHighlights();
        restoreSidebarNav();
        return;
      }

      capturePreSearchCollapsedState();

      searchTimeout = setTimeout(() => {
        applySidebarFilter(term);
        highlightDocumentTerm(rawTerm, { scrollToFirst: false });
      }, 300);
    });

    const restoredTerm = loadSearchStateForCurrentPage();
    if (restoredTerm) {
      searchInput.value = restoredTerm;
      if (sidebarSearchClear) {
        sidebarSearchClear.style.display = 'flex';
      }
      capturePreSearchCollapsedState();
      applySidebarFilter(restoredTerm.toLowerCase());
      window.requestAnimationFrame(() => {
        setTimeout(() => {
          highlightDocumentTerm(restoredTerm, { scrollToFirst: true });
        }, 80);
      });
    }
  }

  return {
    init,
    clearSearch,
    highlightDocumentTerm,
    clearHighlights,
    stepMatch
  };
})();
