/*
 * AIW modal enhancements - SIMPLIFIED VERSION
 * Fixed infinite loop issues by removing recursive calls
 */

(function(){
  function onReady(fn){
    if(document.readyState === 'complete' || document.readyState === 'interactive'){
      setTimeout(fn, 0);
    } else {
      document.addEventListener('DOMContentLoaded', fn);
    }
  }

  onReady(function(){
    var modal = document.getElementById('awb-modal');
    if(!modal) return;
    
    // PERFORMANCE: Cache all DOM elements once
    var domCache = {
      modal: modal,
      stage: modal.querySelector('.aiw-preview__stage'),
      widthInput: modal.querySelector('#aiwWidthCm') || modal.querySelector('input[name="awb_width_modal"]'),
      heightInput: modal.querySelector('#aiwHeightCm') || modal.querySelector('input[name="awb_height_modal"]'),
      previewContainer: modal.querySelector('.awb-preview-images'),
      laneSelect: modal.querySelector('#aiwLaneSelect'),
      form: document.querySelector('form.cart')
    };
    
    var stage = domCache.stage;
    var widthInput = domCache.widthInput;
    var heightInput = domCache.heightInput;
    var previewContainer = domCache.previewContainer;
    
    console.log('Found inputs:', {
      widthInput: widthInput ? widthInput.id || widthInput.name : 'NOT FOUND',
      heightInput: heightInput ? heightInput.id || heightInput.name : 'NOT FOUND'
    });
    
    if(!stage || !widthInput || !heightInput) return;
    
    var canvasCrop = null;
    var cropContainer = null;
    var currentImageSrc = null;
    var currentCropData = { ratio: 4.5, cropX: 0.5 };
    
    console.log('Canvas crop modal system initialized (SIMPLIFIED)');
    
    // SIMPLIFIED: Only save to hidden fields, NO loops
    function saveToHiddenFields(data) {
      var form = domCache.form;
      if (!form) return;
      
      if (data.ratio !== undefined) {
        var ratioField = form.querySelector('.aiw-ratio');
        if (!ratioField) {
          ratioField = document.createElement('input');
          ratioField.type = 'hidden';
          ratioField.name = 'aiwallpaper_ratio';
          ratioField.className = 'aiw-ratio';
          form.appendChild(ratioField);
        }
        ratioField.value = data.ratio.toFixed(6);
        currentCropData.ratio = data.ratio;
      }
      
      if (data.cropX !== undefined) {
        var cropField = form.querySelector('.aiw-crop-x');
        if (!cropField) {
          cropField = document.createElement('input');
          cropField.type = 'hidden';
          cropField.name = 'aiwallpaper_crop_x';
          cropField.className = 'aiw-crop-x';
          form.appendChild(cropField);
        }
        cropField.value = data.cropX.toFixed(4);
        currentCropData.cropX = data.cropX;
      }
      
      // Save cropped image URL
      if (data.croppedImageUrl !== undefined) {
        var imageUrlField = form.querySelector('.aiw-cropped-image-url');
        if (!imageUrlField) {
          imageUrlField = document.createElement('input');
          imageUrlField.type = 'hidden';
          imageUrlField.name = 'aiwallpaper_cropped_image_url';
          imageUrlField.className = 'aiw-cropped-image-url';
          form.appendChild(imageUrlField);
        }
        imageUrlField.value = data.croppedImageUrl;
        currentCropData.croppedImageUrl = data.croppedImageUrl;
      }
      
      // Save cropped image filename
      if (data.croppedImageFile !== undefined) {
        var imageFileField = form.querySelector('.aiw-cropped-image-file');
        if (!imageFileField) {
          imageFileField = document.createElement('input');
          imageFileField.type = 'hidden';
          imageFileField.name = 'aiwallpaper_cropped_image_file';
          imageFileField.className = 'aiw-cropped-image-file';
          form.appendChild(imageFileField);
        }
        imageFileField.value = data.croppedImageFile;
        currentCropData.croppedImageFile = data.croppedImageFile;
      }
      
      // Save width
      if (data.width !== undefined) {
        var widthField = form.querySelector('.aiw-width');
        if (!widthField) {
          widthField = document.createElement('input');
          widthField.type = 'hidden';
          widthField.name = 'awb_width_modal';
          widthField.className = 'aiw-width';
          form.appendChild(widthField);
        }
        widthField.value = data.width;
        currentCropData.width = data.width;
      }
      
      // Save height
      if (data.height !== undefined) {
        var heightField = form.querySelector('.aiw-height');
        if (!heightField) {
          heightField = document.createElement('input');
          heightField.type = 'hidden';
          heightField.name = 'awb_height_modal';
          heightField.className = 'aiw-height';
          form.appendChild(heightField);
        }
        heightField.value = data.height;
        currentCropData.height = data.height;
      }
      
      console.log('Saved to hidden fields:', data);
    }

    function getCurrentAspect() {
      var w = parseFloat(widthInput.value) || 900;
      var h = parseFloat(heightInput.value) || 200;
      return w / h;
    }

    function updateStageOnly() {
      if (!stage) return;
      
      var aspect = getCurrentAspect();
      var currentWidth = stage.clientWidth || 800;
      var newHeight = currentWidth / aspect;
      stage.style.height = newHeight + 'px';
      
      console.log('Stage updated - aspect:', aspect);
    }

    function updateCropOnly() {
      if (!canvasCrop) {
        console.log('Cannot update crop - canvasCrop not initialized');
        return;
      }
      
      var aspect = getCurrentAspect();
      console.log('Updating crop with aspect:', aspect);
      canvasCrop.setAspect(aspect);
      
      console.log('Crop updated successfully');
    }

    function createCropContainer() {
      if (cropContainer) return;
      
      cropContainer = document.createElement('div');
      cropContainer.id = 'awb-canvas-crop-container';
      cropContainer.style.cssText = 'position:absolute;top:0;left:0;right:0;bottom:0;z-index:10;';
      
      stage.appendChild(cropContainer);
      createRuler();
      createLaneOverlay();
    }

    function createRuler() {
      if (!stage) {
        console.log('Stage not found, cannot create ruler');
        return;
      }
      
      // Remove existing ruler
      var existingRuler = stage.querySelector('.awb-ruler');
      if (existingRuler) {
        existingRuler.remove();
      }
      
      // COMPLETELY NEW MATERIAL UI RULER
      var ruler = document.createElement('div');
      ruler.className = 'awb-ruler';
      ruler.style.cssText = 'position:absolute;top:0;left:0;right:0;height:32px;background:#fafafa;border-bottom:1px solid #e0e0e0;z-index:9999;display:flex;align-items:flex-end;font-family:Roboto,sans-serif;pointer-events:none;';
      
      // Get current dimensions
      var currentWidth = parseFloat(widthInput.value) || 900;
      var stageWidth = stage.clientWidth || 800;
      var pixelsPerCm = stageWidth / currentWidth; // FIXED CALCULATION!
      
      console.log('🚀 NEW RULER - width:', currentWidth + 'cm', 'stage:', stageWidth + 'px', 'pixels per cm:', pixelsPerCm);
      
      // Create markers every 50cm up to customer input
      for (var cm = 0; cm <= currentWidth; cm += 50) {
        var pixelPosition = cm * pixelsPerCm; // CORRECT CALCULATION!
        
        // Skip if position is beyond stage width
        if (pixelPosition > stageWidth) break;
        
        // Marker line
        var marker = document.createElement('div');
        marker.style.cssText = 'position:absolute;width:1px;height:12px;background:#666;bottom:0;left:' + pixelPosition + 'px;';
        
        // Label with proper positioning
        var label = document.createElement('div');
        label.textContent = cm + 'cm';
        label.style.cssText = 'position:absolute;bottom:14px;left:' + Math.max(0, pixelPosition - 15) + 'px;width:30px;text-align:center;font-size:10px;color:#666;font-weight:400;background:#fafafa;padding:2px 4px;border-radius:2px;border:1px solid #e0e0e0;white-space:nowrap;';
        
        ruler.appendChild(marker);
        ruler.appendChild(label);
        
        console.log('✅ Ruler marker at', cm + 'cm', '→', pixelPosition + 'px');
      }
      
      // Add final marker at customer input value if not already added
      if (currentWidth % 50 !== 0) {
        var finalPixelPosition = currentWidth * pixelsPerCm;
        if (finalPixelPosition <= stageWidth) {
          // Final marker line
          var finalMarker = document.createElement('div');
          finalMarker.style.cssText = 'position:absolute;width:2px;height:16px;background:#333;bottom:0;left:' + finalPixelPosition + 'px;';
          
          // Final label
          var finalLabel = document.createElement('div');
          finalLabel.textContent = currentWidth + 'cm';
          finalLabel.style.cssText = 'position:absolute;bottom:14px;left:' + Math.max(0, finalPixelPosition - 15) + 'px;width:30px;text-align:center;font-size:10px;color:#333;font-weight:600;background:#fafafa;padding:2px 4px;border-radius:2px;border:2px solid #333;white-space:nowrap;';
          
          ruler.appendChild(finalMarker);
          ruler.appendChild(finalLabel);
          
          console.log('✅ Final ruler marker at', currentWidth + 'cm', '→', finalPixelPosition + 'px');
        }
      }
      
      // Add intermediate marks every 25cm (without numbers)
      for (var cm = 25; cm <= currentWidth; cm += 50) {
        var pixelPosition = cm * pixelsPerCm;
        
        // Skip if position is beyond stage width
        if (pixelPosition > stageWidth) break;
        
        // Smaller intermediate marker
        var intermediateMarker = document.createElement('div');
        intermediateMarker.style.cssText = 'position:absolute;width:1px;height:6px;background:#999;bottom:0;left:' + pixelPosition + 'px;opacity:0.6;';
        
        ruler.appendChild(intermediateMarker);
        
        console.log('✅ Intermediate marker at', cm + 'cm', '→', pixelPosition + 'px');
      }
      
      // Position ruler INSIDE stage for perfect alignment
      stage.appendChild(ruler);
      console.log('✅ Ruler positioned INSIDE stage for perfect alignment');
      
      console.log('Ruler created with', Math.floor(currentWidth / 50) + 1, 'markers');
    }

    function createLaneOverlay() {
      console.log('createLaneOverlay called');
      
      if (!stage) {
        console.log('Stage not found for lane overlay');
        return;
      }
      
      // Remove existing lane overlay
      var existingOverlay = stage.querySelector('.awb-lane-overlay');
      if (existingOverlay) {
        existingOverlay.remove();
        console.log('Removed existing lane overlay');
      }
      
      // Get lane width from dropdown
      var laneSelect = modal.querySelector('#aiwLaneSelect');
      if (!laneSelect) {
        console.log('Lane select not found');
        return;
      }
      
      var laneWidth = parseInt(laneSelect.value);
      console.log('Selected lane width:', laneWidth);
      
      // If no valid lane width selected, don't show lanes
      if (!laneWidth || laneWidth === 0) {
        console.log('No lane width selected - hiding lanes');
        return;
      }
      
      // Get crop area from canvasCrop
      if (!canvasCrop) {
        console.log('No crop area available for lane overlay');
        return;
      }
      
      var currentWidth = parseFloat(widthInput.value) || 900;
      var stageWidth = stage.clientWidth || 800;
      var cmPerPixel = currentWidth / stageWidth;
      
      // Get crop dimensions and position
      var cropX = canvasCrop.cropX || 0;
      var cropY = canvasCrop.cropY || 0;
      var cropWidth = canvasCrop.cropWidth || stageWidth;
      var cropHeight = canvasCrop.cropHeight || 400;
      
      console.log('Lane overlay params:', {
        currentWidth: currentWidth + 'cm',
        stageWidth: stageWidth + 'px',
        cmPerPixel: cmPerPixel,
        laneWidth: laneWidth + 'cm',
        cropArea: cropX + ',' + cropY + ' ' + cropWidth + 'x' + cropHeight
      });
      
      // CROP-AREA-ONLY BAHNEN OVERLAY
      var overlay = document.createElement('div');
      overlay.className = 'awb-lane-overlay';
      overlay.style.cssText = 'position:absolute;top:' + cropY + 'px;left:' + cropX + 'px;width:' + cropWidth + 'px;height:' + cropHeight + 'px;z-index:15;pointer-events:none;overflow:hidden;';
      
      var lineCount = 0;
      // Calculate crop width in cm
      var cropWidthCm = (cropWidth / stageWidth) * currentWidth;
      
      // Safety check: Don't draw lanes if crop is too small
      if (cropWidthCm <= laneWidth) {
        console.log('Crop too small for', laneWidth + 'cm lanes (crop width:', cropWidthCm + 'cm)');
        stage.appendChild(overlay); // Still append empty overlay
        return;
      }
      
      // Draw vertical DASHED lines every lane width starting from crop left edge
      for (var cropCm = laneWidth; cropCm < cropWidthCm; cropCm += laneWidth) {
        var linePosition = (cropCm / cropWidthCm) * cropWidth; // Position within crop area
        
        var line = document.createElement('div');
        line.style.cssText = 'position:absolute;top:0;bottom:0;left:' + linePosition + 'px;width:1px;border-left:2px dashed magenta;opacity:0.9;z-index:16;';
        
        overlay.appendChild(line);
        lineCount++;
        console.log('Added CROP-RELATIVE lane line at crop-cm:', cropCm + 'cm', 'position:', linePosition + 'px');
      }
      
      stage.appendChild(overlay);
      console.log('CROP-AREA lane overlay created with', lineCount, 'lines for', laneWidth + 'cm lanes in crop area');
    }

    function initCanvasCrop(imageSrc) {
      if (!window.CanvasCrop) {
        setTimeout(function() { initCanvasCrop(imageSrc); }, 500);
        return;
      }
      
      createCropContainer();
      
      if (canvasCrop) {
        canvasCrop.destroy();
        canvasCrop = null;
      }
      
      currentImageSrc = imageSrc;
      var aspect = getCurrentAspect();
      
      console.log('Initializing Canvas Crop');
      
      canvasCrop = new window.CanvasCrop(cropContainer, imageSrc, {
        aspect: aspect,
        onCropChange: function(cropData) {
          // ONLY save crop data, nothing else
          saveToHiddenFields({
            ratio: cropData.ratio,
            cropX: cropData.cropX
          });
          
          // Update lane overlay when crop area changes
          createLaneOverlay();
        }
      });
      
      console.log('Canvas Crop ready');
    }

    // Watch for new images
    if (previewContainer) {
      var observer = new MutationObserver(function(records) {
        records.forEach(function(record) {
          record.addedNodes.forEach(function(node) {
            if (node.tagName && node.tagName.toLowerCase() === 'img') {
              console.log('New image detected');
              setTimeout(function() {
                initCanvasCrop(node.src);
              }, 200);
            }
          });
        });
      });
      
      observer.observe(previewContainer, { childList: true, subtree: true });
      
      var existingImg = previewContainer.querySelector('img');
      if (existingImg) {
        setTimeout(function() {
          initCanvasCrop(existingImg.src);
        }, 500);
      }
    }

    // SIMPLIFIED: Size input changes - NO LOOPS
    var sizeChangeTimeout = null;
    function onSizeChange(event) {
      console.log('Size changed triggered by:', event.target.id || event.target.name);
      console.log('Current values - width:', widthInput.value, 'height:', heightInput.value);
      
      // Clear previous timeout
      if (sizeChangeTimeout) {
        clearTimeout(sizeChangeTimeout);
      }
      
      // Debounce to prevent rapid calls
      sizeChangeTimeout = setTimeout(function() {
        console.log('Processing size change...');
        updateStageOnly();
        updateCropOnly();
        createRuler(); // Update ruler with new dimensions
        createLaneOverlay(); // Update lane overlay with new dimensions
        
              // Save current aspect ratio AND size values
      var aspect = getCurrentAspect();
      var width = parseFloat(widthInput.value) || 900;
      var height = parseFloat(heightInput.value) || 200;
      
      console.log('New aspect ratio:', aspect, 'Size:', width + 'x' + height);
      
      saveToHiddenFields({ 
        ratio: aspect,
        width: width,
        height: height
      });
      
      // Also update main form size fields
      var mainForm = document.querySelector('.awb-box');
      if (mainForm) {
        var mainWidthField = mainForm.querySelector('input[name="awb_width"]');
        var mainHeightField = mainForm.querySelector('input[name="awb_height"]');
        
        if (mainWidthField) mainWidthField.value = width;
        if (mainHeightField) mainHeightField.value = height;
        
        console.log('Updated main form size fields');
      }
      }, 100);
    }
    
    // ONLY input events, debounced
    widthInput.addEventListener('input', onSizeChange);
    heightInput.addEventListener('input', onSizeChange);
    
    // Lane select change event - ORIGINAL DROPDOWN VERSION
    var laneSelect = modal.querySelector('#aiwLaneSelect');
    if (laneSelect) {
      laneSelect.addEventListener('change', function() {
        console.log('Lane width changed to:', this.value + 'cm');
        createLaneOverlay();
      });
      console.log('✅ Lane select found and event bound');
    } else {
      console.log('❌ Lane select NOT found!');
    }
    
    console.log('Size input events bound (debounced)');

    // Modal visibility
    var modalObserver = new MutationObserver(function() {
      if (modal.classList.contains('open')) {
        console.log('Modal opened');
        
        // CRITICAL: Sync all fields from main form to modal when opening
        syncMainToModal();
        
        updateStageOnly();
        
        if (previewContainer && !currentImageSrc) {
          var img = previewContainer.querySelector('img');
          if (img) {
            setTimeout(function() {
              initCanvasCrop(img.src);
            }, 300);
          }
        }
      }
    });
    
    modalObserver.observe(modal, { attributes: true, attributeFilter: ['class'] });
    
    // Function to upload cropped image
    function uploadCroppedImage(blob, callback) {
      var formData = new FormData();
      formData.append('action', 'awb_save_cropped_image');
      formData.append('cropped_image', blob, 'crop_' + Date.now() + '.jpg');
      formData.append('nonce', awb_ajax.nonce || '');
      
      // Add size information
      var width = parseFloat(widthInput.value) || 0;
      var height = parseFloat(heightInput.value) || 0;
      formData.append('width', width);
      formData.append('height', height);
      
      // Try to get order ID from URL or generate temp one
      var urlParams = new URLSearchParams(window.location.search);
      var orderId = urlParams.get('order_id') || 'PREVIEW_' + Date.now();
      formData.append('order_id', orderId);
      
      fetch(awb_ajax.ajax_url || '/wp-admin/admin-ajax.php', {
        method: 'POST',
        body: formData
      })
      .then(function(response) { return response.json(); })
      .then(function(data) {
        console.log('Image upload response:', data);
        if (data.success) {
          callback(data.data);
        } else {
          console.error('Image upload failed:', data.data);
          callback(null);
        }
      })
      .catch(function(error) {
        console.error('Image upload error:', error);
        callback(null);
      });
    }

    // "Bild verwenden" button
    document.addEventListener('click', function(e) {
      if (e.target && (e.target.classList.contains('awb-apply') || e.target.closest('.awb-apply'))) {
        console.log('Bild verwenden clicked');
        
        if (currentCropData) {
          saveToHiddenFields(currentCropData);
          
          // Generate and upload cropped image
          if (canvasCrop && canvasCrop.getCroppedImageAsBlob) {
            console.log('Generating cropped image...');
            
            canvasCrop.getCroppedImageAsBlob(function(blob) {
              if (blob) {
                console.log('Cropped image generated, uploading...');
                
                uploadCroppedImage(blob, function(uploadResult) {
                  if (uploadResult) {
                    console.log('Cropped image uploaded successfully:', uploadResult.filename);
                    
                    // Save image info to hidden fields
                    saveToHiddenFields({
                      croppedImageUrl: uploadResult.url,
                      croppedImageFile: uploadResult.filename
                    });
                  } else {
                    console.error('Failed to upload cropped image');
                  }
                });
              } else {
                console.error('Failed to generate cropped image blob');
              }
            });
          } else {
            console.log('Canvas crop not available, skipping image generation');
          }
          
          // Copy to main form
          var mainForm = document.querySelector('.awb-box');
          if (mainForm) {
            var mainRatioField = mainForm.querySelector('.aiw-ratio');
            var mainCropField = mainForm.querySelector('.aiw-crop-x');
            
            if (mainRatioField) mainRatioField.value = currentCropData.ratio.toFixed(6);
            if (mainCropField) mainCropField.value = currentCropData.cropX.toFixed(4);
            
            console.log('Data copied to main form');
          }
        }
      }
    });
    
    // Initial setup
    updateStageOnly();
    createRuler(); // Create ruler initially
    
    // Create lane overlay after a short delay to ensure DOM is ready
    setTimeout(function() {
      createLaneOverlay();
    }, 100);
    
    // Function to sync all fields from main form to modal
    function syncMainToModal() {
      var mainForm = document.querySelector('form.cart') || document.querySelector('.awb-box');
      if (!mainForm) {
        console.log('⚠️ Main form not found for sync');
        return;
      }
      
      console.log('🔄 Syncing main form to modal...');
      
      // Sync all input fields and selects
      var modalFields = modal.querySelectorAll('input[name*="_modal"], textarea[name*="_modal"], select[name*="_modal"]');
      modalFields.forEach(function(modalField) {
        var modalFieldName = modalField.name;
        var mainFieldName = modalFieldName.replace('_modal', '');
        
        if (modalField.type === 'radio') {
          // Handle radio buttons
          var mainRadios = mainForm.querySelectorAll('input[name="' + mainFieldName + '"]');
          var checkedMainRadio = mainForm.querySelector('input[name="' + mainFieldName + '"]:checked');
          
          if (checkedMainRadio && modalField.value === checkedMainRadio.value) {
            modalField.checked = true;
            console.log('✅ Radio synced to modal:', mainFieldName, '=', checkedMainRadio.value);
          }
        } else {
          // Handle text inputs, textareas, selects
          var mainField = mainForm.querySelector('input[name="' + mainFieldName + '"], textarea[name="' + mainFieldName + '"], select[name="' + mainFieldName + '"]');
          
          // Special handling for material dropdown - try to find WooCommerce attribute select
          if (modalFieldName === 'awb_material_modal' && !mainField) {
            // Try to find material attribute select (common patterns)
            mainField = mainForm.querySelector('select[name*="attribute_pa_material"], select[name*="attribute_material"]');
            if (mainField) {
              console.log('🎯 Found WooCommerce material attribute:', mainField.name);
            }
          }
          
          if (mainField && modalField.value !== mainField.value) {
            modalField.value = mainField.value;
            console.log('✅ Field synced to modal:', mainFieldName, '=', mainField.value);
          } else if (mainField && modalField.tagName === 'SELECT') {
            // For selects, also sync the selected option
            var selectedOption = mainField.options[mainField.selectedIndex];
            if (selectedOption) {
              // Try to find matching option in modal select
              var modalOptions = modalField.options;
              for (var i = 0; i < modalOptions.length; i++) {
                if (modalOptions[i].value === selectedOption.value || modalOptions[i].text === selectedOption.text) {
                  modalField.selectedIndex = i;
                  console.log('✅ Select option synced to modal:', mainFieldName, '=', selectedOption.value);
                  break;
                }
              }
            }
          }
        }
      });
    }
    
    // Sync variant changes to main WooCommerce form
    modal.addEventListener('change', function(e) {
      if (e.target && e.target.name && e.target.name.includes('_modal')) {
        var modalFieldName = e.target.name;
        var mainFieldName = modalFieldName.replace('_modal', '');
        var selectedValue = e.target.value;
        
        console.log('🔄 Modal field changed:', modalFieldName, '=', selectedValue);
        
        // Find corresponding field in main form
        var mainForm = document.querySelector('form.cart') || document.querySelector('.awb-box');
        if (mainForm) {
          var synced = false;
          
          if (e.target.type === 'radio') {
            // Handle radio buttons
            var mainRadios = mainForm.querySelectorAll('input[name="' + mainFieldName + '"]');
            if (mainRadios.length > 0) {
              mainRadios.forEach(function(radio) {
                radio.checked = (radio.value === selectedValue);
              });
              synced = true;
            }
          } else {
            // Handle text inputs, textareas, selects
            var mainField = mainForm.querySelector('input[name="' + mainFieldName + '"], textarea[name="' + mainFieldName + '"], select[name="' + mainFieldName + '"]');
            
            // Special handling for material dropdown - try to find WooCommerce attribute select
            if (modalFieldName === 'awb_material_modal' && !mainField) {
              // Try to find material attribute select (common patterns)
              mainField = mainForm.querySelector('select[name*="attribute_pa_material"], select[name*="attribute_material"]');
              if (mainField) {
                console.log('🎯 Found WooCommerce material attribute for sync back:', mainField.name);
              }
            }
            
            if (mainField) {
              mainField.value = selectedValue;
              synced = true;
              
              // Special handling for WooCommerce variation selects
              if (mainField.tagName === 'SELECT' && (mainFieldName.includes('attribute_') || modalFieldName === 'awb_material_modal')) {
                // Trigger WooCommerce variation change event
                var changeEvent = new Event('change', { bubbles: true });
                mainField.dispatchEvent(changeEvent);
                console.log('🎯 WooCommerce variation event triggered for:', mainField.name);
              }
            }
          }
          
          if (synced) {
            console.log('✅ Variant synced:', mainFieldName, '=', selectedValue);
            
            // Special handling for material changes - trigger WooCommerce found_variation
            if (modalFieldName === 'awb_material_modal') {
              setTimeout(function() {
                var variationsForm = document.querySelector('.variations_form');
                if (variationsForm && typeof jQuery !== 'undefined') {
                  jQuery(variationsForm).trigger('woocommerce_variation_has_changed');
                  console.log('🎯 WooCommerce variation recalculation triggered');
                }
              }, 100);
            }
          } else {
            console.log('⚠️ No corresponding field found for:', mainFieldName);
          }
        }
      }
    });
    
    console.log('Canvas crop modal system ready (SIMPLIFIED)');
  });
})();
// FUNKTIONSTEST: Material Synchronisation
function performMaterialSyncTest() {
  console.log('🧪 === FUNKTIONSTEST: MATERIAL SYNCHRONISATION ===');
  
  var modal = document.getElementById('awb-modal');
  var mainForm = document.querySelector('form.cart') || document.querySelector('.awb-box');
  
  if (!modal || !mainForm) {
    console.log('❌ TEST FEHLGESCHLAGEN: Modal oder Hauptform nicht gefunden');
    return;
  }
  
  var modalMaterial = modal.querySelector('#aiwMaterial');
  var mainMaterial = mainForm.querySelector('select[name*="attribute_pa_material"], select[name*="attribute_material"]');
  
  console.log('📋 TEST SETUP:');
  console.log('  Modal Material Select:', modalMaterial ? modalMaterial.name : 'NICHT GEFUNDEN');
  console.log('  Main Material Select:', mainMaterial ? mainMaterial.name : 'NICHT GEFUNDEN');
  
  if (modalMaterial) {
    console.log('  Modal Material Optionen:', Array.from(modalMaterial.options).map(o => o.text));
  }
  if (mainMaterial) {
    console.log('  Main Material Optionen:', Array.from(mainMaterial.options).map(o => o.text));
  }
  
  console.log('🧪 FUNKTIONSTEST BEREIT - Ändere Material im Modal um Test durchzuführen');
}

// Auto-run test when modal opens
document.addEventListener('DOMContentLoaded', function() {
  var modal = document.getElementById('awb-modal');
  if (modal) {
    var observer = new MutationObserver(function() {
      if (modal.classList.contains('open')) {
        setTimeout(performMaterialSyncTest, 1000);
      }
    });
    observer.observe(modal, { attributes: true, attributeFilter: ['class'] });
  }
});
