/* The Ex Files: subtle motion (GSAP + ScrollTrigger, bundled in assets/js/vendor/gsap).
 *
 * - Homepage / Sections pages: the hero text eases in on load and the other
 *   sections' content fades up as it scrolls into view.
 * - window.exfReveal(el): eases in content that a gate question has just shown
 *   (questionnaire.js) or the next step of the free guide (guide.js).
 *
 * A tiny inline script in <head> (inc/setup.php) adds .exf-motion to <html> so
 * the CSS can hide reveal targets before first paint. It's skipped for visitors
 * who prefer reduced motion, and removed after a few seconds if this file never
 * runs, so content can't get stuck hidden.
 */
(function () {
  'use strict';

  var root = document.documentElement;
  var reduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var gsap = window.gsap;

  /* ----- Reveal for content shown after a page has loaded ----- */
  function reveal(el, opts) {
    if (!el || reduced || !gsap) { return; }
    opts = opts || {};
    gsap.killTweensOf(el);
    if (opts.height === false) {
      gsap.fromTo(el, { autoAlpha: 0, y: 10 }, { autoAlpha: 1, y: 0, duration: 0.4, ease: 'power2.out', clearProps: 'opacity,visibility,transform' });
      return;
    }
    // Grow from nothing so the content below slides down rather than jumping.
    var cs = window.getComputedStyle(el);
    gsap.fromTo(el,
      { height: 0, opacity: 0, marginTop: 0, paddingTop: 0, paddingBottom: 0, overflow: 'hidden' },
      {
        height: 'auto', opacity: 1,
        marginTop: cs.marginTop, paddingTop: cs.paddingTop, paddingBottom: cs.paddingBottom,
        duration: 0.45, ease: 'power2.out',
        clearProps: 'height,opacity,marginTop,paddingTop,paddingBottom,overflow'
      });
  }
  // Called when a gate hides the content again: stop any reveal part-way through.
  reveal.reset = function (el) {
    if (!el || !gsap) { return; }
    gsap.killTweensOf(el);
    gsap.set(el, { clearProps: 'height,opacity,visibility,transform,marginTop,paddingTop,paddingBottom,overflow' });
  };
  window.exfReveal = reveal;

  /* ----- Page sections ----- */
  window.exfMotionReady = true;
  if (!root.classList.contains('exf-motion')) { return; }
  if (reduced || !gsap || !window.ScrollTrigger) {
    root.classList.remove('exf-motion');
    return;
  }
  gsap.registerPlugin(window.ScrollTrigger);

  var SECTIONS = '.services, .howitworks, .trust, .guide, .contact';
  var GROUPS = '.cards, .steps, .trust-items';

  // Hero text: a gentle staggered fade on load.
  var hero = document.querySelectorAll('.hero .wrap > *');
  if (hero.length) {
    gsap.fromTo(hero, { opacity: 0, y: 16 }, { opacity: 1, y: 0, duration: 0.9, ease: 'power2.out', stagger: 0.12, delay: 0.1 });
  }

  // Everything else: headings, text, cards, steps and trust items fade up as they arrive.
  var targets = [];
  document.querySelectorAll(SECTIONS).forEach(function (section) {
    section.querySelectorAll('.wrap > *').forEach(function (el) {
      if (el.matches(GROUPS)) {
        Array.prototype.push.apply(targets, el.children);
      } else if (!el.closest(GROUPS)) {
        targets.push(el);
      }
    });
  });

  if (targets.length) {
    gsap.set(targets, { opacity: 0, y: 24 });
    window.ScrollTrigger.batch(targets, {
      start: 'top 90%',
      once: true,
      onEnter: function (batch) {
        gsap.to(batch, { opacity: 1, y: 0, duration: 0.8, ease: 'power2.out', stagger: 0.1, overwrite: true });
      }
    });
    // Anything already scrolled past (a reload part-way down, or a #link) just shows.
    window.ScrollTrigger.refresh();
    targets.forEach(function (el) {
      if (el.getBoundingClientRect().bottom < 0) { gsap.set(el, { opacity: 1, y: 0 }); }
    });
  }

  // The CSS pre-hide has done its job; GSAP owns these styles now.
  root.classList.remove('exf-motion');
})();
