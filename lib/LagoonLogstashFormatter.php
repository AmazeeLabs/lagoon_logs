<?php

class LagoonLogstashFormatter {

  protected $systemName;

  protected $applicationName;

  /**
   * @param string $applicationName the application that sends the data, used as the "type" field of logstash
   * @param string $systemName      the system/machine name, used as the "source" field of logstash, defaults to the hostname of the machine
   */
  public function __construct($applicationName, $systemName = null)
  {
    // logstash requires a ISO 8601 format date with optional millisecond precision.
//    parent::__construct('Y-m-d\TH:i:s.uP');

    $this->systemName = $systemName ?: gethostname();
    $this->applicationName = $applicationName;

  }

  public function format($record) {

    if (empty($record['datetime'])) {
      $record['datetime'] = gmdate('c');
    }
    $message = array(
      '@timestamp' => $record['datetime'],
      '@version' => 1,
      'host' => $this->systemName,
    );
    if (isset($record['message'])) {
      $message['message'] = $record['message'];
    }
    if (isset($record['channel'])) {
      $message['type'] = $record['channel'];
      $message['channel'] = $record['channel'];
    }
    if (isset($record['level_name'])) {
      $message['level'] = $record['level_name'];
    }
    if ($this->applicationName) {
      $message['type'] = $this->applicationName;
    }
    if (!empty($record['extra'])) {
      foreach ($record['extra'] as $key => $val) {
        $message[$key] = $val;
      }
    }
    return $message;
  }


}
