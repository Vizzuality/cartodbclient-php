<?php
require_once 'cartodb.class.php'; 


$key = 'XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX';
$secret = 'XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX';
$email = 'cartodbuser@domain.com';
$password = 'pass';
$cartodb_domain = 'subdomain';
$cartodb =  new CartoDBClient($key,$secret,$email,$password,$cartodb_domain);

#Check if the $key and $secret work fine and you are authorized
if(!$cartodb->authorized) {
    error_log("uauth");
    echo("There is a problem authenticating, check the key and secret");
    die();
}

#Now we can perform queries straigh away. The second param indicates if you want
#the result to be json_decode (true) or just return the raw json string
$result = $cartodb->runSql("SELECT * from countries limit 1",false);
echo($result);


?>