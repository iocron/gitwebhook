<?php
class Gitwebhook
{
    private $config,$configName;
    private $secret;
    private $repository,$branch;
    private $deployDir;
    private $gitOutput;
    private $data,$event,$delivery;
    private $mail,$mailSubject;
    private $linuxUser;
    private $debug;

    public function __construct(array $config) {
        $this->config = $this->getConfig($config);
        $this->repository = $this->getConfigVar("git_repository");
        $this->branch = $this->getConfigVar("git_branch");
        $this->secret = $this->getConfigVar("git_secret");
        $this->deployDir = $this->getConfigVar("deployDir");
        $this->mail = $this->getConfigVar("mail");
        $this->mailSubject = $this->getConfigVar("mailSubject");
        $this->linuxUser = $this->getConfigVar("linux_user");
        $this->debug = $this->getConfigVar("debug") == "1" ? true : false;
    }

    // GETTER
    public function getData() { return $this->data; }
    public function getDelivery() { return $this->delivery; }
    public function getEvent() { return $this->event; }
    public function getDeployDir(): string { return $this->deployDir; }
    public function getGitOutput() { return $this->gitOutput; }
    public function getRepository(): string { return $this->repository; }
    public function getSecret(): string { return $this->secret; }

    protected function getConfigVar(string $name, string $mode="text"): string {
        if(!isset($this->config[$name]) || empty($this->config[$name])) return "";
        if($mode == "text") return $this->config[$name];
        if($mode == "shell") return escapeshellarg($this->config[$name]);

        return "";
    }

    protected function getConfig($config) {
        // Allocate the right gitwebhook config according to the right repo
        $payloadData = json_decode(file_get_contents('php://input'), true);
        $payloadDataRepoFullname = print_r($payloadData["repository"]["full_name"], true);
        $configPick = false;

        foreach($config as $key => $conf) {
            if(stristr($conf["git_repository"], $payloadDataRepoFullname)) {
                $this->configName = str_replace(["..", "/", " "], ["", "", ""], $key);
                $configPick = $conf;
                break;
            }
        }

        if($configPick == false) {
            $errMsg = "[ERROR]: Gitwebhook: Your repository ".htmlspecialchars($payloadDataRepoFullname,ENT_QUOTES,'utf-8')." didn't match any of the config repository entries.";
            if(ini_get('display_errors') != "1") echo "{$errMsg}";

            throw new Exception("{$errMsg}");
        } else {
            return $configPick;
        }
    }

    // SETTER, HELPER & VALIDATORS
    public function notification(string $subject, string $message, string $mode="ERROR") {
        if($this->debug && $mode == "ERROR") {
            file_put_contents(__DIR__."/../logs/{$this->configName}_error_log_".date("Y-m-d-His").".log","{$subject}: {$message}\n\nConfig Data:\n".print_r($this->config,true)."\n"."Server Data:\n".print_r($_SERVER,true));
        }

        if($this->mail != "false" && $this->mail != "") {
            $subjectWithInsertTag = str_replace('{{subject}}', $subject, $this->mailSubject);
            $messagePrefix = "Repository: {$this->repository}\n";

            mail($this->mail, $subjectWithInsertTag, $messagePrefix.$message);
        }
    }

    public function handle() {
        $eol = PHP_EOL;

        // Set Identity Variables of the current Linux User and Group of the running script
        $currentUser = exec('whoami'); // $currentGroup = exec("id -Gn {$currentUser}");

        // Setup Git Pull / Clone Commands
        $tmpDeployDir = escapeshellarg($this->deployDir);
        $tmpBranch = escapeshellarg($this->branch);
        $tmpRepository = escapeshellarg($this->repository);

        if(file_exists("{$this->deployDir}/.git")) {
            $execCommand = "( cd {$tmpDeployDir} && git checkout {$tmpBranch} && git pull -f )";
            $tmpMailSubject = "Successful: Git pull executed";
        } else {
            $execCommand = "( cd {$tmpDeployDir} && git clone {$tmpRepository} . && git checkout {$tmpBranch} )";
            $tmpMailSubject = "Successful: Git clone executed";
        }

        // Setup execCommand as another Linux User if a Linux User is defined in the Config
        if(!empty($this->linuxUser) && $currentUser != $this->linuxUser) {
            $execCommand = "su -c '{$execCommand}' 2>&1 {$this->linuxUser}";
        } else {
            $execCommand = "{$execCommand} 2>&1";
        }

        // Execute Git Pull / Clone Commands
        exec($execCommand,$this->gitOutput);

        // Generate Git Report
        $gitReport = $this->gitOutput;
        if(is_array($this->gitOutput)) {
            $gitReport = "";
            foreach($this->gitOutput as $oCnt => $oVal) {
                $gitReport .= $oVal."\n";
            }
        }

        // Send Notification about the Git Deployment (Git Report)
        $this->notification($tmpMailSubject, "gitCommand:{$eol}{$execCommand}{$eol}{$eol}gitOutput:{$eol}{$gitReport}{$eol}Server Output:{$eol}".print_r($_SERVER, true), "TEXT");

        return true;
    }

    public function validateInit(bool $lock=true) {
        $validate = $this->validate();

        // Lock Validation (Security)
        if($lock) {
            $lockFile = __DIR__."/../tmp/lock_gitwebhook";
            $lockFileContent = file_exists($lockFile) ? file_get_contents($lockFile) : "0";
            $lockNum = intval($lockFileContent);

            // Reset Lock after 15 Minutes
            if(file_exists($lockFile) && time() - filemtime($lockFile) > 900) {
                file_put_contents($lockFile, "0", LOCK_EX);
                $lockNum = 0;
            }

            // Set Lockdown if lockNum (lock attempts) is 10 or higher (for 15 Minutes) and set validate false
            if($lockNum >= 10) {
                $this->notification("Error: Too many errors / wrong attempts in a short time","Gitwebhook has reached too many errors / wrong attempts in a short time. This can be caused by a misconfiguration of the gitwebhook, access rights on your server, or even a suspicious process might be running. Please check your error emails or your error logs. The lock will be suspended after 15 Minutes and you can try again.");
                return false;
            }
        }

        // Regular Validation
        if($validate) {
            return true;
        } else {
            if($lock){
            // Write new Lock with Lock Number
            file_put_contents($lockFile, print_r(($lockNum+1),true), LOCK_EX);
            }
            return false;
        }
    }

    public function validate(): bool {
        // Bitbucket Payload Validation
        if(isset(getallheaders()['X-Hub-Signature'])) {
            $bitbucketSignature = getallheaders()['X-Hub-Signature'];
            if(!empty($bitbucketSignature)) {
                $requestContent = file_get_contents('php://input');
                if(!hash_equals('sha256=' . hash_hmac('sha256', $requestContent, $this->secret), $bitbucketSignature)) {
                    $this->notification("Error: Not compliant secrets", "Please make sure the secret key is equal on both sides (Your Server & Bitbucket)");
                    return false;
                }

                $payload = json_decode($requestContent, true);
                $event = @$_SERVER['HTTP_X_EVENT_KEY'];
                $delivery = @$_SERVER['HTTP_X_REQUEST_UUID'];
                $attemptNumber = @$_SERVER['HTTP_X_ATTEMPT_NUMBER'];
                // X-Attempt-Number
                // HTTP_X_ATTEMPT_NUMBER

                if(empty($payload)) {
                    $this->notification("Error: Payload is empty.","Something went really wrong about your payload (empty).");
                    return false;
                }
                if(!isset($payload["repository"]["name"], $payload["push"]["changes"])) {
                    $this->notification("Error: Invalid Payload Data received.","Your payload data isn't valid.\nPayload Data:\n".print_r($payload, true));
                    return false;
                }
                if(!isset($attemptNumber)) {
                    $this->notification("Error: Invalid Payload Data received (attemptNumber).","Your payload data isn't valid.\nPayload Data:\n".print_r($payload, true));
                    return false;
                } else if($attemptNumber > 1) {
                    echo "The Git Execution is still in progress (this is the #{$attemptNumber} attempt), please wait..";
                    return false;
                }

                $this->data = $payload;
                $this->event = $event;
                $this->delivery = $delivery;

                return true;
            }
        }

        // @deprecated Bitbucket Payload Validation (simple - fallback)
        if(isset($_REQUEST['bitbucket_secret'])) {
            $payload = json_decode(file_get_contents('php://input'), true);
            $event = @$_SERVER['HTTP_X_EVENT_KEY'];
            $delivery = @$_SERVER['HTTP_X_REQUEST_UUID'];
            $attemptNumber = @$_SERVER['HTTP_X_ATTEMPT_NUMBER'];
            // X-Attempt-Number
            // HTTP_X_ATTEMPT_NUMBER

            if($_REQUEST["bitbucket_secret"] != $this->secret) {
                $this->notification("Error: Not compliant secrets", "Please make sure the secret key is equal on both sides (Your Server & Bitbucket). \nBitbucket Secret is ".htmlspecialchars($_REQUEST["bitbucket_secret"])."\nJSON Config Secret is {$this->secret}");
                return false;
            }
            if(empty($payload)) {
                $this->notification("Error: Payload is empty.", "Something went really wrong about your payload (empty).");
                return false;
            }
            if(!isset($payload["repository"]["name"], $payload["push"]["changes"])) {
                $this->notification("Error: Invalid Payload Data received.","Your payload data isn't valid.\nPayload Data:\n".print_r($payload, true));
                return false;
            }
            if(!isset($attemptNumber)) {
                $this->notification("Error: Invalid Payload Data received (attemptNumber).","Your payload data isn't valid.\nPayload Data:\n".print_r($payload, true));
                return false;
            } else if($attemptNumber > 1) {
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
            $this->notification("Error: Signature, Event or Delivery (Header) is not set.","Server Output:\n".print_r($_SERVER, true)."\n\nWebhook Data:\n".print_r($payloadData, true));
            return false;
        }

        if (!$this->validateSignature($signature, $payload)) {
            $this->notification("Error: Secret (or Payload) Validation Failed","Server Output:\n".print_r($_SERVER, true)."\n\nWebhook Data:\n".print_r($payloadData, true));
            return false;
        }

        $this->data = $payloadData;
        $this->event = $event;
        $this->delivery = $delivery;

        return true;
    }

    protected function validateSignature($gitHubSignatureHeader, $payload) {
        // Github Payload Validation
        list($algo, $gitHubSignature) = explode("=", $gitHubSignatureHeader);

        if ($algo !== 'sha1') {
            // see https://developer.github.com/webhooks/securing/
            return false;
        }

        $payloadHash = hash_hmac($algo, $payload, $this->secret);
        return ($payloadHash === $gitHubSignature);
    }
}
