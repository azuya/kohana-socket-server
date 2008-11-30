<?php defined('SYSPATH') or die('No direct script access.');

class Socket_Server_Core {
	
	protected $process_store, $clients, $pm;
	
	// Helpers
	
	protected static $instance;
	
	public static function factory($config = array())
	{
		return new Socket_Server($config);
	}

	public static function instance($config = array())
	{
		static $instance;

		// Load the Socket Server instance
		empty($instance) and $instance = new Socket_Server($config);

		return $instance;
	}
	
	public function __construct($config = array())
	{
		$config += Kohana::config('socket_server');
		
		$this->config = $config;
		
		if ($this->config['enabled'] !== TRUE)
		{
			throw new Kohana_Exception('socket_server.disabled');
		}
		
		$this->clients = 0;
		$this->pm = new Process_Manager();
		
		self::$instance = $this;
	}
	
	public function run()
	{
		ob_end_flush();
		ob_implicit_flush(TRUE);
		
		Socket_Server::stdout(Kohana::lang('socket_server.info_startup', $this->config['port']));
		
		$server_socket = @socket_create_listen($this->config['port']);
		
		if ($server_socket === false)
		{
			Socket_Server::stdout(Kohana::lang('socket_server.error_listening', $this->config['port']));
			exit(1);
		} else {
			$server_command_buffer = '';
			
			socket_set_nonblock($server_socket);
			
			$server_is_running = true;
			do {
				if ($this->pm->processes() < $this->config['max_clients'])
				{
					if (($client_socket = @socket_accept($server_socket)) === false) {
						//Socket_Server::stdout(Kohana::lang('socket_server.error_listening', socket_strerror(socket_last_error($server_socket))));
						usleep(100);
					} elseif ($client_socket < 0) {
						Socket_Server::stdout(Kohana::lang('socket_server.error_failed_connection', socket_strerror($client_socket)));
						break;
					} else {
						// Connection established
						$this->clients += 1;
						
						if (($ipc_streams = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP)) === false)
						{
							echo 'stream_socket_pair() failed.';
							exit(1);
						}
						
						//stream_set_blocking($ipc_streams[0], FALSE);
						//stream_set_blocking($ipc_streams[1], TRUE);
						
						Socket_Server::stdout(Kohana::lang('socket_server.info_new_client', $this->clients));
						
						$resource = new System_Resource_Model($client_socket, 'socket_close');
						$resource_id = 'socket'.microtime(true);
						
						$pid = pcntl_fork();
						
						if ($pid == -1)
						{
							// Failed to fork
							Socket_Server::stdout('Failed to fork! Shutting down!');
							$server_is_running = false; // trigger shutdown
						} elseif ($pid == 0) {
							// Child process
							$ppid = posix_getpid();
							
							$child_process = new Child_Process_Model($ppid, $ipc_streams[1]);
							$child_process->add_resource($resource, $resource_id);
							$child_process->add_ipc_stream($ipc_streams[1]);
							$this->pm->add_process($pid, $child_process);
							$this->pm->add_ipc_stream($ipc_streams[0], $ppid); // unnecessary
							
							$child_process->ipc_write('Client #'.$this->clients." connected to $ppid\n");
							
							//socket_set_nonblock($client_socket);
							
							$msg = "\n" . Kohana::lang('socket_server.client_welcome_msg') . "\n";
							socket_write($client_socket, $msg, strlen($msg));
							$keep_client_open = true;
							$cur_buf = '';
							$write_stream = NULL;
							$exception_stream = NULL;
							do {
								// first we check the stream (server IPC connection)
								$server_message = $child_process->ipc_read_select();
								if (NULL != $server_message)
								{
									$command_from_server = $this->simple_rpc($server_message);;
									
									if ($command_from_server[0] == 'ping')
									{
										$child_process->ipc_write("pong\n");
									} elseif ($command_from_server[0] == 'quit') {
										$msg = "You have been disconnected.\n";
										socket_write($client_socket, $msg, strlen($msg));
										$keep_client_open = false;
										break;
									} elseif ($command_from_server[0] == 'broadcast') {
										if (count($command_from_server[1]) == 1)
										{
											$args = substr($command_from_server[1][0], strpos($command_from_server[1][0], '"') + 1, strrpos($command_from_server[1][0], '"') - strpos($command_from_server[1][0], '"') - 1);
											$msg = 'Broadcast: '.stripslashes($command_from_server[1][0])."\n";
											socket_write($client_socket, $msg, strlen($msg));
										}
									}
								}
								
								// then we check the client connection socket (timeout in 1 second from listening... we're good listeners)
								$read_socket = array($client_socket);
								if (false === ($num_changed_sockets = socket_select($read_socket, $write_stream, $exception_stream, 1, 0)))
								{
									/* Error handling */
								} elseif ($num_changed_sockets > 0) {
									/* At least on one of the streams something interesting happened */
									$buffer = @socket_read($client_socket, $this->config['read_size']);
									
									if ($buffer === false)
									{
										// unexpected error, client probably was disconnected
										$keep_client_open = false;
										$msg_to_server = "kill $ppid\n";
										Socket_Server::stdout('Broken Pipe. Sending IPC kill message to parent');
										if (false === $child_process->ipc_write($msg_to_server))
										{
											Socket_Server::stdout('Error sending IPC message');
										}
									} else {
										$command = trim($buffer);
									}
									
									if ($command == 'quit') {
										//$msg = "You have been disconnected.\n";
										//socket_write($client_socket, $msg, strlen($msg));
										//$keep_client_open = false;
										$msg_to_server = "kill:$ppid\n";
										Socket_Server::stdout('Sending IPC kill message to parent');
										if (false === $child_process->ipc_write($msg_to_server))
										{
											Socket_Server::stdout('Error sending IPC message');
										}
										//break;
									} elseif ($command == 'shutdown') {
										$msg = "You have started a shutdown!\n";
										socket_write($client_socket, $msg, strlen($msg));
										Socket_Server::stdout('Shutdown server from client.');
										$msg_to_server = "shutdown\n";
										if (false === $child_process->ipc_write($msg_to_server))
										{
											Socket_Server::stdout('Error sending IPC message');
										}
										//$keep_client_open = false;
										//break;
									} elseif ($command == 'ping') {
										$talkback = "pong\n";
										socket_write($client_socket, $talkback, strlen($talkback));
									} else {
										$talkback = "Unknown command: $command\n";
										socket_write($client_socket, $talkback, strlen($talkback));
									}
									//Socket_Server::stdout(Kohana::lang('socket_server.info_command', $this->clients, $command));
								}
								usleep(200000);
							} while ($keep_client_open);
							// on our way out!
							Socket_Server::stdout(Kohana::lang('socket_server.info_disconnecting', $this->clients));
							// take care of socket resouce used
							socket_shutdown($client_socket);
							$child_process->ipc_write('killed:'.$ppid."\n");
							//$this->pm->kill($ppid);
							@socket_close($client_socket);
							exit(0);
						} else {
							$child_process = new Child_Process_Model($pid); // ipc stream for child to use
							//$child_process->add_ipc_stream($ipc_streams[1]);
							$child_process->add_resource($resource, $resource_id);
							$this->pm->add_process($pid, $child_process);
							$this->pm->add_ipc_stream($ipc_streams[0], $pid); // add ipc stream for parent to use
							// Continue listening in parent
							Socket_Server::stdout('Started child with pid: '.$pid);
						}
					}
				} else {
					Socket_Server::stdout('Max clients reached. Rejecting additional attempts.');
				}
				
				$read_streams = array(STDIN);
				
				$client_input = $this->pm->ipc_read_select($this->config['read_size']);
				if (count($client_input) > 0)
				{
					foreach($client_input as $pid => $ipc_input)
					{
						Socket_Server::stdout('Read a command from IPC ['.$pid.']: '.$ipc_input);
						$this->ipc_received($ipc_input);
						if (trim($ipc_input) == 'shutdown')
						{
							$server_is_running = false; // trigger break in while
							break; // NOW!
						}
					}
				}
				
				if (false === ($num_changed_streams = stream_select($read_streams, $write_stream = NULL, $exception_stream = NULL, 0, 0)))
				{
					/* Error handling */
				} elseif ($num_changed_streams > 0) {
					/* At least on one of the streams something interesting happened */
					foreach($read_streams as $key => $stream)
					{
						$command = trim(fread($read_streams[$key], $this->config['read_size']));
						$args = array();
						
						if ($read_streams[$key] == STDIN)
						{
							Socket_Server::stdout('Read a command from console: '.$command);
						} elseif ($read_streams[$key] == $ipc_streams[0]) {
							Socket_Server::stdout('Read a command from IPC: '.$command);
						}
						
						// handle commands from console and clients the same (not a real good idea...)
						$this->ipc_received($command);
						if ($command == 'shutdown')
						{
							$server_is_running = false; // trigger break in while
							break; // NOW!
						}
					}
				}
				
			} while ($server_is_running);
			Socket_Server::stdout(Kohana::lang('socket_server.info_shutdown'));
			// handle external facing sockets
			@socket_shutdown($server_socket);
			@socket_close($server_socket);
			// handle inter process communication streams
			$this->pm->kill_all();
		}
	}
	
	private function ipc_received($command)
	{
		$rpc = $this->simple_rpc($command);
		if ($rpc[0] == 'processes') {
			Socket_Server::stdout('Processes: '.$this->pm->processes());
		} elseif ($rpc[0] == 'ping') {
			Socket_Server::stdout('Pinging all children');
			$broadcast = "ping\n";
			$this->pm->ipc_broadcast($broadcast);
		} elseif ($rpc[0] == 'killed') {
			if (count($rpc[1]) == 1)
			{
				if ($this->pm->kill(intval($rpc[1][0])))
				{
					Socket_Server::stdout('Killed process: '.$rpc[1][0]);
				}
			}
		} elseif ($rpc[0] == 'kill') {
			if (count($rpc[1]) == 1)
			{
				$this->pm->tell_child(intval($rpc[1][0]), 'quit');
			}
		} elseif ($rpc[0] == 'broadcast') {
			if (count($rpc[1]) == 1)
			{
				$msg = 'broadcast:"'.addslashes($rpc[1][0])."\"\n";
				$this->pm->ipc_broadcast($msg);
			}
		}
	}
	
	private function simple_rpc($line)
	{
		$line = trim($line);
		$args = array();
		if (strpos($line, ':') > -1)
		{
			$parts = explode(':', $line);
			$line = $parts[0];
			$args = explode('|', $parts[1]);
		}
		return array($line, $args);
	}
	
	public static function stdout($msg)
	{
		fwrite(STDOUT, $msg . "\n");
	}
	
	public static function sig_handler($sig)
	{
		switch($sig)
		{
			case SIGTERM:
			case SIGINT:
				exit();
			break;
	
			case SIGCHLD:
				pcntl_waitpid(-1, $status);
			break;
		}
	} 
}

?>