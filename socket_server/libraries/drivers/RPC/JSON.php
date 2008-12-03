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
interface RPC_Driver {
	
	// returns a RPC_Response_Model
	public function service(RPC_Request_Model $request);
	
	// returns a RPC_Response_Model
	public function notify();
	
	public function ping();
	
	public function pong();
	
}

?>