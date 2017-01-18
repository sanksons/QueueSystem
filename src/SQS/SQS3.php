<?php
namespace QueueSystem\SQS;

/**
 * SQS V3 Implementation for Queue System.
 * 
 * @author Sankalp Bhatt
 *
 */
class SQS3 extends SQS implements \QueueSystem\QueueInterface
{
    
    protected function getClient($options = array())
    {
        if (empty($options['region'])) {
            $options['region'] = static::DEF_REGION;
        }
        $sdk = new \Aws\Sdk([
            'region'   => $options['region'],
        ]);
        $client = $sdk->createClient('sqs',['version'=>'2012-11-05']);
        return $client;
    }
}