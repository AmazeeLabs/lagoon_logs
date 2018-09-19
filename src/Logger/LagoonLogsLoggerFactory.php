<?php

namespace Drupal\lagoon_logs\Logger;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Logger\LogMessageParserInterface;

class LagoonLogsLoggerFactory {
  public static function create(ConfigFactoryInterface $config, LogMessageParserInterface $parser) {
    $host = $config->get('lagoon_logs.settings')->get('host');
    $port = $config->get('lagoon_logs.settings')->get('port');
    $identifier = $config->get('lagoon_logs.settings')->get('identifier');

    return new LagoonLogsLogger($host, $port, $config, $parser);
  }
}