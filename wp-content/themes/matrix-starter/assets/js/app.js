// assets/js/app.js
"use strict";
import '../css/app.css';

// (Optional: your jQuery/WP helpers here; DO NOT import or start Alpine here.)
document.addEventListener('DOMContentLoaded', () => {
  const checkbox = document.querySelector('#ship-to-different-address-checkbox');
  const shipping = document.querySelector('.shipping_address');
  if (checkbox && shipping) {
    const sync = () => { shipping.style.display = checkbox.checked ? 'block' : 'none'; };
    checkbox.addEventListener('change', sync);
    sync();
  }
});


(function () {
  function enhanceNotice(el, type) {
    // Set the appropriate live region + role so SRs announce changes
    if (type === 'error') {
      el.setAttribute('role', 'alert');
      el.setAttribute('aria-live', 'assertive');
    } else {
      el.setAttribute('role', 'status');
      el.setAttribute('aria-live', 'polite');
    }
  }

  function applyToExisting() {
    document.querySelectorAll('.woocommerce-error').forEach(function (ul) {
      enhanceNotice(ul, 'error');
      ul.querySelectorAll('li').forEach(function (li) { li.setAttribute('role', 'alert'); });
    });
    document.querySelectorAll('.woocommerce-message').forEach(function (n) {
      enhanceNotice(n, 'message');
    });
    document.querySelectorAll('.woocommerce-info').forEach(function (n) {
      // Woo sometimes outputs <ul class="woocommerce-info"> or a single <div>
      enhanceNotice(n, 'info');
    });
  }

  // Initial pass
  applyToExisting();

  // Watch for notices added dynamically (AJAX add-to-cart, etc.)
  var wrapper = document.querySelector('.woocommerce-notices-wrapper') || document.body;
  if (!wrapper) return;
  try {
    new MutationObserver(function (muts) {
      muts.forEach(function (m) {
        m.addedNodes && m.addedNodes.forEach(function (node) {
          if (!(node instanceof Element)) return;
          if (node.classList && (node.classList.contains('woocommerce-message') ||
            node.classList.contains('woocommerce-info') ||
            node.classList.contains('woocommerce-error'))) {
            applyToExisting();
          } else {
            // Also check descendants (some plugins insert wrappers)
            if (node.querySelector) {
              if (node.querySelector('.woocommerce-message, .woocommerce-info, .woocommerce-error')) {
                applyToExisting();
              }
            }
          }
        });
      });
    }).observe(wrapper, { childList: true, subtree: true });
  } catch (e) { }
})();