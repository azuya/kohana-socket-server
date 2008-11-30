<?php defined('SYSPATH') or die('No direct script access.');

class Process_Manager {
	
	protected $store, $cache, $server_ipc;
	
	public function __construct()
	{
		$this->store = array();
		$this->server_ipc = array();
		$this->cache = new Cache();
		
		$this->cleanup();
	}
	
	public function get_process($pid = NULL)
	{
		if (array_key_exists($pid, $this->store))
		{
			return $this->store[$pid];
		}
		return NULL;
	}
	
	protected function cleanup()
	{
		$zombies = 0;
		$running_processes = $this->cache->find('running');
		if (count($running_processes) > 0)
		{
			foreach($running_processes as $process)
			{
				if (Child_Process_Model::is_pid_running($process->pid) AND !$this->kill($process->pid))
				{
					// zombie process
					$zombies++;
				}
			}
		}
		foreach($this->store as $process)
		{
			if (!$process->is_running())
			{
				$this->kill($process->pid);
			}
		}
		
		if ($zombies > 0)
		{
			throw new Kohana_Exception('socket_server.error_zombies', count($zombies));
		} else {
			$this->cache->delete_tag('running');
		}
	}
	
	public function add_process($pid = NULL, Child_Process_Model &$value = NULL)
	{
		if ($pid != NULL AND is_int($pid))
		{
			if ($value == NULL)
			{
				$this->kill($pid);
			} else {
				if ($this->has_process($pid)
					AND $this->get_process($pid)->is_running())
				{
					// process is still running
					if (!$this->kill($pid))
					{
						// not killed!??
						throw new Kohana_Exception('socket_server.error_zombie', $pid);
					}
				}
				// process is not running
				$this->store[$pid] = $value;
				$this->cache->set($pid, $this->store[$pid], array('process', 'running'));
			}
		}
	}
	
	public function add_ipc_stream(&$stream = NULL, $pid = NULL)
	{
		if ($stream != NULL AND is_resource($stream) AND $this->has_process($pid))
		{
			$this->server_ipc[$pid] = $stream;
		}
	}
	
	public function ipc_read_select($read_size = 1024)
	{
		$read_stream = array();
		$read_stream += $this->server_ipc;
		
		// first we check the stream (server IPC connection)
		if (false === ($num_changed_streams = stream_select($read_stream, $write_stream = NULL, $exception_stream = NULL, 0, 0)))
		{
			// nothing
		} elseif ($num_changed_streams > 0) {
			$results = array();
			foreach($read_stream as $stream)
			{
				$key = array_search($stream, $this->server_ipc);
				$results[$key] = fread($stream, $read_size);
			}
			return $results;
		}
		return array();
	}
	
	public function ipc_broadcast($message)
	{
		foreach($this->server_ipc as $pid => $stream)
		{
			if (false === fwrite($stream, $message, strlen($message)))
			{
				throw new Kohana_Exception('socket_server.error_ipc_write', $pid, $message);
			}
		}
	}
	
	public function tell_child($pid = NULL, $message = NULL)
	{
		if ($pid != NULL
			AND $message != NULL
			AND $this->has_process($pid)
			AND $this->has_ipc($pid))
		{
			return fwrite($this->server_ipc[$pid], $message, strlen($message));
		}
		return false;
	}
	
	public function get_ipc_streams()
	{
		return $this->server_ipc;
	}
	
	public function has_ipc($pid = NULL)
	{
		return array_key_exists($pid, $this->server_ipc);
	}
	
	public function has_process($pid = NULL)
	{
		return array_key_exists($pid, $this->store);
	}
	
	public function processes()
	{
		return count($this->store);
	}
	
	private function monitor()
	{
		$processes = array();
		foreach($this->store as $pid => $process)
		{
			if ($process instanceof Child_Process_Model)
			{
				if ($process->is_running())
				{
					$processes[$pid] = $process;
				}
			}
		}
		$this->store = $processes;
		
		$streams = array();
		foreach($this->server_ipc as $pid => $stream)
		{
			if (is_resource($stream))
			{
				$streams[$pid] = $stream;
			}
		}
		$this->server_ipc = $streams;
		
	}
	
	public function kill($pid = NULL)
	{
		// kill process
		if (!is_object($pid))
		{
			if (array_key_exists($pid, $this->store))
			{
				// process is still running
				$this->store[$pid]->kill_all_resources();
				$this->store[$pid]->__destruct();
				fclose($this->server_ipc[$pid]);
				unset($this->server_ipc[$pid]);
				unset($this->store[$pid]);
				//$this->monitor();
			}
			if (posix_kill($pid, SIGTERM))
			{
				$this->cache->delete($pid);
				return true;
				// process killed
			} else {
				// zombie process
				Socket_Server::stdout(Kohana::lang('socket_server.error_zombie', $pid));
				pcntl_waitpid($pid, $status = NULL);
				return false;
			}
		} elseif (is_object($pid) AND $pid instanceof Child_Process_Model) {
			return $this->kill($pid->pid);
		}
		return false;
	}
	
	public function kill_all()
	{
		foreach($this->store as $key => $child_process)
		{
			if ($child_process instanceof Child_Process_Model)
			{
				Socket_Server::stdout('Killing process: '.$child_process->pid);
				$this->kill($child_process);
				pcntl_waitpid($child_process->pid, $status = NULL);
			}
			unset($this->store[$key]);
		}
		$this->store = array();
	}
	
	public function __destruct()
	{
		if ($this->processes() > 0)
		{
			$this->kill_all();
		}
	}

} // End Process Manager