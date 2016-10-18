# Gitwebhook for automatic Deployment to your Server

## Overview & Usage

This script automates the pushes from github so they can be directly provisioned (pulled / cloned) to the right server.

Use the following steps to set up a new gitwebhook on your github account & server:
(note: be sure that a deploy key on your server is set as well: https://github.com/\<username\>/\<repository\>/settings/keys, more informations about the whole process of deploy keys: [Setup a deploy key for your server](https://developer.github.com/guides/managing-deploy-keys/#setup-2))

### On Github (First Step):

1. Go to your Repository and click on "Settings", then click on "Add webhook"
2. Use the following payload url: https://\<yourwebsite\>/gitwebhook/index.php
3. Add a secret of your choice
4. Select the option "Just the push event.", then click on "Add webhook"

### On your Server (Second Step):

1. `cd <yourWebsiteFolder>` (e.g. /var/www/example.com/httpdocs/)
2. `git clone git@github.com:iocron/gitwebhook.git`
3. `cp config_example.json config.json`
4. `vim config.json`
5. Fill out the settings:

// Optional, can be left empty<br>
"remote":"",<br>
// Your secret key (created from the previous steps "On Github")<br>
"secret":"<yourSecret>",<br>
// Your Deployment directory<br>
"deployDir":"/var/www/example.com/httpdocs",<br>
// Notifications about the deployment will be sent to your email (leave empty if none)<br>
"mail":"mail@example.com",<br>
// Mail Subject Prefix (the subject will be completed by the error type if anything bad happens)<br>
"mailSubject":"Gitwebhook - "

*Additional Notes:*
*You can use the gitwebhook on a different domain (e.g. Subdomain) and deploy to a different location on your server. The gitwebhook module supports only a single git repository at the moment (multiple git repositories will be added in the future).*

### Troubleshooting:
If you run into any problems and the git data doesn't get pulled correctly, then look into the webhook section on github (Settings -> Webhooks) and click on the webhook, further below you'll see the "Recent Deliveries", you can inspect them and even trigger the events manually again if necessary.

You'll also get all the necessary informations through the notification emails if something goes wrong, check them out.
