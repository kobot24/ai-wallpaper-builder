/*
 * AIW modal enhancements - SIMPLIFIED VERSION
 * (WooCommerce native selects werden ins Modal verschoben)
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
    // ... bestehender Modal-Code ...

    // --- BEGIN: Dynamische WooCommerce-Selects in Modal kopieren ---
    function moveWooVariationFieldsToModal() {
      const wcForm = document.querySelector('form.variations_form');
      const sidebar = document.getElementById('aiw-wc-variation-fields');
      if (!wcForm || !sidebar) return;
      const wcSelects = wcForm.querySelectorAll('select[name^="attribute_"]');
      wcSelects.forEach(sel => {
        const wrapper = document.createElement('div');
        wrapper.className = 'aiw-sidebar-field';
        wrapper.appendChild(sel);
        sidebar.appendChild(wrapper);
      });
    }
    function moveWooVariationFieldsBack() {
      const wcForm = document.querySelector('form.variations_form');
      const sidebar = document.getElementById('aiw-wc-variation-fields');
      if (!wcForm || !sidebar) return;
      const wrappers = sidebar.querySelectorAll('.aiw-sidebar-field');
      wrappers.forEach(wrapper => {
        const sel = wrapper.querySelector('select[name^="attribute_"]');
        if (sel) wcForm.appendChild(sel);
        wrapper.remove();
      });
    }

    document.addEventListener('aiw-modal-open', moveWooVariationFieldsToModal);
    document.addEventListener('aiw-modal-close', moveWooVariationFieldsBack);

    // Wenn du eigene openModal/closeModal Funktionen nutzt, dort zusätzlich:
    // document.dispatchEvent(new Event('aiw-modal-open'));
    // document.dispatchEvent(new Event('aiw-modal-close'));
    // ... (restlicher Code bleibt wie gehabt)
  });
})();
