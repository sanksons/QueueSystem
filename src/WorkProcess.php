<?php
namespace QueueSystem;

class WorkProcess extends \Jenner\SimpleFork\Process
{
    // Contains the message data received from Queue.
    // object of QueueSystem\Message
    private $message;
    // Callback to process message data.
    private $callback;
    // Action to be taken when job is complete.
    private $onJobDone;
    // Action to perform before job starts.
    private $beforeJobStart;

    private $logger;

    /**
     * Exposed method to set Message.
     *
     * @param string|array $message            
     * @return \QueueSystem\WorkProcess
     */
    public function setMessage(Message $message)
    {
        $this->message = $message;
        return $this;
    }

    /**
     * Exposed method to define callback function.
     *
     * @param string|array $callback            
     * @return \QueueSystem\WorkProcess
     */
    public function setcallback($callback)
    {
        $this->callback = $callback;
        return $this;
    }

    /**
     * Exposed method to define callback to be called on job process.
     *
     * @param string|array $callback            
     * @return \QueueSystem\WorkProcess
     */
    public function onJobDone($callback)
    {
        $this->onJobDone = $callback;
        return $this;
    }

    /**
     * Exposed method to define callback to be called before job process.
     *
     * @param string|array $callback            
     * @return \QueueSystem\WorkProcess
     */
    public function beforeJobStart($callback)
    {
        $this->beforeJobStart = $callback;
        return $this;
    }

    /**
     * Define logger object.
     *
     * @param unknown $logger            
     */
    public function setLogger($logger)
    {
        $this->logger = $logger;
    }

    /**
     * This method is called through jenner library when the process execution is started.
     *
     * {@inheritdoc}
     *
     * @see \Jenner\SimpleFork\Process::run()
     */
    public function run()
    {
        try {
            $this->logger->info('Processing message:', array(
                $this->message->getMessageId()
            ));
            //pre job start hook
            if (Utils::isCallable($this->beforeJobStart)) {
                call_user_func($this->beforeJobStart, $this->message);
            }
            //execute job
            if (Utils::isCallable($this->callback)) {
                call_user_func($this->callback, $this->message);
            }
            //job end hook
            if (Utils::isCallable($this->onJobDone)) {
                call_user_func($this->onJobDone, $this->message);
            }
            return true;
        } catch (\Exception $e) {
            $exceptionData = array(
                'ErrorMsg' => $e->getMessage(),
                'ErrorCode' => $e->getCode(),
                'MessagePayload' => $this->message->getBody()
            );
            $this->logger->critical("ERR: Exception occured in: \QueueSystem\WorkProcess::run()", $exceptionData);
        }
        return false;
    }
}
