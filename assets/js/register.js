/* The Ex Files: "Create your Ex File" registration, one step at a time.
   Without this script all four steps show on one page and the server validates. */
(function () {
  'use strict';

  var form = document.querySelector('[data-register-form]');
  if (!form) { return; }

  var cfg = window.exfRegister || {};
  var steps = Array.prototype.slice.call(form.querySelectorAll('[data-reg-step]'));
  var total = steps.length;
  var btnBack = form.querySelector('[data-reg-back]');
  var btnNext = form.querySelector('[data-reg-next]');
  var btnSubmit = form.querySelector('[data-reg-submit]');
  var progress = form.querySelector('[data-reg-progress]');
  var count = form.querySelector('[data-reg-count]');
  var errorBox = form.querySelector('[data-reg-error]');
  var current = Math.min(Math.max(parseInt(form.dataset.startStep, 10) || 1, 1), total);

  form.classList.add('is-stepped');

  function setError(msg) { errorBox.textContent = msg || ''; }

  function show(n, focus) {
    current = n;
    steps.forEach(function (s, i) { s.hidden = i + 1 !== n; });
    btnBack.hidden = n === 1;
    btnNext.hidden = n === total;
    btnSubmit.hidden = n !== total;
    progress.style.width = Math.round((n / total) * 100) + '%';
    count.textContent = (cfg.stepLabel || 'Step %1$s of %2$s').replace('%1$s', n).replace('%2$s', total);
    setError('');
    if (focus) {
      var legend = steps[n - 1].querySelector('.gq-label');
      if (legend) { legend.focus({ preventScroll: true }); }
      var top = form.getBoundingClientRect().top + window.pageYOffset - 110;
      if (window.pageYOffset > top) { window.scrollTo({ top: top, behavior: 'smooth' }); }
    }
  }

  function mark(input, bad) {
    var field = input.closest('.field');
    if (field) { field.classList.toggle('has-error', bad); }
    input.setAttribute('aria-invalid', bad ? 'true' : 'false');
  }

  function validStep(n) {
    var step = steps[n - 1];
    var ok = true;
    var first = null;
    var message = cfg.required || 'Please fill in the highlighted fields.';

    step.querySelectorAll('input, select, textarea').forEach(function (input) {
      if (input.type === 'hidden' || input.type === 'checkbox' || input.type === 'radio') { return; }
      var bad = !input.checkValidity();
      mark(input, bad);
      if (bad) { ok = false; first = first || input; }
    });

    var radios = step.querySelectorAll('input[type="radio"][required]');
    if (radios.length && !step.querySelector('input[type="radio"]:checked')) {
      ok = false; first = first || radios[0];
    }

    if (n === 2) {
      var dobParts = form.querySelectorAll('[name^="ex_dob["]');
      var unknown = form.querySelector('[name="ex_dob_unknown"]');
      var filled = Array.prototype.some.call(dobParts, function (i) { return i.value.trim() !== ''; });
      if (!filled && !unknown.checked) {
        ok = false; dobParts.forEach(function (i) { mark(i, true); }); first = first || dobParts[0]; message = cfg.exDob || message;
      }
    }

    if (n === total) {
      var pw = form.querySelector('[name="password"]');
      var pw2 = form.querySelector('[name="password2"]');
      var terms = form.querySelector('[name="terms"]');
      if (pw.value.length < 10) { ok = false; mark(pw, true); first = first || pw; message = cfg.short || message; }
      else if (pw.value !== pw2.value) { ok = false; mark(pw2, true); first = first || pw2; message = cfg.mismatch || message; }
      if (!terms.checked) { ok = false; first = first || terms; }
    }

    if (!ok) {
      setError(message);
      if (first) { first.focus(); }
    }
    return ok;
  }

  // Marriage date only matters if they're married or in a civil partnership.
  var marriage = form.querySelector('.reg-if-married');
  function toggleMarriage() {
    var r = form.querySelector('[name="relationship"]:checked');
    if (marriage) { marriage.hidden = !r || r.value === 'not_married'; }
  }
  form.querySelectorAll('[name="relationship"]').forEach(function (r) { r.addEventListener('change', toggleMarriage); });
  toggleMarriage();

  // Ticking "I don't know" clears the date.
  var unknownBox = form.querySelector('[name="ex_dob_unknown"]');
  var exDob = form.querySelectorAll('[name^="ex_dob["]');
  if (unknownBox && exDob.length) {
    var syncDob = function () {
      exDob.forEach(function (i) {
        i.disabled = unknownBox.checked;
        if (unknownBox.checked) { i.value = ''; mark(i, false); }
      });
    };
    unknownBox.addEventListener('change', syncDob);
    syncDob();
  }


  btnNext.addEventListener('click', function () {
    if (validStep(current)) { show(current + 1, true); }
  });
  btnBack.addEventListener('click', function () { show(current - 1, true); });

  form.addEventListener('keydown', function (e) {
    if (e.key === 'Enter' && current < total && e.target.tagName !== 'TEXTAREA' && e.target.tagName !== 'BUTTON') {
      e.preventDefault();
      btnNext.click();
    }
  });

  form.addEventListener('submit', function (e) {
    if (current !== total || !validStep(current)) { e.preventDefault(); return; }
    if (exDob) { exDob.disabled = false; }
    btnSubmit.disabled = true;
    btnSubmit.textContent = cfg.sending || 'Creating your Ex File…';
  });

  show(current, false);
})();
