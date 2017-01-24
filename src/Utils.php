<?php
namespace QueueSystem;

/**
 * A Utility class for QueueSystem
 *
 * @author Sankalp Bhatt
 *        
 */
class Utils
{

    const DEF_PATH = '/tmp/';

    const DEF_LEVEL = \Psr\Log\LogLevel::DEBUG;

    const DEF_FILE_NAME = 'queuesystem';

    /**
     * Get Logger Instance;
     *
     * @param string $path            
     * @param int $level            
     * @param string $fileName            
     * @return \QueueSystem\Katzgrau\KLogger\Logger
     */
    public static function getLogger(
        $path = self::DEF_PATH, 
        $level = self::DEF_LEVEL, 
        $fileName = self::DEF_FILE_NAME
    ) 
    {
        $fileName = $fileName . '-' . date('Y-m-d') . '.log';
        return new \Katzgrau\KLogger\Logger($path, $level, array(
            'filename' => $fileName
        ));
    }

    /**
     * Checks if the supplied argument is a callable function.
     * 
     * @param string|array $func            
     */
    public static function isCallable($func = null)
    {
        if (! empty($func) && is_callable($func)) {
            return true;
        }
        return false;
    }
    
    /**
     * Prepare logger specific settings.
     *
     * @param array $options
     * @return array
     */
    public static function getLoggerSettings($options = array())
    {
        $ret = array(
            'filePath' => Utils::DEF_PATH,
            'level' => Utils::DEF_LEVEL,
            'fileName' => Utils::DEF_FILE_NAME
        );
        foreach ($ret as $key => $val) {
            if (! empty($options['logger'][$key])) {
                $ret[$key] = $options['logger'][$key];
            }
        }
        return $ret;
    }
    
}