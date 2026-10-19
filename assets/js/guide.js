/* The Ex Files: Free Divorce Guide stepper.
   Shows one question at a time and follows the same branching as
   exf_guide_next_step() in inc/core/guide/logic.php. Keep the two in step.
   Without this script every question shows and the server works out the path. */
(function () {
  'use strict';

  var form = document.querySelector('[data-guide-form]');
  if (!form) { return; }

  var cfg = window.exfGuide || {};

  function next(step, answer) {
    switch (step) {
      case 'q1': return answer === 'yes' ? 'q2' : 'exit';
      case 'q2': return answer === 'yes' ? 'q3' : 'q2a';
      case 'q2a': return answer === 'yes' ? 'final' : 'exit';
      case 'q3': return answer === 'yes' ? 'q4' : 'q3a';
      case 'q3a': return 'final';
      case 'q4': return answer === 'yes' ? 'q5' : 'q5a';
      case 'q5':
      case 'q5a': return 'q6';
      case 'q6': return 'final';
    }
    return 'exit';
  }

  // Rough number of steps still to come, for the progress bar.
  var remaining = { q1: 5, q2: 4, q2a: 1, q3: 4, q3a: 1, q4: 3, q5: 2, q5a: 2, q6: 1, final: 0, exit: 0 };

  var steps = {};
  form.querySelectorAll('[data-step]').forEach(function (el) { steps[el.dataset.step] = el; });

  var btnBack = form.querySelector('[data-guide-back]');
  var btnNext = form.querySelector('[data-guide-next]');
  var btnSubmit = form.querySelector('[data-guide-submit]');
  var progress = form.querySelector('[data-guide-progress]');
  var status = form.querySelector('[data-guide-status]');
  var errorBox = form.querySelector('[data-guide-error]');

  var history = [];
  var current = 'q1';

  form.classList.add('is-stepped');

  function answerOf(step) {
    var checked = form.querySelector('input[name="answers[' + step + ']"]:checked');
    return checked ? checked.value : '';
  }

  function setError(msg) {
    errorBox.textContent = msg || '';
  }

  function show(step, focus) {
    Object.keys(steps).forEach(function (key) {
      var active = key === step;
      steps[key].hidden = !active;
      // Questions off the path don't submit stale answers.
      steps[key].querySelectorAll('input').forEach(function (input) {
        if (input.name && input.name.indexOf('answers[') === 0) { input.disabled = !active && history.indexOf(key) === -1; }
      });
    });
    current = step;
    setError('');

    var isQuestion = /^q\d+a?$/.test(step) && step !== 'q3a';
    btnBack.hidden = history.length === 0;
    btnNext.hidden = step === 'final' || step === 'exit';
    btnSubmit.hidden = step !== 'final';

    var done = history.length;
    var pct = step === 'final' || step === 'exit' ? 100 : Math.round((done / (done + remaining[step] + 1)) * 100);
    progress.style.width = Math.max(pct, 6) + '%';
    if (status) {
      status.textContent = isQuestion ? (cfg.progressLabel || 'Question') + ' ' + (done + 1) : '';
    }

    if (focus) {
      var label = steps[step].querySelector('.gq-label');
      if (label) { label.focus({ preventScroll: true }); }
      var top = form.getBoundingClientRect().top + window.pageYOffset - 110;
      if (window.pageYOffset > top) { window.scrollTo({ top: top, behavior: 'smooth' }); }
    }
  }

  btnNext.addEventListener('click', function () {
    var answer = '';
    if (current !== 'q3a') {
      answer = answerOf(current);
      if (!answer) { setError(cfg.chooseAnswer || 'Please choose an answer.'); return; }
    }
    history.push(current);
    show(next(current, answer), true);
  });

  btnBack.addEventListener('click', function () {
    if (!history.length) { return; }
    show(history.pop(), true);
  });

  // Pressing Enter on a question moves on rather than submitting.
  form.addEventListener('keydown', function (e) {
    if (e.key === 'Enter' && current !== 'final' && e.target.tagName !== 'TEXTAREA') {
      e.preventDefault();
      btnNext.click();
    }
  });

  form.addEventListener('submit', function (e) {
    var email = form.querySelector('input[name="email"]');
    if (current !== 'final') { e.preventDefault(); return; }
    if (!email.value || !email.checkValidity()) {
      e.preventDefault();
      setError(cfg.badEmail || 'Please enter a valid email address.');
      email.focus();
      return;
    }
    // Re-enable answers on the path so they're submitted.
    history.forEach(function (key) {
      steps[key].querySelectorAll('input').forEach(function (input) { input.disabled = false; });
    });
    btnSubmit.disabled = true;
    btnSubmit.textContent = cfg.sending || 'Creating your guide…';
  });

  show('q1', false);
})();
