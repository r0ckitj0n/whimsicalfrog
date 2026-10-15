<?php
require __DIR__ . '/../../api/config.php';
require_once __DIR__ . '/../../includes/christmas_catalog.php';

function esc($v): string
{
    if ($v === null) {
        return 'NULL';
    }
    return "'" . str_replace(["\\", "'"], ["\\\\", "\\'"], (string) $v) . "'";
}

$out = [];
$out[] = 'SET FOREIGN_KEY_CHECKS=0;';

foreach (wf_christmas_catalog_pages() as $page) {
    $room = $page['room'];
    $rs = Database::queryOne('SELECT * FROM room_settings WHERE room_number=?', [$room]);
    if ($rs) {
        $out[] = 'INSERT INTO room_settings (room_number, room_name, door_label, description, background_url, target_aspect_ratio, render_context, background_display_type, show_search_bar, has_icons_white_background, icon_panel_color, icon_vertical_alignment, room_role, display_order, is_active)
VALUES (' . esc($rs['room_number']) . ',' . esc($rs['room_name']) . ',' . esc($rs['door_label']) . ',' . esc($rs['description']) . ',' . esc($rs['background_url']) . ',' . esc($rs['target_aspect_ratio']) . ',' . esc($rs['render_context']) . ',' . esc($rs['background_display_type']) . ',' . ((int) $rs['show_search_bar']) . ',' . ((int) $rs['has_icons_white_background']) . ',' . esc($rs['icon_panel_color']) . ',' . esc($rs['icon_vertical_alignment']) . ',' . esc($rs['room_role']) . ',' . ((int) $rs['display_order']) . ',1)
ON DUPLICATE KEY UPDATE room_name=VALUES(room_name), door_label=VALUES(door_label), description=VALUES(description), background_url=VALUES(background_url), target_aspect_ratio=VALUES(target_aspect_ratio), render_context=VALUES(render_context), background_display_type=VALUES(background_display_type), show_search_bar=VALUES(show_search_bar), has_icons_white_background=VALUES(has_icons_white_background), icon_panel_color=VALUES(icon_panel_color), icon_vertical_alignment=VALUES(icon_vertical_alignment), room_role=VALUES(room_role), display_order=VALUES(display_order), is_active=1;';
    }

    $map = Database::queryOne('SELECT * FROM room_maps WHERE room_number=? AND is_active=1 ORDER BY id DESC LIMIT 1', [$room]);
    if ($map) {
        $out[] = 'UPDATE room_maps SET is_active=0 WHERE room_number=' . esc($room) . ';';
        $out[] = 'INSERT INTO room_maps (room_number, map_name, coordinates, is_active) VALUES (' . esc($map['room_number']) . ',' . esc($map['map_name']) . ',' . esc($map['coordinates']) . ',1);';
    }

    $out[] = 'DELETE FROM room_category_assignments WHERE room_number=' . esc($room) . ';';
    $out[] = 'INSERT INTO room_category_assignments (room_number, room_name, category_id, is_primary, display_order) VALUES (' . esc($room) . ',' . esc($page['title']) . ',1012,1,0);';

    $bg = Database::queryOne("SELECT * FROM backgrounds WHERE room_number=? AND name LIKE '%Christmas Catalog%' ORDER BY id DESC LIMIT 1", [$room]);
    if ($bg) {
        $out[] = 'UPDATE backgrounds SET is_active=0 WHERE room_number=' . esc($room) . ';';
        $out[] = 'INSERT INTO backgrounds (room_number, name, image_filename, png_filename, webp_filename, is_active, theme) VALUES (' . esc($bg['room_number']) . ',' . esc($bg['name']) . ',' . esc($bg['image_filename']) . ',' . esc($bg['png_filename']) . ',' . esc($bg['webp_filename']) . ',1,' . esc($bg['theme']) . ');';
    }

    $maps = Database::queryAll('SELECT * FROM area_mappings WHERE room_number=? AND is_active=1', [$room]);
    foreach ($maps as $m) {
        $out[] = 'DELETE FROM area_mappings WHERE room_number=' . esc($room) . ' AND area_selector=' . esc($m['area_selector']) . ';';
        $out[] = 'INSERT INTO area_mappings (room_number, area_selector, mapping_type, link_label, content_target, content_image, link_image, display_order, is_active) VALUES (' . esc($m['room_number']) . ',' . esc($m['area_selector']) . ',' . esc($m['mapping_type']) . ',' . esc($m['link_label']) . ',' . esc($m['content_target']) . ',' . esc($m['content_image']) . ',' . esc($m['link_image']) . ',' . ((int) $m['display_order']) . ',1);';
    }
}

$m = Database::queryOne("SELECT * FROM area_mappings WHERE room_number='6' AND area_selector='.area-15' LIMIT 1");
if ($m) {
    $out[] = "DELETE FROM area_mappings WHERE room_number='6' AND area_selector='.area-15';";
    $out[] = 'INSERT INTO area_mappings (room_number, area_selector, mapping_type, link_label, content_target, content_image, link_image, display_order, is_active) VALUES (\'6\',\'.area-15\',' . esc($m['mapping_type']) . ',' . esc($m['link_label']) . ',' . esc($m['content_target']) . ',' . esc($m['content_image']) . ',' . esc($m['link_image']) . ',' . ((int) $m['display_order']) . ',1);';
}

$out[] = 'SET FOREIGN_KEY_CHECKS=1;';
$path = '/tmp/christmas_catalog_live_patch.clean.sql';
file_put_contents($path, implode("\n", $out) . "\n");
echo 'wrote ' . $path . ' statements=' . count($out) . ' bytes=' . filesize($path) . PHP_EOL;
