'use strict';
(() => {
  const data = window.matrixDzTakeActions;
  if (!data || !Array.isArray(data.actions)) {
    return;
  }

  const { actions } = data;
  const pageSize = Math.max(1, Number(data.pageSize) || 9);

  const params = new URLSearchParams(location.search);
  const state = {
    query: params.get('q') || '',
    sort: ['newest', 'oldest', 'az'].includes(params.get('sort') || '')
      ? params.get('sort')
      : 'az',
    view: params.get('view') === 'list' ? 'list' : 'cards',
    page: Math.max(1, Math.floor(Number(params.get('page'))) || 1),
  };

  const search = document.querySelector('#search-actions');
  const sort = document.querySelector('#action-sort');
  const results = document.querySelector('#action-results');
  const count = document.querySelector('#result-count');
  const empty = document.querySelector('#empty-results');
  const clear = document.querySelector('#clear-search');
  const pagination = document.querySelector('#action-pagination');
  const form = document.querySelector('#action-search');

  if (!search || !sort || !results || !count || !empty || !clear || !pagination || !form) {
    return;
  }

  const esc = (s) =>
    String(s).replace(/[&<>"']/g, (c) => ({
      '&': '&amp;',
      '<': '&lt;',
      '>': '&gt;',
      '"': '&quot;',
      "'": '&#39;',
    }[c]));

  function render() {
    search.value = state.query;
    sort.value = state.sort;
    clear.hidden = !state.query;

    document.querySelectorAll('[data-view]').forEach((b) => {
      b.setAttribute('aria-pressed', String(b.dataset.view === state.view));
    });

    const terms = state.query
      .trim()
      .toLocaleLowerCase()
      .split(/\s+/)
      .filter(Boolean);

    const found = actions.filter((a) =>
      terms.every(
        (t) =>
          String(a.title).toLocaleLowerCase().includes(t) ||
          String(a.theme).toLocaleLowerCase().includes(t) ||
          String(a.description).toLocaleLowerCase().includes(t)
      )
    );

    found.sort((a, b) => {
      if (state.sort === 'newest') {
        return b.date - a.date;
      }
      if (state.sort === 'oldest') {
        return a.date - b.date;
      }
      return a.title.localeCompare(b.title, 'en');
    });

    const pages = Math.max(1, Math.ceil(found.length / pageSize));
    state.page = Math.min(state.page, pages);

    const next = new URLSearchParams();
    if (state.query) next.set('q', state.query);
    if (state.sort !== 'az') next.set('sort', state.sort);
    if (state.view !== 'cards') next.set('view', state.view);
    if (state.page > 1) next.set('page', String(state.page));
    history.replaceState(null, '', location.pathname + (next.size ? `?${next}` : '') + location.hash);

    count.textContent = `${found.length} ${found.length === 1 ? 'action' : 'actions'}`;
    results.classList.toggle('list-view', state.view === 'list');
    results.hidden = !found.length;
    empty.hidden = !!found.length;

    const emptyTitle = document.querySelector('#empty-title');
    if (emptyTitle) {
      emptyTitle.textContent = state.query.trim()
        ? 'No actions match your search'
        : 'No actions published yet';
    }

    const slice = found.slice((state.page - 1) * pageSize, state.page * pageSize);
    results.innerHTML = slice
      .map((a) => {
        const returnQs = encodeURIComponent(location.search);
        const href = `${esc(a.url)}${a.url.includes('?') ? '&' : '?'}return=${returnQs}`;
        return `<article class="directory-card">
          ${a.image ? `<img class="directory-photo" src="${esc(a.image)}" alt="" loading="lazy" width="600" height="400">` : `<div class="directory-photo directory-photo-placeholder" style="--category:${esc(a.themeColor)}" aria-hidden="true"></div>`}
          <div class="directory-card-content">
            <div class="card-labels">
              <span class="directory-category" style="--category:${esc(a.themeColor)}">
                ${a.themeIcon ? `<img src="${esc(a.themeIcon)}" alt="">` : ''}${esc(a.theme)}
              </span>
              <span class="status">${esc('Pathway')}</span>
            </div>
            <h2><a class="project-link" href="${href}">${esc(a.title)}</a></h2>
            <p>${esc(a.description)}</p>
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

  const reset = document.querySelector('#reset-search');
  if (reset) {
    reset.addEventListener('click', () => {
      clearTimeout(debounce);
      state.query = '';
      state.page = 1;
      render();
      search.focus();
    });
  }

  render();
})();
