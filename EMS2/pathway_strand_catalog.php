<?php

function load_pathway_strand_catalog() {
    static $catalog = null;

    if ($catalog !== null) {
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

    if ($track === null || $track === '') {
        return $items;
    }

    return array_values(array_filter($items, function ($item) use ($track) {
        return isset($item['track']) && strcasecmp($item['track'], $track) === 0;
    }));
}
?>
