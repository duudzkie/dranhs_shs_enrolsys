<?php
$conn = new mysqli('localhost', 'root', '', 'dranhswin');
if ($conn->connect_error) {
    fwrite(STDERR, "Connection failed: " . $conn->connect_error . PHP_EOL);
    exit(1);
}

$columns = [];
$res = $conn->query("SHOW COLUMNS FROM g11_completers");
if (!$res) {
    fwrite(STDERR, "Unable to inspect g11_completers: " . $conn->error . PHP_EOL);
    exit(1);
}

while ($row = $res->fetch_assoc()) {
    $columns[] = $row['Field'];
}

$queries = [];
if (!in_array('strand', $columns, true)) {
    $queries[] = "ALTER TABLE g11_completers ADD COLUMN strand VARCHAR(100) NULL AFTER section";
}
if (!in_array('completer_status', $columns, true)) {
    $queries[] = "ALTER TABLE g11_completers ADD COLUMN completer_status VARCHAR(50) NOT NULL DEFAULT 'regular' AFTER strand";
}

foreach ($queries as $sql) {
    if (!$conn->query($sql)) {
        fwrite(STDERR, "Migration failed: " . $conn->error . PHP_EOL);
        exit(1);
    }
}

echo empty($queries) ? "No changes needed." . PHP_EOL : "g11_completers updated." . PHP_EOL;
$conn->close();
