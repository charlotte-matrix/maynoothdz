/**
 * Maynooth DZ header/footer behaviour:
 * mobile menu, mega menu, footer accordion, info dialogs, scrolled header.
 */
(function () {
  'use strict';

  var assetsBase =
    (window.matrixDzChrome && window.matrixDzChrome.assetsUrl) ||
    '';

  var megaCopy = {
    About: [
      'About the Zone',
      'Get to know Maynooth’s journey towards a lower-carbon future.',
    ],
    Map: [
      'Explore by theme',
      'Discover the places and ideas shaping a greener Maynooth.',
    ],
    Projects: [
      'Local projects',
      'See climate action taking shape across the town.',
    ],
    'Take Action': [
      'Make a difference',
      'Find your next step — at home, on the move or together.',
    ],
    Community: [
      'Together in Maynooth',
      'Local stories, shared ideas and ways to get involved.',
    ],
    Contact: [
      'Get in touch',
      'Connect with the Climate Action Office.',
    ],
  };

  var megaIcons = {
    Map: 'map-icon.svg',
    About: 'leaf-icon.svg',
    Projects: 'all-projects-icon.svg',
    'Take Action': 'home-icon.svg',
    Community: 'community-icon.svg',
    Contact: 'community-icon.svg',
  };

  var footerGroups = {
    Map: [
      'Energy',
      'Retrofit',
      'Transport',
      'Biodiversity & resilience',
      'Community action',
      'Public realm',
      'Education awareness',
      'Sustainable practices',
      'Circular economy',
      'Water & nature-based solutions',
    ],
    About: [
      'Resources',
      'Why Maynooth',
      'What is the Maynooth DZ',
      'Climate action explained',
    ],
    Projects: [
      'Community garden harbour field',
      'Climate champions',
      'Together for sustainable future podcast',
      'Kildare demohouse',
      'Picnic in the park',
      'Solar PV Meitheal Moyglare hall',
    ],
    'Take Action': [
      'Retrofit your home',
      'Travel sustainably',
      'Start a community project',
      'Business and school actions',
    ],
    Community: ['Events', 'Updates', 'Add an event or update'],
    Contact: [
      'Climate Action Office',
      'Kildare County Council',
      'climateaction@kildarecoco.ie',
    ],
  };

  var information = {
    accessibility: [
      'Accessibility',
      'You can navigate this site with a keyboard and reduce animation through your device settings. For assistance or accessibility feedback, contact climateaction@kildarecoco.ie.',
    ],
    privacy: [
      'Privacy & cookies',
      'This site does not include analytics or advertising cookies in this prototype. Forms prepare email requests in your browser; entries are not saved by this website and no automatic newsletter subscription is created.',
    ],
    cookies: [
      'Cookie settings',
      'This site does not set optional analytics or advertising cookies. There are no optional cookie preferences to configure.',
    ],
  };

  function ready(fn) {
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', fn);
    } else {
      fn();
    }
  }

  ready(function () {
    var header = document.querySelector('.site-header');
    var nav = document.querySelector('#navigation');
    var menuButton = document.querySelector('.menu-toggle');
    if (!header || !nav) {
      return;
    }

    // Mobile menu
    function closeMenu() {
      if (!menuButton) {
        return;
      }
      menuButton.setAttribute('aria-expanded', 'false');
      menuButton.setAttribute('aria-label', 'Open menu');
      nav.classList.remove('open');
      document.body.classList.remove('mobile-menu-open');
      var light = header.querySelector('.logo-light');
      if (light) {
        light.hidden = true;
      }
    }

    function openMenu() {
      if (!menuButton) {
        return;
      }
      menuButton.setAttribute('aria-expanded', 'true');
      menuButton.setAttribute('aria-label', 'Close menu');
      nav.classList.add('open');
      document.body.classList.add('mobile-menu-open');
      var light = header.querySelector('.logo-light');
      if (light) {
        light.hidden = false;
      }
    }

    if (menuButton) {
      menuButton.addEventListener('click', function () {
        if (menuButton.getAttribute('aria-expanded') === 'true') {
          closeMenu();
        } else {
          openMenu();
        }
      });

      nav.querySelectorAll('a').forEach(function (link) {
        link.addEventListener('click', closeMenu);
      });

      document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
          closeMenu();
        }
      });

      window
        .matchMedia('(min-width:951px)')
        .addEventListener('change', closeMenu);
    }

    // Scrolled header
    function updateScroll() {
      header.classList.toggle('scrolled', window.scrollY > 20);
    }
    window.addEventListener('scroll', updateScroll, { passive: true });
    updateScroll();

    // Mega menu (desktop)
    var desktop = window.matchMedia('(min-width:951px)');
    var panel = document.createElement('div');
    panel.className = 'mega-menu container';
    panel.id = 'desktop-mega-menu';
    panel.hidden = true;
    panel.setAttribute('role', 'region');
    panel.setAttribute('aria-labelledby', 'mega-title');
    panel.innerHTML =
      '<div class="mega-glass"><div class="mega-intro"><span class="mega-icon"><img alt=""></span><h2 id="mega-title"></h2><p></p></div><ul class="mega-items"></ul></div>';
    header.appendChild(panel);

    var active = null;
    var timer;

    function closeMega() {
      clearTimeout(timer);
      panel.hidden = true;
      if (active) {
        active.setAttribute('aria-expanded', 'false');
        active = null;
      }
    }

    function openMega(link) {
      if (!desktop.matches) {
        return;
      }
      clearTimeout(timer);
      var title = link.textContent.trim();
      if (!megaCopy[title]) {
        return;
      }

      if (active) {
        active.setAttribute('aria-expanded', 'false');
      }
      active = link;
      link.setAttribute('aria-expanded', 'true');

      panel.querySelector('h2').textContent = megaCopy[title][0];
      panel.querySelector('.mega-intro p').textContent = megaCopy[title][1];
      var icon = megaIcons[title] || 'community-icon.svg';
      panel.querySelector('img').src = assetsBase + '/' + icon;

      var list = panel.querySelector('ul');
      var items = footerGroups[title] || [];
      list.replaceChildren.apply(
        list,
        items.map(function (label) {
          var li = document.createElement('li');
          li.textContent = label;
          return li;
        })
      );
      panel.hidden = false;
    }

    Array.prototype.filter
      .call(nav.children, function (el) {
        return el.tagName === 'A';
      })
      .forEach(function (link) {
        link.setAttribute('aria-controls', panel.id);
        link.addEventListener('pointerenter', function (event) {
          if (event.pointerType !== 'touch') {
            openMega(link);
          }
        });
        link.addEventListener('focus', function () {
          openMega(link);
        });
        link.addEventListener('click', closeMega);
      });

    header.addEventListener('pointerleave', function () {
      timer = setTimeout(closeMega, 160);
    });
    header.addEventListener('pointerenter', function () {
      clearTimeout(timer);
    });
    document.addEventListener('focusin', function (event) {
      if (!nav.contains(event.target) && !panel.contains(event.target)) {
        closeMega();
      }
    });
    document.addEventListener('keydown', function (event) {
      if (event.key === 'Escape') {
        closeMega();
      }
    });
    document.addEventListener('pointerdown', function (event) {
      if (!header.contains(event.target)) {
        closeMega();
      }
    });
    desktop.addEventListener('change', function () {
      closeMega();
      if (!desktop.matches) {
        Array.prototype.forEach.call(nav.children, function (link) {
          link.removeAttribute('aria-expanded');
        });
      }
    });

    // Footer groups: collapse on small screens
    var footerMobile = window.matchMedia('(max-width:640px)');
    function footerMode() {
      document.querySelectorAll('.footer-group').forEach(function (el) {
        el.open = !footerMobile.matches;
      });
    }
    footerMode();
    footerMobile.addEventListener('change', footerMode);

    var sitemapLink = document.getElementById('sitemap-link');
    if (sitemapLink) {
      sitemapLink.addEventListener('click', function () {
        document.querySelectorAll('.footer-group').forEach(function (el) {
          el.open = true;
        });
      });
    }

    // Prevent desktop summary toggles
    document.querySelectorAll('.footer-group summary').forEach(function (summary) {
      summary.addEventListener('click', function (event) {
        if (window.innerWidth > 640) {
          event.preventDefault();
        }
      });
    });

    // Info dialogs
    var infoDialog = document.getElementById('info-dialog');
    if (infoDialog) {
      var titleEl = document.getElementById('info-title');
      var copyEl = document.getElementById('info-copy');
      var closeBtn = infoDialog.querySelector('.close-dialog');

      document.querySelectorAll('[data-info]').forEach(function (button) {
        button.addEventListener('click', function () {
          var key = button.getAttribute('data-info');
          var entry = information[key];
          if (!entry) {
            return;
          }
          titleEl.textContent = entry[0];
          copyEl.textContent = entry[1];
          closeMenu();
          infoDialog.showModal();
          document.body.classList.add('modal-open');
        });
      });

      if (closeBtn) {
        closeBtn.addEventListener('click', function () {
          infoDialog.close();
        });
      }

      infoDialog.addEventListener('close', function () {
        document.body.classList.remove('modal-open');
      });

      infoDialog.addEventListener('click', function (event) {
        var rect = infoDialog.getBoundingClientRect();
        if (
          event.target === infoDialog &&
          (event.clientX < rect.left ||
            event.clientX > rect.right ||
            event.clientY < rect.top ||
            event.clientY > rect.bottom)
        ) {
          infoDialog.close();
        }
      });
    }
  });
})();
