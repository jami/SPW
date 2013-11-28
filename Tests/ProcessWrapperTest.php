<?php

namespace SPW;

use SPW\ProcessWrapper;
use SPW\ProcessQueue;

class ProcessWrapperTest
    extends \PHPUnit_Framework_TestCase
{
    /**
     * @group unittest
     */
    public function testInstance()
    {
        $spw = new ProcessWrapper('pwd');
        $this->assertInstanceOf('SPW\ProcessWrapper', $spw);
    }

    /**
     * @group unittest
     */
    public function testCwd()
    {
        $spw = new ProcessWrapper('pwd');
        $spw->start();
        $spw->wait();

        $this->assertEquals(
            array(getcwd()),
            $spw->getOutput()
        );
    }

    /**
     * @group unittest
     */
    public function testInputPass()
    {
        $spw = new ProcessWrapper('php');
        $spw->setInput('<?php echo "1234567"; ?>');
        $spw->start();
        $spw->wait();

        $this->assertEquals(
            array('1234567'),
            $spw->getOutput()
        );
    }

    /**
     * @group unittest
     */
    public function testProcessTime()
    {
        $spw = new ProcessWrapper('php');
        $spw->setInput('<?php sleep(1); echo "1234567"; sleep(1); ?>');
        $spw->start();
        $spw->wait();

        $this->assertEquals(
            2.0,
            $spw->getProcessTime(),
            'Processtime out of bounds',
            0.05
        );
    }

    /**
     * @group unittest
     */
    public function testExitCode()
    {
        $spw = new ProcessWrapper('php');
        $spw->setInput('<?php exit(1); ?>');
        $spw->start();
        $spw->wait();

        $this->assertEquals(
            1,
            $spw->getExitCode()
        );

        $spw->setInput('<?php exit(4); ?>');
        $spw->start();
        $spw->wait();

        $this->assertEquals(
            4,
            $spw->getExitCode()
        );

        $spw->setInput('<?php echo "foo"; ?>');
        $spw->start();
        $spw->wait();

        $this->assertEquals(
            0,
            $spw->getExitCode()
        );
    }
}