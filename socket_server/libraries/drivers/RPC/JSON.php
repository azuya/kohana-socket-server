<?php defined('SYSPATH') or die('No direct script access.');
/**
 * User authorization library. Handles user login and logout, as well as secure
 * password hashing.
 *
 * @package    Auth
 * @author     Kohana Team
 * @copyright  (c) 2007 Kohana Team
 * @license    http://kohanaphp.com/license.html
 */
class RPC_JSON_Driver implements RPC_Driver {
	
	protected $json_obj;
	
	private $secure = false;
	
	// Helpers
	
	protected $session;
	
	protected $input;
	
	protected static $instance;
	
	public static function factory($config = array())
	{
		return new Json_Rpc($config);
	}

	public static function instance($config = array())
	{
		static $instance;

		// Load the Json_Rpc instance
		empty($instance) and $instance = new Json_Rpc($config);

		return $instance;
	}
	
	public function __construct($config = array())
	{
		$config += Kohana::config('json_rpc');
		
		$this->config = $config;
		
		$this->session = Session::instance();
		$this->input = Input::instance();
		
		if ($this->input->server('CONTENT_TYPE') != 'application/json')
		{
			throw new Kohana_Exception('json_rpc.content_type', $this->input->server('CONTENT_TYPE'));
		}
		
		$this->secure = (($this->input->server('HTTPS')) != '');
		if ($this->config['secure'] && !$this->secure)
		{
			throw new Kohana_Exception('json_rpc.unsecure');
		}
		
		self::$instance = $this;
	}
}

?>