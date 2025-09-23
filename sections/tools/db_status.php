<?php
require_once dirname(__DIR__, 2) . '/api/config.php';
require_once dirname(__DIR__, 2) . '/includes/auth_helper.php';

$__wf_included_layout = false;
if (!function_exists('__wf_admin_root_footer_shutdown')) {
    include dirname(__DIR__, 2) . '/partials/header.php';
    $__wf_included_layout = true;
}
?>
    <div class="">
        <div class="header">
            <h1>🐸 WhimsicalFrog Database Status</h1>
            <p>Database Management Dashboard</p>
        </div>
        <?php
        $smokeResult = null;
if (isset($_GET['smoke'])) {
    AuthHelper::requireAdmin(403, 'Admin access required to run smoke tests');
    $target = $_GET['target'] ?? 'current';
    if (!in_array($target, ['current','local','live'], true)) {
        $target = 'current';
    }
    try {
        if ($target === 'current') {
            $pdo = Database::getInstance();
            $cfg = wf_get_db_config('current');
        } else {
            $cfg = wf_get_db_config($target);
            $pdo = Database::createConnection(
                $cfg['host'],
                $cfg['db'],
                $cfg['user'],
                $cfg['pass'],
                $cfg['port'] ?? 3306,
                $cfg['socket'] ?? null,
                [ PDO::ATTR_TIMEOUT => 5 ]
            );
        }
        $meta = $pdo->query("SELECT VERSION() AS version, DATABASE() AS dbname")->fetch(PDO::FETCH_ASSOC) ?: [];
        $tables = null;
        try {
            $dbName = $cfg['db'] ?? $meta['dbname'] ?? '';
            if ($dbName) {
                $row2 = $pdo->query("SELECT COUNT(*) AS table_count FROM information_schema.tables WHERE table_schema = '" . addslashes($dbName) . "'")->fetch(PDO::FETCH_ASSOC) ?: [];
                $tables = (int)($row2['table_count'] ?? 0);
            }
        } catch (Throwable $e) {
            $tables = null;
        }
        $smokeResult = [
            'ok' => true,
            'target' => $target,
            'config' => [
                'host' => $cfg['host'] ?? null,
                'db' => $cfg['db'] ?? null,
                'user' => $cfg['user'] ?? null,
                'port' => $cfg['port'] ?? null,
                'socket' => $cfg['socket'] ?? null,
            ],
            'mysql_version' => $meta['version'] ?? null,
            'current_db' => $meta['dbname'] ?? null,
            'tables' => $tables,
        ];
    } catch (Throwable $e) {
        $smokeResult = [ 'ok' => false, 'error' => $e->getMessage() ];
    }
}
?>
        
        <div class="status-grid">
            <?php
    try {
        $localPdo = Database::getInstance();
        $localStatus = 'online';

        $stmt = $localPdo->query("SELECT COUNT(*) as count FROM global_css_rules");
        $cssRules = $stmt->fetch()['count'];

        $stmt = $localPdo->query("SELECT COUNT(*) as count FROM items");
        $items = $stmt->fetch()['count'];

        $stmt = $localPdo->query("SELECT COUNT(*) as count FROM rooms");
        $rooms = $stmt->fetch()['count'];

    } catch (Exception $e) {
        $localStatus = 'offline';
        $localError = $e->getMessage();
    }
?>
            
            <div class="status-card">
                <h3>
                    <span class="status-indicator <?= $localStatus === 'online' ? 'status-online' : 'status-offline' ?>"></span>
                    Local Database
                </h3>
                
                <?php if ($localStatus === 'online'): ?>
                    <div class="stat-row">
                        <span>CSS Rules</span>
                        <span class="stat-value"><?= number_format($cssRules) ?></span>
                    </div>
                    <div class="stat-row">
                        <span>Items</span>
                        <span class="stat-value"><?= number_format($items) ?></span>
                    </div>
                    <div class="stat-row">
                        <span>Rooms</span>
                        <span class="stat-value"><?= number_format($rooms) ?></span>
                    </div>
                    <div class="stat-row">
                        <span>Host</span>
                        <span class="stat-value">localhost</span>
                    </div>
                    <div class="stat-row">
                        <span>Database</span>
                        <span class="stat-value">whimsicalfrog</span>
                    </div>
                <?php else: ?>
                    <div class="error">
                        Connection failed: <?= htmlspecialchars($localError ?? 'Unknown error') ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <?php
try {
    $liveCfg = wf_get_db_config('live');
    $livePdo = Database::createConnection(
        $liveCfg['host'],
        $liveCfg['db'],
        $liveCfg['user'],
        $liveCfg['pass'],
        $liveCfg['port'] ?? 3306,
        $liveCfg['socket'] ?? null,
        [ PDO::ATTR_TIMEOUT => 5 ]
    );
    $liveStatus = 'online';

    $stmt = $livePdo->query("SELECT COUNT(*) as count FROM global_css_rules");
    $liveCssRules = $stmt->fetch()['count'];

    $stmt = $livePdo->query("SELECT COUNT(*) as count FROM items");
    $liveItems = $stmt->fetch()['count'];

    $stmt = $livePdo->query("SELECT COUNT(*) as count FROM rooms");
    $liveRooms = $stmt->fetch()['count'];

} catch (Exception $e) {
    $liveStatus = 'offline';
    $liveError = $e->getMessage();
}
?>
            
            <div class="status-card">
                <h3>
                    <span class="status-indicator <?= $liveStatus === 'online' ? 'status-online' : 'status-offline' ?>"></span>
                    Live Database
                </h3>
                
                <?php if ($liveStatus === 'online'): ?>
                    <div class="stat-row">
                        <span>CSS Rules</span>
                        <span class="stat-value"><?= number_format($liveCssRules) ?></span>
                    </div>
                    <div class="stat-row">
                        <span>Items</span>
                        <span class="stat-value"><?= number_format($liveItems) ?></span>
                    </div>
                    <div class="stat-row">
                        <span>Rooms</span>
                        <span class="stat-value"><?= number_format($liveRooms) ?></span>
                    </div>
                    <div class="stat-row">
                        <span>Host</span>
                        <span class="stat-value"><?= htmlspecialchars($liveCfg['host']) ?></span>
                    </div>
                    <div class="stat-row">
                        <span>Database</span>
                        <span class="stat-value"><?= htmlspecialchars($liveCfg['db']) ?></span>
                    </div>
                <?php else: ?>
                    <div class="error">
                        Connection failed: <?= htmlspecialchars($liveError ?? 'Unknown error') ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php if ($smokeResult !== null): ?>
            <div class="status-card">
                <h3>🧪 DB Smoke Test (<?= htmlspecialchars($smokeResult['target'] ?? 'current') ?>)</h3>
                <?php if (!empty($smokeResult['ok'])): ?>
                    <div class="stat-row"><span>Host</span><span class="stat-value"><?= htmlspecialchars($smokeResult['config']['host'] ?? '') ?></span></div>
                    <div class="stat-row"><span>Database</span><span class="stat-value"><?= htmlspecialchars($smokeResult['config']['db'] ?? '') ?></span></div>
                    <div class="stat-row"><span>User</span><span class="stat-value"><?= htmlspecialchars($smokeResult['config']['user'] ?? '') ?></span></div>
                    <div class="stat-row"><span>Port</span><span class="stat-value"><?= htmlspecialchars((string)($smokeResult['config']['port'] ?? '')) ?></span></div>
                    <div class="stat-row"><span>MySQL Version</span><span class="stat-value"><?= htmlspecialchars($smokeResult['mysql_version'] ?? '') ?></span></div>
                    <div class="stat-row"><span>Current DB</span><span class="stat-value"><?= htmlspecialchars($smokeResult['current_db'] ?? '') ?></span></div>
                    <div class="stat-row"><span>Tables</span><span class="stat-value"><?= htmlspecialchars((string)($smokeResult['tables'] ?? 'n/a')) ?></span></div>
                    <div class="success">✅ Connection OK</div>
                <?php else: ?>
                    <div class="error">❌ Connection FAILED: <?= htmlspecialchars($smokeResult['error'] ?? 'Unknown error') ?></div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if ($localStatus === 'online' && $liveStatus === 'online'): ?>
            <div class="status-card">
                <h3>🔄 Sync Status</h3>
                <div class="stat-row">
                    <span>CSS Rules Difference</span>
                    <span class="stat-value"><?= abs($cssRules - $liveCssRules) ?> rules</span>
                </div>
                <div class="stat-row">
                    <span>Items Difference</span>
                    <span class="stat-value"><?= abs($items - $liveItems) ?> items</span>
                </div>
                <div class="stat-row">
                    <span>Rooms Difference</span>
                    <span class="stat-value"><?= abs($rooms - $liveRooms) ?> rooms</span>
                </div>

                <?php if ($cssRules === $liveCssRules && $items === $liveItems && $rooms === $liveRooms): ?>
                    <div class="success">✅ Databases are in sync!</div>
                <?php else: ?>
                    <div class="error">⚠️ Databases are out of sync</div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <div class="actions">
            <a href="/admin/?section=db-web-manager" class="btn">🔧 Web Manager</a>
            <a href="?refresh=1" class="btn">🔄 Refresh Status</a>
            <a href="#" data-action="runCommand" data-params='{"command":"test-css"}' class="btn">🧪 Test CSS</a>
            <a href="#" data-action="runCommand" data-params='{"command":"generate-css"}' class="btn">🎨 Generate CSS</a>
            <a href="?smoke=1&target=current" class="btn">🧪 Smoke Test (Current)</a>
            <a href="?smoke=1&target=local" class="btn">🧪 Smoke Test (Local)</a>
            <a href="?smoke=1&target=live" class="btn">🧪 Smoke Test (Live)</a>
            <button id="runApiSmokeBtn" type="button" class="btn">⚡ Run via API</button>
        </div>

        <div id="apiSmokeTest" class="status-card is-hidden mt-12">
            <h3>🧪 DB Smoke Test via API
                <small class="subtle-note">(api/db_smoke_test.php)</small>
            </h3>
            <div class="stat-row">
                <span>Target</span>
                <span class="stat-value">
                    <select id="apiTargetSelect">
                        <option value="current">current</option>
                        <option value="local">local</option>
                        <option value="live">live</option>
                    </select>
                </span>
            </div>
            <div id="apiParsed" class="mt-8"></div>
            <details id="apiRawWrap" class="mt-8">
                <summary>Show Raw JSON</summary>
                <pre id="apiRaw" class="json-output">(no data)</pre>
            </details>
        </div>

        <div class="status-card mt-12">
            <h3>🧭 DB Tools (Introspection)</h3>
            <div class="stat-row">
                <span>Environment</span>
                <span class="stat-value">
                    <select id="toolsEnvSelect">
                        <option value="local">local</option>
                        <option value="live">live</option>
                    </select>
                </span>
            </div>
            <div class="stat-row">
                <span>Table (for Describe)</span>
                <span class="stat-value"><input id="toolsTableInput" type="text" class="form-input" placeholder="e.g., items"></span>
            </div>
            <div class="actions mt-3">
                <button class="btn" data-action="runCommand" data-params='{"command":"version"}'>🛠 Version</button>
                <button class="btn" data-action="runCommand" data-params='{"command":"table_counts"}'>📊 Table Count</button>
                <button class="btn" data-action="runCommand" data-params='{"command":"db_size"}'>💾 DB Size</button>
                <button class="btn" data-action="runCommand" data-params='{"command":"list_tables"}'>📃 List Tables</button>
                <button class="btn" data-action="runCommand" data-params='{"command":"describe"}'>📐 Describe Table</button>
            </div>
            <pre id="dbToolsOutput" class="json-output mt-4">(run a command to see output)</pre>
        </div>
    </div>
    <?php if ($__wf_included_layout) {
        include dirname(__DIR__, 2) . '/partials/footer.php';
    } ?>
