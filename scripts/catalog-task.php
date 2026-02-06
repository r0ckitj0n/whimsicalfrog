<?php
/**
 * scripts/catalog-task.php
 * 
 * CLI tool to index AI agent tasks into the agent_knowledge_catalog table.
 * Usage: php scripts/catalog-task.php "ConvID" "Task Name" "Summary" "Path/to/Walkthrough"
 */

require_once __DIR__ . '/../api/config.php';

if ($argc < 5) {
    echo "Usage: php catalog-task.php <conversation_id> <task_name> <summary> <artifact_path>\n";
    exit(1);
}

$convId = $argv[1];
$taskName = $argv[2];
$summary = $argv[3];
$artifactPath = $argv[4];

// Database configuration from environment variables
$host = getenv('WF_DB_LOCAL_HOST') ?: 'localhost';
$db = getenv('WF_DB_LOCAL_NAME') ?: 'whimsicalfrog';
$user = getenv('WF_DB_LOCAL_USER') ?: 'root';
$pass = getenv('WF_DB_LOCAL_PASS') ?: '';
$port = getenv('WF_DB_LOCAL_PORT') ?: '3306';

try {
    $dsn = "mysql:host=$host;dbname=$db;port=$port;charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];
    $pdo = new PDO($dsn, $user, $pass, $options);

    $sql = "INSERT INTO agent_knowledge_catalog (conversation_id, task_name, summary, artifact_path) 
            VALUES (:convId, :taskName, :summary, :artifactPath)";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        'convId' => $convId,
        'taskName' => $taskName,
        'summary' => $summary,
        'artifactPath' => $artifactPath
    ]);

    echo "✅ Task successfully cataloged.\n";

} catch (\PDOException $e) {
    echo "❌ Database Error: " . $e->getMessage() . "\n";
    exit(1);
}
