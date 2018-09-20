# Lagoon Logs

This module aims to be as close to a zero-configuration logging system for Drupal 7 sites running on the the Amazee.io Lagoon platform.


## Installation


## Use/configuration

Lagoon Logs is meant to be a Zero Configuration setup for Amazee.IO Lagoon projects.
Once the prerequisite modules and libraries have been installed,
it will, by default send its logs to a Logstash instance at "application-logs.lagoon.svc:5555".
Further, the logs sent do Logstash are identified by default as 'DRUPAL', but this can be overriden by setting
the 'LAGOON_LOGS_IDENTIFIER' setting.

You're able to override these options by setting a configuration entry in your *.settings.php files by using the keys "LAGOON_LOGS_HOST",
 "LAGOON_LOGS_PORT", and 'LAGOON_LOGS_IDENTIFIER' like so:
 
```
$conf['LAGOON_LOGS_HOST'] = 'new-application-logs.lagoon.svc';
$conf['LAGOON_LOGS_PORT'] = '5556';
$conf['LAGOON_LOGS_IDENTIFIER'] = 'specific system identifier name';
```

You're able to view the effective settings for the current Drupal installation by visiting the page [admin/settings/lagoon_logs](admin/settings/lagoon_logs)
