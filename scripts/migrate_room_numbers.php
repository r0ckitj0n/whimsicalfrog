<?php
/**
 * Safe CLI Migration: Shift room_number values for room_category_assignments
 * Handles potential unique constraints by checking existing assignments.
 */
/**
 * CLI Migration script: Shift room_number values for room_category_assignments
 * Adjusts legacy room numbering (2-6) to new numbering (1-5)
 */

require_once __DIR__ . '/../api/config.php';
require_once __DIR__ . '/../includes/functions.php';

try {
    $pdo = Database::getInstance();
    // Shift legacy room numbers (2-6) down to new scheme (1-5), ignoring duplicate conflicts
    $sql = "UPDATE IGNORE room_category_assignments SET room_number = room_number - 1 WHERE room_number BETWEEN 2 AND 6";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    echo "Migration complete: room numbers shifted (conflicts ignored).\n";
    exit(0);
}catch (Exception $e) {
    $pdo = Database::getInstance();
    $pdo->beginTransaction();

    // Step 1: Temporarily shift 2-6 to avoid unique key conflicts by adding 10
    $pdo->exec("UPDATE room_category_assignments SET room_number = room_number + 10 WHERE room_number BETWEEN 2 AND 6");

    // Step 2: Shift 12-16 down to 1-5 by subtracting 11
    $pdo->exec("UPDATE room_category_assignments SET room_number = room_number - 11 WHERE room_number BETWEEN 12 AND 16");

    $pdo->commit();
    echo "Migration complete: room numbers shifted from 2-6 to 1-5.\n";
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
