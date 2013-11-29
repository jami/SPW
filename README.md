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

### Running tests

    cd ./SPW
    curl -sS https://getcomposer.org/installer | php
    php composer.phar install
    phpunit
