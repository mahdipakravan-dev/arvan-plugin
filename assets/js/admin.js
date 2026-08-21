(function () {
  'use strict';

  document.addEventListener('click', function (event) {
    var secretButton = event.target.closest('.acr-toggle-secret');
    if (secretButton) {
      var input = secretButton.parentElement.querySelector('input');
      var hidden = input.type === 'password';
      input.type = hidden ? 'text' : 'password';
      secretButton.setAttribute('aria-label', hidden ? 'پنهان کردن توکن' : 'نمایش توکن');
    }

    var menuButton = event.target.closest('.acr-mobile-menu');
    if (menuButton) {
      var sidebar = document.getElementById('acr-sidebar');
      var open = sidebar.classList.toggle('is-open');
      menuButton.setAttribute('aria-expanded', open ? 'true' : 'false');
    }
  });

  document.addEventListener('submit', function (event) {
    var form = event.target;
    var action = form.querySelector('input[name="action"]');
    var diagnosticActions = ['acr_refresh_api', 'acr_sync_catalog', 'acr_test_connection'];
    if (!action || diagnosticActions.indexOf(action.value) === -1) {
      return;
    }

    var button = form.querySelector('button[type="submit"]');
    if (button) {
      button.disabled = true;
      button.classList.add('is-loading');
      button.innerHTML = '<span class="dashicons dashicons-update"></span> در حال ارسال درخواست…';
    }
  });
})();
