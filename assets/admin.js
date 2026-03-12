jQuery(function ($) {
  function showTokenToast(message) {
    let $toast = $('#yrwp-admin-toast');

    if (!$toast.length) {
      $toast = $('<div id="yrwp-admin-toast" class="yrwp-admin-toast" aria-live="polite"></div>');
      $('body').append($toast);
    }

    $toast.stop(true, true).text(message).addClass('is-visible');

    window.clearTimeout(showTokenToast._timer);
    showTokenToast._timer = window.setTimeout(function () {
      $toast.removeClass('is-visible');
    }, 1800);
  }

  // Code editor for custom CSS
  if (typeof YRWP_ADMIN !== 'undefined' && YRWP_ADMIN.codeEditor) {
    if (document.getElementById('yrwp_custom_css')) {
      wp.codeEditor.initialize('yrwp_custom_css', YRWP_ADMIN.codeEditor);
    }
  }

  if (typeof YRWP_ADMIN !== 'undefined' && YRWP_ADMIN.templateEditor) {
    if (document.getElementById('yrwp_manual_template_current')) {
      wp.codeEditor.initialize('yrwp_manual_template_current', YRWP_ADMIN.templateEditor);
    }
    if (document.getElementById('yrwp_manual_template_today')) {
      wp.codeEditor.initialize('yrwp_manual_template_today', YRWP_ADMIN.templateEditor);
    }
    if (document.getElementById('yrwp_manual_template_today_item')) {
      wp.codeEditor.initialize('yrwp_manual_template_today_item', YRWP_ADMIN.templateEditor);
    }
    if (document.getElementById('yrwp_manual_template_7')) {
      wp.codeEditor.initialize('yrwp_manual_template_7', YRWP_ADMIN.templateEditor);
    }
    if (document.getElementById('yrwp_manual_template_7_item')) {
      wp.codeEditor.initialize('yrwp_manual_template_7_item', YRWP_ADMIN.templateEditor);
    }
    if (document.getElementById('yrwp_manual_template_9')) {
      wp.codeEditor.initialize('yrwp_manual_template_9', YRWP_ADMIN.templateEditor);
    }
    if (document.getElementById('yrwp_manual_template_9_item')) {
      wp.codeEditor.initialize('yrwp_manual_template_9_item', YRWP_ADMIN.templateEditor);
    }
  }

  function toggleRows() {
    const mode = $('input[name="yrwp[location_mode]"]:checked').val();
    $('.yrwp-row-manual').toggle(mode === 'manual_coords');
    $('.yrwp-row-city').toggle(mode === 'city');
  }
  toggleRows();
  $(document).on('change', 'input[name="yrwp[location_mode]"]', toggleRows);

  // Fill manual coords via browser geolocation
  $('.yrwp-use-browser-geo').on('click', function (e) {
    e.preventDefault();
    if (!navigator.geolocation) return alert('Geolocation is not supported');
    navigator.geolocation.getCurrentPosition(function (pos) {
      $('input[name="yrwp[lat]"]').val(pos.coords.latitude.toFixed(6));
      $('input[name="yrwp[lon]"]').val(pos.coords.longitude.toFixed(6));
    }, function () {
      alert('Unable to get location (permission denied?)');
    });
  });

  // City autocomplete (simple)
  let timer = null;
  $('.yrwp-city').on('input', function () {
    const $inp = $(this);
    const q = $inp.val().trim();
    clearTimeout(timer);
    if (q.length < 2) return;

    timer = setTimeout(function () {
      $.get(YRWP_ADMIN.ajax, { action: 'yrwp_city_search', nonce: YRWP_ADMIN.nonce, q }, function (resp) {
        if (!resp || !resp.success) return;

        // Minimal dropdown
        $('.yrwp-city-dd').remove();
        const results = resp.data.results || [];
        const $dd = $('<div class="yrwp-city-dd"></div>');
        results.forEach(function (r) {
          const $it = $('<div class="yrwp-city-item"></div>').text(r.label);
          $it.on('click', function () {
            $inp.val(r.label);
            $('input[name="yrwp[city_lat]"]').val(r.lat);
            $('input[name="yrwp[city_lon]"]').val(r.lon);
            $('input[name="yrwp[city_timezone]"]').val(r.timezone || '');
            $dd.remove();
          });
          $dd.append($it);
        });
        $inp.after($dd);
      });
    }, 250);
  });

  $(document).on('click', function (e) {
    if (!$(e.target).closest('.yrwp-city, .yrwp-city-dd').length) {
      $('.yrwp-city-dd').remove();
    }
  });

  $(document).on('click', '.yrwp-manual-token-help code', function () {
    const el = this;
    const text = $(el).text().trim();
    if (!text) return;

    const markCopied = function () {
      $(el).addClass('yrwp-token-copied');
      showTokenToast((typeof YRWP_ADMIN !== 'undefined' && YRWP_ADMIN.tokenCopied) ? YRWP_ADMIN.tokenCopied : 'Token copied to clipboard.');
      window.setTimeout(function () {
        $(el).removeClass('yrwp-token-copied');
      }, 1200);
    };

    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(text).then(markCopied).catch(function () {
        const range = document.createRange();
        range.selectNodeContents(el);
        const selection = window.getSelection();
        selection.removeAllRanges();
        selection.addRange(range);
        try {
          document.execCommand('copy');
          markCopied();
        } finally {
          selection.removeAllRanges();
        }
      });
      return;
    }

    const range = document.createRange();
    range.selectNodeContents(el);
    const selection = window.getSelection();
    selection.removeAllRanges();
    selection.addRange(range);
    try {
      document.execCommand('copy');
      markCopied();
    } finally {
      selection.removeAllRanges();
    }
  });

  $(document).on('click', '.yrwp-copy-shortcode', function () {
    const text = ($(this).data('shortcode') || '').toString().trim();
    if (!text) return;

    const markCopied = function () {
      showTokenToast((typeof YRWP_ADMIN !== 'undefined' && YRWP_ADMIN.shortcodeCopied) ? YRWP_ADMIN.shortcodeCopied : 'Shortcode copied to clipboard.');
    };

    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(text).then(markCopied).catch(function () {
        const $tmp = $('<input type="text" class="screen-reader-text" aria-hidden="true">').val(text).appendTo('body');
        $tmp.trigger('focus').trigger('select');
        try {
          document.execCommand('copy');
          markCopied();
        } finally {
          $tmp.remove();
        }
      });
      return;
    }

    const $tmp = $('<input type="text" class="screen-reader-text" aria-hidden="true">').val(text).appendTo('body');
    $tmp.trigger('focus').trigger('select');
    try {
      document.execCommand('copy');
      markCopied();
    } finally {
      $tmp.remove();
    }
  });

  (function initTemplatePreviewModal() {
    const $modal = $('#yrwp-template-preview-modal');
    if (!$modal.length) return;

    const $image = $modal.find('.yrwp-template-preview-modal__image');
    let hideTimer = null;
    const modalWidth = 700;
    const modalHeight = 600;
    const gap = 12;

    function positionPreview($trigger) {
      const rect = $trigger[0].getBoundingClientRect();
      const viewportWidth = window.innerWidth || document.documentElement.clientWidth || 0;
      const viewportHeight = window.innerHeight || document.documentElement.clientHeight || 0;
      const modalRect = $modal[0].getBoundingClientRect();
      const currentModalHeight = modalRect.height || modalHeight;

      let left = rect.right + gap;
      let top = rect.top - currentModalHeight - gap;

      const maxLeft = viewportWidth - modalWidth - gap;
      const minLeft = gap;
      const minTop = gap;
      const maxTop = viewportHeight - currentModalHeight - gap;

      if (left > maxLeft) {
        left = maxLeft;
      }
      if (left < minLeft) {
        left = minLeft;
      }

      if (top < minTop) {
        top = rect.bottom + gap;
      }
      if (top > maxTop) {
        top = maxTop;
      }
      if (top < minTop) {
        top = minTop;
      }

      $modal.css({
        left: left + 'px',
        top: top + 'px',
      });
    }

    function showPreview($trigger) {
      const src = ($trigger.data('previewUrl') || '').toString().trim();
      const title = ($trigger.data('previewTitle') || '').toString().trim();
      if (!src) return;

      window.clearTimeout(hideTimer);
      $image.attr('src', src);
      $image.attr('alt', title || 'Template preview');
      $modal.addClass('is-visible').attr('aria-hidden', 'false');

      if ($image[0].complete) {
        positionPreview($trigger);
        return;
      }

      $image.one('load', function () {
        positionPreview($trigger);
      });
    }

    function scheduleHide() {
      window.clearTimeout(hideTimer);
      hideTimer = window.setTimeout(function () {
        $modal.removeClass('is-visible').attr('aria-hidden', 'true');
      }, 90);
    }

    $(document).on('mouseenter', '.yrwp-template-preview-trigger', function () {
      showPreview($(this));
    });

    $(document).on('mouseleave', '.yrwp-template-preview-trigger', function () {
      scheduleHide();
    });

    $modal.on('mouseenter', function () {
      window.clearTimeout(hideTimer);
    });

    $modal.on('mouseleave', function () {
      scheduleHide();
    });
  })();
});
