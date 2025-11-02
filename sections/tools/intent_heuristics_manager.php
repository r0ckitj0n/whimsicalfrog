<?php
$root = dirname(__DIR__, 2);
$inModal = (isset($_GET['modal']) && $_GET['modal'] == '1');
require_once $root . '/api/config.php';
require_once $root . '/includes/vite_helper.php';
if (!$inModal) {
  if (!defined('WF_LAYOUT_BOOTSTRAPPED')) {
    $page = 'admin';
    include $root . '/partials/header.php';
    if (!function_exists('__wf_intent_footer_shutdown')) {
      function __wf_intent_footer_shutdown(){ @include __DIR__ . '/../../partials/footer.php'; }
    }
    register_shutdown_function('__wf_intent_footer_shutdown');
  }
}
if ($inModal) {
  include $root . '/partials/modal_header.php';
}
?>
<div class="admin-marketing-page" id="intentHeuristicsRoot">
  <div class="admin-modal admin-modal-content admin-modal--xl admin-modal--actions-in-header">
    <div class="modal-header">
      <h2 class="admin-card-title">🧠 Intent Heuristics Config</h2>
      <div class="btn-row">
        <button type="button" class="btn" id="btnLoadDefaults">Load Defaults</button>
        <button type="button" class="btn" id="btnReload">Reload Current</button>
        <button type="button" class="btn btn-primary" id="btnSave">Save</button>
        <button type="button" class="btn" data-action="close-admin-modal" aria-label="Close">Close</button>
      </div>
    </div>
    <div class="modal-body">
      <div class="space-y-3 text-sm text-gray-700">
        <div class="card">
          <div class="section-title">Weights</div>
          <div class="admin-form-grid">
            <div><label class="label">Popularity cap</label><input class="input" type="number" step="0.1" id="w_popularity_cap"></div>
            <div><label class="label">Keyword positive</label><input class="input" type="number" step="0.1" id="w_kw_positive"></div>
            <div><label class="label">Category positive</label><input class="input" type="number" step="0.1" id="w_cat_positive"></div>
            <div><label class="label">Seasonal boost</label><input class="input" type="number" step="0.1" id="w_seasonal"></div>
            <div><label class="label">Same category</label><input class="input" type="number" step="0.1" id="w_same_category"></div>
            <div><label class="label">Upgrade price ratio threshold</label><input class="input" type="number" step="0.01" id="w_upgrade_ratio"></div>
            <div><label class="label">Upgrade price boost</label><input class="input" type="number" step="0.1" id="w_upgrade_price"></div>
            <div><label class="label">Upgrade label boost</label><input class="input" type="number" step="0.1" id="w_upgrade_label"></div>
            <div><label class="label">Replacement label boost</label><input class="input" type="number" step="0.1" id="w_replacement_label"></div>
            <div><label class="label">Gift set boost</label><input class="input" type="number" step="0.1" id="w_gift_set"></div>
            <div><label class="label">Gift price boost</label><input class="input" type="number" step="0.1" id="w_gift_price"></div>
            <div><label class="label">Teacher price ceiling</label><input class="input" type="number" step="0.1" id="w_teacher_ceiling"></div>
            <div><label class="label">Teacher price boost</label><input class="input" type="number" step="0.1" id="w_teacher_boost"></div>
            <div><label class="label">Budget proximity multiplier</label><input class="input" type="number" step="0.1" id="w_budget_mult"></div>
            <div><label class="label">Negative keyword penalty</label><input class="input" type="number" step="0.1" id="w_neg_penalty"></div>
            <div><label class="label">Intent badge threshold</label><input class="input" type="number" step="0.1" id="w_badge_threshold"></div>
          </div>
        </div>
        <div class="card">
          <div class="section-title">Budget Ranges</div>
          <div class="admin-form-grid">
            <div><label class="label">Low (min)</label><input class="input" type="number" step="0.1" id="b_low_min"></div>
            <div><label class="label">Low (max)</label><input class="input" type="number" step="0.1" id="b_low_max"></div>
            <div><label class="label">Mid (min)</label><input class="input" type="number" step="0.1" id="b_mid_min"></div>
            <div><label class="label">Mid (max)</label><input class="input" type="number" step="0.1" id="b_mid_max"></div>
            <div><label class="label">High (min)</label><input class="input" type="number" step="0.1" id="b_high_min"></div>
            <div><label class="label">High (max)</label><input class="input" type="number" step="0.1" id="b_high_max"></div>
          </div>
          <div class="help">Prices are in site currency. Used for budget proximity and gift sweet spots.</div>
        </div>
        <div class="card">
          <div class="section-title">Advanced: Seasonal Months Hints (JSON)</div>
          <textarea id="t_seasonal" class="input" rows="4" placeholder='{"12": ["christmas"], "2": ["valentine"]}'></textarea>
          <div class="help">Map month number to an array of seasonal keywords to boost (only used for Holiday intent).</div>
        </div>
        <div class="card">
          <div class="section-title">Advanced: Keyword Positives by Intent (JSON)</div>
          <textarea id="t_kw_pos" class="input" rows="6" placeholder='{"upgrade": ["pro","premium"]}'></textarea>
        </div>
        <div class="card">
          <div class="section-title">Advanced: Keyword Negatives by Intent (JSON)</div>
          <textarea id="t_kw_neg" class="input" rows="4" placeholder='{"replacement": ["gift","decor"]}'></textarea>
        </div>
        <div class="card">
          <div class="section-title">Advanced: Category Affinities by Intent (JSON)</div>
          <textarea id="t_cat_pos" class="input" rows="6" placeholder='{"home-decor": ["home decor","signs"]}'></textarea>
        </div>
      </div>
    </div>
  </div>
</div>
<script>
(function(){
  const $ = (sel)=>document.querySelector(sel);
  const toast=(msg)=>{ const t=document.createElement('div'); t.className='toast'; t.textContent=msg; document.body.appendChild(t); setTimeout(()=>{t.remove();}, 2200); };
  const DefaultConfig = {
    weights: {
      popularity_cap: 3.0,
      kw_positive: 2.5,
      cat_positive: 3.5,
      seasonal: 2.0,
      same_category: 2.0,
      upgrade_price_ratio_threshold: 1.25,
      upgrade_price_boost: 3.0,
      upgrade_label_boost: 2.5,
      replacement_label_boost: 3.0,
      gift_set_boost: 1.0,
      gift_price_boost: 1.5,
      teacher_price_ceiling: 30.0,
      teacher_price_boost: 1.5,
      budget_proximity_mult: 2.0,
      neg_keyword_penalty: 2.0,
      intent_badge_threshold: 2.0
    },
    budget_ranges: {
      low: [8.0,20.0],
      mid: [15.0,40.0],
      high: [35.0,120.0]
    },
    keywords: {
      positive: {
        gift: ["gift","set","bundle","present","pack","box"],
        personal: [],
        replacement: ["refill","replacement","spare","recharge","insert"],
        upgrade: ["upgrade","pro","deluxe","premium","xl","plus","pro+","ultimate"],
        "diy-project": ["diy","kit","project","starter","make your own","how to"],
        "home-decor": ["decor","wall","frame","sign","plaque","art","canvas"],
        holiday: ["holiday","christmas","xmas","easter","halloween","valentine","mother","father"],
        birthday: ["birthday","party","celebration","cake","bday"],
        anniversary: ["anniversary","love","heart","romance","romantic"],
        wedding: ["wedding","bride","groom","bridal","mr & mrs","mr and mrs"],
        "teacher-gift": ["teacher","school","classroom","teach"],
        "office-decor": ["office","desk","workspace","cubicle"],
        "event-supplies": ["event","party","supplies","decoration","bulk"],
        "workshop-class": ["class","workshop","lesson","course","tutorial"]
      },
      negative: {
        gift: ["refill","replacement"],
        replacement: ["gift","decor"],
        upgrade: ["refill"]
      },
      categories: {
        gift: ["gifts","gift sets","bundles"],
        replacement: ["supplies","refills","consumables"],
        "diy-project": ["diy","kits","craft kits","projects"],
        "home-decor": ["home decor","decor","wall art","signs"],
        holiday: ["holiday","seasonal"],
        "office-decor": ["office decor"],
        "event-supplies": ["event supplies","party"],
        "workshop-class": ["classes","workshops"]
      }
    },
    seasonal_months: {
      1:["valentine"], 2:["valentine"], 3:["easter"], 4:["easter"],
      5:["mother"], 6:["father"], 9:["halloween"],10:["halloween"],
      11:["christmas"],12:["christmas"]
    }
  };

  function setForm(cfg){
    const W = cfg.weights || {};
    $('#w_popularity_cap').value = W.popularity_cap ?? '';
    $('#w_kw_positive').value = W.kw_positive ?? '';
    $('#w_cat_positive').value = W.cat_positive ?? '';
    $('#w_seasonal').value = W.seasonal ?? '';
    $('#w_same_category').value = W.same_category ?? '';
    $('#w_upgrade_ratio').value = W.upgrade_price_ratio_threshold ?? '';
    $('#w_upgrade_price').value = W.upgrade_price_boost ?? '';
    $('#w_upgrade_label').value = W.upgrade_label_boost ?? '';
    $('#w_replacement_label').value = W.replacement_label_boost ?? '';
    $('#w_gift_set').value = W.gift_set_boost ?? '';
    $('#w_gift_price').value = W.gift_price_boost ?? '';
    $('#w_teacher_ceiling').value = W.teacher_price_ceiling ?? '';
    $('#w_teacher_boost').value = W.teacher_price_boost ?? '';
    $('#w_budget_mult').value = W.budget_proximity_mult ?? '';
    $('#w_neg_penalty').value = W.neg_keyword_penalty ?? '';
    $('#w_badge_threshold').value = W.intent_badge_threshold ?? '';

    const BR = cfg.budget_ranges || {};
    const low = BR.low || [null,null]; const mid = BR.mid || [null,null]; const high = BR.high || [null,null];
    $('#b_low_min').value = low[0] ?? ''; $('#b_low_max').value = low[1] ?? '';
    $('#b_mid_min').value = mid[0] ?? ''; $('#b_mid_max').value = mid[1] ?? '';
    $('#b_high_min').value = high[0] ?? ''; $('#b_high_max').value = high[1] ?? '';

    $('#t_seasonal').value = JSON.stringify(cfg.seasonal_months || {}, null, 2);
    $('#t_kw_pos').value = JSON.stringify((cfg.keywords && cfg.keywords.positive) || {}, null, 2);
    $('#t_kw_neg').value = JSON.stringify((cfg.keywords && cfg.keywords.negative) || {}, null, 2);
    $('#t_cat_pos').value = JSON.stringify((cfg.keywords && cfg.keywords.categories) || {}, null, 2);
  }

  function getForm(){
    const cfg = { weights:{}, budget_ranges:{}, keywords:{ positive:{}, negative:{}, categories:{} }, seasonal_months:{} };
    cfg.weights.popularity_cap = parseFloat($('#w_popularity_cap').value || '0');
    cfg.weights.kw_positive = parseFloat($('#w_kw_positive').value || '0');
    cfg.weights.cat_positive = parseFloat($('#w_cat_positive').value || '0');
    cfg.weights.seasonal = parseFloat($('#w_seasonal').value || '0');
    cfg.weights.same_category = parseFloat($('#w_same_category').value || '0');
    cfg.weights.upgrade_price_ratio_threshold = parseFloat($('#w_upgrade_ratio').value || '0');
    cfg.weights.upgrade_price_boost = parseFloat($('#w_upgrade_price').value || '0');
    cfg.weights.upgrade_label_boost = parseFloat($('#w_upgrade_label').value || '0');
    cfg.weights.replacement_label_boost = parseFloat($('#w_replacement_label').value || '0');
    cfg.weights.gift_set_boost = parseFloat($('#w_gift_set').value || '0');
    cfg.weights.gift_price_boost = parseFloat($('#w_gift_price').value || '0');
    cfg.weights.teacher_price_ceiling = parseFloat($('#w_teacher_ceiling').value || '0');
    cfg.weights.teacher_price_boost = parseFloat($('#w_teacher_boost').value || '0');
    cfg.weights.budget_proximity_mult = parseFloat($('#w_budget_mult').value || '0');
    cfg.weights.neg_keyword_penalty = parseFloat($('#w_neg_penalty').value || '0');
    cfg.weights.intent_badge_threshold = parseFloat($('#w_badge_threshold').value || '0');

    cfg.budget_ranges.low = [parseFloat($('#b_low_min').value || '0'), parseFloat($('#b_low_max').value || '0')];
    cfg.budget_ranges.mid = [parseFloat($('#b_mid_min').value || '0'), parseFloat($('#b_mid_max').value || '0')];
    cfg.budget_ranges.high = [parseFloat($('#b_high_min').value || '0'), parseFloat($('#b_high_max').value || '0')];

    try { cfg.seasonal_months = JSON.parse($('#t_seasonal').value || '{}'); } catch(e){ cfg.seasonal_months = {}; }
    try { cfg.keywords.positive = JSON.parse($('#t_kw_pos').value || '{}'); } catch(e){ cfg.keywords.positive = {}; }
    try { cfg.keywords.negative = JSON.parse($('#t_kw_neg').value || '{}'); } catch(e){ cfg.keywords.negative = {}; }
    try { cfg.keywords.categories = JSON.parse($('#t_cat_pos').value || '{}'); } catch(e){ cfg.keywords.categories = {}; }

    return cfg;
  }

  async function loadCurrent(){
    try{
      const res = await fetch('/api/business_settings.php?action=get_setting&key=cart_intent_heuristics', { credentials:'include' });
      if(!res.ok){ setForm(DefaultConfig); toast('Loaded defaults (no stored config)'); return; }
      const data = await res.json();
      const row = data && data.setting ? data.setting : null;
      if(!row || !row.setting_value){ setForm(DefaultConfig); toast('Loaded defaults (no stored config)'); return; }
      let cfg = null; try { cfg = JSON.parse(row.setting_value); } catch(e){ cfg = null; }
      if(!cfg || typeof cfg !== 'object'){ setForm(DefaultConfig); toast('Invalid stored config, showing defaults'); return; }
      setForm(cfg); toast('Loaded current config');
    }catch(err){ setForm(DefaultConfig); toast('Failed to load, showing defaults'); }
  }

  async function save(){
    const cfg = getForm();
    const body = JSON.stringify({ category:'ecommerce', settings: { cart_intent_heuristics: cfg } });
    const res = await fetch('/api/business_settings.php?action=upsert_settings', {
      method:'POST', headers:{ 'Content-Type':'application/json' }, body, credentials:'include'
    });
    if(!res.ok){ toast('Save failed'); return; }
    const j = await res.json().catch(()=>({}));
    toast('Saved');
  }

  document.getElementById('btnLoadDefaults').addEventListener('click', ()=>{ setForm(DefaultConfig); });
  document.getElementById('btnReload').addEventListener('click', ()=>{ loadCurrent(); });
  document.getElementById('btnSave').addEventListener('click', ()=>{ save(); });

  loadCurrent();
})();
</script>
