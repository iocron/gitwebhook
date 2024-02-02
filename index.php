<?php
// Requirements / Includes
require_once(__DIR__."/lib/gitwebhook.php");

// Config / Default Settings
$configFile = __DIR__."/configs/config.json";

if(!file_exists($configFile)) {
    $errMsg = "[ERROR]: The Gitwebhook Config File ({$configFile}) doesn't exist (or the path is not accessible)!";
    if(ini_get('display_errors') != "1") echo "{$errMsg}";
    throw new Exception("{$errMsg}");
}

$config = json_decode(file_get_contents($configFile),true);

if(!is_readable($configFile)) {
    $errMsg = "[ERROR]: The Gitwebhook Config File ({$configFile}) is not accessible / readable.";
    if(ini_get('display_errors') != "1") echo "{$errMsg}";
    throw new Exception("{$errMsg}");
}

if(empty($config) || !is_array($config)) {
    $errMsg = "[ERROR]: The Gitwebhook Config File ({$configFile}) is not a valid JSON File.";
    if(ini_get('display_errors') != "1") echo "{$errMsg}";
    throw new Exception("{$errMsg}");
}

// Gitwebhook
$webhook = new Gitwebhook($config);
$validate = $webhook->validateInit();

if($validate) {
    $webhook->handle();
}
