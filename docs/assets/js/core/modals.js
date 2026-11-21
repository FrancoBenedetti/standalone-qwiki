/**
 * Standalone Qwiki - Core Modals Controller
 * Handles modal dialog open/close lifecycle, escape key dismissal,
 * backdrop click-outside dismissal, and user dropdown menu handling.
 */
window.QwikiApp = window.QwikiApp || {};

window.QwikiApp.modals = (function() {
  'use strict';

  function open(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
      modal.classList.add('open');
      const firstInput = modal.querySelector('input:not([type="hidden"]), select, textarea');
      if (firstInput) {
        setTimeout(() => firstInput.focus(), 50);
      }
    }
  }

  function close(modalId) {
    const modal = typeof modalId === 'string' ? document.getElementById(modalId) : modalId;
    if (modal) {
      modal.classList.remove('open');
      modal.classList.remove('active');
      if (modal.style.display === 'flex') {
        modal.style.display = '';
      }
    }
  }

  function closeAll() {
    document.querySelectorAll('.modal-overlay.open, .modal-overlay.active').forEach(m => {
      m.classList.remove('open');
      m.classList.remove('active');
      if (m.style.display === 'flex') {
        m.style.display = '';
      }
    });
  }

  function init() {
    // Data-close buttons
    document.querySelectorAll('[data-close]').forEach(btn => {
      btn.addEventListener('click', () => {
        const modalId = btn.getAttribute('data-close');
        close(modalId);
      });
    });

    // Close on backdrop click (clicking outside the modal card, but NOT releasing after drag/selection from inside)
    let pointerDownInsideCard = false;

    document.addEventListener('mousedown', (e) => {
      pointerDownInsideCard = !!e.target.closest('.modal-card, .modal-dialog, .modal-content');
    });

    document.addEventListener('mouseup', (e) => {
      if (e.target.closest('.modal-card, .modal-dialog, .modal-content')) {
        pointerDownInsideCard = true;
      }
      setTimeout(() => {
        pointerDownInsideCard = false;
      }, 0);
    });

    document.addEventListener('click', (e) => {
      const modal = e.target.closest('.modal-overlay');
      if (modal && e.target === modal) {
        if (!pointerDownInsideCard) {
          close(modal.id);
        }
        pointerDownInsideCard = false;
      }
    });

    window.addEventListener('blur', () => {
      pointerDownInsideCard = false;
    });

    // Close open modal on Escape key
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') {
        const openModal = document.querySelector('.modal-overlay.open, .modal-overlay.active');
        if (openModal) {
          close(openModal.id);
        }
      }
    });

    // User Dropdown Menu handling
    const userDropdownToggle = document.getElementById('user-dropdown-toggle');
    if (userDropdownToggle) {
      const dropdownMenu = userDropdownToggle.nextElementSibling;
      userDropdownToggle.addEventListener('click', (e) => {
        e.stopPropagation();
        dropdownMenu.classList.toggle('show');
      });

      document.addEventListener('click', (e) => {
        if (!userDropdownToggle.contains(e.target) && !dropdownMenu.contains(e.target)) {
          dropdownMenu.classList.remove('show');
        }
      });

      dropdownMenu.addEventListener('click', (e) => {
        if (e.target.closest('.dropdown-item')) {
          dropdownMenu.classList.remove('show');
        }
      });
    }

    // Header Wiki Selector Dropdown & Search handling
    const wikiSelectorToggle = document.getElementById('brand-wiki-selector-toggle');
    const wikiSelectorMenu = document.getElementById('brand-wiki-selector-menu');
    const wikiSelectorSearch = document.getElementById('wiki-selector-search');
    const wikiSelectorEmpty = document.getElementById('wiki-selector-empty');

    if (wikiSelectorToggle && wikiSelectorMenu) {
      const filterWikiItems = (rawTerm) => {
        const term = (rawTerm || '').toLowerCase().trim();
        const items = wikiSelectorMenu.querySelectorAll('.wiki-selector-item');
        let visibleCount = 0;
        items.forEach(item => {
          const title = (item.getAttribute('data-wiki-title') || item.textContent || '').toLowerCase();
          const slug = (item.getAttribute('data-wiki-slug') || '').toLowerCase();
          const matches = !term || title.includes(term) || slug.includes(term);
          item.style.display = matches ? '' : 'none';
          if (matches) visibleCount++;
        });
        if (wikiSelectorEmpty) {
          wikiSelectorEmpty.style.display = visibleCount === 0 ? 'block' : 'none';
        }
      };

      const closeWikiSelector = (restoreFocus) => {
        if (!wikiSelectorMenu.classList.contains('show')) return;
        wikiSelectorMenu.classList.remove('show');
        wikiSelectorToggle.setAttribute('aria-expanded', 'false');
        if (restoreFocus && typeof wikiSelectorToggle.focus === 'function') {
          wikiSelectorToggle.focus();
        }
      };

      wikiSelectorToggle.addEventListener('click', (e) => {
        e.stopPropagation();
        const willOpen = !wikiSelectorMenu.classList.contains('show');
        if (willOpen) {
          if (wikiSelectorSearch) {
            wikiSelectorSearch.value = '';
          }
          filterWikiItems('');
          wikiSelectorMenu.classList.add('show');
          wikiSelectorToggle.setAttribute('aria-expanded', 'true');
          if (wikiSelectorSearch && typeof wikiSelectorSearch.focus === 'function') {
            setTimeout(() => wikiSelectorSearch.focus(), 10);
          }
        } else {
          closeWikiSelector(false);
        }
      });

      if (wikiSelectorSearch) {
        wikiSelectorSearch.addEventListener('input', (e) => {
          filterWikiItems(e.target.value);
        });
        wikiSelectorSearch.addEventListener('click', (e) => {
          e.stopPropagation();
        });
      }

      wikiSelectorMenu.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
          e.preventDefault();
          e.stopPropagation();
          closeWikiSelector(true);
          return;
        }
        if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
          const visibleItems = Array.from(wikiSelectorMenu.querySelectorAll('.wiki-selector-item'))
            .filter(el => el.style.display !== 'none');
          if (visibleItems.length === 0) return;
          e.preventDefault();
          const currentIndex = visibleItems.indexOf(document.activeElement);
          let nextIndex = 0;
          if (e.key === 'ArrowDown') {
            nextIndex = currentIndex < 0 ? 0 : (currentIndex + 1) % visibleItems.length;
          } else {
            nextIndex = currentIndex <= 0 ? visibleItems.length - 1 : currentIndex - 1;
          }
          visibleItems[nextIndex].focus();
        } else if (e.key === 'Enter' && document.activeElement === wikiSelectorSearch) {
          const firstVisible = Array.from(wikiSelectorMenu.querySelectorAll('.wiki-selector-item'))
            .find(el => el.style.display !== 'none');
          if (firstVisible) {
            e.preventDefault();
            firstVisible.click();
          }
        }
      });

      document.addEventListener('click', (e) => {
        if (!wikiSelectorToggle.contains(e.target) && !wikiSelectorMenu.contains(e.target)) {
          closeWikiSelector(false);
        }
      });
    }
  }

  return {
    init,
    open,
    close,
    closeAll
  };
})();
