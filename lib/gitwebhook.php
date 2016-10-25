<?php
namespace Gitdeployer;

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
    private $linuxUser,$linuxGroup;

    public function __construct($config){
      $conf = $this->validateConfig($config);
      $this->repository = $conf["git_repository"];
      $this->branch = $conf["git_branch"];
      $this->secret = $conf["git_secret"];
      $this->gitDir = $conf["deployDir"];
      $this->mail = $conf["mail"];
      $this->mailSubject = $conf["mailSubject"];
      $this->linuxUser = $conf["linux_user"];
      $this->linuxGroup = $conf["linux_group"];  
    }

    public function getData(){ return $this->data; }
    public function getDelivery(){ return $this->delivery; }
    public function getEvent(){ return $this->event; }
    public function getGitDir(){ return $this->gitDir; }
    public function getGitOutput(){ return $this->gitOutput; }
    public function getRepository(){ return $this->repository; }
    public function getSecret(){ return $this->secret; }
    
    public function notification($subject,$message){
        if($this->mail != "false" && $this->mail != ""){
            $subjectWithInsertTag = str_replace('{{subject}}',$subject,$this->mailSubject);
            mail($this->mail,$subjectWithInsertTag,$message);
        }
    }

    public function handle(){
        $eol = PHP_EOL;
        
        // Validation Check
        if (!$this->validate()) {
            $this->notification("Error: Git handle validation check failed","Server Output:{$eol}".print_r($_SERVER,true));
            return false;
        }

        // Setup Git Pull / Clone Commands
        if(file_exists("{$this->gitDir}/.git")){
          $execCommand = "{ cd {$this->gitDir} && git checkout {$this->branch} && git pull -f } 2>&1";
          $tmpMailSubject = "Successful: Git pull executed";
        } else {
          $execCommand = "{ cd {$this->gitDir} && git clone {$this->repository} . && git checkout {$this->branch} } 2>&1";
          $tmpMailSubject = "Successful: Git clone executed";
        }
        
        if(!empty($this->linuxUser) || !empty($this->linuxGroup)){
          $execCommand .= " && find . -exec chown {$this->linuxUser}:{$this->linuxGroup} {} \;";
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

    public function validate(){      
      // Bitbucket Payload Validation (simple)
      if(isset($_REQUEST['bitbucket_secret'])){
        $payload = json_decode(file_get_contents('php://input'));
        
        if($_REQUEST["bitbucket_secret"] != $this->secret){
          $this->notification("Error: Not compliant secrets","Please make sure the secret key is equal on both sides (Your Server & Bitbucket).");
          return false;
        }
        if(empty($payload)){
          $this->notification("Error: Payload is empty.","Something went really wrong about your payload (empty).");
          return false;
        }
        if(!isset($payload->repository->name, $payload->push->changes)){
          $this->notification("Error: Invalid Payload Data received.","Your payload data isn't valid.\nPayload Data:\n".$payload);
          return false;
        }
        
        return true;
      }
      
      // Github Payload Validation
      $signature = @$_SERVER['HTTP_X_HUB_SIGNATURE'];
      $event = @$_SERVER['HTTP_X_GITHUB_EVENT'];
      $delivery = @$_SERVER['HTTP_X_GITHUB_DELIVERY'];
      $payload = file_get_contents('php://input');

      if (!isset($signature, $event, $delivery)) {
          return false;
      }

      if (!$this->validateSignature($signature, $payload)) {
          return false;
      }

      $this->data = json_decode($payload,true);
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
      $payloadData = json_decode(file_get_contents('php://input'));
      $payloadDataRepoFullname = $payloadData->repository->full_name;
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
