<?php

namespace TheFox\Imap;

use Exception;
use RuntimeException;

use TheFox\Network\AbstractSocket;

class Client{
	
	const MSG_SEPARATOR = "\r\n";
	
	private $id = 0;
	private $status = array();
	
	private $server = null;
	private $socket = null;
	private $ip = '';
	private $port = 0;
	private $recvBufferTmp = '';
	
	public function __construct(){
		#print __CLASS__.'->'.__FUNCTION__.''."\n";
		
		$this->status['hasShutdown'] = false;
	}
	
	public function setId($id){
		$this->id = $id;
	}
	
	public function getId(){
		return $this->id;
	}
	
	public function getStatus($name){
		if(array_key_exists($name, $this->status)){
			return $this->status[$name];
		}
		return null;
	}
	
	public function setStatus($name, $value){
		$this->status[$name] = $value;
	}
	
	public function setServer(Server $server){
		$this->server = $server;
	}
	
	public function getServer(){
		return $this->server;
	}
	
	public function setSocket(AbstractSocket $socket){
		$this->socket = $socket;
	}
	
	public function getSocket(){
		return $this->socket;
	}
	
	public function setIp($ip){
		$this->ip = $ip;
	}
	
	public function getIp(){
		if(!$this->ip){
			$this->setIpPort();
		}
		return $this->ip;
	}
	
	public function setPort($port){
		$this->port = $port;
	}
	
	public function getPort(){
		if(!$this->port){
			$this->setIpPort();
		}
		return $this->port;
	}
	
	public function setIpPort($ip = '', $port = 0){
		$this->getSocket()->getPeerName($ip, $port);
		$this->setIp($ip);
		$this->setPort($port);
	}
	
	public function getIpPort(){
		return $this->getIp().':'.$this->getPort();
	}
	
	private function getLog(){
		#print __CLASS__.'->'.__FUNCTION__.''."\n";
		
		if($this->getServer()){
			return $this->getServer()->getLog();
		}
		
		return null;
	}
	
	private function log($level, $msg){
		#print __CLASS__.'->'.__FUNCTION__.': '.$level.', '.$msg."\n";
		
		if($this->getLog()){
			if(method_exists($this->getLog(), $level)){
				$this->getLog()->$level($msg);
			}
		}
	}
	
	public function run(){
		
	}
	
	public function dataRecv(){
		$data = $this->getSocket()->read();
		
		#print __CLASS__.'->'.__FUNCTION__.': "'.$data.'"'."\n";
		do{
			$separatorPos = strpos($data, static::MSG_SEPARATOR);
			if($separatorPos === false){
				$this->recvBufferTmp .= $data;
				$data = '';
				
				print __CLASS__.'->'.__FUNCTION__.': collect data'."\n";
			}
			else{
				$msg = $this->recvBufferTmp.substr($data, 0, $separatorPos);
				$this->recvBufferTmp = '';
				
				$this->msgHandle($msg);
				
				$data = substr($data, $separatorPos + strlen(static::MSG_SEPARATOR));
				
				#print __CLASS__.'->'.__FUNCTION__.': rest data "'.$data.'"'."\n";
			}
		}
		while($data);
	}
	
	private function msgHandle($msgRaw){
		#print __CLASS__.'->'.__FUNCTION__.': "'.$msgRaw.'"'."\n";
		
		$pos = strpos($msgRaw, ' ');
		if($pos !== false){
			$tag = substr($msgRaw, 0, $pos);
			
			$command = substr($msgRaw, $pos + 1);
			$commandcmp = strtolower($command);
			
			print __CLASS__.'->'.__FUNCTION__.': >'.$tag.'< >'.$command.'< '."\n";
			
			if($commandcmp == 'capability'){
				$this->sendCapability($tag);
			}
			elseif(substr($commandcmp, 0, 12) == 'authenticate'){
				$mechanism = substr($command, 13);
				print __CLASS__.'->'.__FUNCTION__.' authenticate: "'.$mechanism.'"'."\n";
				
				$this->sendAuthenticate($tag, $mechanism);
			}
			elseif(substr($commandcmp, 0, 4) == 'lsub'){
				$this->sendLsub($tag);
			}
			elseif(substr($commandcmp, 0, 4) == 'list'){
				$this->sendList($tag);
			}
			elseif(substr($commandcmp, 0, 6) == 'create'){
				$this->sendCreate($tag);
			}
			elseif(substr($commandcmp, 0, 6) == 'select'){
				$this->sendSelect($tag);
			}
		}
	}
	
	private function dataSend($msg){
		print __CLASS__.'->'.__FUNCTION__.': "'.$msg.'"'."\n";
		$this->getSocket()->write($msg.static::MSG_SEPARATOR);
	}
	
	public function sendHello(){
		$this->dataSend('* OK IMAP4rev1 Service Ready');
	}
	
	private function sendCapability($tag){
		$this->dataSend('* CAPABILITY IMAP4rev1 AUTH=PLAIN');
		$this->dataSend($tag.' OK CAPABILITY completed');
	}
	
	private function sendAuthenticate($tag, $mechanism){
		$this->dataSend('+');
		#$this->dataSend('+ '.base64_encode('hello'));
		$this->dataSend($tag.' OK '.$mechanism.' authentication successful');
	}
	
	private function sendLsub($tag){
		#$this->dataSend('* LSUB () "." #new.test');
		$this->dataSend($tag.' OK LSUB completed');
	}
	
	private function sendList($tag){
		$this->dataSend($tag.' OK LIST completed');
	}
	
	private function sendCreate($tag){
		$this->dataSend($tag.' OK CREATE completed');
	}
	
	private function sendSelect($tag){
		/*$this->dataSend('* 172 EXISTS');
		$this->dataSend('* 1 RECENT');
		$this->dataSend('* OK [UNSEEN 12] Message 12 is first unseen');
		$this->dataSend('* OK [UIDVALIDITY 3857529045] UIDs valid');
		$this->dataSend('* OK [UIDNEXT 4392] Predicted next UID');
		$this->dataSend('* FLAGS (\Answered \Flagged \Deleted \Seen \Draft)');
		$this->dataSend('* OK [PERMANENTFLAGS (\Deleted \Seen \*)] Limited');
		$this->dataSend($tag.' OK [READ-WRITE] SELECT completed');
		*/
		$this->dataSend('* 0 EXISTS');
		$this->dataSend('* 0 RECENT');
		#$this->dataSend('* OK [UNSEEN 0] Message 12 is first unseen');
		#$this->dataSend('* OK [UIDVALIDITY 3857529045] UIDs valid');
		#$this->dataSend('* OK [UIDNEXT 4392] Predicted next UID');
		$this->dataSend('* FLAGS (\Answered \Flagged \Deleted \Seen \Draft)');
		$this->dataSend('* OK [PERMANENTFLAGS (\Deleted \Seen \*)] Limited');
		$this->dataSend($tag.' OK [READ-WRITE] SELECT completed');
	}
	
	public function shutdown(){
		if(!$this->getStatus('hasShutdown')){
			$this->setStatus('hasShutdown', true);
			
			$this->getSocket()->shutdown();
			$this->getSocket()->close();
		}
	}
	
}
