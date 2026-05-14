<?php
require_once __DIR__ . '/../db.php';
$c = db_connect();
$q = $c->query('DESCRIBE students');
$cols = array_column($q->fetch_all(MYSQLI_ASSOC), 'Field');
$new = ['prev_school', 'prev_school_year', 'prev_section'];
foreach ($new as $col) {
    if (!in_array($col, $cols)) {
        $c->query("ALTER TABLE students ADD COLUMN $col VARCHAR(255) NULL");
        echo "Added $col\n";
    }
}
?>