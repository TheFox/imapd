<?php

namespace TheFox\Imap;

use Exception;
use RuntimeException;
use InvalidArgumentException;
use DateTime;

use Zend\Mail\Storage;
use Zend\Mail\Headers;
use Zend\Mail\Message;
use Zend\Mail\Header\Date;

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
	private $ip = '';
	private $port = 0;
	private $recvBufferTmp = '';
	private $expunge = array();
	private $subscriptions = array();
	
	// Remember the selected mailbox for each client.
	private $selectedFolder = null;
	
	public function __construct(){
		#print __CLASS__.'->'.__FUNCTION__.''."\n";
		
		$this->status['hasShutdown'] = false;
		$this->status['hasAuth'] = false;
		$this->status['authStep'] = 0;
		$this->status['authTag'] = '';
		$this->status['authMechanism'] = '';
		$this->status['appendStep'] = 0;
		$this->status['appendTag'] = '';
		$this->status['appendFolder'] = '';
		$this->status['appendFlags'] = array();
		$this->status['appendDate'] = '';
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
		#fwrite(STDOUT, "log: $level, $msg\n");
		
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
				
				$this->log('debug', 'client '.$this->id.': collect data');
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
	
	public function msgParseString($msgRaw, $argsMax = null){
		$str = new StringParser($msgRaw, $argsMax);
		return $str->parse();
	}
	
	public function msgGetArgs($msgRaw, $argsMax = null){
		$args = $this->msgParseString($msgRaw, $argsMax);
		
		#ve($args);
		
		$tag = array_shift($args);
		$command = array_shift($args);
		
		return array(
			'tag' => $tag,
			'command' => $command,
			'args' => $args,
		);
	}
	
	public function msgGetParenthesizedlist($msgRaw, $level = 0){
		#fwrite(STDOUT, str_repeat(' ', $level * 4)."raw '".$msgRaw."'\n");
		#usleep(100000);
		
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
						#fwrite(STDOUT, str_repeat(' ', $level * 4)."    find $pos '".substr($msgRaw, $pos, 1)."'\n");
						if(substr($msgRaw, $pos, 1) == $pair){
							break;
						}
						$pos--;
						#usleep(100000);
					}
					
					#fwrite(STDOUT, str_repeat(' ', $level * 4)."    c open\n");
					$rvc++;
					$rv[$rvc] = $this->msgGetParenthesizedlist(substr($msgRaw, 0, $pos + 1), $level + 1);
					$msgRaw = substr($msgRaw, $pos + 1);
					#fwrite(STDOUT, str_repeat(' ', $level * 4)."    left '$msgRaw'\n");
					$rvc++;
				}
				else{
					if(!isset($rv[$rvc])){
						$rv[$rvc] = '';
					}
					$rv[$rvc] .= $msgRaw[0];
					
					##fwrite(STDOUT, str_repeat(' ', $level * 4)."    c '".$msgRaw[0]."' '".$rv[$rvc]."'\n");
					#fwrite(STDOUT, str_repeat(' ', $level * 4)."    c '".$msgRaw."' '".$rv[$rvc]."'\n");
					$msgRaw = substr($msgRaw, 1);
				}
				
				$msgRawLen = strlen($msgRaw);
				#usleep(100000);
			}
		}
		
		$rv2 = array();
		foreach($rv as $n => $item){
			if(is_string($item)){
				#fwrite(STDOUT, str_repeat(' ', $level * 4)."item $n '$item'\n");
				
				foreach($this->msgParseString($item) as $j => $sitem){
					#fwrite(STDOUT, str_repeat(' ', $level * 4)."    sitem $j '$sitem'\n");
					$rv2[] = $sitem;
				}
			}
			else{
				#fwrite(STDOUT, str_repeat(' ', $level * 4)."item $n is array\n");
				$rv2[] = $item;
			}
		}
		
		return $rv2;
	}
	
	public function createSequenceSet($setStr, $isUid = false){
		// Collect messages with sequence-sets.
		$setStr = trim($setStr);
		#$this->log('debug', 'createSequenceSet: '.$setStr);
		#fwrite(STDOUT, 'createSequenceSet'."\n");
		
		$msgSeqNums = array();
		foreach(preg_split('/,/', $setStr) as $seqItem){
			$seqItem = trim($seqItem);
			#fwrite(STDOUT, 'createSequenceSet: '.$seqItem."\n");
			
			$seqMin = 0;
			$seqMax = 0;
			$seqLen = 0;
			$seqAll = false;
			
			$items = preg_split('/:/', $seqItem, 2);
			#$items = array_map(function($item){ return trim($item);}, $items);
			$items = array_map('trim', $items);
			
			$nums = array();
			#ve($items);
			
			#fwrite(STDOUT, 'selected folder: /'.$this->selectedFolder.'/'."\n");
			
			#$storage = $this->getServer()->getStorageMailbox();
			#$count = $storage['object']->countMessages();
			$count = $this->getServer()->getCountMailsByFolder($this->selectedFolder);
			if(!$count){
				#throw new RuntimeException('No messages in selected mailbox.', 2);
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
							
							#$this->log('debug', 'createSequenceSet search: '.$uid.'');
							#fwrite(STDOUT, 'createSequenceSet search: '.$uid."\n");
							
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
					#ve($items);
					if($items[0] == '*'){
						#$this->log('debug', 'createSequenceSet alles');
						$seqAll = true;
					}
					else{
						#$this->log('debug', 'createSequenceSet nicht alles');
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
			#$this->log('debug', 'sequence len: '.$seqLen.' ('.$seqMin.'/'.$seqMax.') '.(int)$seqAll);
			#fwrite(STDOUT, 'createSequenceSet len: '.$seqLen.' ('.$seqMin.'/'.$seqMax.') '.(int)$seqAll."\n");
			
			if($isUid){
				if($seqLen >= 1){
					#$this->log('debug', 'createSequenceSet seq: U "'.$seqMin.'" - "'.$seqMax.'"');
					#fwrite(STDOUT, 'createSequenceSet seq: U "'.$seqMin.'" - "'.$seqMax.'"'."\n");
					for($msgSeqNum = 1; $msgSeqNum <= $count; $msgSeqNum++){
						$uid = $this->getServer()->getMsgIdBySeq($msgSeqNum, $this->selectedFolder);
						
						#$tmp = 'createSequenceSet msg: '.$msgSeqNum.', '.$uid.' ['.$seqMin.'/'.$seqMax.'] => ';
						#$tmp .= (int)($uid >= $seqMin).' '.(int)($uid <= $seqMax);
						#$this->log('debug', $tmp);
						#fwrite(STDOUT, $tmp."\n");
						
						if($uid >= $seqMin && $uid <= $seqMax || $seqAll){
							#$this->log('debug', "\t add");
							#fwrite(STDOUT, "\t add");
							$nums[] = (int)$msgSeqNum;
						}
						
						if(count($nums) >= $seqLen && !$seqAll){
							break;
						}
					}
				}
				else{
					throw new RuntimeException('Invalid minimum sequence length: "'.$seqLen.'" ('.$seqMin.'/'.$seqMax.')', 2);
				}
			}
			else{
				if($seqLen == 1){
					#$uid = $this->getServer()->getMsgIdBySeq($seqMin, $this->selectedFolder);
					#$this->log('debug', 'createSequenceSet msg: '.$seqMin);
					#fwrite(STDOUT, 'createSequenceSet msg: '.$seqMin.''."\n");
					#$nums[] =(int)$uid;
					$nums[] = (int)$seqMin;
				}
				elseif($seqLen >= 2){
					#$this->log('debug', 'createSequenceSet seq: N "'.$seqMin.'" - "'.$seqMax.'"');
					#fwrite(STDOUT, 'createSequenceSet seq: N "'.$seqMin.'" - "'.$seqMax.'"'."\n");
					for($msgSeqNum = 1; $msgSeqNum <= $count; $msgSeqNum++){
						#$tmp = 'createSequenceSet msg: '.$msgSeqNum.' ['.$seqMin.'/'.$seqMax.'] => ';
						#$tmp .= (int)($msgSeqNum >= $seqMin).' '.(int)($msgSeqNum <= $seqMax);
						#$this->log('debug', $tmp);
						#fwrite(STDOUT, $tmp."\n");
						
						if($msgSeqNum >= $seqMin && $msgSeqNum <= $seqMax){
							#$this->log('debug', "\t add");
							#fwrite(STDOUT, "\t add");
							$nums[] = (int)$msgSeqNum;
						}
						
						if(count($nums) >= $seqLen){
							break;
						}
					}
				}
				else{
					throw new RuntimeException('Invalid minimum sequence length: "'.$seqLen.'" ('.$seqMin.'/'.$seqMax.')', 1);
				}
			}
			
			#ve($nums);
			$msgSeqNums = array_merge($msgSeqNums, $nums);
		}
		
		sort($msgSeqNums, SORT_NUMERIC);
		
		return $msgSeqNums;
	}
	
	public function msgHandle($msgRaw){
		$this->log('debug', 'client '.$this->id.' raw: /'.$msgRaw.'/');
		
		$rv = '';
		
		$args = $this->msgParseString($msgRaw, 3);
		#ve($args);
		
		#$tag = $args['tag'];
		$tag = array_shift($args);
		#$command = $args['command'];
		$command = array_shift($args);
		$commandcmp = strtolower($command);
		#$args = $args['args'];
		$args = array_shift($args);
		
		
		
		
		#$this->log('debug', 'client '.$this->id.': >'.$tag.'< >'.$command.'< >"'.join('" "', $args).'"<');
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
			#ve($args);
			
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
			#ve($args);
			
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
			#ve($args);
			
			#$this->log('debug', 'client '.$this->id.' select: "'.$args[0].'"');
			#fwrite(STDOUT, 'client '.$this->id.' select: "'.$args[0].'"'."\n");
			
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
			#ve($args);
			
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
			#ve($args);
			
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
			
			#ve($args);
			
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
					
					#fwrite(STDOUT, 'flags'."\n");
					#ve($flags);
					
					#fwrite(STDOUT, 'literal: /'.$literal.'/'."\n");
					
					if($literal[0] == '{' && substr($literal, -1) == '}'){
						#fwrite(STDOUT, 'literal ok'."\n");
						$literal = (int)substr(substr($literal, 1), 0, -1);
					}
					else{
						#fwrite(STDOUT, 'literal failed'."\n");
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
			#ve('uid');
			
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
	
	public function sendHello(){
		$this->sendOk('IMAP4rev1 Service Ready');
	}
	
	private function sendCapability($tag){
		$rv = '';
		$rv .= $this->dataSend('* CAPABILITY IMAP4rev1 AUTH=PLAIN');
		$rv .= $this->sendOk('CAPABILITY completed', $tag);
		return $rv;
	}
	
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
		if($this->getStatus('authStep') == 1){
			return $this->dataSend('+');
		}
		elseif($this->getStatus('authStep') == 2){
			$this->setStatus('hasAuth', true);
			$this->setStatus('authStep', 0);
			
			return $this->sendOk($this->getStatus('authMechanism').' authentication successful', $this->getStatus('authTag'));
		}
	}
	
	private function sendLogin($tag){
		return $this->sendOk('LOGIN completed', $tag);
	}
	
	private function sendSelectedFolderInfos(){
		$rv = '';
		$nextId = $this->getServer()->getNextMsgId();
		$count = $this->getServer()->getCountMailsByFolder($this->selectedFolder);
		
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
		$rv .= $this->dataSend('* '.$this->getServer()->getCountMailsByFolder($this->selectedFolder, array(Storage::FLAG_RECENT)).' RECENT');
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
		
		#fwrite(STDOUT, 'folder: '.$folder."\n");
		
		if($this->select($folder)){
			#fwrite(STDOUT, 'folder: ok'."\n");
			$rv = $this->sendSelectedFolderInfos();
			$rv .= $this->sendOk('SELECT completed', $tag, 'READ-WRITE');
			return $rv;
		}
		
		#fwrite(STDOUT, 'folder: failed'."\n");
		return $this->sendNo('"'.$folder.'" no such mailbox', $tag);
	}
	
	private function sendCreate($tag, $folder){
		#fwrite(STDOUT, __FUNCTION__.': '.$folder."\n");
		
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
			# NOT_IMPLEMENTED
			
			#fwrite(STDOUT, 'subsc: '.$folder."\n");
			
			#$folders = $this->getServer()->getFolders($folder);
			#\Doctrine\Common\Util\Debug::dump($folders);
			$this->subscriptions[] = $folder;
			
			return $this->sendOk('SUBSCRIBE completed', $tag);
		}
		
		return $this->sendNo('SUBSCRIBE failure: no subfolder named test_dir', $tag);
	}
	
	private function sendUnsubscribe($tag, $folder){
		if($this->getServer()->folderExists($folder)){
			# NOT_IMPLEMENTED
			
			#$folders = $this->getServer()->getFolders($folder);
			#\Doctrine\Common\Util\Debug::dump($folders);
			#unset($this->subscriptions[$folder]);
			
			return $this->sendOk('UNSUBSCRIBE completed', $tag);
		}
		
		return $this->sendNo('UNSUBSCRIBE failure: no subfolder named test_dir', $tag);
	}
	
	private function sendList($tag, $baseFolder, $folder){
		$this->log('debug', 'client '.$this->id.' list: /'.$baseFolder.'/ /'.$folder.'/');
		
		$folder = str_replace('%', '*', $folder); # NOT_IMPLEMENTED
		
		$folders = $this->getServer()->getFolders($baseFolder, $folder, true);
		$rv = '';
		if(count($folders)){
			foreach($folders as $cfolder){
				$rv .= $this->dataSend('* LIST () "." "'.$cfolder.'"');
			}
		}
		else{
			if($this->getServer()->folderExists($folder)){
				#fwrite(STDOUT, 'folder: ok'."\n");
				$rv .= $this->dataSend('* LIST () "." "'.$folder.'"');
			}
			#else{
			#	fwrite(STDOUT, 'folder failed: '.$folder."\n");
			#}
		}
		
		$rv .= $this->sendOk('LIST completed', $tag);
		
		return $rv;
	}
	
	private function sendLsub($tag){
		#$this->log('debug', 'client '.$this->id.' sendLsub');
		#ve($this->subscriptions);
		
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
					return $this->sendNo('Can not get folder: '.$e->getMessage(), $this->getStatus('appendTag'), 'TRYCREATE');
				}
			}
			else{
				$this->log('debug', 'client '.$this->id.' append left: '.($this->getStatus('appendLiteral') - $appendMsgLen).' ('.$appendMsgLen.')');
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
		
		#$storage = $this->getServer()->getStorageMailbox(); # TODO
		
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
		#$this->select();
		#$this->log('debug', 'client '.$this->id.' current folder: '.$this->selectedFolder);
		
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
		
		#fwrite(STDOUT, str_repeat("\t", $level)."".'parseSearchKeys: '.$len.', '.$posOffset."\n");
		#ve($list);
		
		if($len <= 1){
			return $list;
		}
		
		$itemsC = 0;
		for($pos = 0; $pos < $len; $pos++){
			$orgpos = $pos;
			$item = $list[$pos];
			$itemWithArgs = '';
			
			$and = true;
			$offset = 0;
			
			if(is_array($item)){
				#fwrite(STDOUT, str_repeat("\t", $level)."\t".'pos: '.$pos.' array'."\n");
				$subPosOffset = 0;
				$itemWithArgs = array($this->$func($item, $subPosOffset, 0, true, $level + 1));
				#$offset += $subPosOffset;
				#fwrite(STDOUT, str_repeat("\t", $level)."\t".'-> subitem counter offset: '.$subPosOffset."\n");
			}
			else{
				#fwrite(STDOUT, str_repeat("\t", $level)."\t".'pos: '.$pos.' /'.$item.'/'."\n");
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
					#ve($sublist);
					$itemWithArgs = array(array($sublist[0], 'OR', $sublist[1]));
					
					$offset += $subPosOffset;
					
					#fwrite(STDOUT, str_repeat("\t", $level)."\t\t".'-> subitem counter offset: '.$subPosOffset."\n");
					#fwrite(STDOUT, str_repeat("\t", $level)."\t\t".'-> subitem1 array: '.(int)is_array($sublist[0])."\n");
					#fwrite(STDOUT, str_repeat("\t", $level)."\t\t".'-> subitem2 array: '.(int)is_array($sublist[1])."\n");
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
					
					#fwrite(STDOUT, str_repeat("\t", $level)."\t\t".'-> subitem counter offset: '.$subPosOffset."\n");
				}
				elseif(is_numeric($itemcmp)){
					$itemWithArgs = $item;
				}
			}
			
			if($pos <= 0){
				$and = false;
			}
			
			#fwrite(STDOUT, str_repeat("\t", $level)."\t".'-> end: '.$pos.' (+'.$offset.') '.$itemsC."\n");
			
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
		#\Doctrine\Common\Util\Debug::dump($flags);
		
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
				#$this->log('debug', 'bcc: '.$bcc);
				if(count($bccAddressList)){
					#\Doctrine\Common\Util\Debug::dump($bccAddressList->get());
					foreach($bccAddressList as $bcc){
						#fwrite(STDOUT, 'bcc: '.$bcc->getEmail()."\n");
						$rv = strpos(strtolower($bcc->getEmail()), $searchStr) !== false;
						break;
					}
				}
				break;
			case 'before':
				# NOT_IMPLEMENTED
				break;
			case 'body':
				$searchStr = strtolower($items[1]);
				$rv = strpos(strtolower($message->getBody()), $searchStr) !== false;
				break;
			case 'cc':
				$searchStr = strtolower($items[1]);
				$rv = strpos(strtolower($message->cc), $searchStr) !== false;
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
				#fwrite(STDOUT, 'get header: '.$fieldName.' /'.$searchStr.'/ -> '.$val."\n");
				$rv = strpos(strtolower($val), $searchStr) !== false;
				break;
			case 'keyword':
				# NOT_IMPLEMENTED
				break;
			case 'larger':
				#fwrite(STDOUT, 'larger: /'.$items[1].'/'."\n");
				#\Doctrine\Common\Util\Debug::dump( (string) $message->getHeaders()->toString());
				#\Doctrine\Common\Util\Debug::dump( (string) $message->toString());
				#$rv = strlen($message->toString()) > (int)$items[1];
				$rv = strlen($message->getBody()) > (int)$items[1];
				break;
			case 'new':
				#fwrite(STDOUT, 'new'."\n");
				#\Doctrine\Common\Util\Debug::dump($flags);
				$rv = in_array(Storage::FLAG_RECENT, $flags) && !in_array(Storage::FLAG_SEEN, $flags);
				break;
			case 'old':
				$rv = !in_array(Storage::FLAG_RECENT, $flags);
				break;
			case 'on':
				# NOT_IMPLEMENTED
				break;
			case 'recent':
				#fwrite(STDOUT, 'recent'."\n");
				#\Doctrine\Common\Util\Debug::dump($flags);
				$rv = in_array(Storage::FLAG_RECENT, $flags);
				break;
			case 'seen':
				#fwrite(STDOUT, 'seen'."\n");
				#\Doctrine\Common\Util\Debug::dump($flags);
				$rv = in_array(Storage::FLAG_SEEN, $flags);
				break;
			case 'sentbefore':
				$checkDate = new DateTime($items[1]);
				#$messageDate = new DateTime($message->date);
				$messageDate = new DateTime($message->getHeaders()->get('Date')->getFieldValue());
				#$messageDate = new DateTime($messageDate->format('Y-m-d'));
				$rv = $messageDate < $checkDate;
				break;
			case 'senton':
				$checkDate = new DateTime($items[1]);
				#$messageDate = new DateTime($message->date);
				$messageDate = new DateTime($message->getHeaders()->get('Date')->getFieldValue());
				#$messageDate = new DateTime($messageDate->format('Y-m-d'));
				$rv = $messageDate == $checkDate;
				break;
			case 'sentsince':
				$checkDate = new DateTime($items[1]);
				#$messageDate = new DateTime($message->date);
				$messageDate = new DateTime($message->getHeaders()->get('Date')->getFieldValue());
				#$messageDate = new DateTime($messageDate->format('Y-m-d'));
				$rv = $messageDate >= $checkDate;
				break;
			case 'since':
				# NOT_IMPLEMENTED
				break;
			case 'smaller':
				#$rv = $message->getSize() < (int)$items[1];
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
				#$text = $message->getHeaders()->toString().Headers::EOL.$message->getContent();
				#$text = $message->toString();
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
				# NOT_IMPLEMENTED
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
	
	public function parseSearchMessage($message, $messageSeqNum, $messageUid, $isUid, $gate){
		$func = __FUNCTION__;
		#fwrite(STDOUT, $func.': '.get_class($message).', '.get_class($gate).''."\n");
		
		#ve($message);
		#ve($gate);
		
		
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
			#fwrite(STDOUT, $func.': other '.get_class($gate).''."\n");
			#fwrite(STDOUT, $func.': other '.$gate->getValue().', '.get_class($gate).''."\n");
			$val = $this->searchMessageCondition($message, $messageSeqNum, $messageUid, $gate->getValue());
			$gate->setValue($val);
			#return $gate->bool();
		}
		
		foreach($subgates as $subgate){
			if($subgate instanceof AndGate){
				#fwrite(STDOUT, 'subgate: AndGate'."\n");
				$this->$func($message, $messageSeqNum, $messageUid, $isUid, $subgate);
			}
			elseif($subgate instanceof OrGate){
				#fwrite(STDOUT, 'subgate: OrGate'."\n");
				$this->$func($message, $messageSeqNum, $messageUid, $isUid, $subgate);
			}
			elseif($subgate instanceof NotGate){
				#fwrite(STDOUT, 'subgate: NotGate'."\n");
				$this->$func($message, $messageSeqNum, $messageUid, $isUid, $subgate);
			}
			elseif($subgate instanceof Obj){
				#fwrite(STDOUT, 'subgate: Obj: '.$subgate->getValue()."\n");
				$val = $this->searchMessageCondition($message, $messageSeqNum, $messageUid, $subgate->getValue());
				$subgate->setValue($val);
				#$subgate->setValue($val.'xyz');
				#fwrite(STDOUT, $func.' subgate: Obj: '.$val."\n");
			}
		}
		
		#ve($gate);
		
		return $gate->bool();
	}
	
	private function sendSearchRaw($criteriaStr, $isUid = false){
		#fwrite(STDOUT, 'sendSearchRaw: "'.$criteriaStr.'"'."\n");
		
		$criteria = array();
		$criteria = $this->msgGetParenthesizedlist($criteriaStr);
		$criteria = $this->parseSearchKeys($criteria);
		
		$tree = new CriteriaTree($criteria);
		$tree->build();
		
		if(!$tree->getRootGate()){
			return '';
		}
		
		#$storage = $this->getServer()->getStorageMailbox(); # TODO
		#fwrite(STDOUT, 'class: "'.get_class($storage['object']).'"'."\n");
		
		$ids = array();
		$msgSeqNums = $this->createSequenceSet('*');
		foreach($msgSeqNums as $msgSeqNum){
			$uid = $this->getServer()->getMsgIdBySeq($msgSeqNum, $this->selectedFolder);
			$this->log('debug', 'client '.$this->id.' check msg: '.$msgSeqNum.', '.$uid);
			
			$message = null;
			#$message = $storage['object']->getMessage($msgSeqNum);
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
					$ids[] = $msgSeqNum;
				}
			}
		}
		
		sort($ids);
		
		$rv = '';
		while($ids){
			$this->log('debug', 'client '.$this->id.' msg: '.$msgSeqNum);
			
			$sendIds = array_slice($ids, 0, 30);
			$ids = array_slice($ids, 30);
			
			$rv .= $this->dataSend('* SEARCH '.join(' ', $sendIds).'');
		}
		return $rv;
	}
	
	private function sendSearch($tag, $criteriaStr){
		#$this->select();
		$this->log('debug', 'client '.$this->id.' current folder: '.$this->selectedFolder);
		
		$rv = '';
		$rv .= $this->sendSearchRaw($criteriaStr, false);
		$rv .= $this->sendOk('SEARCH completed', $tag);
		return $rv;
	}
	
	private function sendFetchRaw($tag, $seq, $name, $isUid = false){
		#ve('fetchRaw');
		$rv = '';
		
		$msgItems = array();
		if($isUid){
			$msgItems['uid'] = '';
		}
		if(isset($name)){
			$wanted = $this->msgGetParenthesizedlist($name);
			#ve($wanted);
			foreach($wanted as $n => $item){
				
				if(is_string($item)){
					$itemcmp = strtolower($item);
					
					#$this->log('debug', 'client '.$this->id.': "'.$item.'"');
					
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
		
		$msgSeqNums = array();
		try{
			$msgSeqNums = $this->createSequenceSet($seq, $isUid);
		}
		catch(Exception $e){
			$this->sendBad($e->getMessage(), $tag);
		}
		
		#$storage = $this->getServer()->getStorageMailbox();
		
		// Process collected msgs.
		foreach($msgSeqNums as $msgSeqNum){
			#$message = $storage['object']->getMessage($msgSeqNum);
			#$message = $this->getMailBySeq($msgSeqNum);
			#$flags = $message->getFlags();
			#$flags = $this->getServer()->getFlagsBySeq($msgSeqNum);
			
			$msgId = $this->getServer()->getMsgIdBySeq($msgSeqNum, $this->selectedFolder);
			if(!$msgId){
				$this->log('error', 'Can not get ID for seq num '.$msgSeqNum.' from root storage.');
				continue;
			}
			
			$message = $this->getServer()->getMailById($msgId);
			$flags = $this->getServer()->getFlagsById($msgId);
			
			#$this->log('debug', 'sendFetchRaw msg: '.$msgSeqNum.' '.sprintf('%10s', $msgId));
			
			$output = array();
			$outputHasFlag = false;
			$outputBody = '';
			foreach($msgItems as $item => $val){
				#$this->log('debug', 'client '.$this->id.' msg item: "'.$item.'"');
				
				if($item == 'flags'){
					$outputHasFlag = true;
				}
				elseif($item == 'body' || $item == 'body.peek'){
					$peek = $item == 'body.peek';
					$section = '';
					
					#$msgStr = $message->getHeaders()->toString().Headers::EOL.$message->getContent();
					$msgStr = $message->toString();
					if(isset($val['header'])){
						#$this->log('debug', 'client '.$this->id.' fetch header');
						$section = 'HEADER';
						$msgStr = $message->getHeaders()->toString();
					}
					elseif(isset($val['header.fields'])){
						#$this->log('debug', 'client '.$this->id.' fetch header.fields');
						$section = 'HEADER';
						$msgStr = '';
						
						$headerStrs = array();
						foreach($val['header.fields'] as $fieldNum => $field){
							try{
								$header = $message->getHeader($field);
								$msgStr .= $header->toString().Headers::EOL;
							}
							catch(InvalidArgumentException $e){
							}
						}
					}
					
					$msgStr .= Headers::EOL;
					$msgStrLen = strlen($msgStr);
					#$output[] = 'BODY['.$section.'] {'.$msgStrLen.'}'.Headers::EOL.$msgStr.Headers::EOL;
					$outputBody = 'BODY['.$section.'] {'.$msgStrLen.'}'.Headers::EOL.$msgStr;
				}
				elseif($item == 'rfc822.size'){
					#$size = $message->getSize();
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
			#$storage['object']->setFlags($msgSeqNum, $flags);
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
		#$flags = array_combine($flags, $flags);
		$flags = array_unique($flags);
		#ve($flags);
		
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
		
		$msgSeqNums = array();
		try{
			$msgSeqNums = $this->createSequenceSet($seq, $isUid);
		}
		catch(Exception $e){
			$this->sendBad($e->getMessage(), $tag);
		}
		
		#$storage = $this->getServer()->getStorageMailbox(); # TODO
		
		// Process collected msgs.
		foreach($msgSeqNums as $msgSeqNum){
			#$this->log('debug', 'client '.$this->id.' msg: '.$msgSeqNum);
			
			#$message = $storage['object']->getMessage($msgSeqNum);
			#$messageFlags = $message->getFlags();
			$messageFlags = $this->getServer()->getFlagsBySeq($msgSeqNum, $this->selectedFolder);
			#\Doctrine\Common\Util\Debug::dump($messageFlags);
			#if(($key = array_search(Storage::FLAG_RECENT, $messageFlags)) !== false){
			#	unset($messageFlags[$key]);
			#}
			
			if(!$add && !$rem){
				$messageFlags = $flags;
				#$this->log('debug', 'client '.$this->id.'     set flags');
			}
			elseif($add){
				#$this->log('debug', 'client '.$this->id.'     add flags');
				#ve($messageFlags);
				$messageFlags = array_merge($messageFlags, $flags);
				#ve($messageFlags);
			}
			elseif($rem){
				#$this->log('debug', 'client '.$this->id.'     rem flags');
				foreach($flags as $flag){
					unset($messageFlags[$flag]);
					#$this->log('debug', 'client '.$this->id.'     unset flag: '.$flag);
				}
			}
			
			#\Doctrine\Common\Util\Debug::dump($messageFlags);
			#ve($messageFlags);
			#$storage['object']->setFlags($msgSeqNum, $messageFlags);
			$this->getServer()->setFlagsBySeq($msgSeqNum, $this->selectedFolder, $messageFlags);
			$messageFlags = $this->getServer()->getFlagsBySeq($msgSeqNum, $this->selectedFolder);
			
			if(!$silent){
				$rv .= $this->dataSend('* '.$msgSeqNum.' FETCH (FLAGS ('.join(' ', $messageFlags).'))');
			}
		}
		
		return $rv;
	}
	
	private function sendStore($tag, $seq, $name, $flagsStr){
		#$this->select();
		$this->log('debug', 'client '.$this->id.' current folder: '.$this->selectedFolder);
		
		$this->sendStoreRaw($tag, $seq, $name, $flagsStr, false);
		$this->sendOk('STORE completed', $tag);
	}
	
	private function sendCopy($tag, $seq, $folder, $isUid = false){
		$msgSeqNums = array();
		try{
			$msgSeqNums = $this->createSequenceSet($seq, $isUid);
		}
		catch(Exception $e){
			return $this->sendBad($e->getMessage(), $tag);
		}
		
		#\Doctrine\Common\Util\Debug::dump($msgSeqNums);
		if($this->getServer()->getCountMailsByFolder($this->selectedFolder) == 0){
			return $this->sendBad('No messages in selected mailbox.', $tag);
		}
		
		if(!$this->getServer()->folderExists($folder)){
			return $this->sendNo('Can not get folder: no subfolder named '.$folder, $tag, 'TRYCREATE');
		}
		
		foreach($msgSeqNums as $msgSeqNum){
			#fwrite(STDOUT, 'msgSeqNum: '.$msgSeqNum."\n");
			$this->getServer()->copyMailBySequenceNum($msgSeqNum, $this->selectedFolder, $folder);
		}
		
		return $this->sendOk('COPY completed', $tag);
	}
	
	private function sendUid($tag, $args){
		$this->log('debug', 'client '.$this->id.' sendUid: "'.$args.'"');
		#ve($args);
		
		$args = $this->msgParseString($args, 2);
		#ve($args);
		
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
			#$this->select();
			#$this->log('debug', 'client '.$this->id.' current folder: '.$this->selectedFolder);
			
			$args = $this->msgParseString($args, 2);
			$seq = $args[0];
			$name = $args[1];
			
			$rv .= $this->sendFetchRaw($tag, $seq, $name, true);
			$rv .= $this->sendOk('UID FETCH completed', $tag);
		}
		elseif($commandcmp == 'store'){
			#$this->select();
			#$this->log('debug', 'client '.$this->id.' current folder: '.$this->selectedFolder);
			
			$args = $this->msgParseString($args, 3);
			$seq = $args[0];
			$name = $args[1];
			$flagsStr = $args[2];
			
			$rv .= $this->sendStoreRaw($tag, $seq, $name, $flagsStr, true);
			$rv .= $this->sendOk('UID STORE completed', $tag);
		}
		elseif($commandcmp == 'search'){
			#$this->select();
			$this->log('debug', 'client '.$this->id.' current folder: '.$this->selectedFolder);
			
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
		#fwrite(STDOUT, 'select: '.$folder."\n");
		
		if($this->getServer()->folderExists($folder)){
			#fwrite(STDOUT, 'select: ok'."\n");
			
			$this->log('debug', 'client '.$this->id.' old folder: "'.$this->selectedFolder.'"');
			$this->selectedFolder = $folder;
			$this->log('debug', 'client '.$this->id.' new folder: "'.$this->selectedFolder.'"');
			
			return true;
		}
		
		#fwrite(STDOUT, 'select: failed'."\n");
		$this->selectedFolder = null;
		return false;
	}
	
	public function getSelectedFolder(){
		return $this->selectedFolder;
	}
	
}
