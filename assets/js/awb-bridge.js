(function($){
  // Utility to find the visible preview image inside the AWB lightbox
  function findPreviewImage(){
    // Common containers: anything with 'awb' and 'light' in id/class, else fallback to biggest image on page
    var $candidates = $('.awb-lightbox, #awb-lightbox, .awb-modal, .mfp-content, .awp-preview-modal, .lightbox, .modal:visible').filter(':visible');
    var $img = null;
    $candidates.each(function(){
      var img = $(this).find('img:visible').last();
      if (img.length){ $img = img; return false; }
    });
    if (!$img || !$img.length){
      // Fallback: last large image on screen
      var imgs = $('img:visible').get().sort(function(a,b){ return (b.naturalWidth*b.naturalHeight)-(a.naturalWidth*a.naturalHeight);});
      if (imgs.length) $img = $(imgs[0]);
    }
    return $img;
  }

  function ensureButtons(){
    // Try to identify the existing buttons by text and annotate them with known classes
    $('button, a, .button').filter(function(){
      var t = $(this).text().trim().toLowerCase();
      return t === 'bild verwenden' || t === 'korrektur einreichen' || t === 'schließen' || t === 'schliessen';
    }).each(function(){
      var t = $(this).text().trim().toLowerCase();
      if (t === 'bild verwenden') $(this).addClass('awb-use-image');
      if (t === 'korrektur einreichen') {
        // Do not annotate the correction button here. The frontend script handles the
        // revision UI directly using its own .awb-revise handler. Leaving this empty
        // avoids duplicate event handlers and class conflicts.
      }
      if (t === 'schließen' || t === 'schliessen') $(this).addClass('awb-close-image');
    });
  }

  function attachHandlers(){
    ensureButtons();

    // Use Image -> save, submit add-to-cart, redirect to cart
    $(document).off('click.awbUse', '.awb-use-image').on('click.awbUse', '.awb-use-image', function(e){
      e.preventDefault();
      var $img = findPreviewImage();
      if (!$img || !$img.length){ alert('Kein Bild gefunden.'); return; }
      var src = $img.attr('src');
      if (!src){ alert('Kein Bild gefunden.'); return; }

      // Show tiny state
      var $btn = $(this); $btn.prop('disabled', true).addClass('is-busy');

      $.post(AWB_BRIDGE.ajax, { action:'awb_bridge_save_image', nonce:AWB_BRIDGE.nonce, img:src }, function(resp){
        if (!resp || !resp.success){
          alert((resp && resp.data && resp.data.msg) ? resp.data.msg : 'Speichern fehlgeschlagen');
          $btn.prop('disabled',false).removeClass('is-busy');
          return;
        }

        // Submit current product form to add to cart and redirect
        var $form = $('form.cart').first();
        if ($form.length){
          // add redirect flag
          if (!$form.find('input[name="awb_to_cart"]').length){
            $('<input type="hidden" name="awb_to_cart" value="1">').appendTo($form);
          } else {
            $form.find('input[name="awb_to_cart"]').val('1');
          }
          // If product uses AJAX add-to-cart, trigger click – Woo will redirect via filter
          var $btnAdd = $form.find('.single_add_to_cart_button').first();
          if ($btnAdd.length){
            $btnAdd.trigger('click');
            // Fallback hard redirect after short delay
            setTimeout(function(){ window.location.href = AWB_BRIDGE.redir; }, 1200);
          } else {
            $form.trigger('submit');
            setTimeout(function(){ window.location.href = AWB_BRIDGE.redir; }, 1200);
          }
        } else {
          window.location.href = AWB_BRIDGE.redir;
        }
      }).fail(function(){
        alert('Netzwerkfehler beim Speichern.');
        $btn.prop('disabled',false).removeClass('is-busy');
      });
    });

    // Close
    $(document).off('click.awbClose', '.awb-close-image').on('click.awbClose', '.awb-close-image', function(e){
      e.preventDefault();
      // try common lightbox libs
      $('.mfp-close, .fancybox-close-small, .awp-close, .awb-close, .close').trigger('click');
      // also remove any overlay we can
      $('.mfp-wrap, .fancybox-container, .awb-lightbox, .awp-preview-modal, .modal').hide();
    });

    // No correction handler here. The frontend script handles the revision UI on its own.
  }

  // Initial and on DOM changes
  $(function(){
    attachHandlers();
    // observe for dynamic lightbox insertion
    var obs = new MutationObserver(function(){ attachHandlers(); });
    obs.observe(document.documentElement, {childList:true, subtree:true});
  });

})(jQuery);
