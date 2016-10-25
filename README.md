# Gitwebhook for automatic Deployments to your Server

## Overview & Usage

This script automates the pushes from github (or bitbucket) so they can be directly provisioned (pulled / cloned) on the right server and location (the gitwebhook and the location where you want to deploy your code have to be on the same server).

Use the following steps to set up a new gitwebhook on your github (or bitbucket) account & server:

### On Github (First Step):

1. Go to your Repository and click on "Settings", then click on "Add webhook"
2. Use the following payload url: https://\<example.com\>/gitwebhook/index.php
3. Add a secret of your choice
4. Select the option "Just the push event.", then click on "Add webhook"

### On Bitbucket:

1. Go to your Repository and click on "Settings", then click on "Webhooks"
2. Click on "Add webhook"
3. Use the following url: https://\<example.com\>/gitwebhook/index.php?bitbucket_secret=\<secret\> 

   (replace \<secret\> with a Secret of your choice)

### Setup Gitwebhook (Second Step):

1. Go to the directory of your choice (has to be accessible from outside / the web)
   ```
   cd <yourWebsiteFolder>
   ``` 
   
   *(e.g. /var/www/example.com/httpdocs/)*
2. Clone the gitwebhook

   ```
   git clone https://github.com/iocron/gitwebhook.git && cd gitwebhook
   ```
   
3. Copy configuration file and htaccess so you can use them:

   ```
   cp config_example.json config.json && cp .htaccess_example .htaccess && chmod 600 config.json
   ```

   *(Note: If you are a bitbucket user, then edit the .htaccess and uncomment the Bitbucket User Block for better security)*
4. Fill out the config.json Settings (see options at the bottom):

   ```
   vim config.json
   ``` 
   
### Setup a SSH-Keygen & Deploy Key on your Server (Third Step)
1. Generate a SSH-Key first:

   ```
   ssh-keygen -t rsa -b 4096 -C "your_email@example.com"
   ```
   
   Further reading: [Github - Generate a new SSH Key](https://help.github.com/articles/generating-a-new-ssh-key-and-adding-it-to-the-ssh-agent/)

   *(Note: When you do the setup for another user as admin / root, then save the key to /var/www/\<domain\>/.ssh/id_rsa instead and chown the rights to the webuser)*

2. Copy the public key and add it to Github / Bitbucket as the Deploy Key 

   ```
   cat ~/.ssh/id_rsa.pub
   ```
   
   *(Note: When you do the setup for another user as admin / root, then use: cat /var/www/\<domain\>/.ssh/id_rsa.pub instead)*

   Further Reading:<br>
   [Github - Setup deploy key](https://developer.github.com/guides/managing-deploy-keys/#setup-2)<br>
   [Bitbucket - Setup deploy key](https://confluence.atlassian.com/bitbucket/use-deployment-keys-294486051.html)
   
### Setup a Host Key on your Server & Test the Connection (Fourth Step)

1. Add the Github & Bitbucket Host Key to your Known Hosts (if not already done): 

   ```
   ssh-keyscan -t rsa github.com >> ~/.ssh/known_hosts && ssh-keyscan -t rsa bitbucket.org >> ~/.ssh/known_hosts
   ```
   
   *(Note: When you do the setup for another user as admin / root, then use the following example (adjust the paths to your needs): `ssh-keyscan -t rsa github.com >> /var/www/example.com/.ssh/known_hosts && ssh-keyscan -t rsa bitbucket >> /var/www/example.com/.ssh/known_hosts && chmod 600 /var/www/example.com/.ssh/known_hosts` instead and chown the rights to the webuser)*
   
2. Make a test connection: 

   ```
   ssh -Tv git@github.com
   ```
   
   or
   
   ```
   ssh -Tv git@bitbucket.org
   ```
   
   *(Note: When you do the setup for another user as admin / root, then test with: `su -p -c "ssh -Tv git@github.com" <username>` instead)*
   
3. Make a test commit to your Github / Bitbucket Repo and see if the deployed repo directory / code on your server has changed as well, have fun.

*Tips:*
*You can also use the gitwebhook on a different domain (e.g. Subdomain) and deploy to a different location on your server if you like (needs to be the same webuser because of access rights). If you want to use the gitwebhook with multiple git repositories, then copy the "gitwebhook": {...} block and paste it below the first "gitwebhook": {...} block (don't forget to add a "," at the end of each "gitwebhook": {...}, block).*

### config.json Options

   // Your URL to the Repository (preferably use a ssh url if it's a private repo)<br>
   `"repository":"git@github.com:octocat/Hello-World.git",`<br>
   // Your secret key (created from the previous steps "On Github" / "On Bitbucket")<br>
   `"secret":"\<secret\>",`<br>
   // Your Deployment directory<br>
   `"deployDir":"/var/www/example.com/httpdocs",`<br>
   // Notifications about the deployment will be sent to your email (leave empty if none)<br>
   `"mail":"mail@example.com",`<br>
   // Mail Subject Prefix (the subject will be completed by the error type if anything bad happens)<br>
   `"mailSubject":"Gitwebhook - "`

### Troubleshooting:
If you run into any problems and the git data doesn't get pulled correctly, then look into the webhook section on github (Settings -> Webhooks) and click on the webhook, further below you'll see the "Recent Deliveries", you can inspect them and even trigger the events manually again if necessary.

You'll also get all the necessary informations through the notification emails if something goes wrong, check them out.

### Known Issues:
- Gitwebhook always clones, but never pulls:<br>
  You might have some permission issues and gitwebhook can't access your deployDir folder, please check the permissions of Gitwebhook and your deployDir (both need to have the same access rights through the same webuser)
