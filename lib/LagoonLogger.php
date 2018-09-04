<?php

use Monolog\Logger;
use Monolog\Handler\SocketHandler;
use Monolog\Formatter\LogstashFormatter;

class LagoonLogger {

  protected static $loggerInstance = NULL;

  protected $hostName;

  protected $hostPort;

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
    if(!in_array($watchdogErrorLevel, array_keys(self::$watchdogMonologErrorMap))) {
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
  public function __construct($hostName, $hostPort) {
    $this->hostName = $hostName;
    $this->hostPort = $hostPort;
  }

  /**
   * @param $hostName
   * @param $hostPort
   *
   * @return \LagoonLogger|null
   */
  public static function getLogger($hostName, $hostPort) {
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
    $nameArray['system'] = 'DRUPAL';
    $nameArray['lagoonProjectName'] = getenv("LAGOON_PROJECT");
    $nameArray['lagoonGitBranchName'] = getenv('LAGOON_GIT_SAFE_BRANCH');

    return implode('-', $nameArray);
  }

  /**
   * @param $logEntry
   */
  public function log($logEntry) {
    global $base_url;

    $logger = new Logger('LagoonLogs');
    $formatter = new LogstashFormatter($this->getHostProcessIndex());

    $connectionString = sprintf("tcp://%s:%s", $this->hostName, $this->hostPort);

    $udpHandler = new SocketHandler($connectionString);

    $udpHandler->setFormatter($formatter);

    $logger->pushHandler($udpHandler);
    $message = !is_null($logEntry['variables']) ? strtr($logEntry['message'], $logEntry['variables']) : $logEntry['message'];

    //let's build the data ...

    $processorData = $this->transformDataForProcessor($logEntry, $message, $base_url);


    $logger->pushProcessor(function ($record) use ($processorData) {
      foreach ($processorData as $key => $value) {
        if (empty($record[$key])) {
          $record[$key] = $value;
        }
      }
      return $record;
    });


    try {
      $logger->log($this->mapWatchdogtoMonologLevels($logEntry['severity']), $message);
    } catch (Exception $exception) {
      //TODO: This is currently not handled, although it should be
      //What might work here is either some kind of fallback, or better,
      //some kind of buffering.
    }
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