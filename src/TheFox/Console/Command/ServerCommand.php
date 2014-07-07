<?php

namespace TheFox\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Liip\ProcessManager\ProcessManager;
use Liip\ProcessManager\PidFile;

use TheFox\Logger\Logger;
use TheFox\Logger\StreamHandler;
use TheFox\Imap\Server;

class ServerCommand extends Command{
	
	const PIDFILE_PATH = 'pid/server.pid';
	
	private $log;
	private $exit = 0;
	private $pidFile;
	private $server;
	
	protected function configure(){
		$this->setName('server');
		$this->setDescription('Run IMAP server.');
		$this->addOption('daemon', 'd', InputOption::VALUE_NONE, 'Run in daemon mode.');
		$this->addOption('address', 'a', InputOption::VALUE_REQUIRED, 'The address of the network interface. Default = 127.0.0.1');
		$this->addOption('port', 'p', InputOption::VALUE_REQUIRED, 'The port of the network interface. Default = 20143');
		$this->addOption('shutdown', 's', InputOption::VALUE_NONE, 'Shutdown the server.');
	}
	
	protected function execute(InputInterface $input, OutputInterface $output){
		$this->log = new Logger('application');
		$this->log->pushHandler(new StreamHandler('php://stdout', Logger::DEBUG));
		$this->log->pushHandler(new StreamHandler('log/application.log', Logger::DEBUG));
		
		$this->checkShutdown($input->getOption('shutdown'));
		$this->checkDaemon($input->getOption('daemon'));
		
		$this->signalHandlerSetup();
		$this->pidFileCreate();
		
		$this->log->info('start');
		
		$address = '127.0.0.1';
		if($input->getOption('address')){
			$address = $input->getOption('address');
		}
		
		$port = 20143;
		if($input->getOption('port')){
			$port = (int)$input->getOption('port');
		}
		
		$this->server = new Server($address, $port);
		#$this->server->setLog($this->log);
		try{
			$this->server->storageAddMaildir('mailbox');
		}
		catch(Exception $e){
			$this->log->error('storage: '.$e->getMessage());
			exit(1);
		}
		
		try{
			$this->server->init();
		}
		catch(Exception $e){
			$this->log->error('init: '.$e->getMessage());
			exit(1);
		}

		try{
			$this->server->loop();
		}
		catch(Exception $e){
			$this->log->error('loop: '.$e->getMessage());
			exit(1);
		}
		
		$this->pidFileRemove();
		$this->log->info('exit');
	}
	
	private function signalHandlerSetup(){
		if(function_exists('pcntl_signal')){
			declare(ticks = 1);
			pcntl_signal(SIGTERM, array($this, 'signalHandler'));
			pcntl_signal(SIGINT, array($this, 'signalHandler'));
		}
	}
	
	public function signalHandler($signal){
		$this->exit++;
		print "\n";
		$this->log->notice('main abort ['.$this->exit.']: '.$signal);
		if($this->server){
			$this->server->setExit($this->exit);
		}
		if($this->exit >= 2){
			exit(1);
		}
	}
	
	private function pidFileCreate(){
		$this->pidFile = new PidFile(new ProcessManager(), static::PIDFILE_PATH);
		$this->pidFile->acquireLock();
		$this->pidFile->setPid(getmypid());
	}
	
	private function pidFileRemove(){
		$this->pidFile->releaseLock();
	}
	
	private function checkShutdown($isShutdown){
		if($isShutdown){
			if(file_exists(static::PIDFILE_PATH)){
				$pid = file_get_contents(static::PIDFILE_PATH);
				$this->log->info('kill '.$pid);
				posix_kill($pid, SIGINT);
			}
			exit();
		}
	}
	
	private function checkDaemon($isDaemon){
		if($isDaemon){
			fclose(STDIN);
			fclose(STDOUT);
			fclose(STDERR);
			$STDIN = fopen('/dev/null', 'r');
			$STDOUT = fopen('/dev/null', 'wb');
			$STDERR = fopen('/dev/null', 'wb');
			
			if(function_exists('pcntl_fork')){
				$pid = pcntl_fork();
				$this->log->info('pid = '.$pid);
				if($pid == -1 || $pid){
					exit();
				}
			}
		}
	}
	
}
