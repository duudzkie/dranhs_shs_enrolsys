<?php
$c = mysqli_connect('localhost','root','','dranhswin');
if (!$c) { die('connect fail'); }
$r = mysqli_query($c, 'SHOW COLUMNS FROM students');
while ($x = mysqli_fetch_assoc($r)) {
    echo $x['Field'] . ':' . $x['Type'] . "\n";
}
