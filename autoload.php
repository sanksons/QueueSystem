<?php 

require_once 'vendor/autoload.php';

spl_autoload_register(function ($classname) {
    $dir = dirname(__FILE__) . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR;
    if (strpos($classname, "QueueSystem\\SQS\\") === 0) {
        $file = $dir . "SQS/".basename(str_replace('\\', '/', $classname)) . '.php';
        if (file_exists($file)) require $file;
    }else if (strpos($classname, "QueueSystem\\") === 0) {
        $file = $dir . basename(str_replace('\\', '/', $classname)) . '.php';
        if (file_exists($file)) require $file;
    }
    
});