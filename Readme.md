# Introduction #

Here you'll find a few tips to get running with the module.


# Details #

Firstly, your php installation must have pcntl, socket, and command line mode enabled. This module has been developed with Kohana v2.2, so you'll need to download that from http://www.kohanaphp.com

Extract the Kohana framework to a directory, and extract the Socket Server module into the _modules_ directory. Take a look through modules/socket\_server/config for some info regarding connecting to the server once it's running.

Edit the config.php in application/config/config.php to include the socket server module like so:
```
$config['modules'] = array
(
	MODPATH.'socket_server'      	// Socket Server
);
```

From the terminal, cd to your kohana root and run `php index.php server`

By default, the listening port number should be 10000. You can telnet to it using `telnet localhost 10000` to open a new connection.

You should be running now.