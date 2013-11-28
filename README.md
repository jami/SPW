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

    // run 3 processes at the same time
    $processQueue = new ProcessQueue();

    $cmd1 = "sleep 2; echo 'cmd1 1'; sleep 2; echo 'cmd1 2';";
    $cmd2 = "sleep 4; echo 'cmd2 1'; sleep 4; echo 'cmd2 2';";
    $cmd3 = 'php -r \'for ($t = 0; $t < 10; $t++){ sleep(1); echo "foo {$t}\n"; }\'; echo "cmd3 1";';

    $processQueue->attachProcess(new ProcessWrapper($cmd1));
    $processQueue->attachProcess(new ProcessWrapper($cmd2));

    $p3 = new ProcessWrapper($cmd3);
    $p3->setAppendBufferCallback(
        function ($process, $buffer)
        {
            echo "Buffer appended {$buffer}" . PHP_EOL;
        }
    );

    $processQueue->attachProcess($p3);

    // do stuff

    $processQueue->wait();


### Running tests

    cd ./SPW
    curl -sS https://getcomposer.org/installer | php
    php composer.phar install
    phpunit
