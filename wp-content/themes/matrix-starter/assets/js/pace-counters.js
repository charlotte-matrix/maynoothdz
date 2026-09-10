/**
 * PACE counters — count up when scrolled into view.
 */
(function () {
  function formatValue(current, format, target) {
    const value = Math.max(0, Math.round(current));
    if (format === 'compact' && target >= 1000) {
      return String(Math.round(value / 1000)) + 'K';
    }
    return value.toLocaleString();
  }

  function animateCounter(span, target, format, duration) {
    if (target <= 0) {
      span.textContent = formatValue(0, format, target);
      return;
    }

    const start = performance.now();

    function tick(now) {
      const progress = Math.min(1, (now - start) / duration);
      const current = Math.round(target * progress);
      span.textContent = formatValue(current, format, target);
      if (progress < 1) {
        requestAnimationFrame(tick);
      } else {
        span.textContent = formatValue(target, format, target);
      }
    }

    span.textContent = formatValue(0, format, target);
    requestAnimationFrame(tick);
  }

  function initSection(section) {
    if (section.dataset.paceCountersReady === '1') {
      return;
    }
    section.dataset.paceCountersReady = '1';

    const cards = section.querySelectorAll('[data-pace-counter-card]');
    if (!cards.length) {
      return;
    }

    const observer = new IntersectionObserver(
      (entries) => {
        entries.forEach((entry) => {
          if (!entry.isIntersecting) {
            return;
          }
          const card = entry.target;
          if (card.dataset.paceCounterDone === '1') {
            return;
          }
          card.dataset.paceCounterDone = '1';

          const span = card.querySelector('.pace-counter-value');
          if (!span) {
            return;
          }

          const target = parseInt(span.dataset.target || '0', 10) || 0;
          const format = span.dataset.format || 'number';
          animateCounter(span, target, format, 1400);
          observer.unobserve(card);
        });
      },
      { threshold: 0.25, rootMargin: '0px 0px -5% 0px' }
    );

    cards.forEach((card) => {
      const span = card.querySelector('.pace-counter-value');
      if (span) {
        const target = parseInt(span.dataset.target || '0', 10) || 0;
        const format = span.dataset.format || 'number';
        span.textContent = formatValue(0, format, target);
      }
      observer.observe(card);
    });
  }

  function boot() {
    document.querySelectorAll('.pace-counters-001').forEach(initSection);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})();
