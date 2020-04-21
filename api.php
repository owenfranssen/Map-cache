<?php
error_reporting(E_ERROR|E_USER_WARNING|E_USER_NOTICE);
ini_set('display_errors', 'On');

require_once("includes/mysqli.php");
require_once("includes/rest.php");

class API extends REST {

	public $data = '';

	private $mysqli = NULL;
	private $cache_dir = 'cache';
	private $cache_request = '';

  private $mapbox_access_token = "";

//! Functions
	public function __construct() {
		parent::__construct();
		IF(!($this->mysqli = Database())) $this->_request["nocache"] = FALSE;
	}

	// private function cache($string) {
	// 	file_put_contents($this->cache_request, $string);
	// }

	// private function json($data){
  //   IF(is_array($data)) {
  //     IF(!($json = @json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_NUMERIC_CHECK | JSON_INVALID_UTF8_SUBSTITUTE))) {
  //       return json_last_error_msg();
  //     } ELSE {
  //       return $json;
  //     }
  //   }
  // }

	public function requestedAction(){
    $params = explode("/", $_REQUEST['rquest']);
    IF(isset($_REQUEST['id'])) $func = 'property';
    ELSEIF(isset($_REQUEST['lat'])) {
      $func = 'map';
      $this->_request['latitude'] = $_REQUEST['lat'];
      $this->_request['longitude'] = $_REQUEST['lon'];
      $this->_request['zoom'] = $_REQUEST['zoom'];
      $this->_request['dimensions'] = $_REQUEST['dimensions'];
    } ELSE $func = $_REQUEST['func'] ?: array_shift($params);
'', implode('-', $this->_request)))).'.txt';

    IF(count($params) > 0) $this->_request = array_merge($params, $this->_request);


    IF(method_exists($this,$func)) :
		    $this->$func();

    ELSE : 
      $this->response('The requested action does not exist', 501);
    ENDIF;
	}

  private function clearcache() {
	  $files = glob("{$this->cache_dir}/*");
		FOREACH($files AS $file) :
			IF(is_file($file) && $file != __FILE__)
				unlink($file);
		ENDFOREACH;
  }

  private function get_data($url, &$info) {
    $ch = curl_init();
    $timeout = 5;
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
    $data = curl_exec($ch);
    $info = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    curl_close($ch);
    return $data;
  }

  // Clear cached map(s)
  // EXAMPLE /ID|all
  private function clear() {
    $params = array(
      "all"       => $this->_request[0] == "all" ? true : false,
      "id"        => $this->_request[0] ?: $this->_request['id']
    );

    IF($params['all'] === true) {
      // clear all cached maps

    } ELSEIF($params['id'] > 0) {
      $this->_request['id'] = $params['id'];
      $data = $this->property(true);
      $filename = "cache/{$data[latitude]},{$data[longitude]}*";
      $maps = glob($filename);
      FOREACH($maps AS $file) {
        IF(is_file($file) && $file != __FILE__)
          unlink($file);
      }
    }
  }

  // Return map image
  // EXAMPLE: /LAT,LON,ZOOM
  private function map() {
  	$params = array(
        'dimensions'          => $this->_request["dimensions"]    ?: '1000x500',
      	'latitude'            => $this->_request["latitude"]      ?: 0,
			  'longitude'           => $this->_request["longitude"]     ?: 0,
				'zoom'                => $this->_request["zoom"]          ?: ''
      );

    $extension = "png";
    $filename = "cache/{$params[latitude]},{$params[longitude]},{$params[dimensions]}.{$extension}";

    IF(file_exists($filename)) {
      $string = file_get_contents($filename);
      header("HTTP/1.1 200 OK");
      header("Content-Type: image/png");
      echo $string;

    } ELSE {
      $url = "https://api.mapbox.com/styles/v1/mapbox/outdoors-v11/static/pin-l-home+0080c0({$params[longitude]},{$params[latitude]})/{$params[longitude]},{$params[latitude]},{$params[zoom]}/{$params[dimensions]}?access_token={$this->mapbox_access_token}";

      $contenttype = "";
      $string = $this->get_data($url, $contenttype);
      $extension = (strpos($contenttype, 'jpeg') ? 'jpg' : 'png');

      IF($extension != ".png") $filename = "{$this->cache_dir}/{$params[latitude]},{$params[longitude]},{$params[dimensions]}.{$extension}";

      IF( strlen($string) == 0 ) ECHO("MAPI: Map not found");
      ELSE {
        file_put_contents($filename, $string);

        header("HTTP/1.1 200 OK");
        header("Content-Type: {$contenttype}");
        echo $string;
      }
    }
  }

  //! B&B Database Functions

	// Return the details of a single property
	// EXAMPLE: /BNB_ID
	// EXAMPLE: /LAT,LNG,ZOOM
	private function property($return = false) {
  	$params = array(
      	'debug'            => $this->_request["debug"]           ?: false,
			  'id'               => $this->_request["id"]              ?: 0,
				'dimensions'       => $this->_request["dimensions"]      ?: '',
				'name'             => $this->_request["name"]            ?: '',
				'town'             => $this->_request["urltown"]         ?: ''
			);

		$query = "SELECT
            	latitude,
            	longtitude AS longitude,
            	map_zoom AS zoom

            FROM bnb
              JOIN options ON bnb.bnb_id = options.bnb_ref
            WHERE (? != '' AND ? != 0 AND bnb_id = ?)
            	OR (? != '' AND urlbnbname = ? AND urltown = ?)
            LIMIT 1";
    IF(!($stmnt = $this->mysqli->prepare( $query ))) : ECHO($this->mysqli->error);
		ELSEIF(!$stmnt->bind_param('iiisss',
    		$params['id'],
    		$params['id'],
    		$params['id'],
    		$params['name'],
    		$params['name'],
    		$params['town'])) : ECHO($stmnt->error);
		ELSEIF(!$stmnt->execute()) : ECHO($stmnt->error);
		ELSE :
			$result = $stmnt->get_result();
			IF($result->num_rows > 0) :
        $row = $result->fetch_object();
    		
        $this->_request['latitude'] = $row->latitude;
        $this->_request['longitude'] = $row->longitude;
        $this->_request['zoom'] = $row->zoom;
        IF(!$return) $this->map();
        ELSE return [
            'latitude' => $row->latitude,
            'longitude' => $row->longitude,
            'zoom' => $row->zoom
          ];
  		ENDIF;
		ENDIF;
  }
   
  /* END RETRIEVAL FUNCTIONS */

} // END class API

$api = new API();
//IF($api->security())
$api->requestedAction();
?>
