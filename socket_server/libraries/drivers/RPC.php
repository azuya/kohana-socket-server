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
	
	public function ping();
	
	public function pong();
	
}

?>