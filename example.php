<?php
require_once 'cartodb.class.php'; 


$key = 'my_key';
$secret = 'my_secret';
$cartodb =  new CartoDBClient($key,$secret);

#Check if the $key and $secret work fine and you are authorized
if(!$cartodb->authorized) {
    echo("There is a problem authenticating, check the key and secret");
    die();
}

#Now we can perform queries straigh away. The second param indicates if you want
#the result to be json_decode (true) or just return the raw json string
$result = $cartodb->runSql("SELECT *,geojson(the_geom) FROM my_table",false);
echo($result);


?>