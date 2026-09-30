/**
 * Front-end: tooltip + click navigation for linked regions only.
 */
(function () {
	'use strict';

	function initMap(root) {
		var canvas = root.querySelector('.misvm-map__canvas');
		var tooltip = root.querySelector('.misvm-tooltip');
		if (!canvas || !tooltip) {
			return;
		}

		var linked = canvas.querySelectorAll('.misvm-region--linked');

		function showTooltip(region, clientX, clientY) {
			var name = region.getAttribute('data-misvm-name') || '';
			if (!name) {
				hideTooltip();
				return;
			}
			tooltip.textContent = name;
			tooltip.hidden = false;
			positionTooltip(clientX, clientY);
		}

		function positionTooltip(clientX, clientY) {
			var rect = root.getBoundingClientRect();
			var x = clientX - rect.left;
			var y = clientY - rect.top;
			tooltip.style.left = x + 'px';
			tooltip.style.top = y + 'px';
		}

		function hideTooltip() {
			tooltip.hidden = true;
			tooltip.textContent = '';
		}

		function go(region) {
			var url = region.getAttribute('data-misvm-url');
			if (url) {
				window.location.href = url;
			}
		}

		linked.forEach(function (region) {
			region.addEventListener('mouseenter', function (e) {
				region.classList.add('is-hover');
				showTooltip(region, e.clientX, e.clientY);
			});

			region.addEventListener('mousemove', function (e) {
				if (!tooltip.hidden) {
					positionTooltip(e.clientX, e.clientY);
				}
			});

			region.addEventListener('mouseleave', function () {
				region.classList.remove('is-hover');
				hideTooltip();
			});

			region.addEventListener('click', function (e) {
				e.preventDefault();
				go(region);
			});

			region.addEventListener('keydown', function (e) {
				if (e.key === 'Enter' || e.key === ' ') {
					e.preventDefault();
					go(region);
				}
			});

			// Touch: briefly show name, then navigate.
			region.addEventListener(
				'touchend',
				function (e) {
					e.preventDefault();
					var touch = e.changedTouches && e.changedTouches[0];
					if (touch) {
						showTooltip(region, touch.clientX, touch.clientY);
					}
					window.setTimeout(function () {
						go(region);
					}, 180);
				},
				{ passive: false }
			);
		});
	}

	function boot() {
		document.querySelectorAll('.misvm-map').forEach(initMap);
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', boot);
	} else {
		boot();
	}
})();
