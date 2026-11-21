/**
 * Standalone Qwiki - Core Sidebar & Navigation Controller
 * Handles mobile drawer toggle, draggable resize engine, accordion state persistence,
 * and scroll position restoration across page navigation.
 */
window.QwikiApp = window.QwikiApp || {};

window.QwikiApp.sidebar = (function() {
  'use strict';

  function getCategoryId(catItem) {
    if (!catItem) return null;
    return catItem.getAttribute('data-category-id') 
      || catItem.getAttribute('data-node-id')
      || catItem.querySelector('.nav-document-list')?.getAttribute('data-parent-node-id')
      || (catItem.classList.contains('nav-subwikis-group') ? 'subwikis' : null);
  }

  function getSavedCategoryStates() {
    try {
      return JSON.parse(sessionStorage.getItem('qwiki_category_states') || '{}');
    } catch (e) {
      return {};
    }
  }

  function saveCategoryState(catId, isCollapsed) {
    if (!catId) return;
    const states = getSavedCategoryStates();
    states[catId] = isCollapsed ? 'collapsed' : 'expanded';
    try {
      sessionStorage.setItem('qwiki_category_states', JSON.stringify(states));
    } catch (e) {}
  }

  function initWidth() {
    const savedWidth = localStorage.getItem('qwiki_sidebar_width');
    if (savedWidth) {
      document.documentElement.style.setProperty('--sidebar-width', savedWidth + 'px');
    }
  }

  function initMobileToggle() {
    const mobileToggle = document.getElementById('mobile-toggle');
    const sidebar = document.getElementById('app-sidebar');
    if (mobileToggle && sidebar) {
      mobileToggle.addEventListener('click', () => {
        sidebar.classList.toggle('open');
      });
    }
  }

  function initResizer() {
    const resizer = document.getElementById('sidebar-resizer');
    const sidebar = document.getElementById('app-sidebar');
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
  }

  function initAccordions() {
    const savedCatStates = getSavedCategoryStates();
    document.querySelectorAll('.nav-category-item').forEach(catItem => {
      const hasActiveLink = !!catItem.querySelector('.nav-link.active');
      if (hasActiveLink) {
        catItem.classList.remove('collapsed');
        return;
      }
      const catId = getCategoryId(catItem);
      if (catId && savedCatStates[catId] !== undefined) {
        if (savedCatStates[catId] === 'expanded') {
          catItem.classList.remove('collapsed');
        } else if (savedCatStates[catId] === 'collapsed') {
          catItem.classList.add('collapsed');
        }
      }
    });

    document.querySelectorAll('.nav-category-header').forEach(header => {
      header.addEventListener('click', (e) => {
        if (e.target.closest('.btn-edit-cat-icon') || e.target.closest('.drag-handle')) return;
        const catItem = header.closest('.nav-category-item');
        if (catItem) {
          catItem.classList.toggle('collapsed');
          const catId = getCategoryId(catItem);
          if (catId) {
            saveCategoryState(catId, catItem.classList.contains('collapsed'));
          }
        }
      });
    });
  }

  function initScrollPersistence() {
    const sidebarNav = document.querySelector('.sidebar-nav');
    if (!sidebarNav) return;

    const rawSavedScroll = sessionStorage.getItem('qwiki_sidebar_scroll');
    const hasSavedScroll = rawSavedScroll !== null && !isNaN(parseInt(rawSavedScroll, 10));

    if (hasSavedScroll) {
      const targetScroll = parseInt(rawSavedScroll, 10);
      sidebarNav.scrollTop = targetScroll;
      if (typeof requestAnimationFrame === 'function') {
        requestAnimationFrame(() => {
          sidebarNav.scrollTop = targetScroll;
        });
      }
    }

    const activeLink = sidebarNav.querySelector('.nav-link.active');
    if (activeLink) {
      if (!hasSavedScroll) {
        if (typeof activeLink.scrollIntoView === 'function') {
          activeLink.scrollIntoView({ block: 'nearest', inline: 'nearest' });
        }
      } else {
        if (typeof activeLink.getBoundingClientRect === 'function' && typeof sidebarNav.getBoundingClientRect === 'function') {
          const linkRect = activeLink.getBoundingClientRect();
          const navRect = sidebarNav.getBoundingClientRect();
          const isVisible = (linkRect.top >= navRect.top && linkRect.bottom <= navRect.bottom);
          if (!isVisible && typeof activeLink.scrollIntoView === 'function') {
            activeLink.scrollIntoView({ block: 'nearest', inline: 'nearest' });
          }
        }
      }
    }

    sidebarNav.addEventListener('click', (e) => {
      const link = e.target.closest('a');
      if (link && (!link.target || link.target === '_self')) {
        try {
          sessionStorage.setItem('qwiki_sidebar_scroll', sidebarNav.scrollTop);
        } catch (e) {}
      }
    });

    window.addEventListener('beforeunload', () => {
      try {
        sessionStorage.setItem('qwiki_sidebar_scroll', sidebarNav.scrollTop);
      } catch (e) {}
    });
  }

  function toggleLeftSidebar() {
    const container = document.querySelector('.app-container');
    if (!container) return;
    const isCollapsed = container.classList.toggle('sidebar-collapsed');
    try {
      localStorage.setItem('qwiki_sidebar_collapsed', isCollapsed ? '1' : '0');
    } catch (e) {}
    const btn = document.getElementById('btn-toggle-left-sidebar');
    if (btn) btn.classList.toggle('active', isCollapsed);
  }

  function toggleRightToc() {
    const container = document.querySelector('.app-container');
    if (!container) return;
    const isCollapsed = container.classList.toggle('toc-collapsed');
    try {
      localStorage.setItem('qwiki_toc_collapsed', isCollapsed ? '1' : '0');
    } catch (e) {}
    const btn = document.getElementById('btn-toggle-right-toc');
    if (btn) btn.classList.toggle('active', isCollapsed);
  }

  function initCollapseControls() {
    const container = document.querySelector('.app-container');
    if (!container) return;

    try {
      if (localStorage.getItem('qwiki_sidebar_collapsed') === '1') {
        container.classList.add('sidebar-collapsed');
        const leftBtn = document.getElementById('btn-toggle-left-sidebar');
        if (leftBtn) leftBtn.classList.add('active');
      }
      if (localStorage.getItem('qwiki_toc_collapsed') === '1') {
        container.classList.add('toc-collapsed');
        const rightBtn = document.getElementById('btn-toggle-right-toc');
        if (rightBtn) rightBtn.classList.add('active');
      }
    } catch (e) {}

    const leftBtn = document.getElementById('btn-toggle-left-sidebar');
    if (leftBtn) {
      leftBtn.addEventListener('click', toggleLeftSidebar);
    }

    const rightBtn = document.getElementById('btn-toggle-right-toc');
    if (rightBtn) {
      rightBtn.addEventListener('click', toggleRightToc);
    }

    document.addEventListener('keydown', (e) => {
      if (e.ctrlKey || e.metaKey || e.altKey) return;
      const active = document.activeElement;
      if (active) {
        const tag = active.tagName;
        if (tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT' || active.isContentEditable || active.closest('.toastui-editor')) {
          return;
        }
      }
      if (e.key === '[') {
        e.preventDefault();
        toggleLeftSidebar();
      } else if (e.key === ']') {
        e.preventDefault();
        toggleRightToc();
      }
    });
  }

  function init() {
    initWidth();
    initMobileToggle();
    initResizer();
    initAccordions();
    initScrollPersistence();
    initCollapseControls();
  }

  // Export globally for backward compatibility with existing drag-and-drop code
  window.getCategoryId = getCategoryId;
  window.saveCategoryState = saveCategoryState;

  return {
    init,
    getCategoryId,
    getSavedCategoryStates,
    saveCategoryState,
    toggleLeftSidebar,
    toggleRightToc
  };
})();
