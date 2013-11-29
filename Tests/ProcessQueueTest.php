<?php

namespace SPW;

use SPW\ProcessWrapper;
use SPW\ProcessQueue;

class ProcessQueueTest
    extends \PHPUnit_Framework_TestCase
{
    /**
     * @group unittest
     */
    public function testParallelCmdQueue()
    {
        $processQueue = new ProcessQueue();

        // in sum 10 seconds processing time
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
    }
}
