<?php
/**
 * room_locator.php — Public room locator API
 * Returns JSON of all room assignments for the visual map
 */

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');
require_once __DIR__ . '/db.php';

$conn = db_connect();

// add_sections.grade_level stores '11'/'12'
// classrooms.grade_level stores 'Grade 11'/'Grade 12'
// Join on section name only — don't rely on grade_level matching
$res = $conn->query("
    SELECT
        s.id,
        s.name AS section_name,
        s.grade_level,
        s.room,
        c.adviser_name,
        c.track,
        c.pathway_strand
    FROM add_sections s
    LEFT JOIN classrooms c ON LOWER(TRIM(c.section_name COLLATE utf8mb4_general_ci)) = LOWER(TRIM(s.name COLLATE utf8mb4_general_ci))
    WHERE s.room IS NOT NULL AND s.room != ''
    ORDER BY CAST(s.room AS UNSIGNED) ASC, s.grade_level ASC
");

$rooms = [];
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $r = $row['room'];
        if (!isset($rooms[$r])) $rooms[$r] = [];
        $rooms[$r][] = [
            'section'   => $row['section_name'],
            'grade'     => $row['grade_level'], // '11' or '12'
            'adviser'   => $row['adviser_name'] ?? '',
            'track'     => $row['track'] ?? '',
            'pathway'   => $row['pathway_strand'] ?? '',
        ];
    }
}

// Fetch annex entries — only those NOT in Bldg 14 or 15
$annex = [];
$ar = $conn->query("SELECT * FROM room_annex WHERE building_number NOT IN ('14','15') ORDER BY building_number, floor_number, room_number");
if ($ar) { while ($r = $ar->fetch_assoc()) $annex[] = $r; $ar->close(); }

// Fetch facilities
$facilities = [];
$fr = $conn->query("SELECT * FROM room_facilities ORDER BY building_number, floor_number, room_number");
if ($fr) { while ($r = $fr->fetch_assoc()) $facilities[] = $r; $fr->close(); }

$conn->close();
echo json_encode(['rooms' => $rooms, 'annex' => $annex, 'facilities' => $facilities]);