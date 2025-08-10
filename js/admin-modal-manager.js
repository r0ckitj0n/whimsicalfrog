/* Centralized modal management for admin settings page */
(function(){
  // helper to open a modal by id
  window.openModal = function(id){
    if(!id) return;
    // hide all other open overlays
    document.querySelectorAll('.admin-modal-overlay.show').forEach(function(o){
      if(o.id!==id){ o.classList.remove('show'); o.classList.add('hidden'); }
    });
    var el = document.getElementById(id);
    if(el){
      el.classList.remove('hidden');
      el.classList.add('show');
      // Centralized scroll lock
      if (window.WFModals && typeof WFModals.lockScroll === 'function') {
        WFModals.lockScroll();
      }
    }
  };

  // helper to close a modal by id
  window.closeModal = function(id){
    var el = document.getElementById(id);
    if(el){
      el.classList.remove('show');
      el.classList.add('hidden');
      // Centralized unlock if none open
      if (window.WFModals && typeof WFModals.unlockScrollIfNoneOpen === 'function') {
        WFModals.unlockScrollIfNoneOpen();
      }
    }
  };

  // ESC key closes any open modal
  document.addEventListener('keydown', function(e){
    if(e.key === 'Escape'){
      document.querySelectorAll('.admin-modal-overlay.show').forEach(function(o){
        o.classList.remove('show');
        o.classList.add('hidden');
      });
      if (window.WFModals && typeof WFModals.unlockScrollIfNoneOpen === 'function') {
        WFModals.unlockScrollIfNoneOpen();
      }
    }
  });

  // outside click closes modal
  document.addEventListener('click', function(e){
    var overlay = e.target.closest('.admin-modal-overlay');
    if(overlay && e.target === overlay){
      overlay.classList.remove('show');
      overlay.classList.add('hidden');
      if (window.WFModals && typeof WFModals.unlockScrollIfNoneOpen === 'function') {
        WFModals.unlockScrollIfNoneOpen();
      }
    }
  });
  // ensure all overlays start hidden on initial page load
  document.addEventListener('DOMContentLoaded',function(){
    document.querySelectorAll('.admin-modal-overlay').forEach(function(el){
      el.classList.remove('show');
      el.classList.add('hidden');
    });
  });
})();
