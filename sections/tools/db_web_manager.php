<?php
// Web Database Manager (migrated to sections/tools)
require_once dirname(__DIR__, 2) . '/api/config.php';

// Simple authentication (legacy inline)
if (!isset($_SESSION)) { @session_start(); }
$password = 'Palz2516Admin';
$authenticated = isset($_SESSION['db_auth']) && $_SESSION['db_auth'] === true;
if (isset($_POST['password'])) {
    if ($_POST['password'] === $password) {
        $_SESSION['db_auth'] = true;
        $authenticated = true;
    } else {
        $error = 'Invalid password';
    }
}
if (isset($_GET['logout'])) { unset($_SESSION['db_auth']); $authenticated = false; }
if (!$authenticated) {
    $__wf_included_layout = false;
    if (!function_exists('__wf_admin_root_footer_shutdown')) {
        include dirname(__DIR__, 2) . '/partials/header.php';
        $__wf_included_layout = true;
    }
    ?>
    <div class="page-content">
      <div class="panel" style="max-width:640px;margin:48px auto;">
        <h2>🐸 Database Manager</h2>
        <p>Enter password to access database management tools:</p>
        <?php if (isset($error)) { echo "<div class='error'>" . htmlspecialchars($error) . "</div>"; } ?>
        <form method="post">
          <input type="password" name="password" placeholder="Password" required class="form-input" style="max-width:320px;">
          <button type="submit" class="btn btn-primary">Login</button>
        </form>
      </div>
    </div>
    <?php if ($__wf_included_layout) { include dirname(__DIR__, 2) . '/partials/footer.php'; } ?>
    <?php
    exit;
}

// Database configurations (centralized)
$configs = [
    'local' => array_merge(wf_get_db_config('local'), ['name' => 'Local Database']),
    'live'  => array_merge(wf_get_db_config('live'),  ['name' => 'Live Database (IONOS)'])
];
$currentEnv = $_GET['env'] ?? 'local';
$config = $configs[$currentEnv] ?? $configs['local'];

// Handle AJAX requests
if (isset($_POST['action'])) {
    header('Content-Type: application/json');
    try {
        $options = [ PDO::ATTR_TIMEOUT => 10 ];
        $pdo = Database::createConnection(
            $config['host'], $config['db'], $config['user'], $config['pass'], 3306, null, $options
        );
        $qAll = function(string $sql, array $params = []) use ($pdo): array { $stmt=$pdo->prepare($sql); $stmt->execute($params); return $stmt->fetchAll(PDO::FETCH_ASSOC); };
        $qOne = function(string $sql, array $params = []) use ($pdo): ?array { $stmt=$pdo->prepare($sql); $stmt->execute($params); $row=$stmt->fetch(PDO::FETCH_ASSOC); return $row===false?null:$row; };
        $exec = function(string $sql, array $params = []) use ($pdo): int { $stmt=$pdo->prepare($sql); $stmt->execute($params); return (int)$stmt->rowCount(); };
        switch ($_POST['action']) {
            case 'status':
                $versionRow = $qOne("SELECT VERSION() as version");
                $countRow   = $qOne("SELECT COUNT(*) as count FROM information_schema.tables WHERE table_schema = ?", [$config['db']]);
                $sizeRow    = $qOne("SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS size_mb FROM information_schema.tables WHERE table_schema = ?", [$config['db']]);
                echo json_encode(['success'=>true,'data'=>[
                    'version'=>$versionRow['version']??'', 'tables'=>$countRow['count']??0, 'size'=>($sizeRow['size_mb']??0).' MB',
                    'host'=>$config['host'], 'database'=>$config['db']
                ]]);
                break;
            case 'tables':
                $tablesRaw = $qAll("SHOW TABLES");
                $tables = array_map(function($r){ return array_values($r)[0]; }, $tablesRaw);
                $tableData=[]; foreach ($tables as $t){ $row=$qOne("SELECT COUNT(*) as count FROM `{$t}`"); $tableData[]=['name'=>$t,'rows'=>(int)($row['count']??0)]; }
                echo json_encode(['success'=>true,'data'=>$tableData]);
                break;
            case 'query':
                $sql = $_POST['sql'] ?? '';
                if (empty($sql)) { echo json_encode(['success'=>false,'error'=>'SQL query required']); break; }
                $op = strtoupper(trim(explode(' ', trim($sql))[0]));
                if (in_array($op, ['SELECT','SHOW','DESCRIBE','EXPLAIN'])) {
                    $results = $qAll($sql);
                    echo json_encode(['success'=>true,'data'=>$results,'type'=>'select','rows'=>count($results)]);
                } else {
                    $affected = $exec($sql);
                    echo json_encode(['success'=>true,'data'=>[],'type'=>'update','affected'=>$affected]);
                }
                break;
            case 'describe':
                $table = $_POST['table'] ?? '';
                if (!$table) { echo json_encode(['success'=>false,'error'=>'Table name required']); break; }
                $structure = $qAll("DESCRIBE `{$table}`");
                echo json_encode(['success'=>true,'data'=>$structure]);
                break;
        }
    } catch (Exception $e) {
        echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
    }
    exit;
}

$__wf_included_layout = false;
if (!function_exists('__wf_admin_root_footer_shutdown')) {
    include dirname(__DIR__, 2) . '/partials/header.php';
    $__wf_included_layout = true;
}
?>
<div class="header">
  <h1>🐸 WhimsicalFrog Database Manager</h1>
  <a href="?logout=1" class="logout">Logout</a>
</div>
<div class="env-switcher">
  <a href="?env=local" class="<?= $currentEnv === 'local' ? 'active' : '' ?>">Local Database</a>
  <a href="?env=live" class="<?= $currentEnv === 'live' ? 'active' : '' ?>">Live Database</a>
</div>
<div class="panel">
  <h2><?= htmlspecialchars($config['name']) ?> Status</h2>
  <div id="status" class="loading">Loading database status...</div>
</div>
<div class="tabs">
  <button class="tab active" data-action="showTab" data-params='{"tabName":"query"}'>SQL Query</button>
  <button class="tab" data-action="showTab" data-params='{"tabName":"tables"}'>Tables</button>
  <button class="tab" data-action="showTab" data-params='{"tabName":"structure"}'>Table Structure</button>
</div>
<div id="query" class="tab-content active panel">
  <h3>Execute SQL Query</h3>
  <textarea id="sqlQuery" placeholder="SELECT * FROM global_css_rules WHERE category = 'main_room' LIMIT 10;"></textarea>
  <br>
  <button data-action="executeQuery">Execute Query</button>
  <button class="secondary" data-action="clearQuery">Clear</button>
  <div id="queryResults" class="results"></div>
</div>
<div id="tables" class="tab-content panel">
  <h3>Database Tables</h3>
  <button data-action="loadTables">Refresh Tables</button>
  <div id="tablesResults" class="results"></div>
</div>
<div id="structure" class="tab-content panel">
  <h3>Table Structure</h3>
  <input type="text" id="tableName" placeholder="Enter table name" class="input-table-name">
  <button data-action="describeTable">Describe Table</button>
  <div id="structureResults" class="results"></div>
</div>
<div class="panel">
  <h2>🧭 DB Tools (Introspection)</h2>
  <div class="grid" style="grid-template-columns: 1fr 1fr; gap: 12px; align-items: end;">
    <div>
      <label for="wfDbToolsEnv" class="block text-sm">Environment</label>
      <select id="wfDbToolsEnv" class="form-input">
        <option value="local">local</option>
        <option value="live">live</option>
      </select>
    </div>
    <div>
      <label for="wfDbToolsTable" class="block text-sm">Table (for Describe)</label>
      <input id="wfDbToolsTable" type="text" class="form-input" placeholder="e.g., items">
    </div>
  </div>
  <div class="actions" style="margin-top: 12px; display: flex; gap: 8px; flex-wrap: wrap;">
    <button class="btn" data-wf-dbtools="version">🛠 Version</button>
    <button class="btn" data-wf-dbtools="table_counts">📊 Table Count</button>
    <button class="btn" data-wf-dbtools="db_size">💾 DB Size</button>
    <button class="btn" data-wf-dbtools="list_tables">📃 List Tables</button>
    <button class="btn" data-wf-dbtools="describe">📐 Describe Table</button>
  </div>
  <pre id="wfDbToolsOut" class="json-output" style="margin-top:12px; min-height: 120px;">(run a command to see output)</pre>
</div>
<script>
(function(){
  const envSel = document.getElementById('wfDbToolsEnv');
  const tableInput = document.getElementById('wfDbToolsTable');
  const out = document.getElementById('wfDbToolsOut');
  function qsFor(action){
    const p = new URLSearchParams();
    p.set('action', action);
    const env = envSel ? envSel.value : 'local';
    if (env) p.set('env', env);
    const tbl = tableInput ? (tableInput.value||'').trim() : '';
    if (tbl) p.set('table', tbl);
    return p.toString();
  }
  async function call(action, needsCsrf){
    try {
      let res = await fetch('/api/db_tools.php?' + qsFor(action), { credentials: 'include' });
      if (res.status === 428 && needsCsrf) {
        const tRes = await fetch('/api/db_tools.php?action=csrf_token', { credentials: 'include' });
        const token = tRes.headers.get('X-CSRF-Token');
        if (token) {
          res = await fetch('/api/db_tools.php?' + qsFor(action), { credentials: 'include', headers: { 'X-CSRF-Token': token } });
        }
      }
      const data = await res.json();
      if (out) out.textContent = JSON.stringify(data.data || data, null, 2);
    } catch (e) { if (out) out.textContent = 'Error: ' + (e?.message || String(e)); }
  }
  document.addEventListener('click', (e) => {
    const b = e.target.closest('[data-wf-dbtools]');
    if (!b) return;
    e.preventDefault();
    const action = b.getAttribute('data-wf-dbtools');
    call(action, action === 'generate-css');
  });
})();
</script>
<?php if ($__wf_included_layout) { include dirname(__DIR__, 2) . '/partials/footer.php'; } ?>
