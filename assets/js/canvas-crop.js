/**
 * Simple Canvas-based Crop Solution
 * No external dependencies, pure JavaScript
 */

(function() {
  'use strict';
  
  function CanvasCrop(container, imageSrc, options) {
    this.container = container;
    this.imageSrc = imageSrc;
    this.options = options || {};
    this.aspect = this.options.aspect || 1;
    this.onCropChange = this.options.onCropChange || function() {};
    
    this.canvas = null;
    this.ctx = null;
    this.image = null;
    this.isDragging = false;
    this.cropX = 0;
    this.cropY = 0;
    this.cropWidth = 0;
    this.cropHeight = 0;
    this.imageX = 0;
    this.imageY = 0;
    this.imageWidth = 0;
    this.imageHeight = 0;
    
    this.init();
  }
  
  CanvasCrop.prototype.init = function() {
    var self = this;
    
    // Create canvas
    this.canvas = document.createElement('canvas');
    this.canvas.style.cssText = 'width:100%;height:100%;border:none;display:block;background:#ffffff;cursor:move;';
    this.ctx = this.canvas.getContext('2d');
    
    // Clear container and add canvas
    this.container.innerHTML = '';
    this.container.appendChild(this.canvas);
    
    // Load image
    this.image = new Image();
    this.image.crossOrigin = 'anonymous';
    this.image.onload = function() {
      self.setupCanvas();
      self.setupEvents();
      self.draw();
      console.log('Canvas crop initialized');
    };
    this.image.onerror = function() {
      console.error('Failed to load image:', self.imageSrc);
    };
    this.image.src = this.imageSrc;
  };
  
  CanvasCrop.prototype.setupCanvas = function() {
    var rect = this.container.getBoundingClientRect();
    this.canvas.width = rect.width;
    this.canvas.height = rect.height;
    
    // Calculate image size to fit canvas
    var canvasAspect = this.canvas.width / this.canvas.height;
    var imageAspect = this.image.width / this.image.height;
    
    if (imageAspect > canvasAspect) {
      this.imageWidth = this.canvas.width;
      this.imageHeight = this.canvas.width / imageAspect;
      this.imageX = 0;
      this.imageY = (this.canvas.height - this.imageHeight) / 2;
    } else {
      this.imageHeight = this.canvas.height;
      this.imageWidth = this.canvas.height * imageAspect;
      this.imageY = 0;
      this.imageX = (this.canvas.width - this.imageWidth) / 2;
    }
    
    // Calculate initial crop area
    this.cropHeight = this.imageHeight;
    this.cropWidth = this.cropHeight * this.aspect;
    
    if (this.cropWidth > this.imageWidth) {
      this.cropWidth = this.imageWidth;
      this.cropHeight = this.cropWidth / this.aspect;
    }
    
    this.cropX = this.imageX + (this.imageWidth - this.cropWidth) / 2;
    this.cropY = this.imageY + (this.imageHeight - this.cropHeight) / 2;
  };
  
  CanvasCrop.prototype.setupEvents = function() {
    var self = this;
    
    this.canvas.addEventListener('mousedown', function(e) {
      var rect = self.canvas.getBoundingClientRect();
      var x = e.clientX - rect.left;
      var y = e.clientY - rect.top;
      
      // Check if click is inside crop area
      if (x >= self.cropX && x <= self.cropX + self.cropWidth &&
          y >= self.cropY && y <= self.cropY + self.cropHeight) {
        self.isDragging = true;
        self.dragStartX = x - self.cropX;
        self.dragStartY = y - self.cropY;
        console.log('Drag started');
      }
    });
    
    // PERFORMANCE: Debounced mouse move for better performance
    var mouseMoveTimeout;
    this.canvas.addEventListener('mousemove', function(e) {
      if (!self.isDragging) return;
      
      var rect = self.canvas.getBoundingClientRect();
      var x = e.clientX - rect.left;
      var y = e.clientY - rect.top;
      
      var newCropX = x - self.dragStartX;
      var newCropY = y - self.dragStartY;
      
      // Constrain to image bounds
      newCropX = Math.max(self.imageX, Math.min(newCropX, self.imageX + self.imageWidth - self.cropWidth));
      newCropY = Math.max(self.imageY, Math.min(newCropY, self.imageY + self.imageHeight - self.cropHeight));
      
      self.cropX = newCropX;
      self.cropY = newCropY;
      
      // PERFORMANCE: Debounce expensive redraw operations
      clearTimeout(mouseMoveTimeout);
      mouseMoveTimeout = setTimeout(function() {
        self.draw();
        self.fireCropChange();
      }, 16); // ~60fps limit
    });
    
    this.canvas.addEventListener('mouseup', function() {
      if (self.isDragging) {
        self.isDragging = false;
        console.log('Drag ended');
      }
    });
    
    // Handle mouse leave
    document.addEventListener('mouseup', function() {
      self.isDragging = false;
    });
  };
  
  CanvasCrop.prototype.draw = function() {
    this.ctx.clearRect(0, 0, this.canvas.width, this.canvas.height);
    
    // Draw image
    this.ctx.drawImage(this.image, this.imageX, this.imageY, this.imageWidth, this.imageHeight);
    
    // Draw overlay (darken areas outside crop)
    this.ctx.fillStyle = 'rgba(0, 0, 0, 0.5)';
    
    // Top overlay
    if (this.cropY > this.imageY) {
      this.ctx.fillRect(this.imageX, this.imageY, this.imageWidth, this.cropY - this.imageY);
    }
    
    // Bottom overlay
    if (this.cropY + this.cropHeight < this.imageY + this.imageHeight) {
      this.ctx.fillRect(this.imageX, this.cropY + this.cropHeight, this.imageWidth, 
                       (this.imageY + this.imageHeight) - (this.cropY + this.cropHeight));
    }
    
    // Left overlay
    if (this.cropX > this.imageX) {
      this.ctx.fillRect(this.imageX, this.cropY, this.cropX - this.imageX, this.cropHeight);
    }
    
    // Right overlay
    if (this.cropX + this.cropWidth < this.imageX + this.imageWidth) {
      this.ctx.fillRect(this.cropX + this.cropWidth, this.cropY, 
                       (this.imageX + this.imageWidth) - (this.cropX + this.cropWidth), this.cropHeight);
    }
    
    // Draw thin crop border
    this.ctx.strokeStyle = '#ffffff';
    this.ctx.lineWidth = 1;
    this.ctx.strokeRect(this.cropX, this.cropY, this.cropWidth, this.cropHeight);
    
    // No corner handles - just clean border
  };
  
  CanvasCrop.prototype.fireCropChange = function() {
    // Calculate crop position as ratio
    var availableWidth = this.imageWidth - this.cropWidth;
    var offsetRatio = availableWidth > 0 ? (this.cropX - this.imageX) / availableWidth : 0;
    
    this.onCropChange({
      ratio: this.aspect,
      cropX: Math.max(0, Math.min(1, offsetRatio)),
      x: this.cropX - this.imageX,
      y: this.cropY - this.imageY,
      width: this.cropWidth,
      height: this.cropHeight
    });
  };
  
  CanvasCrop.prototype.setAspect = function(aspect) {
    this.aspect = aspect;
    this.setupCanvas();
    this.draw();
    // DON'T fire crop change on aspect update to prevent loops
  };
  
    CanvasCrop.prototype.getCroppedImageAsBlob = function(callback, quality) {
    quality = quality || 0.9;
    
    // Create a new canvas for the cropped area only
    var cropCanvas = document.createElement('canvas');
    var cropCtx = cropCanvas.getContext('2d');
    
    // Set crop canvas size to actual crop dimensions
    cropCanvas.width = this.cropWidth;
    cropCanvas.height = this.cropHeight;
    
    // Draw the cropped portion of the image
    cropCtx.drawImage(
      this.image,
      // Source coordinates (crop area relative to original image)
      (this.cropX - this.imageX) * (this.image.naturalWidth / this.imageWidth),
      (this.cropY - this.imageY) * (this.image.naturalHeight / this.imageHeight),
      this.cropWidth * (this.image.naturalWidth / this.imageWidth),
      this.cropHeight * (this.image.naturalHeight / this.imageHeight),
      // Destination coordinates (full crop canvas)
      0, 0, this.cropWidth, this.cropHeight
    );
    
    // Convert to blob
    cropCanvas.toBlob(callback, 'image/jpeg', quality);
  };

  CanvasCrop.prototype.destroy = function() {
    if (this.container && this.canvas) {
      this.container.removeChild(this.canvas);
    }
  };

  // Global API
  window.CanvasCrop = CanvasCrop;
  
  console.log('Canvas Crop library loaded');
})(); 