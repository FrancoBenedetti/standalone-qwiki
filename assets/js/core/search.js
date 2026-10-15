/**
 * Standalone Qwiki - Core Search Controller
 * Handles debounced sidebar quick search, asynchronous querying against api/search.php,
 * dynamic document filtering, and category auto-expansion with state restoration.
 */
window.QwikiApp = window.QwikiApp || {};

window.QwikiApp.search = (function() {
  'use strict';

  let searchTimeout = null;
  let abortController = null;
  let preSearchCollapsedState = null;

  function clearSearch() {
    const searchInput = document.getElementById('search-input');
    const sidebarSearchClear = document.getElementById('sidebar-search-clear');
    if (searchInput) searchInput.value = '';
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

  return {
    init,
    clearSearch
  };
})();
