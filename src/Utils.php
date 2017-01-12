<?php
namespace QueueSystem;

class Utils {
    
    const DEF_PATH = '/tmp/queue-system.log';
    const LEVEL = Psr\Log\LogLevel::DEBUG;
    
    public static function getLogger($path = self::DEF_PATH, $level = self::LEVEL) {
        return new Katzgrau\KLogger\Logger($path, $level);
        
    }
    
    
}