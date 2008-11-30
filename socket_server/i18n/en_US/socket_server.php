<?php defined('SYSPATH') or die('No direct script access.');

$lang = array
(
	'error_disabled'		=> 'Server is disabled. See config/socket_server.php to enable it.',
	'error_listening'		=> 'Error listening on port %s',
	'error_accept'			=> 'socket_accept() failed: reason: %s',
	'error_read'			=> 'socket_read() failed: reason: %s',
	'error_failed_connection'	=> 'Failed establishing new connection: %s',
	'error_invalid_resource_destroyer'	=> 'Invalid resource destroyer function supplied: %s',
	'error_invalid_resource'			=> 'Invalid resource supplied',
	'error_zombies'			=> 'Zombies found: %d',
	'error_zombie'			=> 'Zombie process: %d',
	
	'info_startup'			=> 'Server is starting up on port %s',
	'info_new_client'		=> 'Client # %s connected.',
	'info_disconnecting'	=> 'Client # %s disconnecting.',
	'info_shutdown'			=> 'Shutting down the server.',
	'info_command'			=> 'Client # %s Command: %s',
	'info_command'			=> 'Client # %s Command: %s',
	
	'client_welcome_msg'	=> 'Welcome to the Socket Server!'
);