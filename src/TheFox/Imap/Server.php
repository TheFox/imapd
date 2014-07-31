<?php

namespace TheFox\Imap;

use Exception;
use RuntimeException;
use InvalidArgumentException;

use Zend\Mail\Storage\Writable\Maildir;
use Zend\Mail\Message;
use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Filesystem\Filesystem;

use TheFox\Imap\Exception\NotImplementedException;
use TheFox\Logger\Logger;
use TheFox\Logger\StreamHandler;
use TheFox\Network\Socket;

class Server extends Thread{
	
	const LOOP_USLEEP = 10000;
	
	private $log;
	private $isListening = false;
	private $ip;
	private $port;
	private $clientsId = 0;
	private $clients = array();
	private $storageMaildir;
	private $eventsId = 0;
	private $events = array();
	
	public function __construct($ip = '127.0.0.1', $port = 20143){
		#print __CLASS__.'->'.__FUNCTION__.''."\n";
		
		$this->setIp($ip);
		$this->setPort($port);
	}
	
	public function setLog($log){
		$this->log = $log;
	}
	
	public function getLog(){
		return $this->log;
	}
	
	public function setIp($ip){
		$this->ip = $ip;
	}
	
	public function setPort($port){
		$this->port = $port;
	}
	
	public function init(){
		if(!$this->log){
			$this->log = new Logger('server');
			$this->log->pushHandler(new StreamHandler('php://stdout', Logger::DEBUG));
			if(file_exists('log')){
				$this->log->pushHandler(new StreamHandler('log/server.log', Logger::DEBUG));
			}
		}
		$this->log->info('start');
		$this->log->info('ip = "'.$this->ip.'"');
		$this->log->info('port = "'.$this->port.'"');
	}
	
	public function listen(){
		if($this->ip && $this->port){
			#$this->log->notice('listen on '.$this->ip.':'.$this->port);
			
			$this->socket = new Socket();
			
			$bind = false;
			try{
				$bind = $this->socket->bind($this->ip, $this->port);
			}
			catch(Exception $e){
				$this->log->error($e->getMessage());
			}
			
			if($bind){
				try{
					if($this->socket->listen()){
						$this->log->notice('listen ok');
						$this->isListening = true;
						
						return true;
					}
				}
				catch(Exception $e){
					$this->log->error($e->getMessage());
				}
			}
			
		}
	}
	
	public function run(){
		#print __CLASS__.'->'.__FUNCTION__.''."\n";
		#print __CLASS__.'->'.__FUNCTION__.': client '.count($this->clients)."\n";
		
		$readHandles = array();
		$writeHandles = null;
		$exceptHandles = null;
		
		if($this->isListening){
			$readHandles[] = $this->socket->getHandle();
		}
		foreach($this->clients as $clientId => $client){
			// Collect client handles.
			$readHandles[] = $client->getSocket()->getHandle();
			
			// Run client.
			#print __CLASS__.'->'.__FUNCTION__.': client run'."\n";
			$client->run();
		}
		$readHandlesNum = count($readHandles);
		
		$handlesChanged = $this->socket->select($readHandles, $writeHandles, $exceptHandles);
		#$this->log->debug('collect readable sockets: '.(int)$handlesChanged.'/'.$readHandlesNum);
		
		if($handlesChanged){
			foreach($readHandles as $readableHandle){
				if($this->isListening && $readableHandle == $this->socket->getHandle()){
					// Server
					$socket = $this->socket->accept();
					if($socket){
						$client = $this->clientNew($socket);
						$client->sendHello();
						#$client->sendPreauth('IMAP4rev1 server logged in as thefox');
						#$client->sendPreauth('server logged in as thefox');
						
						#$this->log->debug('new client: '.$client->getId().', '.$client->getIpPort());
					}
				}
				else{
					// Client
					$client = $this->clientGetByHandle($readableHandle);
					if($client){
						if(feof($client->getSocket()->getHandle())){
							$this->clientRemove($client);
						}
						else{
							#$this->log->debug('old client: '.$client->getId().', '.$client->getIpPort());
							$client->dataRecv();
							
							if($client->getStatus('hasShutdown')){
								$this->clientRemove($client);
							}
						}
					}
					
					#$this->log->debug('old client: '.$client->getId().', '.$client->getIpPort());
					
				}
			}
		}
	}
	
	public function loop(){
		$s = time();
		$r1 = 0;
		$r2 = 0;
		
		while(!$this->getExit()){
			$this->run();
			
			if(time() - $s >= 10 && !$r1){
				$r1 = 1;
				
				print __CLASS__.'->'.__FUNCTION__.' add msg A'."\n";
				
				try{
					#$this->storageMaildir['object']->createFolder('test2');
				}
				catch(Exception $e){}
				
				$message = new Message();
				$message->addFrom('thefox21at@gmail.com');
				$message->addTo('thefox@fox21.at');
				$message->setBody('body');
				
				$message->setSubject('t1 '.date('H:i:s'));
				$this->mailAdd($message->toString());
				
				$message->setSubject('t2 '.date('H:i:s'));
				#$this->mailAdd($message->toString());
				
				$message->setSubject('t3 '.date('H:i:s'));
				#$this->mailAdd($message->toString());
				
				$message->setSubject('t4 '.date('H:i:s'));
				#$this->mailAdd($message->toString());
				
			}
			
			if(time() - $s >= 300 && !$r2){
				$r2 = 1;
				
				print __CLASS__.'->'.__FUNCTION__.' add msg B'."\n";
				
				$message = new Message();
				$message->addFrom('thefox21at@gmail.com');
				$message->addTo('thefox@fox21.at');
				$message->setSubject('test '.date('H:i:s'));
				$message->setBody('body');
				
				#$this->mailAdd($message->toString(), null, null, true);
				
				$this->mailAdd($message->toString());
				
			}
			
			usleep(static::LOOP_USLEEP);
		}
		
		$this->shutdown();
	}
	
	public function shutdown(){
		#$this->log->debug('shutdown');
		
		// Notify all clients.
		foreach($this->clients as $clientId => $client){
			$client->sendBye('Server shutdown');
			$this->clientRemove($client);
		}
		
		// Remove all temp files and save dbs.
		$this->storageRemoveTempAndSave();
		
		#$this->log->debug('shutdown done');
	}
	
	private function clientNew($socket){
		$this->clientsId++;
		#print __CLASS__.'->'.__FUNCTION__.' ID: '.$this->clientsId."\n";
		
		$client = new Client();
		$client->setSocket($socket);
		$client->setId($this->clientsId);
		$client->setServer($this);
		
		$this->clients[$this->clientsId] = $client;
		#print __CLASS__.'->'.__FUNCTION__.' clients: '.count($this->clients)."\n";
		
		return $client;
	}
	
	private function clientGetByHandle($handle){
		foreach($this->clients as $clientId => $client){
			if($client->getSocket()->getHandle() == $handle){
				return $client;
			}
		}
		
		return null;
	}
	
	private function clientRemove(Client $client){
		$this->log->debug('client remove: '.$client->getId());
		
		$client->shutdown();
		
		$clientsId = $client->getId();
		unset($this->clients[$clientsId]);
	}
	
	public function getStorageMailbox(){
		$this->storageInit();
		return $this->storageMaildir;
	}
	
	public function storageInit(){
		#$this->log->debug(__CLASS__.'->'.__FUNCTION__.'');
		if(!$this->storageMaildir){
			#$this->log->debug(__CLASS__.'->'.__FUNCTION__.': no storage is set. create one...');
			
			$mailboxPath = './tmp_mailbox_'.mt_rand(1, 9999999);
			return $this->storageAddMaildir($mailboxPath, 'temp');
		}
		
		return null;
	}
	
	public function storageAddMaildir($path, $type = 'normal'){
		if(!file_exists($path)){
			try{
				Maildir::initMaildir($path);
			}
			catch(Exception $e){
				$this->log->error('initMaildir: '.$e->getMessage());
			}
		}
		
		try{
			$dbPath = $path;
			if(substr($dbPath, -1) == '/'){
				$dbPath = substr($dbPath, 0, -1);
			}
			$dbPath .= '.msgs.yml';
			#$this->log->debug('dbpath: '.$dbPath);
			$db = new MsgDb($dbPath);
			$db->load();
			
			$storage = new Maildir(array('dirname' => $path));
			
			$this->storageMaildir = array(
				'object' => $storage,
				'path' => $path,
				'type' => $type,
				'db' => $db,
			);
			
			return $this->storageMaildir;
		}
		catch(Exception $e){
			$this->log->error('storageAddMaildir: '.$e->getMessage());
		}
		
		return null;
	}
	
	public function storageFolderAdd($path){
		$this->storageInit();
		
		$this->storageMaildir['object']->createFolder($path);
	}
	
	public function storageRemoveTempAndSave(){
		if($this->storageMaildir['type'] == 'temp'){
			$filesystem = new Filesystem();
			$filesystem->remove($this->storageMaildir['path']);
			if($this->storageMaildir['db']){
				$filesystem->remove($this->storageMaildir['db']->getFilePath());
			}
		}
		else{
			if($this->storageMaildir['db']){
				#$this->log->debug('save db: '.$this->storageMaildir['db']->getFilePath());
				$this->storageMaildir['db']->save();
			}
		}
	}
	
	public function storageMailboxGetFolders($baseFolder, $searchFolder, $recursive = false, $level = 0){
		$func = __FUNCTION__;
		$this->log->debug(__CLASS__.'->'.$func.$level.': '.$baseFolder.' /'.$searchFolder.'/ '.(int)$recursive.', '.$level);
		
		if($level >= 100){
			return array();
		}
		
		if($baseFolder == '' && $searchFolder == 'INBOX'){
			return $this->$func('INBOX', '*', true, $level + 1);
		}
		
		$storage = $this->getStorageMailbox();
		
		$rv = array();
		$folders = $storage['object']->getFolders($baseFolder);
		#$folders = $storage['object']->getFolders();
		foreach($folders as $subfolder){
			$name = 'N/A';
			if(is_object($subfolder)){
				#ve($subfolder);
				#$name = $subfolder->getGlobalName();
				$name = $subfolder->getLocalName();
			}
			
			#$this->log->debug(__CLASS__.'->'.$func.'     subfolder: /'.$name.'/');
			
			if(fnmatch($searchFolder, $name)){
				#$this->log->debug(__CLASS__.'->'.$func.'     add');
				$rv[] = $subfolder;
			}
			
			if($recursive && strtolower($name) != 'inbox'){
				$rv = array_merge($rv, $this->$func(($baseFolder ? $baseFolder.'.' : '').$name, $searchFolder, $recursive, $level + 1));
			}
		}
		return $rv;
	}
	
	public function storageMailboxGetDbNextId(){
		#$this->log->debug(__CLASS__.'->'.__FUNCTION__.'');
		
		$storage = $this->getStorageMailbox();
		if($storage['db']){
			#$this->log->debug(__CLASS__.'->'.__FUNCTION__.': db ok');
			return $storage['db']->getNextId();
		}
		
		#$this->log->debug(__CLASS__.'->'.__FUNCTION__.': db failed');
		return null;
	}
	
	public function storageMailboxGetDbSeqById($msgId){
		#$this->log->debug(__CLASS__.'->'.__FUNCTION__.'');
		
		$storage = $this->getStorageMailbox();
		if($storage['db']){
			#$this->log->debug(__CLASS__.'->'.__FUNCTION__.': db ok');
			return $storage['db']->getSeqById($msgId);
		}
		
		#$this->log->debug(__CLASS__.'->'.__FUNCTION__.': db failed');
		return null;
	}
	
	public function storageMaildirGetDbMsgIdBySeqNum($seqNum){
		#$this->log->debug(__FUNCTION__.': '.$seqNum);
		
		if($this->storageMaildir['db']){
			#$this->log->debug(__FUNCTION__.' db ok');
			
			try{
				$uid = $this->storageMaildir['object']->getUniqueId($seqNum);
				#$this->log->debug(__FUNCTION__.' uid: '.$uid);
				$msgId = $this->storageMaildir['db']->getMsgIdByUid($uid);
				#$this->log->debug(__FUNCTION__.' msgid: '.$msgId);
				return $msgId;
			}
			catch(Exception $e){
				$this->log->error('storageMaildirGetDbMsgIdBySeqNum: '.$e->getMessage());
			}
			
			return null;
		}
		
		return null;
	}
	
	public function mailAdd($mail, $folder = null, $flags = array(), $recent = true){
		#fwrite(STDOUT, __CLASS__.'->'.__FUNCTION__.''."\n");
		$this->eventExecute(Event::TRIGGER_MAIL_ADD_PRE);
		$this->storageInit();
		
		$uid = null;
		$msgId = null;
		
		// Because of ISSUE 6317 (https://github.com/zendframework/zf2/issues/6317) in the Zendframework we must reselect the current folder.
		$oldFolder = $this->storageMaildir['object']->getCurrentFolder();
		if($folder){
			$this->storageMaildir['object']->selectFolder($folder);
		}
		$this->storageMaildir['object']->appendMessage($mail, null, $flags, $recent);
		$lastId = $this->storageMaildir['object']->countMessages();
		$message = $this->storageMaildir['object']->getMessage($lastId);
		$uid = $this->storageMaildir['object']->getUniqueId($lastId);
		$this->storageMaildir['object']->selectFolder($oldFolder);
		
		$this->eventExecute(Event::TRIGGER_MAIL_ADD, array($message));
		
		if($this->storageMaildir['db']){
			try{
				#fwrite(STDOUT, "add msg: ".$uid."\n");
				$msgId = $this->storageMaildir['db']->msgAdd($uid, $lastId, $folder ? $folder : $oldFolder);
				#ve($storage['db']);
			}
			catch(Exception $e){
				$this->log->error('db: '.$e->getMessage());
			}
		}
		
		$this->eventExecute(Event::TRIGGER_MAIL_ADD_POST, array($msgId));
		
		return $msgId;
	}
	
	public function mailRemove($msgId){
		#$this->log->debug(__FUNCTION__);
		
		if(!$this->getStorageMailbox()){
			throw new RuntimeException('Root storage not initialized.', 1);
		}
		
		if($this->storageMaildir['db']){
			$seqNum = 0;
			
			#$this->log->debug('remove msgId: /'.$msgId.'/');
			
			$oldFolder = $this->storageMaildir['object']->getCurrentFolder();
			#$this->log->debug('folder: /'.$oldFolder.'/');
			
			$uid = $this->storageMaildir['db']->getMsgUidById($msgId);
			#$this->log->debug('remove uid: /'.$uid.'/');
			
			$seqNum = $this->storageMaildir['db']->getSeqById($msgId);
			#$this->log->debug('remove seqNum: /'.$seqNum.'/');
			
			$storageRemove = false;
			try{
				if($seqNum){
					$this->storageMaildir['object']->removeMessage($seqNum);
					$storageRemove = true;
				}
			}
			catch(Exception $e){
				$this->log->error('root storage remove: '.$e->getMessage());
			}
			
			if($storageRemove){
				try{
					$this->storageMaildir['db']->msgRemove($msgId);
				}
				catch(Exception $e){
					$this->log->error('db remove: '.$e->getMessage());
				}
			}
		}
		
	}
	
	public function mailRemoveBySequenceNum($seqNum){
		#$this->log->debug(__FUNCTION__.': /'.$seqNum.'/');
		
		if(!$this->getStorageMailbox()){
			throw new RuntimeException('Root storage not initialized.', 1);
		}
		
		$msgId = null;
		try{
			$msgId = $this->storageMaildirGetDbMsgIdBySeqNum($seqNum);
			#$this->log->debug('msgId: /'.$msgId.'/');
		}
		catch(Exception $e){
			$this->log->error('db remove: '.$e->getMessage());
		}
		
		$storageRemove = false;
		try{
			$this->storageMaildir['object']->removeMessage($seqNum);
			$storageRemove = true;
		}
		catch(Exception $e){
			$this->log->error('root storage remove: '.$e->getMessage());
		}
		
		if($this->storageMaildir['db'] && $storageRemove && $msgId){
			try{
				#$this->log->debug('remove');
				$this->storageMaildir['db']->msgRemove($msgId);
				#$this->log->debug('removed');
			}
			catch(Exception $e){
				$this->log->error('db remove: '.$e->getMessage());
			}
		}
	}
	
	public function mailCopy($msgId, $folder){
		#fwrite(STDOUT, __CLASS__.'->'.__FUNCTION__.': '.$msgId.', '.$folder."\n");
		
		if(!$this->getStorageMailbox()){
			throw new RuntimeException('Root storage not initialized.', 1);
		}
		
		if($this->storageMaildir['db']){
			#fwrite(STDOUT, "copy: $msgId\n");
			
			$seqNum = $this->storageMaildir['db']->getSeqById($msgId);
			#fwrite(STDOUT, "seqNum: $seqNum\n");
			
			if($seqNum){
				$this->mailCopyBySequenceNum($seqNum, $folder);
			}
			
		}
	}
	
	public function mailCopyBySequenceNum($seqNum, $folder){
		#fwrite(STDOUT, __CLASS__.'->'.__FUNCTION__.': '.$seqNum.', '.$folder."\n");
		
		if(!$this->getStorageMailbox()){
			throw new RuntimeException('Root storage not initialized.', 1);
		}
		
		if($this->storageMaildir['db']){
			$this->storageMaildir['object']->copyMessage($seqNum, $folder);
			
			$oldFolder = $this->storageMaildir['object']->getCurrentFolder();
			#fwrite(STDOUT, "oldFolder: $oldFolder\n");
			
			$this->storageMaildir['object']->selectFolder($folder);
			#fwrite(STDOUT, "folder: $folder\n");
			
			$lastId = $this->storageMaildir['object']->countMessages();
			#fwrite(STDOUT, "lastId: $lastId\n");
			
			$uid = $this->storageMaildir['object']->getUniqueId($lastId);
			#fwrite(STDOUT, "uid: $uid\n");
			
			$this->storageMaildir['object']->selectFolder($oldFolder);
			
			$this->storageMaildir['db']->msgAdd($uid, $lastId, $folder);
		}
	}
	
	public function mailGet($msgId){
		if($this->storageMaildir['db']){
			$this->log->debug(__CLASS__.'->'.__FUNCTION__.' db ok: '.$msgId);
			
			$uid = $this->storageMaildir['db']->getMsgUidById($msgId);
			$this->log->debug(__CLASS__.'->'.__FUNCTION__.' uid: '.$uid);
			
			$seqNum = 0;
			
			try{
				$seqNum = $this->storageMaildir['object']->getNumberByUniqueId($uid);
				$this->log->debug(__CLASS__.'->'.__FUNCTION__.' seqNum: '.$seqNum);
			}
			catch(Exception $e){
				$this->log->error(__FUNCTION__.': '.$e->getMessage());
			}
			
			if(!$seqNum){
				// If this failover is used ZF2 ISSUE 6317 is still not fixed.
				// https://github.com/zendframework/zf2/issues/6317
				
				$seqNum = $this->storageMaildir['db']->getSeqById($msgId);
				$this->log->debug(__CLASS__.'->'.__FUNCTION__.' failover: '.$seqNum);
			}
			if($seqNum){
				$message = $this->storageMaildir['object']->getMessage($seqNum);
				#ve($message);
				return $message;
			}
			
			return null;
		}
		
		return null;
	}
	
	public function eventAdd(Event $event){
		#fwrite(STDOUT, __CLASS__.'->'.__FUNCTION__.''."\n");
		
		$this->eventsId++;
		$this->events[$this->eventsId] = $event;
	}
	
	private function eventExecute($trigger, $args = array()){
		#fwrite(STDOUT, __CLASS__.'->'.__FUNCTION__.''."\n");
		
		foreach($this->events as $eventId => $event){
			#fwrite(STDOUT, __CLASS__.'->'.__FUNCTION__.' event: '.$eventId."\n");
			if($event->getTrigger() == $trigger){
				$event->execute($args);
			}
		}
	}
	
}
