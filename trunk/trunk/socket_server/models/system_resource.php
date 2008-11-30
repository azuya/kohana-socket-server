<?php defined('SYSPATH') or die('No direct script access.');

class System_Resource_Model extends Model {
	
	protected $rs;
	protected $destroyer;
	
	public function __construct(&$resource, $destroyer)
	{
		if (!is_resource($resource))
		{
			throw new Kohana_Exception('socket_server.error_invalid_resource');
		}
		if (!function_exists($destroyer))
		{
			throw new Kohana_Exception('socket_server.error_invalid_resource_destroyer');
		}
		$this->rs = $resource;
		$this->destroyer = $destroyer;
	}
	
	public function __destruct()
	{
		if (is_resource($this->rs))
		{
			Socket_Server::stdout('Releasing resource by calling: '.$this->destroyer);
			@call_user_func_array($this->destroyer, $this->rs);
		}
	}

} // End System Resource Model