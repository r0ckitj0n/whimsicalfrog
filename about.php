<?php
// About Us page - content loaded from business settings
if (!defined('INCLUDED_FROM_INDEX')) {
    define('INCLUDED_FROM_INDEX', true);
}
require_once __DIR__ . '/api/business_settings_helper.php';
$__wf_skip_footer = true;

$title = BusinessSettings::get('about_page_title', 'Our Story');
$content = BusinessSettings::get('about_page_content', '');

// Whimsical default story (used only if setting is blank)
$defaultStory = '<p>Once upon a time in a cozy little workshop, Calvin &amp; Lisa Lemley began crafting whimsical treasures for friends and family. What started as a weekend habit of chasing ideas and laughter soon grew into WhimsicalFrog&mdash;a tiny brand with a big heart.</p><p>Every piece we make is a small celebration of play and everyday magic: things that delight kids, spark curiosity, and make grown‑ups smile. We believe in craftsmanship, kindness, and creating goods that feel like they were made just for you.</p><p>Thank you for visiting our little corner of the pond. We hope our creations bring a splash of joy to your day!</p>';

// Fallback if DB value exists but is empty
if (!is_string($content) || trim(strip_tags($content)) === '') {
    $content = $defaultStory;
}
if (!is_string($title) || trim($title) === '') {
    $title = 'Our Story';
}
?>
<style>
  .wf-cloud-card .content{padding-top:0;padding-bottom:20px}
  /* Lock html scroll only on About */
  html.about-no-scroll{overflow:hidden!important;height:100%}
  html.about-no-scroll, body[data-page='about']{overflow:hidden!important;height:100%}
  /* Place button row exactly on the cloud card's bottom edge */
  .wf-cloud-card #aboutButtonsRow{position:absolute;left:50%;bottom:105px !important;transform:translateX(-50%) !important;margin-top:0;z-index:2;pointer-events:auto}
  @media (max-width:520px){ .wf-cloud-card #aboutButtonsRow{transform:translateX(-50%) !important;} }
  @media (min-width:1400px){ .wf-cloud-card #aboutButtonsRow{transform:translateX(-50%) !important;} }
  /* No scrollbar under fixed header, but let the cloud card size itself by content */
  body[data-page='about']{overflow:hidden; padding-top:0 !important}
  body[data-page='about'] .page-content{position:fixed;top:var(--wf-header-height);left:0;right:0;height:calc(100vh - var(--wf-header-height));overflow:hidden!important;padding:0!important;margin:0!important}
  body[data-page='about'] .prose{margin:0!important}
  /* IMPORTANT: do not force .wf-cloud-card height, so the buttons anchor to its real bottom */
  @media (max-width:520px){ body[data-page='about'] .wf-cloud-card .content{padding-top:0} }
  @media (min-width:1400px){ body[data-page='about'] .wf-cloud-card .content{padding-top:0} }
  /* Policy modal content box (replaces prior inline style attributes) */
  .policy-content-box{background:rgba(255,255,255,.10);border:1px solid rgba(255,255,255,.18);border-radius:12px;padding:18px}
  
</style>
<div class="page-content bg-transparent container mx-auto px-4 pt-8 pb-0">
  <div class="prose max-w-none">
    <div class="wf-cloud-card relative">
      <div class="content leading-relaxed text-gray-800">
        <h1 class="wf-cloud-title"><?php echo htmlspecialchars($title); ?></h1>
        <?php echo is_string($content) ? $content : ''; ?>
      </div>
      <?php
        try {
          echo '<div id="aboutButtonsRow" class="flex flex-wrap gap-2 justify-center">';
          echo '<a class="btn btn-secondary" href="/privacy.php" data-open-policy="1">Privacy Policy</a>';
          echo '<a class="btn btn-secondary" href="/terms.php" data-open-policy="1">Terms of Service</a>';
          echo '<a class="btn btn-secondary" href="/policy.php" data-open-policy="1">Store Policies</a>';
          echo '</div>';
        } catch (Throwable $____) {}
      ?>
    </div>
  </div>
</div>

 
<script>
  (function lockAboutScroll(){
    try {
      if (document.body && document.body.getAttribute('data-page') === 'about') {
        document.documentElement.classList.add('about-no-scroll');
        window.addEventListener('pagehide', function(){ try{ document.documentElement.classList.remove('about-no-scroll'); }catch(_){} }, { once: true });
      }
    } catch(_) { /* noop */ }
  })();
  
  (function ensurePolicyModalFallback(){
    if (typeof window.openPolicyModal === 'function') return;
    window.openPolicyModal = function(url,label){
      try {
        var overlay = document.getElementById('wfPolicyModalOverlay');
        if (!overlay){
          overlay = document.createElement('div');
          overlay.id = 'wfPolicyModalOverlay';
          overlay.className = 'overlay';
          overlay.setAttribute('role','dialog');
          overlay.setAttribute('aria-hidden','true');
          overlay.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,.6);display:flex;align-items:center;justify-content:center;padding:16px;opacity:0;visibility:hidden;transition:opacity .2s ease,visibility .2s ease;z-index:11050';
          var modal = document.createElement('div');
          modal.id = 'wfPolicyModal';
          modal.setAttribute('role','dialog');
          modal.setAttribute('aria-modal','true');
          modal.setAttribute('aria-label','Policy');
          modal.style.cssText = 'background:linear-gradient(135deg, var(--brand-primary,#87ac3a), var(--brand-secondary,#BF5700));color:#fff;border-radius:12px;box-shadow:0 25px 50px -12px rgba(0,0,0,.25);width:min(96vw,1100px);max-height:92vh;display:flex;flex-direction:column;overflow:hidden';
          var header = document.createElement('div');
          header.className = 'policy-modal-header';
          header.style.cssText = 'display:flex;align-items:center;justify-content:space-between;padding:10px 14px;font-weight:700;color:#fff;';
          var title = document.createElement('h3'); title.className='policy-modal-title'; title.textContent = label || 'Policy'; title.style.cssText='margin:0;font-size:1.1rem';
          var close = document.createElement('button'); close.className='policy-modal-close'; close.type='button'; close.setAttribute('aria-label','Close'); close.textContent='×'; close.style.cssText='background:none;border:0;color:#fff;font-size:22px;cursor:pointer';
          header.appendChild(title); header.appendChild(close);
          var body = document.createElement('div'); body.className='policy-modal-body'; body.style.cssText='padding:16px;max-height:calc(92vh - 46px);overflow:auto';
          var content = document.createElement('div'); content.id='wfPolicyModalContent'; content.style.cssText='line-height:1.6;color:#fff';
          body.appendChild(content);
          modal.appendChild(header); modal.appendChild(body);
          overlay.appendChild(modal);
          document.body.appendChild(overlay);
          // Define and store hide/trap on overlay for reuse
          overlay.__wfPolicyHide = function hide(){ try{ overlay.classList.remove('show'); overlay.setAttribute('aria-hidden','true'); overlay.style.opacity='0'; overlay.style.visibility='hidden'; }catch(_){} try{ document.removeEventListener('keydown', overlay.__wfPolicyTrap, true); }catch(_){} };
          overlay.__wfPolicyTrap = function trap(e){ try{ if(e.key==='Escape' && overlay.classList.contains('show')) overlay.__wfPolicyHide(); if(e.key!=='Tab') return; var f=[].filter.call(overlay.querySelectorAll('button,[href],[tabindex]:not([tabindex="-1"])'), function(x){return !x.disabled && x.offsetParent!==null}); if(!f.length) return; var first=f[0], last=f[f.length-1]; if(e.shiftKey){ if(document.activeElement===first){e.preventDefault(); last.focus();}} else { if(document.activeElement===last){e.preventDefault(); first.focus();}} }catch(_){} };
          close.addEventListener('click', overlay.__wfPolicyHide);
          overlay.addEventListener('click', function(e){ if(e.target===overlay) overlay.__wfPolicyHide(); });
        }
        // Show overlay and load content
        overlay.classList.add('show'); overlay.setAttribute('aria-hidden','false'); overlay.style.opacity='1'; overlay.style.visibility='visible';
        var content = document.getElementById('wfPolicyModalContent'); if(content){ var box=document.createElement('div'); box.className='policy-content-box'; box.textContent='Loading…'; content.innerHTML=''; content.appendChild(box); }
        try { document.addEventListener('keydown', overlay.__wfPolicyTrap || function(){}, true); } catch(_){ }
        try { (overlay.querySelector('.policy-modal-close')||overlay).focus(); } catch(_){}
        var target = url + (url.indexOf('?')>-1?'&':'?') + 'modal=1';
        fetch(target, { credentials: 'include' }).then(function(r){ return r.text(); }).then(function(html){
          try { var doc = new DOMParser().parseFromString(html,'text/html'); var node = doc.querySelector('.wf-cloud-card .content') || doc.querySelector('.page-content'); var inner = node ? node.innerHTML : html; if(content){ var box=document.createElement('div'); box.className='policy-content-box'; box.innerHTML = inner; content.innerHTML=''; content.appendChild(box); } }
          catch(_){ if(content){ content.innerHTML = html; } }
        }).catch(function(){ if(content){ content.textContent='Failed to load.'; } });
      } catch(_){ window.location.href = url; }
    };
  })();
  
  (function enforceAboutButtonsPlacement(){
    try {
      var row = document.getElementById('aboutButtonsRow');
      if (!row) return;
      function apply(){
        try{
          row.style.setProperty('position','absolute','important');
          row.style.setProperty('left','50%','important');
          row.style.setProperty('transform','translateX(-50%)','important');
          row.style.setProperty('z-index','2','important');
          row.style.setProperty('pointer-events','auto','');
        }catch(_){ }
      }
      apply();
      try { new MutationObserver(apply).observe(row,{attributes:true,attributeFilter:['style','class']}); } catch(_){ }
      window.addEventListener('resize', apply);
      setTimeout(apply,0);
    } catch(_){ }
  })();
  
  (function bindAboutPolicyButtons(){
    try{
      var row=document.getElementById('aboutButtonsRow');
      if(!row) return;
      row.addEventListener('click', function(e){
        try{
          var a = e.target && e.target.closest ? e.target.closest('a[data-open-policy]') : null;
          if(!a) return;
          e.preventDefault(); e.stopPropagation(); try{ e.stopImmediatePropagation && e.stopImmediatePropagation(); }catch(_){}
          try { if (window.openPolicyModal) { openPolicyModal(a.href, (a.textContent||'').trim()||'Policy'); return; } } catch(_){ }
          try { window.location.href = a.href; } catch(__) {}
        }catch(_){ }
      }, true);
    }catch(_){ }
  })();
  
  (function bindAboutPolicyButtonsDocumentFallback(){
    try{
      if (window.__wfAboutPolicyDocBound) return; window.__wfAboutPolicyDocBound = true;
      document.addEventListener('click', function(e){
        try{
          if (e.defaultPrevented) return;
          if (e.metaKey||e.ctrlKey||e.shiftKey||e.altKey||e.button!==0) return;
          var a = e.target && e.target.closest ? e.target.closest('a[data-open-policy]') : null;
          if(!a) return;
          e.preventDefault(); e.stopPropagation(); try{ e.stopImmediatePropagation && e.stopImmediatePropagation(); }catch(_){}
          try { if (window.openPolicyModal) { openPolicyModal(a.href, (a.textContent||'').trim()||'Policy'); return; } } catch(_){ }
          try { window.location.href = a.href; } catch(__) {}
        }catch(_){ }
      }, true);
    }catch(_){ }
  })();
  
  </script>
