(function () {
  'use strict';

  function initializePhoneAuth(form) {
    var config = window.acrPhoneAuth;
    var requestId = '';
    var phoneStep = form.querySelector('.acr-phone-auth__phone');
    var otpStep = form.querySelector('.acr-phone-auth__otp');
    var phoneInput = form.elements.phone;
    var otpInput = form.elements.otp;
    var message = form.parentElement.querySelector('.acr-auth-message');

    function setMessage(text, error, loading) {
      message.textContent = text || '';
      message.classList.toggle('is-error', Boolean(error));
      message.classList.toggle('is-loading', Boolean(loading));
    }

    function setBusy(busy) {
      form.setAttribute('aria-busy', busy ? 'true' : 'false');
      form.querySelectorAll('button[type="submit"]').forEach(function (button) {
        button.disabled = busy && button.closest('[data-active="true"]') !== null;
      });
    }

    function showStep(step) {
      var phoneActive = step === 'phone';
      form.dataset.step = step;
      phoneStep.hidden = !phoneActive;
      otpStep.hidden = phoneActive;
      phoneStep.dataset.active = phoneActive ? 'true' : 'false';
      otpStep.dataset.active = phoneActive ? 'false' : 'true';
      phoneInput.disabled = !phoneActive;
      otpInput.disabled = phoneActive;
    }

    function post(action, values) {
      var body = new URLSearchParams(Object.assign({ action: action, nonce: config.nonce }, values));
      return fetch(config.ajaxUrl, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
        body: body.toString()
      }).then(function (response) {
        return response.json().catch(function () {
          throw new Error('پاسخ نامعتبر از سرور دریافت شد.');
        });
      });
    }

    showStep('phone');
    form.addEventListener('submit', function (event) {
      event.preventDefault();
      setBusy(true);

      if (form.dataset.step === 'phone') {
        if (!phoneInput.value.trim()) {
          setMessage('شماره موبایل را وارد کنید.', true, false);
          setBusy(false);
          phoneInput.focus();
          return;
        }
        setMessage('در حال ارسال درخواست کد ورود…', false, true);
        post('acr_request_otp', { phone: phoneInput.value }).then(function (result) {
          if (!result.success) {
            throw new Error(result.data && result.data.message ? result.data.message : 'ارسال کد ورود ناموفق بود.');
          }
          requestId = result.data.requestId || '';
          showStep('otp');
          setMessage(result.data.message || 'کد ورود ارسال شد.', false, false);
          setBusy(false);
          otpInput.focus();
        }).catch(function (error) {
          setMessage(error.message, true, false);
          setBusy(false);
          phoneInput.focus();
        });
        return;
      }

      setMessage('در حال بررسی کد و ورود به سیستم…', false, true);
      post('acr_verify_otp', { phone: phoneInput.value, otp: otpInput.value, requestId: requestId, redirect: config.redirect }).then(function (result) {
        if (!result.success) {
          throw new Error(result.data && result.data.message ? result.data.message : 'ورود ناموفق بود.');
        }
        setMessage(result.data.message, false, false);
        window.location.assign(result.data.redirect);
      }).catch(function (error) {
        setMessage(error.message, true, false);
        setBusy(false);
      });
    });

    form.querySelector('.acr-auth-back').addEventListener('click', function () {
      requestId = '';
      otpInput.value = '';
      showStep('phone');
      setMessage('', false, false);
      phoneInput.focus();
    });
  }

  function initializeDashboard(portal) {
    var tabButtons = portal.querySelectorAll('[data-acr-tab]');
    var panels = portal.querySelectorAll('[data-acr-panel]');

    function showTab(name) {
      var target = portal.querySelector('[data-acr-panel="' + name + '"]');
      if (!target) return;
      tabButtons.forEach(function (button) {
        var active = button.dataset.acrTab === name;
        button.classList.toggle('is-active', active);
        button.setAttribute('aria-selected', active ? 'true' : 'false');
      });
      panels.forEach(function (panel) {
        var active = panel.dataset.acrPanel === name;
        panel.hidden = !active;
        panel.classList.toggle('is-active', active);
      });
    }

    tabButtons.forEach(function (button) {
      button.addEventListener('click', function () { showTab(button.dataset.acrTab); });
    });
    portal.querySelectorAll('[data-acr-go]').forEach(function (button) {
      button.addEventListener('click', function () { showTab(button.dataset.acrGo); });
    });

    var createArea = portal.querySelector('.acr-create-service');
    var createToggle = portal.querySelector('[data-acr-toggle-create]');
    if (createArea && createToggle) {
      createToggle.addEventListener('click', function () {
        var opening = createArea.hidden;
        createArea.hidden = !opening;
        createToggle.setAttribute('aria-expanded', opening ? 'true' : 'false');
        if (opening) createArea.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
      });
    }

    portal.querySelectorAll('[data-acr-create]').forEach(function (button) {
      button.addEventListener('click', function () {
        var name = button.dataset.acrCreate;
        portal.querySelectorAll('[data-acr-create]').forEach(function (item) {
          item.classList.toggle('is-active', item === button);
        });
        portal.querySelectorAll('[data-acr-create-panel]').forEach(function (panel) {
          var active = panel.dataset.acrCreatePanel === name;
          panel.hidden = !active;
          panel.classList.toggle('is-active', active);
        });
      });
    });

    if (window.location.hash === '#acr-server-order' || window.location.hash === '#acr-order-area') {
      showTab('services');
      if (createArea && createToggle) {
        createArea.hidden = false;
        createToggle.setAttribute('aria-expanded', 'true');
      }
    }
  }

  function initialize() {
    document.querySelectorAll('.acr-portal:not(.acr-login)').forEach(initializeDashboard);
    if (window.acrPhoneAuth) document.querySelectorAll('.acr-phone-auth').forEach(initializePhoneAuth);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initialize);
  } else {
    initialize();
  }
})();
