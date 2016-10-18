# Gitwebhook for automatic Deployment to your Server

## Overview & Usage

This script automates the pushes from github so they can be directly provisioned to the right server.

If not already done, then use the following steps to set up a new gitwebhook script for the server you are on:
(note: be sure that a deploy key for the server is set: https://github.com/\<username\>/\<repository\>/settings/keys)

On Github:
1. Go to your Repository and click on "Settings", then click on "Add webhook"
2. Use the following payload url: https://\<yourdomain\>/gitwebhook/index.php
3. Add a secret of your choice
4. You only need "Just the push event.", then click on "Add webhook"

On your Server:
1. git clone 

5. Copy the file "config_example.json" to "config.json" in your dgmshop installation (usually dgmtools/gitwebhook/config_example.json)
6. Put in all your configurations, the most important one is the "secret" we just created and the "mail" parameter (for email notifications / informations / errors)
7. Now you should be ready to go, just test it by pushing to the github repo. You should get a email notification and the dgmshop code should be up to date on this server

### Troubleshooting:
If you run into any problems and the git data doesn't get pulled correctly, then look into the webhook on github, further below there are the "Recent Deliveries", you can inspect them and even trigger the events manually again if necessary.

You also get all the necessary informations through the notification emails if something goes wrong, check them out.
