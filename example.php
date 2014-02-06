<?php

require_once 'cartodb.class.php';
require_once 'cartodb.config.php';

$config = getConfig();
$cartodb =  new CartoDBClient($config);

// Check if the $key and $secret work fine and you are authorized
if (!$cartodb->authorized) {
  error_log("uauth");
  print 'There is a problem authenticating, check the key and secret.';
  exit();
}

// Now we can perform queries straigh away.

$tableName = 'example';

$response = $cartodb->getTableVisualizations();
print_r($response);

$response = $cartodb->createTable($tableName);
print_r($response);

$response = $cartodb->addColumn($tableName, 'col3', 'text');
print_r($response);

$response = $cartodb->dropColumn($tableName, 'col2');
print_r($response);

$data = array(
  'col1' => "'row1 - col1'",
  'col3' => "'row1 - col3'",
);
$response = $cartodb->insertRow($tableName, $data);
$row = array_pop($response['return']['rows']);
print_r($row);

$data['col1'] = "'row1 - col1 new'";
$data['col3'] = "'row1 - col3 new'";
$response = $cartodb->updateRow($tableName, $row->id, $data);
print_r($response);

$response = $cartodb->getRow($tableName, $row->id);
print_r($response);

$response = $cartodb->deleteRow($tableName, $row->id);
print_r($response);

$response = $cartodb->getRecords($tableName, array('rows_per_page' => 0));
$total_rows = $response['return']['total_rows'];
$response = $cartodb->getRecords($tableName, array('rows_per_page' => $total_rows));
print_r($response);

$response = $cartodb->dropTableVisualization($tableName);
print_r($response);

?>