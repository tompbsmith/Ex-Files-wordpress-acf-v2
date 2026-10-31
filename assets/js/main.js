/* The Ex Files theme scripts. */
(function () {
  'use strict';

  /* ----- Mobile menu ----- */
  var burger = document.getElementById('burger');
  var navLinks = document.getElementById('navLinks');
  if (burger && navLinks) {
    var setOpen = function (open) {
      navLinks.classList.toggle('open', open);
      burger.setAttribute('aria-expanded', open ? 'true' : 'false');
    };
    burger.addEventListener('click', function () {
      var opening = !navLinks.classList.contains('open');
      setOpen(opening);
      // Take keyboard users straight into the menu (it sits before the button in the page).
      if (opening && window.matchMedia('(max-width: 880px)').matches) {
        var first = navLinks.querySelector('a');
        if (first) { setTimeout(function () { first.focus(); }, 30); }
      }
    });
    // If focus leaves the menu and its button (e.g. tabbing on), close the menu so focus is never hidden behind it.
    document.addEventListener('focusin', function (e) {
      if (navLinks.classList.contains('open') && !navLinks.contains(e.target) && e.target !== burger) {
        setOpen(false);
      }
    });
    navLinks.querySelectorAll('a').forEach(function (a) {
      a.addEventListener('click', function () { setOpen(false); });
    });
    // Escape closes the open menu and puts focus back on the menu button.
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && navLinks.classList.contains('open')) {
        setOpen(false);
        burger.focus();
      }
    });
  }

  /* ----- Hero video -----
     Load only the clip that matches this viewport: desktop and mobile are
     cropped differently, so neither visitor downloads the other's file. */
  var isDesktop = window.matchMedia('(min-width: 768px)').matches;
  var reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  document.querySelectorAll('[data-hero-media]').forEach(function (media) {
    var d = media.dataset;
    var video = media.querySelector('video');
    var poster = media.querySelector('.poster-fallback');

    var videoSrc = isDesktop ? (d.videoDesktop || d.videoMobile) : (d.videoMobile || d.videoDesktop);
    var posterSrc = isDesktop ? (d.posterDesktop || d.posterMobile) : (d.posterMobile || d.posterDesktop);
    // The mobile clip needs a different vertical crop; only apply it when that clip is used.
    var mobileCrop = !isDesktop && !!d.videoMobile;

    if (poster && posterSrc) {
      poster.src = posterSrc;
      if (mobileCrop) { poster.classList.add('is-mobile-src'); }
    }

    if (!video) { return; }
    if (mobileCrop) { video.classList.add('is-mobile-src'); }
    if (posterSrc) { video.poster = posterSrc; }

    // CSS hides the video for reduced-motion visitors; don't download it either.
    if (!videoSrc || reducedMotion) { return; }

    var source = document.createElement('source');
    source.src = videoSrc;
    source.type = 'video/mp4';
    video.appendChild(source);
    video.load();

    // Pause / play control (WCAG 2.2.2): the clip loops, so visitors must be able to stop it.
    var btn = media.parentNode.querySelector('.hero-pause');
    if (btn) {
      var label = btn.querySelector('.label');
      btn.hidden = false;
      btn.addEventListener('click', function () {
        var paused = !btn.classList.contains('is-paused');
        if (paused) { video.pause(); } else { video.play(); }
        btn.classList.toggle('is-paused', paused);
        var text = paused ? (btn.dataset.play || 'Play video') : (btn.dataset.pause || 'Pause video');
        if (label) { label.textContent = text; }
        btn.title = text;
      });
    }
  });

  /* ----- Contact form ----- */
  var cfg = window.exfContact || {};

  document.querySelectorAll('[data-contact-form]').forEach(function (form) {
    var status = form.querySelector('.form-status');
    var button = form.querySelector('[type="submit"]');
    var label = form.dataset.label || (button ? button.textContent : '');

    form.addEventListener('submit', function (e) {
      e.preventDefault();
      status.textContent = '';
      status.className = 'form-status';
      button.disabled = true;
      button.textContent = cfg.sending || 'Sending…';

      var data = new FormData(form);
      data.append('action', 'exf_contact');

      fetch(cfg.ajaxUrl, { method: 'POST', body: data, credentials: 'same-origin' })
        .then(function (res) {
          return res.json().catch(function () { return {}; }).then(function (json) {
            return { ok: res.ok, json: json };
          });
        })
        .then(function (r) {
          if (r.ok && r.json.success) {
            status.textContent = form.dataset.success;
            status.className = 'form-status ok';
            form.reset();
          } else {
            status.textContent = (r.json.data && r.json.data.error) || cfg.genericError;
            status.className = 'form-status err';
          }
        })
        .catch(function () {
          status.textContent = cfg.genericError;
          status.className = 'form-status err';
        })
        .then(function () {
          button.disabled = false;
          button.textContent = label;
        });
    });
  });
})();

// Day / Month / Year boxes: move on once a day or month has two digits.
document.addEventListener('input', function (e) {
  var input = e.target;
  if (!input.closest || !input.closest('.date-part--d, .date-part--m') || input.value.length !== 2) { return; }
  var next = input.closest('.date-part').nextElementSibling;
  if (next) { next.querySelector('input').focus(); }
});

// Phones: the portal tabs scroll sideways, so bring the current one into view.
(function () {
  var active = document.querySelector('.portal-nav a.active');
  var nav = active && active.closest('.portal-nav');
  if (!nav || nav.scrollWidth <= nav.clientWidth) { return; }
  nav.scrollLeft = Math.max(0, active.offsetLeft - nav.offsetLeft - (nav.clientWidth - active.offsetWidth) / 2);
})();

// Services list: "Show more" for text that's been shortened.
(function () {
  'use strict';
  function check() {
    document.querySelectorAll('[data-clamp]').forEach(function (box) {
      var btn = box.parentNode.querySelector('.service-list-more');
      if (!btn || box.classList.contains('is-open')) { return; }
      btn.hidden = box.scrollHeight <= box.clientHeight + 2;
    });
  }
  document.addEventListener('click', function (e) {
    var btn = e.target.closest && e.target.closest('.service-list-more');
    if (!btn) { return; }
    var box = document.getElementById(btn.getAttribute('aria-controls'));
    var open = box.classList.toggle('is-open');
    btn.setAttribute('aria-expanded', open ? 'true' : 'false');
    btn.textContent = open ? btn.dataset.less : btn.dataset.more;
  });
  window.addEventListener('load', check);
  window.addEventListener('resize', check);
  check();
})();
