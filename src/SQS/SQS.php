<?php
namespace QueueSystem\SQS;

use QueueSystem\Utils;

/**
 * SQS Implementation for Queue System.
 *
 * @author Sankalp Bhatt
 *        
 */
class SQS implements \QueueSystem\QueueInterface
{
    // Default region to use when none is specified.
    const DEF_REGION = "eu-central-1";
    // Long Poll wait time.
    const DEF_POLL_TIME = 1;
    // Maximum messages that can be received in single call.
    const MAX_MSG_2_RECEIVE = 5;
    // Max Workers to be run in Parallel.
    const MAX_CHILDS = 2;
    // Error message for Queue not declared.
    const ERR_MSG_QNOT_DECLARED = 'You need to declare a Queue before publishing message, see: declareQueue().';
    const ERR_NO_MESSAGE_MSG = 'No Message Found.';
    const ERR_NO_MESSAGE_CODE = 100;
    // there are cases where SQS gives false alarm (0 messages), even though message exists in Queue.
    const FALSE_ALARM_TOLERANCE = 3;
    
    const MICRO_SECONDS = 1000000;
    
    /**
     * Stores connection Client to AWS.
     * 
     * @var Object of Aws\Sqs\SqsClient
     */
    private $client;
    
    /**
     * URL of SQS Queue.
     * 
     * @var string
     */
    private $qURL;
    
    /**
     * Queue of Messages to be processed.
     * 
     * @var array
     */
    private $messages = array();
    
    /**
     * @var (object) SleepTimer
     */
    private $sleepTimer = NULL;
    
    /**
     * @var (object) \QueueSystem\WorkerPool
     */
    private $workerPool = NULL;

    /**
     * Do we need to log stats of calls made to SQS.
     * 
     * @var boolean
     */
    protected $stats = false;

    /**
     * Http Timeout during AWS call [seconds].
     *
     * @var integer
     */
    protected $httpTimeout = 5;
    
    /**
     *
     * @var Katzgrau\KLogger\Logger
     */
    public $logger = NULL;

    /**
     * Expects SQS related options to be passed as Params.
     *
     * $options['client']['region'] : Region to which the Q belongs.
     * $options['client']['stats'] : LOG Http Call transfer Stats.
     *
     * $options['pollSettings'] : Settings related to polling.
     * - formula : poll formula to use.
     * - variant : poll formula variant.
     *
     * $options['logger'] : settings for logger.
     * - filePath : path to write log file.
     * - level : (string) log level to be used.
     * - fileName : filename without extension.
     *
     * $options['maxWorkers'] : max. allowed parallel workers.
     *
     * @param array $options            
     */
    public function __construct($options = array())
    {
        //Initialize Logger.
        $this->initLogger($options);
        
        //Initialize SQS Client. 
        $this->initSQSClient($options);
        
        //Initialize SleepTimer.
        $this->initSleepTimer($options);
        
        //Calculate Max Workers and Initialize WorkerPool.
        $this->initWorkerPool($options);
    }
    
    /**
     * Initialize worker pool.
     * 
     * @param array $options
     */
    protected function initWorkerPool($options = array())
    {
        $maxWorkers = static::MAX_CHILDS;
        if (! empty($options['maxWorkers'])) {
            $maxWorkers = (int) $options['maxWorkers'];
        }
        $this->logger->info('Creating Worker Pool with worker count :', array(
            $maxWorkers
        ));
        $this->workerPool = new \QueueSystem\WorkerPool($maxWorkers);
    }
    
    /**
     * Initialize sleep Timer.
     * 
     * @param array $options
     */
    protected function initSleepTimer($options = array())
    {
        $pollSettings = NULL;
        if (! empty($options['pollSettings']['formula']) && ! empty($options['pollSettings']['variant'])) {
            $this->logger->info('Using following Poll Settings:', array(
                $pollSettings
            ));
            $this->sleepTimer = new SleepTimer(
                $options['pollSettings']['formula'], 
                $options['pollSettings']['variant']
            );
        } else {
            $this->sleepTimer = new SleepTimer();
        }
    }

    /**
     * Initialize logger object.
     * 
     * @param array $options : options to use.
     */
    protected function initLogger($options = array())
    {
        $ls = Utils::getLoggerSettings($options);
        $this->logger = \QueueSystem\Utils::getLogger($ls['filePath'], $ls['level'], $ls['fileName']);
        $this->logger->info('Creating SQS instance with following options:', $options);
    }
    
    /**
     * Initialize SQS Client.
     * 
     * @param array $options
     */
    protected function initSQSClient($options = array()) 
    {
        $clientOptions = array();
        if (! empty($options['client'])) {
            $clientOptions = $options['client'];
        }
        $this->client = $this->getClient($clientOptions);
    }

    /**
     * Create a Client connection to SQS service.
     *
     * @param array $options            
     * @return object Aws\Sqs\SqsClient
     */
    protected function getClient($options = array())
    {
        if (empty($options['region'])) {
            $options['region'] = static::DEF_REGION;
        }
        $client = \Aws\Sqs\SqsClient::factory(array(
            'region' => static::DEF_REGION
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
     * @return boolean Exception
     */
    public function publish($payload)
    {
        if (! $this->isQueueDeclared()) {
            throw new \Exception(static::ERR_MSG_QNOT_DECLARED);
        }
        $this->logger->info("[pid:{" . getmypid() . "}] Publishing Message.");
        $this->logger->debug("Published Message Content:  {$payload}");
        $result = $this->client->sendMessage(array(
            'QueueUrl' => $this->qURL,
            'MessageBody' => $payload
        ));
        $this->logTransferStats($result, 'Publish Request:');
        return true;
    }

    /**
     * Subscribe for messages to Queue.
     *
     * @param array|string $callback
     *            : Callback to be called on each message.
     * @param int $count
     *            : Messages to receive in single call.
     *            
     * @throws Exception
     */
    public function subscribe($callback = NULL, $count = self::MAX_MSG_2_RECEIVE, $waitTime = self::DEF_POLL_TIME)
    {
        while (1) {
            try {
                $this->logger->info("[" . getmypid() . "] Parent");
                $this->logger->info("Receiving Messages, Max limit {$count}");
                $this->receiveMessages($count, $waitTime);
                $this->logger->info("Received " . count($this->messages) . " Messages");
                // check for empty message list.
                if (! (empty($this->messages))) {
                    $this->sleepTimer->resetSleepTimer();
                    $this->processMessages($callback);
                    continue;
                }
                // no message
                if ($this->workerPool->isAnyJobPending()) {
                    $this->workerPool->wait(true, 3 * self::MICRO_SECONDS);
                    continue;
                }
                throw new \RuntimeException(static::ERR_NO_MESSAGE_MSG, static::ERR_NO_MESSAGE_CODE);
                // reset sleep timer.
            } catch (\Exception $e) {
                if ($e->getCode() != static::ERR_NO_MESSAGE_CODE) {
                    $exceptionData = array(
                        'ErrMsg' => $e->getMessage(),
                        'ErrCode' => $e->getCode()
                    );
                    $this->logger->critical('ERR: Exception occurred in QueueSystem\SQS\subscribe()', $exceptionData);
                }
                $sleepTime = $this->sleepTimer->getSleepTime();
                $this->logger->info("Sleeping for {$sleepTime} seconds");
                sleep($sleepTime);
                continue;
            }
        }
    }

    /**
     * Check if Queue is already declared or not
     * 
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
            // no messages to process, simply return.
            return;
        }
        if ((! is_callable($callback))) {
            throw new \RuntimeException('Not a callable callback.');
        }
        // create a child process pool.
        $pool = $this->workerPool;
        foreach ($this->messages as $message) {
            try {
                $msg = new \QueueSystem\Message(array(
                    'body' => $message['Body'],
                    'messageId' => $message['MessageId'],
                    'meta' => array(
                        'ReceiptHandle' => $message['ReceiptHandle']
                    )
                ));
                $workProcess = new \QueueSystem\WorkProcess();
                $workProcess->setcallback($callback)
                    ->setMessage($msg)
                    ->beforeJobStart(array(
                    $this,
                    'markDeleted'
                ))
                    ->setLogger($this->logger);
                $pool->execute($workProcess);
            } catch (\Exception $e) {
                echo 'process message failed;';
                echo $e->getMessage();
            }
        }
        // reset messages.
        $this->messages = array();
        $pool->wait(true, 3 * self::MICRO_SECONDS);
    }

    /**
     * Mark the message Deleted, so that it is not returned again.
     * $message['ReceiptHandle'] : Message Identifier to be used in delete call.
     *
     * @todo : Check for responses from delete message call.
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
        $result = $this->client->deleteMessage(array(
            'QueueUrl' => $this->qURL,
            'ReceiptHandle' => $receiptHandle
        ));
        $this->logTransferStats($result, 'Delete Request:');
        
        return true;
    }

    /**
     * Try to fetch messages from SQS.
     *
     * @param int $maxCount            
     * @return array
     */
    private function receiveMessages($maxCount, $waitTime)
    {
        try {
            // NOTE: Bcoz SQS does not support more than 10 messages in single call.
            $sqsFetchLimit = ($maxCount >= 10) ? 10 : $maxCount;
            $queuedMsgCount = count($this->messages);
            $tolerance = 0;
            
            while ($queuedMsgCount < $maxCount) {
                $fetchLimit = $sqsFetchLimit;
                if (($maxCount - $queuedMsgCount) < $sqsFetchLimit) {
                    $fetchLimit = $maxCount - $queuedMsgCount;
                }
                $result = $this->client->receiveMessage(array(
                    'QueueUrl' => $this->qURL,
                    'MaxNumberOfMessages' => $fetchLimit,
                    'WaitTimeSeconds' => $waitTime
                ));
                
                $this->logTransferStats($result, 'Receive Request:'); // log stats
                $tmpMessages = $result->get('Messages');
                if (empty($tmpMessages)) {
                    if ($tolerance < static::FALSE_ALARM_TOLERANCE) {
                        $tolerance ++;
                        continue;
                    }
                    break;
                }
                $this->messages = array_merge($this->messages, $tmpMessages);
                $queuedMsgCount = count($this->messages);
            }
        } catch (\Exception $e) {
            $msg = "SQS->receiveMessages(): ". $e->getMessage();
            throw new \Exception($msg);
        }
    }

    /**
     * Check if Stats enabled.
     *
     * @return boolean
     */
    protected function isStatsEnabled()
    {
        if ($this->stats) {
            return true;
        }
        return false;
    }

    /**
     * Log Stats of Http Transfer.
     *
     * @param array  $result
     * @param string $type            
     */
    protected function logTransferStats($result = null, $type = '')
    {
        if (empty($result['@metadata']['transferStats']) || (! $this->isStatsEnabled())) {
            return;
        }
        $this->logger->debug("STATS {$type}:", $result['@metadata']['transferStats']);
    }
}
