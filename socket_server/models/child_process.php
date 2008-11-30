<?php defined('SYSPATH') or die('No direct script access.');

class Child_Process_Model extends Model {
	
	protected $pid;
	protected $resources;
	protected $ipc;
	protected $read_size = 1024;
	
	public function __construct($pid = NULL, &$ipc)
	{
		if ($pid == NULL)
		{
			throw new Kohana_User_Exception('PID is null!');
		}
		if (!is_resource($ipc))
		{
			throw new Kohana_Exception('socket_server.error_ipc_resource', $pid);
		}
		$this->resources = array();
		$this->pid = $pid;
		$this->ipc = $ipc;
	}
	
	public function ipc_write($message, $serialize = FALSE)
	{
		if ($serialize)
		{
			$message = serialize($message);
		}
		return fwrite($this->ipc, $message, strlen($message));
	}
	
	public function ipc_read()
	{
		return fread($this->ipc, $this->read_size);
	}
	
	public function add_resource(System_Resource_Model $resource, $id)
	{
		if (!in_array($resource, $this->resources) AND !array_key_exists($id, $this->resources))
		{
			$this->resources[$id] = $resource;
		}
	}
	
	public function kill_resource($id)
	{
		if (array_key_exists($id, $this->resources))
		{
			$this->resources[$id]->__destruct();
			unset($this->resources[$id]);
		}
	}
	
	public function kill_all_resources()
	{
		foreach($this->resources as $resource)
		{
			$resource->__destruct();
			unset($resource);
		}
	}
	
	public function __get($name)
	{
		if ($name == 'status')
		{
			return ($this->is_running() ? 'running' : 'stopped');
		} elseif ($name == 'pid') {
			return $this->pid;
		}
	}
	
	public function is_running()
	{
		return Child_Process_Model::is_pid_running($this->pid);
	}
	
	public static function is_pid_running($pid)
	{
		if (posix_kill($pid, 0))
		{
			if (posix_get_last_error() == 1)
			{
				return true;
			}
		}
		return false;
	}
	
	public function __destruct()
	{
		Socket_Server::stdout('Destructing child process: '.$this->pid);
		if ($this->resources)
		{
			$this->kill_all_resources();
			unset($this->resources);
		}
		posix_kill($this->pid, SIGTERM);
	}

} // End Child Process Model