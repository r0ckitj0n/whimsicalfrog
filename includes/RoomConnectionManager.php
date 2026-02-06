<?php
/**
 * Room Connection Manager
 * Extracted logic for room navigation, external links, and connection detection.
 */

require_once __DIR__ . '/database.php';

class RoomConnectionManager
{

    /**
     * Get all connection types: internal, external, and header.
     */
    public static function getAll(): array
    {
        $internal = Database::queryAll("
            SELECT 
                am.room_number as source_room,
                CASE 
                    WHEN am.content_target LIKE 'room:%' THEN SUBSTRING(am.content_target, 6)
                    ELSE am.content_target
                END as target_room,
                rs_source.room_name as source_name,
                rs_target.room_name as target_name,
                am.area_selector,
                am.mapping_type,
                'internal' as connection_type
            FROM area_mappings am
            LEFT JOIN room_settings rs_source ON am.room_number = rs_source.room_number
            LEFT JOIN room_settings rs_target ON 
                (am.content_target LIKE 'room:%' AND SUBSTRING(am.content_target, 6) = rs_target.room_number)
                OR (am.content_target NOT LIKE 'room:%' AND am.content_target = rs_target.room_number)
            WHERE am.room_number IS NOT NULL
            AND (
                (am.content_target REGEXP '^[0-9A-Za-z]$' AND am.mapping_type IN ('content', 'button'))
                OR am.content_target LIKE 'room:%'
            )
            ORDER BY rs_source.room_name, rs_target.room_name
        ");

        $external = Database::queryAll("
            SELECT 
                am.room_number as source_room,
                am.link_url as target_url,
                rs_source.room_name as source_name,
                am.area_selector,
                am.mapping_type,
                'external' as connection_type
            FROM area_mappings am
            LEFT JOIN room_settings rs_source ON am.room_number = rs_source.room_number
            WHERE am.room_number IS NOT NULL
            AND am.link_url IS NOT NULL 
            AND am.link_url != ''
            ORDER BY rs_source.room_name
        ");

        $header = Database::queryAll("
            SELECT slug, label, url, 'header' as connection_type
            FROM sitemap_entries 
            WHERE slug IN ('shop', 'about', 'contact')
            AND is_active = 1
            ORDER BY 
                CASE slug 
                    WHEN 'shop' THEN 1 
                    WHEN 'about' THEN 2 
                    WHEN 'contact' THEN 3 
                END
        ");

        return [
            'connections' => $internal ?? [],
            'external_links' => $external ?? [],
            'header_links' => $header ?? []
        ];
    }

    /**
     * Get incoming and outgoing connections for a specific room.
     */
    public static function getForRoom(string $room): array
    {
        $outgoing = Database::queryAll("
            SELECT rc.*, rs.room_name as target_name
            FROM room_connections rc
            LEFT JOIN room_settings rs ON rc.target_room = rs.room_number
            WHERE rc.source_room = ?
            ORDER BY rc.target_room
        ", [$room]);

        $incoming = Database::queryAll("
            SELECT rc.*, rs.room_name as source_name
            FROM room_connections rc
            LEFT JOIN room_settings rs ON rc.source_room = rs.room_number
            WHERE rc.target_room = ?
            ORDER BY rc.source_room
        ", [$room]);

        return [
            'outgoing' => $outgoing ?? [],
            'incoming' => $incoming ?? []
        ];
    }

    /**
     * Get connections that are defined but not yet linked in area_mappings.
     */
    public static function getMissingLinks(): array
    {
        return Database::queryAll("
            SELECT rc.*, sr.room_name as source_name, tr.room_name as target_name
            FROM room_connections rc
            LEFT JOIN room_settings sr ON rc.source_room = sr.room_number
            LEFT JOIN room_settings tr ON rc.target_room = tr.room_number
            WHERE rc.link_created = FALSE
            ORDER BY rc.source_room, rc.target_room
        ") ?? [];
    }

    /**
     * Scan area_mappings to detect and sync room connections.
     */
    public static function detectAndSync(): array
    {
        $roomMappings = Database::queryAll("
            SELECT DISTINCT 
                room_number as source_room,
                CASE 
                    WHEN content_target LIKE 'room:%' THEN SUBSTRING(content_target, 6)
                    ELSE content_target
                END as target_room
            FROM area_mappings 
            WHERE room_number IS NOT NULL
            AND (
                (content_target REGEXP '^[0-9A-Za-z]$' AND mapping_type IN ('content', 'button'))
                OR content_target LIKE 'room:%'
            )
        ");

        $added = 0;
        $updated = 0;
        $found = [];

        foreach ($roomMappings as $mapping) {
            $source = $mapping['source_room'];
            $target = $mapping['target_room'];

            if (strlen((string) $source) === 0 || strlen((string) $target) === 0 || $source === $target) {
                continue;
            }

            $existing = Database::queryOne(
                "SELECT id, link_created FROM room_connections WHERE source_room = ? AND target_room = ?",
                [$source, $target]
            );

            if ($existing) {
                if (!$existing['link_created']) {
                    Database::execute("UPDATE room_connections SET link_created = TRUE WHERE id = ?", [$existing['id']]);
                    $updated++;
                }
            } else {
                Database::execute(
                    "INSERT INTO room_connections (source_room, target_room, connection_type, link_created) VALUES (?, ?, 'one_way', TRUE)",
                    [$source, $target]
                );
                $added++;
            }

            $found[] = ['source' => $source, 'target' => $target];

            $reverse = Database::queryOne(
                "SELECT id FROM room_connections WHERE source_room = ? AND target_room = ?",
                [$target, $source]
            );

            if ($reverse) {
                Database::execute("UPDATE room_connections SET connection_type = 'bidirectional' WHERE source_room = ? AND target_room = ?", [$source, $target]);
                Database::execute("UPDATE room_connections SET connection_type = 'bidirectional' WHERE source_room = ? AND target_room = ?", [$target, $source]);
            }
        }

        // Mark stale links
        Database::execute("
            UPDATE room_connections rc
            SET link_created = FALSE
            WHERE NOT EXISTS (
                SELECT 1 FROM area_mappings am 
                WHERE am.room_number = rc.source_room 
                AND (
                    am.content_target = CONCAT('room:', rc.target_room)
                    OR (am.content_target = rc.target_room AND am.mapping_type IN ('content', 'button'))
                )
            )
        ");

        return [
            'connections_found' => count($found),
            'added' => $added,
            'updated' => $updated,
            'detected' => $found
        ];
    }

    /**
     * Create a new room connection.
     */
    public static function create(string $source, string $target, string $type = 'bidirectional'): int
    {
        if ($source === $target)
            throw new Exception('Cannot connect a room to itself');

        $existing = Database::queryOne("SELECT id FROM room_connections WHERE source_room = ? AND target_room = ?", [$source, $target]);
        if ($existing)
            throw new Exception('Connection already exists');

        Database::execute(
            "INSERT INTO room_connections (source_room, target_room, connection_type, link_created) VALUES (?, ?, ?, FALSE)",
            [$source, $target, $type]
        );
        $id = (int) Database::lastInsertId();

        if ($type === 'bidirectional') {
            $rev = Database::queryOne("SELECT id FROM room_connections WHERE source_room = ? AND target_room = ?", [$target, $source]);
            if (!$rev) {
                Database::execute("INSERT INTO room_connections (source_room, target_room, connection_type, link_created) VALUES (?, ?, 'bidirectional', FALSE)", [$target, $source]);
            }
        }
        return $id;
    }

    /**
     * Update connection type or linked status.
     */
    public static function update(int $id, array $data): bool
    {
        $current = Database::queryOne("SELECT * FROM room_connections WHERE id = ?", [$id]);
        if (!$current)
            throw new Exception('Connection not found');

        if (isset($data['connection_type'])) {
            $newType = $data['connection_type'];
            $oldType = $current['connection_type'];
            Database::execute("UPDATE room_connections SET connection_type = ? WHERE id = ?", [$newType, $id]);

            if ($newType === 'bidirectional' && $oldType === 'one_way') {
                $rev = Database::queryOne("SELECT id FROM room_connections WHERE source_room = ? AND target_room = ?", [$current['target_room'], $current['source_room']]);
                if (!$rev) {
                    Database::execute("INSERT INTO room_connections (source_room, target_room, connection_type, link_created) VALUES (?, ?, 'bidirectional', FALSE)", [$current['target_room'], $current['source_room']]);
                }
            } elseif ($newType === 'one_way' && $oldType === 'bidirectional') {
                Database::execute("DELETE FROM room_connections WHERE source_room = ? AND target_room = ?", [$current['target_room'], $current['source_room']]);
            }
        }

        if (isset($data['link_created'])) {
            Database::execute("UPDATE room_connections SET link_created = ? WHERE id = ?", [$data['link_created'] ? 1 : 0, $id]);
        }

        return true;
    }

    /**
     * Delete a connection and its bidirectional counterpart if applicable.
     */
    public static function delete(int $id): bool
    {
        $conn = Database::queryOne("SELECT * FROM room_connections WHERE id = ?", [$id]);
        if (!$conn)
            throw new Exception('Connection not found');

        Database::execute("DELETE FROM room_connections WHERE id = ?", [$id]);

        if ($conn['connection_type'] === 'bidirectional') {
            Database::execute("DELETE FROM room_connections WHERE source_room = ? AND target_room = ?", [$conn['target_room'], $conn['source_room']]);
        }
        return true;
    }
}
