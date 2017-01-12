<?php
namespace QueueSystem\SQS;

use QueueSystem\Utils;

/**
 *  SQS Q Implementation.
 */
class SQS implements \QueueSystem\QueueInterface
{
    //Default region to use when none is specified.
    const DEF_REGION = "eu-central-1";
    //Maximum messages that can be received in single call.
    const MAX_MSG_2_RECEIVE = 5;
    //Long Poll wait time.
    const DEF_POLL_TIME = 1; //seconds
    //Max Workers to be run in Parallel.
    const MAX_CHILDS = 2;
    //Error message for Queue not declared.
    const ERR_MSG_QNOT_DECLARED = 'You need to declare a Queue before publishing message, see: declareQueue().';
    const ERR_NO_MESSAGE_MSG = 'No Message Found.';
    const ERR_NO_MESSAGE_CODE = 100;
    
    //Object of Aws\Sqs\SqsClient
    //Stores connection Client to AWS. 
    private $client;
    //URL of SQS queue
    private $qURL;
    //Contains Queue of messages to be processed
    private $messages = array();
    //Object of SleepTimer.
    private $sleepTimer = NULL;
    private $workerPool = NULL;

    /**
     * @var Katzgrau\KLogger\Logger
     */
    public  $logger = NULL;

    /**
     * Expects SQS related options to be passed as Params.
     * $options['client']['region'] : Region to which the Q belongs.
     *  
     * $options['pollSettings'] : Settings related to polling.
     *  - formula : poll formula to use.
     *  - variant : poll formula variant.
     *  
     * $options['maxWorkers'] : max. allowed parallel workers.  
     * 
     * @param array $options
     */
    public function __construct($options = array())
    {
        //initiate logger
        //@todo: bring path and level from outside.
        $this->logger = \QueueSystem\Utils::getLogger();
        $this->logger->info('Creating SQS instance with following options:', $options);
        
        $clientOptions = array();
        if (!empty($options['client'])) {
            $clientOptions = $options['client'];
        }
        $this->client = $this->getClient($clientOptions);
        
        //declare SleepTimer object
        $pollSettings = NULL;
        if (! empty($options['pollSettings']['formula']) &&
                         ! empty($options['pollSettings']['variant'])) {
            $this->sleepTimer = new SleepTimer($options['pollSettings']['formula'], 
                            $options['pollSettings']['variant']);
        } else {
            $this->sleepTimer = new SleepTimer();
        }
        //define max allowed workers.
        $maxWorkers = self::MAX_CHILDS;
        if (!empty($options['maxWorkers'])) {
            $maxWorkers = (int) $options['maxWorkers'];
        }
        $this->workerPool = new \QueueSystem\WorkerPool($maxWorkers);
        
        
    }

    /**
     * Create a Client connection to SQS service.
     * 
     * @param array $options
     * @return object Aws\Sqs\SqsClient
     */
    private function getClient($options = array())
    {
        if (empty($options['region'])) {
            $options['region'] = self::DEF_REGION;
        }
        $client = \Aws\Sqs\SqsClient::factory(array(
                'region' => self::DEF_REGION,
        ));
        return $client;
    }

    /**
     * Specify SQS Queue URL.
     * 
     * @param string $queueName
     * @return object SQS
     */
    public function declareQueue($queueName)
    {
        $this->qURL = $queueName;
        return $this;
    }

    /**
     * Publish Message to Queue.
     * 
     * @param string $message
     * @return boolean| Exception
     */
    public function publish($payload)
    {
        if (!$this->isQueueDeclared()) {
            throw new \Exception(
            self::ERR_MSG_QNOT_DECLARED
            );
        }
        $this->client->sendMessage(array(
            'QueueUrl' => $this->qURL,
            'MessageBody' => $payload,
        ));
        return true;
    }

    /**
     * Subscribe for messages to Queue.
     * 
     * @param array|string $callback : Callback to be called on each message.  
     * @param int $count : Messages to receive in single call.
     * 
     * @throws Exception
     */
    public function subscribe($callback = NULL, $count = self::MAX_MSG_2_RECEIVE)
    {
        while (1) {
            try {
                echo "[" . getmypid() . "] Parent" . PHP_EOL;
                $this->receiveMessages($count);
                //check for empty message list.
                if (!(empty($this->messages))) {
                	$this->sleepTimer->resetSleepTimer();
                	$this->processMessages($callback);
                	continue;
                }
                //no message
                if ($this->workerPool->isAnyJobPending()) {
                	$this->workerPool->wait(true, 3 * 1000000);
                	continue;
                }
                throw new \RuntimeException(self::ERR_NO_MESSAGE_MSG, self::ERR_NO_MESSAGE_CODE);
                //reset sleep timer.
            } catch (\Exception $e) {
            	if ($e->getCode() != self::ERR_NO_MESSAGE_CODE) {
            		//@todo: log eror before continuing.
            		echo $e->getMessage();
            	}
                $sleepTime = $this->sleepTimer->getSleepTime();
                sleep($sleepTime);
                continue;
            }
        }
    }

    /**
     * Check if Queue is already declared or not
     * @return boolean
     */
    private function isQueueDeclared()
    {
        if (empty($this->qURL)) {
            return false;
        }
        return true;
    }

    /**
     * Process message data.
     * 
     * @param array $messages
     * @param callable $callback
     * @return boolean
     */
    private function processMessages($callback)
    {
        if (empty($this->messages)) {
            //no messages to process, simply return.
            return;
        }
        if ((!is_callable($callback))) {
            throw new \RuntimeException('Not a callable callback.');
        }
        //create a child process pool.
        $pool = $this->workerPool;
        foreach ($this->messages as $message) {
            try {
                $msg = new \QueueSystem\Message(array(
                    'body' => $message['Body'],
                    'messageId' => $message['MessageId'],
                    'meta' => array('ReceiptHandle' => $message['ReceiptHandle'])
                ));
                $workProcess = new \QueueSystem\WorkProcess();
                $workProcess->setcallback($callback)
                    ->setMessage($msg)
                    ->onJobDone(array($this, 'markDeleted'));
                $pool->execute($workProcess);
            } catch (\Exception $e) {
            	echo 'process message failed;';
                echo $e->getMessage();
            }
        }
        //reset messages.
        $this->messages = array();
        $pool->wait(true, 3 * 1000000);
    }

    /**
     * Mark the message Deleted, so that it is not returned again.
     * $message['ReceiptHandle'] : Message Identifier to be used in delete call.
     * 
     * @todo: Check for responses from delete message call.
     * 
     * @param array $message
     * @return type
     */
    public function markDeleted(\QueueSystem\Message $message)
    {
        $meta = $message->getMetaInfo();
        $receiptHandle = "";
        if (isset($meta['ReceiptHandle'])) {
            $receiptHandle = $meta['ReceiptHandle'];
        }
        $this->client->deleteMessage(array(
            'QueueUrl' => $this->qURL,
            'ReceiptHandle' => $receiptHandle,
        ));
        return true;
    }

    /**
     * Try to fetch messages from SQS.
     * 
     * @param int $maxCount
     * @return array
     */
    private function receiveMessages($maxCount)
    {
        //NOTE: Bcoz SQS does not support more than 10 messages in single call.
        $sqsFetchLimit = ($maxCount >= 10) ? 10 : $maxCount;
        $queuedMsgCount = count($this->messages);
        while ($queuedMsgCount < $maxCount) {
            $fetchLimit = $sqsFetchLimit;
            if (($maxCount - $queuedMsgCount) < $sqsFetchLimit) {
                $fetchLimit = $maxCount - $queuedMsgCount;
            }
            $result = $this->client->receiveMessage(array(
                'QueueUrl' => $this->qURL,
                'MaxNumberOfMessages' => $fetchLimit,
                'WaitTimeSeconds' => self::DEF_POLL_TIME,
            ));
            $tmpMessages = $result->get('Messages');
            if (empty($tmpMessages)) {
                break;
            }
            $this->messages = array_merge($this->messages, $tmpMessages);
            $queuedMsgCount = count($this->messages);
        }
        
    }
}
