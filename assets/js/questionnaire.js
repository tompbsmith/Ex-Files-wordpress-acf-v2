/**
 * Full service questionnaire: conditional fields, Add another / Remove,
 * monthly equivalents and outgoings totals. The form works without this
 * (the server drops hidden answers and re-shows fields after saving).
 */
(function () {
  'use strict';
  // Usually one form; the staff preview page has one per section.
  var forms = document.querySelectorAll('[data-questionnaire]');
  for (var f = 0; f < forms.length; f++) { init(forms[f]); }

  function init(form) {
  // The staff preview doesn't save: stop it submitting.
  var preview = form.hasAttribute('data-preview');
  if (preview) { form.addEventListener('submit', function (e) { e.preventDefault(); }); }

  var PER_YEAR = { 'Weekly': 52, 'Fortnightly': 26, 'Four-weekly': 13, 'Monthly': 12, 'Termly': 3, 'Quarterly': 4, 'Annual': 1 };

  function pounds(n) {
    return '£' + n.toLocaleString('en-GB', { minimumFractionDigits: n % 1 ? 2 : 0, maximumFractionDigits: 2 });
  }
  function num(v) {
    v = String(v || '').replace(/[£,\s]/g, '');
    return v === '' || isNaN(v) ? null : parseFloat(v);
  }

  // The value of a field (by data-key) in the nearest scope, ignoring nested scopes.
  function scopeValue(scope, key) {
    var els = scope.querySelectorAll('[data-key="' + key + '"]');
    for (var i = 0; i < els.length; i++) {
      var el = els[i];
      var own = el.closest('.q-scope');
      // A money fieldset is its own scope but its data-key belongs to the parent.
      if (own !== scope && !(el.classList.contains('q-scope'))) { continue; }
      if (el.type === 'radio') { if (el.checked) { return el.value; } continue; }
      if (el.tagName === 'SELECT' || el.tagName === 'INPUT' || el.tagName === 'TEXTAREA') { return el.value; }
    }
    return '';
  }

  // Ease in content a gate question has just shown (assets/js/motion.js).
  function revealEl(el) { if (window.exfReveal) { window.exfReveal(el); } }
  function resetEl(el) { if (window.exfReveal) { window.exfReveal.reset(el); } }

  function applyConditions(animate) {
    var els = form.querySelectorAll('[data-show-if]');
    for (var i = 0; i < els.length; i++) {
      var el = els[i];
      if (el.closest('template')) { continue; }
      var rule;
      try { rule = JSON.parse(el.getAttribute('data-show-if')); } catch (e) { continue; }
      var parent = el.parentElement ? el.parentElement.closest('.q-scope') : null;
      // Money rows look inside their own fieldset.
      if (el.parentElement && el.parentElement.classList.contains('q-money')) { parent = el.parentElement; }
      if (!parent) { continue; }
      var show = rule.values.indexOf(scopeValue(parent, rule.key)) !== -1;
      var wasHidden = el.hidden;
      el.hidden = !show;
      if (animate && wasHidden && show) { revealEl(el); }
      if (!show && !wasHidden) { resetEl(el); }
    }
  }

  function updateMonthly() {
    var moneys = form.querySelectorAll('.q-money');
    for (var i = 0; i < moneys.length; i++) {
      var m = moneys[i];
      var out = m.querySelector(':scope > [data-monthly]');
      if (!out) { continue; }
      var amount = num((m.querySelector('[data-key="amount"]') || {}).value);
      var freq = (m.querySelector('[data-key="frequency"]') || {}).value;
      var c = scopeValue(m, 'certainty');
      if (amount !== null && PER_YEAR[freq] && freq !== 'Monthly' && c !== "Don't know") {
        out.textContent = 'That’s about ' + pounds(Math.round(amount * PER_YEAR[freq] / 12 * 100) / 100) + ' a month.';
      } else {
        out.textContent = '';
      }
    }
  }

  function updateTotals() {
    var box = form.querySelector('[data-outgoings]');
    if (!box) { return; }
    var totals = { current: 0, future: 0 };
    var rows = box.querySelectorAll('.q-out-row');
    for (var i = 0; i < rows.length; i++) {
      var freq = rows[i].querySelector('[data-col="frequency"]').value;
      ['current', 'future'].forEach(function (col) {
        var v = num(rows[i].querySelector('[data-col="' + col + '"]').value);
        if (v !== null && PER_YEAR[freq]) { totals[col] += v * PER_YEAR[freq] / 12; }
      });
    }
    ['current', 'future'].forEach(function (col) {
      var el = box.querySelector('[data-total="' + col + '"]');
      if (el) { el.textContent = pounds(Math.round(totals[col] * 100) / 100); }
    });
  }

  function renumber(repeater) {
    var label = repeater.getAttribute('data-item-label');
    var items = repeater.querySelectorAll(':scope > .q-items > .q-item');
    for (var i = 0; i < items.length; i++) {
      items[i].querySelector('.q-item-title').textContent = label + ' ' + (i + 1);
      items[i].querySelector('.q-remove').hidden = items.length === 1;
    }
  }

  var counter = Date.now() % 100000;
  form.addEventListener('click', function (e) {
    var add = e.target.closest('.q-add');
    if (add) {
      var rep = add.closest('.q-repeater');
      var tpl = rep.querySelector(':scope > template');
      var html = tpl.innerHTML.replace(/__i__/g, 'n' + (counter++));
      var wrap = document.createElement('div');
      wrap.innerHTML = html;
      var item = wrap.firstElementChild;
      rep.querySelector(':scope > .q-items').appendChild(item);
      renumber(rep);
      applyConditions();
      revealEl(item);
      var first = item.querySelector('input:not([type=radio]), select, textarea');
      if (first) { first.focus(); }
      return;
    }
    var remove = e.target.closest('.q-remove');
    if (remove) {
      var r = remove.closest('.q-repeater');
      var it = remove.closest('.q-item');
      if (it.querySelector('input[type=text]:not([value=""]), textarea') && !window.confirm('Remove this item and its answers?')) { return; }
      it.remove();
      renumber(r);
      var addBtn = r.querySelector(':scope > .q-add');
      if (addBtn) { addBtn.focus(); }
    }
  });

  form.addEventListener('change', function () { applyConditions(true); updateMonthly(); updateTotals(); });
  form.addEventListener('input', function (e) {
    if (e.target.matches('[data-key="amount"]')) { updateMonthly(); }
    if (e.target.closest('[data-outgoings]')) { updateTotals(); }
  });

  // Warn about leaving with unsaved changes.
  var dirty = false;
  form.addEventListener('input', function () { dirty = true; });
  form.addEventListener('submit', function () { dirty = false; });
  if (!preview) { window.addEventListener('beforeunload', function (e) { if (dirty) { e.preventDefault(); e.returnValue = ''; } }); }

  var reps = form.querySelectorAll('.q-repeater');
  for (var i = 0; i < reps.length; i++) { renumber(reps[i]); }
  applyConditions();
  updateMonthly();
  updateTotals();
  }
})();
