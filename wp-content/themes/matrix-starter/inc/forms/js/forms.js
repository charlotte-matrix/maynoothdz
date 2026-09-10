/* Theme-Forms helper v3 — AJAX submit + banners + CAPTCHA (reCAPTCHA v3 / Cloudflare Turnstile)
 *
 * Provide globals via enqueue:
 *   window.themeFormsCaptchaProvider  = 'none' | 'recaptcha_v3' | 'turnstile'
 *   window.themeFormsRecaptchaV3      = 'YOUR_RECAPTCHA_SITE_KEY'
 *   window.themeFormsTurnstileSiteKey = 'YOUR_TURNSTILE_SITE_KEY'
 */

(function () {
  // Prevent double-init if the file is accidentally enqueued twice.
  if (window._themeFormsInitDone) return;
  window._themeFormsInitDone = true;

  /** ACF / other scripts may leave nested objects; Turnstile requires a non-empty string sitekey. */
  function coerceTurnstileSiteKey(raw) {
    let v = raw;
    for (let i = 0; i < 12 && v != null && typeof v === 'object'; i++) {
      const next = v.value ?? v.key ?? v.sitekey ?? v.site_key;
      if (next === undefined || next === v) break;
      v = next;
    }
    if (v == null) return '';
    if (typeof v === 'string') return v.trim();
    if (typeof v === 'number' || typeof v === 'boolean') return String(v).trim();
    return '';
  }

  document.addEventListener('DOMContentLoaded', () => {
    let provider = window.themeFormsCaptchaProvider;
    if (!provider || typeof provider !== 'string') provider = 'none';
    provider = provider.toLowerCase().trim();

    let tsSiteKey = coerceTurnstileSiteKey(window.themeFormsTurnstileSiteKey);

    const reSiteKey = (typeof window.themeFormsRecaptchaV3 === 'string' ? window.themeFormsRecaptchaV3.trim() : '');
    const clearTurnstileToken = (form) => {
      const inp = form.querySelector('input[name="cf-turnstile-response"]');
      if (inp) inp.value = '';
    };

    const ensureTurnstileRendered = (form) => {
      if (provider !== 'turnstile' || !window.turnstile || !tsSiteKey) return;
      const placeholder = form.querySelector('.cf-turnstile');
      if (!placeholder || form._tsWidgetId) return;
      form._tsWidgetId = window.turnstile.render(placeholder, {
        sitekey: coerceTurnstileSiteKey(tsSiteKey),
        size: placeholder.getAttribute('data-size') || 'normal',
        theme: placeholder.getAttribute('data-theme') || 'auto',
        callback: (token) => {
          let inp = form.querySelector('input[name="cf-turnstile-response"]');
          if (!inp) {
            inp = document.createElement('input');
            inp.type = 'hidden';
            inp.name = 'cf-turnstile-response';
            form.appendChild(inp);
          }
          inp.value = token;
        },
        'expired-callback': () => {
          clearTurnstileToken(form);
          showBanner(form, 'Captcha expired, please complete it again.', false);
        },
        'error-callback': () => {
          clearTurnstileToken(form);
          showBanner(form, 'Captcha failed, please try again.', false);
        },
      });
    };

    document.querySelectorAll('form[data-theme-form]').forEach(form => {
      form._submitSeq = 0;
      // Render visible Turnstile widgets immediately so users can solve before submit.
      ensureTurnstileRendered(form);

      form.addEventListener('submit', ev => {
        if (!form.checkValidity()) { ev.preventDefault(); form.reportValidity(); return; }
        ev.preventDefault();
        ev.stopImmediatePropagation();
        if (form.dataset.submitting === '1') return; // prevent double submit
        if (form.dataset.sent === '1') return; // ignore repeat submits after success

        const lock = (on) => {
          form.dataset.submitting = on ? '1' : '0';
          form.classList.toggle('is-submitting', !!on);
          form.querySelectorAll('button, [type="submit"]').forEach(b => {
            b.disabled = !!on;
            if (!b.dataset.originalLabel) {
              b.dataset.originalLabel = b.textContent || 'Submit';
            }
            b.textContent = on ? 'Processing...' : b.dataset.originalLabel;
          });
        };

        // Abort any prior in-flight request for this form
        if (form._submitController) { try { form._submitController.abort(); } catch (e) { } }
        form._submitController = new AbortController();

        const send = () => {
          const submitSeq = ++form._submitSeq;
          lock(true);

          const data = new FormData(form);
          data.append('is_ajax', '1');

          fetch(form.getAttribute('action'), {
            method: 'POST',
            body: data,
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            signal: form._submitController.signal
          })
            .then(async r => {
              if (submitSeq !== form._submitSeq) return;
              const ct = (r.headers.get('content-type') || '').toLowerCase();
              const body = ct.includes('application/json') ? await r.json() : await r.text();
              const ok = typeof body === 'object' ? !!body.success : false;

              if (ok) {
                form.dataset.sent = '1';
                showBanner(form, 'Thanks! Your message has been sent.', true);
                form.reset();
                // Reset Turnstile invisible widget if one was rendered
                if (window.turnstile && form._tsWidgetId) {
                  try { window.turnstile.reset(form._tsWidgetId); } catch (e) { }
                }
              } else {
                if (form.dataset.sent === '1') return;
                let msg = 'Sorry, something went wrong. Please try again.';
                if (typeof body === 'object' && body && body.data) {
                  const d = body.data;
                  if (d.message) {
                    msg = String(d.message);
                  }
                  if (d.mail_error) msg += ` (Mail error: ${d.mail_error})`;
                  if (d.to) msg += ` [to: ${d.to}]`;
                }
                // If backend rejects Turnstile, force a fresh challenge token.
                if (provider === 'turnstile' && msg.indexOf('captcha_failed') !== -1) {
                  clearTurnstileToken(form);
                  if (window.turnstile && form._tsWidgetId) {
                    try { window.turnstile.reset(form._tsWidgetId); } catch (e) { }
                  }
                  msg = 'Captcha check failed. Please complete captcha again and resubmit.';
                }
                showBanner(form, msg, false);
              }
            })
            .catch((e) => {
              if (submitSeq !== form._submitSeq) return;
              if (e && e.name === 'AbortError') return;
              if (form.dataset.sent === '1') return;
              showBanner(form, 'Sorry, something went wrong. Please try again.', false);
            })
            .finally(() => lock(false));
        };

        // --- CAPTCHA branches ---
        // Google reCAPTCHA v3
        if (provider === 'recaptcha_v3' && window.grecaptcha && reSiteKey) {
          grecaptcha.ready(() => {
            grecaptcha.execute(reSiteKey, { action: 'submit' }).then(token => {
              let inp = form.querySelector('input[name="g-recaptcha-response"]');
              if (!inp) {
                inp = document.createElement('input');
                inp.type = 'hidden';
                inp.name = 'g-recaptcha-response';
                form.appendChild(inp);
              }
              inp.value = token;
              send();
            });
          });
          return;
        }

        // Cloudflare Turnstile (visible or invisible)
        if (provider === 'turnstile' && window.turnstile && tsSiteKey) {
          const existingToken = form.querySelector('input[name="cf-turnstile-response"]');
          if (existingToken && existingToken.value) { send(); return; }

          const placeholder = form.querySelector('.cf-turnstile');
          if (placeholder) {
            showBanner(form, 'Please complete the captcha, then submit once.', false);
            return;
          }

          if (!form._tsWidgetId) {
            form._tsWidgetId = window.turnstile.render(placeholder || form, {
              sitekey: coerceTurnstileSiteKey(tsSiteKey),
              size: placeholder ? (placeholder.getAttribute('data-size') || 'normal') : 'invisible',
              theme: placeholder ? (placeholder.getAttribute('data-theme') || 'auto') : 'auto',
              callback: (token) => {
                let inp = form.querySelector('input[name="cf-turnstile-response"]');
                if (!inp) {
                  inp = document.createElement('input');
                  inp.type = 'hidden';
                  inp.name = 'cf-turnstile-response';
                  form.appendChild(inp);
                }
                inp.value = token;
                send();
              },
              'expired-callback': () => {
                clearTurnstileToken(form);
                showBanner(form, 'Captcha expired, please complete it again.', false);
              },
              'error-callback': () => {
                clearTurnstileToken(form);
                showBanner(form, 'Captcha failed, please try again.', false);
              },
            });
          }

          try {
            // Only execute automatically if invisible
            if (!placeholder) window.turnstile.execute(form._tsWidgetId);
          } catch (e) {
            send();
          }
          return;
        }

        // No CAPTCHA
        send();
      });
    });

    // Handle race conditions where Turnstile script finishes loading after this file.
    if (provider === 'turnstile' && tsSiteKey) {
      let attempts = 0;
      const waitForTurnstile = setInterval(() => {
        attempts++;
        if (window.turnstile) {
          clearInterval(waitForTurnstile);
          document.querySelectorAll('form[data-theme-form]').forEach(ensureTurnstileRendered);
        } else if (attempts > 80) {
          clearInterval(waitForTurnstile);
        }
      }, 50);
    }

    function showBanner(form, text, ok) {
      form.parentElement.querySelectorAll('.theme-form-alert').forEach(el => el.remove());
      const div = document.createElement('div');
      div.className = `theme-form-alert ${ok ? 'is-success' : 'is-error'}`;
      div.textContent = text;
      form.after(div);
      setTimeout(() => div.remove(), 6000);
    }
  });

  // === NEWSLETTER (Brevo) ===
  if (!window._brevoInitDone) {
    window._brevoInitDone = true;

    document.addEventListener('DOMContentLoaded', () => {
      let providerNews = window.themeFormsCaptchaProvider;
      if (!providerNews || typeof providerNews !== 'string') providerNews = 'none';
      providerNews = providerNews.toLowerCase().trim();
      const tsKeyNews = coerceTurnstileSiteKey(window.themeFormsTurnstileSiteKey);

      document.querySelectorAll('form[data-brevo-newsletter]').forEach(form => {
      const ensureNewsletterTurnstileRendered = () => {
        if (providerNews !== 'turnstile' || !window.turnstile || !tsKeyNews) return;
        const placeholder = form.querySelector('.cf-turnstile');
        if (!placeholder || form._newsletterTsWidgetId) return;
        form._newsletterTsWidgetId = window.turnstile.render(placeholder, {
          sitekey: tsKeyNews,
          size: placeholder.getAttribute('data-size') || 'normal',
          theme: placeholder.getAttribute('data-theme') || 'auto',
          callback: (token) => {
            let inp = form.querySelector('input[name="cf-turnstile-response"]');
            if (!inp) {
              inp = document.createElement('input');
              inp.type = 'hidden';
              inp.name = 'cf-turnstile-response';
              form.appendChild(inp);
            }
            inp.value = token;
          },
        });
      };

      // Render visible Turnstile widget for newsletter forms.
      ensureNewsletterTurnstileRendered();

        form.addEventListener('submit', ev => {
          if (!form.checkValidity()) { ev.preventDefault(); form.reportValidity(); return; }
          ev.preventDefault();

        let provider = window.themeFormsCaptchaProvider;
        if (!provider || typeof provider !== 'string') provider = 'none';
        provider = provider.toLowerCase().trim();

        const tsSiteKey = coerceTurnstileSiteKey(window.themeFormsTurnstileSiteKey);

        const reSiteKey = (typeof window.themeFormsRecaptchaV3 === 'string' ? window.themeFormsRecaptchaV3.trim() : '');

          const fd = new FormData(form);
          fd.set('action', 'matrix_subscribe_brevo');
          fd.set('nonce', window.matrixBrevoNonce || '');
          fd.set('is_ajax', '1');

          // Abort previous if any
          if (form._brevoController) { try { form._brevoController.abort(); } catch (e) { } }
          form._brevoController = new AbortController();

        const submitNewsletter = () => {
          fetch((window.ajaxurl || form.getAttribute('action') || '/wp-admin/admin-ajax.php'), {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: fd,
            signal: form._brevoController.signal
          })
            .then(async r => {
              let out;
              try { out = await r.json(); } catch (e) { out = { success: false }; }
              const ok = out && out.success;
              showBanner(
                form,
                (out && out.data && out.data.message)
                  ? out.data.message
                  : (ok ? 'Thanks, subscribed!' : 'Sorry, something went wrong.'),
                ok
              );
              if (ok) form.reset();
            })
            .catch((e) => {
              if (e && e.name === 'AbortError') return;
              showBanner(form, 'Sorry, something went wrong.', false);
            });
        };

        // reCAPTCHA v3 for newsletter forms
        if (provider === 'recaptcha_v3' && window.grecaptcha && reSiteKey) {
          grecaptcha.ready(() => {
            grecaptcha.execute(reSiteKey, { action: 'newsletter_subscribe' }).then(token => {
              fd.set('g-recaptcha-response', token);
              submitNewsletter();
            });
          });
          return;
        }

        // Turnstile for newsletter forms (visible widget)
        if (provider === 'turnstile' && window.turnstile && tsSiteKey) {
          ensureNewsletterTurnstileRendered();
          const tokenInput = form.querySelector('input[name="cf-turnstile-response"]');
          if (!tokenInput || !tokenInput.value) {
            showBanner(form, 'Please complete the captcha before subscribing.', false);
            return;
          }
          fd.set('cf-turnstile-response', tokenInput.value);
          submitNewsletter();
          return;
        }

        submitNewsletter();
        });
      // Handle race condition if Turnstile script loads after DOM ready.
      if (providerNews === 'turnstile' && tsKeyNews) {
        let attempts = 0;
        const waitForNewsletterTurnstile = setInterval(() => {
          attempts++;
          if (window.turnstile) {
            clearInterval(waitForNewsletterTurnstile);
            ensureNewsletterTurnstileRendered();
          } else if (attempts > 80) {
            clearInterval(waitForNewsletterTurnstile);
          }
        }, 50);
      }
      });

      function showBanner(form, text, ok) {
        const div = document.createElement('div');
        div.className = `theme-form-alert ${ok ? 'is-success' : 'is-error'}`;
        div.textContent = text;
        form.after(div);
        setTimeout(() => div.remove(), 6000);
      }
    });
  }
})();
