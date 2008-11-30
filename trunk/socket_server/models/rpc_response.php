<?php defined('SYSPATH') or die('No direct script access.');

class Rpc_Response_Model extends Model {
	
	protected $service, $method, $params, $id;
	
	protected $raw;
	
	public function __construct($blob)
	{
		try {
			$this->raw = $blob;
			
		} catch (Exception $error) {
			throw new Kohana_Exception('json_rpc.invalid_input', $blob);
		}
	}

} // End Json_Rpc Model