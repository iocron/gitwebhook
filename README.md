# Gitwebhook for automatic Deployments to your Server

## Overview & Usage

This script automates the pushes from github (or bitbucket) so they can be directly provisioned (pulled / cloned) to the right server and location.

Use the following steps to set up a new gitwebhook on your github (or bitbucket) account & server:
(Note: Be sure that a deploy key on your server is set as well, you can validate it by running `ssh -Tv git@github.com` or for bitbucket users `ssh -Tv git@bitbucket.org`. More informations about the whole process of deploy keys: [Github - Setup deploy key](https://developer.github.com/guides/managing-deploy-keys/#setup-2) or [Bitbucket - Setup deploy key](https://confluence.atlassian.com/bitbucket/use-deployment-keys-294486051.html))

### On Github (First Step):

1. Go to your Repository and click on "Settings", then click on "Add webhook"
2. Use the following payload url: https://\<yourwebsite\>/gitwebhook/index.php
3. Add a secret of your choice
4. Select the option "Just the push event.", then click on "Add webhook"

### On Bitbucket (skip if you are a Github User):

1. Go to your Repository and click on "Settings", then click on "Webhooks"
2. Click on "Add webhook"
3. Use the following url: https://\<yourwebsite\>/gitwebhook/index.php?bitbucket_secret=\<secret\> 

   (replace \<secret\> with a Secret of your choice)

### On your Server (Second Step):

1. `cd <yourWebsiteFolder>` (e.g. /var/www/example.com/httpdocs/)
2. `git clone https://github.com/iocron/gitwebhook.git`
3. `cp config_example.json config.json && cp .htaccess_example .htaccess`
4. `vim config.json` or `nano config.json`
5. Fill out the settings:

   // Your URL to the Repository (preferably use a ssh url if it's a private repo)<br>
   "repository":"git@github.com:octocat/Hello-World.git",<br>
   // Your secret key (created from the previous steps "On Github" / "On Bitbucket")<br>
   "secret":"\<secret\>",<br>
   // Your Deployment directory<br>
   "deployDir":"/var/www/example.com/httpdocs",<br>
   // Notifications about the deployment will be sent to your email (leave empty if none)<br>
   "mail":"mail@example.com",<br>
   // Mail Subject Prefix (the subject will be completed by the error type if anything bad happens)<br>
   "mailSubject":"Gitwebhook - "
6. Make a test commit to your Github / Bitbucket Repo and see if the code on your server has changed as well, have fun.

Note - If you are a bitbucket user: 
  - then edit the .htaccess and uncomment the Bitbucket User Block for better security (important!)
  - use `ssh -Tv git@bitbucket.org` additionally and check if a valid connection can be established before you try a test commit (and accept the RSA key fingerprint as well if asked)

*Tips:*
*You can use the gitwebhook on a different domain (e.g. Subdomain) and deploy to a different location on your server if you like. The gitwebhook module supports only a single git repository at the moment (multiple git repositories will be added in the future).*

### Troubleshooting:
If you run into any problems and the git data doesn't get pulled correctly, then look into the webhook section on github (Settings -> Webhooks) and click on the webhook, further below you'll see the "Recent Deliveries", you can inspect them and even trigger the events manually again if necessary.

You'll also get all the necessary informations through the notification emails if something goes wrong, check them out.
