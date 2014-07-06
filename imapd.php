#!/usr/bin/env php
<?php

require_once __DIR__.'/bootstrap.php';

use TheFox\Logger\Logger;
use TheFox\Logger\StreamHandler;
use TheFox\Imap\Server;

$mailboxPath = 'mailbox';

$log = new Logger('main');
$log->pushHandler(new StreamHandler('php://stdout', Logger::DEBUG));
#$log->pushHandler(new StreamHandler('log/bootstrap.log', Logger::DEBUG));

$server = new Server('127.0.0.1', 20143);
try{
	$server->storageAddMaildir($mailboxPath);
}
catch(Exception $e){
	$log->error('storage: '.$e->getMessage());
	exit(1);
}

$log->info('signal handler setup');
declare(ticks = 1);
$exit = 0;
if(function_exists('pcntl_signal')){
	function signalHandler($signo){
		global $exit, $log, $server;
		$exit++;
		print "\n";
		$log->notice('main abort ['.$exit.']');
		$server->setExit($exit);
		if($exit >= 2)
			exit(1);
	}
	pcntl_signal(SIGTERM, 'signalHandler');
	pcntl_signal(SIGINT, 'signalHandler');
}

try{
	$server->init();
}
catch(Exception $e){
	$log->error('init: '.$e->getMessage());
	exit(1);
}

try{
	$server->loop();
}
catch(Exception $e){
	$log->error('loop: '.$e->getMessage());
	exit(1);
}

exit(0);
