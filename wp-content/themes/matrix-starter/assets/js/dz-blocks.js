/**
 * Maynooth DZ blocks: map stickers, pathway tabs, carousel, video consent, newsletter prototype.
 */
(function () {
  'use strict';

  var assets =
    (window.matrixDzChrome && window.matrixDzChrome.assetsUrl) ||
    (window.matrixDzBlocks && window.matrixDzBlocks.assetsUrl) ||
    '';

  document.documentElement.classList.add('js');

  function ready(fn) {
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', fn);
    } else {
      fn();
    }
  }

  function placeStickers(layer) {
    if (!layer || layer.dataset.stickersReady === '1') {
      return;
    }
    layer.dataset.stickersReady = '1';
    var stickers = [
      ['bee', 95, 163, 53],
      ['bee', 651, 379, 53],
      ['tree', 584, 12, 51],
      ['tree', 34, 38, 51],
      ['train', 477, 289, 71],
      ['chicken', 624, 110, 53],
      ['house', 278, 136, 51],
      ['house', 422, 82, 51],
      ['house', 137, 352, 51],
      ['house', 409, 405, 51],
    ];
    stickers.forEach(function (item, index) {
      var img = document.createElement('img');
      img.src = assets + '/' + item[0] + '.webp';
      img.alt = '';
      img.className = item[0];
      img.style.cssText =
        'left:' +
        (item[1] / 721) * 100 +
        '%;top:' +
        (item[2] / 485) * 100 +
        '%;width:' +
        (item[3] / 721) * 100 +
        '%;--delay:' +
        -index * 0.65 +
        's';
      layer.appendChild(img);
    });
  }

  function initPathway(root) {
    var tabs = Array.prototype.slice.call(
      root.querySelectorAll('.pathway-tabs [data-step]')
    );
    var accordions = Array.prototype.slice.call(
      root.querySelectorAll('.accordion-heading [data-step]')
    );
    var panels = Array.prototype.slice.call(root.querySelectorAll('.step-panel'));
    var sections = Array.prototype.slice.call(
      root.querySelectorAll('.pathway-step')
    );
    if (!tabs.length && !accordions.length) {
      return;
    }

    var mobile = window.matchMedia('(max-width:800px)');
    var count = Math.max(tabs.length, accordions.length, panels.length) || 1;
    var active = 1;
    var expanded = true;

    function update(focus) {
      tabs.forEach(function (b, i) {
        b.setAttribute('aria-selected', String(i + 1 === active));
        b.tabIndex = i + 1 === active ? 0 : -1;
      });
      accordions.forEach(function (b, i) {
        b.setAttribute('aria-expanded', String(i + 1 === active && expanded));
      });
      panels.forEach(function (p, i) {
        p.hidden = i + 1 !== active || (mobile.matches && !expanded);
      });
      sections.forEach(function (s, i) {
        s.classList.toggle('is-open', i + 1 === active && expanded);
      });
      if (focus) {
        var btn = (mobile.matches ? accordions : tabs)[active - 1];
        if (btn) {
          btn.focus({ preventScroll: true });
        }
      }
    }

    tabs.forEach(function (b, i) {
      b.addEventListener('click', function () {
        active = i + 1;
        expanded = true;
        update();
      });
    });
    accordions.forEach(function (b, i) {
      b.addEventListener('click', function () {
        expanded = active === i + 1 ? !expanded : true;
        active = i + 1;
        update();
      });
    });
    root.querySelectorAll('[data-go]').forEach(function (b) {
      b.addEventListener('click', function () {
        active = Number(b.getAttribute('data-go')) || 1;
        expanded = true;
        update(true);
      });
    });
    mobile.addEventListener('change', function () {
      expanded = true;
      update();
    });
    update();
  }

  function setupCarousel(carousel) {
    if (!carousel) {
      return;
    }
    var track = carousel.querySelector('.track');
    var controls = carousel.querySelector('.carousel-controls');
    if (!track || !controls) {
      return;
    }
    var previous = controls.querySelector('.previous');
    var next = controls.querySelector('.next');
    var progress = controls.querySelector('.scroll-progress span');
    if (!previous || !next) {
      return;
    }

    var reduced = window.matchMedia('(prefers-reduced-motion: reduce)');

    function scrollByCard(direction) {
      var gap = parseFloat(getComputedStyle(track).columnGap) || 0;
      var first = track.firstElementChild;
      if (!first) {
        return;
      }
      var amount = first.getBoundingClientRect().width + gap;
      track.scrollBy({
        left: direction * amount,
        behavior: reduced.matches ? 'auto' : 'smooth',
      });
    }

    function update() {
      var maximum = track.scrollWidth - track.clientWidth;
      previous.disabled = track.scrollLeft < 2;
      next.disabled = maximum < 2 || track.scrollLeft >= maximum - 2;
      if (progress) {
        progress.style.setProperty(
          '--progress',
          (maximum > 0
            ? Math.max(0, Math.min(1, track.scrollLeft / maximum)) * 300
            : 0) + '%'
        );
      }
    }

    previous.addEventListener('click', function () {
      scrollByCard(-1);
    });
    next.addEventListener('click', function () {
      scrollByCard(1);
    });
    track.addEventListener('scroll', update, { passive: true });

    var startX = 0;
    var startScroll = 0;
    var active = false;
    var dragged = false;
    track.addEventListener('pointerdown', function (e) {
      if (e.pointerType !== 'mouse' || e.button !== 0) {
        return;
      }
      active = true;
      dragged = false;
      startX = e.clientX;
      startScroll = track.scrollLeft;
    });
    track.addEventListener('pointermove', function (e) {
      if (!active) {
        return;
      }
      var dx = e.clientX - startX;
      if (Math.abs(dx) > 5) {
        dragged = true;
        track.classList.add('dragging');
        track.setPointerCapture(e.pointerId);
        track.scrollLeft = startScroll - dx;
        e.preventDefault();
      }
    });
    function endDrag() {
      active = false;
      track.classList.remove('dragging');
    }
    track.addEventListener('pointerup', endDrag);
    track.addEventListener('pointercancel', endDrag);
    track.addEventListener(
      'click',
      function (e) {
        if (dragged) {
          e.preventDefault();
          e.stopPropagation();
          dragged = false;
        }
      },
      true
    );
    if (window.ResizeObserver) {
      new ResizeObserver(update).observe(track);
    }
    update();
  }

  function initVideoConsent(root) {
    var btn = root.querySelector('[data-video-consent]');
    var status = root.querySelector('[data-video-status]');
    if (!btn || !status) {
      return;
    }
    btn.addEventListener(
      'click',
      function () {
        status.textContent =
          'Consent granted. Add an approved video URL when connecting this block to the CMS.';
        status.setAttribute('role', 'status');
        btn.textContent = 'Reset video example';
        btn.onclick = function () {
          location.reload();
        };
      },
      { once: true }
    );
  }

  function initNewsletter(root) {
    var form = root.querySelector('[data-dz-newsletter]');
    if (!form) {
      return;
    }
    var feedback = root.querySelector('[data-newsletter-feedback]');
    var emailLink = root.querySelector('[data-newsletter-email]');
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      var name = (form.querySelector('[name="name"]') || {}).value || '';
      var email = (form.querySelector('[name="email"]') || {}).value || '';
      if (feedback) {
        feedback.hidden = false;
      }
      if (emailLink) {
        var body =
          'Please add me to Maynooth DZ community updates.%0A%0AName: ' +
          encodeURIComponent(name) +
          '%0AEmail: ' +
          encodeURIComponent(email);
        emailLink.href =
          'mailto:climateaction@kildarecoco.ie?subject=' +
          encodeURIComponent('Maynooth DZ newsletter request') +
          '&body=' +
          body;
      }
    });
  }

  ready(function () {
    document.querySelectorAll('.map-stickers').forEach(placeStickers);
    document.querySelectorAll('.dz-pathway').forEach(initPathway);
    document.querySelectorAll('.project-carousel').forEach(setupCarousel);
    document.querySelectorAll('.dz-flexi').forEach(function (root) {
      initVideoConsent(root);
      initNewsletter(root);
    });
  });
})();
