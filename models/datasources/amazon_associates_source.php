<?php
/**
 * A CakePHP datasource for interacting with the amazon associates API.
 *
 * @version 0.2 (updated to newest version)
 * @author Felix Geisendörfer <felix@debuggable.com>, Tim Koschützki <tim@debuggable.com>, Nick Baker <nick@webtechnick.com>
 * @license http://www.opensource.org/licenses/mit-license.php The MIT License
 */
App::import('Xml');
class AmazonAssociatesSource extends DataSource{
	var $description = "AmazonAssociates Data Source";
	var $query = null;
	var $region = "com";
	var $_request = null;
	var $Http = null;

	/**
	  * Append HttpSocket to Http
	  */
	function __construct($config) {
		parent::__construct($config);
		App::import('HttpSocket');
		$this->Http = new HttpSocket();
	}

	/**
	  * Query the Amazon Database
	  */
	function find($type = null, $query = array()) {
	  if($type){
	    $query['SearchIndex'] = $type;
	  }
		if (!is_array($query)) {
			$query = array('Title' => $query);
		}
		foreach ($query as $key => $val) {
			if (preg_match('/^[a-z]/', $key)) {
				$query[Inflector::camelize($key)] = $val;
				unset($query[$key]);
			}
		}
		
		$this->query = am(array(
			'Service' => 'AWSECommerceService',
			'AWSAccessKeyId' => $this->config['key'],
			'Timestamp' => gmdate("Y-m-d\TH:i:s\Z"),
			'AccociateTag' => $this->config['tag'],
			'Operation' => 'ItemSearch',
			'Version' => '2009-03-31',
		), $query);
		
		return $this->__request();
	}
	
	/**
	  * Find an item by it's ID
	  *
	  * @param string id of amazon product
	  * @return mixed array of the resulting request or false if unable to contact server 
	  */
	function findById($item_id){
	  $this->query = am(
	    array(
	      'Service' => 'AWSECommerceService',
        'AWSAccessKeyId' => $this->config['key'],
        'Timestamp' => gmdate("Y-m-d\TH:i:s\Z"),
        'AccociateTag' => $this->config['tag'],
        'Version' => '2009-03-31',
	      'Operation' => 'ItemLookup',
	    ),
	    array('ItemId' => $item_id)
	  );
	  
	  return $this->__request();
	}
	
	/**
	  * Actually preform the request to AWS
	  *
	  * @return mixed array of the resulting request or false if unable to contact server
	  */
	function __request(){
	  $this->_request = $this->__signQuery();
	  $retval = $this->Http->get($this->_request);
		$retval = Set::reverse(new Xml($retval));
		return $retval;
	}
	
	/**
	  * Sign a query using sha256.
	  * this is a required step for the new Amazon API
	  *
	  * @return string request signed string. 
	  */
	function __signQuery(){
	  $method = "GET";
    $host = "ecs.amazonaws.".$this->region;
    $uri = "/onca/xml";
    
    ksort($this->query);
    // create the canonicalized query
    $canonicalized_query = array();
    foreach ($this->query as $param=>$value){
      $param = str_replace("%7E", "~", rawurlencode($param));
      $value = str_replace("%7E", "~", rawurlencode($value));
      $canonicalized_query[] = $param."=".$value;
    }
    $canonicalized_query = implode("&", $canonicalized_query);
    
    $string_to_sign = $method."\n".$host."\n".$uri."\n".$canonicalized_query;
    
    // calculate HMAC with SHA256 and base64-encoding
    $signature = base64_encode(hash_hmac("sha256", $string_to_sign, $this->config['secret'], true));
    
    // encode the signature for the request
    $signature = str_replace("%7E", "~", rawurlencode($signature));
    
    // create request
    return "http://".$host.$uri."?".$canonicalized_query."&Signature=".$signature;
	}

}
?>