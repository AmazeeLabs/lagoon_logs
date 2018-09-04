<?php

use Monolog\Logger;
use Monolog\Handler\SocketHandler;
use Monolog\Formatter\LogstashFormatter;

class LagoonLogger {

  protected static $loggerInstance = NULL;

  protected $hostName;

  protected $hostPort;

  protected $parser;

  protected $watchdogMonologErrorMap = [
    WATCHDOG_EMERGENCY => 600,
    WATCHDOG_ALERT => 550,
    WATCHDOG_CRITICAL => 500,
    WATCHDOG_ERROR => 400,
    WATCHDOG_WARNING => 300,
    WATCHDOG_NOTICE => 250,
    WATCHDOG_INFO => 200,
    WATCHDOG_DEBUG => 100,
  ];

  protected function mapWatchdogtoMonologLevels($watchdogErrorLevel) {
    return $this->watchdogMonologErrorMap[$watchdogErrorLevel];
  }

  public function __construct($hostName, $hostPort) {
    $this->hostName = $hostName;
    $this->hostPort = $hostPort;
    $this->parser = NULL;
  }

  public static function getLogger($hostName, $hostPort) {
    if (!isset(self::$loggerInstance)) {
      self::$loggerInstance = new self($hostName, $hostPort);
    }
    return self::$loggerInstance;
  }

  /**
   * This will return some kind of representation of the process
   */
  protected function getHostProcessIndex() {
    $nameArray = [];
    $nameArray['system'] = 'DRUPAL';
    $nameArray['lagoonProjectName'] = getenv("LAGOON_PROJECT");
    $nameArray['lagoonGitBranchName'] = getenv('LAGOON_GIT_SAFE_BRANCH');

    return implode('-', $nameArray);
  }

  public function log($logEntry) {

    global $base_url; //Stole this from the syslog logger - not sure if it's cool?

    $logger = new Logger('LagoonLogs');
    $formatter = new LogstashFormatter($this->getHostProcessIndex()); //TODO: grab/set application name from somewhere ...

    $connectionString = sprintf("tcp://%s:%s", $this->hostName, 5141);//$this->hostPort);
    $udpHandler = new SocketHandler($connectionString);
    $udpHandler->setFormatter($formatter);

    $logger->pushHandler($udpHandler);
    $message = !is_null($logEntry['variables']) ? strtr($logEntry['message'], $logEntry['variables']) : $logEntry['message'];

    //let's build the data ...

    $processorData = ["extra" => []];
    $processorData['message'] = $message;
    $processorData['base_url'] = $base_url;
    $processorData['extra']['watchdog_timestamp'] = $logEntry['timestamp']; //Logstash will also add it's own event time
    //    $processorData['type'] = $context['channel'];
    $processorData['extra']['ip'] = $logEntry['ip'];
    $processorData['request_uri'] = $logEntry['request_uri'];
    $processorData['level'] = $this->mapWatchdogtoMonologLevels($logEntry['severity']);
    $processorData['extra']['server'] = $level;
    $processorData['extra']['uid'] = $logEntry['uid'];
    $processorData['extra']['url'] = $logEntry['request_uri'];
    $processorData['extra']['link'] = strip_tags($logEntry['link']);
    $processorData['extra']['type'] = $logEntry['type'];



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
      //TODO: come up with some sane fallback for when we can't reach the logging endpoint.
    }
  }


}