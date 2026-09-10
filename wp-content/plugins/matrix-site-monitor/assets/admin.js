(function () {
	'use strict';

	function activateTab(tab) {
		var panels = document.querySelectorAll('.msm-panel');
		var links = document.querySelectorAll('.msm-tabs .nav-tab');
		panels.forEach(function (panel) {
			panel.classList.toggle('is-active', panel.getAttribute('data-panel') === tab);
		});
		links.forEach(function (link) {
			link.classList.toggle('nav-tab-active', link.getAttribute('data-tab') === tab);
		});
		try {
			var url = new URL(window.location.href);
			url.searchParams.set('tab', tab);
			window.history.replaceState({}, '', url.toString());
		} catch (e) {
			/* ignore */
		}
	}

	document.querySelectorAll('.msm-tabs .nav-tab').forEach(function (link) {
		link.addEventListener('click', function (e) {
			e.preventDefault();
			activateTab(link.getAttribute('data-tab') || 'overview');
		});
	});

	document.querySelectorAll('.msm-js-tab, .msm-card--clickable').forEach(function (el) {
		el.addEventListener('click', function (e) {
			var tab = el.getAttribute('data-tab') || el.getAttribute('data-tab-link');
			if (!tab) {
				return;
			}
			e.preventDefault();
			activateTab(tab);
		});
		el.addEventListener('keydown', function (e) {
			if (e.key !== 'Enter' && e.key !== ' ') {
				return;
			}
			var tab = el.getAttribute('data-tab') || el.getAttribute('data-tab-link');
			if (!tab) {
				return;
			}
			e.preventDefault();
			activateTab(tab);
		});
	});

	document.querySelectorAll('.msm-section__head').forEach(function (btn) {
		btn.addEventListener('click', function () {
			var section = btn.closest('.msm-section');
			if (section) {
				section.classList.toggle('is-open');
			}
		});
	});

	document.querySelectorAll('.msm-coverage-toggle').forEach(function (btn) {
		btn.addEventListener('click', function (e) {
			e.preventDefault();
			var target = document.getElementById(btn.getAttribute('aria-controls') || '');
			if (!target) {
				return;
			}
			var open = target.classList.toggle('is-open');
			btn.setAttribute('aria-expanded', open ? 'true' : 'false');
			btn.textContent = open
				? btn.getAttribute('data-label-hide') || 'Hide details'
				: btn.getAttribute('data-label-show') || 'Show details';
		});
	});

	var selectAll = document.getElementById('msm-select-all-checks');
	var deselectAll = document.getElementById('msm-deselect-all-checks');
	function setAll(checked) {
		document.querySelectorAll('#msm-enabled-checks .msm-check-toggle').forEach(function (el) {
			el.checked = checked;
		});
	}
	if (selectAll) {
		selectAll.addEventListener('click', function (e) {
			e.preventDefault();
			setAll(true);
		});
	}
	if (deselectAll) {
		deselectAll.addEventListener('click', function (e) {
			e.preventDefault();
			setAll(false);
		});
	}

	var filter = document.getElementById('msm-results-filter');
	if (filter) {
		filter.addEventListener('input', function () {
			var q = (filter.value || '').toLowerCase().trim();
			document.querySelectorAll('.msm-section').forEach(function (section) {
				var rows = section.querySelectorAll('tbody tr[data-check]');
				var visible = 0;
				rows.forEach(function (row) {
					var hay = (row.getAttribute('data-search') || '').toLowerCase();
					var show = !q || hay.indexOf(q) !== -1;
					row.style.display = show ? '' : 'none';
					if (show) {
						visible++;
					}
				});
				if (q && visible > 0) {
					section.classList.add('is-open');
				}
				section.style.display = !q || visible > 0 ? '' : 'none';
			});
		});
	}

	var failOnly = document.getElementById('msm-fail-only');
	if (failOnly) {
		failOnly.addEventListener('change', function () {
			var on = failOnly.checked;
			document.querySelectorAll('.msm-section').forEach(function (section) {
				var rows = section.querySelectorAll('tbody tr[data-check]');
				var visible = 0;
				rows.forEach(function (row) {
					var isFail = row.classList.contains('is-fail');
					var show = !on || isFail;
					if (row.style.display === 'none' && filter && filter.value) {
						return;
					}
					row.style.display = show ? '' : 'none';
					if (show) {
						visible++;
					}
				});
				if (on && visible > 0) {
					section.classList.add('is-open');
				}
				if (on) {
					section.style.display = visible > 0 ? '' : 'none';
				} else if (!filter || !filter.value) {
					section.style.display = '';
				}
			});
		});
	}
})();
