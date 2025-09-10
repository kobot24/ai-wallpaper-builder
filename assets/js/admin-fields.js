(function($){
  const $mount = $('#awb_field_builder');
  let fields = $mount.length ? ($mount.data('fields') || []) : [];
  const $hidden = $('#awb-fields-json');

  function uid(){ return 'fld_'+Math.random().toString(36).slice(2,9); }
  function sync(){ $hidden.val(JSON.stringify(fields)); }
  function typeIcon(t){ return {text:'🅣',textarea:'📝',cards:'🖼️',square_meter:'📐'}[t] || '🅧'; }

  function rowTpl(f){
    const req = f.required ? 'checked' : '';
    const opts = (f.options||[]).join('\n');
    return `<div class="awb-toggle" data-id="${f.id}">
      <div class="awb-head">
        <span class="drag">↕</span>
        <span class="title">${f.label||'(ohne Label)'} <small class="meta">${f.key||''}</small></span>
        <span class="req ${f.required?'on':''}">Pflicht</span>
        <button type="button" class="button-link awb-collapse">▼</button>
        <button type="button" class="button-link awb-del">Entfernen</button>
      </div>
      <div class="awb-body">
        <div class="row"><label>Label</label><input class="i-label" type="text" value="${f.label||''}"></div>
        <div class="row"><label>Key</label><input class="i-key" type="text" value="${f.key||''}" placeholder="auto aus Label"></div>
        <div class="row"><label>Pflichtfeld</label><input class="i-req" type="checkbox" ${req}></div>
        <div class="row"><label>Typ</label>
          <select class="i-type">
            <option value="text" ${f.type==='text'?'selected':''}>Text</option>
            <option value="textarea" ${f.type==='textarea'?'selected':''}>Textarea</option>
            <option value="cards" ${f.type==='cards'?'selected':''}>Cards (Bilder)</option>
            <option value="square_meter" ${f.type==='square_meter'?'selected':''}>Square Meter</option>
          </select>
        </div>
        <div class="row when when-text"><label>Placeholder</label><input class="i-ph" type="text" value="${f.placeholder||''}"></div>
        <div class="row when when-text"><label>Maximale Zeichen</label><input class="i-max" type="number" min="0" value="${f.maxlength||0}"></div>
        <div class="row when when-text"><label>Minimale Zeichen</label><input class="i-min" type="number" min="0" value="${f.minlength||0}"></div>
        <div class="row when when-textarea"><label>Placeholder</label><input class="i-ph" type="text" value="${f.placeholder||''}"></div>
        <div class="row when when-textarea"><label>Rows</label><input class="i-rows" type="number" min="1" value="${f.rows||3}"></div>
        <div class="row when when-cards">
          <label>Bild-URLs</label>
          <textarea class="i-opts" rows="3" placeholder="eine URL pro Zeile">${opts}</textarea>
          <button type="button" class="button awb-media-add">Bilder aus Mediathek</button>
        </div>
        <div class="row when when-square-meter">
          <label>Quadratmeterpreis</label>
          <div style="display:flex;align-items:center;gap:8px">
            <input class="i-sqm-price" type="checkbox" ${f.sqm_price?'checked':''}>
            <span>Standardpreis als €/m² verwenden</span>
          </div>
        </div>
      </div>
    </div>`;
  }

  function render(){
    const list = fields.map(rowTpl).join('');
    $mount.html(`
      <div class="awb-toolbar">
        <select id="awb-new-type">
          <option value="text">Text</option>
          <option value="textarea">Textarea</option>
          <option value="cards">Cards (Bilder)</option>
          <option value="square_meter">Square Meter</option>
        </select>
        <button type="button" class="button button-primary" id="awb-add">Feld hinzufügen</button>
      </div>
      <div id="awb-list" class="awb-list">${list}</div>
    `);
    bind();
    showWhen();
    $('#awb-list').sortable({handle:'.drag', update:function(){
      const ids = $('#awb-list .awb-toggle').map(function(){return $(this).data('id');}).get();
      fields.sort((a,b)=> ids.indexOf(a.id)-ids.indexOf(b.id));
      sync();
    }});
    sync();
  }

  function showWhen(){
    $('#awb-list .awb-toggle').each(function(){
      const t = $(this).find('.i-type').val();
      $(this).find('.when').hide();
      $(this).find('.when-'+t.replace('_','-')).show();
    });
  }

  function bind(){
    $('#awb-add').off('click').on('click', function(){
      const t = $('#awb-new-type').val();
      const f = {id:uid(), type:t, key:'', label:'', required:false};
      if (t==='textarea'){ f.rows=3; }
      fields.push(f); render();
    });
    $('#awb-list').off('click').on('click','.awb-del', function(){
      const id = $(this).closest('.awb-toggle').data('id');
      fields = fields.filter(f=>f.id!==id); render();
    }).on('click','.awb-collapse', function(){
      $(this).closest('.awb-toggle').toggleClass('closed');
    }).on('change','.i-type', function(){
      const id = $(this).closest('.awb-toggle').data('id'); const f = fields.find(x=>x.id===id);
      f.type = $(this).val(); showWhen(); sync();
    }).on('input change','.i-label', function(){
      const $r = $(this).closest('.awb-toggle'); const id=$r.data('id'); const f=fields.find(x=>x.id===id);
      f.label=$(this).val(); $r.find('.title').text(f.label||'(ohne Label)'); sync();
    }).on('input change','.i-key', function(){
      const $r=$(this).closest('.awb-toggle'); const id=$r.data('id'); const f=fields.find(x=>x.id===id);
      f.key=$(this).val(); $r.find('.meta').text(f.key||''); sync();
    }).on('change','.i-req', function(){
      const $r=$(this).closest('.awb-toggle'); const id=$r.data('id'); const f=fields.find(x=>x.id===id);
      f.required=$(this).is(':checked'); $r.find('.req').toggleClass('on',f.required); sync();
    }).on('input change','.i-ph', function(){
      const id=$(this).closest('.awb-toggle').data('id'); const f=fields.find(x=>x.id===id); f.placeholder=$(this).val(); sync();
    }).on('input change','.i-max', function(){
      const id=$(this).closest('.awb-toggle').data('id'); const f=fields.find(x=>x.id===id); f.maxlength=parseInt($(this).val()||0); sync();
    }).on('input change','.i-min', function(){
      const id=$(this).closest('.awb-toggle').data('id');
      const f=fields.find(x=>x.id===id);
      f.minlength=parseInt($(this).val()||0);
      sync();
    }).on('change','.i-sqm-price', function(){
      const id=$(this).closest('.awb-toggle').data('id');
      const f=fields.find(x=>x.id===id);
      f.sqm_price=$(this).is(':checked');
      // Update required class for req indicator? Not needed. Just sync.
      sync();
    }).on('input change','.i-rows', function(){
      const id=$(this).closest('.awb-toggle').data('id'); const f=fields.find(x=>x.id===id); f.rows=parseInt($(this).val()||3); sync();
    }).on('input change','.i-opts', function(){
      const id=$(this).closest('.awb-toggle').data('id'); const f=fields.find(x=>x.id===id);
      f.options=$(this).val().split(/\n/).map(s=>s.trim()).filter(Boolean); sync();
    }).on('click', '.awb-media-add', function(e){
      e.preventDefault();
      const $ta = $(this).siblings('textarea.i-opts');
      var frame = wp.media({ title:'Bilder auswählen', multiple:true, library:{type:'image'} });
      frame.on('select', function(){
        var urls = frame.state().get('selection').map(function(att){ return att.get('url'); });
        const current = $ta.val().trim();
        const next = (current ? current + "\n" : "") + urls.join("\n");
        $ta.val(next).trigger('input');
      });
      frame.open();
    });
  }

  if ($mount.length) { render(); }
  // AI Wallpaper default image uploader. This binds the media frame to the
  // "Bild wählen" button in the AI Wallpaper tab. When an image is selected,
  // its URL is populated into the corresponding input field.
  $(document).on('click', '.aiw-upload-button', function(e){
    e.preventDefault();
    var $input = $('#aiw_default_image');
    // Ensure the media frame is available
    if (typeof wp === 'undefined' || !wp.media) return;
    var frame = wp.media({
      title: 'Standardbild auswählen',
      button: { text: 'Auswählen' },
      library: { type: 'image' },
      multiple: false
    });
    frame.on('select', function(){
      var selection = frame.state().get('selection');
      if (!selection) return;
      var att = selection.first().toJSON();
      if (att && att.url){
        $input.val(att.url);
      }
    });
    frame.open();
  });

  // Reference image upload button
  $(document).on('click', '.aiw-upload-button-ref', function(e){
    e.preventDefault();
    var $input = $('#aiw_reference_image');
    // Ensure the media frame is available
    if (typeof wp === 'undefined' || !wp.media) return;
    var frame = wp.media({
      title: 'Referenzbild auswählen',
      button: { text: 'Auswählen' },
      library: { type: 'image' },
      multiple: false
    });
    frame.on('select', function(){
      var selection = frame.state().get('selection');
      if (!selection) return;
      var att = selection.first().toJSON();
      if (att && att.url){
        $input.val(att.url);
      }
    });
    frame.open();
  });

})(jQuery);
