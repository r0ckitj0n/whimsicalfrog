<?php

/**
 * Room and Slug Mapping utility functions
 */

function wf_slugify($value)
{
    $value = trim((string)$value);
    if ($value === '') {
        return null;
    }

    $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
    if ($converted !== false && $converted !== '') {
        $value = $converted;
    }

    $value = strtolower($value);
    $value = preg_replace('/[^a-z0-9]+/', '-', $value);
    $value = trim((string)$value, '-');

    return $value !== '' ? $value : null;
}

function wf_room_settings_has_column($column)
{
    static $cache = [];
    if (array_key_exists($column, $cache)) {
        return $cache[$column];
    }

    try {
        $row = Database::queryOne('SHOW COLUMNS FROM room_settings LIKE ?', [$column]);
        return $cache[$column] = !empty($row);
    } catch (Exception $e) {
        return $cache[$column] = false;
    }
}

function wf_get_room_slug_map()
{
    static $map = null;
    if ($map !== null) {
        return $map;
    }

    $map = [
        'by_slug' => [],
        'by_room' => []
    ];

    try {
        $columns = ['room_number', 'room_name', 'door_label'];
        if (wf_room_settings_has_column('slug')) {
            $columns[] = 'slug';
        }

        $rows = Database::queryAll(
            'SELECT ' . implode(', ', $columns) . ' FROM room_settings WHERE is_active = 1'
        );

        foreach ($rows as $row) {
            $room_number = (string)$row['room_number'];
            $candidates = [];

            if (!empty($row['slug'])) {
                $candidates[] = $row['slug'];
            }
            if (!empty($row['door_label'])) {
                $candidates[] = $row['door_label'];
            }
            if (!empty($row['room_name'])) {
                $candidates[] = $row['room_name'];
            }

            $candidates[] = 'room-' . $room_number;

            $uniqueSlugs = [];
            foreach ($candidates as $candidate) {
                $slug = wf_slugify($candidate);
                if ($slug && !in_array($slug, $uniqueSlugs, true)) {
                    $uniqueSlugs[] = $slug;
                }
            }

            if (empty($uniqueSlugs)) {
                $uniqueSlugs[] = 'room-' . $room_number;
            }

            $preferred = null;
            foreach ($uniqueSlugs as $slug) {
                if ($slug !== 'room-' . $room_number) {
                    $preferred = $slug;
                    break;
                }
            }
            if ($preferred === null) {
                $preferred = $uniqueSlugs[0];
            }

            $map['by_room'][$room_number] = $preferred;

            foreach ($uniqueSlugs as $slug) {
                $map['by_slug'][$slug] = $room_number;
            }

            $map['by_slug']['room' . $room_number] = $room_number;
            $map['by_slug']['room-' . $room_number] = $room_number;
        }
    } catch (Exception $e) {
        // leave map empty on failure
    }

    return $map;
}

function wf_resolve_room_slug($room_number)
{
    $room_number = (string)$room_number;
    $map = wf_get_room_slug_map();
    return $map['by_room'][$room_number] ?? null;
}

function wf_resolve_room_number_from_slug($slug)
{
    $slug = wf_slugify($slug);
    if (!$slug) {
        return null;
    }

    $map = wf_get_room_slug_map();
    return $map['by_slug'][$slug] ?? null;
}

function wf_room_canonical_path($room_number)
{
    $slug = wf_resolve_room_slug($room_number);
    if (!$slug) {
        return null;
    }

    return '/rooms/' . $slug;
}
