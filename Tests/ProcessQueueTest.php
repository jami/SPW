<?php

namespace SPW;

use SPW\ProcessWrapper;
use SPW\ProcessQueue;

class ProcessQueueTest
    extends \PHPUnit_Framework_TestCase
{

    public function testQueueCmd()
    {
        $processQueue = new ProcessQueue();

        $cmd1 = "sleep 2; echo 'cmd1 1'; sleep 2; echo 'cmd1 2';";
        $cmd2 = "sleep 4; echo 'cmd2 1'; sleep 4; echo 'cmd2 2';";
        $cmd3 = 'php -r \'for ($t=0;$t<10;$t++){ sleep(1);echo "foo {$t}\n"; }\'; echo "cmd3 1";';
        $cmd4 = 'php -r \'for ($t=0;$t<5;$t++){ sleep(2);echo "bar {$t}\n"; }\'; echo "cmd4 1";';

        $processQueue->attachProcess(new ProcessWrapper($cmd1));
        $processQueue->attachProcess(new ProcessWrapper($cmd2));

        $p3 = new ProcessWrapper($cmd3);
        $p3->setAppendBufferCallback(
            function ($process, $buffer)
            {
                echo "CB:APPEND:{$buffer}\n";
            }
        );

        $processQueue->attachProcess($p3);

        $p4 = new ProcessWrapper($cmd4);
        $p4->setAppendBufferCallback(
            function ($process, $buffer)
            {
                echo "CB:APPEND:{$buffer}\n";
            }
        );

        $processQueue->attachProcess($p4);



        $processQueue->wait();
    }
}
