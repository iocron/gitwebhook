<?php
class Gitwebhook
{
    private $secret;
    private $repository,$branch;
    private $gitDir;
    private $gitOutput;
    private $data;
    private $event;
    private $delivery;
    private $mail,$mailSubject;
    private $linuxUser;
    private $valid;

    public function __construct($config){
      $conf = $this->validateConfig($config);
      $this->repository = $this->getConfigVar($conf["git_repository"]);
      $this->branch = $this->getConfigVar($conf["git_branch"]);
      $this->secret = $this->getConfigVar($conf["git_secret"]);
      $this->gitDir = $this->getConfigVar($conf["deployDir"]);
      $this->mail = $this->getConfigVar($conf["mail"]);
      $this->mailSubject = $this->getConfigVar($conf["mailSubject"]);
      $this->linuxUser = $this->getConfigVar($conf["linux_user"] );
    }

    // GETTER
    protected function getConfigVar($var){ return !empty($var) ? escapeshellarg($var) : ""; }
    public function getData(){ return $this->data; }
    public function getDelivery(){ return $this->delivery; }
    public function getEvent(){ return $this->event; }
    public function getGitDir(){ return $this->gitDir; }
    public function getGitOutput(){ return $this->gitOutput; }
    public function getRepository(){ return $this->repository; }
    public function getSecret(){ return $this->secret; }
    
    // SETTER, HELPER & VALIDATORS
    public function notification($subject,$message){
        if($this->mail != "false" && $this->mail != ""){
            $subjectWithInsertTag = str_replace('{{subject}}',$subject,$this->mailSubject);
            mail($this->mail,$subjectWithInsertTag,$message);
        }
    }

    public function handle(){
        $eol = PHP_EOL;
        
        // Validation Check
        if (!$this->validateInit(false)) {
            $this->notification("Error: Git handle validation check failed","Server Output:{$eol}".print_r($_SERVER,true));
            return false;
        }
        
        // Set Identity Variables of the current Linux User and Group of the running script
        $currentUser = exec('whoami'); // $currentGroup = exec("id -Gn {$currentUser}");

        // Setup Git Pull / Clone Commands
        if(file_exists("{$this->gitDir}/.git")){
          $execCommand = "( cd {$this->gitDir} && git checkout {$this->branch} && git pull -f )";
          $tmpMailSubject = "Successful: Git pull executed";
        } else {
          $execCommand = "( cd {$this->gitDir} && git clone {$this->repository} . && git checkout {$this->branch} )";
          $tmpMailSubject = "Successful: Git clone executed";
        }
        
        // Setup execCommand as another Linux User if a Linux User is defined in the Config
        if(!empty($this->linuxUser) && $currentUser != $this->linuxUser){
          $execCommand = "su -c '{$execCommand}' 2>&1 {$this->linuxUser}";
        } else {
          $execCommand = "{$execCommand} 2>&1";
        }
        
        // Execute Git Pull / Clone Commands
        exec($execCommand,$this->gitOutput);
        
        // Generate Git Report
        $gitReport = $this->gitOutput;
        if(is_array($this->gitOutput)){
            $gitReport = "";
            foreach($this->gitOutput as $oCnt => $oVal){
                $gitReport .= $oVal."\n";
            }
        }
        
        // Send Notification about the Git Deployment (Git Report)
        $this->notification($tmpMailSubject,"gitCommand:{$eol}{$execCommand}{$eol}{$eol}gitOutput:{$eol}{$gitReport}{$eol}Server Output:{$eol}".print_r($_SERVER,true));

        return true;
    }
    
    public function validateInit($lock=true){
      $validate = $this->validate();
      
      if($lock){
        $lockFile = file_exists(__DIR__."/.lock_gitwebhook") ? __DIR__."/.lock_gitwebhook" : false;
        $lockFileContent = $lockFile ? file_get_contents($lockFile) : "0";
        $lockNum = intval($lockFileContent);
        
        // Reset Lock after 15 Minutes
        if(time() - filemtime($lockFile) > 900){
          file_put_contents($lockFile, "0");
        }
        
        // Set Lock after 10 failed validations (for 15 Minutes) and set validate false
        if($lockNum >= 10){
          return false;
        }
      }
      
      if($validate){
        return true;
      } else {
        if($lock){
          // Write new Lock Number
          file_put_contents($lockFile, print_r(($lockNum+1),true));
        }
        
        return false;
      }
    }

    public function validate(){
      // Bitbucket Payload Validation (simple)
      if(isset($_REQUEST['bitbucket_secret'])){
        $payload = json_decode(file_get_contents('php://input'),true);
        $event = @$_SERVER['X-Event-Key'];
        $delivery = @$_SERVER['X-Request-UUID'];
        $attemptNumber = @$_SERVER['X-Attempt-Number'];
        
        if($_REQUEST["bitbucket_secret"] != $this->secret){
          $this->notification("Error: Not compliant secrets","Please make sure the secret key is equal on both sides (Your Server & Bitbucket).");
          return false;
        }
        if(empty($payload)){
          $this->notification("Error: Payload is empty.","Something went really wrong about your payload (empty).");
          return false;
        }
        if(!isset($payload["repository"]["name"], $payload["push"]["changes"])){
          $this->notification("Error: Invalid Payload Data received.","Your payload data isn't valid.\nPayload Data:\n".print_r($payload,true));
          return false;
        }
        if(!isset($attemptNumber)){
          $this->notification("Error: Invalid Payload Data received (attemptNumber).","Your payload data isn't valid.\nPayload Data:\n".print_r($payload,true));
          return false;
        } else if($attemptNumber>1){
          echo "The Git Execution is still in progress (this is the #{$attemptNumber} attempt), please wait..";
          return false;
        }
        
        $this->data = $payload;
        $this->event = $event;
        $this->delivery = $delivery;
        
        return true;
      }
      
      // Github Payload Validation
      $signature = @$_SERVER['HTTP_X_HUB_SIGNATURE'];
      $event = @$_SERVER['HTTP_X_GITHUB_EVENT'];
      $delivery = @$_SERVER['HTTP_X_GITHUB_DELIVERY'];
      $payload = file_get_contents('php://input');
      $payloadData = json_decode($payload,true);

      if (!isset($signature, $event, $delivery)) {
          $this->notification("Error: Signature, Event or Delivery (Header) is not set.","Server Output:\n".print_r($_SERVER,true)."\n\nWebhook Data:\n".print_r($payloadData,true));
          return false;
      }

      if (!$this->validateSignature($signature, $payload)) {
          $this->notification("Error: Secret (or Payload) Validation Failed","Server Output:\n".print_r($_SERVER,true)."\n\nWebhook Data:\n".print_r($payloadData,true));
          return false;
      }

      $this->data = $payloadData;
      $this->event = $event;
      $this->delivery = $delivery;
      return true;
    }

    protected function validateSignature($gitHubSignatureHeader, $payload){    
      // Github Payload Validation
      list ($algo, $gitHubSignature) = explode("=", $gitHubSignatureHeader);

      if ($algo !== 'sha1') {
          // see https://developer.github.com/webhooks/securing/
          return false;
      }

      $payloadHash = hash_hmac($algo, $payload, $this->secret);
      return ($payloadHash === $gitHubSignature);
    }
    
    protected function validateConfig($config){      
      // Allocate the right gitwebhook config according to the right repo
      $payloadData = json_decode(file_get_contents('php://input'),true);
      $payloadDataRepoFullname = print_r($payloadData["repository"]["full_name"],true);
      $configPick = false;
      
      foreach($config as $conf){
        if(stristr($conf["git_repository"],$payloadDataRepoFullname)){
          $configPick = $conf;
          break;
        }
      }
      
      if($configPick == false){
        $errMsg = "[ERROR]: Gitwebhook: Your repository ".htmlspecialchars($payloadDataRepoFullname,ENT_QUOTES,'utf-8')." didn't match any of the config repository entries.";
        if(ini_get('display_errors') != "1") echo "{$errMsg}";
        throw new Exception("{$errMsg}");
      } else {
        return $configPick;
      }
    }
}
