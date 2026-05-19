<?php
require_once __DIR__ . '/../db.php';
$c = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
if (!$c) { die('connect fail'); }
$r = mysqli_query($c, 'SHOW COLUMNS FROM students');
while ($x = mysqli_fetch_assoc($r)) {
    echo $x['Field'] . ':' . $x['Type'] . "\n";
}
