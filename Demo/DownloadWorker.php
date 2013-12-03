<?php

$range  = $_SERVER['range'];
$url    = $_SERVER['url'];
$file   = $_SERVER['tmpfile'];

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RANGE, $range);
curl_setopt($ch, CURLOPT_BINARYTRANSFER, 1);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
$data = curl_exec($ch);

curl_close($ch);
file_put_contents($file, $data);
