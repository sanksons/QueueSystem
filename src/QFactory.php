<?php
namespace QueueSystem;

class QFactory
{

    const TYPE_SQS = "SQS";
    const TYPE_RABBITMQ = "RabbitMQ";
    const TYPE_DUMMYHTTPQ = "DummyHttpQ";

    public static function Initialize($options, $type = self::TYPE_SQS)
    {
        $client = NULL;
        switch ($type) {
            case self::TYPE_SQS:
                $client = new SQS\SQS3($options);
                break;
            case self::TYPE_RABBITMQ:
                throw new Exception('RabbitMQ is not currently supported');
            case self::TYPE_DUMMYHTTPQ:
                $client = new DummyQ\DummyHttpQ($options);
                break;
            default:
                throw new Exception('Not a valid Queue system');
        }
        return $client;
    }
}
