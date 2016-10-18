<?php
    // Requirements / Includes
    require_once(__DIR__."/classes/gitwebhook.php");
    
    // Namespaces
    use Gitdeployer\Githubwebhook;
    
    // Config / Default Settings
    if(file_exists(__DIR__."/config.json")){
        $config = json_decode(file_get_contents(__DIR__."/config.json"),true);
        
        if(empty($config)){
            echo "The gitwebhook Config File is not valid or is corrupted.";
            die();
        }
    } else {
        echo "The gitwebhook Config File doesn't exist!";
        die();
    }
    
    // GITHUB WEBHOOK
    $webhook = new Githubwebhook($config["gitwebhook"]);
    $webhookData = $webhook->getData();
    $webhookDataStr = print_r($webhookData,true);
    $eol = PHP_EOL;
    
    if($webhook->validate()){
        $webhook->handle();
    } else {
        $webhook->notification("Error: Secret Validation Failed","Server Output:{$eol}".(print_r($_SERVER,true))."{$eol}{$eol}Webhook Data:{$eol}".$webhookDataStr);
    }
?>
