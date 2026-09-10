(function ($) {
  let sectionIndex = 1;
  const cfg = window.matrixDbAdmin || {};

  function slugify(label) {
    return label
      .toLowerCase()
      .trim()
      .replace(/[^a-z0-9]+/g, '_')
      .replace(/^_+|_+$/g, '')
      .replace(/^([^a-z])/, 'section_$1');
  }

  function cleanFigmaPaste(text) {
    let value = (text || '').trim();
    if (!value) {
      return '';
    }

    const urlPattern = /https?:\/\/[^\s\]\)"'<>]*figma\.com\/[^\s\]\)"'<>]+/i;
    let match = value.match(urlPattern);
    if (match) {
      return match[0].replace(/[)\],.'"']+$/, '');
    }

    value = value.replace(/(?:\s*Implement this design from Figma\.?\s*)+/gi, ' ');
    value = value.replace(/^@+/gm, '').trim();

    match = value.match(urlPattern);
    if (match) {
      return match[0].replace(/[)\],.'"']+$/, '');
    }

    return value;
  }

  function featuresForType(typeKey) {
    const registry = cfg.features || {};
    const out = [];
    Object.keys(registry).forEach((key) => {
      const feature = registry[key];
      const types = feature.types || [];
      if (types.indexOf(typeKey) !== -1) {
        out.push({ key, feature });
      }
    });
    return out;
  }

  function buildFeatureHtml(index, typeKey, checked) {
    const items = featuresForType(typeKey);
    if (!items.length) {
      return '<p class="description">No optional features for this type.</p>';
    }

    checked = checked || [];
    return items
      .map(({ key, feature }) => {
        const isChecked = checked.indexOf(key) !== -1 ? ' checked' : '';
        const desc = feature.description
          ? `<span class="matrix-db-check__desc">${feature.description}</span>`
          : '';
        return `<label class="matrix-db-check">
          <input type="checkbox" name="sections[${index}][features][]" value="${key}"${isChecked} />
          <span class="matrix-db-check__label">${feature.label}</span>
          ${desc}
        </label>`;
      })
      .join('');
  }

  function refreshFeaturePanel($card) {
    const index = $card.data('index');
    const typeKey = $card.find('.matrix-db-section-type').val() || 'flexi';
    const checked = [];
    $card.find('.matrix-db-feature-list input:checked').each(function () {
      checked.push($(this).val());
    });
    $card.find('.matrix-db-feature-list').html(buildFeatureHtml(index, typeKey, checked));
  }

  function renumberSections() {
    $('#matrix-db-sections .matrix-db-section-card').each(function (i) {
      $(this).find('.matrix-db-section-num').text(i + 1);
    });
  }

  function updateRemoveButtons() {
    const cards = $('#matrix-db-sections .matrix-db-section-card');
    cards.find('.matrix-db-remove-card').prop('disabled', cards.length <= 1);
  }

  function parseFigmaPaste($textarea) {
    const raw = ($textarea.val() || '').trim();
    if (!raw) {
      return;
    }
    const url = cleanFigmaPaste(raw);
    if (!url || url.indexOf('figma.com') === -1) {
      return;
    }
    $textarea.val(url);

    const $card = $textarea.closest('.matrix-db-section-card');
    const $label = $card.find('.matrix-db-label').first();
    const $layout = $card.find('.matrix-db-layout').first();

    if (!$label.val()) {
      const nameMatch = raw.match(/node-id=([^&\s]+)/i);
      const hint = nameMatch ? nameMatch[1].replace(/[:-]/g, ' ') : 'section';
      $label.val(hint);
    }
    if (!$layout.data('touched') && $label.val()) {
      $layout.val(slugify($label.val()));
    }
  }

  function addSectionCard() {
    const tpl = document.getElementById('matrix-db-section-template');
    if (!tpl) {
      return;
    }
    const html = tpl.innerHTML.replace(/\{\{INDEX\}\}/g, String(sectionIndex));
    const $card = $(html.trim());
    $card.attr('data-index', sectionIndex);
    $('#matrix-db-sections').append($card);
    refreshFeaturePanel($card);
    sectionIndex += 1;
    renumberSections();
    updateRemoveButtons();
  }

  $('#matrix-db-add-section').on('click', addSectionCard);

  $(document).on('click', '.matrix-db-remove-card', function () {
    $(this).closest('.matrix-db-section-card').remove();
    renumberSections();
    updateRemoveButtons();
  });

  $(document).on('change', '.matrix-db-section-type', function () {
    refreshFeaturePanel($(this).closest('.matrix-db-section-card'));
  });

  $(document).on('input', '.matrix-db-label', function () {
    const $card = $(this).closest('.matrix-db-section-card');
    const $layout = $card.find('.matrix-db-layout').first();
    if (!$layout.data('touched') && $(this).val()) {
      $layout.val(slugify($(this).val()));
    }
  });

  $(document).on('input', '.matrix-db-layout', function () {
    $(this).data('touched', true);
  });

  $(document).on('paste', '.matrix-db-figma', function (e) {
    const clipboard = e.originalEvent?.clipboardData?.getData('text');
    if (!clipboard) {
      return;
    }
    e.preventDefault();
    const $el = $(this);
    $el.val(cleanFigmaPaste(clipboard));
    parseFigmaPaste($el);
  });

  $(document).on('blur input', '.matrix-db-figma', function () {
    parseFigmaPaste($(this));
  });

  updateRemoveButtons();

  function pollLocalJob() {
    if (!cfg.jobId || !cfg.ajaxUrl) {
      return;
    }
    const $box = $('.matrix-db-local-progress');
    if (!$box.length) {
      return;
    }

    $.post(cfg.ajaxUrl, {
      action: 'matrix_db_local_job_status',
      nonce: cfg.statusNonce,
      job_id: cfg.jobId,
    })
      .done(function (res) {
        if (!res || !res.success) {
          setTimeout(pollLocalJob, 8000);
          return;
        }
        const data = res.data || {};
        if (data.log) {
          $('.matrix-db-local-log').text(data.log);
        }
        if (data.progress) {
          const p = data.progress;
          const mins = Math.floor((p.elapsed || 0) / 60);
          const secs = (p.elapsed || 0) % 60;
          $('.matrix-db-elapsed').text(
            mins + ':' + String(secs).padStart(2, '0') + ' elapsed',
          );
          $('.matrix-db-log-bytes').text(p.log_bytes || 0);
          if (p.hint) {
            $('.matrix-db-hint').text(p.hint);
          }
          if (Array.isArray(p.sections)) {
            const lines = p.sections.map(function (row) {
              const ok = row.acf && row.template;
              return (
                '<li><code>' +
                row.layout +
                '</code> ' +
                (ok ? '✓ files ready' : '… waiting for files') +
                '</li>'
              );
            });
            $('.matrix-db-file-progress').html(lines.join(''));
          }
        }
        if (!data.running && data.status !== 'generating_local') {
          window.location.reload();
          return;
        }
        setTimeout(pollLocalJob, 5000);
      })
      .fail(function () {
        setTimeout(pollLocalJob, 8000);
      });
  }

  pollLocalJob();
})(jQuery);
