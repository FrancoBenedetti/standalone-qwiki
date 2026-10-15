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
    const modal = document.getElementById(modalId);
    if (modal) modal.classList.remove('open');
  }

  function closeAll() {
    document.querySelectorAll('.modal-overlay.open').forEach(m => m.classList.remove('open'));
  }

  function init() {
    // Data-close buttons
    document.querySelectorAll('[data-close]').forEach(btn => {
      btn.addEventListener('click', () => {
        const modalId = btn.getAttribute('data-close');
        close(modalId);
      });
    });

    // Close on backdrop click (clicking outside the modal card)
    document.querySelectorAll('.modal-overlay').forEach(modal => {
      modal.addEventListener('click', (e) => {
        if (e.target === modal) {
          modal.classList.remove('open');
        }
      });
    });

    // Close open modal on Escape key
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') {
        const openModal = document.querySelector('.modal-overlay.open');
        if (openModal) {
          openModal.classList.remove('open');
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
