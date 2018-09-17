<?php

use Monolog\Logger;
use Monolog\Handler\SocketHandler;
use Monolog\Formatter\LogstashFormatter;

class LagoonLogger {

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

  protected static $loggerInstance = NULL;

  protected $hostName;

  protected $hostPort;

  protected $logIdentifier;

  /**
   * See
   * https://github.com/Seldaek/monolog/blob/master/doc/01-usage.md#log-levels
   *
   * @var array
   */
  protected static $watchdogMonologErrorMap = [
    WATCHDOG_EMERGENCY => 600,
    WATCHDOG_ALERT => 550,
    WATCHDOG_CRITICAL => 500,
    WATCHDOG_ERROR => 400,
    WATCHDOG_WARNING => 300,
    WATCHDOG_NOTICE => 250,
    WATCHDOG_INFO => 200,
    WATCHDOG_DEBUG => 100,
  ];

  /**
   * @param $watchdogErrorLevel
   *
   * @return mixed
   */
  protected function mapWatchdogtoMonologLevels($watchdogErrorLevel) {
    if (!in_array($watchdogErrorLevel,
      array_keys(self::$watchdogMonologErrorMap))
    ) {
      return self::$watchdogMonologErrorMap[WATCHDOG_ALERT];
    }
    return self::$watchdogMonologErrorMap[$watchdogErrorLevel];
  }

  /**
   * LagoonLogger constructor.
   *
   * @param $hostName
   * @param $hostPort
   */
  protected function __construct($hostName, $hostPort, $logIdentifier) {
    $this->hostName = $hostName;
    $this->hostPort = $hostPort;
    $this->logIdentifier = $logIdentifier;
  }

  /**
   * @param $hostName
   * @param $hostPort
   *
   * @return \LagoonLogger|null
   */
  public static function getLogger(
    $hostName,
    $hostPort,
    $logIdentifier = 'DRUPAL'
  ) {
    if (!isset(self::$loggerInstance)) {
      self::$loggerInstance = new self($hostName, $hostPort, $logIdentifier);
    }
    return self::$loggerInstance;
  }

  /**
   * @return string
   *
   * This will return some kind of representation of the process
   */
  protected function getHostProcessIndex() {
    $nameArray = [];
    $nameArray['system'] = $this->logIdentifier;
    $nameArray['lagoonProjectName'] = getenv('LAGOON_PROJECT',
      self::LAGOON_LOGS_DEFAULT_LAGOON_PROJECT);
    $nameArray['lagoonGitBranchName'] = getenv('LAGOON_GIT_SAFE_BRANCH',
      self::LAGOON_LOGS_DEFAULT_SAFE_BRANCH);

    return implode('-', $nameArray);
  }

  /**
   * @param $logEntry
   */
  public function log($logEntry) {
    global $base_url;

    $logger = new Logger(self::LAGOON_LOGS_MONOLOG_CHANNEL_NAME);
    $formatter = new LogstashFormatter($this->getHostProcessIndex());

    $connectionString = sprintf("udp://%s:%s", $this->hostName, $this->hostPort);

    $udpHandler = new SocketHandler($connectionString);
    $udpHandler->setChunkSize(self::LAGOON_LOGS_DEFAULT_CHUNK_SIZE_BYTES);

    $udpHandler->setFormatter($formatter);

    $logger->pushHandler($udpHandler);
    $message = !is_null($logEntry['variables']) ? strtr($logEntry['message'], $logEntry['variables']) : $logEntry['message'];

    $processorData = $this->transformDataForProcessor($logEntry, $message,
      $base_url);

    $logger->pushProcessor(function ($record) use ($processorData) {
      foreach ($processorData as $key => $value) {
        if (empty($record[$key])) {
          $record[$key] = $value;
        }
      }
      return $record;
    });


    try {
      $logger->log($this->mapWatchdogtoMonologLevels($logEntry['severity']),
        $message);
    } catch (Exception $exception) {
      $logMessage = sprintf("Unable to reach %s to log: %s", $connectionString,
        json_encode([
          $message,
          $processorData,
        ]));
      self::logWatchdogFallbackMessage($logMessage);
    }
  }

  public static function logWatchdogFallbackMessage(
    $logMessage,
    $severity = WATCHDOG_NOTICE
  ) {
    watchdog(self::LAGOON_LOGGER_WATCHDOG_FALLBACK_IDENTIFIER, $logMessage);
  }

  /**
   * @param $logEntry
   * @param $message
   * @param $base_url
   *
   * @return array
   */
  public function transformDataForProcessor($logEntry, $message, $base_url) {
    $processorData = ["extra" => []];
    $processorData['message'] = $message;
    $processorData['base_url'] = $base_url;
    $processorData['extra']['watchdog_timestamp'] = $logEntry['timestamp']; //Logstash will also add it's own event time
    $processorData['extra']['ip'] = $logEntry['ip'];
    $processorData['request_uri'] = $logEntry['request_uri'];
    $processorData['level'] = $this->mapWatchdogtoMonologLevels($logEntry['severity']);
    $processorData['extra']['uid'] = $logEntry['uid'];
    $processorData['extra']['url'] = $logEntry['request_uri'];
    $processorData['extra']['link'] = strip_tags($logEntry['link']);
    $processorData['extra']['type'] = $logEntry['type'];
    return $processorData;
  }

}