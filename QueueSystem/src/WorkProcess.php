<?php

namespace QueueSystem;

//require_once dirname(dirname(__FILE__)) . '/Message.php';

class WorkProcess extends \Jenner\SimpleFork\Process
{
	//Contains the message data received from Queue.
	//object of QueueSystem\Message
    private $message;
    //Callback to process message data.
    private $callback;
    //Action to be taken when job is complete.
    private $onJobDone;
    
    /**
     * Exposed method to set Message.
     * 
     * @param string|array $message
     * @return \QueueSystem\WorkProcess
     */
    public function setMessage(QueueSystem\Message $message) {
        $this->message = $message;
        return $this;
    } 
    
    /**
     * Exposed method to define callback function.
     * 
     * @param string|array $callback
     * @return \QueueSystem\WorkProcess
     */
     public function setcallback($callback) {
        $this->callback = $callback;
        return $this;
    }
    
   /**
    * Exposed method to define callback to be called on job process.
    * 
    * @param string|array $callback
    * @return \QueueSystem\WorkProcess
    */
    public function onJobDone($callback) {
        $this->onJobDone = $callback;
        return $this;
    }
    
    /**
     * This method is called through jenner library when the process execution is started.
     * 
     * {@inheritDoc}
     * @see \Jenner\SimpleFork\Process::run()
     */
    public function run()
    {
        try {
            $result = call_user_func($this->callback, $this->message);
            call_user_func($this->onJobDone, $this->message);
            return true;
        } catch (Exception $e) {
            echo "Exception[{$e->getCode()}]:  " . $e->getMessage();
            echo PHP_EOL;
        }
    }
}