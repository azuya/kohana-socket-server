<?php defined('SYSPATH') or die('No direct script access.');

$config['enabled'] = TRUE;
$config['port'] = 10000;
$config['process_timeout'] = -1; // wait forever
$config['max_clients'] = 100; // 100 simultaneous clients
$config['read_size'] = 2048; // buffer