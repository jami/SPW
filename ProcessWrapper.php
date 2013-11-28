<?php
/**
 * SPW - Simple process wrapper
 * This is a simple wrapper script for process handling
 * @author Jan Michalowsky - <sejamich@gmail.com>
 */

namespace SPW;

class ProcessWrapper
{
    const PIPE_STDIN            = 0;
    const PIPE_STDOUT           = 1;
    const PIPE_STDERR           = 2;
    const STREAM_SELECT_TIMEOUT = 10;

    protected $_proc        = null;
    protected $_command     = null;
    protected $_workingDir  = null;
    protected $_isRunning   = false;
    protected $_pid         = false;
    protected $_pipes       = null;
    protected $_descriptor  = null;
    protected $_exitCode    = 0;
    protected $_options     = array();
    protected $_default     = array();
    protected $_output      = array();
    protected $_input       = null;
    protected $_status      = array();
    protected $_outputIndex = 0;
    protected $_processTime = 0;

    protected $_appendBufferCallback = null;
    protected $_startProcessCallback = null;
    protected $_stopProcessCallback  = null;

    /**
     * constructor
     * @param string $command
     * @param string $workingDirectory
     * @throws \Exception
     */
    public function __construct($command, $workingDirectory = null)
    {
        if (true === defined('PHP_WINDOWS_VERSION_BUILD')) {
            throw new \Exception('Process wrapper will not work under Windows');
        }

        $this->_command = $command;
        $this->_workingDir = $workingDirectory;

        // set cwd because the default value can vary
        if (null === $this->_workingDir) {
            $this->_workingDir = getcwd();
        }

        // stderr got 'a' cause of a possible buffer overrun block on 'w'
        $this->_descriptor = array(
            self::PIPE_STDIN  => array('pipe', 'r'),
            self::PIPE_STDOUT => array('pipe', 'w'),
            self::PIPE_STDERR => array('file', 'php://stderr', 'a')
        );

        $this->_default = array(
            'suppress_errors' => true,
            'binary_pipes' => true
        );

        $this->_options = $this->_default;
    }

    /**
     * destructor
     */
    public function __destruct()
    {
        $this->stop();
    }

    /**
     * reset vars
     */
    protected function reset()
    {
        $this->_isRunning   = false;
        $this->_output      = array();
        $this->_proc        = null;
        $this->_outputIndex = 0;
    }

    /**
     * start the process asynchronously (non-blocking)
     * @param array $env
     */
    public function start($env = array())
    {
        $this->reset();

        $this->_processTime = microtime(true);

        $this->_proc = proc_open(
            $this->_command,
            $this->_descriptor,
            $this->_pipes,
            $this->_workingDir,
            $env,
            $this->_options
        );

        if (false === is_resource($this->_proc)) {
            throw new \Exception("Can't open process {$this->_command}");
        }

        $this->_status = proc_get_status($this->_proc);
        $this->_pid = $this->_status['pid'];

        stream_set_blocking($this->_pipes[self::PIPE_STDIN], 0);
        stream_set_blocking($this->_pipes[self::PIPE_STDOUT], 0);

        // pass input
        if (is_string($this->_input)
            && false == empty($this->_input)) {
            fwrite($this->_pipes[self::PIPE_STDIN], $this->_input);
            fclose($this->_pipes[self::PIPE_STDIN]);
        }

        $this->_isRunning = true;

        if ($this->_startProcessCallback != null
            && is_callable($this->_startProcessCallback)) {
            $this->_startProcessCallback->__invoke($this);
        }
    }

    /**
     * stop running process
     */
    public function stop()
    {
        if (false === $this->_isRunning) {
            return 0;
        }

        $this->_isRunning = false;
        $this->_processTime = microtime(true) - $this->_processTime;

        // close stdin
        if (null === $this->_input) {
            fclose($this->_pipes[self::PIPE_STDIN]);
        }

        // close stdout
        fclose($this->_pipes[self::PIPE_STDOUT]);

        $this->_exitCode = proc_close($this->_proc);

        if ($this->_stopProcessCallback != null
            && is_callable($this->_stopProcessCallback)) {
            $this->_stopProcessCallback->__invoke($this);
        }

        return $this->_exitCode;
    }

    /**
     * append buffer to output buffer
     * @param string $buffer
     */
    public function appendBuffer($buffer)
    {
        $buffer = trim($buffer);
        $this->_output[] = $buffer;

        if ($this->_appendBufferCallback != null
            && is_callable($this->_appendBufferCallback)) {
            $this->_appendBufferCallback->__invoke($this, $buffer);
        }
    }

    /**
     * set the input that is passed to stdin 0
     * @param string $input
     */
    public function setInput($input)
    {
        $this->_input = $input;
    }

    /**
     * get the output buffer
     */
    public function getOutput()
    {
        return $this->_output;
    }

    /**
     * get exit code
     */
    public function getExitCode()
    {
        return $this->_exitCode;
    }

    /**
     * get assigned pipes
     */
    public function getPipes()
    {
        return $this->_pipes;
    }

    /**
     * is the process still running
     */
    public function isRunning()
    {
        return $this->_isRunning;
    }

    /**
     * get the pid
     */
    public function getPid()
    {
        return $this->_pid;
    }

    /**
     * get the processing time in float seconds
     * @return float
     */
    public function getProcessTime()
    {
        return $this->_processTime;
    }

    /**
     * read stream
     */
    public function readStream()
    {
        $pipes = $this->getPipes();

        // get the stdout
        $buffer = stream_get_contents($pipes[1]);
        if (false == empty($buffer)) {
            $this->appendBuffer($buffer);
        }

        // check for end of stream
        if (feof($pipes[1])) {
            $this->stop();
            return false;
        }

        return true;
    }

    /**
     * returns the latest output buffer
     * @return array
     */
    public function getLatestOutputBuffer($rewind = false)
    {
        $lastIndex   = $this->_outputIndex;
        $outputSlice = array();
        if (count($this->_output) > ($lastIndex + 1)) {
            $outputSlice = array_slice($this->_output, $lastIndex);
        }

        if (false == $rewind) {
            $this->_outputIndex = count($this->_output) - 1;
        }

        return $outputSlice;
    }

    /**
     * wait until process finished
     */
    public function wait()
    {
        $readPipes  = array($this->_pipes[self::PIPE_STDOUT]);
        $writePipes = null;
        $errorPipes = null;
        $wait       = true;

        while ($wait) {
            $selected = stream_select($readPipes, $writePipes, $errorPipes, ProcessWrapper::STREAM_SELECT_TIMEOUT);

            if (false === $selected) {
                throw new \RuntimeException('Process interrupted');
            }

            if ($selected > 0) {
                $wait = $this->readStream();
            }
        }
    }

    /**
     * set options that should be available in $_ENV
     */
    public function setOptions(array $options)
    {
        $this->_options = array_merge(options, $this->_default);
    }

    /**
     * append buffer callback
     * @param Closure $callback
     */
    public function setAppendBufferCallback(\Closure $callback)
    {
        $this->_appendBufferCallback = $callback;
    }

    /**
     * start process callback
     * @param Closure $callback
     */
    public function setStartProcessCallback(\Closure $callback)
    {
        $this->_startProcessCallback = $callback;
    }

    /**
     * stop process callback
     * @param Closure $callback
     */
    public function setStopProcessCallback(\Closure $callback)
    {
        $this->_startProcessCallback = $callback;
    }
}
