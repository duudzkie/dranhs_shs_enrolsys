<?php
$c = mysqli_connect('localhost','root','','dranhswin');
if(!$c){die('connect fail');}
$r = mysqli_query($c, 'SHOW CREATE TABLE students');
$x = mysqli_fetch_assoc($r);
echo $x['Create Table'];
