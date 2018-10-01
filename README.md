# Lagoon Logs

This module aims to be as close to a zero-configuration logging system for Drupal 7 sites running on the the Amazee.io Lagoon platform.


## Installation

You should be able to simply download and enable the lagoon logs package.

```
drush dl lagoon_logs
drush pm-enable lagoon_logs
```


## Use/configuration

Lagoon Logs is meant to be a Zero Configuration setup for Amazee.IO Lagoon projects.
Once the library has been installed,it will, by default send its logs to a Logstash instance at "application-logs.lagoon.svc:5140".

You're able to view the effective settings for the current Drupal installation by visiting the page [admin/settings/lagoon_logs](admin/settings/lagoon_logs)
