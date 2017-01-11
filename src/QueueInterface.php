<?php

namespace QueueSystem;

interface QueueInterface {
    
    public function declareQueue($queueName);
    
    public function publish($message);
    
    public function subscribe();
    
}



