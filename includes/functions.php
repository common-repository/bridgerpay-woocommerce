<?php
// exit if file is called directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


/******************************************/
/***** Debug functions start from here **********/
/******************************************/
if(!function_exists("alert")){

  function alert($alertText){
  	echo '<script type="text/javascript">';
  	echo "alert(\"$alertText\");";
  	echo "</script>";
  } // function alert

}// if end


if(!function_exists("js_log")){
  function js_log($alertText){
  	echo '<script type="text/javascript">';
    echo "console.log(\"$alertText\")";
  	echo "</script>";
  } // function alert

}// if end


if(!function_exists('db')){
	function db($array1)
	{
		echo "<pre>";
		var_dump($array1);
		echo "</pre>";
	}
}

if(!function_exists('dbt')){
	function dbt($array1, $ip = '', $exit = true)
	{
		if(in_array($_SERVER['REMOTE_ADDR'], ['127.0.0.1', '::1', $ip])){
			echo "<pre>";
			var_dump($array1);
			echo "</pre>";
			if($exit)
				exit();
		}
		
	}
}



if(!function_exists('dbh')){
  function dbh($debug_data){
    echo '<div style="display:none">';
    db($debug_data);
    echo '</div>';
  }
}


if(!function_exists('get_file_time')){
  function get_file_time($file){
      return date("ymd-Gis", filemtime( $file ));
  }
}




/******************************************/
/***** arrayToSerializeString **********/
/******************************************/
if(!function_exists("ArrayToSerializeString")){
  function ArrayToSerializeString($array){
    if(isset($array) && is_array($array) && count($array) >= 1)
      return serialize($array);
    else
      return serialize(array());
  }
}


/******************************************/
/***** SerializeStringToArray **********/
/******************************************/
if(!function_exists("SerializeStringToArray")){
  function SerializeStringToArray($string){
    if(isset($string) && is_array($string) && count($string) >= 1)
      return $string;
    elseif(isset($string) && $string && @unserialize($string)){
      return unserialize($string);
    }else
      return array();
  }
}



/******************************************/
/***** get_leads_fields function start from here *********/
/******************************************/
if(!function_exists("bridger_pay_remote_post")){

  function bridger_pay_remote_post($endpoint, $body = array(), $headers = array(), $method = 'POST'){    

    $default_headers = array(
      // 'Authorization' => 'Bearer '.$this->accessToken,
      // 'Content-Type' => 'application/x-www-form-urlencoded',
      'Content-Type' => 'application/json',
    );
    $headers = array_merge($default_headers, $headers);

    $options = [
      'headers'     => $headers,
      'timeout'     => 60,
      'redirection' => 5,
      'blocking'    => true,
      'httpversion' => '1.0',
      'sslverify'   => true,
      'data_format' => 'body',
      'method'      => $method,
    ];
    if($body && is_array($body) && count($body) >= 1){
      if(isset($body['upload']) && file_exists( $body['upload'] )){
        $fp = fopen($body['upload'], 'rb');
        $size = filesize($body['upload']);
        $options['body'] = fread( $fp, $size );
      }else{
        if(isset($headers['Content-Type']) && $headers['Content-Type'] == 'application/x-www-form-urlencoded')
          $body = http_build_query( $body );
        else
          $body = wp_json_encode( $body );
        $options['body'] = $body;
      }      
    }
    
    $response = wp_remote_post( $endpoint, $options );
    return $response;
	}
}
  

  /******************************************/
  /***** get_leads_fields function start from here *********/
  /******************************************/
  if(!function_exists("bridger_pay_remote_get")){
    function bridger_pay_remote_get($endpoint, $headers = array(), $method = 'GET'){
      $default_headers = array(
        // 'Authorization' => 'Bearer '.$this->accessToken,
        // 'Content-Type' => 'application/x-www-form-urlencoded',
        'Content-Type' => 'application/json',
      );
      $headers = array_merge($default_headers, $headers);
  
      $options = [
        'headers'     => $headers,
        'timeout'     => 60,
        'redirection' => 5,
        'blocking'    => true,
        'httpversion' => '1.0',
        'sslverify'   => true,
        'data_format' => 'body',
        'method'      => $method,
      ];
      $response = wp_remote_get( $endpoint, $options );
      return $response;
    }
    
  }
