<?php

namespace Drupal\lagoon_logs\Logger;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Logger\LogMessageParserInterface;
use Drupal\Core\Logger\RfcLogLevel;
use Drupal\Core\Logger\RfcLoggerTrait;
use Psr\Log\LoggerInterface;

use Monolog\Logger;
use Monolog\Handler\SyslogUdpHandler;
use Monolog\Formatter\LogstashFormatter;
use Drupal\lagoon_logs\LagoonLogsLogProcessor;


class LagoonLogsLogger implements LoggerInterface {

  use RfcLoggerTrait;

  // protected static $logger;

  protected $hostName;

  protected $hostPort;

  protected $parser;

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

  protected function getRFCLevelName(int $rfcErrorLevel) {
    $levels = RfcLogLevel::getLevels();
    return $levels[$rfcErrorLevel];
  }

  /**
   * {@inheritdoc}
   */
  public function log($level, $message, array $context = []) {
    global $base_url; //Stole this from the syslog logger - not sure if it's cool?

    $logger = new Logger('LagoonLogs');
    $formatter = new LogstashFormatter('DRUPAL'); //TODO: grab/set application name from somewhere ...
    $udpHandler = new SyslogUdpHandler($this->hostName, $this->hostPort);
    $udpHandler->setFormatter($formatter);

    $logger->pushHandler($udpHandler);

    $record = [];

    $message_placeholders = $this->parser->parseMessagePlaceholders($message, $context);
    $message = strip_tags(empty($message_placeholders) ? $message : strtr($message, $message_placeholders));

    $processorData['base_url'] = $base_url;
    $processorData['timestamp'] = $context['timestamp'];
    $processorData['type'] = $context['channel'];
    $processorData['extra']['ip'] = $context['ip'];
    $processorData['request_uri'] = $context['request_uri'];
    $processorData['severity'] = $this->mapRFCtoMonologLevels($level);
    $processorData['extra']['drupal_severity'] = $level;
    $processorData['extra']['uid'] = $context['uid'];
    $processorData['link'] = strip_tags($context['link']);
    $processorData['level_name'] = $this->getRFCLevelName($level);

    $logger->pushProcessor(new LagoonLogsLogProcessor($processorData));

    $logger->log($this->mapRFCtoMonologLevels($level), $message, $context);
  }

}
