<?php defined('SYSPATH') or die('No direct script access.');

class Process_Manager {
	
	protected $store, $cache;
	
	public function __construct()
	{
		$this->store = array();
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
	
	public function has_process($pid = NULL)
	{
		return array_key_exists($pid, $this->store);
	}
	
	public function processes()
	{
		return count($this->store);
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
				$this->store[$pid] = NULL;
				array_splice($this->store, $pid);
				unset($this->store[$pid]);
			}
			if (posix_kill($pid, SIGTERM))
			{
				$this->cache->delete($pid);
				return true;
				// process killed
			} else {
				// zombie process
				Socket_Server::stdout(Kohana::lang('socket_server.error_zombie', $pid));
				pcntl_waitpid($pid);
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
				$status = NULL;
				pcntl_waitpid($child_process->pid, $status);
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