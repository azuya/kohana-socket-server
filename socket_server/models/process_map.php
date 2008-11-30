<?php defined('SYSPATH') or die('No direct script access.');

class Process_Map_Model extends Model {
	
	protected $store, $cache;
	
	public function __construct()
	{
		parent::__construct();
		$this->store = array();
		$this->cache = new Cache();
		
		$this->cleanup();
	}
	
	public function __get($pid = NULL)
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
				if (!posix_kill($process->pid, SIGTERM))
				{
					// zombie process
					$zombies++;
				}
			}
		}
		if ($zombies > 0)
		{
			throw new Kohana_Exception('socket_server.error_zombies', count($zombies));
		} else {
			$this->cache->delete_tag('running');
		}
	}
	
	public function __set($pid = NULL, Child_Process_Model $value = NULL)
	{
		if ($pid != NULL AND is_int($pid))
		{
			if ($value == NULL)
			{
				$this->kill($pid);
			} else {
				if (array_key_exists($pid, $this->store)
					AND $this->store[$pid]->is_running())
				{
					// process is still running
					if (posix_kill($this->pid, SIGTERM))
					{
						$this->cache->delete($pid);
						unset($this->store[$pid]);
						// process killed
					} else {
						// zombie process
					}
				} else {
					// process is not running
					$this->store[$pid] = $value;
					$this->cache->set($pid, $this->store[$pid], array('process', 'running'));
				}
			}
		}
	}
	
	public function kill($pid = NULL)
	{
		// kill process
		if (is_int($pid))
		{
			if (array_key_exists($pid, $this->store)
				AND $this->store[$pid]->is_running())
			{
				// process is still running
				if (posix_kill($this->pid, SIGTERM))
				{
					$this->cache->delete($pid);
					unset($this->store[$pid]);
					return true;
					// process killed
				} else {
					// zombie process
					return false;
				}
			}
		} elseif (is_object($pid) AND $pid instanceof Child_Process_Model) {
			if ($pid->is_running())
			{
				return $this->kill($pid->pid);
			}
		}
		return false;
	}
	
	public function killAll()
	{
		foreach($this->store as $child_process)
		{
			Socket_Server::stdout('Killing process: '.$child_process->pid);
			$status;
			posix_kill($child_process->pid, $status, SIGTERM);
			pcntl_waitpid($child_process->pid);
			$child_process->__destruct();
		}
	}
	
	public function __destruct()
	{
		$this->killAll();
	}

} // End Process Map Model