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
			if (($ipc_streams = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP)) === false)
			{
				echo 'stream_socket_pair() failed.';
				exit(1);
			}
			
			$server_command_buffer = '';
			
			stream_set_blocking($ipc_streams[0], FALSE);
			stream_set_blocking($ipc_streams[1], FALSE);
			socket_set_nonblock($server_socket);
			
			$server_is_running = true;
			do {
				if (($client_socket = @socket_accept($server_socket)) === false) {
					//Socket_Server::stdout(Kohana::lang('socket_server.error_listening', socket_strerror(socket_last_error($server_socket))));
					usleep(100);
				} elseif ($client_socket < 0) {
					Socket_Server::stdout(Kohana::lang('socket_server.error_failed_connection', socket_strerror($client_socket)));
					break;
				} else {
					// Connection established
					$this->clients += 1;
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
						
						fclose($ipc_streams[0]);
						
						$child_process = new Child_Process_Model($ppid, $ipc_streams[1]);
						$child_process->add_resource($resource, $resource_id);
						$this->pm->add_process($pid, $child_process);
						
						fwrite($ipc_streams[1], 'Client #'.$this->clients." connected to $ppid\n");
						
						//socket_set_nonblock($client_socket);
						
						$msg = "\n" . Kohana::lang('socket_server.client_welcome_msg') . "\n";
						socket_write($client_socket, $msg, strlen($msg));
						$keep_client_open = true;
						$cur_buf = '';
						$write_stream = NULL;
						$exception_stream = NULL;
						do {
							$read_socket = array($client_socket);
							$read_stream = array($ipc_streams[1]);
							//echo '.';
							// first we check the stream (server IPC connection)
							if (false === ($num_changed_streams = stream_select($read_stream, $write_stream, $exception_stream, 0, 0)))
							{
								/* Error handling */
							} elseif ($num_changed_streams > 0) {
								/* At least on one of the streams something interesting happened */
								$line = trim($child_process->ipc_read());
								$command_from_server = $line;
								$args = '';
								if (strpos($line, ':') > -1)
								{
									$command_from_server = substr($line, 0, strpos($line, ':'));
									$args = substr($line, strpos($line, ':') + 1);
								}
								if ($command_from_server == 'ping')
								{
									$child_process->ipc_write("$ppid:pong\n");
								} elseif ($command_from_server == "broadcast") {
									$args = substr($args, strpos($args, '"') + 1, strrpos($args, '"') - strpos($args, '"') - 1);
									$msg = 'Broadcast: '.stripslashes($args)."\n";
									socket_write($client_socket, $msg, strlen($msg));
								}
								//Socket_Server::stdout('Command from server received: '.$command_from_server);
							}
							
							// then we check the client connection socket (timeout in 1 second from listening)
							if (false === ($num_changed_sockets = socket_select($read_socket, $write_stream, $exception_stream, 1, 0)))
							{
								/* Error handling */
							} elseif ($num_changed_sockets > 0) {
								/* At least on one of the streams something interesting happened */
								$buffer = @socket_read($client_socket, 1024);
								
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
									$msg = "You have been disconnected.\n";
									socket_write($client_socket, $msg, strlen($msg));
									$keep_client_open = false;
									$msg_to_server = "kill $ppid\n";
									Socket_Server::stdout('Sending IPC kill message to parent');
									if (false === $child_process->ipc_write($msg_to_server))
									{
										Socket_Server::stdout('Error sending IPC message');
									}
									break;
								} elseif ($command == 'shutdown') {
									$msg = "You have started a shutdown!\n";
									socket_write($client_socket, $msg, strlen($msg));
									Socket_Server::stdout('Shutdown server from client.');
									$msg_to_server = "shutdown\n";
									if (false === $child_process->ipc_write($msg_to_server))
									{
										Socket_Server::stdout('Error sending IPC message');
									}
									$keep_client_open = false;
									break;
								}
								
								$talkback = "Unknown command: $command\n";
								socket_write($client_socket, $talkback, strlen($talkback));
								Socket_Server::stdout(Kohana::lang('socket_server.info_command', $this->clients, $command));
							}
							usleep(200000);
						} while ($keep_client_open);
						// on our way out!
						Socket_Server::stdout(Kohana::lang('socket_server.info_disconnecting', $this->clients));
						// take care of socket resouce used
						socket_shutdown($client_socket);
						$this->pm->kill($ppid);
						@socket_close($client_socket);
						exit(0);
					} else {
						$child_process = new Child_Process_Model($pid, $ipc_streams[1]);
						$child_process->add_resource($resource, $resource_id);
						$this->pm->add_process($pid, $child_process);
						// Continue listening in parent
						Socket_Server::stdout('Started child with pid: '.$pid);
					}
				}
				
				$read_streams = array(STDIN, $ipc_streams[0]);
				
				if (false === ($num_changed_streams = stream_select($read_streams, $write_stream = NULL, $exception_stream = NULL, 0, 0)))
				{
					/* Error handling */
				} elseif ($num_changed_streams > 0) {
					/* At least on one of the streams something interesting happened */
					foreach($read_streams as $key => $stream)
					{
						$command = trim(fread($read_streams[$key], 1024));
						
						if ($read_streams[$key] == STDIN)
						{
							Socket_Server::stdout('Read a command from console: '.$command);
						} elseif ($read_streams[$key] == $ipc_streams[0]) {
							Socket_Server::stdout('Read a command from IPC: '.$command);
						}
						// handle commands from console and clients the same (not a real good idea...)
						if ($command == 'shutdown')
						{
							$server_is_running = false; // trigger break in while
							break; // NOW!
						} elseif ($command == 'processes') {
							Socket_Server::stdout('Processes: '.$this->pm->processes());
						} elseif ($command == 'ping') {
							Socket_Server::stdout('Pinging all children');
							$broadcast = "ping\n";
							fwrite($ipc_streams[0], $broadcast, 16);
						} elseif (strpos($command, ' ')) {
							$parts = explode(' ', $command);
							if ($parts[0] == 'kill')
							{
								// then we need 2 parameters
								if (count($parts) == 2)
								{
									if ($this->pm->kill(intval($parts[1])))
									{
										Socket_Server::stdout('Killed process: '.$parts[1]);
									}
								}
							} elseif ($parts[0] == 'broadcast') {
								$msg = 'broadcast:"'.addslashes(substr($command, strlen($parts[0]) + 1))."\"\n";
								fwrite($ipc_streams[0], $msg, strlen($msg));
							}
						}
						$read_commands[$key] = '';
					}
				}
				
			} while ($server_is_running);
			Socket_Server::stdout(Kohana::lang('socket_server.info_shutdown'));
			// handle external facing sockets
			@socket_shutdown($server_socket);
			@socket_close($server_socket);
			// handle inter process communication streams
			fclose($ipc_streams[0]);
			fclose($ipc_streams[1]);
			$this->pm->kill_all();
		}
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