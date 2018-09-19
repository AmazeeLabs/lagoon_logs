<?php

namespace Drupal\lagoon_logs\Logger;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Logger\LogMessageParserInterface;
use Drupal\Core\Logger\RfcLogLevel;
use Drupal\Core\Logger\RfcLoggerTrait;
use Psr\Log\LoggerInterface;

use Monolog\Logger;
use Monolog\Handler\SocketHandler;
use Monolog\Formatter\LogstashFormatter;
use Drupal\lagoon_logs\LagoonLogsLogProcessor;


class LagoonLogsLogger implements LoggerInterface {

  use RfcLoggerTrait;

  const LAGOON_LOGS_MONOLOG_CHANNEL_NAME = 'LagoonLogs';

  const LAGOON_LOGS_DEFAULT_HOST = 'application-logs.lagoon.svc';

  const LAGOON_LOGS_DEFAULT_PORT = '5555';

  const LAGOON_LOGS_DEFAULT_IDENTIFIER = 'DRUPAL';

  const LAGOON_LOGS_DEFAULT_SAFE_BRANCH = 'unset';

  const LAGOON_LOGS_DEFAULT_LAGOON_PROJECT = 'unset';

  const LAGOON_LOGS_DEFAULT_CHUNK_SIZE_BYTES = 15000;

  //The following is used to log Lagoon Logs issues if logging target
  //cannot be reached.
  const LAGOON_LOGGER_WATCHDOG_FALLBACK_IDENTIFIER = 'lagoon_logs_fallback_error';

  // protected static $logger;

  protected $hostName;

  protected $hostPort;

  protected $logFullIdentifier;

  protected $parser;


  /**
   * See
   * https://github.com/Seldaek/monolog/blob/master/doc/01-usage.md#log-levels
   *
   * @var array
   */
  protected $rfcMonologErrorMap = [
    RfcLogLevel::EMERGENCY => 600,
    RfcLogLevel::ALERT => 550,
    RfcLogLevel::CRITICAL => 500,
    RfcLogLevel::ERROR => 400,
    RfcLogLevel::WARNING => 300,
    RfcLogLevel::NOTICE => 250,
    RfcLogLevel::INFO => 200,
    RfcLogLevel::DEBUG => 100,
  ];



  public function __construct($host, $port, ConfigFactoryInterface $config_factory, LogMessageParserInterface $parser) {
    $this->hostName = $host;
    $this->hostPort = $port;
    $this->parser = $parser;
  }

  protected function mapRFCtoMonologLevels(int $rfcErrorLevel) {
    return $this->rfcMonologErrorMap[$rfcErrorLevel];
  }

  /**
   * @param $level
   * @param $message
   * @param array $context
   * @param $base_url
   *
   * @return array
   */
  protected function transformDataForProcessor(
    $level,
    $message,
    array $context,
    $base_url
  ) {
    $processorData = ["extra" => []];
    $processorData['message'] = $message;
    $processorData['base_url'] = $base_url;
    $processorData['extra']['watchdog_timestamp'] = $context['timestamp']; //Logstash will also add it's own event time
    $processorData['extra']['ip'] = $context['ip'];
    $processorData['request_uri'] = $context['request_uri'];
    $processorData['level'] = $this->mapRFCtoMonologLevels($level);
    $processorData['extra']['uid'] = $context['uid'];
    $processorData['extra']['url'] = $context['request_uri'];
    $processorData['extra']['link'] = strip_tags($context['link']);
    $processorData['extra']['type'] = $context['channel'];
    return $processorData;
  }

  protected function getRFCLevelName(int $rfcErrorLevel) {
    $levels = RfcLogLevel::getLevels();
    return $levels[$rfcErrorLevel];
  }

  /**
   * {@inheritdoc}
   */
  public function log($level, $message, array $context = []) {
    global $base_url; //Stole this from the syslog logger - not sure if it's cool?

    $logger = new Logger(self::LAGOON_LOGS_MONOLOG_CHANNEL_NAME);
    $formatter = new LogstashFormatter('DRUPAL'); //TODO: grab/set application name from somewhere ...

    $connectionString = sprintf("udp://%s:%s", $this->hostName, $this->hostPort);
    $udpHandler = new SocketHandler($connectionString);
    $udpHandler->setChunkSize(self::LAGOON_LOGS_DEFAULT_CHUNK_SIZE_BYTES);

    $udpHandler->setFormatter($formatter);

    $logger->pushHandler($udpHandler);

    $message_placeholders = $this->parser->parseMessagePlaceholders($message, $context);
    $message = strip_tags(empty($message_placeholders) ? $message : strtr($message, $message_placeholders));


    $processorData = $this->transformDataForProcessor($level, $message,
      $context, $base_url);


    $logger->pushProcessor(new LagoonLogsLogProcessor($processorData));

    $logger->log($this->mapRFCtoMonologLevels($level), $message);
  }

}
