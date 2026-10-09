'use strict';
(() => {
  const data = window.matrixDzMap;
  if (!data || !data.projects) {
    return;
  }

  const { categories, categoryIcons } = data;
  const projects = data.projects.filter((p) => p.showMap !== false && p.location != null);
  const aspect = Number(data.mapAspect) > 0 ? Number(data.mapAspect) : 5063 / 2848;

  const surface = document.querySelector('#map-surface');
  const stage = document.querySelector('#map-stage');
  const pins = document.querySelector('#project-pins');
  const panel = document.querySelector('#map-project-card');
  const filters = document.querySelector('#map-category-filters');
  const counter = document.querySelector('#map-count');
  const toggle = document.querySelector('#map-filter-toggle');
  if (!surface || !stage || !pins || !panel || !filters || !counter || !toggle) {
    return;
  }

  const params = new URLSearchParams(location.search);
  let category = Object.prototype.hasOwnProperty.call(categories, params.get('category'))
    ? params.get('category')
    : 'All projects';
  let selected = projects.find((p) => p.id === params.get('project')) || null;
  if (selected) {
    category = 'All projects';
  }

  const esc = (s) =>
    String(s).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

  toggle.addEventListener('click', () => {
    const open = toggle.getAttribute('aria-expanded') !== 'true';
    toggle.setAttribute('aria-expanded', String(open));
    filters.hidden = !open;
  });
  filters.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
      filters.hidden = true;
      toggle.setAttribute('aria-expanded', 'false');
      toggle.focus();
    }
  });

  for (const [c, color] of Object.entries(categories)) {
    const b = document.createElement('button');
    b.type = 'button';
    b.className = 'category-filter';
    b.dataset.category = c;
    b.style.setProperty('--category', color);
    const icon = categoryIcons[c] || '';
    b.innerHTML = `${icon ? `<img src="${esc(icon)}" alt="">` : ''}<span>${esc(c)}</span>`;
    b.addEventListener('click', () => {
      category = c;
      close();
      draw();
      url();
      updateFilters();
    });
    filters.append(b);
  }

  function updateFilters() {
    filters.querySelectorAll('button').forEach((b) =>
      b.setAttribute('aria-pressed', String(b.dataset.category === category))
    );
    const badge = document.querySelector('.map-active-filter');
    if (badge) {
      badge.hidden = category === 'All projects';
    }
    toggle.setAttribute(
      'aria-label',
      category === 'All projects' ? 'Filter by category' : `Filter by category: ${category}`
    );
  }
  updateFilters();

  const legend = document.querySelector('#map-legend');
  if (legend) {
    legend.innerHTML = Object.entries(categories)
      .filter(([name]) => name !== 'All projects')
      .map(([name, color]) => {
        const icon = categoryIcons[name] || '';
        return `<span><i style="--category:${esc(color)}">${
          icon ? `<img src="${esc(icon)}" alt="" loading="lazy">` : ''
        }</i>${esc(name)}</span>`;
      })
      .join('');
  }

  let z = 1;
  let x = 0;
  let y = 0;
  let w = 0;
  let h = 0;
  let vw = 0;
  let vh = 0;
  let previousView = null;
  let restoringFocus = false;

  function paint() {
    x = w * z <= vw ? (vw - w * z) / 2 : Math.min(0, Math.max(vw - w * z, x));
    y = h * z <= vh ? (vh - h * z) / 2 : Math.min(0, Math.max(vh - h * z, y));
    stage.style.transform = `translate(${x}px,${y}px) scale(${z})`;
    // Keep pin visual size constant across zoom / viewport (responsive).
    stage.style.setProperty('--pin-scale', String(1 / z));
    const zoomOut = document.querySelector('#map-zoom-out');
    const zoomIn = document.querySelector('#map-zoom-in');
    const zoomLevel = document.querySelector('#map-zoom-level');
    if (zoomLevel) {
      zoomLevel.value = Math.round(z * 100) + '%';
    }
    if (zoomOut) {
      zoomOut.disabled = z <= 1;
    }
    if (zoomIn) {
      zoomIn.disabled = z >= 4;
    }
  }

  function center(p, zoom) {
    z = zoom || z;
    const mobile = vw <= 800;
    x = vw * (mobile ? 0.5 : 0.4) - (w * z * p.location.x) / 100;
    y = vh * (mobile ? 0.35 : 0.5) - (h * z * p.location.y) / 100;
    paint();
  }

  function resize() {
    vw = surface.clientWidth;
    vh = surface.clientHeight;
    w = Math.max(vw, vh * aspect);
    h = w / aspect;
    stage.style.width = w + 'px';
    stage.style.height = h + 'px';
    if (selected) {
      center(selected, z);
    } else {
      x = (vw - w * z) / 2;
      y = (vh - h * z) / 2;
      paint();
    }
  }

  function url() {
    const q = new URLSearchParams();
    if (category !== 'All projects') {
      q.set('category', category);
    }
    if (selected) {
      q.set('project', selected.id);
    }
    history.replaceState(null, '', location.pathname + (q.size ? '?' + q : ''));
  }

  function show(p, move = false) {
    if (!selected) {
      previousView = { z, x, y };
    }
    selected = p;
    pins.querySelectorAll('button').forEach((b) =>
      b.setAttribute('aria-pressed', String(b.dataset.id === p.id))
    );
    panel.hidden = false;
    const color = categories[p.category] || '#203129';
    const icon = categoryIcons[p.category] || '';
    panel.innerHTML = `<button type="button" class="map-card-close" aria-label="Close project preview">×</button>${
      p.image ? `<img class="map-card-photo" src="${esc(p.image)}" alt="">` : ''
    }<div class="map-card-content"><div class="card-labels"><span class="directory-category" style="--category:${esc(
      color
    )}">${icon ? `<img src="${esc(icon)}" alt="">` : ''}${esc(p.category)}</span><span class="status">${esc(
      p.status
    )}</span></div><h2>${esc(p.title)}</h2><p>${esc(p.description || '')}</p><a class="button" href="${esc(
      p.url
    )}">View project →</a></div>`;
    panel.querySelector('button')?.addEventListener('click', () => close(true));
    counter.textContent = `${visible().length} projects · Selected: ${p.title}`;
    if (move) {
      center(p, Math.max(z, 1.5));
    }
    url();
  }

  function close(focus = false) {
    const id = selected?.id;
    selected = null;
    panel.hidden = true;
    pins.querySelectorAll('button').forEach((b) => b.setAttribute('aria-pressed', 'false'));
    counter.textContent = `Showing ${visible().length} projects`;
    url();
    if (previousView) {
      ({ z, x, y } = previousView);
      previousView = null;
      paint();
    }
    if (focus && id) {
      restoringFocus = true;
      pins.querySelector(`[data-id="${id}"]`)?.focus({ preventScroll: true });
      restoringFocus = false;
    }
  }

  function visible() {
    return projects.filter((p) => category === 'All projects' || p.category === category);
  }

  function draw() {
    pins.innerHTML = '';
    for (const p of visible()) {
      const b = document.createElement('button');
      b.type = 'button';
      b.className = 'map-pin';
      b.dataset.id = p.id;
      const color = categories[p.category] || '#203129';
      const icon = categoryIcons[p.category] || '';
      b.style.cssText = `left:${p.location.x}%;top:${p.location.y}%;--category:${color};--float-delay:-${
        projects.indexOf(p) * 0.47
      }s`;
      b.setAttribute('aria-label', `${p.title}, ${p.category}, ${p.status}`);
      b.setAttribute('aria-pressed', String(selected?.id === p.id));
      b.innerHTML = `<span class="map-pin-face"><svg class="map-pin-shape" viewBox="0 0 56 72" aria-hidden="true"><path d="M28 2C13.6 2 2 13.6 2 28c0 18 19 35 26 42 7-7 26-24 26-42C54 13.6 42.4 2 28 2Z"/></svg>${
        icon ? `<img src="${esc(icon)}" alt="">` : ''
      }</span>`;
      b.title = p.title;
      b.addEventListener('click', () => show(p, true));
      b.addEventListener('focus', () => {
        if (restoringFocus) {
          return;
        }
        const sx = x + (w * z * p.location.x) / 100;
        const sy = y + (h * z * p.location.y) / 100;
        if (sx < 30 || sx > vw - 30 || sy < 30 || sy > vh - 30) {
          center(p);
        }
      });
      pins.append(b);
    }
    counter.textContent = `Showing ${visible().length} projects`;
  }

  function zoom(next, cx = vw / 2, cy = vh / 2) {
    next = Math.max(1, Math.min(4, next));
    const ratio = next / z;
    x = cx - (cx - x) * ratio;
    y = cy - (cy - y) * ratio;
    z = next;
    paint();
  }

  document.querySelector('#map-zoom-in')?.addEventListener('click', () => zoom(z * 1.3));
  document.querySelector('#map-zoom-out')?.addEventListener('click', () => zoom(z / 1.3));
  document.querySelector('#map-reset')?.addEventListener('click', () => {
    z = 1;
    x = (vw - w) / 2;
    y = (vh - h) / 2;
    paint();
  });

  const points = new Map();
  let pinch = null;
  let drag = null;
  surface.addEventListener('pointerdown', (e) => {
    if (e.target.closest('button') || e.button > 0) {
      return;
    }
    points.set(e.pointerId, { x: e.clientX, y: e.clientY });
    surface.setPointerCapture(e.pointerId);
    surface.classList.add('is-dragging');
    if (points.size === 1) {
      drag = { x: e.clientX, y: e.clientY };
    }
    if (points.size === 2) {
      const [a, b] = [...points.values()];
      pinch = { distance: Math.hypot(a.x - b.x, a.y - b.y), z };
    }
  });
  surface.addEventListener('pointermove', (e) => {
    if (!points.has(e.pointerId)) {
      return;
    }
    points.set(e.pointerId, { x: e.clientX, y: e.clientY });
    if (points.size === 2 && pinch) {
      const [a, b] = [...points.values()];
      const rect = surface.getBoundingClientRect();
      zoom(
        (pinch.z * Math.hypot(a.x - b.x, a.y - b.y)) / pinch.distance,
        (a.x + b.x) / 2 - rect.left,
        (a.y + b.y) / 2 - rect.top
      );
    } else if (drag) {
      x += e.clientX - drag.x;
      y += e.clientY - drag.y;
      drag = { x: e.clientX, y: e.clientY };
      paint();
    }
  });
  function end(e) {
    points.delete(e.pointerId);
    pinch = null;
    drag = points.size === 1 ? [...points.values()][0] : null;
    if (!points.size) {
      surface.classList.remove('is-dragging');
    }
  }
  surface.addEventListener('pointerup', end);
  surface.addEventListener('pointercancel', end);
  surface.addEventListener(
    'wheel',
    (e) => {
      if (!e.ctrlKey) {
        return;
      }
      e.preventDefault();
      const r = surface.getBoundingClientRect();
      zoom(z * Math.exp(-e.deltaY * 0.005), e.clientX - r.left, e.clientY - r.top);
    },
    { passive: false }
  );
  surface.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
      close(true);
      return;
    }
    if (e.target !== surface) {
      return;
    }
    if (['ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown', '+', '=', '-'].includes(e.key)) {
      e.preventDefault();
      if (e.key === 'ArrowLeft') {
        x += 80;
      }
      if (e.key === 'ArrowRight') {
        x -= 80;
      }
      if (e.key === 'ArrowUp') {
        y += 80;
      }
      if (e.key === 'ArrowDown') {
        y -= 80;
      }
      if (e.key === '+' || e.key === '=') {
        zoom(z * 1.3);
      }
      if (e.key === '-') {
        zoom(z / 1.3);
      }
      paint();
    }
  });
  panel.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
      close(true);
    }
  });

  const initialProject = selected;
  selected = null;
  draw();
  resize();
  new ResizeObserver(resize).observe(surface);
  if (initialProject) {
    show(initialProject);
    center(initialProject, 2.5);
  }
})();
