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
  }

  return {
    init,
    open,
    close,
    closeAll
  };
})();
