<?php
$target = __DIR__ . '/../../sections/admin_settings.php';
$src = file_get_contents($target);
if ($src === false) { fwrite(STDERR, "read fail\n"); exit(1); }

$before = $src;

// Fix draw function - replace optional chaining with ES5-safe code
$src = str_replace('d?.available_sections', '(d && d.available_sections)', $src);
$src = str_replace('Array.isArray(d?.sections)', '(d && Array.isArray(d.sections))', $src);

// Fix the draw function completely to handle the data structure correctly
$src = preg_replace_callback(
    '#const draw=d=>\{([^}]+)\};#s',
    function($m) {
        $body = $m[1];
        // Replace the entire draw function with ES5-safe version
        return 'const draw = function(d){
    var tb = document.getElementById(TBODY); if (!tb) return;
    var avail = (d && d.available_sections) ? d.available_sections : {};
    var act = (d && Array.isArray(d.sections)) ? d.sections : [];
    var ks = new Set(Object.keys(avail));
    act.forEach(function(s){ if (s && s.section_key) ks.add(s.section_key); });
    var active = new Set(act.map(function(s){ return s.section_key; }));
    var sectionData = Array.from(ks).sort().map(function(k,i){
      var found = null;
      for (var ii=0; ii<act.length; ii++){
        if (act[ii] && act[ii].section_key === k){
          found = act[ii]; break;
        }
      }
      var section = found || { section_key:k, display_order:i+1, is_active:0, show_title:1, show_description:1, custom_title:null, custom_description:null, width_class:\'half-width\' };
      var info = avail[k];
      return {
        key: k,
        title: (info && info.title) || k,
        active: active.has(k),
        order: section.display_order || (i+1),
        width: section.width_class || \'half-width\',
        section_key: section.section_key,
        display_order: section.display_order,
        is_active: section.is_active,
        show_title: section.show_title,
        show_description: section.show_description,
        custom_title: section.custom_title,
        custom_description: section.custom_description,
        width_class: section.width_class
      };
    });
    tb.innerHTML = \'\';
    sectionData.forEach(function(item,i){
      var tr = document.createElement(\'tr\');
      tr.className = \'border-b\';
      tr.innerHTML = \'<td class="p-2"><div class="flex items-center gap-1"><button class="text-xs p-1" data-action="move-up" data-key="\' + item.key + \'" \' + (i===0 ? \'disabled\' : \'\') + \'>▲</button><button class="text-xs p-1" data-action="move-down" data-key="\' + item.key + \'" \' + (i===sectionData.length-1 ? \'disabled\' : \'\') + \'>▼</button><span class="ml-1 text-gray-500">\' + item.order + \'</span></div></td><td class="p-2">\' + item.title + \'</td><td class="p-2"><code>\' + item.key + \'</code></td><td class="p-2"><select class="dash-width text-xs" data-key="\' + item.key + \'"><option value="half-width" \' + (item.width===\'half-width\' ? \'selected\' : \'\') + \'>Half</option><option value="full-width" \' + (item.width===\'full-width\' ? \'selected\' : \'\') + \'>Full</option></select></td><td class="p-2"><input type="checkbox" class="dash-active" data-key="\' + item.key + \'" \' + (item.active ? \'checked\' : \'\') + \'></td>\';
      tb.appendChild(tr);
    });
  };';
    },
    $src,
    1
);

// Fix payload function to handle the data structure correctly
$src = preg_replace_callback(
    '#const payload=\(\)=>\{([^}]+)\};#s',
    function($m) {
        $body = $m[1];
        // Fix the payload function to properly collect data from the table
        return 'const payload = function(){
    var rows = Array.prototype.slice.call(document.querySelectorAll(\'#' + TBODY + \' tr\'));
    return {
      action: \'update_sections\',
      sections: rows.map(function(row, i){
        var elA = row.querySelector(\'.dash-active\');
        var key = elA && elA.dataset ? elA.dataset.key : undefined;
        var elW = row.querySelector(\'.dash-width\');
        var width = elW ? elW.value : \'half-width\';
        var active = elA && elA.checked ? 1 : 0;
        return {
          key: key,
          section_key: key,
          display_order: i+1,
          is_active: active,
          show_title: 1,
          show_description: 1,
          custom_title: null,
          custom_description: null,
          width_class: width
        };
      }).filter(function(s){ return s.key; })
    };
  };';
    },
    $src,
    1
);

// Fix move-up/move-down handlers to use ES5-safe syntax
$src = str_replace(
    'r.querySelector(\'.dash-active\')?.dataset.key',
    '(function(){var __el=r.querySelector(\'.dash-active\');return __el && __el.dataset ? __el.dataset.key : undefined;})()',
    $src
);

// If nothing changed, exit quietly
if ($src === $before) { echo "No changes needed.\n"; exit(0); }

// Backup and write
$bak = $target . '.' . date('Ymd_His') . '.bak';
if (file_put_contents($bak, $before) === false) { fwrite(STDERR, "backup failed\n"); exit(1); }
if (file_put_contents($target, $src) === false) { fwrite(STDERR, "write failed\n"); exit(1); }

echo "Patched successfully. Backup: $bak\n";
?>
