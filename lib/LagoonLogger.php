<?php

class LagoonLogger {

  const LAGOON_LOGS_MONOLOG_CHANNEL_NAME = 'LagoonLogs';

  const LAGOON_LOGS_DEFAULT_HOST = 'application-logs.lagoon.svc';

  const LAGOON_LOGS_DEFAULT_PORT = '5140';

  const LAGOON_LOGS_DEFAULT_IDENTIFIER = 'drupal';

  const LAGOON_LOGS_DEFAULT_SAFE_BRANCH = 'safe_branch_unset';

  const LAGOON_LOGS_DEFAULT_LAGOON_PROJECT = 'project_unset';

  const LAGOON_LOGS_DEFAULT_CHUNK_SIZE_BYTES = 15000;

  //The following is used to log Lagoon Logs issues if logging target
  //cannot be reached.
  const LAGOON_LOGGER_WATCHDOG_FALLBACK_IDENTIFIER = 'lagoon_logs_fallback_error';

  protected static $loggerInstance = NULL;

  protected $hostName;

  protected $hostPort;
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

  protected static $levelNames = [
    600 => 'EMERGENCY',
    550 => 'ALERT',
    500 => 'CRITICAL',
    400 => 'ERROR',
    300 => 'WARNING',
    250 => 'NOTICE',
    200 => 'INFO',
    100 => 'DEBUG',
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

  protected function mapWatchdogToMonologLevelNames($watchdogErrorLevel) {
    return self::$levelNames[self::mapWatchdogtoMonologLevels($watchdogErrorLevel)];
  }

  /**
   * LagoonLogger constructor.
   *
   * @param $hostName
   * @param $hostPort
   */
  protected function __construct($hostName, $hostPort) {
    $this->hostName = $hostName;
    $this->hostPort = $hostPort;
  }

  /**
   * @param $hostName
   * @param $hostPort
   *
   * @return \LagoonLogger|null
   */
  public static function getLogger(
    $hostName,
    $hostPort
  ) {
    if (!isset(self::$loggerInstance)) {
      self::$loggerInstance = new self($hostName, $hostPort);
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
    $nameArray['lagoonProjectName'] = getenv('LAGOON_PROJECT') ?: self::LAGOON_LOGS_DEFAULT_LAGOON_PROJECT;
    $nameArray['lagoonGitBranchName'] = getenv('LAGOON_GIT_SAFE_BRANCH') ?: self::LAGOON_LOGS_DEFAULT_SAFE_BRANCH;

    return implode('-', $nameArray);
  }

  /**
   * @param $logEntry
   */
  public function log($logEntry) {
    global $base_url;

    $formatter = new LagoonLogstashFormatter($this->getHostProcessIndex());

    $message = !is_null($logEntry['variables']) ? strtr($logEntry['message'], $logEntry['variables']) : $logEntry['message'];

    $processorData = $this->transformDataForProcessor($logEntry, $message,
      $base_url);

    try {
      LagoonLogstashPusher::pushUdp($this->hostName, $this->hostPort, $formatter->format($processorData));
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
  protected function transformDataForProcessor($logEntry, $message, $base_url) {
    $processorData = ["extra" => []];
    $processorData['channel'] = $logEntry['type'];
    $processorData['message'] = $message;
    $processorData['base_url'] = $base_url;
    $processorData['extra']['ip'] = $logEntry['ip'];
    $processorData['extra']['request_uri'] = $logEntry['request_uri'];
    $processorData['level_name'] = $this->mapWatchdogToMonologLevelNames($logEntry['severity']);
    $processorData['extra']['uid'] = $logEntry['uid'];
    $processorData['extra']['link'] = strip_tags($logEntry['link']);
    $processorData['extra']['application'] = self::LAGOON_LOGS_DEFAULT_IDENTIFIER;
    return $processorData;
  }

}