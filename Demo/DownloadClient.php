<?php

namespace DownloadClient;

require_once('../ProcessWrapper.php');
require_once('../ProcessQueue.php');

use SPW\ProcessWrapper;
use SPW\ProcessQueue;

if (count($argv) < 3) {
    echo "Usage:" . PHP_EOL;
    echo "php DownloadClient.php [url] [numworkers]" . PHP_EOL;
}

$url       = $argv[1];
$numWorker = (int)$argv[2];

//get header information
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL,            $url);
curl_setopt($ch, CURLOPT_HEADER,         true);
curl_setopt($ch, CURLOPT_NOBODY,         true);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT,        15);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
$header = curl_exec($ch);
curl_close($ch);

// get content size
$match = null;
$contentLength = 0;
if (preg_match('/Content\-Length:\s(\d+)/', $header, $match)) {
    $contentLength = $match[1];
}

if (0 == $contentLength) {
    throw new \RuntimeException('Header request returned without content length');
}

echo "Downloading {$contentLength} bytes from {$url} with {$numWorker} workers" . PHP_EOL;

$processQueue = new ProcessQueue();
$partLength   = floor($contentLength / $numWorker);
$worker       = file_get_contents('DownloadWorker.php');
$tmpFiles     = array();

for ($i = 0; $i < $numWorker; $i++) {
    $startOffset = $i * $partLength;
    $size = $partLength - 1;

    if (($i + 1) == $numWorker) {
        $size = $contentLength - $startOffset - 1;
    }

    $range      = $startOffset . '-' . ($startOffset + $size);
    $filename   = sprintf('tmp_%02d.part', $i);
    $tmpFiles[] = $filename;

    $process  = new ProcessWrapper('php');
    $process->setEnvironment(
        array(
            'range'      => $range,
            'tmpfile'    => $filename,
            'url'        => $url,
            'http_proxy' => $_SERVER['http_proxy']
        )
    );

    $process->setInput($worker);
    $processQueue->attachProcess($process);
    echo "Attach worker process for range {$range}" . PHP_EOL;
}

$processQueue->wait();
$time = $processQueue->getProcessTime();

echo "Downloaded {$contentLength} bytes in {$time}s\n";

// merging tmp files
$outFile = basename($url);
$fh = fopen($outFile, "w");

foreach ($tmpFiles as $tmp) {
    fwrite($fh, file_get_contents($tmp));
    unlink($tmp);
}

fclose($fh);

