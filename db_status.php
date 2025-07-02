<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WhimsicalFrog Database Status</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            color: #333;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .header {
            text-align: center;
            color: white;
            margin-bottom: 30px;
        }
        
        .header h1 {
            font-size: 2.5em;
            margin-bottom: 10px;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        }
        
        .status-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .status-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.2);
        }
        
        .status-card h3 {
            margin-top: 0;
            color: #4a5568;
            font-size: 1.3em;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .status-indicator {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            display: inline-block;
        }
        
        .status-online {
            background: #48bb78;
            box-shadow: 0 0 10px rgba(72, 187, 120, 0.5);
        }
        
        .status-offline {
            background: #f56565;
            box-shadow: 0 0 10px rgba(245, 101, 101, 0.5);
        }
        
        .stat-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .stat-row:last-child {
            border-bottom: none;
        }
        
        .stat-value {
            font-weight: bold;
            color: #2d3748;
        }
        
        .actions {
            text-align: center;
            margin-top: 30px;
        }
        
        .btn {
            display: inline-block;
            padding: 12px 24px;
            margin: 0 10px;
            background: rgba(255,255,255,0.2);
            color: white;
            text-decoration: none;
            border-radius: 25px;
            border: 2px solid rgba(255,255,255,0.3);
            transition: all 0.3s ease;
            backdrop-filter: blur(10px);
        }
        
        .btn:hover {
            background: rgba(255,255,255,0.3);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        
        .error {
            background: #fed7d7;
            color: #c53030;
            padding: 15px;
            border-radius: 8px;
            margin: 10px 0;
        }
        
        .success {
            background: #c6f6d5;
            color: #22543d;
            padding: 15px;
            border-radius: 8px;
            margin: 10px 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🐸 WhimsicalFrog Database Status</h1>
            <p>Database Management Dashboard</p>
        </div>
        
        <div class="status-grid">
            <?php
            // Local Database Status
            try {
                $localDsn = "mysql:host=localhost;dbname=whimsicalfrog;charset=utf8mb4";
                $localPdo = new PDO($localDsn, 'root', 'Palz2516', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
                $localStatus = 'online';
                
                // Get local stats
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
            // Live Database Status
            try {
                $liveDsn = "mysql:host=db5017975223.hosting-data.io;dbname=dbs14295502;charset=utf8mb4";
                $livePdo = new PDO($liveDsn, 'dbu2826619', 'Palz2516!', [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_TIMEOUT => 5
                ]);
                $liveStatus = 'online';
                
                // Get live stats
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
                        <span class="stat-value">db5017975223.hosting-data.io</span>
                    </div>
                    <div class="stat-row">
                        <span>Database</span>
                        <span class="stat-value">dbs14295502</span>
                    </div>
                <?php else: ?>
                    <div class="error">
                        Connection failed: <?= htmlspecialchars($liveError ?? 'Unknown error') ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
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
            <a href="db_web_manager.php" class="btn">🔧 Web Manager</a>
            <a href="?refresh=1" class="btn">🔄 Refresh Status</a>
            <a href="#" onclick="runCommand('test-css')" class="btn">🧪 Test CSS</a>
            <a href="#" onclick="runCommand('generate-css')" class="btn">🎨 Generate CSS</a>
        </div>
    </div>
    
    <script>
        function runCommand(cmd) {
            fetch(`db_api.php?action=${cmd}`)
                .then(response => response.json())
                .then(data => {
                    alert(data.message || 'Command executed');
                    if (data.success) {
                        location.reload();
                    }
                })
                .catch(error => {
                    alert('Error: ' + error.message);
                });
        }
        
        // Auto-refresh every 30 seconds
        setTimeout(() => {
            location.reload();
        }, 30000);
    </script>
</body>
</html> 