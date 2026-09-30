/**
 * Maynooth DZ flexi — reveal + stats count-up.
 */
(function () {
  'use strict';

  document.documentElement.classList.add('js');

  var reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');

  function countUp(element) {
    if (element.dataset.counted) {
      return;
    }
    element.dataset.counted = 'true';
    if (reducedMotion.matches) {
      return;
    }

    var end = Number(element.dataset.count);
    var decimals = Number(element.dataset.decimals || 0);
    var suffix = element.dataset.suffix || '';
    var start = performance.now();

    function frame(now) {
      var t = Math.min(1, (now - start) / 1450);
      var value = end * (1 - Math.pow(1 - t, 3));
      element.textContent =
        value.toLocaleString('en-IE', {
          minimumFractionDigits: decimals,
          maximumFractionDigits: decimals,
        }) + suffix;
      if (t < 1) {
        requestAnimationFrame(frame);
      }
    }

    requestAnimationFrame(frame);
  }

  function initReveals() {
    var reveals = document.querySelectorAll('.dz-flexi .reveal, .dz-home .reveal');
    if (!reveals.length) {
      return;
    }

    if (!('IntersectionObserver' in window)) {
      reveals.forEach(function (el) {
        el.classList.add('visible');
      });
      return;
    }

    var observer = new IntersectionObserver(
      function (entries) {
        entries.forEach(function (entry) {
          if (!entry.isIntersecting) {
            return;
          }
          entry.target.classList.add('visible');
          entry.target.querySelectorAll('[data-count]').forEach(countUp);
          observer.unobserve(entry.target);
        });
      },
      { threshold: 0.12 }
    );

    reveals.forEach(function (el) {
      observer.observe(el);
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initReveals);
  } else {
    initReveals();
  }
})();
