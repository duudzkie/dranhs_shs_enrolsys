<?php
/**
 * room_locator.php — Public room locator API
 * Returns JSON of all room assignments for the visual map
 * Usage: room_locator.php
 */

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

$conn = new mysqli('localhost', 'root', '', 'dranhswin');
if ($conn->connect_error) {
    echo json_encode(['error' => true]);
    exit;
}

// Fetch all sections with room assignments
// Each room can have up to 2 sections (1 per grade level)
$res = $conn->query("
    SELECT s.id, s.name AS section_name, s.grade_level, s.room,
           c.adviser_name, c.track, c.pathway_strand
    FROM add_sections s
    LEFT JOIN classrooms c ON c.section_name = s.name
    WHERE s.room IS NOT NULL AND s.room != ''
    ORDER BY s.room ASC, s.grade_level ASC
");

$rooms = [];
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $r = $row['room'];
        if (!isset($rooms[$r])) $rooms[$r] = [];
        $rooms[$r][] = [
            'section'   => $row['section_name'],
            'grade'     => $row['grade_level'],
            'adviser'   => $row['adviser_name'] ?? '',
            'track'     => $row['track'] ?? '',
            'pathway'   => $row['pathway_strand'] ?? '',
        ];
    }
}

$conn->close();
echo json_encode(['rooms' => $rooms]);
