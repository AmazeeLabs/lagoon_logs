#Lagoon Logs

This module aims to be as close to a zero-configuration logging system for Drupal 7 sites running on the the Amazee.io Lagoon platform.


## Installation

Lagoon Logs relies on the wonderful [monolog library](https://github.com/Seldaek/monolog),
as such, the library needs to be available at runtime.

Monolog is installed via composer, and one way of accomplishing this in Drupal 7 is to install the [Composer manager](https://www.drupal.org/project/composer_manager) module.

Essential reading to help understand and set up Composer Manager under Drupal 7 can be found here:
* [Composer Manager for Drupal 6 and 7](https://www.drupal.org/node/2405805)


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
