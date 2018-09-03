<?php

class LagoonProcessor {

protected $processData;

public function __construct(array $processData) {
$this->processData = $processData;
}

/**
* @param  array $record
*
* @return array
*/
public function __invoke(array $record) {
foreach ($this->processData as $key => $value) {
if (empty($record[$key])) {
$record[$key] = $value;
}
}
return $record;
}
}