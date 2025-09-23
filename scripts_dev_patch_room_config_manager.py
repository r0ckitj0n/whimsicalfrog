import re, io, sys
p = "sections/tools/room_config_manager.php"
s = open(p, "r", encoding="utf-8").read()
pattern = re.compile(r"<script>\s*document.getElementById\(roomConfigForm\)[\s\S]*?</script>")
new_js = (
"<script>(function(){\n"
"  var form = document.getElementById(\"roomConfigForm\");\n"
"  var sel = document.getElementById(\"roomSelect\");\n"
"  var container = document.getElementById(\"configFormContainer\");\n"
"  if (!form || !sel) return;\n"
"  function loadConfig(room){\n"
"    if(!room) return;\n"
"    fetch(\"/api/room_config.php?action=get&room=\" + encodeURIComponent(room))\n"
"      .then(function(r){return r.json();})\n"
"      .then(function(j){\n"
"        var cfg = (j && j.config) ? j.config : {};\n"
"        for (var k in cfg){ if(!cfg.hasOwnProperty(k)) continue;\n"
"          var el = form.querySelector(\"[name=\\\"\"+k+\"\\\"]\");\n"
"          if(!el) continue;\n"
"          if (el.type === \"checkbox\") el.checked = !!cfg[k]; else el.value = cfg[k];\n"
"        }\n"
"        var rn = form.querySelector(\"#roomNumber\"); if (rn) rn.value = room;\n"
"        if (container) container.classList.remove(\"hidden\");\n"
"      }).catch(function(e){});\n"
"  }\n"
"  sel.addEventListener(\"change\", function(e){ loadConfig(e.target.value); });\n"
"  form.addEventListener(\"submit\", function(e){\n"
"    e.preventDefault();\n"
"    var room = sel.value; if (!room){ alert(\"Select a room\"); return; }\n"
"    var fd = new FormData(form); var cfg = {};\n"
"    fd.forEach(function(v,k){ if(k===\"room_number\") return; var el=form.querySelector(\"[name=\\\"\"+k+\"\\\"]\"); var val = el && el.type===\"checkbox\" ? el.checked : v; var n = Number(val); cfg[k] = isNaN(n) ? val : n; });\n"
"    fetch(\"/api/room_config.php?action=save\", {method: \"POST\", headers: {\"Content-Type\": \"application/json\"}, body: JSON.stringify({room: room, config: cfg})})\n"
"      .then(function(r){return r.json();})\n"
"      .then(function(j){ if (j && j.success){ alert(\"Settings saved successfully\"); location.reload(); } else { alert(\"Save failed: \" + ((j && (j.message || j.error)) || \"Unknown\")); } })\n"
"      .catch(function(){ alert(\"Save failed\"); });\n"
"  });\n"
"  if (sel.value) loadConfig(sel.value);\n"
"})();</script>\n"
)
if not pattern.search(s):
    print("No fake handler found; aborting.")
    sys.exit(1)
s2 = pattern.sub(new_js, s, count=1)
open(p, "w", encoding="utf-8").write(s2)
print("Patched:", p)
