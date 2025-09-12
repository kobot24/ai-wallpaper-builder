(function($){
  // Keep track of the last generated image URL so that a revision can display
  // both the original and revised images side by side. When no image has
  // been generated yet, this stays empty.
  let awb_last_image = '';
  // Format a number with given decimals and return as string. Does not add currency symbol.
  function formatNumber(n, decimals){
    n = parseFloat(n) || 0;
    const d = typeof decimals === 'number' ? decimals : 2;
    return n.toFixed(d);
  }
  $(function(){
    const $box = $('.awb-box'); if (!$box.length) return;
    const $form = $('form.cart').first();

    // Ensure hidden AIW meta inputs are inside the add-to-cart form so they submit
    try {
      if ($form.length){
        // Hidden meta fields may live outside the AWB box (e.g. after the order bar).
        // Move all elements with the aiw-hidden-meta class into the add‑to‑cart form
        // so they are submitted correctly.
        const $hidden = $('.aiw-hidden-meta');
        if ($hidden.length){ $hidden.appendTo($form); }
      }
    } catch(e){}

    function recalc(){
      const w = parseFloat($('.awb-width').val() || 0);
      const h = parseFloat($('.awb-height').val() || 0);
      // Convert centimetres to square metres (cm -> m)
      const sqm = (w / 100) * (h / 100);
      const pricePerSqm = parseFloat(AWB.price_per_sqm || 0);
      const decimals    = typeof AWB.decimals !== 'undefined' ? AWB.decimals : 2;
      const symbol      = AWB.currency_symbol || '';
      // Always multiply area by price per m² to compute the total. If pricePerSqm is zero,
      // leave total as zero so that the user sees 0 € until a variation selection is made.
      let total = 0;
      if (pricePerSqm > 0) {
        total = sqm * pricePerSqm;
      }
      // Format numbers
      const totalStr = formatNumber(total, decimals) + ' ' + symbol;
      let sqmPriceStr = '';
      if (pricePerSqm > 0) {
        if (AWB.price_per_sqm_formatted) {
          sqmPriceStr = AWB.price_per_sqm_formatted;
        } else {
          sqmPriceStr = formatNumber(pricePerSqm, decimals) + ' ' + symbol + ' / m²';
        }
      }
      // Update DOM
      // Write the calculated total into both the builder box and the new order bar
      $('.awb-box .total-price, .awb-order-bar .total-price').text(totalStr);
      if (sqmPriceStr) {
        // Update the square metre price in both containers and ensure they are visible
        $('.awb-box .sqm-price, .awb-order-bar .sqm-price').text(sqmPriceStr).show();
      } else {
        // Hide sqm price display when no price per square metre is defined
        $('.awb-box .sqm-price, .awb-order-bar .sqm-price').text('').hide();
      }
      // Update backwards‑compatible meta fields (if present)
      $('.awb-box .meta .area').text( AWB.i18n && AWB.i18n.area ? (AWB.i18n.area + ': ' + formatNumber(sqm, 2) + ' m²') : '' );
      $('.awb-box .meta .total').text( totalStr );
    }
    $box.on('input change', '.awb-width, .awb-height', recalc);
    // Trigger calculation on initial load
    recalc();

    const $modal = $('#awb-modal');

    // Track a user-selected image file and its data URL for preview purposes. When
    // the "Open AI aktivieren" checkbox is unchecked, the preview will use
    // this uploaded image instead of generating one. These variables are
    // updated in the file input change handler below.
    let aiw_user_file = null;
    let aiw_user_data_url = '';

    // Listen for file selection on the upload input. Use FileReader to
    // convert the selected image into a data URL so it can be displayed
    // immediately in the modal without uploading it. The actual upload is
    // performed asynchronously when the preview is opened.
    $(document).on('change', '#userImage', function(e){
      const file = (e.target && e.target.files && e.target.files[0]) ? e.target.files[0] : null;
      if(!file){ aiw_user_file = null; aiw_user_data_url = ''; return; }
      aiw_user_file = file;
      const reader = new FileReader();
      reader.onload = function(ev){
        aiw_user_data_url = ev.target.result || '';
      };
      reader.readAsDataURL(file);
    });

    // Helper to asynchronously upload a user-provided image to WordPress via
    // the awb_upload_user_image endpoint. Accepts a File object and a
    // callback of the form function(err, url){}. The nonce and action are
    // appended automatically. Errors are returned via the first argument.
    function uploadUserImage(file, cb){
      if(!file){ if(cb) cb('no_file'); return; }
      const fd = new FormData();
      fd.append('file', file);
      fd.append('action', 'awb_upload_user_image');
      fd.append('nonce', AWB.nonce);
      $.ajax({
        url: AWB.ajax,
        type: 'POST',
        data: fd,
        processData: false,
        contentType: false,
        success: function(res){
          if(res && res.success && res.data && res.data.url){
            cb && cb(null, res.data.url);
          } else {
            cb && cb(res && res.data ? res.data : 'Upload error');
          }
        },
        error: function(xhr){
          cb && cb('HTTP '+xhr.status);
        }
      });
    }
    // Important fix: ensure the modal is NOT inside the WooCommerce add-to-cart form.
    // If the modal lives inside the form, required fields inside the modal (even when hidden)
    // trigger HTML5 validation and block the form submission. Move it to <body>.
    if ($modal.length) {
      $modal.appendTo(document.body);
    }
    /**
     * Open the full screen modal. Copy values from the main builder into the
     * corresponding inputs inside the modal (width/height and dynamic fields)
     * so that the user sees the same data and can adjust it if needed.
     */
    function openModal(){
      // Copy size values
      var wVal = $('.awb-width').first().val();
      var hVal = $('.awb-height').first().val();
      $modal.find('input[name="awb_width_modal"]').val(wVal);
      $modal.find('input[name="awb_height_modal"]').val(hVal);
      // Copy dynamic field values: look for inputs whose name starts with awb_ and ends
      // with the field key, then copy to the corresponding *_modal input inside the modal.
      $('.awb-box').find('[name^="awb_"]').each(function(){
        var name = $(this).attr('name');
        // Skip hidden image field
        if(name === 'awb_image') return;
        // Derive modal name by appending _modal
        var modalName = name + '_modal';
        var val = $(this).val();
        $modal.find('[name="'+modalName+'"]').val(val);
      });
      $modal.show().addClass('open');
    }

    /**
     * Close the modal and reset its state. Clears preview images and hides the
     * progress overlay and revision area.
     */
    function closeModal(){
      $modal.removeClass('open').hide();
      // Clear preview images but keep the overlay
      $modal.find('.awb-preview-images').empty();
      // Hide and reset progress overlay text
      $modal.find('.progress-overlay').removeClass('visible').find('.pct').text('0%');
      // Hide revision area and clear its text
      $modal.find('.awb-modal-revision').hide();
      $modal.find('.revise-text').val('');
    }

    function startProgress(){
      // Show the progress overlay and update its percentage text periodically.
      const $overlay = $modal.find('.progress-overlay');
      $overlay.addClass('visible');
      let p = 0;
      let pulseDirection = 1; // For pulsing effect at 98%
      
      // More realistic progress distribution for 30-120s OpenAI response times
      const t = setInterval(function(){
        if (p < 70){
          // Fast initial progress (0-70% in ~8 seconds)
          p = Math.min(70, p + 4);
        } else if (p < 85){
          // Slower progress (70-85% in ~6 seconds) 
          p = Math.min(85, p + 2);
        } else if (p < 96){
          // Very slow progress (85-96% in ~12 seconds)
          p = Math.min(96, p + 0.5);
        } else if (p < 98){
          // Crawling to 98% (96-98% in ~4 seconds)
          p = Math.min(98, p + 0.25);
        } else {
          // Pulse between 98-99% to show activity
          p = 98 + pulseDirection * 0.5;
          if (p >= 99) pulseDirection = -1;
          if (p <= 98) pulseDirection = 1;
        }
        $overlay.find('.pct').text(Math.floor(p) + '%');
      }, 500); // Slower updates for smoother feel
      
      return function stop(){
        clearInterval(t);
        $overlay.find('.pct').text('100%');
        setTimeout(function(){ $overlay.removeClass('visible'); }, 400);
      };
    }

    /**
     * Gather the values for name, description and style to send to the server.
     * When the modal is open, prefer the values from the modal fields; otherwise
     * fallback to the main builder inputs.
     */
    function collect(){
      var name = '', desc = '', style = '';
      var meta_fields = {};
      
      if($modal.is(':visible')){
        // Use modal fields if present
        var $nm = $modal.find('[name^="awb_name_"]').filter('input');
        if($nm.length) name = $nm.val() || '';
        var $ds = $modal.find('[name^="awb_beschreibung_"]').filter('textarea');
        if($ds.length) desc = $ds.val() || '';
        var $st = $modal.find('[name^="awb_stil_"]').filter('input');
        if($st.length) style = $st.val() || '';
        
        // Collect all other awb_ fields for meta placeholders
        $modal.find('[name^="awb_"]').each(function(){
          var name_attr = $(this).attr('name');
          var value = $(this).val() || '';
          if (name_attr && value) {
            meta_fields[name_attr] = value;
          }
        });
      }
      
      // Fallback to main builder inputs
      if(!name){ name = $('[name^="awb_name"], [name*="name"]').filter('input').first().val() || ''; }
      if(!desc){ desc = $('[name^="awb_beschreibung"], [name*="beschreibung"], [name*="description"]').filter('textarea').first().val() || ''; }
      if(!style){ style = $('[name^="awb_stil"], [name*="stil"], [name*="style"]').filter('input').first().val() || ''; }
      
      // Also collect from main form if not in modal
      if(!$modal.is(':visible')){
        $('[name^="awb_"]').each(function(){
          var name_attr = $(this).attr('name');
          var value = $(this).val() || '';
          if (name_attr && value) {
            meta_fields[name_attr] = value;
          }
        });
      }
      
      return {name, desc, style, meta_fields};
    }

    function setHidden(url){
      if (!$form.length) return;
      let $h = $form.find('input[name="awb_image"]');
      if (!$h.length){ $h = $('<input type="hidden" name="awb_image">').appendTo($form); }
      $h.val(url);
    }

    function generate(correction){
      const stop = startProgress();
      const f = collect();
      const payload = {action:'awb_generate', nonce:AWB.nonce, product_id:AWB.product_id, name:f.name, description:f.desc, style:f.style};
      // Add all meta fields to payload
      if (f.meta_fields) {
        Object.assign(payload, f.meta_fields);
      }
      if (correction) payload.correction = correction;
      $.post(AWB.ajax, payload, function(res){
        stop();
        const $imgContainer = $modal.find('.awb-preview-images');
        if (res && res.success){
          const url = res.data.url;
          // If the response contains the prompt used for generation, store it in
          // a hidden field so that it can be persisted with the order. The
          // prompt is returned from the server via the awb_generate endpoint.
          if(res.data.prompt){
            // Update hidden OpenAI prompt meta field on the main form
            $form.find('.aiw-openai-prompt').val(res.data.prompt);
          }
          // Prepare content based on whether this is a correction and we have a previous image
          $imgContainer.empty();
          if (correction && awb_last_image){
            // Show tabs to toggle between original and new images
            // Use a distinct tab key for the corrected image (corr1) to avoid
            // collisions with reserved words. Tab labels are pulled from
            // translations or default to German. The content panes use the same keys.
            const tabs = '<div class="awb-tabs"'
              + '>'
              + '<button type="button" class="awb-tab-btn active" data-tab="orig">'+(AWB.i18n && AWB.i18n.original ? AWB.i18n.original : 'Vorschau')+'</button>'
              + '<button type="button" class="awb-tab-btn" data-tab="corr1">'+(AWB.i18n && AWB.i18n.new ? AWB.i18n.new : 'Korrektur 1')+'</button>'
              + '</div>';
            const panes = '<div class="awb-tab-content"'
              + '>'
              + '<div class="awb-pane orig active"><img src="'+awb_last_image+'" class="awb-img"></div>'
              + '<div class="awb-pane corr1"><img src="'+url+'" class="awb-img"></div>'
              + '</div>';
            $imgContainer.append(tabs + panes);
          } else {
            // Initial generation: show a single image
            $imgContainer.append('<img src="'+url+'" class="awb-img">');
          }
          // Attempt to save the remote image via the order bridge to obtain a local URL
          
          function useLocal(fallbackUrl){
            if (window.AWB_BRIDGE && AWB_BRIDGE.ajax && AWB_BRIDGE.nonce){
              $.post(AWB_BRIDGE.ajax, { action:'awb_bridge_save_image', nonce:AWB_BRIDGE.nonce, img:fallbackUrl }, function(r){
                if (r && r.success && r.data && r.data.url){
                  var local = r.data.url;
                  // Verify that the returned local URL actually loads before swapping preview images.
                  var testImg = new Image();
                  testImg.onload = function(){
                    // Swap all preview images to the local URL
                    $imgContainer.find('img.awb-img').each(function(){ $(this).attr('src', local); });
                    setHidden(local);
                    awb_last_image = local;
                  };
                  testImg.onerror = function(){
                    // Keep the working fallback and store it
                    setHidden(fallbackUrl);
                    awb_last_image = fallbackUrl;
                  };
                  testImg.src = local;
                } else {
                  setHidden(fallbackUrl);
                  awb_last_image = fallbackUrl;
                }
              }).fail(function(){
                setHidden(fallbackUrl);
                awb_last_image = fallbackUrl;
              });
            } else {
              setHidden(fallbackUrl);
              awb_last_image = fallbackUrl;
            }
          }

          useLocal(url);
        } else {
          // If the response contains a specific error (e.g. blocked keyword), display it
          // in a separate alert and close the modal. Otherwise show a generic error inside
          // the modal. This ensures blocked keywords are handled before any preview.
          var msg = (res && res.data) ? res.data : 'Fehler';
          // Detect blocked keyword: our server returns a German string containing
          // Lizenz Hinweis. You can adjust the condition as needed.
          if(msg && msg.indexOf('Lizenz') !== -1){
            closeModal();
            alert(msg);
          } else {
            $imgContainer.empty();
            $imgContainer.append('<div class="awb-error">'+msg+'</div>');
          }
        }
      }).fail(function(xhr){
        stop();
        const $imgContainer = $modal.find('.awb-preview-images');
        $imgContainer.empty();
        $imgContainer.append('<div class="awb-error">HTTP '+xhr.status+'</div>');
      });
    }

    // Preview button: delegate to any element with class awb-preview anywhere in the DOM.
    // Previously this handler was bound only to the .awb-box container, but the
    // new layout places the primary preview button outside of that box. Using
    // document‑level delegation ensures the click is captured regardless of
    // where the button resides.
    $(document).on('click','.awb-preview', function(){
      // Before opening the modal, perform a blacklist check on user inputs.
      try {
        if (Array.isArray(AWB.blacklist) && AWB.blacklist.length){
          var f = collect();
          // Combine all user‑entered texts for scanning and convert to lower case.
          var haystack = String(f.name + ' ' + f.desc + ' ' + f.style).toLowerCase();
          var blocked = '';
          AWB.blacklist.forEach(function(word){
            var w = String(word || '').toLowerCase().trim();
            if (!w) return;
            // Simple substring match; can be enhanced with regex or word boundaries.
            if (haystack.indexOf(w) !== -1 && !blocked){ blocked = word; }
          });
          if (blocked){
            alert('Leider können wir aus Lizenzgründen keine Designs mit '+blocked+' erstellen. Bitte passen Sie Ihre Beschreibung an.');
            return;
          }
        }
      } catch(ex){ console.error(ex); }
      // Open the modal first to synchronise values. The openModal() call copies
      // the size and dynamic fields into the modal. After the modal is shown
      // we use the per‑product OpenAI flag to determine whether to generate or use
      // the administrator‑provided default image.
      openModal();
      // Delay execution slightly so that the modal DOM exists and values have been copied.
      setTimeout(function(){
        // Determine whether AI generation is enabled for this product. The value
        // is passed via AWB.aiw_use_openai from PHP and may be boolean or a string.
        var useAI = false;
        try {
          if (typeof AWB.aiw_use_openai !== 'undefined'){
            useAI = !!AWB.aiw_use_openai;
          }
        } catch(e){}
        // Fallback: if AWB.aiw_use_openai is undefined, read from hidden input
        if(!useAI){
          var $fallbackUse = $form.find('.aiw-use-openai');
          if($fallbackUse.length){
            var v = $fallbackUse.val();
            if(v === '1' || v === 'true'){ useAI = true; }
          }
        }
        if(useAI){
          // When OpenAI is enabled, generate a new image via the API. The generate()
          // function will handle progress, preview display and saving hidden fields.
          generate();
        } else {
          // OpenAI is disabled: use the administrator‑provided default image as the
          // preview source. Retrieve the URL from AWB.aiw_default_image.
          var url = '';
          try { url = AWB.aiw_default_image || ''; } catch(e){}
          // Fallback: if AWB.aiw_default_image is empty, use the hidden input value
          if(!url){
            var $fallbackInput = $form.find('.aiw-default-image-url');
            if($fallbackInput.length){
              url = $fallbackInput.val() || '';
            }
          }
          if(!url){
            alert('Es wurde kein Standardbild für dieses Produkt festgelegt. Bitte wenden Sie sich an den Shop‑Betreiber.');
            closeModal();
            return;
          }
          // Display the default image in the preview area. Remove existing
          // content and insert a single image. The MutationObserver in
          // aiw-modal.js will detect this and initialise the cropping overlay.
          var $imgContainer = $modal.find('.awb-preview-images');
          $imgContainer.empty();
          $imgContainer.append('<img src="'+url+'" class="awb-img">');
          // Compute and store the ratio (width_cm / height_cm) based on the modal inputs.
          var wVal = parseFloat($modal.find('input[name="awb_width_modal"]').val() || 0);
          var hVal = parseFloat($modal.find('input[name="awb_height_modal"]').val() || 1);
          var ratio = 0;
          if(hVal > 0){ ratio = wVal / hVal; }
          if(!isFinite(ratio) || ratio <= 0){ ratio = 0; }
          $form.find('.aiw-ratio').val(ratio ? ratio.toFixed(6) : '');
          // Set bahnen active to true so that lane overlay is saved. We store
          // a boolean string; the lane width itself will be stored by AIW_UpdateHiddenMeta().
          $form.find('.aiw-bahnen').val('true');
          // Store the default image URL in the hidden user image field. This
          // will be persisted with the order.
          $form.find('.aiw-user-image-url').val(url);
          // Clear any previously stored OpenAI prompt value.
          $form.find('.aiw-openai-prompt').val('');
          // Update awb_last_image to the default image so that corrections
          // use this as the original preview.
          awb_last_image = url;
        }
      }, 20);
    });

    // Apply (Bild verwenden) -> Copy width/height from the modal back to the main
    // builder inputs, recalculate pricing and then close the modal. Hidden image
    // field is already set via setHidden() during generation.
    $box.on('click','.awb-apply', function(){
      // When the modal is visible, copy the size values to the main inputs.
      if($modal.is(':visible')){
        var mw = $modal.find('input[name="awb_width_modal"]').val() || '';
        var mh = $modal.find('input[name="awb_height_modal"]').val() || '';
        // Update the main builder inputs and trigger recalculation
        $('.awb-box').find('input[name="awb_width"], .awb-box .awb-width').val(mw).trigger('input');
        $('.awb-box').find('input[name="awb_height"], .awb-box .awb-height').val(mh).trigger('input');
      }
      closeModal();
    });

    // Open/close revision area. On click of the revise button, toggle the
    // visibility of the modal's revision block. The revision area is within
    // .awb-modal-revision; show it when hidden and hide when visible.
    $(document).on('click', '.awb-revise', function(e){
      e.preventDefault();
      var $rev = $modal.find('.awb-modal-revision');
      if ($rev.is(':visible')){
        $rev.hide();
      } else {
        $rev.show();
      }
    });

    // Send revision. When the user submits a correction, collect the note from
    // the revision textarea, clear the preview images and generate a new image.
    $(document).on('click', '.awb-send-revision', function(){
      var $txt = $modal.find('.awb-modal-revision .revise-text');
      const note = $txt.val().trim();
      if (!note){ $txt.focus(); return; }
      // Clear preview images but keep overlay
      $modal.find('.awb-preview-images').empty();
      generate(note);
    });

    // Close modal. Bind to both the standard close button and the X icon in
    // the modal header. This ensures the modal can be closed from anywhere.
    $(document).on('click', '.awb-close', function(){
      closeModal();
    });

    /*
     * When a variable product variation is selected, update the price per m² to the
     * selected variation’s price. WooCommerce triggers the `found_variation` event
     * on the variations form with the variation data. We hook into that event
     * to update the pricing variables and recalculate the totals.
     */
    $('form.variations_form').on('found_variation', function(e, variation){
      if (!variation) return;
      if (typeof variation.display_price !== 'undefined'){
        var vPrice = parseFloat(variation.display_price);
        if (!isNaN(vPrice) && vPrice > 0){
          AWB.price_per_sqm = vPrice;
          // Update formatted string for per m² display
          var dec = typeof AWB.decimals !== 'undefined' ? AWB.decimals : 2;
          var sym = AWB.currency_symbol || '';
          AWB.price_per_sqm_formatted = formatNumber(vPrice, dec) + ' ' + sym + ' / m²';
          // Recalculate totals
          recalc();
        }
      }
    });

    // Tab switching for revision images. When a tab button is clicked, show the
    // corresponding image pane and update the active state.
    $(document).on('click', '.awb-tab-btn', function(){
      var $btn = $(this);
      var tab = $btn.data('tab');
      // Activate the clicked tab button and deactivate its siblings
      $btn.addClass('active').siblings('.awb-tab-btn').removeClass('active');
      // The tab content is expected to follow the .awb-tabs container directly. Find
      // the nearest .awb-tabs ancestor and select its sibling .awb-tab-content.
      var $container = $btn.closest('.awb-tabs');
      var $content = $container.next('.awb-tab-content');
      if ($content.length){
        $content.find('.awb-pane').removeClass('active');
        $content.find('.awb-pane.'+tab).addClass('active');
      }
    });

    // Card selection styling: when a radio input inside a card is changed,
    // add the 'selected' class to its parent card and remove from siblings.
    $box.on('change', '.cards input[type="radio"]', function(){
      var $card = $(this).closest('.card');
      // Within this cards container, remove selection from others
      $card.siblings('.card').removeClass('selected');
      $card.addClass('selected');
    });
    // Same logic for cards within the modal form column
    $(document).on('change', '.awb-modal .cards input[type="radio"]', function(){
      var $card = $(this).closest('.card');
      $card.siblings('.card').removeClass('selected');
      $card.addClass('selected');
    });
  });
})(jQuery);
