<?php
    // Requirements / Includes
    require_once(__DIR__."/lib/gitwebhook.php");
    
    // Namespaces
    use Gitdeployer\Gitwebhook;
    
    // Config / Default Settings
    if(file_exists(__DIR__."/config.json")){
        $config = json_decode(file_get_contents(__DIR__."/config.json"),true);
        
        if(empty($config)){
          $errMsg = "[ERROR]: The Gitwebhook Config File is not valid or is corrupted.";
          if(ini_get('display_errors') != "1") echo "{$errMsg}";
          throw new Exception("{$errMsg}");
        }
    } else {
        $errMsg = "[ERROR]: The Gitwebhook Config File doesn't exist!"; 
        if(ini_get('display_errors') != "1") echo "{$errMsg}"; 
        throw new Exception("{$errMsg}");
    }
    
    // Gitwebhook
    $webhook = new Gitwebhook($config);
    $webhookData = $webhook->getData();
    $webhookDataStr = print_r($webhookData,true);
    $eol = PHP_EOL;
    
    if($webhook->validate()){
        $webhook->handle();
    } else {
        $webhook->notification("Error: Secret (or Payload) Validation Failed","Server Output:{$eol}".(print_r($_SERVER,true))."{$eol}{$eol}Webhook Data:{$eol}".$webhookDataStr);
    }
?>
