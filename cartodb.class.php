<?php

/**
 * CartoDBClient
 *
 * A simple CartoDB client to perform requests against the CartoDB API.
 * Internally it uses OAuth, curl and json_decode
 *
 * Requirements:
 * -----------------
 * PHP version 5.2
 * PHP/CURL
 *
 *
 * Example use:
 * -----------------
 * $cartodb =  new CartoDBClient('my_cartodb_key','my_cartodb_secret');
 * echo $cartodb->runSql("SELECT *,geojson(the_geom) FROM my_table");
 *
 */

require_once 'oauth.php';

class CartoDBClient {
  public $key;
  public $secret;
  public $email;
  public $password;
  public $subdomain;
  public $authorized = FALSE;
  public $json_decode = TRUE;
  private $credentials = array();
  private $OAUTH_URL;
  private $API_URL;

  private $TEMP_TOKEN_FILE_PATH;

  function __construct($config) {
    foreach ($config as $key => $value) {
      $this->$key = $value;
    }

    $this->TEMP_TOKEN_FILE_PATH = sys_get_temp_dir() . '/' . $this->subdomain . '.cartodbtempkey.txt';
    $this->OAUTH_URL = 'https://' . $this->subdomain . '.cartodb.com/oauth/';
    $this->API_URL = 'https://' . $this->subdomain . '.cartodb.com/api/v1/';

    try {
      if (file_exists($this->TEMP_TOKEN_FILE_PATH)) {
        $this->credentials = unserialize(file_get_contents($this->TEMP_TOKEN_FILE_PATH));
      }
      else {
        $this->credentials = $this->getAccessToken();
      }
      $this->authorized = true;
    }
    catch (Exception $e) {
      $this->authorized = false;
    }
  }

  function __toString() {
    return "OAuthConsumer[key=$this->key, secret=$this->secret]";
  }

  private function request($uri, $method = 'GET', $args = array()) {
    $url = $this->API_URL . $uri;
    $sig_method = new OAuthSignatureMethod_HMAC_SHA1();
    $consumer = new OAuthConsumer($this->key, $this->secret, NULL);
    $token = new OAuthToken($this->credentials['oauth_token'], $this->credentials['oauth_token_secret']);

    $params = isset($args['params']) ? $args['params'] : array();
    $acc_req = OAuthRequest::from_consumer_and_token($consumer, $token, $method, $url, $params);
    if (!isset($args['headers']['Accept'])) {
      $args['headers']['Accept'] = 'application/json';
    }

    $acc_req->sign_request($sig_method, $consumer, $token);
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $acc_req->to_postdata());
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_ANY);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $args['headers']);

    $response = array();
    $response['return'] = ($this->json_decode) ? (array) json_decode(curl_exec($ch)) :
      curl_exec($ch);
    $response['info'] = curl_getinfo($ch);

    curl_close($ch);

    if ($response['info']['http_code'] == 401) {
      $this->authorized = false;
      $this->credentials = $this->getAccessToken();
      return $this->request($uri, $method, $args);
    }

    return $response;
  }

  public function runSql($sql) {
    $params = array('q' => $sql);
    $response = $this->request('sql', 'POST', array('params' => $params));

    if ($response['info']['http_code'] != 200) {
      throw new Exception('There was a problem with your request: ' . var_export($response['return'], true));
    }
    return $response;
  }

  /**
   * Creates a new table
   * @param string $tablename
   */
  public function createTable($table) {
    return $this->request('tables', 'POST', array('params' => array('name' => $table)));
  }

  /**
   * @deprecated
   */
  public function dropTable($table) {
    trigger_error("Deprecated method. Use instead dropTableVisualization()", E_USER_NOTICE);
  }

  public function addColumn($table, $column_name, $column_type) {
    $params = array();
    $params['name'] = $column_name;
    $params['type'] = $column_type;
    return $this->request("tables/$table/columns", 'POST', array('params' => $params));
  }

  public function dropColumn($table, $column) {
    return $this->request("tables/$table/columns/$column", 'DELETE');
  }

  public function changeColumn($table, $column, $new_column_name, $new_column_type) {
    $params = array();
    $params['name'] = $new_column_name;
    $params['type'] = $new_column_type;
    return $this->request("tables/$table/columns/$column", 'PUT', array('params' => $params));
  }

  /**
   * Returns all the data from a table given its name
   */
  public function getTable($table_name) {
    return $this->request("tables/$table_name");
  }

  /**
   * @deprecated
   */
  public function getTables() {
    trigger_error("Deprecated method. Use instead getTableVisualizations()", E_USER_NOTICE);
  }

  /**
   * Searches for a table in all visualizations and if finds one who is a table visualization/canonical visualization, 
   * deletes it (this will delete the associated table).
   */
  public function dropTableVisualization($table_name) {
    $result = false;
    $table_name = strtolower($table_name);

    $allVisualizations = $this->getVisualizations();
    if (!empty($allVisualizations['return']) && isset($allVisualizations['return']['visualizations'])) {
      $tables = array();

      for ($idx = 0, $size = count($allVisualizations['return']['visualizations']); $idx < $size && !$result; $idx++) {
        if ($allVisualizations['return']['visualizations'][$idx]->type == 'table') {
          $visTableName = strtolower($allVisualizations['return']['visualizations'][$idx]->name);
          $visId = $allVisualizations['return']['visualizations'][$idx]->id;
          if ($visTableName === $table_name) {
            $result = $this->request("viz/$visId", 'DELETE');
          }
        }
      }
    }

    return $result;
  }

  /**
   * Returns all visualizations
   */
  public function getVisualizations() {
    return $this->request('viz');
  }

  /**
   * Returns all available tables, by getting a list of visualizations and then grabbing those tables 
   * whose visualization is of type=table ('table visualization' or 'canonical visualization')
   */
  public function getTableVisualizations() {
    $allVisualizations = $this->getVisualizations();
    if (!empty($allVisualizations['return']) && isset($allVisualizations['return']['visualizations'])) {
      $tables = array();

      for ($idx = 0, $size = count($allVisualizations['return']['visualizations']); $idx < $size; $idx++) {
        if ($allVisualizations['return']['visualizations'][$idx]->type === 'table') {
          $tableDataResponse = $this->getTable($allVisualizations['return']['visualizations'][$idx]->name);
          if (!empty($tableDataResponse['return'])) {
            $tables[] = $tableDataResponse['return'];
          }
        }
      }
      unset($allVisualizations['return']['visualizations']);
      $allVisualizations['return']['tables'] = $tables;
      $allVisualizations['return']->total_entries = count($tables);
    }

    return $allVisualizations;
  }

  public function getRow($table, $row) {
    return $this->request("tables/$table/records/$row");
  }

  /**
   * Inserts a single row of data in a table
   * @param string $table Name of the table to inser the row into
   * @param array $data [ column_name => column_value ]
   */
  public function insertRow($table, $data) {
    $keys = implode(',', array_keys($data));
    $values = implode(',', array_values($data));
    $sql = "INSERT INTO $table ($keys) VALUES($values);";
    $sql .= "SELECT $table.cartodb_id as id, $table.* FROM $table ";
    $sql .= "WHERE cartodb_id = currval('public." . $table . "_cartodb_id_seq');";
    return $this->runSql($sql);
  }

  public function updateRow($table, $row_id, $data) {
    $keys = implode(',', array_keys($data));
    $values = implode(',', array_values($data));
    $sql = "UPDATE $table SET ($keys) = ($values) WHERE cartodb_id = $row_id;";
    $sql .= "SELECT $table.cartodb_id as id, $table.* FROM $table ";
    $sql .= "WHERE cartodb_id = currval('public." . $table . "_cartodb_id_seq');";
    return $this->runSql($sql);
  }

  public function deleteRow($table, $row_id) {
    $sql = "DELETE FROM $table WHERE cartodb_id = $row_id;";
    return $this->runSql($sql);
  }

  /**
   * Gets all the records of a defined table.
   * @param $table the name of table
   * @param $params array of parameters.
   *   Valid parameters:
   *   - 'rows_per_page' : Number of rows per page.
   *   - 'page' : Page index.
   */
  public function getRecords($table, $params = array()) {
    return $this->request("tables/$table/records", 'GET', array('params' => $params));
  }

  private function getAccessToken() {
    $sig_method = new OAuthSignatureMethod_HMAC_SHA1();
    $consumer = new OAuthConsumer($this->key, $this->secret, NULL);

    $params = array(
      'x_auth_username' => $this->email,
      'x_auth_password' => $this->password,
      'x_auth_mode'     => 'client_auth'
    );

    $acc_req = OAuthRequest::from_consumer_and_token($consumer, NULL, "POST",
    $this->OAUTH_URL . 'access_token', $params);

    $acc_req->sign_request($sig_method, $consumer, NULL);
    $ch = curl_init($this->OAUTH_URL . 'access_token');
    curl_setopt($ch, CURLOPT_POST, True);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $acc_req->to_postdata());
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_ANY);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

    $response = curl_exec($ch);
    $info = curl_getinfo($ch);
    curl_close($ch);

    if ($info['http_code'] != 200) {
      throw new Exception('Authorization failed for this key and secret.');
    }


    //Success
    $credentials = $this->parse_query($response, true);
    $this->authorized = true;
    // Now that we have the token, lets save it
    @unlink($this->TEMP_TOKEN_FILE_PATH);
    if ($f = @fopen($this->TEMP_TOKEN_FILE_PATH, 'w')) {
      if (@fwrite($f, serialize($credentials))) {
        @fclose($f);
      }
      else {
        die('Could not write to file ' . $this->TEMP_TOKEN_FILE_PATH);
      }
    }
    else {
      die('Could not open file ' . $this->TEMP_TOKEN_FILE_PATH);
    }
    return $credentials;
  }


  private function http_parse_headers($header) {
    $retVal = array();
    $fields = explode("\r\n", preg_replace('/\x0D\x0A[\x09\x20]+/', ' ', $header));
    foreach ($fields as $field) {
      if (preg_match('/([^:]+): (.+)/m', $field, $match)) {
        $match[1] = preg_replace('/(?<=^|[\x09\x20\x2D])./e', 'strtoupper("\0")', strtolower(trim($match[1])));
        if (isset($retVal[$match[1]])) {
          $retVal[$match[1]] = array($retVal[$match[1]], $match[2]);
        }
        else {
          $retVal[$match[1]] = trim($match[2]);
        }
      }
    }
    return $retVal;
  }

  private function parse_query($var, $only_params = false) {
    /**
     *  Use this function to parse out the query array element from
     *  the output of parse_url().
     */
    if (!$only_params) {
      $var = parse_url($var, PHP_URL_QUERY);
      $var = html_entity_decode($var);
    }

    $var = explode('&', $var);
    $arr = array();

    foreach ($var as $val) {
      $x = explode('=', $val);
      $arr[$x[0]] = $x[1];
    }
    unset($val, $x, $var);
    return $arr;
  }
}