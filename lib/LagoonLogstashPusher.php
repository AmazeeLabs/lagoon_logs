<?php

class LagoonLogstashPusher {

  public static function pushUdp($host, $port, $payload) {

    $socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
    if (!$socket) {
      throw new Exception('Could not open UDP socket for logstash: ' . $errstr);
    }

    try {
      $msg = json_encode($payload) . "\n";
      if(!@socket_sendto($socket, $msg, strlen($msg), $flags = 0, $host, $port)) {
        throw new Exception('Could not send message to Logstash server: ' . $err);
      }
    } catch (Exception $ex) {
      //we'll rethrow this, but we need to run some cleanup
      throw $ex;
    } finally {
      socket_close($socket);
    }
  }
}