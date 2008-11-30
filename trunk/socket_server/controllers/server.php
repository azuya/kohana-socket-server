<?php defined('SYSPATH') or die('No direct script access.');

class Server_Controller extends Controller {

	// Do not allow to run in production
	const ALLOW_PRODUCTION = FALSE;
	
	public function index()
	{
		ob_implicit_flush(TRUE);
		$ss = Socket_Server::instance();
		$ss->run();
		exit(0);
	}

} // End Server
