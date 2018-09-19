# Lagoon Logs

This module aims to be as close to a zero-configuration logging system for Drupal 7 sites running on the the Amazee.io Lagoon platform.


## Installation

Installation in Drupal 8 assumes a composer based workflow.

It's installed by running the following
```
composer require drupal/lagoon_logs
drush pm-enable -y lagoon_logs
```

## Use/configuration

Lagoon Logs is meant to be a Zero Configuration setup for Amazee.IO Lagoon projects.

Once the prerequisite modules and libraries have been installed,
it will, by default send its logs to a Logstash instance at "application-logs.lagoon.svc:5555".
Further, the logs sent do Logstash are identified by default as 'DRUPAL', but this can be overridden by setting
the 'lagoon_logs.settings.identifier' setting (example below).

You're able to override these options by setting a configuration entry in your *.settings.php files by using the following:
 
```
$config['lagoon_logs.settings']['host'] = '172.17.0.1';
$config['lagoon_logs.settings']['port'] = 5141;
$config['lagoon_logs.settings']['identifier'] = 'customidentifier';
```



You're able to view the effective settings for the current Drupal installation by visiting the page [admin/settings/lagoon_logs](admin/settings/lagoon_logs)
