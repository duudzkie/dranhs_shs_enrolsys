<?php
require_once __DIR__ . '/../pathway_strand_catalog.php';
require_once __DIR__ . '/../db.php';

$conn = db_connect();

$result = $conn->query("SELECT id, grade_level, pathway_strand FROM students");
if (!$result) {
    die("Query failed: " . $conn->error . PHP_EOL);
}

$update = $conn->prepare("UPDATE students SET pathway_strand = ? WHERE id = ?");
if (!$update) {
    die("Prepare failed: " . $conn->error . PHP_EOL);
}

$updated = 0;
$skipped = 0;

while ($row = $result->fetch_assoc()) {
    $existing = isset($row['pathway_strand']) ? trim((string)$row['pathway_strand']) : '';
    $code = get_pathway_strand_code($row['grade_level'], $existing);

    if ($code === '' || $existing === $code) {
        $skipped++;
        continue;
    }

    $id = (int)$row['id'];
    $update->bind_param("si", $code, $id);
    if ($update->execute()) {
        $updated++;
        echo "Updated student ID {$id}: {$existing} -> {$code}" . PHP_EOL;
    }
}

$update->close();
$result->close();
$conn->close();

echo "Done. Updated {$updated} row(s), skipped {$skipped} row(s)." . PHP_EOL;
?>
