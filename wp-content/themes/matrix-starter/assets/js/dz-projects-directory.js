'use strict';
(() => {
  const data = window.matrixDzProjects;
  if (!data || !Array.isArray(data.projects)) {
    return;
  }

  const { categories, categoryIcons, projects } = data;
  const pageSize = Math.max(1, Number(data.pageSize) || 9);
  const mapUrl = data.mapUrl || '/map/';

  const params = new URLSearchParams(location.search);
  const state = {
    query: params.get('q') || '',
    category: Object.prototype.hasOwnProperty.call(categories, params.get('category') || '')
      ? params.get('category')
      : 'All projects',
    sort: ['newest', 'oldest', 'az'].includes(params.get('sort') || '')
      ? params.get('sort')
      : 'newest',
    view: params.get('view') === 'list' ? 'list' : 'cards',
    page: Math.max(1, Math.floor(Number(params.get('page'))) || 1),
  };

  const search = document.querySelector('#search-projects');
  const sort = document.querySelector('#project-sort');
  const results = document.querySelector('#project-results');
  const count = document.querySelector('#result-count');
  const empty = document.querySelector('#empty-results');
  const clear = document.querySelector('#clear-search');
  const filters = document.querySelector('#category-filters');
  const toggle = document.querySelector('#filter-toggle');
  const pagination = document.querySelector('#project-pagination');
  const mapLink = document.querySelector('#empty-map-link');
  const form = document.querySelector('#project-search');

  if (!search || !sort || !results || !count || !empty || !clear || !filters || !toggle || !pagination || !form) {
    return;
  }

  if (mapLink && mapUrl) {
    mapLink.setAttribute('href', mapUrl);
  }

  const esc = (s) =>
    String(s).replace(/[&<>"']/g, (c) => ({
      '&': '&amp;',
      '<': '&lt;',
      '>': '&gt;',
      '"': '&quot;',
      "'": '&#39;',
    }[c]));

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

  Object.entries(categories).forEach(([category, color]) => {
    const b = document.createElement('button');
    b.type = 'button';
    b.className = 'category-filter';
    const icon = categoryIcons[category] || '';
    b.innerHTML = `${icon ? `<img src="${esc(icon)}" alt="">` : ''}<span>${esc(category)}</span>`;
    b.style.setProperty('--category', color);
    b.setAttribute('aria-pressed', String(state.category === category));
    b.addEventListener('click', () => {
      state.category = category;
      state.page = 1;
      render();
    });
    filters.append(b);
  });

  function render() {
    search.value = state.query;
    sort.value = state.sort;
    clear.hidden = !state.query;

    filters.querySelectorAll('button').forEach((b) => {
      const label = b.querySelector('span');
      b.setAttribute('aria-pressed', String((label ? label.textContent : b.textContent) === state.category));
    });

    document.querySelectorAll('[data-view]').forEach((b) => {
      b.setAttribute('aria-pressed', String(b.dataset.view === state.view));
    });

    const terms = state.query
      .trim()
      .toLocaleLowerCase()
      .split(/\s+/)
      .filter(Boolean);

    const found = projects.filter(
      (p) =>
        (state.category === 'All projects' || p.category === state.category) &&
        terms.every((t) => String(p.title).toLocaleLowerCase().includes(t))
    );

    found.sort((a, b) => {
      if (state.sort === 'az') {
        return a.title.localeCompare(b.title, 'en');
      }
      if (state.sort === 'oldest') {
        return a.date - b.date;
      }
      return b.date - a.date;
    });

    const pages = Math.max(1, Math.ceil(found.length / pageSize));
    state.page = Math.min(state.page, pages);

    const next = new URLSearchParams();
    if (state.query) next.set('q', state.query);
    if (state.category !== 'All projects') next.set('category', state.category);
    if (state.sort !== 'newest') next.set('sort', state.sort);
    if (state.view !== 'cards') next.set('view', state.view);
    if (state.page > 1) next.set('page', String(state.page));
    history.replaceState(null, '', location.pathname + (next.size ? `?${next}` : '') + location.hash);

    const filterCount = document.querySelector('#active-filter-count');
    if (filterCount) {
      filterCount.hidden = state.category === 'All projects';
    }
    toggle.setAttribute(
      'aria-label',
      state.category === 'All projects'
        ? 'Filter by category'
        : `Filter by category: ${state.category}`
    );

    count.textContent = `${found.length} ${found.length === 1 ? 'project' : 'projects'}`;
    results.classList.toggle('list-view', state.view === 'list');
    results.hidden = !found.length;
    empty.hidden = !!found.length;

    const emptyTitle = document.querySelector('#empty-title');
    if (emptyTitle) {
      emptyTitle.textContent = state.query.trim()
        ? 'No projects match your search'
        : `No ${state.category === 'All projects' ? '' : state.category.toLowerCase() + ' '}projects yet`;
    }

    const slice = found.slice((state.page - 1) * pageSize, state.page * pageSize);
    results.innerHTML = slice
      .map((p) => {
        const color = categories[p.category] || '#203129';
        const icon = categoryIcons[p.category] || '';
        const statusClass = String(p.status || '')
          .toLowerCase()
          .replace(/\s+/g, '-');
        const returnQs = encodeURIComponent(location.search);
        const href = `${esc(p.url)}${p.url.includes('?') ? '&' : '?'}return=${returnQs}`;
        return `<article class="directory-card">
          ${p.image ? `<img class="directory-photo" src="${esc(p.image)}" alt="" loading="lazy" width="600" height="400">` : ''}
          <div class="directory-card-content">
            <div class="card-labels">
              <span class="directory-category" style="--category:${esc(color)}">
                ${icon ? `<img src="${esc(icon)}" alt="">` : ''}${esc(p.category)}
              </span>
              <span class="status ${esc(statusClass)}">${esc(p.status)}</span>
            </div>
            <h2><a class="project-link" href="${href}">${esc(p.title)}</a></h2>
            <p>${esc(p.description)}</p>
          </div>
        </article>`;
      })
      .join('');

    pagination.hidden = pages <= 1;
    pagination.replaceChildren();

    const addPage = (label, page, disabled = false, current = false) => {
      const b = document.createElement('button');
      b.type = 'button';
      b.textContent = label;
      b.disabled = disabled;
      if (current) b.setAttribute('aria-current', 'page');
      b.setAttribute('aria-label', /^\d+$/.test(label) ? `Page ${label}` : `${label} page`);
      b.addEventListener('click', () => {
        state.page = page;
        render();
        const currentBtn = pagination.querySelector('[aria-current="page"]');
        if (currentBtn) currentBtn.focus({ preventScroll: true });
        results.scrollIntoView({
          behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'instant' : 'smooth',
          block: 'start',
        });
      });
      pagination.append(b);
    };

    addPage('Previous', state.page - 1, state.page === 1);
    for (let i = 1; i <= pages; i += 1) {
      addPage(String(i), i, false, state.page === i);
    }
    addPage('Next', state.page + 1, state.page === pages);
  }

  let debounce;
  search.addEventListener('input', () => {
    clearTimeout(debounce);
    state.query = search.value;
    state.page = 1;
    debounce = setTimeout(render, 180);
  });

  form.addEventListener('submit', (e) => {
    e.preventDefault();
    clearTimeout(debounce);
    state.query = search.value;
    state.page = 1;
    render();
  });

  clear.addEventListener('click', () => {
    clearTimeout(debounce);
    state.query = '';
    state.page = 1;
    render();
    search.focus();
  });

  sort.addEventListener('change', () => {
    state.sort = sort.value;
    state.page = 1;
    render();
  });

  document.querySelectorAll('[data-view]').forEach((b) => {
    b.addEventListener('click', () => {
      state.view = b.dataset.view;
      render();
    });
  });

  const reset = document.querySelector('#reset-filters');
  if (reset) {
    reset.addEventListener('click', () => {
      clearTimeout(debounce);
      state.query = '';
      state.page = 1;
      state.category = 'All projects';
      render();
      search.focus();
    });
  }

  render();
})();
