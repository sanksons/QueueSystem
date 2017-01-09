<?php

require_once 'QueueSystem/QFactory.php';

try {
    
    
    $queueName = 'http://sqs.eu-central-1.amazonaws.com/677882075100/pocdmsqs';
    $queue = QueueSystem\QFactory::Initialize(array(
        'region' => 'eu-central-1',
    ));
    //$sqs = new SQS();
    $queue->declareQueue($queueName);
    $queue->subscribe("tmcallback");
} catch (Exception $ex) {
    echo $ex->getMessage();
}



function tmcallback($message) {
    sleep(1);
    //voila();
    return $message;
}