<?php

namespace SPW;

use SPW\ProcessWrapper;

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
    public function testEnvPass()
    {
        $spw = new ProcessWrapper('php');
        $env = array(
            'var1' => 42,
            'var2' => (string) serialize(array(1,2,3))
        );

        $spw->setEnvironment($env);

        $spw->setInput('<?php echo $_SERVER["var1"] . "," . $_SERVER["var2"]; ?>');
        $spw->start();
        $spw->wait();

        $buffer = $spw->getOutput();
        $this->assertEquals("{$env['var1']},{$env['var2']}", $buffer[0]);
    }

    /**
     * @group unittest
     */
    public function testProcessLineBufferOutput()
    {
        $spw = new ProcessWrapper('php');
        $spw->setBufferType(ProcessWrapper::BUFFER_TYPE_LINE);
        $spw->setInput('<?php echo "abc" . PHP_EOL; sleep(1); echo "def" . PHP_EOL; ?>');

        $spw->start();
        $spw->wait();
        $this->assertEquals(
            array(
                'abc',
                'def'
            ),
            $spw->getOutput()
        );
    }

    /**
     * @group unittest
     */
    public function testProcessErrorBuffer()
    {
        $spw = new ProcessWrapper('php');
        $spw->setInput('<?php foobarfoobarfoooo("meh"); ?>');
        $spw->start();
        $spw->wait();

        $this->assertEquals(
            array('PHP Fatal error:  Call to undefined function foobarfoobarfoooo() in - on line 1'),
            $spw->getError()
        );
    }

    /**
     * @group unittest
     */
    public function testProcessErrorBufferCallback()
    {
        $result = '';

        $spw = new ProcessWrapper('php');
        $spw->setInput('<?php foobarfoobarfoooo("meh"); ?>');

        // set callback
        $spw->setAppendErrorCallback(function ($process, $error) use (&$result) {
            $result = $error;
        });

        $spw->start();
        $spw->wait();

        $this->assertEquals(
            'PHP Fatal error:  Call to undefined function foobarfoobarfoooo() in - on line 1',
            $result
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
