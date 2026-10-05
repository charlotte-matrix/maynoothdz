'use strict';
(() => {
  const root = document.querySelector('.take-action-pathway');
  if (!root) {
    return;
  }

  const tabs = [...root.querySelectorAll('.pathway-tabs [data-step]')];
  const accordions = [...root.querySelectorAll('.accordion-heading [data-step]')];
  const panels = [...root.querySelectorAll('.step-panel')];
  const sections = [...root.querySelectorAll('.pathway-step')];
  const total = tabs.length;
  if (!total) {
    return;
  }

  const mobile = matchMedia('(max-width:800px)');
  const n = Number(new URLSearchParams(location.search).get('step'));
  let active = Number.isInteger(n) && n >= 1 && n <= total ? n : 1;
  let expanded = true;

  function update(focus = false) {
    tabs.forEach((b, i) => {
      b.setAttribute('aria-selected', String(i + 1 === active));
      b.tabIndex = i + 1 === active ? 0 : -1;
    });
    accordions.forEach((b, i) => {
      b.setAttribute('aria-expanded', String(i + 1 === active && expanded));
    });
    panels.forEach((p, i) => {
      p.hidden = i + 1 !== active || (mobile.matches && !expanded);
      p.setAttribute('role', mobile.matches ? 'region' : 'tabpanel');
      const tabId = tabs[i] ? tabs[i].id : '';
      const accId = accordions[i] ? accordions[i].id : '';
      p.setAttribute('aria-labelledby', mobile.matches ? accId : tabId);
    });
    sections.forEach((s, i) => {
      s.classList.toggle('is-open', i + 1 === active && expanded);
    });

    const q = new URLSearchParams(location.search);
    if (active === 1) q.delete('step');
    else q.set('step', String(active));
    history.replaceState(null, '', location.pathname + (q.size ? `?${q}` : '') + location.hash);

    if (focus) {
      const b = (mobile.matches ? accordions : tabs)[active - 1];
      if (!b) return;
      b.focus({ preventScroll: true });
      if (mobile.matches) {
        b.scrollIntoView({
          behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'instant' : 'smooth',
          block: 'nearest',
        });
      }
    }
  }

  tabs.forEach((b, i) => {
    b.addEventListener('click', () => {
      active = i + 1;
      expanded = true;
      update();
    });
    b.addEventListener('keydown', (e) => {
      let j = i;
      if (e.key === 'ArrowRight') j = (i + 1) % total;
      else if (e.key === 'ArrowLeft') j = (i + total - 1) % total;
      else if (e.key === 'Home') j = 0;
      else if (e.key === 'End') j = total - 1;
      else return;
      e.preventDefault();
      active = j + 1;
      expanded = true;
      update(true);
    });
  });

  accordions.forEach((b, i) => {
    b.addEventListener('click', () => {
      expanded = active === i + 1 ? !expanded : true;
      active = i + 1;
      update();
    });
  });

  root.querySelectorAll('[data-go]').forEach((b) => {
    b.addEventListener('click', () => {
      active = Number(b.dataset.go) || 1;
      expanded = true;
      update(true);
    });
  });

  mobile.addEventListener('change', () => {
    expanded = true;
    update();
  });

  update();
})();
