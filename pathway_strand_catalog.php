<?php

function load_pathway_strand_catalog_from_db() {
    static $catalog = null;
    if ($catalog !== null) {
        return $catalog;
    }

    if (!class_exists('mysqli')) {
        $catalog = ['grade_11' => [], 'grade_12' => []];
        return $catalog;
    }

    require_once __DIR__ . '/db.php';
    $conn = @new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
    if ($conn->connect_error) {
        $catalog = ['grade_11' => [], 'grade_12' => []];
        return $catalog;
    }

    $catalog = ['grade_11' => [], 'grade_12' => []];
    $result = $conn->query("SELECT grade_level, category, track, pathway_strand, code, description, electives, enabled FROM pathway_strand ORDER BY FIELD(grade_level,'Grade 11','Grade 12'), category, track, pathway_strand");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $grade_key = pathway_strand_grade_key($row['grade_level'] ?? '');
            if ($grade_key === '') {
                continue;
            }
            $electives = null;
            if (!empty($row['electives'])) {
                $decoded = json_decode($row['electives'], true);
                $electives = is_array($decoded) ? array_values($decoded) : [];
            }
            $catalog[$grade_key][] = [
                'track' => $row['track'] ?? '',
                'label' => $row['pathway_strand'] ?? '',
                'code' => $row['code'] ?? '',
                'description' => $row['description'] ?? '',
                'electives' => $electives,
                'enabled' => !empty($row['enabled']),
                'category' => $row['category'] ?? ''
            ];
        }
        $result->close();
    }

    $conn->close();
    return $catalog;
}

function load_pathway_strand_catalog() {
    static $catalog = null;

    if ($catalog !== null) {
        return $catalog;
    }

    $catalog = load_pathway_strand_catalog_from_db();
    if (!empty($catalog['grade_11']) || !empty($catalog['grade_12'])) {
        return $catalog;
    }

    $path = __DIR__ . DIRECTORY_SEPARATOR . 'pathway_strand_catalog.json';
    if (!file_exists($path)) {
        $catalog = ['grade_11' => [], 'grade_12' => []];
        return $catalog;
    }

    $json = file_get_contents($path);
    $decoded = json_decode($json, true);
    if (!is_array($decoded)) {
        $catalog = ['grade_11' => [], 'grade_12' => []];
        return $catalog;
    }

    $catalog = $decoded;
    return $catalog;
}

function load_curriculum_structure_config() {
    static $config = null;

    if ($config !== null) {
        return $config;
    }

    $config = [];
    if (!class_exists('mysqli')) {
        return $config;
    }

    require_once __DIR__ . '/db.php';
    $conn = @new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
    if ($conn->connect_error) {
        return $config;
    }

    $res = $conn->query("SELECT setting_value FROM system_settings WHERE setting_key = 'curriculum_structure' LIMIT 1");
    if ($res && ($row = $res->fetch_assoc())) {
        $decoded = json_decode((string)($row['setting_value'] ?? ''), true);
        if (is_array($decoded)) {
            $config = $decoded;
        }
    }

    $conn->close();
    return $config;
}

function is_curriculum_option_enabled($grade_level, $track, $label) {
    $grade_key = pathway_strand_grade_key($grade_level);
    if ($grade_key === '') {
        return true;
    }

    $catalog = load_pathway_strand_catalog();
    $items = isset($catalog[$grade_key]) && is_array($catalog[$grade_key]) ? $catalog[$grade_key] : [];
    foreach ($items as $item) {
        if (!is_array($item) || !isset($item['label'])) {
            continue;
        }
        if (strcasecmp((string)$item['label'], (string)$label) === 0 || strcasecmp((string)$item['code'], (string)$label) === 0) {
            return !empty($item['enabled']);
        }
    }

    return true;
}

function pathway_strand_grade_key($grade_level) {
    $normalized = strtolower(trim((string)$grade_level));
    if ($normalized === 'grade 11') return 'grade_11';
    if ($normalized === 'grade 12') return 'grade_12';
    return '';
}

function pathway_strand_slugify($value) {
    $value = strtolower(trim((string)$value));
    $value = preg_replace('/[^a-z0-9]+/', '_', $value);
    return trim($value, '_');
}

function get_pathway_strand_code($grade_level, $label_or_code) {
    $lookup = trim((string)$label_or_code);
    if ($lookup === '') return '';

    $grade_key = pathway_strand_grade_key($grade_level);
    $catalog = load_pathway_strand_catalog();
    $items = isset($catalog[$grade_key]) && is_array($catalog[$grade_key]) ? $catalog[$grade_key] : [];

    foreach ($items as $item) {
        if (!isset($item['label'], $item['code'])) continue;
        if (strcasecmp($item['label'], $lookup) === 0 || strcasecmp($item['code'], $lookup) === 0) {
            return $item['code'];
        }
    }

    return pathway_strand_slugify($lookup);
}

function get_pathway_strand_label($grade_level, $code) {
    $lookup = trim((string)$code);
    if ($lookup === '') return '';

    $grade_key = pathway_strand_grade_key($grade_level);
    $catalog = load_pathway_strand_catalog();
    $items = isset($catalog[$grade_key]) && is_array($catalog[$grade_key]) ? $catalog[$grade_key] : [];

    foreach ($items as $item) {
        if (!isset($item['label'], $item['code'])) continue;
        if (strcasecmp($item['code'], $lookup) === 0) {
            return $item['label'];
        }
    }

    return $lookup;
}

function get_pathway_strand_options($grade_level, $track = null) {
    $grade_key = pathway_strand_grade_key($grade_level);
    $catalog = load_pathway_strand_catalog();
    $items = isset($catalog[$grade_key]) && is_array($catalog[$grade_key]) ? $catalog[$grade_key] : [];

    $items = array_values(array_filter($items, function ($item) use ($grade_level) {
        if (!isset($item['label'])) return false;
        return is_curriculum_option_enabled($grade_level, (string)($item['track'] ?? ''), (string)$item['label']);
    }));

    if ($track === null || $track === '') {
        return $items;
    }

    return array_values(array_filter($items, function ($item) use ($track) {
        return isset($item['track']) && strcasecmp($item['track'], $track) === 0;
    }));
}
?>
