jQuery(function ($) {

  let $priceBox = null;

  function nf(amount) {
    try {
      return new Intl.NumberFormat('de-DE', { style: 'currency', currency: 'EUR' }).format(amount);
    } catch (e) {
      amount = parseFloat(amount) || 0;
      return (amount.toFixed(2) + ' €').replace('.', ',');
    }
  }

  function hasBuilder() {
    return $('.awb-box').length &&
           $('input.awb-width[name="awb_width"]').length &&
           $('input.awb-height[name="awb_height"]').length;
  }

  function getWH() {
    const w = parseFloat(String($('.awb-width[name="awb_width"]').val() || '').replace(',', '.')) || 0;
    const h = parseFloat(String($('.awb-height[name="awb_height"]').val() || '').replace(',', '.')) || 0;
    return { w, h };
  }

  function consolidateBox() {
    if ($priceBox && $priceBox.length) {
      // Remove any other variation-price boxes to prevent duplicates after Woo re-renders
      $('.woocommerce-variation-price').not($priceBox).remove();
    }
  }

  function moveBoxOnce() {
    if (!hasBuilder()) return;

    if (!$priceBox || !$priceBox.length) {
      // Use any existing price box and move it below our builder
      const $any = $('.woocommerce-variation-price').first();
      const $wrap = $('.awb-box').last();
      if ($any.length && $wrap.length) {
        $any.insertAfter($wrap);   // Move original (do not clone)
        $priceBox = $any;
      }
    }
    consolidateBox();
  }

  function getPerSqm() {
    // 1) Prefer numeric value from AWB (no parsing issues)
    if (window.AWB && typeof AWB.price_per_sqm !== 'undefined' && !isNaN(parseFloat(AWB.price_per_sqm))) {
      return parseFloat(AWB.price_per_sqm) || 0;
    }
    // 2) Fallback: parse the text inside .sqm-price (e.g. "33,00 € / m²")
    const sq = ($('.awb-price .sqm-price').first().text() || '');
    let raw = sq;
    if (!raw) {
      // 3) Final fallback: use the LAST currency occurrence inside .awb-price
      const txt = ($('.awb-price').first().text() || '');
      const matches = txt.match(/([\d.,]+)\s*€/g) || [];
      raw = matches.length ? matches[matches.length - 1] : '';
    }
    // Robust number parser for "1.234,56" and "1234.56"
    const m = String(raw).match(/[\d.,]+/);
    if (!m) return 0;
    const str = m[0];
    if (str.indexOf(',') >= 0) {
      // German style: comma decimal, dot thousands
      return parseFloat(str.replace(/\./g, '').replace(',', '.')) || 0;
    }
    // English style: dot decimal or plain integer
    return parseFloat(str) || 0;
  }


  function render() {
    if (!$priceBox || !hasBuilder()) return;

    const perSqm = getPerSqm();
    const { w, h } = getWH();
    const sqm = (w > 0 && h > 0) ? (w / 100) * (h / 100) : 0;
    const total = (perSqm > 0 && sqm > 0) ? perSqm * sqm : 0;

    if (total > 0 && perSqm > 0) {
      const html =
        nf(total) + ' inkl. MwSt. zzgl. Versand<br>' +
        'Grundpreis: ' + nf(perSqm) + ' / qm<br>' +
        'Lieferzeit: 3 bis 5 Tage*';

      if ($priceBox.find('.price').length) {
        $priceBox.find('.price').html(html);
      } else {
        $priceBox.html('<span class="price">' + html + '</span>');
      }

      // Hide Germanized extra info inside this block to avoid duplicated lines
      $priceBox.find('.wgm-info').hide();
    }
  }

  // Initial
  moveBoxOnce();
  render();

  // Listen to variation events (namespaced to avoid multiple binds)
  $('.variations_form')
    .off('found_variation.aiwb reset_data.aiwb woocommerce_variation_has_changed.aiwb')
    .on('found_variation.aiwb reset_data.aiwb woocommerce_variation_has_changed.aiwb', function () {
      moveBoxOnce();
      render();
    });

  // React to size inputs changes
  $('.awb-width[name="awb_width"], .awb-height[name="awb_height"]')
    .off('input.aiwb change.aiwb')
    .on('input.aiwb change.aiwb', function () {
      render();
    });

  // Watch for changes to .awb-price (if your builder updates it)
  const ap = document.querySelector('.awb-price');
  if (ap && window.MutationObserver) {
    new MutationObserver(function () {
      moveBoxOnce();
      render();
    }).observe(ap, { childList: true, characterData: true, subtree: true });
  }
});