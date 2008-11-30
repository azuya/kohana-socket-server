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
class RPC_Core {
	
	protected $secure = false;
	protected $queue;
	protected $version = 1.1;
	
	// Helpers
	
	protected static $instance;
	
	public static function factory($config = array())
	{
		return new RPC($config);
	}

	public static function instance($config = array())
	{
		static $instance;

		// Load the Json_Rpc instance
		empty($instance) and $instance = new RPC($config);

		return $instance;
	}
	
	public function __construct($config = array())
	{
		$config += Kohana::config('rpc');
		
		$this->config = $config;
		
		// Set the driver class name
		$driver = 'RPC_'.$config['driver'].'_Driver';
		
		if ( ! Kohana::auto_load($driver))
			throw new Kohana_Exception('core.driver_not_found', $config['driver'], get_class($this));
		
		// Load the driver
		$driver = new $driver($config);

		if ( ! ($driver instanceof RPC_Driver))
			throw new Kohana_Exception('core.driver_implements', $config['driver'], get_class($this), 'RPC_Driver');
		
		// Load the driver for access
		$this->driver = $driver;
		
		self::$instance = $this;
		
		Kohana::log('debug', 'Auth Library loaded');
	}
}

?>