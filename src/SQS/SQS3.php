<?php
namespace QueueSystem\SQS;

/**
 * SQS V3 Implementation for Queue System.
 * 
 * This is an extension of SQS class for V3 of AWS SDK.
 * FOR V2 of AWS SDK use SQS class as it is. 
 * 
 * @author Sankalp Bhatt
 *
 */
class SQS3 extends SQS implements \QueueSystem\QueueInterface
{
    const SQS_VERSION = '2012-11-05';
    
    /**
     * Overriden getClient for SDk 3.0
     * 
     * {@inheritDoc}
     * @see \QueueSystem\SQS\SQS::getClient()
     */
    protected function getClient($options = array())
    {
        if (empty($options['region'])) {
            $options['region'] = static::DEF_REGION;
        }
        //check if stats is supplied, if not use the default one.
        if (!empty($options['stats'])) {
           $this->stats = (bool) $options['stats'];
        }
        if ($this->stats) {
            $stats = array(
                'retries'      => true,
                'timer'        => true,
                'http'         => true,
            );    
        }
        $sdk = new \Aws\Sdk([
            'region'   => $options['region'],
            'http' => array(
                'timeout' => $this->httpTimeout,
            ),
            'stats'   => $stats,
        ]);
        $client = $sdk->createClient('sqs',['version' => self::SQS_VERSION]);
        return $client;
    }
}