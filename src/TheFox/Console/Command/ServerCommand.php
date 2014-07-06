<?php

namespace TheFox\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use TheFox\Logger\Logger;
use TheFox\Logger\StreamHandler;
use TheFox\Imap\Server;

class ServerCommand extends Command{
	
	private $log;
	private $exit = 0;
	private $server;
	
	protected function configure(){
		$this->setName('server');
		$this->setDescription('Run IMAP server.');
		$this->addOption('daemon', 'd', InputOption::VALUE_NONE, 'Run in daemon mode.');
		$this->addOption('address', 'a', InputOption::VALUE_REQUIRED, 'The address of the network interface. Default = 127.0.0.1');
		$this->addOption('port', 'p', InputOption::VALUE_REQUIRED, 'The port of the network interface. Default = 20143');
	}
	
	protected function execute(InputInterface $input, OutputInterface $output){
		$this->log = new Logger('application');
		
		if($input->getOption('daemon')){
			fclose(STDIN);
			fclose(STDOUT);
			fclose(STDERR);
			$output->setVerbosity(OutputInterface::VERBOSITY_NORMAL);
			@mkdir('log');
			$this->log->pushHandler(new StreamHandler('log/imapd.log', Logger::DEBUG));
			$this->log->info('start');
		}
		elseif(OutputInterface::VERBOSITY_QUIET <= $output->getVerbosity()){
			$this->log->pushHandler(new StreamHandler('php://stdout', Logger::DEBUG));
		}
		
		if(function_exists('pcntl_signal')){
			declare(ticks = 1);
			pcntl_signal(SIGTERM, array($this, 'exitCommand'));
			pcntl_signal(SIGINT, array($this, 'exitCommand'));
		}
		if(function_exists('pcntl_fork') && $input->getOption('daemon')){
			$pid = pcntl_fork();
			$this->log->info('pid = '.$pid);
			if($pid == -1 || $pid){
				exit();
			}
		}
		
		$address = '127.0.0.1';
		if($input->getOption('address')){
			$address = $input->getOption('address');
		}
		
		$port = 20143;
		if($input->getOption('port')){
			$port = (int)$input->getOption('port');
		}
		
		$this->server = new Server($address, $port);
		$this->server->setLog($this->log);
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
		
	}
	
	public function exitCommand(){
		$this->exit++;
		print "\n";
		$this->log->notice('main abort ['.$this->exit.']');
		if($this->server){
			$this->server->setExit($this->exit);
		}
		if($this->exit >= 2)
			exit(1);
	}
	
}
