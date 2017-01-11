<?php 

spl_autoload_register(function ($classname) {
    $dir = dirname(__FILE__) . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR;
    if (strstr($classname, "\\QueueSystem") === 0) {
        $file = $dir . basename(str_replace('\\', '/', $classname)) . '.php';
        echo $file;
        echo PHP_EOL;
        if (file_exists($file)) require $file;
    }
});