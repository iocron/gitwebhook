<?php
  // Requirements / Includes
  require_once(__DIR__."/lib/gitwebhook.php");
  
  // Config / Default Settings
  if(file_exists(__DIR__."/configs/config.json")){
    $config = json_decode(file_get_contents(__DIR__."/config.json"),true);
    
    if(empty($config)){
      $errMsg = "[ERROR]: The Gitwebhook Config File (".__DIR__."/configs/config.json) is not valid or is corrupted.";
      if(ini_get('display_errors') != "1") echo "{$errMsg}";
      throw new Exception("{$errMsg}");
    }
  } else {
    $errMsg = "[ERROR]: The Gitwebhook Config File (".__DIR__."/configs/config.json) doesn't exist!"; 
    if(ini_get('display_errors') != "1") echo "{$errMsg}"; 
    throw new Exception("{$errMsg}");
  }
  
  // Gitwebhook
  $webhook = new Gitwebhook($config);
  $webhookData = $webhook->getData();
  $validate = $webhook->validateInit();
  
  if($validate){
    $webhook->handle();
  }
?>
