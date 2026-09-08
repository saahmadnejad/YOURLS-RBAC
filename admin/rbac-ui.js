// YOURLS-RBAC UI enhancements: list search/filter, delete confirmation
// dialog, password strength meter, slug pattern hint. Vanilla JS, no deps.
(function () {
  'use strict';

  function init() {
    // --- Theme toggle (optional dark mode; default follows the OS) ---
    // Stored choice lives on <html data-rbac-theme>; when unset, the CSS
    // media query follows prefers-color-scheme.
    var html = document.documentElement;
    var KEY = 'rbac-theme';
    var apply = function (theme) {
      if (theme === 'auto') {
        html.removeAttribute('data-rbac-theme');
      } else {
        html.setAttribute('data-rbac-theme', theme);
      }
    };
    // re-apply any stored choice (covers bfcache page restores)
    apply(localStorage.getItem(KEY) || 'auto');

    document.querySelectorAll('.rbac-toolbar').forEach(function (bar) {
      var btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'rbac-theme-toggle';
      var label = function () {
        var current = html.getAttribute('data-rbac-theme')
          || (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
        btn.textContent = current === 'dark' ? '☀' : '☾';
        btn.title = current === 'dark' ? 'Switch to light theme' : 'Switch to dark theme';
      };
      btn.addEventListener('click', function () {
        var current = html.getAttribute('data-rbac-theme')
          || (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
        var next = current === 'dark' ? 'light' : 'dark';
        apply(next);
        localStorage.setItem(KEY, next);
        label();
      });
      label();
      bar.appendChild(btn);
    });

    // --- Search / filter for .rbac-list items and matrix rows ---
    document.querySelectorAll('input.rbac-search').forEach(function (input) {
      var list = document.querySelector(input.getAttribute('data-rbac-target'));
      if (!list) return;
      var rows = function () {
        // card lists use .rbac-item, the matrix uses tbody tr
        return list.classList.contains('rbac-matrix-wrap')
          ? list.querySelectorAll('tbody tr')
          : list.querySelectorAll('.rbac-item');
      };
      input.addEventListener('input', function () {
        var q = input.value.trim().toLowerCase();
        rows().forEach(function (item) {
          var match = !q || item.textContent.toLowerCase().indexOf(q) !== -1;
          item.classList.toggle('is-hidden', !match);
        });
      });
    });

    // --- Delete confirmation dialog (replaces window.confirm) ---
    var dialog = document.getElementById('rbac-confirm-dialog');
    document.querySelectorAll('form[data-rbac-confirm]').forEach(function (form) {
      form.addEventListener('submit', function (e) {
        if (form.dataset.rbacConfirmed === '1') return; // already confirmed
        e.preventDefault();
        var message = form.getAttribute('data-rbac-confirm');
        if (dialog) {
          dialog.querySelector('.rbac-confirm-message').textContent = message;
          var yes = dialog.querySelector('.rbac-confirm-yes');
          var no = dialog.querySelector('.rbac-confirm-no');
          var cleanup = function () {
            yes.removeEventListener('click', onYes);
            no.removeEventListener('click', onNo);
            dialog.close();
          };
          var onYes = function () {
            cleanup();
            form.dataset.rbacConfirmed = '1';
            form.submit();
          };
          var onNo = function () { cleanup(); };
          yes.addEventListener('click', onYes);
          no.addEventListener('click', onNo);
          dialog.showModal();
        } else {
          // fallback when the dialog markup is missing
          if (window.confirm(message)) {
            form.dataset.rbacConfirmed = '1';
            form.submit();
          }
        }
      });
    });

    // --- Password strength meter (create-user form only) ---
    var pw = document.querySelector('input[name="rbac_password"]');
    var meter = document.querySelector('.rbac-meter');
    var meterLabel = document.querySelector('.rbac-meter-label');
    if (pw && meter && pw.dataset.rbacKeep !== '1') {
      pw.addEventListener('input', function () {
        var v = pw.value;
        var score = 0;
        if (v.length >= 8) score++;
        if (/[A-Z]/.test(v) && /[a-z]/.test(v)) score++;
        if (/\d/.test(v)) score++;
        if (/[^A-Za-z0-9]/.test(v)) score++;
        meter.className = 'rbac-meter rbac-meter-' + score;
        var labels = ['too weak', 'weak', 'fair', 'good', 'strong'];
        meterLabel.textContent = v ? labels[score] : '';
      });
    }

    // --- Slug pattern hint (roles / permissions fields) ---
    document.querySelectorAll('input[data-rbac-slug]').forEach(function (input) {
      var field = input.closest('.rbac-field');
      var hint = field ? field.querySelector('.rbac-slug-hint') : null;
      var check = function () {
        var v = input.value.trim();
        var ok = v === '' || /^[a-z0-9_]+$/.test(v);
        input.style.borderColor = ok ? '' : '#e74c3c';
        if (hint) hint.style.display = ok ? 'none' : 'inline';
      };
      input.addEventListener('input', check);
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
