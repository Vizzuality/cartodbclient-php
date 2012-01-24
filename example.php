<?php

require_once 'cartodb.class.php';
require_once 'cartodb.config.php';

$config = getConfig();
$cartodb =  new CartoDBClient($config);

#Check if the $key and $secret work fine and you are authorized
if (!$cartodb->authorized) {
  error_log("uauth");
  print 'There is a problem authenticating, check the key and secret.';
  exit();
}

#Now we can perform queries straigh away. The second param indicates if you want
#the result to be json_decode (true) or just return the raw json string

$schema = array(
  'col1' => 'text',
  'col2' => 'integer'
);
$response = $cartodb->createTable('example', $schema);
print_r($response);

$response = $cartodb->addColumn('example', 'col3', 'text');
print_r($response);

$response = $cartodb->dropColumn('example', 'col2');
print_r($response);

$data = array(
  'col1' => "'row1 - col1'",
  'col3' => "'row1 - col3'",
);
$response = $cartodb->insertRow('example', $data);
$row = array_pop($response['return']['rows']);
print_r($row);

$data['col1'] = "'row1 - col1 new'";
$data['col3'] = "'row1 - col3 new'";
$response = $cartodb->updateRow('example', $row->id, $data);
print_r($response);

$response = $cartodb->getRow('example', $row->id);
$print_r($response);

$response = $cartodb->deleteRow('example', $row->id);
print_r($response);

$response = $cartodb->getRecords('example', array('rows_per_page' => 0));
$total_rows = $response['return']['total_rows'];
$response = $cartodb->getRecords('example', array('rows_per_page' => $total_rows));
print_r($response);

$response = $cartodb->dropTable('example');
print_r($response);

?>
