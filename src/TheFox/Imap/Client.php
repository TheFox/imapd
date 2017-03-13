<?php

namespace TheFox\Imap;

use Exception;
use RuntimeException;
use InvalidArgumentException;
use DateTime;

use Zend\Mail\Storage;
use Zend\Mail\Headers;
use Zend\Mail\Message;

use TheFox\Network\AbstractSocket;
use TheFox\Logic\CriteriaTree;
use TheFox\Logic\Obj;
use TheFox\Logic\Gate;
use TheFox\Logic\AndGate;
use TheFox\Logic\OrGate;
use TheFox\Logic\NotGate;

class Client{
	
	const MSG_SEPARATOR = "\r\n";
	
	private $id = 0;
	private $status = array();
	
	private $server = null;
	private $socket = null;
	
	/**
	 * @var string
	 */
	private $ip = '';
	
	/**
	 * @var integer
	 */
	private $port = 0;
	
	private $recvBufferTmp = '';
	private $expunge = array();
	private $subscriptions = array();
	
	// Remember the selected mailbox for each client.
	private $selectedFolder = null;
	
	public function __construct(){
		$this->status['hasShutdown'] = false;
		$this->status['hasAuth'] = false;
		$this->status['authStep'] = 0;
		$this->status['authTag'] = '';
		$this->status['authMechanism'] = '';
		$this->status['appendStep'] = 0;
		$this->status['appendTag'] = '';
		$this->status['appendFolder'] = '';
		$this->status['appendFlags'] = array();
		$this->status['appendDate'] = ''; // @NOTICE NOT_IMPLEMENTED
		$this->status['appendLiteral'] = 0;
		$this->status['appendMsg'] = '';
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
	
	/**
	 * @codeCoverageIgnore
	 */
	public function setSocket(AbstractSocket $socket){
		$this->socket = $socket;
	}
	
	/**
	 * @codeCoverageIgnore
	 */
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
		// @codeCoverageIgnoreStart
		if(!defined('TEST')){
			$this->getSocket()->getPeerName($ip, $port);
		}
		// @codeCoverageIgnoreEnd
		
		$this->setIp($ip);
		$this->setPort($port);
	}
	
	public function getIpPort(){
		return $this->getIp().':'.$this->getPort();
	}
	
	private function getLog(){
		if($this->getServer()){
			return $this->getServer()->getLog();
		}
		return null;
	}
	
	private function log($level, $msg){
		if($this->getLog()){
			if(method_exists($this->getLog(), $level)){
				$this->getLog()->$level($msg);
			}
		}
	}
	
	/**
	 * @codeCoverageIgnore
	 */
	public function run(){
		
	}
	
	/**
	 * @codeCoverageIgnore
	 */
	public function dataRecv(){
		$data = $this->getSocket()->read();
		
		do{
			$separatorPos = strpos($data, static::MSG_SEPARATOR);
			if($separatorPos === false){
				$this->recvBufferTmp .= $data;
				$data = '';
				
				$this->log('debug', 'client '.$this->id.': collect data');
			}
			else{
				$msg = $this->recvBufferTmp.substr($data, 0, $separatorPos);
				$this->recvBufferTmp = '';
				
				$this->msgHandle($msg);
				
				$data = substr($data, $separatorPos + strlen(static::MSG_SEPARATOR));
			}
		}while($data);
	}
	
	public function msgParseString($msgRaw, $argsMax = null){
		$str = new StringParser($msgRaw, $argsMax);
		return $str->parse();
	}
	
	public function msgGetArgs($msgRaw, $argsMax = null){
		$args = $this->msgParseString($msgRaw, $argsMax);
		
		$tag = array_shift($args);
		$command = array_shift($args);
		
		return array(
			'tag' => $tag,
			'command' => $command,
			'args' => $args,
		);
	}
	
	public function msgGetParenthesizedlist($msgRaw, $level = 0){
		$rv = array();
		$rvc = 0;
		if($msgRaw){
			if($msgRaw[0] == '(' && substr($msgRaw, -1) != ')' || $msgRaw[0] != '(' && substr($msgRaw, -1) == ')'){
				$msgRaw = '('.$msgRaw.')';
			}
			if($msgRaw[0] == '(' || $msgRaw[0] == '['){
				$msgRaw = substr($msgRaw, 1);
			}
			if(substr($msgRaw, -1) == ')' || substr($msgRaw, -1) == ']'){
				$msgRaw = substr($msgRaw, 0, -1);
			}
			
			$msgRawLen = strlen($msgRaw);
			while($msgRawLen){
				if($msgRaw[0] == '(' || $msgRaw[0] == '['){
					
					$pair = ')';
					if($msgRaw[0] == '['){
						$pair = ']';
					}
					
					// Find ')'
					$pos = strlen($msgRaw);
					while($pos > 0){
						if(substr($msgRaw, $pos, 1) == $pair){
							break;
						}
						$pos--;
					}
					
					$rvc++;
					$rv[$rvc] = $this->msgGetParenthesizedlist(substr($msgRaw, 0, $pos + 1), $level + 1);
					$msgRaw = substr($msgRaw, $pos + 1);
					$rvc++;
				}
				else{
					if(!isset($rv[$rvc])){
						$rv[$rvc] = '';
					}
					$rv[$rvc] .= $msgRaw[0];
					$msgRaw = substr($msgRaw, 1);
				}
				
				$msgRawLen = strlen($msgRaw);
			}
		}
		
		$rv2 = array();
		foreach($rv as $n => $item){
			if(is_string($item)){
				foreach($this->msgParseString($item) as $j => $sitem){
					$rv2[] = $sitem;
				}
			}
			else{
				$rv2[] = $item;
			}
		}
		
		return $rv2;
	}
	
	public function createSequenceSet($setStr, $isUid = false){
		// Collect messages with sequence-sets.
		$setStr = trim($setStr);
		
		$msgSeqNums = array();
		foreach(preg_split('/,/', $setStr) as $seqItem){
			$seqItem = trim($seqItem);
			
			$seqMin = 0;
			$seqMax = 0;
			$seqLen = 0;
			$seqAll = false;
			
			$items = preg_split('/:/', $seqItem, 2);
			$items = array_map('trim', $items);
			
			$nums = array();
			$count = $this->getServer()->getCountMailsByFolder($this->selectedFolder);
			if(!$count){
				return array();
			}
			
			// Check if it's a range.
			if(count($items) == 2){
				$seqMin = (int)$items[0];
				if($items[1] == '*'){
					if($isUid){
						// Search the last msg
						for($msgSeqNum = 1; $msgSeqNum <= $count; $msgSeqNum++){
							$uid = $this->getServer()->getMsgIdBySeq($msgSeqNum, $this->selectedFolder);
							
							if($uid > $seqMax){
								$seqMax = $uid;
							}
						}
					}
					else{
						$seqMax = $count;
					}
				}
				else{
					$seqMax = (int)$items[1];
				}
			}
			else{
				if($isUid){
					if($items[0] == '*'){
						$seqAll = true;
					}
					else{
						$seqMin = $seqMax = (int)$items[0];
					}
				}
				else{
					if($items[0] == '*'){
						$seqMin = 1;
						$seqMax = $count;
					}
					else{
						$seqMin = $seqMax = (int)$items[0];
					}
				}
			}
			
			if($seqMin > $seqMax){
				$tmp = $seqMin;
				$seqMin = $seqMax;
				$seqMax = $tmp;
			}
			
			$seqLen = $seqMax + 1 - $seqMin;
			
			if($isUid){
				if($seqLen >= 1){
					for($msgSeqNum = 1; $msgSeqNum <= $count; $msgSeqNum++){
						$uid = $this->getServer()->getMsgIdBySeq($msgSeqNum, $this->selectedFolder);
						
						if($uid >= $seqMin && $uid <= $seqMax || $seqAll){
							$nums[] = $msgSeqNum;
						}
						if(count($nums) >= $seqLen && !$seqAll){
							break;
						}
					}
				}
				/*else{
					throw new RuntimeException('Invalid minimum sequence length: "'.$seqLen.'" ('.$seqMin.'/'.$seqMax.')', 2);
				}*/
			}
			else{
				if($seqLen == 1){
					if($seqMin > 0 && $seqMin <= $count){
						$nums[] = (int)$seqMin;
					}
				}
				elseif($seqLen >= 2){
					for($msgSeqNum = 1; $msgSeqNum <= $count; $msgSeqNum++){
						if($msgSeqNum >= $seqMin && $msgSeqNum <= $seqMax){
							$nums[] = $msgSeqNum;
						}
						
						if(count($nums) >= $seqLen){
							break;
						}
					}
				}
				/*else{
					throw new RuntimeException('Invalid minimum sequence length: "'.$seqLen.'" ('.$seqMin.'/'.$seqMax.')', 1);
				}*/
			}
			
			$msgSeqNums = array_merge($msgSeqNums, $nums);
		}
		
		sort($msgSeqNums, SORT_NUMERIC);
		
		return $msgSeqNums;
	}
	
	public function msgHandle($msgRaw){
		$this->log('debug', 'client '.$this->id.' raw: /'.$msgRaw.'/');
		
		$rv = '';
		
		$args = $this->msgParseString($msgRaw, 3);
		$tag = array_shift($args);
		$command = array_shift($args);
		$commandcmp = strtolower($command);
		$args = array_shift($args);
		
		#$this->log('debug', 'client '.$this->id.': >'.$tag.'< >'.$command.'<');
		
		if($commandcmp == 'capability'){
			#$this->log('debug', 'client '.$this->id.' capability: '.$tag);
			
			return $this->sendCapability($tag);
		}
		elseif($commandcmp == 'noop'){
			return $this->sendNoop($tag);
		}
		elseif($commandcmp == 'logout'){
			$rv .= $this->sendBye('IMAP4rev1 Server logging out');
			$rv .= $this->sendLogout($tag);
			$this->shutdown();
		}
		elseif($commandcmp == 'authenticate'){
			$args = $this->msgParseString($args, 1);
			
			#$this->log('debug', 'client '.$this->id.' authenticate: "'.$args[0].'"');
			
			if(strtolower($args[0]) == 'plain'){
				$this->setStatus('authStep', 1);
				$this->setStatus('authTag', $tag);
				$this->setStatus('authMechanism', $args[0]);
				
				return $this->sendAuthenticate();
			}
			else{
				return $this->sendNo($args[0].' Unsupported authentication mechanism', $tag);
			}
		}
		elseif($commandcmp == 'login'){
			$args = $this->msgParseString($args, 2);
			
			#$this->log('debug', 'client '.$this->id.' login: "'.$args[0].'" "'.$args[1].'"');
			
			if(isset($args[0]) && $args[0] && isset($args[1]) && $args[1]){
				return $this->sendLogin($tag);
			}
			else{
				return $this->sendBad('Arguments invalid.', $tag);
			}
		}
		elseif($commandcmp == 'select'){
			$args = $this->msgParseString($args, 1);
			
			#$this->log('debug', 'client '.$this->id.' select: "'.$args[0].'"');
			
			if($this->getStatus('hasAuth')){
				if(isset($args[0]) && $args[0]){
					return $this->sendSelect($tag, $args[0]);
				}
				else{
					$this->selectedFolder = null;
					return $this->sendBad('Arguments invalid.', $tag);
				}
			}
			else{
				$this->selectedFolder = null;
				return $this->sendNo($commandcmp.' failure', $tag);
			}
		}
		elseif($commandcmp == 'create'){
			$args = $this->msgParseString($args, 1);
			
			#$this->log('debug', 'client '.$this->id.' create: '.$args[0]);
			
			if($this->getStatus('hasAuth')){
				if(isset($args[0]) && $args[0]){
					return $this->sendCreate($tag, $args[0]);
				}
				else{
					return $this->sendBad('Arguments invalid.', $tag);
				}
			}
			else{
				return $this->sendNo($commandcmp.' failure', $tag);
			}
		}
		elseif($commandcmp == 'subscribe'){
			$args = $this->msgParseString($args, 1);
			
			#$this->log('debug', 'client '.$this->id.' subscribe: '.$args[0]);
			
			if($this->getStatus('hasAuth')){
				if(isset($args[0]) && $args[0]){
					return $this->sendSubscribe($tag, $args[0]);
				}
				else{
					return $this->sendBad('Arguments invalid.', $tag);
				}
			}
			else{
				return $this->sendNo($commandcmp.' failure', $tag);
			}
		}
		elseif($commandcmp == 'unsubscribe'){
			$args = $this->msgParseString($args, 1);
			
			#$this->log('debug', 'client '.$this->id.' unsubscribe: '.$args[0]);
			
			if($this->getStatus('hasAuth')){
				if(isset($args[0]) && $args[0]){
					return $this->sendUnsubscribe($tag, $args[0]);
				}
				else{
					return $this->sendBad('Arguments invalid.', $tag);
				}
			}
			else{
				return $this->sendNo($commandcmp.' failure', $tag);
			}
		}
		elseif($commandcmp == 'list'){
			$args = $this->msgParseString($args, 2);
			
			#$this->log('debug', 'client '.$this->id.' list');
			
			if($this->getStatus('hasAuth')){
				if(isset($args[0]) && isset($args[1]) && $args[1]){
					$refName = $args[0];
					$folder = $args[1];
					return $this->sendList($tag, $refName, $folder);
				}
				else{
					return $this->sendBad('Arguments invalid.', $tag);
				}
			}
			else{
				return $this->sendNo($commandcmp.' failure', $tag);
			}
		}
		elseif($commandcmp == 'lsub'){
			$args = $this->msgParseString($args, 1);
			
			$this->log('debug', 'client '.$this->id.' lsub: '.(isset($args[0]) ? $args[0] : 'N/A'));
			
			if($this->getStatus('hasAuth')){
				if(isset($args[0]) && $args[0]){
					return $this->sendLsub($tag);
				}
				else{
					return $this->sendBad('Arguments invalid.', $tag);
				}
			}
			else{
				return $this->sendNo($commandcmp.' failure', $tag);
			}
		}
		elseif($commandcmp == 'append'){
			$args = $this->msgParseString($args, 4);
			
			$this->log('debug', 'client '.$this->id.' append');
			
			if($this->getStatus('hasAuth')){
				if(isset($args[0]) && $args[0] && isset($args[1]) && $args[1]){
					$this->setStatus('appendFlags', array());
					$this->setStatus('appendDate', '');
					$this->setStatus('appendLiteral', 0);
					$this->setStatus('appendMsg', '');
					
					$flags = array();
					$literal = 0;
					
					if(!isset($args[2]) && !isset($args[3])){
						$this->log('debug', 'client '.$this->id.' append: 2 not set, 3 not set');
						$literal = $args[1];
					}
					elseif(isset($args[2]) && !isset($args[3])){
						$this->log('debug', 'client '.$this->id.' append: 2 set, 3 not set, A');
						
						if($args[1][0] == '(' && substr($args[1], -1) == ')'){
							$this->log('debug', 'client '.$this->id.' append: 2 set, 3 not set, B');
							
							$flags = $this->msgGetParenthesizedlist($args[1]);
						}
						else{
							$this->log('debug', 'client '.$this->id.' append: 2 set, 3 not set, C');
							
							$this->setStatus('appendDate', $args[1]);
						}
						$literal = $args[2];
					}
					elseif(isset($args[2]) && isset($args[3])){
						$this->log('debug', 'client '.$this->id.' append: 2 set, 3 set');
						
						$flags = $this->msgGetParenthesizedlist($args[1]);
						$this->setStatus('appendDate', $args[2]);
						$literal = $args[3];
					}
					
					if($flags){
						#$flags = array_combine($flags, $flags);
						$flags = array_unique($flags);
					}
					$this->setStatus('appendFlags', $flags);
					
					if($literal[0] == '{' && substr($literal, -1) == '}'){
						$literal = (int)substr(substr($literal, 1), 0, -1);
					}
					else{
						return $this->sendBad('Arguments invalid.', $tag);
					}
					$this->setStatus('appendLiteral', $literal);
					
					$this->setStatus('appendStep', 1);
					$this->setStatus('appendTag', $tag);
					$this->setStatus('appendFolder', $args[0]);
					
					return $this->sendAppend();
				}
				else{
					return $this->sendBad('Arguments invalid.', $tag);
				}
			}
			else{
				return $this->sendNo($commandcmp.' failure', $tag);
			}
		}
		elseif($commandcmp == 'check'){
			#$this->log('debug', 'client '.$this->id.' check');
			
			if($this->getStatus('hasAuth')){
				return $this->sendCheck($tag);
			}
			else{
				return $this->sendNo($commandcmp.' failure', $tag);
			}
		}
		elseif($commandcmp == 'close'){
			$this->log('debug', 'client '.$this->id.' close');
			
			if($this->getStatus('hasAuth')){
				if($this->selectedFolder !== null){
					return $this->sendClose($tag);
				}
				else{
					return $this->sendNo('No mailbox selected.', $tag);
				}
			}
			else{
				return $this->sendNo($commandcmp.' failure', $tag);
			}
		}
		elseif($commandcmp == 'expunge'){
			$this->log('debug', 'client '.$this->id.' expunge');
			
			if($this->getStatus('hasAuth')){
				if($this->selectedFolder !== null){
					return $this->sendExpunge($tag);
				}
				else{
					return $this->sendNo('No mailbox selected.', $tag);
				}
			}
			else{
				return $this->sendNo($commandcmp.' failure', $tag);
			}
		}
		elseif($commandcmp == 'search'){
			$this->log('debug', 'client '.$this->id.' search');
			
			if($this->getStatus('hasAuth')){
				if(isset($args[0]) && $args[0]){
					if($this->selectedFolder !== null){
						$criteriaStr = $args[0];
						return $this->sendSearch($tag, $criteriaStr);
					}
					else{
						return $this->sendNo('No mailbox selected.', $tag);
					}
				}
				else{
					return $this->sendBad('Arguments invalid.', $tag);
				}
			}
			else{
				return $this->sendNo($commandcmp.' failure', $tag);
			}
		}
		elseif($commandcmp == 'store'){
			$args = $this->msgParseString($args, 3);
			
			$this->log('debug', 'client '.$this->id.' store: "'.$args[0].'" "'.$args[1].'" "'.$args[2].'"');
			
			if($this->getStatus('hasAuth')){
				if(isset($args[0]) && $args[0] && isset($args[1]) && $args[1] && isset($args[2]) && $args[2]){
					if($this->selectedFolder !== null){
						$seq = $args[0];
						$name = $args[1];
						$flagsStr = $args[2];
						$this->sendStore($tag, $seq, $name, $flagsStr);
					}
					else{
						$this->sendNo('No mailbox selected.', $tag);
					}
				}
				else{
					$this->sendBad('Arguments invalid.', $tag);
				}
			}
			else{
				$this->sendNo($commandcmp.' failure', $tag);
			}
		}
		elseif($commandcmp == 'copy'){
			$args = $this->msgParseString($args, 2);
			
			#$this->log('debug', 'client '.$this->id.' copy: "'.$args[0].'" "'.$args[1].'"');
			
			if($this->getStatus('hasAuth')){
				if(isset($args[0]) && $args[0] && isset($args[1]) && $args[1]){
					if($this->selectedFolder !== null){
						$seq = $args[0];
						$folder = $args[1];
						return $this->sendCopy($tag, $seq, $folder);
					}
					else{
						return $this->sendNo('No mailbox selected.', $tag);
					}
				}
				else{
					return $this->sendBad('Arguments invalid.', $tag);
				}
			}
			else{
				return $this->sendNo($commandcmp.' failure', $tag);
			}
		}
		elseif($commandcmp == 'uid'){
			if($this->getStatus('hasAuth')){
				if($this->selectedFolder !== null){
					return $this->sendUid($tag, $args);
				}
				else{
					return $this->sendNo('No mailbox selected.', $tag);
				}
			}
			else{
				return $this->sendNo($commandcmp.' failure', $tag);
			}
		}
		else{
			#$this->log('debug', 'client '.$this->id.' auth step:   '.$this->getStatus('authStep'));
			#$this->log('debug', 'client '.$this->id.' append step: '.$this->getStatus('appendStep'));
			
			if($this->getStatus('authStep') == 1){
				$this->setStatus('authStep', 2);
				return $this->sendAuthenticate();
			}
			elseif($this->getStatus('appendStep') >= 1){
				return $this->sendAppend($msgRaw);
			}
			else{
				$this->log('debug', 'client '.$this->id.' not implemented: "'.$tag.'" "'.$command.'" >"'.$args.'"<');
				return $this->sendBad('Not implemented: "'.$tag.'" "'.$command.'"', $tag);
			}
		}
		
		return $rv;
	}
	
	public function dataSend($msg){
		$output = $msg.static::MSG_SEPARATOR;
		$tmp = $msg;
		$tmp = str_replace("\r", '', $tmp);
		$tmp = str_replace("\n", '\\n', $tmp);
		
		if($this->getSocket()){
			$this->log('debug', 'client '.$this->id.' data send: "'.$tmp.'"');
			$this->getSocket()->write($output);
		}
		else{
			$this->log('debug', 'client '.$this->id.' DEBUG data send: "'.$tmp.'"');
		}
		
		return $output;
	}
	
	/**
	 * @codeCoverageIgnore
	 */
	public function sendHello(){
		$this->sendOk('IMAP4rev1 Service Ready');
	}
	
	private function sendCapability($tag){
		$rv = '';
		$rv .= $this->dataSend('* CAPABILITY IMAP4rev1 AUTH=PLAIN');
		$rv .= $this->sendOk('CAPABILITY completed', $tag);
		return $rv;
	}
	
	/**
	 * @codeCoverageIgnore
	 */
	private function sendNoop($tag){
		#$this->select();
		if($this->selectedFolder !== null){
			$this->sendSelectedFolderInfos();
		}
		return $this->sendOk('NOOP completed client '.$this->getId().', "'.$this->selectedFolder.'"', $tag);
	}
	
	private function sendLogout($tag){
		return $this->sendOk('LOGOUT completed', $tag);
	}
	
	private function sendAuthenticate(){
		$rv = '';
		if($this->getStatus('authStep') == 1){
			$rv .= $this->dataSend('+');
		}
		elseif($this->getStatus('authStep') == 2){
			$this->setStatus('hasAuth', true);
			$this->setStatus('authStep', 0);
			
			$rv .= $this->sendOk($this->getStatus('authMechanism').' authentication successful', $this->getStatus('authTag'));
		}
		
		return $rv;
	}
	
	private function sendLogin($tag){
		return $this->sendOk('LOGIN completed', $tag);
	}
	
	private function sendSelectedFolderInfos(){
		$rv = '';
		$nextId = $this->getServer()->getNextMsgId();
		$count = $this->getServer()->getCountMailsByFolder($this->selectedFolder);
		$recent = $this->getServer()->getCountMailsByFolder($this->selectedFolder, array(Storage::FLAG_RECENT));
		
		$firstUnseen = 0;
		for($msgSeqNum = 1; $msgSeqNum <= $count; $msgSeqNum++){
			$flags = $this->getServer()->getFlagsBySeq($msgSeqNum, $this->selectedFolder);
			if(!in_array(Storage::FLAG_SEEN, $flags) && !$firstUnseen){
				$firstUnseen = $msgSeqNum;
				break;
			}
		}
		
		foreach($this->expunge as $msgSeqNum){
			$rv .= $this->dataSend('* '.$msgSeqNum.' EXPUNGE');
		}
		
		$rv .= $this->dataSend('* '.$count.' EXISTS');
		$rv .= $this->dataSend('* '.$recent.' RECENT');
		$rv .= $this->sendOk('Message '.$firstUnseen.' is first unseen', null, 'UNSEEN '.$firstUnseen);
		#$rv .= $this->dataSend('* OK [UIDVALIDITY 3857529045] UIDs valid');
		if($nextId){
			$rv .= $this->sendOk('Predicted next UID', null, 'UIDNEXT '.$nextId);
		}
		$availableFlags = array(Storage::FLAG_ANSWERED,
			Storage::FLAG_FLAGGED,
			Storage::FLAG_DELETED,
			Storage::FLAG_SEEN,
			Storage::FLAG_DRAFT);
		$rv .= $this->dataSend('* FLAGS ('.join(' ', $availableFlags).')');
		$rv .= $this->sendOk('Limited', null, 'PERMANENTFLAGS ('.Storage::FLAG_DELETED.' '.Storage::FLAG_SEEN.' \*)');
		
		return $rv;
	}
	
	private function sendSelect($tag, $folder){
		if(strtolower($folder) == 'inbox' && $folder != 'INBOX'){
			// Set folder to INBOX if folder is not INBOX
			// e.g. Inbox, INbOx or something like this.
			$folder = 'INBOX';
		}
		
		if($this->select($folder)){
			$rv = $this->sendSelectedFolderInfos();
			$rv .= $this->sendOk('SELECT completed', $tag, 'READ-WRITE');
			return $rv;
		}
		
		return $this->sendNo('"'.$folder.'" no such mailbox', $tag);
	}
	
	private function sendCreate($tag, $folder){
		if(strpos($folder, '/') !== false){
			$msg = 'invalid name';
			$msg .= ' - no directory separator allowed in folder name';
			return $this->sendNo('CREATE failure: '.$msg, $tag);
		}
		
		if($this->getServer()->addFolder($folder)){
			return $this->sendOk('CREATE completed', $tag);
		}
		
		return $this->sendNo('CREATE failure: folder already exists', $tag);
	}
	
	private function sendSubscribe($tag, $folder){
		if($this->getServer()->folderExists($folder)){
			// @NOTICE NOT_IMPLEMENTED
			
			#fwrite(STDOUT, 'subsc: '.$folder."\n");
			
			#$folders = $this->getServer()->getFolders($folder);
			$this->subscriptions[] = $folder;
			
			return $this->sendOk('SUBSCRIBE completed', $tag);
		}
		
		return $this->sendNo('SUBSCRIBE failure: no subfolder named test_dir', $tag);
	}
	
	private function sendUnsubscribe($tag, $folder){
		if($this->getServer()->folderExists($folder)){
			// @NOTICE NOT_IMPLEMENTED
			
			#$folders = $this->getServer()->getFolders($folder);
			#unset($this->subscriptions[$folder]);
			
			return $this->sendOk('UNSUBSCRIBE completed', $tag);
		}
		
		return $this->sendNo('UNSUBSCRIBE failure: no subfolder named test_dir', $tag);
	}
	
	private function sendList($tag, $baseFolder, $folder){
		$this->log('debug', 'client '.$this->id.' list: /'.$baseFolder.'/ /'.$folder.'/');
		
		$folder = str_replace('%', '*', $folder); // @NOTICE NOT_IMPLEMENTED
		
		$folders = $this->getServer()->getFolders($baseFolder, $folder, true);
		$rv = '';
		if(count($folders)){
			foreach($folders as $cfolder){
				$rv .= $this->dataSend('* LIST () "." "'.$cfolder.'"');
			}
		}
		else{
			if($this->getServer()->folderExists($folder)){
				$rv .= $this->dataSend('* LIST () "." "'.$folder.'"');
			}
		}
		
		$rv .= $this->sendOk('LIST completed', $tag);
		
		return $rv;
	}
	
	private function sendLsub($tag){
		#$this->log('debug', 'client '.$this->id.' sendLsub');
		
		$rv = '';
		foreach($this->subscriptions as $subscription){
			$rv .= $this->dataSend('* LSUB () "." "'.$subscription.'"');
		}
		
		$rv .= $this->sendOk('LSUB completed', $tag);
		return $rv;
	}
	
	private function sendAppend($data = ''){
		$appendMsgLen = strlen($this->getStatus('appendMsg'));
		#$this->log('debug', 'client '.$this->id.' append step: '.$this->getStatus('appendStep'));
		#$this->log('debug', 'client '.$this->id.' append len: '.$appendMsgLen);
		#$this->log('debug', 'client '.$this->id.' append lit: '.$this->getStatus('appendLiteral'));
		
		if($this->getStatus('appendStep') == 1){
			$this->status['appendStep']++;
			
			return $this->dataSend('+ Ready for literal data');
		}
		elseif($this->getStatus('appendStep') == 2){
			if($appendMsgLen < $this->getStatus('appendLiteral')){
				$this->status['appendMsg'] .= $data.Headers::EOL;
				$appendMsgLen = strlen($this->getStatus('appendMsg'));
			}
			
			if($appendMsgLen >= $this->getStatus('appendLiteral')){
				$this->status['appendStep']++;
				$this->log('debug', 'client '.$this->id.' append len reached: '.$appendMsgLen);
				
				$message = Message::fromString($this->getStatus('appendMsg'));
				
				try{
					$this->getServer()->addMail($message, $this->getStatus('appendFolder'),
						$this->getStatus('appendFlags'), false);
					$this->log('debug', 'client '.$this->id.' append completed: '.$this->getStatus('appendStep'));
					return $this->sendOk('APPEND completed', $this->getStatus('appendTag'));
				}
				catch(Exception $e){
					$noMsg = 'Can not get folder: '.$this->getStatus('appendFolder');
					return $this->sendNo($noMsg, $this->getStatus('appendTag'), 'TRYCREATE');
				}
			}
			else{
				$diff = $this->getStatus('appendLiteral') - $appendMsgLen;
				$this->log('debug', 'client '.$this->id.' append left: '.$diff.' ('.$appendMsgLen.')');
			}
		}
	}
	
	private function sendCheck($tag){
		if($this->selectedFolder !== null){
			return $this->sendOk('CHECK completed', $tag);
		}
		else{
			return $this->sendNo('No mailbox selected.', $tag);
		}
	}
	
	private function sendClose($tag){
		#$this->select();
		$this->log('debug', 'client '.$this->id.' current folder: '.$this->selectedFolder);
		
		$rv = '';
		$this->sendExpungeRaw();
		
		$this->selectedFolder = null;
		$rv .= $this->sendOk('CLOSE completed', $tag);
		return $rv;
	}
	
	private function sendExpungeRaw(){
		$this->log('debug', 'client '.$this->id.' sendExpungeRaw');
		
		$msgSeqNumsExpunge = array();
		$expungeDiff = 0;
		
		$msgSeqNums = $this->createSequenceSet('*');
		
		foreach($msgSeqNums as $msgSeqNum){
			$expungeSeqNum = $msgSeqNum - $expungeDiff;
			$this->log('debug', 'client '.$this->id.' check msg: '.$msgSeqNum.', '.$expungeDiff.', '.$expungeSeqNum);
			
			$flags = $this->getServer()->getFlagsBySeq($expungeSeqNum, $this->selectedFolder);
			if(in_array(Storage::FLAG_DELETED, $flags)){
				$this->log('debug', 'client '.$this->id.'      del msg: '.$expungeSeqNum);
				$this->getServer()->removeMailBySeq($expungeSeqNum, $this->selectedFolder);
				$msgSeqNumsExpunge[] = $expungeSeqNum;
				$expungeDiff++;
			}
		}
		
		return $msgSeqNumsExpunge;
	}
	
	private function sendExpunge($tag){
		$rv = '';
		
		$msgSeqNumsExpunge = $this->sendExpungeRaw();
		foreach($msgSeqNumsExpunge as $msgSeqNum){
			#$this->log('debug', 'client '.$this->id.' expunge: '.$msgSeqNum);
			$rv .= $this->dataSend('* '.$msgSeqNum.' EXPUNGE');
		}
		$rv .= $this->sendOk('EXPUNGE completed', $tag);
		
		$this->expunge = array();
		
		return $rv;
	}
	
	public function parseSearchKeys($list, &$posOffset = 0, $maxItems = 0, $addAnd = true, $level = 0){
		$func = __FUNCTION__;
		$len = count($list);
		$rv = array();
		
		if($len <= 1){
			return $list;
		}
		
		$itemsC = 0;
		$pos = 0;
		for($pos = 0; $pos < $len; $pos++){
			$orgpos = $pos;
			$item = $list[$pos];
			$itemWithArgs = '';
			
			$and = true;
			$offset = 0;
			
			if(is_array($item)){
				$subPosOffset = 0;
				$itemWithArgs = array($this->$func($item, $subPosOffset, 0, true, $level + 1));
			}
			else{
				$itemcmp = strtolower($item);
				if(
					$itemcmp == 'all'
					|| $itemcmp == 'answered'
					|| $itemcmp == 'deleted'
					|| $itemcmp == 'draft'
					|| $itemcmp == 'flagged'
					|| $itemcmp == 'new'
					|| $itemcmp == 'old'
					|| $itemcmp == 'recent'
					|| $itemcmp == 'seen'
					|| $itemcmp == 'unanswered'
					|| $itemcmp == 'undeleted'
					|| $itemcmp == 'undraft'
					|| $itemcmp == 'unflagged'
					|| $itemcmp == 'unseen'
				){
					$itemWithArgs = $item;
				}
				elseif($itemcmp == 'bcc'
					|| $itemcmp == 'before'
					|| $itemcmp == 'body'
					|| $itemcmp == 'cc'
					|| $itemcmp == 'from'
					|| $itemcmp == 'keyword'
					|| $itemcmp == 'larger'
					|| $itemcmp == 'on'
					|| $itemcmp == 'sentbefore'
					|| $itemcmp == 'senton'
					|| $itemcmp == 'sentsince'
					|| $itemcmp == 'since'
					|| $itemcmp == 'smaller'
					|| $itemcmp == 'subject'
					|| $itemcmp == 'text'
					|| $itemcmp == 'to'
					|| $itemcmp == 'uid'
					|| $itemcmp == 'unkeyword'
				){
					$itemWithArgs = $item.' '.$list[$pos + 1];
					$offset++;
				}
				elseif($itemcmp == 'header'){
					$itemWithArgs = $item.' '.$list[$pos + 1].' '.$list[$pos + 2];
					$offset += 2;
				}
				elseif($itemcmp == 'or'){
					$rest = array_slice($list, $pos + 1);
					$subPosOffset = 0;
					$sublist = $this->$func($rest, $subPosOffset, 2, false, $level + 1);
					$itemWithArgs = array(array($sublist[0], 'OR', $sublist[1]));
					
					$offset += $subPosOffset;
				}
				elseif($itemcmp == 'and'){
					$and = false;
				}
				elseif($itemcmp == 'not'){
					$rest = array_slice($list, $pos + 1);
					$subPosOffset = 0;
					$sublist = $this->$func($rest, $subPosOffset, 1, false, $level + 1);
					$itemWithArgs = array($item, $sublist[0]);
					$offset += $subPosOffset;
				}
				elseif(is_numeric($itemcmp)){
					$itemWithArgs = $item;
				}
			}
			
			if($pos <= 0){
				$and = false;
			}
			
			if($addAnd && $and){
				$rv[] = 'AND';
				$and = false;
			}
			if($itemWithArgs){
				if(is_array($itemWithArgs)){
					$rv = array_merge($rv, $itemWithArgs);
				}
				else{
					$rv[] = $itemWithArgs;
				}
			}
			
			$pos += $offset;
			$itemsC++;
			if($maxItems && $itemsC >= $maxItems){
				break;
			}
		}
		
		$posOffset = $pos + 1;
		
		return $rv;
	}
	
	public function searchMessageCondition($message, $messageSeqNum, $messageUid, $searchKey){
		$items = preg_split('/ /', $searchKey, 3);
		$itemcmp = strtolower($items[0]);
		
		$flags = $this->getServer()->getFlagsById($messageUid);
		
		$rv = false;
		switch($itemcmp){
			case 'all':
				$rv = true;
				break;
			case 'answered':
				$rv = in_array(Storage::FLAG_ANSWERED, $flags);
				break;
			case 'bcc':
				$searchStr = strtolower($items[1]);
				$bccAddressList = $message->getBcc();
				if(count($bccAddressList)){
					foreach($bccAddressList as $bcc){
						$rv = strpos(strtolower($bcc->getEmail()), $searchStr) !== false;
						break;
					}
				}
				break;
			case 'before':
				// @NOTICE NOT_IMPLEMENTED
				break;
			case 'body':
				$searchStr = strtolower($items[1]);
				$rv = strpos(strtolower($message->getBody()), $searchStr) !== false;
				break;
			case 'cc':
				$searchStr = strtolower($items[1]);
				$ccAddressList = $message->getCc();
				if(count($ccAddressList)){
					foreach($ccAddressList as $from){
						$rv = strpos(strtolower($from->getEmail()), $searchStr) !== false;
						break;
					}
				}
				break;
			case 'deleted':
				$rv = in_array(Storage::FLAG_DELETED, $flags);
				break;
			case 'draft':
				$rv = in_array(Storage::FLAG_DRAFT, $flags);
				break;
			case 'flagged':
				$rv = in_array(Storage::FLAG_FLAGGED, $flags);
				break;
			case 'from':
				$searchStr = strtolower($items[1]);
				$fromAddressList = $message->getFrom();
				if(count($fromAddressList)){
					foreach($fromAddressList as $from){
						$rv = strpos(strtolower($from->getEmail()), $searchStr) !== false;
						break;
					}
				}
				break;
			case 'header':
				$searchStr = strtolower($items[2]);
				$fieldName = $items[1];
				$header = $message->getHeaders()->get($fieldName);
				$val = $header->getFieldValue();
				$rv = strpos(strtolower($val), $searchStr) !== false;
				break;
			case 'keyword':
				// @NOTICE NOT_IMPLEMENTED
				break;
			case 'larger':
				$rv = strlen($message->getBody()) > (int)$items[1];
				break;
			case 'new':
				$rv = in_array(Storage::FLAG_RECENT, $flags) && !in_array(Storage::FLAG_SEEN, $flags);
				break;
			case 'old':
				$rv = !in_array(Storage::FLAG_RECENT, $flags);
				break;
			case 'on':
				$checkDate = new DateTime($items[1]);
				$messageDate = new DateTime($message->getHeaders()->get('Date')->getFieldValue());
				$rv = $messageDate->format('Y-m-d') == $checkDate->format('Y-m-d');
				break;
			case 'recent':
				$rv = in_array(Storage::FLAG_RECENT, $flags);
				break;
			case 'seen':
				$rv = in_array(Storage::FLAG_SEEN, $flags);
				break;
			case 'sentbefore':
				$checkDate = new DateTime($items[1]);
				$messageDate = new DateTime($message->getHeaders()->get('Date')->getFieldValue());
				$rv = $messageDate < $checkDate;
				break;
			case 'senton':
				$checkDate = new DateTime($items[1]);
				$messageDate = new DateTime($message->getHeaders()->get('Date')->getFieldValue());
				$rv = $messageDate == $checkDate;
				break;
			case 'sentsince':
				$checkDate = new DateTime($items[1]);
				$messageDate = new DateTime($message->getHeaders()->get('Date')->getFieldValue());
				$rv = $messageDate >= $checkDate;
				break;
			case 'since':
				// @NOTICE NOT_IMPLEMENTED
				break;
			case 'smaller':
				$rv = strlen($message->getBody()) < (int)$items[1];
				break;
			case 'subject':
				if(isset($items[2])){
					$items[1] .= ' '.$items[2];
					unset($items[2]);
				}
				$searchStr = strtolower($items[1]);
				$rv = strpos(strtolower($message->getSubject()), $searchStr) !== false;
				break;
			case 'text':
				$searchStr = strtolower($items[1]);
				$rv = strpos(strtolower($message->getBody()), $searchStr) !== false;
				break;
			case 'to':
				$searchStr = strtolower($items[1]);
				$toAddressList = $message->getTo();
				if(count($toAddressList)){
					foreach($toAddressList as $to){
						$rv = strpos(strtolower($to->getEmail()), $searchStr) !== false;
						break;
					}
				}
				break;
			case 'uid':
				$searchId = (int)$items[1];
				$rv = $searchId == $messageUid;
				break;
			case 'unanswered':
				$rv = !in_array(Storage::FLAG_ANSWERED, $flags);
				break;
			case 'undeleted':
				$rv = !in_array(Storage::FLAG_DELETED, $flags);
				break;
			case 'undraft':
				$rv = !in_array(Storage::FLAG_DRAFT, $flags);
				break;
			case 'unflagged':
				$rv = !in_array(Storage::FLAG_FLAGGED, $flags);
				break;
			case 'unkeyword':
				// @NOTICE NOT_IMPLEMENTED
				break;
			case 'unseen':
				$rv = !in_array(Storage::FLAG_SEEN, $flags);
				break;
			
			default:
				if(is_numeric($itemcmp)){
					$searchId = (int)$itemcmp;
					$rv = $searchId == $messageSeqNum;
				}
		}
		return $rv;
	}
	
	public function parseSearchMessage($message, $messageSeqNum, $messageUid, $isUid, $gate, $level = 1){
		$func = __FUNCTION__;
		
		$subgates = array();
		if($gate instanceof Gate){
			if($gate->getObj1()){
				$subgates[] = $gate->getObj1();
			}
			if($gate->getObj2()){
				$subgates[] = $gate->getObj2();
			}
		}
		else{
			$val = $this->searchMessageCondition($message, $messageSeqNum, $messageUid, $gate->getValue());
			$gate->setValue($val);
		}
		
		foreach($subgates as $subgate){
			if($subgate instanceof AndGate){
				$this->$func($message, $messageSeqNum, $messageUid, $isUid, $subgate, $level + 1);
			}
			elseif($subgate instanceof OrGate){
				$this->$func($message, $messageSeqNum, $messageUid, $isUid, $subgate, $level + 1);
			}
			elseif($subgate instanceof NotGate){
				$this->$func($message, $messageSeqNum, $messageUid, $isUid, $subgate, $level + 1);
			}
			elseif($subgate instanceof Obj){
				$val = $this->searchMessageCondition($message, $messageSeqNum, $messageUid, $subgate->getValue());
				$subgate->setValue($val);
			}
		}
		
		return $gate->bool();
	}
	
	private function sendSearchRaw($criteriaStr, $isUid = false){
		$criteria = array();
		$criteria = $this->msgGetParenthesizedlist($criteriaStr);
		$criteria = $this->parseSearchKeys($criteria);
		
		$tree = new CriteriaTree($criteria);
		$tree->build();
		
		if(!$tree->getRootGate()){
			return '';
		}
		
		$ids = array();
		$msgSeqNums = $this->createSequenceSet('*');
		foreach($msgSeqNums as $msgSeqNum){
			$uid = $this->getServer()->getMsgIdBySeq($msgSeqNum, $this->selectedFolder);
			$this->log('debug', 'client '.$this->id.' check msg: '.$msgSeqNum.', '.$uid);
			
			$message = $this->getServer()->getMailBySeq($msgSeqNum, $this->selectedFolder);
			
			$add = false;
			if($message){
				$rootGate = clone $tree->getRootGate();
				$add = $this->parseSearchMessage($message, $msgSeqNum, $uid, $isUid, $rootGate);
			}
			if($add){
				if($isUid){
					$ids[] = $uid;
				}
				else{
					// @NOTICE NOT_IMPLEMENTED
					$ids[] = $msgSeqNum;
				}
			}
		}
		
		sort($ids);
		
		$rv = '';
		while($ids){
			$sendIds = array_slice($ids, 0, 30);
			$ids = array_slice($ids, 30);
			
			$rv .= $this->dataSend('* SEARCH '.join(' ', $sendIds).'');
		}
		return $rv;
	}
	
	/**
	 * @codeCoverageIgnore
	 */
	private function sendSearch($tag, $criteriaStr){
		$this->log('debug', 'client '.$this->id.' current folder: '.$this->selectedFolder);
		
		$rv = '';
		$rv .= $this->sendSearchRaw($criteriaStr, false);
		$rv .= $this->sendOk('SEARCH completed', $tag);
		return $rv;
	}
	
	private function sendFetchRaw($tag, $seq, $name, $isUid = false){
		$rv = '';
		
		$msgItems = array();
		if($isUid){
			$msgItems['uid'] = '';
		}
		if(isset($name)){
			$wanted = $this->msgGetParenthesizedlist($name);
			foreach($wanted as $n => $item){
				if(is_string($item)){
					$itemcmp = strtolower($item);
					if($itemcmp == 'body.peek'){
						$next = $wanted[$n + 1];
						$nextr = array();
						if(is_array($next)){
							$keys = array();
							$vals = array();
							foreach($next as $n => $val){
								if($n % 2 == 0){
									$keys[] = strtolower($val);
								}
								else{
									$vals[] = $val;
								}
							}
							$nextr = array_combine($keys, $vals);
						}
						$msgItems[$itemcmp] = $nextr;
					}
					else{
						$msgItems[$itemcmp] = '';
					}
				}
			}
		}
		
		$msgSeqNums = $this->createSequenceSet($seq, $isUid);
		
		// Process collected msgs.
		foreach($msgSeqNums as $msgSeqNum){
			$msgId = $this->getServer()->getMsgIdBySeq($msgSeqNum, $this->selectedFolder);
			if(!$msgId){
				$this->log('error', 'Can not get ID for seq num '.$msgSeqNum.' from root storage.');
				continue;
			}
			
			$message = $this->getServer()->getMailById($msgId);
			$flags = $this->getServer()->getFlagsById($msgId);
			
			$output = array();
			$outputHasFlag = false;
			$outputBody = '';
			foreach($msgItems as $item => $val){
				if($item == 'flags'){
					$outputHasFlag = true;
				}
				elseif($item == 'body' || $item == 'body.peek'){
					$peek = $item == 'body.peek';
					$section = '';
					
					$msgStr = $message->toString();
					if(isset($val['header'])){
						$section = 'HEADER';
						$msgStr = $message->getHeaders()->toString();
					}
					elseif(isset($val['header.fields'])){
						$section = 'HEADER';
						$msgStr = '';
						
						$headers = $message->getHeaders();
						
						$headerStrs = array();
						foreach($val['header.fields'] as $fieldNum => $field){
							$fieldHeader = $headers->get($field);
							if($fieldHeader !== false){
								$msgStr .= $fieldHeader->toString().Headers::EOL;
							}
						}
					}
					
					$msgStr .= Headers::EOL;
					$msgStrLen = strlen($msgStr);
					#$output[] = 'BODY['.$section.'] {'.$msgStrLen.'}'.Headers::EOL.$msgStr.Headers::EOL;
					$outputBody = 'BODY['.$section.'] {'.$msgStrLen.'}'.Headers::EOL.$msgStr;
				}
				elseif($item == 'rfc822.size'){
					$size = strlen($message->toString());
					$output[] = 'RFC822.SIZE '.$size;
				}
				elseif($item == 'uid'){
					$output[] = 'UID '.$msgId;
				}
			}
			
			if($outputHasFlag){
				$output[] = 'FLAGS ('.join(' ', $flags).')';
			}
			if($outputBody){
				$output[] = $outputBody;
			}
			
			$rv .= $this->dataSend('* '.$msgSeqNum.' FETCH ('.join(' ', $output).')');
			
			unset($flags[Storage::FLAG_RECENT]);
			$this->getServer()->setFlagsById($msgId, $flags);
		}
		
		return $rv;
	}
	
	/*private function sendFetch($tag, $seq, $name){
		#$this->select();
		$this->log('debug', 'client '.$this->id.' current folder: '.$this->selectedFolder);
		
		$this->sendFetchRaw($tag, $seq, $name, false);
		$this->sendOk('FETCH completed', $tag);
	}*/
	
	private function sendStoreRaw($tag, $seq, $name, $flagsStr, $isUid = false){
		$rv = '';
		
		$flags = $this->msgGetParenthesizedlist($flagsStr);
		unset($flags[Storage::FLAG_RECENT]);
		$flags = array_unique($flags);
		
		$add = false;
		$rem = false;
		$silent = false;
		switch(strtolower($name)){
			case '+flags.silent':
				$silent = true;
			case '+flags':
				$add = true;
				break;
			
			case '-flags.silent':
				$silent = true;
			case '-flags':
				$rem = true;
				break;
		}
		
		$msgSeqNums = $this->createSequenceSet($seq, $isUid);
		
		// Process collected msgs.
		foreach($msgSeqNums as $msgSeqNum){
			$messageFlags = $this->getServer()->getFlagsBySeq($msgSeqNum, $this->selectedFolder);
			
			$messageFlags = array_unique($messageFlags);
			
			if(!$add && !$rem){
				$messageFlags = $flags;
			}
			elseif($add){
				$messageFlags = array_merge($messageFlags, $flags);
			}
			elseif($rem){
				foreach($flags as $flag){
					if(($key = array_search($flag, $messageFlags)) !== false){
						unset($messageFlags[$key]);
					}
					$flags = array_values($flags);
				}
			}
			
			$messageFlags = array_values($messageFlags);
			$this->getServer()->setFlagsBySeq($msgSeqNum, $this->selectedFolder, $messageFlags);
			$messageFlags = $this->getServer()->getFlagsBySeq($msgSeqNum, $this->selectedFolder);
			
			if(!$silent){
				$rv .= $this->dataSend('* '.$msgSeqNum.' FETCH (FLAGS ('.join(' ', $messageFlags).'))');
			}
		}
		
		return $rv;
	}
	
	/**
	 * @codeCoverageIgnore
	 */
	private function sendStore($tag, $seq, $name, $flagsStr){
		#$this->select();
		$this->log('debug', 'client '.$this->id.' current folder: '.$this->selectedFolder);
		
		$this->sendStoreRaw($tag, $seq, $name, $flagsStr, false);
		$this->sendOk('STORE completed', $tag);
	}
	
	private function sendCopy($tag, $seq, $folder, $isUid = false){
		$msgSeqNums = $this->createSequenceSet($seq, $isUid);
		
		if($this->getServer()->getCountMailsByFolder($this->selectedFolder) == 0){
			return $this->sendBad('No messages in selected mailbox.', $tag);
		}
		
		if(!$this->getServer()->folderExists($folder)){
			return $this->sendNo('Can not get folder: no subfolder named '.$folder, $tag, 'TRYCREATE');
		}
		
		foreach($msgSeqNums as $msgSeqNum){
			$this->getServer()->copyMailBySequenceNum($msgSeqNum, $this->selectedFolder, $folder);
		}
		
		return $this->sendOk('COPY completed', $tag);
	}
	
	private function sendUid($tag, $args){
		$this->log('debug', 'client '.$this->id.' sendUid: "'.$args.'"');
		
		$args = $this->msgParseString($args, 2);
		$command = $args[0];
		$commandcmp = strtolower($command);
		if(isset($args[1])){
			$args = $args[1];
		}
		else{
			return $this->sendBad('Arguments invalid.', $tag);
		}
		
		$rv = '';
		if($commandcmp == 'copy'){
			$args = $this->msgParseString($args, 2);
			$seq = $args[0];
			if(!isset($args[1])){
				return $this->sendBad('Arguments invalid.', $tag);
			}
			$folder = $args[1];
			
			$rv .= $this->sendCopy($tag, $seq, $folder, true);
		}
		elseif($commandcmp == 'fetch'){
			$args = $this->msgParseString($args, 2);
			$seq = $args[0];
			$name = $args[1];
			
			$rv .= $this->sendFetchRaw($tag, $seq, $name, true);
			$rv .= $this->sendOk('UID FETCH completed', $tag);
		}
		elseif($commandcmp == 'store'){
			$args = $this->msgParseString($args, 3);
			$seq = $args[0];
			$name = $args[1];
			$flagsStr = $args[2];
			
			$rv .= $this->sendStoreRaw($tag, $seq, $name, $flagsStr, true);
			$rv .= $this->sendOk('UID STORE completed', $tag);
		}
		elseif($commandcmp == 'search'){
			$criteriaStr = $args;
			$rv .= $this->sendSearchRaw($criteriaStr, true);
			$rv .= $this->sendOk('UID SEARCH completed', $tag);
		}
		else{
			return $this->sendBad('Arguments invalid.', $tag);
		}
		
		return $rv;
	}
	
	public function sendOk($text, $tag = null, $code = null){
		if($tag === null){
			$tag = '*';
		}
		return $this->dataSend($tag.' OK'.($code ? ' ['.$code.']' : '').' '.$text);
	}
	
	public function sendNo($text, $tag = null, $code = null){
		if($tag === null){
			$tag = '*';
		}
		return $this->dataSend($tag.' NO'.($code ? ' ['.$code.']' : '').' '.$text);
	}
	
	public function sendBad($text, $tag = null, $code = null){
		if($tag === null){
			$tag = '*';
		}
		return $this->dataSend($tag.' BAD'.($code ? ' ['.$code.']' : '').' '.$text);
	}
	
	public function sendPreauth($text, $code = null){
		return $this->dataSend('* PREAUTH'.($code ? ' ['.$code.']' : '').' '.$text);
	}
	
	public function sendBye($text, $code = null){
		return $this->dataSend('* BYE'.($code ? ' ['.$code.']' : '').' '.$text);
	}
	
	public function shutdown(){
		if(!$this->getStatus('hasShutdown')){
			$this->setStatus('hasShutdown', true);
			
			if($this->getSocket()){
				$this->getSocket()->shutdown();
				$this->getSocket()->close();
			}
		}
	}
	
	public function select($folder){
		if($this->getServer()->folderExists($folder)){
			$this->log('debug', 'client '.$this->id.' old folder: "'.$this->selectedFolder.'"');
			$this->selectedFolder = $folder;
			$this->log('debug', 'client '.$this->id.' new folder: "'.$this->selectedFolder.'"');
			
			return true;
		}
		
		$this->selectedFolder = null;
		return false;
	}
	
	public function getSelectedFolder(){
		return $this->selectedFolder;
	}
	
}
