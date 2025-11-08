<?php
require_once dirname(__DIR__) . '/includes/auth.php';
requireAdmin(false);
// Admin Settings (JS-powered). Renders the wrapper the module expects and seeds minimal context.
// Guard auth if helper exists - TEMPORARILY DISABLED FOR DEVELOPMENT
// if (function_exists('isLoggedIn') && !isLoggedIn()) {
//     echo '<div class="text-center"><h1 class="text-2xl font-bold text-red-600">Please login to access settings</h1></div>';
//     return;
// }
// CSRF token for Secrets actions
try {
    require_once dirname(__DIR__) . '/includes/csrf.php';
    $__secrets_csrf = csrf_token('admin_secrets');
} catch (Throwable $____) {
    $__secrets_csrf = '';
}

// Current user for account prefill (prefer AuthHelper)
require_once dirname(__DIR__) . '/includes/auth_helper.php';
$userData = class_exists('AuthHelper') ? (AuthHelper::getCurrentUser() ?? []) : (function_exists('getCurrentUser') ? (getCurrentUser() ?? []) : []);
$uid = $userData['id'] ?? ($userData['userId'] ?? '');
$firstNamePrefill = $userData['firstName'] ?? ($userData['first_name'] ?? '');
$lastNamePrefill = $userData['lastName'] ?? ($userData['last_name'] ?? '');
$emailPrefill = $userData['email'] ?? '';

// Basic page title to match admin design

// Ensure shared layout (header/footer) is bootstrapped so the admin navbar is present
if (!defined('WF_LAYOUT_BOOTSTRAPPED')) {
    $page = 'admin';
    include dirname(__DIR__) . '/partials/header.php';
    if (!function_exists('__wf_admin_settings_footer_shutdown')) {
        function __wf_admin_settings_footer_shutdown()
        {
            @include __DIR__ . '/../partials/footer.php';
        }

        // Fallback opener: Site Deployment (migrated to Vite entry handler)
    }
    register_shutdown_function('__wf_admin_settings_footer_shutdown');
}

// Always include admin navbar on settings page, even when accessed directly
$section = 'settings';
include_once dirname(__DIR__) . '/components/admin_nav_tabs.php';

// Reusable settings card renderer
require_once dirname(__DIR__) . '/components/settings_card.php';

// All navbar positioning, spacing, and content layout for admin settings is controlled by CSS sources.
?>
<?php if (!defined('WF_ADMIN_SECTION_WRAPPED')): ?>
<div class="admin-dashboard page-content">
  <div id="admin-section-content">
<?php endif; ?>
<!-- WF: SETTINGS WRAPPER START -->
  <?php
    // Ensure the Settings page JS bundle loads (bridge + lightweight modal factories + handlers)
    // Skippable with wf_diag_no_vite=1 for debugging minimal inline behavior
    if (function_exists('vite')) {
      $skipVite = isset($_GET['wf_diag_no_vite']) && $_GET['wf_diag_no_vite'] === '1';
      if (!$skipVite) {
        // Guard older inline handlers: ensure size-template functions exist to prevent ReferenceError
        echo '<script>'
          . 'window.loadSizeTemplates=window.loadSizeTemplates||function(){};'
          . 'window.renderSizeTemplates=window.renderSizeTemplates||function(){};'
          . 'window.filterSizeTemplates=window.filterSizeTemplates||function(){};'
          . 'window.createNewSizeTemplate=window.createNewSizeTemplate||function(){};'
          . 'window.editSizeTemplate=window.editSizeTemplate||function(){};'
          . 'window.deleteSizeTemplate=window.deleteSizeTemplate||function(){};'
          . 'window.createSizeTemplateEditModal=window.createSizeTemplateEditModal||function(){};'
          . 'window.showSizeTemplateEditModal=window.showSizeTemplateEditModal||function(){};'
          . 'window.closeSizeTemplateEditModal=window.closeSizeTemplateEditModal||function(){};'
          . 'window.addSizeToTemplate=window.addSizeToTemplate||function(){};'
          . 'window.removeSizeFromTemplate=window.removeSizeFromTemplate||function(){};'
          . 'window.saveSizeTemplate=window.saveSizeTemplate||function(){};'
          . '</script>';
        // Align dev/prod mode with header: prefer dev module when reachable and not mixed-content
        $devOrigin = getenv('WF_VITE_ORIGIN');
        if (!$devOrigin) {
          $hot = dirname(__DIR__) . '/hot';
          if (is_file($hot)) { $raw = @file_get_contents($hot); if (is_string($raw) && trim($raw) !== '') $devOrigin = trim($raw); }
        }
        if (!$devOrigin) { $devOrigin = 'http://localhost:5176'; }
        try {
          $p = @parse_url($devOrigin);
          if (is_array($p) && isset($p['host']) && ($p['host'] === '127.0.0.1' || $p['host'] === '0.0.0.0')) {
            $sch = $p['scheme'] ?? 'http'; $hst = 'localhost'; $prt = isset($p['port']) ? (':' . $p['port']) : ''; $pth = $p['path'] ?? '';
            $devOrigin = $sch . '://' . $hst . $prt . $pth;
          }
        } catch (Throwable $e) { /* noop */ }
        $probeOk = false; $pageIsHttps = (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) ? (strtolower((string)$_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https') : ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['SERVER_PORT'] ?? '') == 443));
        $originIsHttp = (stripos((string)$devOrigin, 'http://') === 0);
        $ctx = stream_context_create(['http'=>['timeout'=>0.6,'ignore_errors'=>true],'https'=>['timeout'=>0.6,'ignore_errors'=>true]]);
        $probeOk = @file_get_contents(rtrim($devOrigin,'/') . '/@vite/client', false, $ctx) !== false;
        if ($probeOk && !($pageIsHttps && $originIsHttp)) {
          // Only emit the module entry; header already loads @vite/client in dev
          echo '<script crossorigin="anonymous" type="module" src="' . rtrim($devOrigin,'/') . '/src/entries/admin-settings.js"></script>' . "\n";
        } else {
          // Fall back to production manifest bundle
          echo vite('js/admin-settings.js');
        }
      } else {
        echo "<!-- [Diagnostics] Skipping js/admin-settings.js due to wf_diag_no_vite=1 -->\n";
      }
    }
  ?>
  <div class="settings-page container mx-auto px-4 mt-0" data-page="admin-settings" data-user-id="<?= htmlspecialchars((string)$uid) ?>">
  <noscript>
    <div class="admin-alert alert-warning">
      JavaScript is required to use the Settings page.
    </div>
  </noscript>

  <script>
  (function(){
    function qs(id){ return document.getElementById(id); }
    function setStatus(t, ok){ try{ var s=qs('shippingAttrToolsStatus'); if(!s) return; s.textContent=t||''; s.classList.remove('text-green-700','text-red-700'); s.classList.add(ok?'text-green-700':'text-red-700'); }catch(_){} }
    function renderPreview(r){ try{ var p=qs('shippingAttrToolsPreview'); if(!p) return; var res=(r&&r.results)||{}; var upd=Number(res.updated||0); var sk=Number(res.skipped||0); var ensured=!!res.ensured; var lines=[]; lines.push('Ensured columns: '+(ensured?'yes':'no')); lines.push('Updated: '+upd+', Skipped: '+sk); var prev=Array.isArray(res.preview)?res.preview:[]; if(prev.length){ var list=prev.slice(0,8).map(function(it){ var dims=(it.LxWxH_in||[]).join('×'); return (it.sku||'')+' · '+(it.weight_oz!=null?String(it.weight_oz)+' oz':'')+(dims?(' · '+dims+' in'):''); }); lines.push('Examples: '+list.join('; ')); } p.textContent=lines.join(' | '); }catch(_){} }
    function handleJson(j){ var d=(j&&j.data)||j||{}; renderPreview(d); setStatus('Done', true); }
    function handleErr(){ setStatus('Failed', false); }
    function ensure(){ setStatus('Ensuring…', true); try { var client=(window.WhimsicalFrog&&WhimsicalFrog.api)||window.ApiClient; if(!client||typeof client.get!=='function') throw new Error('no api'); client.get('/api/item_dimensions_tools.php', { action: 'ensure_columns' }).then(handleJson).catch(handleErr); } catch(_) { handleErr(); } }
    function backfill(){ if(!confirm('Run AI backfill for item shipping attributes?')) return; setStatus('Running…', true); try { var client=(window.WhimsicalFrog&&WhimsicalFrog.api)||window.ApiClient; if(!client||typeof client.post!=='function') throw new Error('no api'); client.post('/api/item_dimensions_tools.php', { action:'run_all', use_ai:1 }).then(handleJson).catch(handleErr); } catch(_) { handleErr(); } }
    function init(){ var a=qs('ensureItemDimsBtn'); var b=qs('backfillItemDimsBtn'); if(a&&!a.__wf){ a.__wf=true; a.addEventListener('click', ensure, true); } if(b&&!b.__wf){ b.__wf=true; b.addEventListener('click', backfill, true); } }
    if(document.readyState==='loading'){ document.addEventListener('DOMContentLoaded', init, {once:true}); } else { init(); }
  })();
  </script>

  <script>
  (function(){
    function qs(id){ return document.getElementById(id); }
    function setShipStatus(t, ok){ try{ var s=document.getElementById('shippingSettingsStatus'); if(!s) return; s.textContent=t||''; s.classList.remove('text-green-700','text-red-700'); s.classList.add(ok?'text-green-700':'text-red-700'); }catch(_){} }
    function setLocalStatus(id, t, ok){ try{ var s=document.getElementById(id); if(!s) return; s.textContent=t||''; s.classList.remove('text-green-700','text-red-700'); s.classList.add(ok?'text-green-700':'text-red-700'); }catch(_){} }
    function valNum(el){ var v=(el&&el.value)||''; var n=parseFloat(v); return isFinite(n)?n:''; }
    function renderCatRows(){ try{ var host=qs('catWeightDefaults'); var box=qs('catWeightRows'); if(!host||!box) return; var init=host.getAttribute('data-initial')||'{}'; var obj={}; try{ obj=JSON.parse(init);}catch(_){obj={};} box.innerHTML=''; var keys=Object.keys(obj); if(keys.length===0){ addCatRow('DEFAULT',''); return; } keys.forEach(function(k){ var v=obj[k]; var w=(typeof v==='object'&&v&&typeof v.weight_oz!=='undefined')?v.weight_oz:v; addCatRow(k, w); }); }catch(_){} }
    function addCatRow(k, w){ var box=qs('catWeightRows'); if(!box) return; var row=document.createElement('div'); row.className='grid grid-cols-12 gap-2'; row.innerHTML='<div class="col-span-7 md:col-span-6"><input type="text" class="form-input w-full" placeholder="Category (e.g., TUMBLER, SHIRT, DEFAULT)" value="'+escapeHtml(k||'')+'"/></div><div class="col-span-4 md:col-span-5"><input type="number" step="0.01" min="0" class="form-input w-full" placeholder="oz" value="'+escapeHtml(String(w||''))+'"/></div><div class="col-span-1 flex items-center"><button type="button" class="btn btn-secondary btn-sm" aria-label="Remove">×</button></div>'; box.appendChild(row); var btn=row.querySelector('button'); if(btn&&!btn.__wf){ btn.__wf=true; btn.addEventListener('click', function(){ try{ row.parentNode.removeChild(row);}catch(_){} }, true); }
    }
    function escapeHtml(s){ try{ return String(s).replace(/[&<>"']/g,function(c){return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;','\'':'&#39;'}[c]);}); }catch(_){ return String(s); } }
    function gatherCatMap(){ var box=qs('catWeightRows'); var map={}; if(!box) return map; var rows=box.children||[]; for(var i=0;i<rows.length;i++){ var r=rows[i]; var c=r.querySelector('input[type="text"]'); var n=r.querySelector('input[type="number"]'); var key=(c&&c.value||'').trim().toUpperCase(); if(!key) continue; var num=n?parseFloat(n.value):NaN; if(isFinite(num)) map[key]=num; }
      return map;
    }
    function onAddRow(){ addCatRow('', ''); }
    function onSave(e){
      e && e.preventDefault && e.preventDefault();
      setShipStatus('Saving…', true);
      var payload = {
        category: 'ecommerce',
        settings: {
          free_shipping_threshold: qs('freeShippingThresholdInput')?.value || '',
          local_delivery_fee: qs('localDeliveryFeeInput')?.value || '',
          shipping_rate_usps: qs('baseUspsInput')?.value || '',
          shipping_rate_fedex: qs('baseFedexInput')?.value || '',
          shipping_rate_ups: qs('baseUpsInput')?.value || '',
          shipping_rate_per_lb_usps: qs('perLbUspsInput')?.value || '',
          shipping_rate_per_lb_fedex: qs('perLbFedexInput')?.value || '',
          shipping_rate_per_lb_ups: qs('perLbUpsInput')?.value || '',
          shipping_category_weight_defaults: gatherCatMap()
        }
        
      };

      // Debug helpers
      try {
        window.__wfDebugAttrPing = () => {
          try {
            var client=(window.WhimsicalFrog&&WhimsicalFrog.api)||window.ApiClient;
            if(!client||typeof client.get!=='function') return Promise.resolve('');
            var url='/components/embeds/attributes_manager.php?modal=1&_='+Date.now();
            return client.get(url).then(function(txt){ try{ console.log('[WF] Debug ping length', (txt||'').length); }catch(_){} return (txt||'').slice(0,200); });
          } catch(_) { return Promise.resolve(''); }
        };
      } catch(_) {}

      // Fallback renderer when embed fetch fails (no attributes_manager.php request or empty HTML)
      async function mountAttributesFallback(mount){
        try { console.warn('[WF] Attributes: entering fallback renderer'); } catch(_) {}
        if (!mount) return;
        mount.innerHTML = `
          <div class="attributes-grid">
            <div class="card">
              <div class="card-header">Genders</div>
              <div class="card-body"><div id="genderList">Loading…</div></div>
            </div>
            <div class="card">
              <div class="card-header">Sizes</div>
              <div class="card-body"><div id="sizeList">Loading…</div></div>
            </div>
            <div class="card">
              <div class="card-header">Colors</div>
              <div class="card-body"><div id="colorList">Loading…</div></div>
            </div>
          </div>`;
        const j = (u)=> { try { var client=(window.WhimsicalFrog&&WhimsicalFrog.api)||window.ApiClient; if(!client||typeof client.get!=='function') return Promise.resolve(null); return client.get(u).catch(()=>null); } catch(_) { return Promise.resolve(null); } };
        try {
          const g = await j('/api/genders_admin.php?action=list_distinct');
          const gl = document.getElementById('genderList');
          if (gl) gl.innerHTML = (g && g.success && Array.isArray(g.genders) && g.genders.length)
            ? `<ul class="simple">${g.genders.map(x=>`<li><span>${String(x)}</span></li>`).join('')}</ul>`
            : '<div class="empty">No genders</div>';
        } catch(_){ try { document.getElementById('genderList').textContent = 'Failed to load'; } catch(__){} }
        try {
          const s = await j('/api/size_templates.php?action=get_all');
          const sl = document.getElementById('sizeList');
          if (sl) sl.innerHTML = (s && s.success && Array.isArray(s.templates) && s.templates.length)
            ? `<ul class="simple">${s.templates.map(t=>`<li><span>${String(t.template_name||'')} ${t.category?('· '+t.category):''}</span></li>`).join('')}</ul>`
            : '<div class="empty">No size templates</div>';
        } catch(_){ try { document.getElementById('sizeList').textContent = 'Failed to load'; } catch(__){} }
        try {
          const c = await j('/api/color_templates.php?action=get_all');
          const cl = document.getElementById('colorList');
          if (cl) cl.innerHTML = (c && c.success && Array.isArray(c.templates) && c.templates.length)
            ? `<ul class="simple">${c.templates.map(t=>`<li><span>${String(t.template_name||'')} ${t.category?('· '+t.category):''}</span></li>`).join('')}</ul>`
            : '<div class="empty">No color templates</div>';
        } catch(_){ try { document.getElementById('colorList').textContent = 'Failed to load'; } catch(__){} }
        try { console.warn('[WF] Attributes: fallback render complete'); } catch(_) {}
      }
      try { window.loadSizeColorInline = loadSizeColorInline; } catch(_) {}
      try {
        var client=(window.WhimsicalFrog&&WhimsicalFrog.api)||window.ApiClient;
        if(!client||typeof client.post!=='function') throw new Error('no api');
        client.post('/api/business_settings.php?action=upsert_settings', { category: payload.category, settings: payload.settings })
          .then(function(j){ setShipStatus('Saved', true); try { console.info('[Shipping Settings] upsert ->', j); } catch(_){} })
          .catch(function(){ setShipStatus('Failed to save', false); });
      } catch(_) { setShipStatus('Failed to save', false); }
    }
    function onSaveRates(e){ e&&e.preventDefault&&e.preventDefault(); setLocalStatus('shippingRatesLocalStatus','Saving…',true); try { var client=(window.WhimsicalFrog&&WhimsicalFrog.api)||window.ApiClient; if(!client||typeof client.post!=='function') throw new Error('no api'); client.post('/api/business_settings.php?action=upsert_settings', { category:'ecommerce', settings:{ free_shipping_threshold: qs('freeShippingThresholdInput')?.value||'', local_delivery_fee: qs('localDeliveryFeeInput')?.value||'', shipping_rate_usps: qs('baseUspsInput')?.value||'', shipping_rate_fedex: qs('baseFedexInput')?.value||'', shipping_rate_ups: qs('baseUpsInput')?.value||'' } }).then(function(){ setLocalStatus('shippingRatesLocalStatus','Saved',true); }).catch(function(){ setLocalStatus('shippingRatesLocalStatus','Failed',false); }); } catch(_){ setLocalStatus('shippingRatesLocalStatus','Failed',false);} }
    function onSavePerLb(e){ e&&e.preventDefault&&e.preventDefault(); setLocalStatus('perLbRatesLocalStatus','Saving…',true); try { var client=(window.WhimsicalFrog&&WhimsicalFrog.api)||window.ApiClient; if(!client||typeof client.post!=='function') throw new Error('no api'); client.post('/api/business_settings.php?action=upsert_settings', { category:'ecommerce', settings:{ shipping_rate_per_lb_usps: qs('perLbUspsInput')?.value||'', shipping_rate_per_lb_fedex: qs('perLbFedexInput')?.value||'', shipping_rate_per_lb_ups: qs('perLbUpsInput')?.value||'' } }).then(function(){ setLocalStatus('perLbRatesLocalStatus','Saved',true); }).catch(function(){ setLocalStatus('perLbRatesLocalStatus','Failed',false); }); } catch(_){ setLocalStatus('perLbRatesLocalStatus','Failed',false);} }
    function onSaveCatWeights(e){ e&&e.preventDefault&&e.preventDefault(); setLocalStatus('catWeightsLocalStatus','Saving…',true); try { var client=(window.WhimsicalFrog&&WhimsicalFrog.api)||window.ApiClient; if(!client||typeof client.post!=='function') throw new Error('no api'); client.post('/api/business_settings.php?action=upsert_settings', { category:'ecommerce', settings:{ shipping_category_weight_defaults: gatherCatMap() } }).then(function(){ setLocalStatus('catWeightsLocalStatus','Saved',true); }).catch(function(){ setLocalStatus('catWeightsLocalStatus','Failed',false); }); } catch(_){ setLocalStatus('catWeightsLocalStatus','Failed',false);} }
    function init(){ var btn = document.getElementById('shippingSettingsSaveBtn'); if(btn && !btn.__wf){ btn.__wf = true; btn.addEventListener('click', onSave, true); } var add=qs('addCatWeightRowBtn'); if(add && !add.__wf){ add.__wf=true; add.addEventListener('click', onAddRow, true);} var rb=qs('saveShippingRatesBtn'); if(rb&&!rb.__wf){ rb.__wf=true; rb.addEventListener('click', onSaveRates, true);} var pb=qs('savePerLbRatesBtn'); if(pb&&!pb.__wf){ pb.__wf=true; pb.addEventListener('click', onSavePerLb, true);} var cb=qs('saveCatWeightsBtn'); if(cb&&!cb.__wf){ cb.__wf=true; cb.addEventListener('click', onSaveCatWeights, true);} var f1=qs('shippingRatesForm'); if(f1&&!f1.__wf){ f1.__wf=true; f1.addEventListener('submit', onSaveRates, true);} var f2=qs('perLbRatesForm'); if(f2&&!f2.__wf){ f2.__wf=true; f2.addEventListener('submit', onSavePerLb, true);} var f3=qs('catWeightsForm'); if(f3&&!f3.__wf){ f3.__wf=true; f3.addEventListener('submit', onSaveCatWeights, true);} renderCatRows(); }
    if(document.readyState==='loading'){ document.addEventListener('DOMContentLoaded', init, {once:true}); } else { init(); }
  })();
  </script>

  <?php
  // Cart & Checkout settings moved into Shopping Cart modal (server-side data)
  try {
      require_once dirname(__DIR__) . '/api/business_settings_helper.php';
  } catch (Throwable $____e) {}
  try {
      require_once dirname(__DIR__) . '/includes/upsell_rules_helper.php';
  } catch (Throwable $____e) {}
  $ecomm = class_exists('BusinessSettings') ? (BusinessSettings::getByCategory('ecommerce') ?? []) : [];

  // Precompute cart settings
  $openOnAdd = strtolower((string)($ecomm['ecommerce_open_cart_on_add'] ?? 'false'));
  $mergeDupes = strtolower((string)($ecomm['ecommerce_cart_merge_duplicates'] ?? 'true'));
  $showUpsells = strtolower((string)($ecomm['ecommerce_cart_show_upsells'] ?? 'false'));
  $confirmClear = strtolower((string)($ecomm['ecommerce_cart_confirm_clear'] ?? 'true'));
  $minTotal = (string)($ecomm['ecommerce_cart_minimum_total'] ?? '0');
  $upsellAutoData = [];
  $upsellSiteLeaders = [];
  $upsellSamplePairs = [];

  if (function_exists('wf_generate_cart_upsell_rules')) {
      try {
          $generatedUpsellRules = wf_generate_cart_upsell_rules();
          if (is_array($generatedUpsellRules)) {
              $upsellAutoData = $generatedUpsellRules;
          }
      } catch (Throwable $____e) {}
  }

  if (!empty($upsellAutoData) && isset($upsellAutoData['map']) && is_array($upsellAutoData['map'])) {
      $map = $upsellAutoData['map'];
      $products = isset($upsellAutoData['products']) && is_array($upsellAutoData['products']) ? $upsellAutoData['products'] : [];

      $defaultLeaders = isset($map['_default']) && is_array($map['_default']) ? array_slice($map['_default'], 0, 3) : [];
      foreach ($defaultLeaders as $leaderSku) {
          $leaderSku = strtoupper(trim((string)$leaderSku));
          if ($leaderSku === '') { continue; }
          $label = $leaderSku;
          if (isset($products[$leaderSku]['name']) && $products[$leaderSku]['name'] !== '') {
              $label = $products[$leaderSku]['name'] . ' (' . $leaderSku . ')';
          }
          if (!in_array($label, $upsellSiteLeaders, true)) {
              $upsellSiteLeaders[] = $label;
          }
      }

      $pairCount = 0;
      foreach ($map as $sourceSku => $targets) {
          if ($sourceSku === '_default') { continue; }
          if (!is_array($targets) || !$targets) { continue; }
          $sourceSku = strtoupper(trim((string)$sourceSku));
          if ($sourceSku === '') { continue; }
          $sourceLabel = $sourceSku;
          if (isset($products[$sourceSku]['name']) && $products[$sourceSku]['name'] !== '') {
              $sourceLabel = $products[$sourceSku]['name'] . ' (' . $sourceSku . ')';
          }
          $recommendationLabels = [];
          foreach (array_slice($targets, 0, 3) as $targetSku) {
              $targetSku = strtoupper(trim((string)$targetSku));
              if ($targetSku === '') { continue; }
              $targetLabel = $targetSku;
              if (isset($products[$targetSku]['name']) && $products[$targetSku]['name'] !== '') {
                  $targetLabel = $products[$targetSku]['name'] . ' (' . $targetSku . ')';
              }
              $recommendationLabels[] = $targetLabel;
          }
          if ($recommendationLabels) {
              $upsellSamplePairs[] = [
                  'source' => $sourceLabel,
                  'recommendations' => $recommendationLabels,
              ];
              $pairCount++;
          }
          if ($pairCount >= 3) { break; }
      }
  }
  ?>

  <!-- STATIC: Shipping & Distance Settings Modal (outside <noscript>) -->
  <div id="shippingSettingsModal" class="admin-modal-overlay wf-modal--content-scroll wf-modal-closable hidden z-10110" aria-hidden="true" role="dialog" aria-modal="true" tabindex="-1" aria-labelledby="shippingSettingsTitle">
    <div class="admin-modal admin-modal-content admin-modal--actions-in-header">
      <div class="modal-header">
        <h2 id="shippingSettingsTitle" class="admin-card-title">🚚 Shipping &amp; Distance Settings</h2>
        <div class="modal-header-actions">
          <span class="text-sm text-gray-600" id="shippingSettingsStatus" aria-live="polite"></span>
          <button type="button" class="btn btn-primary btn-sm" id="shippingSettingsSaveBtn">Save Settings</button>
        </div>
        <button type="button" class="admin-modal-close wf-admin-nav-button" data-action="close-admin-modal" aria-label="Close">×</button>
      </div>
      <div class="modal-body wf-modal-body--scroll">
        <div id="shippingSettingsFormStatic" class="wf-modal-form space-y-4">
          <fieldset class="border rounded p-3">
            <legend class="text-sm font-semibold">USPS</legend>
            <label class="block text-sm font-medium mb-1" for="uspsUserId">USPS Web Tools USERID</label>
            <input id="uspsUserId" type="text" class="form-input w-full" placeholder="(required for USPS live rates)" autocomplete="username" />
          </fieldset>
          <form id="upsSecretsForm" data-action="prevent-submit" autocomplete="off">
          <fieldset class="border rounded p-3">
            <legend class="text-sm font-semibold">UPS</legend>
            <div class="grid gap-3 md:grid-cols-2">
              <div>
                <label class="block text-sm font-medium mb-1" for="upsAccessKey">UPS Access Key</label>
                <input id="upsAccessKey" type="text" class="form-input w-full" placeholder="optional" autocomplete="off" />
              </div>
              <div>
                <label class="block text-sm font-medium mb-1" for="upsSecret">UPS Secret</label>
                <input id="upsSecret" type="password" class="form-input w-full" placeholder="optional" autocomplete="new-password" />
              </div>
            </div>
          </fieldset>
          </form>
          <form id="fedexSecretsForm" data-action="prevent-submit" autocomplete="off">
          <fieldset class="border rounded p-3">
            <legend class="text-sm font-semibold">FedEx</legend>
            <div class="grid gap-3 md:grid-cols-2">
              <div>
                <label class="block text-sm font-medium mb-1" for="fedexKey">FedEx Key</label>
                <input id="fedexKey" type="text" class="form-input w-full" placeholder="optional" autocomplete="off" />
              </div>
              <div>
                <label class="block text-sm font-medium mb-1" for="fedexSecret">FedEx Secret</label>
                <input id="fedexSecret" type="password" class="form-input w-full" placeholder="optional" autocomplete="new-password" />
              </div>
            </div>
          </fieldset>
          </form>
          <form id="shippingRatesForm" data-action="prevent-submit" class="space-y-2" autocomplete="off">
          <fieldset class="border rounded p-3">
            <legend class="text-sm font-semibold">Shipping Rates</legend>
            <div class="grid gap-3 md:grid-cols-3">
              <div>
                <label class="block text-sm font-medium mb-1" for="freeShippingThresholdInput">Free Shipping Threshold ($)</label>
                <input id="freeShippingThresholdInput" type="number" step="0.01" min="0" class="form-input w-full" value="<?php echo htmlspecialchars((string)($ecomm['free_shipping_threshold'] ?? '50.00'), ENT_QUOTES); ?>" />
              </div>
              <div>
                <label class="block text-sm font-medium mb-1" for="localDeliveryFeeInput">Local Delivery Fee ($)</label>
                <input id="localDeliveryFeeInput" type="number" step="0.01" min="0" class="form-input w-full" value="<?php echo htmlspecialchars((string)($ecomm['local_delivery_fee'] ?? '75.00'), ENT_QUOTES); ?>" />
              </div>
              <div class="hidden md:block"></div>
              <div>
                <label class="block text-sm font-medium mb-1" for="baseUspsInput">Base USPS ($)</label>
                <input id="baseUspsInput" type="number" step="0.01" min="0" class="form-input w-full" value="<?php echo htmlspecialchars((string)($ecomm['shipping_rate_usps'] ?? '8.99'), ENT_QUOTES); ?>" />
              </div>
              <div>
                <label class="block text-sm font-medium mb-1" for="baseFedexInput">Base FedEx ($)</label>
                <input id="baseFedexInput" type="number" step="0.01" min="0" class="form-input w-full" value="<?php echo htmlspecialchars((string)($ecomm['shipping_rate_fedex'] ?? '12.99'), ENT_QUOTES); ?>" />
              </div>
              <div>
                <label class="block text-sm font-medium mb-1" for="baseUpsInput">Base UPS ($)</label>
                <input id="baseUpsInput" type="number" step="0.01" min="0" class="form-input w-full" value="<?php echo htmlspecialchars((string)($ecomm['shipping_rate_ups'] ?? '12.99'), ENT_QUOTES); ?>" />
              </div>
            </div>
          </fieldset>
          <div class="flex items-center justify-end gap-3">
            <span id="shippingRatesLocalStatus" class="text-sm text-gray-600" aria-live="polite"></span>
            <button type="submit" class="btn btn-primary btn-sm" id="saveShippingRatesBtn">Save</button>
          </div>
          </form>
          <form id="perLbRatesForm" data-action="prevent-submit" class="space-y-2" autocomplete="off">
          <fieldset class="border rounded p-3">
            <legend class="text-sm font-semibold">Per-Pound Rates</legend>
            <div class="grid gap-3 md:grid-cols-3">
              <div>
                <label class="block text-sm font-medium mb-1" for="perLbUspsInput">USPS per lb ($)</label>
                <input id="perLbUspsInput" type="number" step="0.01" min="0" class="form-input w-full" value="<?php echo htmlspecialchars((string)($ecomm['shipping_rate_per_lb_usps'] ?? '1.50'), ENT_QUOTES); ?>" />
              </div>
              <div>
                <label class="block text-sm font-medium mb-1" for="perLbFedexInput">FedEx per lb ($)</label>
                <input id="perLbFedexInput" type="number" step="0.01" min="0" class="form-input w-full" value="<?php echo htmlspecialchars((string)($ecomm['shipping_rate_per_lb_fedex'] ?? '2.00'), ENT_QUOTES); ?>" />
              </div>
              <div>
                <label class="block text-sm font-medium mb-1" for="perLbUpsInput">UPS per lb ($)</label>
                <input id="perLbUpsInput" type="number" step="0.01" min="0" class="form-input w-full" value="<?php echo htmlspecialchars((string)($ecomm['shipping_rate_per_lb_ups'] ?? '2.00'), ENT_QUOTES); ?>" />
              </div>
            </div>
            <p class="text-xs text-gray-600 mt-1">These apply in addition to base method rates when shipping is weight-based.</p>
          </fieldset>
          <div class="flex items-center justify-end gap-3">
            <span id="perLbRatesLocalStatus" class="text-sm text-gray-600" aria-live="polite"></span>
            <button type="submit" class="btn btn-primary btn-sm" id="savePerLbRatesBtn">Save</button>
          </div>
          </form>
          <form id="catWeightsForm" data-action="prevent-submit" class="space-y-2" autocomplete="off">
          <fieldset class="border rounded p-3">
            <legend class="text-sm font-semibold">Category Weight Defaults</legend>
            <p class="text-xs text-gray-600 mb-2">Optional overrides used when an item is missing a weight. Exact match wins, else partial contains match, else <code>DEFAULT</code> if provided. Keys are case-insensitive.</p>
            <?php $catMapJson = htmlspecialchars(json_encode($ecomm['shipping_category_weight_defaults'] ?? []), ENT_QUOTES); ?>
            <div id="catWeightDefaults" data-initial="<?php echo $catMapJson; ?>">
              <div class="grid grid-cols-12 gap-2 text-xs font-medium text-gray-600 mb-1">
                <div class="col-span-7 md:col-span-6">Category</div>
                <div class="col-span-4 md:col-span-5">Default Weight (oz)</div>
                <div class="col-span-1"></div>
              </div>
              <div id="catWeightRows" class="space-y-2"></div>
              <div class="mt-2">
                <button type="button" class="btn btn-secondary btn-sm" id="addCatWeightRowBtn">Add Row</button>
              </div>
            </div>
          </fieldset>
          <div class="flex items-center justify-end gap-3">
            <span id="catWeightsLocalStatus" class="text-sm text-gray-600" aria-live="polite"></span>
            <button type="submit" class="btn btn-primary btn-sm" id="saveCatWeightsBtn">Save</button>
          </div>
          </form>
          <div class="rounded border p-3 mt-4" id="shippingAttrTools">
            <div class="flex items-center justify-between gap-2">
              <h3 class="text-sm font-semibold">Item Shipping Attributes</h3>
              <span id="shippingAttrToolsStatus" class="text-sm text-gray-600" aria-live="polite"></span>
            </div>
            <div class="mt-2 flex items-center gap-2">
              <button type="button" class="btn btn-secondary" id="ensureItemDimsBtn">Ensure Columns</button>
              <button type="button" class="btn btn-primary" id="backfillItemDimsBtn">Backfill with AI</button>
            </div>
            <div id="shippingAttrToolsPreview" class="mt-2 text-xs text-gray-600"></div>
          </div>
          <fieldset class="border rounded p-3">
            <legend class="text-sm font-semibold">Driving Distance</legend>
            <label class="block text-sm font-medium mb-1" for="orsKey">OpenRouteService API Key</label>
            <input id="orsKey" type="text" class="form-input w-full" placeholder="optional (used for driving miles)" autocomplete="off" />
          </fieldset>
          <fieldset class="border rounded p-3">
            <legend class="text-sm font-semibold">Address Diagnostics</legend>
            <div id="addressDiagnosticsInline" class="space-y-3">
              <div>
                <div class="text-sm font-semibold mb-1">Canonical Business Address</div>
                <div id="addrDiagBizBlock" class="font-mono text-sm"></div>
                <div class="text-sm text-gray-600">Sourced from business_info: business_address, business_address2, business_city, business_state, business_postal.</div>
              </div>
              <div>
                <div class="text-sm font-semibold mb-2">Compute Miles To Target</div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                  <div>
                    <label class="block font-semibold text-gray-700 mb-1" for="addrDiagToAddress">Address Line 1</label>
                    <input id="addrDiagToAddress" class="form-input" placeholder="91 Singletree Ln" />
                  </div>
                  <div>
                    <label class="block font-semibold text-gray-700 mb-1" for="addrDiagToCity">City</label>
                    <input id="addrDiagToCity" class="form-input" placeholder="Dawsonville" />
                  </div>
                  <div>
                    <label class="block font-semibold text-gray-700 mb-1" for="addrDiagToState">State</label>
                    <input id="addrDiagToState" class="form-input" placeholder="GA" />
                  </div>
                  <div>
                    <label class="block font-semibold text-gray-700 mb-1" for="addrDiagToZip">ZIP</label>
                    <input id="addrDiagToZip" class="form-input" placeholder="30534" />
                  </div>
                </div>
                <div class="flex items-center gap-2 mt-3">
                  <button type="button" id="addrDiagCompute" class="btn btn-primary">Compute Miles</button>
                  <button type="button" id="addrDiagUseSample" class="btn btn-secondary">Use Sample Address</button>
                  <span id="addrDiagStatus" class="text-sm text-gray-600" aria-live="polite"></span>
                </div>
                <div id="addrDiagResult" class="mt-3"></div>
                <details class="mt-2">
                  <summary>Debug</summary>
                  <pre id="addrDiagDebug" class="bg-gray-50 p-2 rounded max-h-64 overflow-auto"></pre>
                </details>
              </div>
            </div>
          </fieldset>
          <div class="text-sm text-gray-600">Changes apply immediately. Cache TTL is 24h; rates/distance are auto-cached.</div>
          <div class="wf-modal-actions"></div>
        </div>
      </div>
    </div>
    
    <!-- AI Unified Child Modal (for tools opened above the parent AI modal) -->
    <div id="aiUnifiedChildModal" class="admin-modal-overlay over-header topmost wf-modal-autowide wf-modal-viewport-fill wf-modal-mincols-3 hidden" aria-hidden="true" role="dialog" aria-modal="true" tabindex="-1" aria-labelledby="aiUnifiedChildTitle">
      <div class="admin-modal admin-modal-content admin-modal--responsive admin-modal--actions-in-header">
        <div class="modal-header">
          <h2 id="aiUnifiedChildTitle" class="admin-card-title">AI Tool</h2>
          <button type="button" class="admin-modal-close wf-admin-nav-button" data-action="close-admin-modal" aria-label="Close">×</button>
        </div>
        <div class="modal-body">
          <iframe id="aiUnifiedChildFrame" title="AI Tool" src="about:blank" class="wf-admin-embed-frame" referrerpolicy="no-referrer" data-autosize="1"></iframe>
          <div id="aiUnifiedChildInline" class="wf-ai-inline-tool hidden"></div>
        </div>
      </div>
    </div>
  </div>
  <script>
  (function(){
    function setStatus(t, ok){
      try {
        var s=document.getElementById('cartSimulationStatus'); if(!s) return;
        s.textContent = t || '';
        s.classList.remove('text-green-700','text-red-700');
        s.classList.add(ok ? 'text-green-700' : 'text-red-700');
      } catch(_){ }
    }
    function esc(s){ try { return String(s||'').replace(/[&<>]/g, function(c){return ({'&':'&amp;','<':'&lt;','>':'&gt;'}[c]);}); } catch(_){ return String(s||''); } }
    function renderProfile(d){
      try {
        var p = (d && d.profile) || {};
        var el = document.getElementById('cartSimulationProfile'); if(!el) return;
        var rows = [
          ['Preferred Category', p.preferredCategory||'—'],
          ['Budget', p.budget||'—'],
          ['Intent', p.intent||'—'],
          ['Device', p.device||'—'],
          ['Region', p.region||'—']
        ];
        el.innerHTML = '<ul class="list-none space-y-0.5">'+rows.map(function(r){return '<li><span class="text-gray-500">'+esc(r[0])+':</span> '+esc(r[1])+'</li>';}).join('')+'</ul>';
      } catch(_){ }
    }
    function renderSeed(d){ try { var seedEl=document.getElementById('cartSimulationSeedSkus'); if(seedEl) seedEl.textContent=((d.cart_skus||[]).join(', ')||'—'); } catch(_){ } }
    function renderRecs(d){
      try {
        var list = (d && d.recommendations) || [];
        var recsEl=document.getElementById('cartSimulationRecs'); if(!recsEl) return;
        if(!list.length){ recsEl.innerHTML='<div class="text-sm text-gray-500">No recommendations.</div>'; return; }
        try { recsEl.className = 'grid grid-cols-4 gap-3'; } catch(_) {}
        // Compute a unique primary reason per SKU using priority order
        var priority = ['Site top seller','Category leader',"Matches shopper\'s preferred category",'Strong performer in category','Site second-best seller','Fits shopper budget','High-performing item in catalog'];
        function normalizeReason(s){
          try {
            var t = String(s||'').toLowerCase();
            if (t.indexOf('top seller') !== -1 && t.indexOf('second') === -1) return 'Site top seller';
            if (t.indexOf('second') !== -1 && t.indexOf('seller') !== -1) return 'Site second-best seller';
            if (t.indexOf('category leader') !== -1) return 'Category leader';
            if (t.indexOf('strong performer') !== -1) return 'Strong performer in category';
            if (t.indexOf('preferred category') !== -1) return "Matches shopper's preferred category";
            if (t.indexOf('budget') !== -1) return 'Fits shopper budget';
            if (t.indexOf('high-performing') !== -1) return 'High-performing item in catalog';
            return s;
          } catch(_){ return s; }
        }
        var used = {};
        var primaryBySku = {};
        try {
          list.forEach(function(r){
            var sku = ((r&&r.sku)||'').toString().toUpperCase();
            var rs = (d && d.rationales && d.rationales[sku]) ? d.rationales[sku] : [];
            var norm = rs.map(normalizeReason);
            var chosen = null;
            for (var i=0;i<priority.length;i++){
              var label = priority[i];
              if (norm.indexOf(label) !== -1 && !used[label]) { chosen = label; break; }
            }
            if (!chosen) {
              var backups = ['Fits shopper budget','High-performing item in catalog'];
              for (var j=0;j<backups.length;j++){
                var lb = backups[j];
                if (norm.indexOf(lb) !== -1 && !used[lb]) { chosen = lb; break; }
              }
            }
            if (!chosen) { chosen = used['Popular pick'] ? (norm[0] || 'Recommended') : 'Popular pick'; }
            used[chosen] = true; primaryBySku[sku] = chosen;
          });
        } catch(_){ }
        recsEl.innerHTML = list.map(function(r){
          var sku = ((r&&r.sku)||'').toString().toUpperCase();
          var name = r && r.name ? String(r.name) : sku;
          var price = (r&&r.price!=null) ? ('$'+Number(r.price).toFixed(2)) : '';
          var title = name + ' (' + sku + ') · ' + price;
          var img = r && r.image ? String(r.image) : '';
          var isPlaceholder = /placeholder/i.test(img || '');
          var candidates = [];
          if (img && !isPlaceholder) {
            candidates.push(img);
          } else if (sku) {
            var u = '/images/items/'+encodeURIComponent(sku);
            var l = '/images/items/'+encodeURIComponent(sku.toLowerCase());
            candidates.push(u+'.webp', u+'.png', l+'.webp', l+'.png', u+'A.webp', u+'A.png', l+'A.webp', l+'A.png', u+'B.webp', u+'B.png', l+'B.webp', l+'B.png');
          }
          candidates.push('/images/items/placeholder.webp');
          var first = candidates[0] || '/images/items/placeholder.webp';
          var thumbHtml = '<div class="w-16 h-16 rounded border overflow-hidden">\n'
            + '  <img src="'+esc(first)+'" alt="'+esc(name)+'" class="w-full h-full object-cover" width="64" height="64" loading="lazy" decoding="async" data-candidates="'+esc(candidates.join('|'))+'" data-cursor="0"/>'
            + '</div>';
          var primary = primaryBySku[sku];
          var allReasons = (d && d.rationales && d.rationales[sku] && Array.isArray(d.rationales[sku])) ? d.rationales[sku] : [];
          var intentR = '';
          try { intentR = (allReasons.find(function(rx){ return /^Matches shopping intent/i.test(String(rx||'')); }) || ''); } catch(_){ intentR = ''; }
          var badges = [];
          if (primary) badges.push('<span class="inline-flex items-center px-1.5 py-0.5 rounded bg-gray-100 text-gray-700 text-[11px] mr-1 mb-1">'+esc(primary)+'</span>');
          if (intentR && intentR !== primary) badges.push('<span class="inline-flex items-center px-1.5 py-0.5 rounded bg-blue-50 text-blue-800 border border-blue-200 text-[11px] mr-1 mb-1">'+esc(intentR)+'</span>');
          var reasonHtml = badges.length ? ('<div class="mt-1">'+badges.join('')+'</div>') : '';
          return '<div class="reco-thumb" title="'+esc(title)+'" aria-label="'+esc(title)+'">'
            + thumbHtml
            + '<div class="mt-1 text-sm font-medium truncate" title="'+esc(name)+'">'+esc(name)+'</div>'
            + '<div class="text-xs text-gray-600">'+price+'</div>'
            + reasonHtml
            + '</div>';
        }).join('');
        try {
          recsEl.querySelectorAll('img[data-candidates]').forEach(function(imgEl){
            if (imgEl.__wfErrBound) return; imgEl.__wfErrBound = true;
            imgEl.addEventListener('error', function(){
              try {
                var list = (imgEl.getAttribute('data-candidates') || '').split('|').filter(Boolean);
                var cur = parseInt(imgEl.getAttribute('data-cursor') || '0', 10);
                if (isNaN(cur)) cur = 0;
                var next = cur + 1;
                if (next < list.length) {
                  imgEl.setAttribute('data-cursor', String(next));
                  imgEl.src = list[next];
                }
              } catch(_){ }
            });
          });
        } catch(_){ }
      } catch(_){ }
    }
    function gatherProfile(){
      var p={};
      try { var v=document.getElementById('cartSimPrefCategory')?.value||''; if(v) p.preferredCategory=v; } catch(_){ }
      try { var v2=document.getElementById('cartSimBudget')?.value||''; if(v2) p.budget=v2; } catch(_){ }
      try { var v3=document.getElementById('cartSimIntent')?.value||''; if(v3) p.intent=v3; } catch(_){ }
      try { var v4=document.getElementById('cartSimDevice')?.value||''; if(v4) p.device=v4; } catch(_){ }
      try { var v5=document.getElementById('cartSimRegion')?.value||''; if(v5) p.region=v5; } catch(_){ }
      return p;
    }
    function onRefreshClick(e){
      var btn = e.target && e.target.closest && e.target.closest('[data-action="refresh-cart-simulation"]');
      if(!btn) return;
      e.preventDefault(); e.stopPropagation();
      setStatus('Generating shopper and recommendations…', true);
      var body = { limit: 4 }; var prof = gatherProfile(); if (Object.keys(prof).length) body.profile = prof;
      try {
        (window.WhimsicalFrog&&WhimsicalFrog.api||{}).post('/api/cart_upsell_simulation.php', body)
          .then(function(j){ var d=(j&&j.data)||j||{}; renderProfile(d); renderSeed(d); renderRecs(d); setStatus('Recommendations updated'+(d.id?' (Simulation #'+d.id+')':''), true); })
          .catch(function(){ setStatus('Failed to generate recommendations', false); });
      } catch(_) { setStatus('Failed to generate recommendations', false); }
    }
    function onHistoryClick(e){
      var btn = e.target && e.target.closest && e.target.closest('[data-action="load-cart-sim-history"]');
      if(!btn) return;
      e.preventDefault(); e.stopPropagation();
      var box=document.getElementById('cartSimulationHistoryBox'); var list=document.getElementById('cartSimulationHistory'); if(!box||!list) return;
      box.classList.remove('hidden'); list.innerHTML='<div class="text-sm text-gray-500">Loading…</div>';
      try {
        (window.WhimsicalFrog&&WhimsicalFrog.api||{}).get('/api/cart_upsell_history.php', { limit: 20 })
          .then(function(j){ var d=(j&&j.data)||j||{}; var items=Array.isArray(d.items)?d.items:[]; if(!items.length){ list.innerHTML='<div class="text-sm text-gray-500">No simulations yet.</div>'; return; }
          list.innerHTML = items.map(function(it){ var id=it.id; var ts=esc(it.created_at||''); var prof=it.profile||{}; var cat=prof.preferredCategory||'—'; var first=(it.recommendations||[])[0]||null; var name=first?(esc(first.name||first.sku||'')):'—'; return '<div class="rounded border p-2 text-sm flex items-center justify-between gap-3"><div><div class="font-medium">Simulation #'+id+'</div><div class="text-xs text-gray-500">'+ts+' · Pref Cat: '+esc(cat)+'</div></div><div class="text-xs text-gray-600">Top Rec: '+name+'</div></div>'; }).join('');
          })
          .catch(function(){ list.innerHTML='<div class="text-sm text-red-700">Failed to load history</div>'; });
      } catch(_) { list.innerHTML='<div class="text-sm text-red-700">Failed to load history</div>'; }
    }
    function init(){
      try { document.removeEventListener('click', onRefreshClick, true); } catch(_){ }
      try { document.removeEventListener('click', onHistoryClick, true); } catch(_){ }
      document.addEventListener('click', onRefreshClick, true);
      document.addEventListener('click', onHistoryClick, true);

    // Address Diagnostics (inline) wiring
    try {
      var addrBox = document.getElementById('addressDiagnosticsInline');
      if (addrBox && !addrBox.__wfInit) {
        addrBox.__wfInit = true;
        (function(){
          var biz = null;
          var $ = function(id){ return document.getElementById(id); };
          var statusEl = $('addrDiagStatus');
          var resultEl = $('addrDiagResult');
          var debugEl = $('addrDiagDebug');
          function esc(s){ try { return String(s||'').replace(/[&<>"']/g, function(c){ return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;','\'':'&#39;'}[c]); }); } catch(_){ return String(s||''); } }
          function wfApi(){ return (window.WhimsicalFrog && WhimsicalFrog.api) || window.ApiClient || null; }
          function setStatus(t){ try { if (statusEl) statusEl.textContent = t || ''; } catch(_){} }
          function renderBiz(info){
            try {
              var lines = [];
              if (info.business_address) lines.push(info.business_address);
              if (info.business_address2) lines.push(info.business_address2);
              var cityLine = '';
              if (info.business_city) cityLine += info.business_city;
              if (info.business_state) cityLine += (cityLine?', ':'') + info.business_state;
              if (info.business_postal) cityLine += (cityLine?' ':'') + info.business_postal;
              if (cityLine) lines.push(cityLine);
              var box = $('addrDiagBizBlock'); if (box) box.innerHTML = lines.map(esc).join('<br />');
            } catch(_){}
          }
          // Load business info
          try {
            setStatus('Loading business info…');
            var A = wfApi();
            if (A && typeof A.get === 'function') {
              A.get('/api/business_settings.php?action=get_business_info')
                .then(function(j){ biz = (j && j.data) || j || {}; renderBiz(biz); setStatus(''); })
                .catch(function(){ setStatus('Failed to load business info'); });
            }
          } catch(_) { setStatus('Failed to load business info'); }
          // Sample button
          try {
            var sampleBtn = $('addrDiagUseSample');
            if (sampleBtn && !sampleBtn.__wfInit) {
              sampleBtn.__wfInit = true;
              sampleBtn.addEventListener('click', function(e){ e.preventDefault();
                try { $('addrDiagToAddress').value = '91 Singletree Ln'; } catch(_){}
                try { $('addrDiagToCity').value = 'Dawsonville'; } catch(_){}
                try { $('addrDiagToState').value = 'GA'; } catch(_){}
                try { $('addrDiagToZip').value = '30534'; } catch(_){}
              });
            }
          } catch(_){}
          // Compute button
          try {
            var computeBtn = $('addrDiagCompute');
            if (computeBtn && !computeBtn.__wfInit) {
              computeBtn.__wfInit = true;
              computeBtn.addEventListener('click', function(e){ e.preventDefault();
                if (!biz) { setStatus('Business info not loaded'); return; }
                try { resultEl.textContent=''; debugEl.textContent=''; } catch(_){}
                setStatus('Computing…');
                var from = { address: biz.business_address||'', city: biz.business_city||'', state: biz.business_state||'', zip: biz.business_postal||'' };
                var to = { address: ($('addrDiagToAddress')?.value||'').trim(), city: ($('addrDiagToCity')?.value||'').trim(), state: ($('addrDiagToState')?.value||'').trim(), zip: ($('addrDiagToZip')?.value||'').trim() };
                var A2 = wfApi();
                if (A2 && typeof A2.post === 'function') {
                  A2.post('/api/distance.php', { from: from, to: to, debug: true })
                    .then(function(jr){
                      try {
                        var d = (jr && jr.data) || jr || {};
                        var miles = (typeof d.miles !== 'undefined') ? d.miles : null;
                        setStatus('');
                        if (miles === null) {
                          resultEl.innerHTML = '<strong>Result:</strong> miles = null (ineligible)';
                        } else {
                          var cached = !!d.cached; var estimated = !!d.estimated;
                          resultEl.innerHTML = '<strong>Result:</strong> '+ Number(miles).toFixed(2) +' miles' + (cached?' (cached)':'') + (estimated?' [estimated]':'');
                        }
                        debugEl.textContent = JSON.stringify(d, null, 2);
                      } catch(err){ setStatus('Error computing miles'); try { debugEl.textContent = String(err && err.message || err); } catch(_){} }
                    })
                    .catch(function(){ setStatus('Error computing miles'); });
                } else {
                  setStatus('ApiClient unavailable');
                }
              });
            }
          } catch(_){}
        })();
      }
    } catch(_){}

    // Enforce skinny profile panel without inline styles
    try {
      var panels = document.getElementById('cartSimulationPanels');
      if (panels && !panels.__wfSlim) {
        panels.__wfSlim = true;
        var kids = panels.children || [];
        var profile = kids[0];
        var recs = kids[1];
        if (profile && recs) {
          panels.classList.add('flex','items-start','gap-3');
          profile.classList.remove('p-3');
          profile.classList.add('p-1','w-40','md:w-48','shrink-0');
          recs.classList.add('flex-1','min-w-0');
        }
      }
    } catch(_){ }
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init, { once:true }); else init();
  })();
  </script>

  <!-- Colors & Fonts Modal (branding settings moved here) -->
  <div id="colorsFontsModal" class="admin-modal-overlay wf-modal--content-scroll hidden" aria-hidden="true" role="dialog" aria-modal="true" tabindex="-1" aria-labelledby="colorsFontsTitle">
    <div class="admin-modal admin-modal-content admin-modal--xl admin-modal--actions-in-header">
      <div class="modal-header">
        <h2 id="colorsFontsTitle" class="admin-card-title">🎨 Colors &amp; Fonts</h2>
        <div class="modal-header-actions">
          <span id="colorsFontsStatus" class="text-sm text-gray-600" aria-live="polite"></span>
          <button type="button" class="btn btn-primary btn-sm" data-action="business-save-branding">Save</button>
        </div>
        <button type="button" class="admin-modal-close wf-admin-nav-button" data-action="close-admin-modal" aria-label="Close">×</button>
      </div>
      <div class="modal-body wf-modal-body--scroll bg-white/60 backdrop-blur-sm rounded">
        <form id="colorsFontsForm" data-action="prevent-submit" class="space-y-4">
          <div class="grid gap-4 md:grid-cols-2">
            <div class="md:col-span-2">
              <div id="brandPreviewCard" class="rounded border p-3">
                <div id="brandPreviewTitle" class="font-bold mb-1">Brand Backup</div>
                <div id="brandPreviewText" class="text-sm"><span class="text-gray-600">Saved:</span> <span id="brandBackupSavedAt">Never</span></div>
                <div id="brandPreviewSwatches" class="flex items-center gap-2 mt-2" aria-hidden="true"></div>
                <div class="flex items-center gap-2 mt-3">
                  <button type="button" class="btn btn-secondary" data-action="business-backup-open">Create/Replace Backup</button>
                  <button type="button" class="btn btn-secondary" data-action="business-reset-branding">Reset Branding</button>
                </div>
              </div>
            </div>
          </div>

          <div class="border-t pt-4 mt-4">
            <h3 class="text-sm font-semibold mb-2">Brand Fonts</h3>
            <div class="grid gap-4 md:grid-cols-2">
              <div>
                <label class="block text-sm font-medium mb-1">Primary Font</label>
                <div class="flex items-center gap-2">
                  <input id="brandFontPrimary" type="hidden" />
                  <span id="brandFontPrimaryLabel" class="font-preview-label font-preview-label--primary">System UI (Sans-serif)</span>
                  <button type="button" class="btn btn-secondary btn-sm" data-action="open-font-picker" data-font-target="primary">Edit</button>
                </div>
              </div>
              <div>
                <label class="block text-sm font-medium mb-1">Secondary Font</label>
                <div class="flex items-center gap-2">
                  <input id="brandFontSecondary" type="hidden" />
                  <span id="brandFontSecondaryLabel" class="font-preview-label font-preview-label--secondary">Merriweather (Serif)</span>
                  <button type="button" class="btn btn-secondary btn-sm" data-action="open-font-picker" data-font-target="secondary">Edit</button>
                </div>
              </div>
            </div>
          </div>

          <div class="border-t pt-4 mt-4">
            <h3 class="text-sm font-semibold mb-2">Brand Colors</h3>
            <div class="grid gap-4 md:grid-cols-2">
              <div>
                <label for="brandPrimary" class="block text-sm font-medium mb-1">Primary Color</label>
                <input id="brandPrimary" type="color" class="form-input w-16" />
              </div>
              <div>
                <label for="brandSecondary" class="block text-sm font-medium mb-1">Secondary Color</label>
                <input id="brandSecondary" type="color" class="form-input w-16" />
              </div>
              <div>
                <label for="brandAccent" class="block text-sm font-medium mb-1">Accent Color</label>
                <input id="brandAccent" type="color" class="form-input w-16" />
              </div>
              <div class="md:col-span-2">
                <h4 class="text-xs font-semibold text-gray-600 mt-2">Admin Site Colors</h4>
              </div>
              <div>
                <label for="brandBackground" class="block text-sm font-medium mb-1">Background Color</label>
                <input id="brandBackground" type="color" class="form-input w-16" />
              </div>
              <div>
                <label for="brandText" class="block text-sm font-medium mb-1">Text Color</label>
                <input id="brandText" type="color" class="form-input w-16" />
              </div>
            </div>
              <div>
                <label for="cssToastText" class="block text-sm font-medium mb-1">Toast Text</label>
                <input id="cssToastText" name="toast_text" type="color" class="form-input w-full" value="#ffffff" />
                <p class="text-xs text-gray-500">Maps to <code>--toast-text</code></p>
              </div>
          </div>

          <div class="border-t pt-4 mt-4">
            <h3 class="text-sm font-semibold mb-2">Public Site Colors</h3>
            <div class="grid gap-4 md:grid-cols-2">
              <div>
                <label for="publicHeaderBg" class="block text-sm font-medium mb-1">Header Background</label>
                <input id="publicHeaderBg" type="color" class="form-input w-16" />
              </div>
              <div>
                <label for="publicHeaderText" class="block text-sm font-medium mb-1">Header Text</label>
                <input id="publicHeaderText" type="color" class="form-input w-16" />
              </div>
              <div>
                <label for="publicModalBg" class="block text-sm font-medium mb-1">Modal Background</label>
                <input id="publicModalBg" type="color" class="form-input w-16" />
              </div>
              <div>
                <label for="publicModalText" class="block text-sm font-medium mb-1">Modal Text</label>
                <input id="publicModalText" type="color" class="form-input w-16" />
              </div>
              <div>
                <label for="publicPageBg" class="block text-sm font-medium mb-1">Page Background</label>
                <input id="publicPageBg" type="color" class="form-input w-16" />
              </div>
              <div>
                <label for="publicPageText" class="block text-sm font-medium mb-1">Page Text</label>
                <input id="publicPageText" type="color" class="form-input w-16" />
              </div>
            </div>
          </div>

          <div class="border-t pt-4 mt-4">
            <h3 class="text-sm font-semibold mb-2">Brand Palette</h3>
            <div id="brandPaletteContainer" class="mb-2"></div>
            <div class="flex items-center gap-2">
              <input id="newPaletteName" type="text" class="form-input flex-grow" placeholder="--css-variable-name" />
              <input id="newPaletteHex" type="color" class="form-input w-16" value="#000000" />
              <button type="button" class="btn btn-secondary" data-action="business-palette-add">Add</button>
            </div>
          </div>

          <div class="mt-4">
            <label for="customCssVars" class="block text-sm font-medium mb-1">Custom CSS Variables</label>
            <textarea id="customCssVars" class="form-textarea w-full" rows="3" placeholder="--brand-border: #cccccc;"></textarea>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Branding Backup Confirm Modal -->
  <div id="brandingBackupModal" class="admin-modal-overlay wf-modal--content-scroll hidden" aria-hidden="true" role="dialog" aria-modal="true" tabindex="-1" aria-labelledby="brandingBackupTitle">
    <div class="admin-modal admin-modal-content max-w-xl admin-modal--actions-in-header">
      <div class="modal-header">
        <h2 id="brandingBackupTitle" class="admin-card-title">Confirm Branding Backup</h2>
        <button type="button" class="admin-modal-close wf-admin-nav-button" data-action="close-admin-modal" aria-label="Close">×</button>
      </div>
      <div class="modal-body space-y-3">
        <p class="text-sm text-gray-700">
          Creating a new backup will <strong>replace the existing backup</strong>. The current CSS settings (colors, fonts, custom variables, and palette) will be saved as the new backup.
        </p>
        <div class="rounded border p-3 bg-gray-50">
          <div class="font-semibold mb-1 text-sm">Backup Preview</div>
          <div id="brandingBackupSummary" class="text-sm text-gray-700">Loading…</div>
        </div>
      </div>
      <div class="modal-footer flex items-center justify-end gap-2">
        <button type="button" class="btn btn-secondary" data-action="close-admin-modal">Cancel</button>
        <button type="button" class="btn btn-primary" data-action="business-backup-confirm">Create Backup</button>
      </div>
    </div>
  </div>
  <div id="shoppingCartModal" class="admin-modal-overlay wf-modal--content-scroll wf-modal-closable hidden z-10110" aria-hidden="true" role="dialog" aria-modal="true" tabindex="-1" aria-labelledby="shoppingCartSettingsTitle">
    <div class="admin-modal admin-modal-content admin-modal--actions-in-header">
      <div class="modal-header">
        <h2 id="shoppingCartSettingsTitle" class="admin-card-title">🛒 Shopping Cart Settings</h2>
        <div class="modal-header-actions">
          <span id="shoppingCartStatus" class="text-sm text-gray-600" aria-live="polite"></span>
          <button type="button" class="btn btn-primary btn-sm" id="saveCartSettingsBtn">Save</button>
        </div>
        <button type="button" class="admin-modal-close wf-admin-nav-button" data-action="close-admin-modal" aria-label="Close">×</button>
      </div>
      <div class="modal-body">
        <form id="shoppingCartSettingsForm" data-action="prevent-submit" class="space-y-4">
          <div class="grid gap-3 md:grid-cols-2" id="cartSettingsTop">
            <div class="rounded border p-3">
            <h3 class="text-sm font-semibold mb-2">Cart behavior</h3>
            <div class="form-control">
            <label class="flex items-center gap-2">
              <input type="checkbox" id="openCartOnAddCheckbox" class="form-checkbox" value="1" <?php echo ($openOnAdd === '1' || $openOnAdd === 'true') ? 'checked' : ''; ?> />
              <span>Open cart after adding an item</span>
            </label>
            <p class="text-sm text-gray-600 mt-1">If enabled, the cart modal opens automatically after an item is added to the cart.</p>
            </div>
            </div>
            <div class="rounded border p-3">
            <h3 class="text-sm font-semibold mb-2">Line items</h3>
            <div class="form-control">
            <label class="flex items-center gap-2">
              <input type="checkbox" id="mergeDuplicatesCheckbox" class="form-checkbox" value="1" <?php echo ($mergeDupes === '1' || $mergeDupes === 'true') ? 'checked' : ''; ?> />
              <span>Merge duplicate items into a single line</span>
            </label>
            <p class="text-sm text-gray-600 mt-1">When enabled, adding the same SKU increases quantity instead of creating a new line.</p>
            </div>
            </div>
            <div class="rounded border p-3">
            <h3 class="text-sm font-semibold mb-2">Upsells in cart</h3>
            <div class="form-control">
            <label class="flex items-center gap-2">
              <input type="checkbox" id="showUpsellsCheckbox" class="form-checkbox" value="1" <?php echo ($showUpsells === '1' || $showUpsells === 'true') ? 'checked' : ''; ?> />
              <span>Show upsell recommendations in cart</span>
            </label>
            <p class="text-sm text-gray-600 mt-1">Display related items or accessories below the cart items list.</p>
            </div>
            </div>
            <div class="rounded border p-3">
            <h3 class="text-sm font-semibold mb-2">Safety</h3>
            <div class="form-control">
            <label class="flex items-center gap-2">
              <input type="checkbox" id="confirmClearCheckbox" class="form-checkbox" value="1" <?php echo ($confirmClear === '1' || $confirmClear === 'true') ? 'checked' : ''; ?> />
              <span>Confirm before clearing the cart</span>
            </label>
            <p class="text-sm text-gray-600 mt-1">Prevent accidental clears by requiring a confirmation dialog.</p>
            </div>
            </div>
            <div class="rounded border p-3 md:col-span-2">
            <h3 class="text-sm font-semibold mb-2">Checkout requirement</h3>
            <div class="form-control">
            <label class="block text-sm font-medium mb-1" for="minimumTotalInput">Minimum order total required to checkout ($)</label>
            <input id="minimumTotalInput" type="number" step="0.01" min="0" class="form-input w-full" value="<?php echo htmlspecialchars($minTotal, ENT_QUOTES); ?>" />
            <p class="text-sm text-gray-600 mt-1">Set to 0 to disable minimum total enforcement.</p>
            </div>
            </div>
          </div>
          
          <div class="rounded border p-3 bg-white/70 backdrop-blur-sm" id="cartSimulationBox">
            <div class="flex items-start justify-between gap-3">
              <div>
                <h3 class="text-sm font-semibold mb-1">Live simulation</h3>
                <p class="text-sm text-gray-600">Generate a fictitious shopper and preview upsell recommendations with rationale. Results are saved to the database for auditing.</p>
                <p class="text-sm text-gray-600 mt-2">Upsell engine (auto-generated)</p>
                <p class="text-sm text-gray-600">These recommendations are created automatically from sales performance and cart context using your live database.</p>
              </div>
              <div class="shrink-0 flex items-center gap-2">
                <button type="button" class="btn btn-secondary btn-sm" id="cartSimulationRefreshBtn" data-action="refresh-cart-simulation">Refresh recommendations</button>
                <button type="button" class="btn btn-link btn-sm" data-action="load-cart-sim-history">History</button>
                <button type="button" class="btn btn-secondary btn-sm" data-action="open-intent-heuristics" title="Adjust intent scoring weights and keywords">Tune intent heuristics</button>
              </div>
            </div>
            <div class="mt-3 text-sm" id="cartSimulationStatus" aria-live="polite"></div>
            <div class="mt-3 grid gap-2 md:grid-cols-5" id="cartSimulationControls">
              <div>
                <label for="cartSimPrefCategory" class="block text-xs text-gray-600 mb-1">Preferred category</label>
                <select id="cartSimPrefCategory" class="form-select w-full text-sm"><option value="">Auto</option></select>
              </div>
              <div>
                <label for="cartSimBudget" class="block text-xs text-gray-600 mb-1">Budget</label>
                <select id="cartSimBudget" class="form-select w-full text-sm">
                  <option value="">Auto</option>
                  <option value="low">Low</option>
                  <option value="mid">Mid</option>
                  <option value="high">High</option>
                </select>
              </div>
              <div>
                <label for="cartSimIntent" class="block text-xs text-gray-600 mb-1">Intent</label>
                <select id="cartSimIntent" class="form-select w-full text-sm">
                  <option value="">Auto</option>
                  <option value="gift">Gift</option>
                  <option value="personal">Personal</option>
                  <option value="replacement">Replacement</option>
                  <option value="upgrade">Upgrade</option>
                  <option value="diy-project">DIY Project</option>
                  <option value="home-decor">Home Decor</option>
                  <option value="holiday">Holiday</option>
                  <option value="birthday">Birthday</option>
                  <option value="anniversary">Anniversary</option>
                  <option value="wedding">Wedding</option>
                  <option value="teacher-gift">Teacher Gift</option>
                  <option value="office-decor">Office Decor</option>
                  <option value="event-supplies">Event Supplies</option>
                  <option value="workshop-class">Workshop/Class</option>
                </select>
              </div>
              <div>
                <label for="cartSimDevice" class="block text-xs text-gray-600 mb-1">Device</label>
                <select id="cartSimDevice" class="form-select w-full text-sm">
                  <option value="">Auto</option>
                  <option value="mobile">Mobile</option>
                  <option value="desktop">Desktop</option>
                </select>
              </div>
              <div>
                <label for="cartSimRegion" class="block text-xs text-gray-600 mb-1">Region</label>
                <select id="cartSimRegion" class="form-select w-full text-sm">
                  <option value="">Auto</option>
                  <option value="US">US</option>
                </select>
              </div>
            </div>
            <div class="mt-3 flex items-start gap-3" id="cartSimulationPanels">
              <div class="rounded border p-1 bg-gray-50 w-40 md:w-48 shrink-0">
                <div class="text-xs font-semibold uppercase tracking-wide text-gray-500 mb-2">Shopper profile</div>
                <div id="cartSimulationProfile" class="text-xs text-gray-800">Click Refresh to generate a profile…</div>
                <div class="text-xs text-gray-500 mt-2" id="cartSimulationSeed">Seed cart: <span id="cartSimulationSeedSkus">—</span></div>
              </div>
              <div class="rounded border p-3 bg-white/60 backdrop-blur-sm flex-1 min-w-0">
                <div class="text-xs font-semibold uppercase tracking-wide text-gray-500 mb-2">Recommended items</div>
                <div id="cartSimulationRecs" class="grid grid-cols-1 sm:grid-cols-2 gap-2"></div>
              </div>
            </div>
            <div class="mt-3 hidden" id="cartSimulationHistoryBox">
              <div class="text-xs font-semibold uppercase tracking-wide text-gray-500 mb-2">History</div>
              <div id="cartSimulationHistory" class="grid gap-2"></div>
            </div>
          </div>

        </form>
      </div>
    </div>
  </div>
  <!-- Size/Color Redesign Tool Modal (inline) -->
  <div id="sizeColorRedesignModal" class="admin-modal-overlay wf-overlay-viewport over-header topmost wf-modal-autowide wf-modal-mincols-2 wf-modal-single-scroll wf-modal-closable hidden" aria-hidden="true" role="dialog" aria-modal="true" tabindex="-1" aria-labelledby="sizeColorRedesignTitle">
    <div class="admin-modal admin-modal-content admin-modal--lg admin-modal--actions-in-header admin-modal--responsive">
      <div class="modal-header">
        <h2 id="sizeColorRedesignTitle" class="admin-card-title">🧩 Size/Color System Redesign</h2>
        <button type="button" class="admin-modal-close wf-admin-nav-button" data-action="close-admin-modal" aria-label="Close">×</button>
      </div>
      <div class="modal-body wf-modal-body--autoheight">
        <div id="sizeColorRedesignContainer" class="p-0"></div>
      </div>
    </div>
  </div>

    

    

    <!-- Auto-size all settings modals to content -->
    

    <!-- Auto-resize same-origin iframes inside modals (e.g., Categories) -->
  <div id="categoriesModal" class="admin-modal-overlay wf-overlay-viewport over-header topmost wf-modal-autowide wf-modal-single-scroll wf-modal-closable hidden" aria-hidden="true" role="dialog" aria-modal="true" tabindex="-1" aria-labelledby="categoriesTitle">
    <div class="admin-modal admin-modal-content admin-modal--xl admin-modal--actions-in-header admin-modal--responsive">
      <div class="modal-header">
        <h2 id="categoriesTitle" class="admin-card-title">Categories</h2>
        <button type="button" class="admin-modal-close wf-admin-nav-button" data-action="close-admin-modal" aria-label="Close">×</button>
      </div>
      <div class="modal-body wf-modal-body--autoheight">
        <iframe id="categoriesFrame" title="Categories Manager" class="wf-admin-embed-frame" data-autosize="1" data-allow-settings-autosize="1" data-trust-refined="1" data-reload-on-open="1" data-measure-selector="#categoryManagementRoot,.admin-card,.admin-table" data-src="/sections/admin_categories.php?modal=1" src="/sections/admin_categories.php?modal=1" referrerpolicy="no-referrer" scrolling="no"></iframe>
      </div>
    </div>
  </div>
  

    

  <!-- Customer Messages Modal: Shop Encouragement Phrases -->
  <div id="customerMessagesModal" class="admin-modal-overlay wf-overlay-viewport over-header topmost wf-modal-closable hidden" aria-hidden="true" role="dialog" aria-modal="true" tabindex="-1" aria-labelledby="customerMessagesTitle">
    <div class="admin-modal admin-modal-content admin-modal--xl admin-modal--actions-in-header">
        <div class="modal-header">
          <h2 id="customerMessagesTitle" class="admin-card-title">💬 Marketing &amp; Engagement Hub</h2>
          <button type="button" class="admin-modal-close wf-admin-nav-button" data-action="close-admin-modal" aria-label="Close">×</button>
        </div>
      <div class="modal-body admin-modal-body--xl">
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-4">
          <div class="rounded border p-3 text-center">
            <div class="text-2xl font-bold">53</div>
            <div class="text-xs text-gray-600">AI Suggestions</div>
          </div>
          <div class="rounded border p-3 text-center">
            <div class="text-2xl font-bold">3</div>
            <div class="text-xs text-gray-600">Campaigns</div>
          </div>
          <div class="rounded border p-3 text-center">
            <div class="text-2xl font-bold">2.4%</div>
            <div class="text-xs text-gray-600">Conversion</div>
          </div>
          <div class="rounded border p-3 text-center">
            <div class="text-2xl font-bold">15</div>
            <div class="text-xs text-gray-600">Emails Sent</div>
          </div>
        </div>
        <div class="wf-grid-md-3 mb-3">
          <div>
            <button type="button" class="btn btn-secondary btn-sm" data-action="open-receipt-messages">Receipt Messages</button>
            <p class="text-sm text-gray-600 mt-1">Set the messages shown on printed and emailed receipts (thank-you notes, policies, contact info).</p>
          </div>
          <div>
            <button type="button" class="btn btn-secondary btn-sm" data-action="open-template-manager">Email Templates</button>
            <p class="text-sm text-gray-600 mt-1">Edit the content and layout for system emails like order confirmations and password resets.</p>
          </div>
          <div>
            <button type="button" class="btn btn-secondary btn-sm" data-action="open-cart-button-texts">Cart Button Texts</button>
            <p class="text-sm text-gray-600 mt-1">Customize labels such as Add to Cart, View Cart, Checkout, and Continue Shopping.</p>
          </div>
          <div>
            <button type="button" class="btn btn-secondary btn-sm" data-action="open-shop-encouragements">Shop Encouragement Phrases</button>
            <p class="text-sm text-gray-600 mt-1">Manage short phrases that motivate shoppers across the site (e.g., specials, free shipping notes).</p>
          </div>
          <div>
            <button type="button" class="btn btn-secondary btn-sm" data-action="open-social-posts">Social Media Posts</button>
            <p class="text-sm text-gray-600 mt-1">Create and organize social posts to share items and promotions on connected accounts.</p>
          </div>
          <div>
            <button type="button" class="btn btn-secondary btn-sm" data-action="open-social-media-manager">Social Media</button>
            <p class="text-sm text-gray-600 mt-1">Connect social accounts, schedule posts, and review engagement.</p>
          </div>
          <div>
            <button type="button" class="btn btn-secondary btn-sm" data-action="open-newsletters">Newsletters</button>
            <p class="text-sm text-gray-600 mt-1">Create, schedule, and review newsletters.</p>
          </div>
          <div>
            <button type="button" class="btn btn-secondary btn-sm" data-action="open-discounts">Discount Codes</button>
            <p class="text-sm text-gray-600 mt-1">Generate and manage discount codes.</p>
          </div>
          <div>
            <button type="button" class="btn btn-secondary btn-sm" data-action="open-coupons">Coupons</button>
            <p class="text-sm text-gray-600 mt-1">Create printable or digital coupons.</p>
          </div>
        </div>
        <!-- Encouragement phrases moved to dedicated modal (see: Shop Encouragement Phrases button above) -->
      </div>
    </div>
  </div>


  

  <!-- Fallback handlers: ensure Visual & Design tool modals open even if JS entry lags -->
  

    


    <!-- Dev Status Dashboard Modal (inline) -->
  <div id="devStatusModal" class="admin-modal-overlay wf-overlay-viewport over-header topmost wf-modal-closable hidden" aria-hidden="true" role="dialog" aria-modal="true" tabindex="-1" aria-labelledby="devStatusTitle">
      <div class="admin-modal admin-modal-content admin-modal--lg admin-modal--actions-in-header">
        <div class="modal-header">
          <h2 id="devStatusTitle" class="admin-card-title">🧪 Dev Status Dashboard</h2>
          <button type="button" class="admin-modal-close wf-admin-nav-button" data-action="close-admin-modal" aria-label="Close">×</button>
        </div>
        <div class="modal-body admin-modal-body--lg">
          <div id="devStatusContainer" class="rounded border p-2 bg-white text-sm overflow-auto"></div>
        </div>
      </div>
    </div>

    

    <!-- Attributes Management Modal (inline) -->
    <div id="attributesModal" class="admin-modal-overlay wf-overlay-viewport over-header topmost wf-modal-autowide wf-modal-mincols-3 wf-modal-single-scroll wf-modal-closable hidden" aria-hidden="true" role="dialog" aria-modal="true" tabindex="-1" aria-labelledby="attributesTitle">
      <div class="admin-modal admin-modal-content admin-modal--attributes admin-modal--actions-in-header admin-modal--responsive">
        <div class="modal-header">
          <h2 id="attributesTitle" class="admin-card-title">Genders, Sizes, &amp; Colors</h2>
          <button type="button" class="admin-modal-close wf-admin-nav-button" data-action="close-admin-modal" aria-label="Close">×</button>
        </div>
        <div class="modal-body wf-modal-body--autoheight">
          <div id="attributesInlineContainer" class="p-0"></div>
        </div>
      </div>
    </div>

  <!-- Reports & Documentation Browser Modal (inline) -->
  <div id="reportsBrowserModal" class="admin-modal-overlay wf-overlay-viewport over-header topmost wf-modal-closable hidden" aria-hidden="true" role="dialog" aria-modal="true" tabindex="-1" aria-labelledby="reportsBrowserTitle">
    <div class="admin-modal admin-modal-content admin-modal--lg admin-modal--actions-in-header">
      <div class="modal-header">
        <h2 id="reportsBrowserTitle" class="admin-card-title">Reports &amp; Documentation</h2>
        <button type="button" class="admin-modal-close wf-admin-nav-button" data-action="close-admin-modal" aria-label="Close">×</button>
      </div>
      <div class="modal-body admin-modal-body--lg">
        <div id="reportsBrowserContainer" class="rounded border p-2 bg-white text-sm overflow-auto"></div>
      </div>
    </div>
  </div>
 
                  <div id="adminSettingsRoot" class="admin-settings-root">
    <!-- Settings cards grid using legacy classes -->
    <div class="settings-grid">
      <?php // Content Management ?>
      <?php ob_start(); ?>
        <button type="button" id="categoriesBtn" class="admin-settings-button btn-primary btn-full-width" data-action="open-categories">Categories</button>
        <button type="button" id="dashboardConfigBtn" class="admin-settings-button btn-primary btn-full-width" data-action="open-dashboard-config">Dashboard Configuration</button>
        <button type="button" id="attributesBtn" class="admin-settings-button btn-primary btn-full-width" data-action="open-attributes">Genders, Sizes, &amp; Colors</button>
        <button type="button" id="shoppingCartBtn" class="admin-settings-button btn-primary btn-full-width" data-action="open-shopping-cart">Shopping Cart</button>
      <?php $__content = ob_get_clean(); echo wf_render_settings_card('card-theme-blue', 'Content Management', 'Organize items, categories, and room content', $__content); ?>

      <?php // Visual & Design ?>
      <?php ob_start(); ?>
        <button type="button" class="admin-settings-button btn-primary btn-full-width" data-action="open-area-item-mapper">Area Mappings</button>
        <button type="button" class="admin-settings-button btn-primary btn-full-width" data-action="open-background-manager">Backgrounds</button>
        <button type="button" id="actionIconsManagerBtn" class="admin-settings-button btn-primary btn-full-width" data-action="open-action-icons-manager" title="Open Buttons">Buttons</button>
        <button type="button" class="admin-settings-button btn-primary btn-full-width" data-action="open-colors-fonts">Colors &amp; Fonts</button>
        <button type="button" class="admin-settings-button btn-primary btn-full-width" data-action="open-room-map-manager">Room Map</button>
      <?php $__content = ob_get_clean(); echo wf_render_settings_card('card-theme-purple', 'Visual & Design', 'Customize appearance and interactive elements', $__content); ?>

      <?php // Business & Analytics ?>
      <?php ob_start(); ?>
        <button type="button" id="aiUnifiedBtn" class="admin-settings-button btn-primary btn-full-width" data-action="open-ai-unified">Artificial Intelligence</button>
        <button type="button" id="businessInfoBtn" class="admin-settings-button btn-primary btn-full-width" data-action="open-business-info">Business Information</button>
        <button type="button" id="squareSettingsBtn" class="admin-settings-button btn-primary btn-full-width" data-action="open-square-settings">Configure Square</button>
        <button type="button" class="admin-settings-button btn-primary btn-full-width" data-action="open-cost-breakdown">Cost Breakdown</button>
        <button type="button" id="shippingSettingsBtn" class="admin-settings-button btn-primary btn-full-width" data-action="open-shipping-settings">Shipping &amp; Distance Settings</button>
      <?php $__content = ob_get_clean(); echo wf_render_settings_card('card-theme-emerald', 'Business & Analytics', 'Manage sales, promotions, and business insights', $__content); ?>

      <?php // Communication ?>
      <?php ob_start(); ?>
        <button type="button" id="customerMessagesBtn" class="admin-settings-button btn-primary btn-full-width" data-action="open-customer-messages">Customer Messages</button>
        <button type="button" id="emailConfigBtn" class="admin-settings-button btn-primary btn-full-width" data-action="open-email-settings">Email Configuration</button>
        <button type="button" id="emailHistoryBtn" class="admin-settings-button btn-primary btn-full-width" data-action="open-email-history">Email History</button>
      <?php $__content = ob_get_clean(); echo wf_render_settings_card('card-theme-orange', 'Communication', 'Email configuration and customer messaging', $__content); ?>

      <?php // Technical & System ?>
      <?php ob_start(); ?>
        <button type="button" id="healthDiagnosticsBtn" class="admin-settings-button btn-primary btn-full-width" data-action="open-health-diagnostics">Health &amp; Diagnostics</button>
        <button type="button" id="loggingStatusBtn" class="admin-settings-button btn-primary btn-full-width" data-action="open-logging-status">Log Viewer</button>
        <button type="button" id="reportsBrowserBtn" class="admin-settings-button btn-primary btn-full-width" data-action="open-reports-browser">Reports &amp; Documentation</button>
        
        <button type="button" id="secretsManagerBtn" class="admin-settings-button btn-primary btn-full-width" data-action="open-secrets-modal">Secrets</button>
      <?php $__content = ob_get_clean(); echo wf_render_settings_card('card-theme-red', 'Technical & System', 'System tools and advanced configuration', $__content); ?>
    </div>

    <!-- Health & Diagnostics Modal (hidden by default) -->
    <div id="healthModal" class="admin-modal-overlay wf-overlay-viewport over-header topmost wf-modal-closable hidden" aria-hidden="true" role="dialog" aria-modal="true" tabindex="-1" aria-labelledby="healthTitle">
      <div class="admin-modal admin-modal-content admin-modal--actions-in-header">
        <div class="modal-header">
          <h2 id="healthTitle" class="admin-card-title">🩺 Health &amp; Diagnostics</h2>
          <button type="button" class="admin-modal-close wf-admin-nav-button" data-action="close-admin-modal" aria-label="Close">×</button>
          <span class="modal-status-chip" aria-live="polite"></span>
        </div>
        <div class="modal-body">
          <div class="flex items-center justify-between mb-3">
            <div id="healthStatus" class="text-sm text-gray-600">Loading…</div>
            <div class="flex items-center gap-2">
              <button type="button" class="btn btn-secondary wf-admin-nav-button" data-action="health-refresh">Refresh</button>
              <a class="btn wf-admin-nav-button" href="/sections/admin_router.php?section=dashboard#background">Backgrounds</a>
              <a class="btn wf-admin-nav-button" href="/sections/admin_router.php?section=inventory">Inventory</a>
            </div>
          </div>
          <div class="grid gap-4 md:grid-cols-2">
            <div class="border rounded p-3">
              <div class="font-semibold mb-2">Backgrounds</div>
              <div class="text-sm text-gray-600 mb-2">Active configuration per room (0 = landing)</div>
              <div class="text-sm mb-1">Missing Active: <span id="bgMissingActiveCount">0</span></div>
              <ul id="bgMissingActiveList" class="list-disc ml-4 text-sm"></ul>
              <div class="text-sm mt-3 mb-1">Missing Files: <span id="bgMissingFilesCount">0</span></div>
              <ul id="bgMissingFilesList" class="list-disc ml-4 text-sm"></ul>
            </div>
            <div class="border rounded p-3">
              <div class="font-semibold mb-2">Items</div>
              <div class="text-sm mb-1">No Primary Image: <span id="itemsNoPrimaryCount">0</span></div>
              <ul id="itemsNoPrimaryList" class="list-disc ml-4 text-sm max-h-56 overflow-auto"></ul>
              <div class="text-sm mt-3 mb-1">Missing Image Files: <span id="itemsMissingFilesCount">0</span></div>
              <ul id="itemsMissingFilesList" class="list-disc ml-4 text-sm max-h-56 overflow-auto"></ul>
            </div>
          </div>

          <div class="border rounded p-3 mt-4">
            <div class="font-semibold mb-2">Advanced Diagnostics</div>
            <div class="text-sm text-gray-600 mb-2">Extra checks and dashboards for development and troubleshooting.</div>
            <div class="flex flex-wrap gap-2 mb-3">
              <button type="button" class="btn" data-action="open-dev-status">Open Dev Status Dashboard</button>
              <button type="button" class="btn btn-secondary" data-action="run-health-check">Run /health.php</button>
              <button type="button" class="btn btn-secondary" data-action="scan-item-images">Scan Item Images</button>
            </div>
            <pre id="advancedHealthOutput" class="text-xs bg-gray-50 p-2 rounded border max-h-64 overflow-auto" aria-live="polite"></pre>
          </div>
        </div>
      </div>
    </div>

    <!-- Area-Item Mapper Modal (inline) -->
    <div id="areaItemMapperModal" class="admin-modal-overlay wf-overlay-viewport over-header topmost wf-modal-autowide wf-modal-viewport-fill wf-modal-mincols-3 wf-modal-closable hidden" aria-hidden="true" role="dialog" aria-modal="true" tabindex="-1" aria-labelledby="areaItemMapperTitle">
      <div class="admin-modal admin-modal-content admin-modal--actions-in-header">
        <div class="modal-header">
          <h2 id="areaItemMapperTitle" class="admin-card-title">🧭 Area Mappings</h2>
          <div class="modal-header-actions">
            <span id="areaItemMapperStatus" class="text-sm text-gray-600" aria-live="polite"></span>
            <button type="button" id="areaItemMapperSave" class="btn btn-primary btn-sm">Save</button>
          </div>
          <button type="button" class="admin-modal-close wf-admin-nav-button" data-action="close-admin-modal" aria-label="Close">×</button>
        </div>
        <div class="modal-body">
          <div id="areaItemMapperContainer" class="wf-w-full wf-max-w-none"></div>
        </div>
      </div>
    </div>
    <script>
    (function(){
      try {
        var overlay = document.getElementById('areaItemMapperModal');
        var saveBtn = document.getElementById('areaItemMapperSave');
        var statusEl = document.getElementById('areaItemMapperStatus');
        function loadAreaItemMapperInline(){
          try {
            var mount = document.getElementById('areaItemMapperContainer');
            if (!mount) return;
            if (mount.__wfLoaded) return;
            mount.__wfLoaded = true;
            mount.innerHTML = '<div class="text-sm text-gray-600 p-2">Loading…</div>';
            var url = '/sections/tools/area_item_mapper.php?modal=1';
            ApiClient.get(url).then(function(html){
              try {
                var parser = new DOMParser();
                var doc = parser.parseFromString(String(html||''), 'text/html');
                var styles = Array.from(doc.querySelectorAll('style')).map(function(s){ return s.textContent || ''; }).join('\n');
                var section = doc.querySelector('#admin-section-content');
                var bodyHtml = section ? section.innerHTML : (doc.body ? doc.body.innerHTML : '');
                // Scope styles to container
                var scoped = styles;
                try {
                  scoped = styles.split('}').map(function(rule){
                    var parts = rule.split('{');
                    if (parts.length < 2) return '';
                    var sel = parts[0].trim();
                    var decl = parts.slice(1).join('{');
                    if (!sel) return '';
                    if (sel.startsWith('@')) return rule + '}';
                    var prefixed = sel.split(',').map(function(s){ return '#areaItemMapperContainer ' + s.trim(); }).join(', ');
                    return prefixed + ' {' + decl + '}';
                  }).filter(Boolean).join('}\n');
                } catch(_) {}
                mount.innerHTML = '';
                var styleEl = document.createElement('style'); styleEl.textContent = scoped; mount.appendChild(styleEl);
                var content = document.createElement('div'); content.className = 'aim-inline-root'; content.innerHTML = bodyHtml; mount.appendChild(content);
                // Execute scripts (module and inline)
                var scripts = Array.from(doc.querySelectorAll('script'));
                scripts.forEach(function(srcEl){
                  var s = document.createElement('script');
                  if (srcEl.type) s.type = srcEl.type;
                  if (srcEl.src) { s.src = srcEl.src; } else { s.textContent = srcEl.textContent || ''; }
                  document.body.appendChild(s);
                });
              } catch(err) { mount.innerHTML = '<div class="text-sm text-red-700 p-2">Failed to load Area-Item Mapper</div>'; }
            }).catch(function(){ mount.innerHTML = '<div class="text-sm text-red-700 p-2">Failed to load Area-Item Mapper</div>'; });
          } catch(_){}
        }
        // Expose for click mapping
        try { window.__wfLoadAIMInline = loadAreaItemMapperInline; } catch(_){}

        if (saveBtn && !saveBtn.__wfBound) {
          saveBtn.__wfBound = true;
          saveBtn.addEventListener('click', function(e){
            e.preventDefault();
            try { saveBtn.disabled = true; saveBtn.dataset.prevLabel = saveBtn.textContent || ''; saveBtn.textContent = 'Saving…'; } catch(_){ }
            try { window.postMessage({ source:'wf-aim-parent', type:'save' }, '*'); } catch(_){ }
          }, true);
        }
        if (!window.__wfAIMStaticListener) {
          window.addEventListener('message', function(ev){
            try {
              var d = ev && ev.data; if (!d || d.source !== 'wf-aim') return;
              if (d.type === 'status') {
                if (statusEl) { statusEl.textContent = d.message || ''; statusEl.classList.remove('text-red-700','text-green-700'); statusEl.classList.add(d.ok ? 'text-green-700' : 'text-red-700'); }
                if (saveBtn) { try { saveBtn.disabled = false; saveBtn.textContent = saveBtn.dataset.prevLabel || 'Save'; delete saveBtn.dataset.prevLabel; } catch(_){ } }
              }
            } catch(_){ }
          });
          window.__wfAIMStaticListener = true;
        }
      } catch(_){ }
    })();
    </script>
    



    <!-- AI Tools Proxies (each deep-links into marketing sub-modals) -->
    <div id="marketingSuggestionsProxyModal" class="admin-modal-overlay over-header wf-modal-viewport-fill wf-modal-closable hidden" aria-hidden="true" role="dialog" aria-modal="true" tabindex="-1" aria-labelledby="marketingSuggestionsProxyTitle">
      <div class="admin-modal admin-modal-content admin-modal--actions-in-header">
        <div class="modal-header">
          <h2 id="marketingSuggestionsProxyTitle" class="admin-card-title">🤖 AI Item Suggestions</h2>
          <button type="button" class="admin-modal-close wf-admin-nav-button" data-action="close-admin-modal" aria-label="Close">×</button>
        </div>
        <div class="modal-body">
          <iframe id="marketingSuggestionsProxyFrame" title="AI Item Suggestions" src="about:blank" data-src="/sections/tools/ai_suggestions.php?modal=1" class="wf-admin-embed-frame" referrerpolicy="no-referrer"></iframe>
        </div>
      </div>
    </div>

    <div id="contentGeneratorProxyModal" class="admin-modal-overlay over-header wf-modal-viewport-fill wf-modal-closable hidden" aria-hidden="true" role="dialog" aria-modal="true" tabindex="-1" aria-labelledby="contentGeneratorProxyTitle">
      <div class="admin-modal admin-modal-content admin-modal--actions-in-header">
        <div class="modal-header">
          <h2 id="contentGeneratorProxyTitle" class="admin-card-title">✍️ AI Content Generator</h2>
          <button type="button" class="admin-modal-close wf-admin-nav-button" data-action="close-admin-modal" aria-label="Close">×</button>
        </div>
        <div class="modal-body">
          <iframe id="contentGeneratorProxyFrame" title="AI Content Generator" src="about:blank" data-src="/sections/tools/ai_content_generator.php?modal=1" class="wf-admin-embed-frame" referrerpolicy="no-referrer"></iframe>
        </div>
      </div>
    </div>

    <div id="newslettersProxyModal" class="admin-modal-overlay over-header wf-modal-closable hidden" aria-hidden="true" role="dialog" aria-modal="true" tabindex="-1" aria-labelledby="newslettersProxyTitle">
      <div class="admin-modal admin-modal-content admin-modal--lg admin-modal--actions-in-header">
        <div class="modal-header">
          <h2 id="newslettersProxyTitle" class="admin-card-title">📧 Newsletters</h2>
          <button type="button" class="admin-modal-close wf-admin-nav-button" data-action="close-admin-modal" aria-label="Close">×</button>
        </div>
        <div class="modal-body admin-modal-body--lg">
          <iframe id="newslettersProxyFrame" title="Newsletters" src="about:blank" data-src="/sections/tools/newsletters_manager.php?modal=1" class="wf-admin-embed-frame wf-admin-embed-frame--tall" referrerpolicy="no-referrer"></iframe>
        </div>
      </div>
    </div>


    <div id="discountsProxyModal" class="admin-modal-overlay over-header wf-modal-closable hidden" aria-hidden="true" role="dialog" aria-modal="true" tabindex="-1" aria-labelledby="discountsProxyTitle">
      <div class="admin-modal admin-modal-content admin-modal--lg admin-modal--actions-in-header">
        <div class="modal-header">
          <h2 id="discountsProxyTitle" class="admin-card-title">💸 Discount Codes</h2>
          <button type="button" class="admin-modal-close wf-admin-nav-button" data-action="close-admin-modal" aria-label="Close">×</button>
        </div>
        <div class="modal-body admin-modal-body--lg">
          <iframe id="discountsProxyFrame" title="Discount Codes" src="about:blank" data-src="/sections/tools/discounts_manager.php?modal=1" class="wf-admin-embed-frame wf-admin-embed-frame--tall" referrerpolicy="no-referrer"></iframe>
        </div>
      </div>
    </div>

    <div id="couponsProxyModal" class="admin-modal-overlay over-header wf-modal-closable hidden" aria-hidden="true" role="dialog" aria-modal="true" tabindex="-1" aria-labelledby="couponsProxyTitle">
      <div class="admin-modal admin-modal-content admin-modal--lg admin-modal--actions-in-header">
        <div class="modal-header">
          <h2 id="couponsProxyTitle" class="admin-card-title">🎟️ Coupons</h2>
          <button type="button" class="admin-modal-close wf-admin-nav-button" data-action="close-admin-modal" aria-label="Close">×</button>
        </div>
        <div class="modal-body admin-modal-body--lg">
          <iframe id="couponsProxyFrame" title="Coupons" src="about:blank" data-src="/sections/tools/coupons_manager.php?modal=1" class="wf-admin-embed-frame wf-admin-embed-frame--tall" referrerpolicy="no-referrer"></iframe>
        </div>
      </div>
    </div>

    <!-- Social Media Manager Modal (iframe embed, deep-link to social section) -->
    <div id="socialMediaManagerModal" class="admin-modal-overlay over-header wf-modal-closable hidden" aria-hidden="true" role="dialog" aria-modal="true" tabindex="-1" aria-labelledby="socialMediaManagerTitle">
      <div class="admin-modal admin-modal-content admin-modal--lg admin-modal--actions-in-header">
        <div class="modal-header">
          <h2 id="socialMediaManagerTitle" class="admin-card-title">📱 Social Media Manager</h2>
          <button type="button" class="admin-modal-close wf-admin-nav-button" data-action="close-admin-modal" aria-label="Close">×</button>
        </div>
        <div class="modal-body admin-modal-body--lg">
          <iframe id="socialMediaManagerFrame" title="Social Media Manager" src="about:blank" data-src="/sections/tools/social_manager.php?modal=1" class="wf-admin-embed-frame wf-admin-embed-frame--tall" referrerpolicy="no-referrer"></iframe>
        </div>
      </div>
    </div>

    <!-- Template Manager Modal (iframe embed) -->
    <div id="templateManagerModal" class="admin-modal-overlay wf-overlay-viewport over-header topmost hidden" aria-hidden="true" role="dialog" aria-modal="true" tabindex="-1" aria-labelledby="templateManagerTitle">
      <div class="admin-modal admin-modal-content admin-modal--actions-in-header">
        <div class="modal-header">
          <h2 id="templateManagerTitle" class="admin-card-title">📁 Template Manager</h2>
          <button type="button" class="admin-modal-close wf-admin-nav-button" data-action="close-admin-modal" aria-label="Close">×</button>
          <span class="modal-status-chip" aria-live="polite"></span>
        </div>
        <div class="modal-body">
          <iframe id="templateManagerFrame" title="Template Manager" src="about:blank" data-src="/sections/tools/template_manager.php?modal=1" class="wf-admin-embed-frame"></iframe>
        </div>
      </div>
    </div>

    <!-- Social Media Posts Templates Modal (iframe embed) -->
    <div id="socialPostsManagerModal" class="admin-modal-overlay wf-overlay-viewport over-header topmost hidden" aria-hidden="true" role="dialog" aria-modal="true" tabindex="-1" aria-labelledby="socialPostsManagerTitle">
      <div class="admin-modal admin-modal-content admin-modal--actions-in-header">
        <div class="modal-header">
          <h2 id="socialPostsManagerTitle" class="admin-card-title">📝 Social Media Posts</h2>
          <button type="button" class="admin-modal-close wf-admin-nav-button" data-action="close-admin-modal" aria-label="Close">×</button>
          <span class="modal-status-chip" aria-live="polite"></span>
        </div>
        <div class="modal-body">
          <iframe id="socialPostsManagerFrame" title="Social Media Posts" src="about:blank" data-src="/sections/tools/social_posts_manager.php?modal=1" class="wf-admin-embed-frame"></iframe>
        </div>
      </div>
    </div>

    <!-- Cost Breakdown Manager Modal (iframe embed) -->
    <div id="costBreakdownModal" class="admin-modal-overlay wf-overlay-viewport over-header topmost hidden" aria-hidden="true" role="dialog" aria-modal="true" tabindex="-1" aria-labelledby="costBreakdownTitle">
      <div class="admin-modal admin-modal-content admin-modal--actions-in-header">
        <div class="modal-header">
          <h2 id="costBreakdownTitle" class="admin-card-title">💲 Cost Breakdown Manager</h2>
          <button type="button" class="admin-modal-close wf-admin-nav-button" data-action="close-admin-modal" aria-label="Close">×</button>
          <span class="modal-status-chip" aria-live="polite"></span>
        </div>
        <div class="modal-body">
          <iframe id="costBreakdownFrame" title="Cost Breakdown Manager" src="about:blank" data-src="/sections/tools/cost_breakdown_manager.php?modal=1" class="wf-admin-embed-frame" data-autosize="1" referrerpolicy="no-referrer"></iframe>
        </div>
      </div>
    </div>

    <!-- Font Picker Modal -->
    <div id="fontPickerModal" class="admin-modal-overlay wf-overlay-viewport over-header topmost hidden" aria-hidden="true" role="dialog" aria-modal="true" tabindex="-1" aria-labelledby="fontPickerTitle">
      <div class="admin-modal admin-modal-content max-w-4xl admin-modal--actions-in-header">
        <div class="modal-header">
          <h2 id="fontPickerTitle" class="admin-card-title">Font Library</h2>
          <button type="button" class="admin-modal-close wf-admin-nav-button" data-action="close-font-picker" aria-label="Close">×</button>
          <span class="modal-status-chip" aria-live="polite"></span>
        </div>
        <div class="modal-body">
          <div class="mb-4 flex flex-col gap-2 md:flex-row md:items-center">
            <input id="fontPickerSearch" type="search" class="form-input flex-1" placeholder="Search fonts (e.g., Inter, serif, display)" />
            <select id="fontPickerCategory" class="form-select md:w-48">
              <option value="all">All Categories</option>
              <option value="sans-serif">Sans-serif</option>
              <option value="serif">Serif</option>
              <option value="display">Display</option>
              <option value="handwriting">Handwriting</option>
              <option value="monospace">Monospace</option>
            </select>
          </div>
          <div id="fontPickerList" class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3" data-font-target="" data-selected-font="">
            <!-- Font options populated by JS -->
          </div>
          <div class="mt-4">
            <label for="fontPickerCustomInput" class="block text-sm font-medium mb-1">Custom Font Stack</label>
            <input id="fontPickerCustomInput" type="text" class="form-input w-full" placeholder="Example: Merienda, 'Times New Roman', serif" />
            <p class="text-xs text-gray-500 mt-1">Useful when you need a specific combination not listed above. Separate multiple fonts with commas.</p>
          </div>
        </div>
        <div class="modal-footer flex items-center justify-between">
          <div id="fontPickerDescription" class="text-sm text-gray-600">Choose a font stack that matches your brand tone.</div>
          <div class="flex gap-2">
            <button type="button" class="btn btn-secondary" data-action="close-font-picker">Cancel</button>
            <button type="button" class="btn btn-primary" data-action="apply-font-selection">Use Selected Font</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Business Info Modal (native form, branding removed) -->
    <div id="businessInfoModal" class="admin-modal-overlay wf-overlay-viewport over-header topmost hidden" aria-hidden="true" role="dialog" aria-modal="true" tabindex="-1" aria-labelledby="businessInfoTitle">
      <div class="admin-modal admin-modal-content admin-modal--actions-in-header admin-modal--xl">
        <div class="modal-header">
          <h2 id="businessInfoTitle" class="admin-card-title">🏢 Business Information</h2>
          <div class="modal-header-actions">
            <span id="businessInfoStatus" class="text-sm text-gray-600" aria-live="polite"></span>
            <button type="button" class="btn btn-primary btn-sm" data-action="business-save">Save</button>
          </div>
          <button type="button" class="admin-modal-close wf-admin-nav-button" data-action="close-business-info" aria-label="Close">×</button>
        </div>
        <div class="modal-body">
          <form id="businessInfoForm" data-action="prevent-submit">
            <div class="grid gap-3 sm:grid-cols-1 md:grid-cols-2 lg:grid-cols-3">
              <section class="modal-section">
                <h3 class="text-sm font-semibold mb-2">Identity</h3>
                <div class="grid gap-3 md:grid-cols-2">
                  <div>
                    <label for="bizName" class="block text-sm font-medium mb-1">Business Name</label>
                    <input id="bizName" type="text" class="form-input w-full" placeholder="(ie: Whimsical Frog)" />
                  </div>
                  <div>
                    <label for="bizEmail" class="block text-sm font-medium mb-1">Business Email</label>
                    <input id="bizEmail" type="email" class="form-input w-full" placeholder="(ie: info@yourdomain.com)" />
                  </div>
                  <div>
                    <label for="bizWebsite" class="block text-sm font-medium mb-1">Website</label>
                    <input id="bizWebsite" type="url" class="form-input w-full" placeholder="(ie: https://yourdomain.com)" />
                  </div>
                  <div>
                    <label for="bizLogoUrl" class="block text-sm font-medium mb-1">Logo URL</label>
                    <input id="bizLogoUrl" type="url" class="form-input w-full" placeholder="(ie: https://yourdomain.com/logo.png)" />
                  </div>
                  <div class="md:col-span-2">
                    <label for="bizTagline" class="block text-sm font-medium mb-1">Tagline</label>
                    <input id="bizTagline" type="text" class="form-input w-full" placeholder="(ie: Pond-erful Crafts, Hoppy by Design!)" />
                  </div>
                  <div class="md:col-span-2">
                    <label for="bizDescription" class="block text-sm font-medium mb-1">Short Description</label>
                    <input id="bizDescription" type="text" class="form-input w-full" placeholder="(ie: What customers should know)" />
                  </div>
                </div>
              </section>

              <section class="modal-section">
                <h3 class="text-sm font-semibold mb-2">Contact & Hours</h3>
                <div class="grid gap-3 md:grid-cols-2">
                  <div>
                    <label for="bizPhone" class="block text-sm font-medium mb-1">Phone</label>
                    <input id="bizPhone" type="text" class="form-input w-full" placeholder="(ie: (555) 555-5555)" />
                  </div>
                  <div>
                    <label for="bizHours" class="block text-sm font-medium mb-1">Hours</label>
                    <input id="bizHours" type="text" class="form-input w-full" placeholder="(ie: Mon–Fri 9–5)" />
                  </div>
                  <div>
                    <label for="bizSupportEmail" class="block text-sm font-medium mb-1">Support Email</label>
                    <input id="bizSupportEmail" type="email" class="form-input w-full" placeholder="support@yourdomain.com" />
                  </div>
                  <div>
                    <label for="bizSupportPhone" class="block text-sm font-medium mb-1">Support Phone</label>
                    <input id="bizSupportPhone" type="text" class="form-input w-full" placeholder="(555) 555-5556" />
                  </div>
                </div>
              </section>

              <section class="modal-section">
                <h3 class="text-sm font-semibold mb-2">Address</h3>
                <div class="grid gap-3">
                  <div>
                    <label for="bizAddress" class="block text-sm font-medium mb-1">Address</label>
                    <textarea id="bizAddress" class="form-textarea w-full" rows="3" placeholder="(ie: 123 Main St, City, ST 00000)"></textarea>
                  </div>
                  <div class="grid gap-3 md:grid-cols-3">
                    <div>
                      <label for="bizAddress2" class="block text-sm font-medium mb-1">Address 2</label>
                      <input id="bizAddress2" type="text" class="form-input w-full" placeholder="Suite 100" />
                    </div>
                    <div>
                      <label for="bizCity" class="block text-sm font-medium mb-1">City</label>
                      <input id="bizCity" type="text" class="form-input w-full" />
                    </div>
                    <div>
                      <label for="bizState" class="block text-sm font-medium mb-1">State/Region</label>
                      <input id="bizState" type="text" class="form-input w-full" />
                    </div>
                    <div>
                      <label for="bizPostal" class="block text-sm font-medium mb-1">Postal Code</label>
                      <input id="bizPostal" type="text" class="form-input w-full" />
                    </div>
                    <div>
                      <label for="bizCountry" class="block text-sm font-medium mb-1">Country</label>
                      <input id="bizCountry" type="text" class="form-input w-full" placeholder="US" />
                    </div>
                  </div>
                </div>
              </section>

              <section class="modal-section">
                <h3 class="text-sm font-semibold mb-2">Legal & Locale</h3>
                <div class="grid gap-3 md:grid-cols-2">
                  <div>
                    <label for="bizTermsUrl" class="block text-sm font-medium mb-1">Terms URL</label>
                    <input id="bizTermsUrl" type="url" class="form-input w-full" placeholder="https://.../terms" />
                  </div>
                  <div>
                    <label for="bizPrivacyUrl" class="block text-sm font-medium mb-1">Privacy URL</label>
                    <input id="bizPrivacyUrl" type="url" class="form-input w-full" placeholder="https://.../privacy" />
                  </div>
                  <div>
                    <label for="bizTaxId" class="block text-sm font-medium mb-1">Tax ID</label>
                    <input id="bizTaxId" type="text" class="form-input w-full" placeholder="EIN/Tax ID" />
                  </div>
                  <div>
                    <label for="bizTimezone" class="block text-sm font-medium mb-1">Timezone</label>
                    <input id="bizTimezone" type="text" class="form-input w-full" placeholder="America/New_York" />
                  </div>
                  <div>
                    <label for="bizCurrency" class="block text-sm font-medium mb-1">Currency</label>
                    <input id="bizCurrency" type="text" class="form-input w-full" placeholder="USD" />
                  </div>
                  <div>
                    <label for="bizLocale" class="block text-sm font-medium mb-1">Locale</label>
                    <input id="bizLocale" type="text" class="form-input w-full" placeholder="en-US" />
                  </div>
                </div>
              </section>

              <section class="modal-section">
                <h3 class="text-sm font-semibold mb-2">Footer</h3>
                <div class="grid gap-3 md:grid-cols-2">
                  <div>
                    <label for="footerNote" class="block text-sm font-medium mb-1">Footer Note</label>
                    <textarea id="footerNote" class="form-textarea w-full" rows="2" placeholder="Short note shown in footer"></textarea>
                  </div>
                  <div>
                    <label for="footerHtml" class="block text-sm font-medium mb-1">Footer HTML</label>
                    <textarea id="footerHtml" class="form-textarea w-full" rows="2" placeholder="Custom HTML (safe subset)"></textarea>
                  </div>
                </div>
              </section>

              <section class="modal-section">
                <h3 class="text-sm font-semibold mb-2">Store Policies</h3>
                <div class="grid gap-3 md:grid-cols-2">
                  <div>
                    <label for="returnPolicy" class="block text-sm font-medium mb-1">Return Policy</label>
                    <textarea id="returnPolicy" class="form-textarea w-full" rows="3"></textarea>
                  </div>
                  <div>
                    <label for="shippingPolicy" class="block text-sm font-medium mb-1">Shipping Policy</label>
                    <textarea id="shippingPolicy" class="form-textarea w-full" rows="3"></textarea>
                  </div>
                  <div class="md:col-span-2 grid gap-3 md:grid-cols-2">
                    <div>
                      <label for="warrantyPolicy" class="block text-sm font-medium mb-1">Warranty Policy</label>
                      <textarea id="warrantyPolicy" class="form-textarea w-full" rows="3"></textarea>
                    </div>
                    <div>
                      <label for="policyUrl" class="block text-sm font-medium mb-1">Store Policy URL</label>
                      <input id="policyUrl" type="url" class="form-input w-full" placeholder="https://.../policies" />
                    </div>
                  </div>
                </div>
              </section>

              <section class="modal-section">
                <h3 class="text-sm font-semibold mb-2">About Page</h3>
                <div class="grid gap-3">
                  <div>
                    <label for="aboutPageTitle" class="block text-sm font-medium mb-1">About Page Title</label>
                    <input id="aboutPageTitle" type="text" class="form-input w-full" placeholder="Our Story" />
                  </div>
                  <div>
                    <label for="aboutPageContent" class="block text-sm font-medium mb-1">About Page Content (HTML supported)</label>
                    <textarea id="aboutPageContent" class="form-textarea w-full" rows="6" placeholder="Write your story here…"></textarea>
                  </div>
                </div>
              </section>

              <section class="modal-section md:col-span-2 xl:col-span-3">
                <h3 class="text-sm font-semibold mb-2">Legal Policies Content</h3>
                <div class="grid gap-3 md:grid-cols-3">
                  <div class="md:col-span-1">
                    <label for="privacyPolicyContent" class="block text-sm font-medium mb-1">Privacy Policy</label>
                    <textarea id="privacyPolicyContent" class="form-textarea w-full" rows="6" placeholder="Your privacy policy…"></textarea>
                  </div>
                  <div class="md:col-span-1">
                    <label for="termsOfServiceContent" class="block text-sm font-medium mb-1">Terms of Service</label>
                    <textarea id="termsOfServiceContent" class="form-textarea w-full" rows="6" placeholder="Your terms of service…"></textarea>
                  </div>
                  <div class="md:col-span-1">
                    <label for="storePoliciesContent" class="block text-sm font-medium mb-1">Store Policies (general)</label>
                    <textarea id="storePoliciesContent" class="form-textarea w-full" rows="6" placeholder="General store policies…"></textarea>
                  </div>
                </div>
              </section>
            </div>
          </form>
        </div>
        
      </div>
    </div>

    <!-- Email Settings Modal (lightweight shell; bridge populates) -->
    <div id="emailSettingsModal" class="admin-modal-overlay wf-overlay-viewport over-header topmost hidden" aria-hidden="true" role="dialog" aria-modal="true" tabindex="-1" aria-labelledby="emailSettingsTitle">
      <div class="admin-modal admin-modal--auto admin-modal--actions-in-header">
        <div class="modal-header">
          <h2 id="emailSettingsTitle" class="admin-card-title">✉️ Email Settings</h2>
          <button type="button" class="admin-modal-close wf-admin-nav-button" data-action="close-email-settings" aria-label="Close">×</button>
          <span class="modal-status-chip" aria-live="polite"></span>
        </div>
        <div class="modal-body">
          <div class="text-sm text-gray-600 mb-2">Configure sender and SMTP options. Fields will load automatically.</div>
          <form id="emailConfigForm" data-action="prevent-submit" class="space-y-3">
            <div class="grid gap-3 md:grid-cols-2">
              <div>
                <label for="fromEmail" class="block text-sm font-medium mb-1">From Email</label>
                <input id="fromEmail" type="email" class="form-input w-full" placeholder="you@domain.com" />
              </div>
              <div>
                <label for="fromName" class="block text-sm font-medium mb-1">From Name</label>
                <input id="fromName" type="text" class="form-input w-full" placeholder="Your Business" />
              </div>
            </div>
            <div class="grid gap-3 md:grid-cols-2">
              <div>
                <label for="adminEmail" class="block text-sm font-medium mb-1">Admin Email</label>
                <input id="adminEmail" type="email" class="form-input w-full" />
              </div>
              <div>
                <label for="bccEmail" class="block text-sm font-medium mb-1">BCC Email</label>
                <input id="bccEmail" type="email" class="form-input w-full" />
              </div>
            </div>
            <div>
              <label for="replyToEmail" class="block text-sm font-medium mb-1">Reply-To</label>
              <input id="replyToEmail" type="email" class="form-input w-full" />
            </div>
            <div class="grid gap-3 md:grid-cols-2">
              <div>
                <label for="testRecipient" class="block text-sm font-medium mb-1">Test Recipient</label>
                <input id="testRecipient" type="email" class="form-input w-full" placeholder="test@domain.com" />
              </div>
              <div class="flex items-end">
                <button type="button" class="btn btn-secondary" data-action="email-send-test">Send Test</button>
              </div>
            </div>
            <div>
              <label class="inline-flex items-center gap-2"><input id="smtpEnabled" type="checkbox" /><span>Enable SMTP</span></label>
            </div>
            <div id="smtpSettings" class="grid gap-3 md:grid-cols-2 hidden">
              <div>
                <label for="smtpHost" class="block text-sm font-medium mb-1">SMTP Host</label>
                <input id="smtpHost" type="text" class="form-input w-full" placeholder="smtp.gmail.com" />
              </div>
              <div>
                <label for="smtpPort" class="block text-sm font-medium mb-1">SMTP Port</label>
                <input id="smtpPort" type="number" class="form-input w-full" placeholder="465" />
              </div>
              <div>
                <label for="smtpUsername" class="block text-sm font-medium mb-1">SMTP Username</label>
                <input id="smtpUsername" type="text" class="form-input w-full" autocomplete="username" />
              </div>
              <div>
                <label for="smtpPassword" class="block text-sm font-medium mb-1">SMTP Password</label>
                <input id="smtpPassword" type="password" class="form-input w-full" autocomplete="new-password" />
              </div>
              <div>
                <label for="smtpEncryption" class="block text-sm font-medium mb-1">Encryption</label>
                <select id="smtpEncryption" class="form-select w-full">
                  <option value="">None</option>
                  <option value="ssl">SSL</option>
                  <option value="tls">TLS</option>
                </select>
              </div>
              <div>
                <label for="smtpTimeout" class="block text-sm font-medium mb-1">Timeout (sec)</label>
                <input id="smtpTimeout" type="number" class="form-input w-full" />
              </div>
              <div class="col-span-2">
                <label class="inline-flex items-center gap-2"><input id="smtpAuth" type="checkbox" /><span>Use SMTP Auth</span></label>
              </div>
              <div class="col-span-2">
                <label class="inline-flex items-center gap-2"><input id="smtpDebug" type="checkbox" /><span>Enable SMTP Debug</span></label>
              </div>
            </div>
          </form>
        </div>
      </div>
    </div>

    <!-- Email History Modal (dedicated UI) -->
    <div id="emailHistoryModal" class="admin-modal-overlay wf-overlay-viewport over-header topmost hidden" aria-hidden="true" role="dialog" aria-modal="true" tabindex="-1" aria-labelledby="emailHistoryTitle">
      <div class="admin-modal admin-modal-content admin-modal--actions-in-header">
        <div class="modal-header">
          <h2 id="emailHistoryTitle" class="admin-card-title">📬 Email History</h2>
          <button type="button" class="admin-modal-close wf-admin-nav-button" data-action="close-admin-modal" aria-label="Close">×</button>
          <span class="modal-status-chip" aria-live="polite"></span>
        </div>
        <div class="modal-body">
          <div class="flex flex-wrap items-center gap-2 mb-3">
            <input id="emailHistorySearch" type="text" class="form-input" placeholder="Search subject, to, type..." />
            <button type="button" class="btn btn-secondary" data-action="email-history-search">Search</button>
            <span class="mx-2 text-sm text-gray-500">|</span>
            <input id="emailHistoryFrom" type="date" class="form-input" />
            <input id="emailHistoryTo" type="date" class="form-input" />
            <input id="emailHistoryType" type="text" class="form-input" placeholder="Type (e.g., order_confirmation)" list="emailTypeOptions" />
            <select id="emailHistorySort" class="form-select">
              <option value="sent_at_desc">Sort: Sent At (newest)</option>
              <option value="sent_at_asc">Sort: Sent At (oldest)</option>
              <option value="subject_asc">Sort: Subject (A–Z)</option>
              <option value="subject_desc">Sort: Subject (Z–A)</option>
            </select>
            <select id="emailHistoryStatusFilter" class="form-select">
              <option value="">All Statuses</option>
              <option value="sent">Sent</option>
              <option value="failed">Failed</option>
              <option value="queued">Queued</option>
            </select>
            <button type="button" class="btn btn-secondary" data-action="email-history-apply-filters">Apply Filters</button>
            <button type="button" class="btn btn-secondary" data-action="email-history-clear-filters">Clear</button>
            <button type="button" class="btn btn-secondary" data-action="email-history-refresh">Refresh</button>
            <button type="button" class="btn btn-secondary" data-action="email-history-download">Download CSV</button>
            <div id="emailHistoryStatus" class="text-sm text-gray-600"></div>
          </div>
          <div id="emailHistoryList" class="border rounded-sm divide-y overflow-auto">
            <!-- rows injected here -->
          </div>
          <div id="emailHistoryDrawerOverlay" class="email-drawer-overlay hidden" aria-hidden="true"></div>
          <!-- Detail Drawer -->
          <div id="emailHistoryDrawer" class="email-drawer hidden" aria-hidden="true" role="region" aria-label="Email Details">
            <div class="drawer-header flex items-center justify-between px-3 py-2 border-b">
              <div class="font-semibold">Email Details</div>
              <div class="flex items-center gap-3">
                <button type="button" class="admin-modal-close wf-admin-nav-button" title="Close" aria-label="Close" data-action="email-history-close-drawer">×</button>
              </div>
            </div>
            <div class="drawer-meta px-3 py-2 border-b text-xs" id="emailHistoryDrawerMeta">
              <div class="flex items-center gap-2"><span class="text-gray-500">Subject:</span> <span class="font-mono" id="ehdSubject"></span> <button type="button" class="text-blue-600 hover:underline" data-action="email-history-copy-subject">Copy</button></div>
              <div class="flex items-center gap-2 mt-1"><span class="text-gray-500">To:</span> <span class="font-mono" id="ehdTo"></span> <button type="button" class="text-blue-600 hover:underline" data-action="email-history-copy-to">Copy</button></div>
              <div class="flex items-center gap-2 mt-1"><span class="text-gray-500">Type:</span> <span class="font-mono" id="ehdType"></span> <button type="button" class="text-blue-600 hover:underline" data-action="email-history-copy-type">Copy</button></div>
              <div class="flex items-center gap-3 mt-2">
                <button type="button" class="text-blue-600 hover:underline" data-action="email-history-copy-headers">Copy Headers</button>
                <span class="text-gray-300">|</span>
                <button type="button" class="text-blue-600 hover:underline" data-action="email-history-toggle-json">Minify JSON</button>
                <span class="text-gray-300">|</span>
                <button type="button" class="text-blue-600 hover:underline" data-action="email-history-copy-curl">Copy cURL (POST)</button>
                <span class="text-gray-400">→ paste your endpoint</span>
              </div>
              <div class="flex flex-wrap items-center gap-2 mt-2">
                <label for="ehdEndpoint" class="text-gray-500">Test endpoint:</label>
                <input id="ehdEndpoint" type="url" class="form-input text-xs" placeholder="https://your-endpoint.example/ingest" />
                <button type="button" class="text-blue-600 hover:underline" data-action="email-history-save-endpoint">Save</button>
                <span class="text-gray-300">|</span>
                <button type="button" class="text-blue-600 hover:underline" data-action="email-history-open-test">Open in new tab</button>
              </div>
            </div>
            <div class="p-3 text-xs overflow-auto" id="emailHistoryDrawerContent">
              <!-- populated by JS -->
            </div>
          </div>
          <datalist id="emailTypeOptions"></datalist>
          <div class="flex items-center justify-between mt-3">
            <button type="button" class="btn btn-secondary" data-action="email-history-prev">Prev</button>
            <div id="emailHistoryPage" class="text-sm text-gray-600">Page 1</div>
            <button type="button" class="btn btn-secondary" data-action="email-history-next">Next</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Square Settings Modal (hidden by default) -->
    <div id="squareSettingsModal" class="admin-modal-overlay wf-overlay-viewport over-header topmost hidden" aria-hidden="true" role="dialog" aria-modal="true" tabindex="-1" aria-labelledby="squareSettingsTitle">
      <div class="admin-modal admin-modal--actions-in-header">
        <div class="modal-header">
          <h2 id="squareSettingsTitle" class="admin-card-title">🟩 Square Settings</h2>
          <div class="modal-header-actions">
            <span id="squareSettingsStatus" class="text-sm text-gray-600" aria-live="polite"></span>
            <button id="saveSquareSettingsBtn" type="button" class="btn btn-primary btn-sm" data-action="square-save-settings">Save Settings</button>
          </div>
          <button type="button" class="admin-modal-close wf-admin-nav-button" data-action="close-square-settings" aria-label="Close">×</button>
        </div>
        <div class="modal-body">
          <div class="flex items-center justify-between mb-2">
            <button id="squareSettingsBtn" type="button" class="btn btn-secondary">
              Status
              <span id="squareConfiguredChip" class="status-chip chip-off">Not configured</span>
            </button>
          </div>

          <!-- Connection Status -->
          <div id="squareConnectionStatus" class="mb-4 p-3 rounded-lg border border-gray-200 bg-gray-50">
            <div class="flex items-center gap-2">
              <span id="connectionIndicator" class="w-3 h-3 rounded-full bg-gray-400"></span>
              <span id="connectionText" class="text-sm text-gray-700">Not Connected</span>
            </div>
          </div>

          <!-- Config Form (client saves via JS) -->
          <form id="squareConfigForm" data-action="prevent-submit" class="space-y-4">
            <!-- Environment -->
            <div>
              <label class="block text-sm font-medium mb-1">Environment</label>
              <div class="flex items-center gap-4">
                <label class="inline-flex items-center gap-2">
                  <input type="radio" name="environment" value="sandbox" checked>
                  <span>Sandbox</span>
                </label>
                <label class="inline-flex items-center gap-2">
                  <input type="radio" name="environment" value="production">
                  <span>Production</span>
                </label>
              </div>
            </div>

            <!-- App ID / Location ID -->
            <div class="grid gap-4 md:grid-cols-2">
              <div>
                <label for="squareAppId" class="block text-sm font-medium mb-1">Application ID</label>
                <input id="squareAppId" name="app_id" type="text" class="form-input w-full" placeholder="sq0idp-...">
              </div>
              <div>
                <label for="squareLocationId" class="block text-sm font-medium mb-1">Location ID</label>
                <input id="squareLocationId" name="location_id" type="text" class="form-input w-full" placeholder="L8K4...">
              </div>
            </div>

            <!-- Access Token (never prefilled) -->
            <div>
              <label for="squareAccessToken" class="block text-sm font-medium mb-1">Access Token</label>
              <input id="squareAccessToken" name="access_token" type="password" class="form-input w-full" placeholder="Paste your Square access token" autocomplete="off" />
              <p class="text-xs text-gray-500 mt-1">Token is never prefetched for security. Saving will store it server-side.</p>
            </div>

            <!-- Sync options -->
            <div>
              <label class="block text-sm font-medium mb-2">Sync Options</label>
              <div class="grid gap-2 md:grid-cols-2">
                <label class="inline-flex items-center gap-2">
                  <input id="syncPrices" type="checkbox" checked>
                  <span>Sync Prices</span>
                </label>
                <label class="inline-flex items-center gap-2">
                  <input id="syncInventory" type="checkbox" checked>
                  <span>Sync Inventory</span>
                </label>
                <label class="inline-flex items-center gap-2">
                  <input id="syncDescriptions" type="checkbox">
                  <span>Sync Descriptions</span>
                </label>
                <label class="inline-flex items-center gap-2">
                  <input id="autoSync" type="checkbox">
                  <span>Enable Auto Sync</span>
                </label>
              </div>
            </div>

            <!-- Actions -->
            <div class="flex flex-wrap items-center gap-3">
              <button type="button" class="btn btn-secondary btn--square-action" data-action="square-test-connection">Test Connection</button>
              <button type="button" class="btn btn-secondary btn--square-action" data-action="square-sync-items">Sync Items</button>
              <button type="button" class="btn btn-danger btn--square-action" data-action="square-clear-token">Clear Token</button>
            </div>

            <div id="connectionResult" class="text-sm text-gray-600"></div>
          </form>
        </div>
      </div>
    </div>
    

    <!-- Dashboard Configuration Modal (hidden by default) -->
    <div id="dashboardConfigModal" class="admin-modal-overlay wf-overlay-viewport over-header topmost hidden" aria-hidden="true" role="dialog" aria-modal="true" tabindex="-1" aria-labelledby="dashboardConfigTitle">
      <div class="admin-modal admin-modal-content admin-modal--dashboard-config admin-modal--actions-in-header">
        <div class="modal-header">
          <h2 id="dashboardConfigTitle" class="admin-card-title">⚙️ Dashboard Configuration</h2>
          <div class="modal-header-actions">
            <span id="dashboardConfigResult" class="text-sm text-gray-500" aria-live="polite"></span>
            <button type="button" class="btn btn-primary btn-sm" data-action="dashboard-config-save">Save</button>
          </div>
          <button type="button" class="admin-modal-close wf-admin-nav-button" data-action="close-admin-modal" aria-label="Close">×</button>
        </div>
        <div class="modal-body">
          <div class="space-y-4">
            <p class="text-sm text-gray-700">Toggle which sections are active on your Dashboard, then click Save.</p>
            <div class="overflow-x-auto">
              <table class="w-full text-sm" id="dashboardSectionsTable">
                <colgroup>
                  <col><col><col><col><col>
                </colgroup>
                <thead>
                  <tr class="border-b">
                    <th class="p-2 text-left">Order</th>
                    <th class="p-2 text-left">Section</th>
                    <th class="p-2 text-left">Key</th>
                    <th class="p-2 text-left">Width</th>
                    <th class="p-2 text-left">Active</th>
                  </tr>
                </thead>
                <tbody id="dashboardSectionsBody"></tbody>
              </table>
            </div>
            <div class="flex justify-end items-center">
              <div class="flex items-center gap-2">
                <button type="button" class="btn" data-action="dashboard-config-reset">Reset to defaults</button>
                <button type="button" class="btn btn-secondary" data-action="dashboard-config-refresh">Refresh</button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Logging Status Modal (hidden by default) -->
    <div id="loggingStatusModal" class="admin-modal-overlay wf-overlay-viewport over-header topmost hidden" aria-hidden="true" role="dialog" aria-modal="true">
      <div class="admin-modal admin-modal--actions-in-header">
        <div class="modal-header">
          <h2 class="admin-card-title">📜 Log Viewer</h2>
          <button type="button" class="admin-modal-close wf-admin-nav-button" data-action="close-logging-status" aria-label="Close">×</button>
        </div>
        <div class="modal-body">
          <div class="space-y-3">
            <div id="loggingSummary" class="text-sm text-gray-700">Current log levels and destinations will appear here.</div>
            <div class="flex flex-wrap items-center gap-3">
              <button type="button" class="btn-icon btn-icon--refresh" data-action="logging-refresh-status" aria-label="Refresh" title="Refresh"></button>
              <button type="button" class="btn-icon btn-icon--preview-inline" data-action="logging-open-file" aria-label="View latest log file" title="View latest log file"></button>
              <button type="button" class="btn btn-danger" data-action="logging-clear-logs">Clear Logs</button>
            </div>
            <div id="loggingShortcuts" class="border-t pt-3 mt-3">
              <div id="loggingShortcutsList" class="space-y-2"></div>
              <div class="mt-3 flex items-center gap-2">
                <button type="button" class="btn btn-secondary" data-action="logging-download-all">Download All Log Files (zip)</button>
              </div>
            </div>
            <div id="loggingStatusResult" class="status status--info"></div>
          </div>
        </div>
      </div>
    </div>

    <!-- Log File Viewer Modal (hidden by default) -->
    <div id="logFileViewerModal" class="admin-modal-overlay wf-overlay-viewport over-header topmost wf-modal-closable hidden" aria-hidden="true" role="dialog" aria-modal="true" tabindex="-1" aria-labelledby="logFileViewerTitle">
      <div class="admin-modal admin-modal-content admin-modal--lg admin-modal--actions-in-header">
        <div class="modal-header">
          <h2 id="logFileViewerTitle" class="admin-card-title">🪟 Log Viewer</h2>
          <button type="button" class="admin-modal-close wf-admin-nav-button" data-action="close-admin-modal" aria-label="Close">×</button>
        </div>
        <div class="modal-body admin-modal-body--lg">
          <iframe id="logFileViewerFrame" title="Log content" src="about:blank" class="wf-admin-embed-frame wf-admin-embed-frame--tall" referrerpolicy="no-referrer"></iframe>
        </div>
      </div>
    </div>

    <!-- Secrets Manager Modal (hidden by default) -->
    <div id="secretsModal" class="admin-modal-overlay wf-overlay-viewport over-header topmost hidden" aria-hidden="true" role="dialog" aria-modal="true">
      <div class="admin-modal admin-modal--actions-in-header">
        <div class="modal-header">
          <h2 class="admin-card-title">🔒 Secrets Manager</h2>
          <button type="button" class="admin-modal-close wf-admin-nav-button" data-action="close-secrets-modal" aria-label="Close">×</button>
        </div>
        <div class="modal-body">
          <form id="secretsForm" data-action="prevent-submit" class="space-y-4">
            <input type="hidden" id="secretsCsrf" value="<?= htmlspecialchars((string)($__secrets_csrf ?? '')) ?>">
            <p class="text-sm text-gray-700">Paste JSON or key=value lines to update secrets. Sensitive values are never prefilled.</p>
            <textarea id="secretsPayload" name="secrets_payload" class="form-textarea w-full" rows="8" placeholder='{"SMTP_PASS":"..."}'></textarea>
            <div class="flex flex-wrap items-center gap-3">
              <button type="button" class="btn btn-primary" data-action="secrets-save">Save Secrets</button>
              <button type="button" class="btn btn-secondary" data-action="secrets-rotate">Rotate Keys</button>
              <button type="button" class="btn btn-secondary" data-action="secrets-export">Export Secrets</button>
            </div>
            <div id="secretsResult" class="status status--info"></div>
          </form>
        </div>
      </div>
    <!-- Artificial Intelligence Modal (Tools-first) -->
    <div id="aiUnifiedModal" class="admin-modal-overlay over-header wf-modal-autowide wf-modal-viewport-fill wf-modal-closable hidden" aria-hidden="true" role="dialog" aria-modal="true" tabindex="-1" aria-labelledby="aiUnifiedTitle">
      <div class="admin-modal admin-modal-content admin-modal--responsive admin-modal--actions-in-header">
        <div class="modal-header">
          <h2 id="aiUnifiedTitle" class="admin-card-title">🧠 Artificial Intelligence</h2>
          <button type="button" class="admin-modal-close wf-admin-nav-button" data-action="close-admin-modal" aria-label="Close">×</button>
        </div>
        <div class="modal-body">
          <div id="aiUnifiedToolsPanel" class="">
            <iframe id="aiUnifiedToolsFrame" title="AI Tools" src="about:blank" data-src="/sections/ai_tools.php?modal=1" class="wf-admin-embed-frame" referrerpolicy="no-referrer" data-autosize="1"></iframe>
          </div>
        </div>
      </div>
    </div>

    <!-- AI Settings Modal (separate) -->
    <div id="aiSettingsModal" class="admin-modal-overlay over-header topmost wf-modal-autowide wf-modal-viewport-fill wf-modal-mincols-3 hidden" aria-hidden="true" role="dialog" aria-modal="true">
      <div class="admin-modal admin-modal-content admin-modal--responsive admin-modal--actions-in-header">
        <div class="modal-header">
          <h2 class="admin-card-title">🤖 AI Settings</h2>
          <div class="modal-header-actions">
            <span id="aiSettingsResult" class="text-sm text-gray-500" aria-live="polite"></span>
            <button type="button" class="btn btn-primary btn-sm" data-action="save-ai-settings">Save Settings</button>
          </div>
          <button type="button" class="admin-modal-close wf-admin-nav-button" data-action="close-admin-modal" aria-label="Close">×</button>
        </div>
        <div class="modal-body">
          <form id="aiSettingsForm" class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label for="aiProvider" class="block text-sm font-medium mb-1">AI Provider</label>
                <select id="aiProvider" name="ai_provider" class="form-select w-full">
                  <option value="jons_ai">Jon&apos;s AI</option>
                  <option value="openai">OpenAI</option>
                  <option value="anthropic">Anthropic</option>
                  <option value="google">Google</option>
                  <option value="meta">Meta</option>
                </select>
              </div>
              <div>
                <label for="aiTemperature" class="block text-sm font-medium mb-1">Temperature</label>
                <input id="aiTemperature" name="ai_temperature" type="range" min="0" max="1" step="0.1" class="w-full" />
                <span id="aiTemperatureValue" class="text-xs text-gray-500">0.7</span>
              </div>
            </div>

            <div id="aiProviderSettings"></div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label for="aiMaxTokens" class="block text-sm font-medium mb-1">Max Tokens</label>
                <input id="aiMaxTokens" name="ai_max_tokens" type="number" min="100" max="4000" class="form-input w-full" value="1000" />
              </div>
              <div>
                <label for="aiTimeout" class="block text-sm font-medium mb-1">Timeout (seconds)</label>
                <input id="aiTimeout" name="ai_timeout" type="number" min="5" max="120" class="form-input w-full" value="30" />
              </div>
            </div>

            <div class="flex items-center">
              <input id="fallbackToLocal" name="fallback_to_local" type="checkbox" class="mr-2" />
              <label for="fallbackToLocal" class="text-sm">Fallback to local AI if external API fails</label>
            </div>

            <div class="flex justify-between items-center">
              <div id="aiSettingsResult" class="text-sm text-gray-500"></div>
              <div class="flex items-center gap-2">
                <button type="button" class="btn btn-secondary" data-action="test-ai-provider">Test Provider</button>
              </div>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>

<?php
?>
<!-- WF: SETTINGS WRAPPER END -->


<?php if (!defined('WF_ADMIN_SECTION_WRAPPED')): ?>
    </div>
    </div>
</div>
<?php endif; ?>
