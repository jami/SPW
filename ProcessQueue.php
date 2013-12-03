<?php
/**
 * SPW - Simple process wrapper
 * This is a process queue. The queue contains all processes that run parallel
 *
 * @author Jan Michalowsky - <sejamich@gmail.com>
 */

namespace SPW;

use SPW\ProcessWrapper;

class ProcessQueue
{
    protected $_processTime    = 0;
    protected $_queue          = array();
    protected $_worker         = array();

    /**
     * attach process
     * @param ProcessWrapper $process
     */
    public function attachProcess(ProcessWrapper $process)
    {
        $this->_queue[] = $process;

        return $this;
    }

    /**
     * Get the process queue
     * @return array
     */
    public function getProcessQueue()
    {
        return $this->_queue;
    }

    /**
     * starts all processes in the queue and wait until they done
     */
    public function wait()
    {
        $writePipes = null;
        $errorPipes = null;

        $this->_processTime = microtime(true);

        // start all processes
        foreach ($this->_queue as $process) {
            $this->_worker[] = $process;
            $process->start();
        }

        // waiting loop
        while (count($this->_worker) > 0) {
            $readPipes = array();
            foreach ($this->_worker as $process) {
                $pipes       = $process->getPipes();
                $readPipes[] = $pipes[ProcessWrapper::PIPE_STDOUT];
                $readPipes[] = $pipes[ProcessWrapper::PIPE_STDERR];
            }

            // wait until readable pipe is selected
            $selected = stream_select($readPipes, $writePipes, $errorPipes, ProcessWrapper::STREAM_SELECT_TIMEOUT);

            if (false === $selected) {
                throw new \RuntimeException('Process interrupted');
            }

            if ($selected > 0) {
                foreach ($this->_worker as $index => $process) {
                    if (false === $this->_worker[$index]->readStream()) {
                        unset($this->_worker[$index]);
                    }
                }
            }
        }

        $this->_processTime = microtime(true) - $this->_processTime;
    }

    /**
     * get overall process time
     * @return float
     */
    public function getProcessTime()
    {
        return $this->_processTime;
    }
}
