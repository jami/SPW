SPW - Simple process wrapper
============================

The process wrapper is a lightweight implementation for async processhandling.
Today (2013) it is still a pain in the arse to get php zts with pthreads support.
A simple workaround for that problem is to split threadlogic up to worker scripts and execute them parallel.

Example 1:

    $spw = new ProcessWrapper('php');
    $spw->setInput('<?php echo "1234567"; ?>');
    $spw->start();

    // do stuff

    // wait for process end
    $spw->wait();
    $outputBuffer = $spw->getOutput();

Example 2:


    $processQueue = new ProcessQueue();

    // in sum 10 seconds sequential processing time
    // parallel it should take as long as the duration of the slowest process

    $cmd1 = "sleep 2; echo 'cmd1 1';";
    $cmd2 = "sleep 4; echo 'cmd2 1';";
    $cmd3 = "sleep 2; echo 'cmd3 1'; sleep 2";

    $processQueue->attachProcess(new ProcessWrapper($cmd1));
    $processQueue->attachProcess(new ProcessWrapper($cmd2));
    $processQueue->attachProcess(new ProcessWrapper($cmd3));

    $processQueue->wait();

    $this->assertEquals(
        4.0,
        $processQueue->getProcessTime(),
        'This queue should take 4.0 seconds to process',
        0.1
    );

### Try the demo

The DownloadClient.php demo use the ProcessQueue to spawn N download worker processes. Each process downloads a part
of the target file. For this task it use curl and range headers. In the example below we download the 229MiB File install-amd64-minimal-20131010.iso
with 30 workers.

    cd ./SPW/Demo
    php DownloadClient.php "http://distfiles.gentoo.org/releases/amd64/autobuilds/current-iso/install-amd64-minimal-20131010.iso" 30
    > Attach worker process for range 0-7969177
    > Attach worker process for range 7969177-15938354
    > Attach worker process for range 15938354-23907531
    > Attach worker process for range 23907531-31876708
    > Attach worker process for range 31876708-39845885
    > Attach worker process for range 39845885-47815062
    > Attach worker process for range 47815062-55784239
    > Attach worker process for range 55784239-63753416
    > Attach worker process for range 63753416-71722593
    > Attach worker process for range 71722593-79691770
    > Attach worker process for range 79691770-87660947
    > Attach worker process for range 87660947-95630124
    > Attach worker process for range 95630124-103599301
    > Attach worker process for range 103599301-111568478
    > Attach worker process for range 111568478-119537655
    > Attach worker process for range 119537655-127506832
    > Attach worker process for range 127506832-135476009
    > Attach worker process for range 135476009-143445186
    > Attach worker process for range 143445186-151414363
    > Attach worker process for range 151414363-159383540
    > Attach worker process for range 159383540-167352717
    > Attach worker process for range 167352717-175321894
    > Attach worker process for range 175321894-183291071
    > Attach worker process for range 183291071-191260248
    > Attach worker process for range 191260248-199229425
    > Attach worker process for range 199229425-207198602
    > Attach worker process for range 207198602-215167779
    > Attach worker process for range 215167779-223136956
    > Attach worker process for range 223136956-231106133
    > Attach worker process for range 231106133-239075328
    > Downloaded 239075328 bytes in 39.25455904007s

Imho 39 seconds is really fast. If I download the file with wget i have to wait 7m49s

### Running tests

    cd ./SPW
    curl -sS https://getcomposer.org/installer | php
    php composer.phar install
    phpunit
