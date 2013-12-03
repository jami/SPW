<?php
/**
 * SPW - Simple process wrapper
 * This is a simple wrapper script for process handling
 *
 * @author Jan Michalowsky - <sejamich@gmail.com>
 */

namespace SPW;

class ProcessWrapper
{
    const PIPE_STDIN            = 0;
    const PIPE_STDOUT           = 1;
    const PIPE_STDERR           = 2;
    const STREAM_SELECT_TIMEOUT = 10;
    const BUFFER_TYPE_LINE      = 1;
    const BUFFER_TYPE_BINARY    = 2;

    protected $_proc        = null;
    protected $_command     = null;
    protected $_workingDir  = null;
    protected $_isRunning   = false;
    protected $_pid         = false;
    protected $_pipes       = null;
    protected $_exitCode    = 0;
    protected $_options     = array();
    protected $_default     = array();
    protected $_status      = array();
    protected $_processTime = 0;
    protected $_environment = array();
    protected $_bufferType  = 1;
    // input data
    protected $_input       = null;
    // result buffer
    protected $_output      = array();
    protected $_error       = array();
    // callbacks
    protected $_appendOutputCallback = null;
    protected $_appendErrorCallback  = null;
    protected $_startProcessCallback = null;
    protected $_stopProcessCallback  = null;

    /**
     * constructor
     * @param  string     $command
     * @param  string     $workingDirectory
     * @throws \Exception
     */
    public function __construct($command, $env = array(), $workingDirectory = null)
    {
        if (true === defined('PHP_WINDOWS_VERSION_BUILD')) {
            throw new \Exception('Process wrapper will not work under Windows');
        }

        $this->_command     = $command;
        $this->_environment = $env;
        $this->_workingDir  = $workingDirectory;
        $this->_bufferType  = self::BUFFER_TYPE_LINE;

        // set cwd because the default value can vary
        if (null === $this->_workingDir) {
            $this->_workingDir = getcwd();
        }

        $this->_default = array(
            'suppress_errors' => true,
            'binary_pipes'    => true
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
        $this->_error       = array();
        $this->_proc        = null;
        $this->_outputIndex = 0;
    }

    /**
     * start the process asynchronously (non-blocking)
     */
    public function start()
    {
        $this->reset();

        $descriptor = array(
            self::PIPE_STDIN  => array('pipe', 'r'),
            self::PIPE_STDOUT => array('pipe', 'w'),
            self::PIPE_STDERR => array('pipe', 'w')
        );

        $this->_processTime = microtime(true);

        $this->_proc = proc_open(
            $this->_command,
            $descriptor,
            $this->_pipes,
            $this->_workingDir,
            $this->_environment,
            $this->_options
        );

        if (false === is_resource($this->_proc)) {
            throw new \Exception("Can't open process {$this->_command}");
        }

        // get process informations
        $this->_status = proc_get_status($this->_proc);
        $this->_pid = $this->_status['pid'];
        // set pipes to non blocking
        stream_set_blocking($this->_pipes[self::PIPE_STDIN], 0);
        stream_set_blocking($this->_pipes[self::PIPE_STDOUT], 0);
        stream_set_blocking($this->_pipes[self::PIPE_STDERR], 0);

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

        // close stdout and stderr
        fclose($this->_pipes[self::PIPE_STDOUT]);
        fclose($this->_pipes[self::PIPE_STDERR]);

        $this->_exitCode = proc_close($this->_proc);

        if ($this->_stopProcessCallback != null
            && is_callable($this->_stopProcessCallback)) {
            $this->_stopProcessCallback->__invoke($this);
        }

        return $this->_exitCode;
    }

    /**
     * read stream
     */
    public function readStream()
    {
        $pipes = $this->getPipes();

        // get the stderr
        $buffer = stream_get_contents($pipes[self::PIPE_STDERR]);
        if (false == empty($buffer)) {
            $this->appendErrorBuffer($buffer);
        }

        // get the stdout
        $buffer = stream_get_contents($pipes[self::PIPE_STDOUT]);
        if (false == empty($buffer)) {
            $this->appendOutputBuffer($buffer);
        }

        // check for end of stream
        if (feof($pipes[self::PIPE_STDOUT])) {
            $this->stop();

            return false;
        }

        return true;
    }

    /**
     * wait until process finished
     */
    public function wait()
    {
        $writePipes  = null;
        $exceptPipes = null;
        $wait        = true;

        $readPipes   = array(
            $this->_pipes[self::PIPE_STDOUT],
            $this->_pipes[self::PIPE_STDERR]
        );

        // wait until stdout is closed
        while ($wait) {
            $selected = stream_select($readPipes, $writePipes, $exceptPipes, ProcessWrapper::STREAM_SELECT_TIMEOUT);

            if (false === $selected) {
                throw new \RuntimeException('Process interrupted');
            }

            if ($selected > 0) {
                $wait = $this->readStream();
            }
        }
    }

    /**
     * append error buffer
     * @param string $buffer
     */
    private function appendErrorBuffer($buffer)
    {
        $buffer = trim($buffer);
        $this->_error[] = $buffer;

        if ($this->_appendErrorCallback != null
            && is_callable($this->_appendErrorCallback)) {
            $this->_appendErrorCallback->__invoke($this, $buffer);
        }
    }

    /**
     * append output buffer
     * @param string $buffer
     */
    private function appendOutputBuffer($buffer)
    {
        if (self::BUFFER_TYPE_BINARY == $this->_bufferType) {
            $this->_output[0] += $buffer;
        } elseif (self::BUFFER_TYPE_LINE == $this->_bufferType) {
            $buffer = trim($buffer);
            $lineBuffer = explode(PHP_EOL, $buffer);
            $this->_output = array_merge($this->_output, $lineBuffer);
        }

        if ($this->_appendOutputCallback != null
            && is_callable($this->_appendOutputCallback)) {
            $this->_appendOutputCallback->__invoke($this, $buffer);
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
     * set buffer type
     * @param int $type
     */
    public function setBufferType($type)
    {
        if (in_array($type, array(self::BUFFER_TYPE_BINARY, self::BUFFER_TYPE_LINE))) {
            $this->_bufferType = $type;
        }
    }

    /**
     * get the output buffer
     */
    public function getOutput()
    {
        return $this->_output;
    }

    /**
     * get the output buffer
     */
    public function getError()
    {
        return $this->_error;
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
        $this->_status = proc_get_status($this->_proc);

        return $this->_status['running'];
    }

    /**
     * get the pid
     */
    public function getPid()
    {
        return $this->_pid;
    }

    /**
     *
     */
    public function setEnvironment(array $env)
    {
        $this->_environment = $env;
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
     * set options that should be available in $_ENV
     */
    public function setOptions(array $options)
    {
        $this->_options = array_merge($options, $this->_default);
    }

    /**
     * append output callback
     * @param Closure $callback
     */
    public function setAppendOutputCallback(\Closure $callback)
    {
        $this->_appendOutputCallback = $callback;
    }

    /**
     * append error callback
     * @param Closure $callback
     */
    public function setAppendErrorCallback(\Closure $callback)
    {
        $this->_appendErrorCallback = $callback;
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
