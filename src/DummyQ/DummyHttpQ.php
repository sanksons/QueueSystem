<?php

namespace QueueSystem\DummyQ;

use QueueSystem\Utils;

class DummyHttpQ implements \QueueSystem\QueueInterface
{
    //Maximum messages that can be received in single call.
    const MAX_MSG_2_RECEIVE = 180;
    //Long Poll wait time.
    const DEF_POLL_TIME = 1; //seconds
    //Max Workers to be run in Parallel.
    const MAX_CHILDS = 100;
    //Error message for Queue not declared.
    const ERR_MSG_QNOT_DECLARED = 'You need to declare a Queue before publishing message, see: declareQueue().';
    const ERR_NO_MESSAGE_MSG = 'No Message Found.';
    const ERR_NO_MESSAGE_CODE = 100;
    
    //there are cases where SQS gives false alarm (0 messages), even though message exists in Queue.
    const FALSE_ALARM_TOLERANCE = 3;
    
    
    private $qURL;
    private $publishUrl;
    private $consumerUrl;
    private $deleteUrl;
    private $messages = array();
    
    private $workerPool = NULL;
    
    /**
     * @var Katzgrau\KLogger\Logger
     */
    public  $logger = NULL;
    
    public function __construct($options) {
        
        $ls = static::getLoggerSettings($options);
        $this->logger = \QueueSystem\Utils::getLogger($ls['filePath'], $ls['level'],$ls['fileName']);
        $this->logger->info('Creating SQS instance with following options:', $options);
        
        $clientOptions = array();
        if (!empty($options['client'])) {
            $this->publishUrl = $options['client']['publishUrl'];
            $this->consumerUrl = $options['client']['consumeUrl'];
            $this->deleteUrl = $options['client']['deleteUrl'];
        }
        //define max allowed workers.
        $maxWorkers = static::MAX_CHILDS;
        if (!empty($options['maxWorkers'])) {
            $maxWorkers = (int) $options['maxWorkers'];
        }
        $this->logger->info('Creating Worker Pool with worker count :', array($maxWorkers));
        $this->workerPool = new \QueueSystem\WorkerPool($maxWorkers);
    }
    
    public function declareQueue($queueName) {
        $this->qURL = $queueName;
        return $this;
    }
    
    public function publish($message) {
        $client = new \GuzzleHttp\Client();
        $response = $client->request('POST', $this->publishUrl);
        echo $response->getStatusCode();
    }
    
    public function subscribe($callback = NULL, $count = self::MAX_MSG_2_RECEIVE)
    {
        while (1) {
            try {
                $this->logger->info("[" . getmypid() . "] Parent");
                $this->logger->info("Receiving Messages, Max limit {$count}");
                $this->receiveMessages($count);
                $this->logger->info("Received ".count($this->messages)." Messages");
                //check for empty message list.
                if (!(empty($this->messages))) {
                    $this->processMessages($callback);
                    continue;
                }
                //no message
                if ($this->workerPool->isAnyJobPending()) {
                    $this->workerPool->wait(true, 3 * 1000000);
                    continue;
                }
                throw new \RuntimeException(static::ERR_NO_MESSAGE_MSG, static::ERR_NO_MESSAGE_CODE);
                //reset sleep timer.
            } catch (\Exception $e) {
                if ($e->getCode() != static::ERR_NO_MESSAGE_CODE) {
                    $exceptionData = array(
                        'ErrMsg' => $e->getMessage(),
                        'ErrCode' => $e->getCode(),
                    );
                    $this->logger->critical('ERR: Exception occurred in QueueSystem\SQS\subscribe()', $exceptionData);
                }
                $sleepTime = 1;
                $this->logger->info("Sleeping for {$sleepTime} seconds");
                sleep($sleepTime);
                continue;
            }
        }
    }
    
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
                    'body' => $message->body,
                    'messageId' => $message->id,
                    'meta' => array('ReceiptHandle' => $message->id)
                ));
                $workProcess = new \QueueSystem\WorkProcess();
                $workProcess->setcallback($callback)
                ->setMessage($msg)
                ->onJobDone(array($this, 'markDeleted'))
                ->setLogger($this->logger);
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
    
    public function markDeleted(\QueueSystem\Message $message)
    {
        $meta = $message->getMetaInfo();
        $receiptHandle = "";
        if (isset($meta['ReceiptHandle'])) {
            $receiptHandle = $meta['ReceiptHandle'];
        }
        $client = new \GuzzleHttp\Client();
        $response = $client->request('GET', $this->deleteUrl);
        if ($response->getStatusCode() != 200) {
            throw new Exception("Failed to delete message");
        }
        return true;
    }
    
    
    
    
    private function receiveMessages($maxCount)
    {
        //NOTE: Bcoz SQS does not support more than 10 messages in single call.
        $sqsFetchLimit = ($maxCount >= 10) ? 10 : $maxCount;
        $queuedMsgCount = count($this->messages);
        $tolerance = 0;
    
        while ($queuedMsgCount < $maxCount) {
            $fetchLimit = $sqsFetchLimit;
            if (($maxCount - $queuedMsgCount) < $sqsFetchLimit) {
                $fetchLimit = $maxCount - $queuedMsgCount;
            }
            $client = new \GuzzleHttp\Client();
            $response = $client->request('GET', $this->consumerUrl);
            if ($response->getStatusCode() != 200) {
                throw new Exception("Failed to fetch messages"); 
            }
            $body = $response->getBody();
            $parsedBody = json_decode($body);
            $tmpMessages = $parsedBody;
            $this->messages = array_merge($this->messages, $tmpMessages);
            $queuedMsgCount = count($this->messages);
        }
    }
    
    private static function getLoggerSettings($options = array()) {
        $ret = array(
            'filePath' =>  Utils::DEF_PATH,
            'level' =>     Utils::DEF_LEVEL,
            'fileName' =>  Utils::DEF_FILE_NAME,
        );
        foreach($ret as $key => $val) {
            if (!empty($options['logger'][$key])) {
                $ret[$key] = $options['logger'][$key];
            }
        }
        return $ret;
    }
    
    
}