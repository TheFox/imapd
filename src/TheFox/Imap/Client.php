<?php

namespace TheFox\Imap;

use Exception;
use RuntimeException;
use InvalidArgumentException;

use Zend\Mail\Storage;
use Zend\Mail\Headers;
use Zend\Mail\Message;

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
	private $expunge = array(); # TODO
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
		$args = preg_split('/ /', $msgRaw);
		
		$argsr = array();
		$argsrc = -1;
		$isStr = false;
		foreach($args as $n => $arg){
			#fwrite(STDOUT, "arg $n ".(int)$isStr." '$arg'\n");
			
			$isStrBegin = false;
			$isStrEnd = false;
			if($arg){
				#fwrite(STDOUT, "    is arg\n");
				if($isStr){
					#fwrite(STDOUT, "    is str A\n");
					if($arg[0] == '"'){
						#fwrite(STDOUT, "    first char is \"\n");
						$isStr = false;
						$isStrEnd = true;
					}
					if(strlen($arg) > 1 && substr($arg, -1) == '"'){
						#fwrite(STDOUT, "    last char is \"\n");
						$isStr = false;
						$isStrEnd = true;
					}
				}
				else{
					#fwrite(STDOUT, "    no str A\n");
					if($arg[0] == '"'){
						#fwrite(STDOUT, "    first char is \"\n");
						$isStr = true;
						$isStrBegin = true;
					}
					if(strlen($arg) > 1 && substr($arg, -1) == '"'){
						#fwrite(STDOUT, "    last char is \"\n");
						$isStr = false;
						$isStrEnd = true;
					}
				}
			}
			#else{ continue; }
			
			$new = false;
			$empty = false;
			if($isStrBegin && !$isStrEnd){
				#fwrite(STDOUT, "    str begin\n");
				$new = true;
				$arg = substr($arg, 1);
				if(!$arg){
					$empty = true;
				}
			}
			elseif(!$isStrBegin && $isStrEnd){
				#fwrite(STDOUT, "    str end\n");
				$arg = substr($arg, 0, -1);
			}
			elseif($isStrBegin && $isStrEnd){
				#fwrite(STDOUT, "    str begin & end\n");
				$new = true;
				$arg = substr(substr($arg, 1), 0, -1);
				if(!$arg){
					$empty = true;
				}
			}
			else{
				if($isStr){
					#fwrite(STDOUT, "    is str B\n");
				}
				else{
					#fwrite(STDOUT, "    no str B\n");
					$new = true;
				}
			}
			
			#fwrite(STDOUT, "    check new: ".(int)$new.", ".(int)($argsMax === null).", ".(int)count($argsr)." < ".(int)$argsMax."\n");
			
			if($new && ($argsMax === null || count($argsr) < $argsMax)){
				
				if($arg){
					#fwrite(STDOUT, "    new A ".(int)$empty." '".$arg."'\n");
					
					$argsrc++;
					$argsr[$argsrc] = array($arg);
					
				}
				else{
					if($empty){
						#fwrite(STDOUT, "    new B empty '".$arg."'\n");
						
						$argsrc++;
						$argsr[$argsrc] = array('');
						
					}
					else{
						#fwrite(STDOUT, "    new B else '".$arg."'\n");
					}
				}
			}
			else{
				#fwrite(STDOUT, "    append '".$arg."'\n");
				$argsr[$argsrc][] = $arg;
			}
		}
		
		$args = array_values($args);

		#fwrite(STDOUT, "\n");

		foreach($argsr as $n => $arg){
			$argstr = join(' ', $arg);
			#fwrite(STDOUT, "r arg $n '".$argstr."'\n");
			
			$argsr[$n] = $argstr;
			
			#foreach($arg as $j => $sarg){ fwrite(STDOUT, "    s arg $j '".$sarg."'\n"); }
		}
		$argsr = array_values($argsr);
		
		return $argsr;
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
		#fwrite(STDOUT, str_repeat(' ', $level * 4)."raw '$msgRaw'\n");
		#usleep(100000);
		
		#if($level >= 100){ exit(); } # TODO
		
		$rv = array();
		$rvc = 0;
		if($msgRaw){
			if($msgRaw[0] == '(' || $msgRaw[0] == '['){
				$msgRaw = substr($msgRaw, 1);
			}
			if(substr($msgRaw, -1) == ')' || substr($msgRaw, -1) == ']'){
				$msgRaw = substr($msgRaw, 0, -1);
			}
			
			$str = '';
			while($msgRaw){
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
					
					#fwrite(STDOUT, str_repeat(' ', $level * 4)."    c '".$msgRaw[0]."' '".$rv[$rvc]."'\n");
					$msgRaw = substr($msgRaw, 1);
				}
				
				#usleep(100000);
			}
			
			#fwrite(STDOUT, str_repeat(' ', $level * 4)."str '$str'\n");
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
	
	private function msgHandle($msgRaw){
		$this->log('debug', 'client '.$this->id.' raw: "'.$msgRaw.'"');
		
		#$args = $this->msgGetArgs($msgRaw);
		$args = $this->msgParseString($msgRaw, 3);
		
		
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
			
			$this->sendCapability($tag);
		}
		elseif($commandcmp == 'noop'){
			$this->sendNoop($tag);
		}
		elseif($commandcmp == 'logout'){
			$this->sendBye('IMAP4rev1 Server logging out');
			$this->sendLogout($tag);
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
				
				$this->sendAuthenticate();
			}
			else{
				$this->sendNo($args[0].' Unsupported authentication mechanism', $tag);
			}
		}
		elseif($commandcmp == 'login'){
			$args = $this->msgParseString($args, 2);
			#ve($args);
			
			#$this->log('debug', 'client '.$this->id.' login: "'.$args[0].'" "'.$args[1].'"');
			
			if(isset($args[0]) && $args[0] && isset($args[1]) && $args[1]){
				$this->sendLogin($tag);
			}
			else{
				$this->sendBad('Arguments invalid.', $tag);
			}
		}
		elseif($commandcmp == 'select'){
			$args = $this->msgParseString($args, 1);
			#ve($args);
			
			$this->log('debug', 'client '.$this->id.' select: "'.$args[0].'"');
			
			if($this->getStatus('hasAuth')){
				if(isset($args[0]) && $args[0]){
					$this->sendSelect($tag, $args[0]);
				}
				else{
					$this->selectedFolder = null;
					$this->sendBad('Arguments invalid.', $tag);
				}
			}
			else{
				$this->selectedFolder = null;
				$this->sendNo($commandcmp.' failure', $tag);
			}
		}
		elseif($commandcmp == 'create'){
			$args = $this->msgParseString($args, 1);
			#ve($args);
			
			$this->log('debug', 'client '.$this->id.' create: '.$args[0]);
			
			if($this->getStatus('hasAuth')){
				if(isset($args[0]) && $args[0]){
					$this->sendCreate($tag, $args[0]);
				}
				else{
					$this->sendBad('Arguments invalid.', $tag);
				}
			}
			else{
				$this->sendNo($commandcmp.' failure', $tag);
			}
		}
		elseif($commandcmp == 'subscribe'){
			$args = $this->msgParseString($args, 1);
			
			$this->log('debug', 'client '.$this->id.' subscribe: '.$args[0]);
			
			if($this->getStatus('hasAuth')){
				if(isset($args[0]) && $args[0]){
					$this->sendSubscribe($tag, $args[0]);
				}
				else{
					$this->sendBad('Arguments invalid.', $tag);
				}
			}
			else{
				$this->sendNo($commandcmp.' failure', $tag);
			}
		}
		elseif($commandcmp == 'unsubscribe'){
			$args = $this->msgParseString($args, 1);
			
			$this->log('debug', 'client '.$this->id.' unsubscribe: '.$args[0]);
			
			if($this->getStatus('hasAuth')){
				if(isset($args[0]) && $args[0]){
					$this->sendUnsubscribe($tag, $args[0]);
				}
				else{
					$this->sendBad('Arguments invalid.', $tag);
				}
			}
			else{
				$this->sendNo($commandcmp.' failure', $tag);
			}
		}
		elseif($commandcmp == 'list'){
			$args = $this->msgParseString($args, 1);
			#ve($args);
			
			#$this->log('debug', 'client '.$this->id.' list: '.$args[0]);
			
			if($this->getStatus('hasAuth')){
				if(isset($args[0]) && $args[0]){
					$this->sendList($tag, $args[0]);
				}
				else{
					$this->sendBad('Arguments invalid.', $tag);
				}
			}
			else{
				$this->sendNo($commandcmp.' failure', $tag);
			}
		}
		elseif($commandcmp == 'lsub'){
			$args = $this->msgParseString($args, 1);
			
			$this->log('debug', 'client '.$this->id.' lsub: '.$args[0]);
			
			if($this->getStatus('hasAuth')){
				if(isset($args[0]) && $args[0]){
					$this->sendLsub($tag);
				}
				else{
					$this->sendBad('Arguments invalid.', $tag);
				}
			}
			else{
				$this->sendNo($commandcmp.' failure', $tag);
			}
		}
		elseif($commandcmp == 'append'){
			$args = $this->msgParseString($args, 4);
			
			#ve($args);
			
			$this->log('debug', 'client '.$this->id.' append: "'.$args[0].'" "'.$args[1].'"');
			
			if($this->getStatus('hasAuth')){
				if(isset($args[0]) && $args[0] && isset($args[1]) && $args[1]){
					if($this->selectedFolder !== null){
						$this->setStatus('appendStep', 1);
						$this->setStatus('appendTag', $tag);
						$this->setStatus('appendFolder', $args[0]);
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
						
						$flags = array_combine($flags, $flags);
						$this->setStatus('appendFlags', $flags);
						#ve('flags');
						#ve($flags);
						
						if($literal[0] == '{' && substr($literal, -1) == '}'){
							$literal = (int)substr(substr($literal, 1), 0, -1);
						}
						$this->setStatus('appendLiteral', $literal);
						
						$this->sendAppend();
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
		elseif($commandcmp == 'check'){
			#$this->log('debug', 'client '.$this->id.' check');
			
			if($this->getStatus('hasAuth')){
				$this->sendCheck($tag);
			}
			else{
				$this->sendNo($commandcmp.' failure', $tag);
			}
		}
		elseif($commandcmp == 'close'){
			$this->log('debug', 'client '.$this->id.' close');
			
			if($this->getStatus('hasAuth')){
				if($this->selectedFolder !== null){
					$this->sendClose($tag);
				}
				else{
					$this->sendNo('No mailbox selected.', $tag);
				}
			}
			else{
				$this->sendNo($commandcmp.' failure', $tag);
			}
		}
		elseif($commandcmp == 'expunge'){
			$this->log('debug', 'client '.$this->id.' expunge');
			
			if($this->getStatus('hasAuth')){
				if($this->selectedFolder !== null){
					$this->sendExpunge($tag);
				}
				else{
					$this->sendNo('No mailbox selected.', $tag);
				}
			}
			else{
				$this->sendNo($commandcmp.' failure', $tag);
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
			
			$this->log('debug', 'client '.$this->id.' copy: "'.$args[0].'" "'.$args[1].'"');
			
			if($this->getStatus('hasAuth')){
				if(isset($args[0]) && $args[0] && isset($args[1]) && $args[1]){
					if($this->selectedFolder !== null){
						$seq = $args[0];
						$folder = $args[1];
						$this->sendCopy($tag, $seq, $folder);
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
		elseif($commandcmp == 'uid'){
			#ve('uid');
			
			if($this->getStatus('hasAuth')){
				if($this->selectedFolder !== null){
					$this->sendUid($tag, $args);
				}
				else{
					$this->sendNo('No mailbox selected.', $tag);
				}
			}
			else{
				$this->sendNo($commandcmp.' failure', $tag);
			}
		}
		else{
			if($this->getStatus('authStep') == 1){
				$this->setStatus('authStep', 2);
				$this->sendAuthenticate();
			}
			elseif($this->getStatus('appendStep') >= 1){
				
				#ve('appendStep data '.$this->getStatus('appendStep').' '.strlen($args));
				
				$this->sendAppend($msgRaw);
			}
			else{
				$this->log('debug', 'client '.$this->id.' not implemented: "'.$tag.'" "'.$command.'" >"'.$args.'"<');
				$this->sendBad('Not implemented: "'.$tag.'" "'.$command.'"', $tag);
			}
		}
	}
	
	public function dataSend($msg){
		$this->log('debug', 'client '.$this->id.' data send: "'.$msg.'"');
		$this->getSocket()->write($msg.static::MSG_SEPARATOR);
	}
	
	public function sendHello(){
		$this->sendOk('IMAP4rev1 Service Ready');
	}
	
	private function sendCapability($tag){
		$this->dataSend('* CAPABILITY IMAP4rev1 AUTH=PLAIN');
		$this->sendOk('CAPABILITY completed', $tag);
	}
	
	private function sendNoop($tag){
		$this->select();
		if($this->selectedFolder !== null){
			$this->sendSelectedFolderInfos();
		}
		$this->sendOk('NOOP completed client '.$this->getId().', '.$this->selectedFolder, $tag);
	}
	
	private function sendLogout($tag){
		$this->sendOk('LOGOUT completed', $tag);
	}
	
	private function sendAuthenticate(){
		if($this->getStatus('authStep') == 1){
			$this->dataSend('+');
		}
		elseif($this->getStatus('authStep') == 2){
			$this->setStatus('hasAuth', true);
			$this->setStatus('authStep', 0);
			$this->sendOk($this->getStatus('authMechanism').' authentication successful', $this->getStatus('authTag'));
		}
	}
	
	private function sendLogin($tag){
		$this->sendOk('LOGIN completed', $tag);
	}
	
	private function sendSelectedFolderInfos(){
		$nextId = $this->getServer()->getRootStorageDbNextId();
		$count = $this->getServer()->getRootStorage()->countMessages();
		
		$firstUnseen = 0;
		for($msgSeqNum = 1; $msgSeqNum <= $count; $msgSeqNum++){
			#$this->log('debug', 'client '.$this->id.' msg: '.$msgSeqNum);
			
			try{
				$message = $this->getServer()->getRootStorage()->getMessage($msgSeqNum);
				if(!$message->hasFlag(Storage::FLAG_SEEN) && !$firstUnseen){
					$firstUnseen = $msgSeqNum;
					break;
				}
			}
			catch(Exception $e){
				$this->log('error', $e->getMessage());
			}
		}
		
		foreach($this->expunge as $msgSeqNum){
			$this->dataSend('* '.$msgSeqNum.' EXPUNGE');
		}
		
		$this->dataSend('* '.$count.' EXISTS');
		$this->dataSend('* '.$this->getServer()->getRootStorage()->countMessages(Storage::FLAG_RECENT).' RECENT');
		$this->sendOk('Message '.$firstUnseen.' is first unseen', null, 'UNSEEN '.$firstUnseen);
		#$this->dataSend('* OK [UIDVALIDITY 3857529045] UIDs valid');
		if($nextId){
			#$this->dataSend('* OK [UIDNEXT '.$nextId.'] Predicted next UID');
			$this->sendOk('Predicted next UID', null, 'UIDNEXT '.$nextId);
		}
		$this->dataSend('* FLAGS ('.Storage::FLAG_ANSWERED.' '.Storage::FLAG_FLAGGED.' '.Storage::FLAG_DELETED.' '.Storage::FLAG_SEEN.' '.Storage::FLAG_DRAFT.')');
		#$this->dataSend('* OK [PERMANENTFLAGS ('.Storage::FLAG_DELETED.' '.Storage::FLAG_SEEN.' \*)] Limited');
		$this->sendOk('Limited', null, 'PERMANENTFLAGS ('.Storage::FLAG_DELETED.' '.Storage::FLAG_SEEN.' \*)');
	}
	
	private function sendSelect($tag, $folder){
		if(strtolower($folder) == 'inbox' && $folder != 'INBOX'){
			// Set folder to INBOX if folder is not INBOX
			// e.g. Inbox, INbOx or something like this.
			$folder = 'INBOX';
		}
		try{
			$this->select($folder);
		}
		catch(Exception $e){
			$this->selectedFolder = null;
			$this->sendNo('"'.$folder.'" no such mailbox', $tag);
			return;
		}
		
		$this->sendSelectedFolderInfos();
		
		$this->sendOk('SELECT completed', $tag, 'READ-WRITE');
	}
	
	private function sendCreate($tag, $folder){
		try{
			$this->getServer()->getRootStorage()->createFolder($folder);
			$this->sendOk('CREATE completed', $tag);
		}
		catch(Exception $e){
			$this->sendNo('CREATE failure: '.$e->getMessage(), $tag);
		}
	}
	
	private function sendSubscribe($tag, $folder){
		try{
			$folder = $this->getServer()->getRootStorage()->getFolders($folder);
			$this->sendOk('SUBSCRIBE completed', $tag);
			
			#ve($folder);
			
			$this->subscriptions[] = $folder->getGlobalName();
			$this->subscriptions = array_unique($this->subscriptions);
			ve($this->subscriptions);
			
		}
		catch(Exception $e){
			$this->sendNo('SUBSCRIBE failure: '.$e->getMessage(), $tag);
		}
	}
	
	private function sendUnsubscribe($tag, $folder){
		try{
			$folder = $this->getServer()->getRootStorage()->getFolders($folder);
			$this->sendOk('UNSUBSCRIBE completed', $tag);
			
			#ve($folder);
			
			unset($this->subscriptions[$folder->getGlobalName()]);
			ve($this->subscriptions);
			
		}
		catch(Exception $e){
			$this->sendNo('UNSUBSCRIBE failure: '.$e->getMessage(), $tag);
		}
	}
	
	private function sendList($tag, $folder){
		$this->log('debug', 'client '.$this->id.' list: "'.$folder.'"');
		
		$folder = str_replace('%', '*', $folder); # TODO
		
		$folders = array();
		if(strpos($folder, '*') === false){
			$this->log('debug', 'client '.$this->id.' list found no *');
			$folders = $this->getServer()->getRootStorage()->getFolders($folder);
		}
		else{
			$this->log('debug', 'client '.$this->id.' list found a *');
			$items = preg_split('/\*/', $folder, 2);
			#ve($items);
			
			$search = '';
			if(count($items) <= 1){
				$search = null;
			}
			else{
				$search = $items[0];
			}
			
			#$this->log('debug', 'client '.$this->id.' list search: "'.$search.'"');
			$folders = $this->getServer()->getRootStorageFolders($search, true);
		}
		
		#ve($folders);
		
		foreach($folders as $cfolder){
			#$this->log('debug', 'client '.$this->id.'    folder '.$cfolder->getGlobalName());
			
			#if(fnmatch($folder, $cfolder->getGlobalName(), FNM_CASEFOLD)){
				$attrs = array();
				if(strtolower($cfolder->getGlobalName()) == 'inbox'){
					#$attrs[] = '\\Noinferiors';
				}
				
				$this->dataSend('* LIST ('.join(' ', $attrs).') "." "'.$cfolder->getGlobalName().'"');
			#}
		}
		$this->sendOk('LIST completed', $tag);
	}
	
	private function sendLsub($tag){
		foreach($this->subscriptions as $subscription){
			$this->dataSend('* LSUB () "." "'.$subscription.'"');
		}
		
		$this->sendOk('LSUB completed', $tag);
	}
	
	private function sendAppend($data = ''){
		#ve('sendAppend');
		#ve($data);
		
		#$this->log('debug', 'client '.$this->id.' append: '.$this->getStatus('appendStep').', '.$this->getStatus('appendTag').', '.$this->getStatus('appendFolder').', '.count($this->getStatus('appendFlags')).', '.$this->getStatus('appendDate').', '.$this->getStatus('appendLiteral').' '.strlen($this->getStatus('appendMsg')).' "'.$data.'"');
		
		if($this->getStatus('appendStep') == 1){
			$this->status['appendStep']++;
			
			$this->dataSend('+ Ready for literal data');
		}
		elseif($this->getStatus('appendStep') == 2){
			if(strlen($this->getStatus('appendMsg')) < $this->getStatus('appendLiteral')){
				$this->status['appendMsg'] .= $data.Headers::EOL;
			}
			
			if(strlen($this->getStatus('appendMsg')) >= $this->getStatus('appendLiteral')){
				$this->status['appendStep']++;
				
				$message = Message::fromString($this->getStatus('appendMsg'));
				
				#ve($message);
				#ve($message->toString());
				
				try{
					$this->getServer()->mailAdd($message->toString(), $this->getStatus('appendFolder'), $this->getStatus('appendFlags'));
					$this->sendOk('APPEND completed', $this->getStatus('appendTag'));
				}
				catch(Exception $e){
					$this->sendNo('Can not get folder: '.$e->getMessage(), $this->getStatus('appendTag'), 'TRYCREATE');
				}
				
				
			}
		}
	}
	
	private function sendCheck($tag){
		if($this->selectedFolder !== null){
			$this->sendOk('CHECK completed', $tag);
		}
		else{
			$this->sendNo('No mailbox selected.', $tag);
		}
	}
	
	private function sendClose($tag){
		$this->select();
		$this->log('debug', 'client '.$this->id.' current folder: '.$this->selectedFolder);
		
		$this->sendExpungeRaw();
		
		$this->selectedFolder = null;
		$this->sendOk('CLOSE completed', $tag);
	}
	
	private function sendExpungeRaw(){
		$msgSeqNumsExpunge = array();
		$expungeDiff = 0;
		
		$msgSeqNums = $this->createSequenceSet('*');
		foreach($msgSeqNums as $msgSeqNum){
			$expungeSeqNum = $msgSeqNum - $expungeDiff;
			$this->log('debug', 'client '.$this->id.' check msg: '.$msgSeqNum.', '.$expungeDiff.', '.$expungeSeqNum);
			
			$message = null;
			try{
				$message = $this->getServer()->getRootStorage()->getMessage($expungeSeqNum);
			}
			catch(Exception $e){
				$this->log('error', 'client '.$this->id.' getMessage: '.$e->getMessage());
			}
			
			if($message && $message->hasFlag(Storage::FLAG_DELETED)){
				$this->log('debug', 'client '.$this->id.'      del msg');
				$this->getServer()->mailRemove($expungeSeqNum);
				
				$msgSeqNumsExpunge[] = $expungeSeqNum;
				$expungeDiff++;
			}
		}
		
		return $msgSeqNumsExpunge;
	}
	
	private function sendExpunge($tag){
		$this->select();
		$this->log('debug', 'client '.$this->id.' current folder: '.$this->selectedFolder);
		
		$msgSeqNumsExpunge = $this->sendExpungeRaw();
		
		foreach($msgSeqNumsExpunge as $msgSeqNum){
			$this->dataSend('* '.$msgSeqNum.' EXPUNGE');
		}
		$this->sendOk('EXPUNGE completed', $tag);
		
		$this->expunge = array();
	}
	
	private function createSequenceSet($setStr, $isUid = false){
		// Collect messages with sequence-sets.
		$msgSeqNums = array();
		foreach(preg_split('/,/', $setStr) as $seqItem){
			$seqMin = null;
			$seqMax = null;
			
			$items = preg_split('/:/', $seqItem);
			if(isset($items[0])){
				$seqMin = $items[0];
				
				if(isset($items[1])){
					$seqMax = $items[1];
				}
				else{
					$seqMax = $items[0];
				}
			}
			if($seqMin == '*'){
				$seqMin = $seqMax;
				$seqMax = '*';
			}
			
			#$this->log('debug', 'createSequenceSet seq: '.(int)$isUid.' "'.$seqMin.'" - "'.$seqMax.'"');
			
			if($seqMin === null){
				throw new RuntimeException('Invalid minimum sequence number: "'.$seqMin.'" ('.$seqMax.')', 1);
			}
			
			$count = $this->getServer()->getRootStorage()->countMessages();
			if(!$count){
				throw new RuntimeException('No messages in selected mailbox.', 2);
			}
			
			$msgSeqAdd = false;
			$msgSeqIsEnd = false;
			for($msgSeqNum = 1; $msgSeqNum <= $count; $msgSeqNum++){
				$uid = $this->getServer()->getRootStorageDbMsgIdBySeqNum($msgSeqNum);
				
				#$this->log('debug', 'createSequenceSet msg: '.$msgSeqNum.' '.sprintf('%10s', $uid).' ['.$seqMin.'/'.$seqMax.'] => '. (int)$isUid .' '. (int)($uid == $seqMin) .' '. (int)($msgSeqNum >= $seqMin) .' '. (int)($msgSeqNum >= $seqMax) );
				
				if($seqMin == '1' && $seqMax == '*' || $seqMin == '*' && $seqMax == '*'){
					// All
					$msgSeqAdd = true;
				}
				else{
					// Part
					if($isUid){
						if($uid == $seqMin || $seqMax == '*'){
						#if($uid >= $seqMin){
							$msgSeqAdd = true;
						}
						if($uid == $seqMax){
						#if($uid >= $seqMax){
							$msgSeqIsEnd = true;
						}
					}
					else{
						if($msgSeqNum >= $seqMin){
							$msgSeqAdd = true;
						}
						if($msgSeqNum >= $seqMax){
							$msgSeqIsEnd = true;
						}
					}
				}
				
				if($msgSeqAdd){
					#$this->log('debug', 'createSequenceSet msg:       add');
					$msgSeqNums[] = $msgSeqNum;
				}
				
				if($msgSeqIsEnd){
					break;
				}
			}
		}
		$msgSeqNums = array_unique($msgSeqNums);
		sort($msgSeqNums);
		
		return $msgSeqNums;
	}
	
	private function sendFetchRaw($tag, $seq, $name, $isUid = false){
		#ve('fetchRaw');
		
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
				#$this->log('debug', 'client '.$this->id.' wanted by '.$commandcmp.': "'.$item.'"');
			}
		}
		
		$msgSeqNums = array();
		try{
			$msgSeqNums = $this->createSequenceSet($seq, $isUid);
		}
		catch(Exception $e){
			$this->sendBad($e->getMessage(), $tag);
		}
		
		// Process collected msgs.
		foreach($msgSeqNums as $msgSeqNum){
			$message = $this->getServer()->getRootStorage()->getMessage($msgSeqNum);
			$flags = $message->getFlags();
			$uid = $this->getServer()->getRootStorageDbMsgIdBySeqNum($msgSeqNum);
			if(!$uid){
				$this->log('error', 'Can not get ID for seq num '.$msgSeqNum.' from root storage.');
				continue;
			}
			
			#$this->log('debug', 'sendFetchRaw msg: '.$msgSeqNum.' '.sprintf('%10s', $uid));
			
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
					
					$msgStr = $message->getHeaders()->toString().Headers::EOL.$message->getContent();
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
					else{
						#$this->log('debug', 'client '.$this->id.' fetch all');
					}
					
					$msgStr .= Headers::EOL;
					$msgStrLen = strlen($msgStr);
					#$output[] = 'BODY['.$section.'] {'.$msgStrLen.'}'.Headers::EOL.$msgStr.Headers::EOL;
					$outputBody = 'BODY['.$section.'] {'.$msgStrLen.'}'.Headers::EOL.$msgStr;
				}
				elseif($item == 'rfc822.size'){
					#$size = $message->getSize();
					$size = strlen($message->getHeaders()->toString().Headers::EOL.$message->getContent());
					$output[] = 'RFC822.SIZE '.$size;
				}
				elseif($item == 'uid'){
					$output[] = 'UID '.$uid;
				}
			}
			
			if($outputHasFlag){
				$output[] = 'FLAGS ('.join(' ', array_values($message->getFlags())).')';
			}
			if($outputBody){
				$output[] = $outputBody;
			}
			
			$this->dataSend('* '.$msgSeqNum.' FETCH ('.join(' ', $output).')');
			
			unset($flags[Storage::FLAG_RECENT]);
			$this->getServer()->getRootStorage()->setFlags($msgSeqNum, $flags);
		}
		
	}
	
	/*private function sendFetch($tag, $seq, $name){
		$this->select();
		$this->log('debug', 'client '.$this->id.' current folder: '.$this->selectedFolder);
		
		$this->sendFetchRaw($tag, $seq, $name, false);
		$this->sendOk('FETCH completed', $tag);
	}*/
	
	private function sendStoreRaw($tag, $seq, $name, $flagsStr, $isUid = false){
		#ve('sendStoreRaw');
		
		$flags = $this->msgGetParenthesizedlist($flagsStr);
		#ve('sendStoreRaw flags');
		#ve($flags);
		unset($flags[Storage::FLAG_RECENT]);
		$flags = array_combine($flags, $flags);
		
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
		
		// Process collected msgs.
		foreach($msgSeqNums as $msgSeqNum){
			#$this->log('debug', 'client '.$this->id.' msg: '.$msgSeqNum);
			
			$message = $this->getServer()->getRootStorage()->getMessage($msgSeqNum);
			$messageFlags = $message->getFlags();
			
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
			
			$this->getServer()->getRootStorage()->setFlags($msgSeqNum, $messageFlags);
			
			if(!$silent){
				$this->dataSend('* '.$msgSeqNum.' FETCH (FLAGS ('.join(' ', $messageFlags).'))');
			}
		}
		
	}
	
	private function sendStore($tag, $seq, $name, $flagsStr){
		$this->select();
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
			$this->sendBad($e->getMessage(), $tag);
		}
		
		try{
			$this->getServer()->getRootStorage()->getFolders($folder);
		}
		catch(Exception $e){
			$this->sendNo('Can not get folder: '.$e->getMessage(), $tag, 'TRYCREATE');
			return;
		}
		
		foreach($msgSeqNums as $msgSeqNum){
			try{
				$this->getServer()->mailCopy($msgSeqNum, $folder);
			}
			catch(Exception $e){
				$this->sendNo('Can not copy message: '.$msgSeqNum, $tag);
				return;
			}
		}
		
		$this->sendOk('COPY completed', $tag);
	}
	
	private function sendUid($tag, $args){
		$args = $this->msgParseString($args, 2);
		
		#ve('sendUid A');ve($args);
		
		$command = $args[0];
		$commandcmp = strtolower($command);
		$args = $args[1];
		
		#ve('sendUid B');ve($args);
		
		if($commandcmp == 'copy'){
			$args = $this->msgParseString($args, 2);
			$seq = $args[0];
			$folder = $args[1];
			$this->sendCopy($tag, $seq, $folder, true);
		}
		elseif($commandcmp == 'fetch'){
			$this->select();
			#$this->log('debug', 'client '.$this->id.' current folder: '.$this->selectedFolder);
			
			$args = $this->msgParseString($args, 2);
			$seq = $args[0];
			$name = $args[1];
			$this->sendFetchRaw($tag, $seq, $name, true);
			$this->sendOk('UID FETCH completed', $tag);
		}
		elseif($commandcmp == 'store'){
			$this->select();
			#$this->log('debug', 'client '.$this->id.' current folder: '.$this->selectedFolder);
			
			$args = $this->msgParseString($args, 3);
			$seq = $args[0];
			$name = $args[1];
			$flagsStr = $args[2];
			$this->sendStoreRaw($tag, $seq, $name, $flagsStr, true);
			$this->sendOk('UID STORE completed', $tag);
		}
		elseif($commandcmp == 'search'){
			#$this->sendBad('search not implemented.', $tag);
			$this->select();
			
			ve($args);
			
			#$this->sendSearchRaw($tag, $args, true); # TODO
			$this->sendOk('UID SEARCH completed', $tag);
		}
		else{
			$this->sendBad('Arguments invalid.', $tag);
		}
	}
	
	public function sendOk($text, $tag = null, $code = null){
		if($tag === null){
			$tag = '*';
		}
		$this->dataSend($tag.' OK'.($code ? ' ['.$code.']' : '').' '.$text);
	}
	
	public function sendNo($text, $tag = null, $code = null){
		if($tag === null){
			$tag = '*';
		}
		$this->dataSend($tag.' NO'.($code ? ' ['.$code.']' : '').' '.$text);
	}
	
	public function sendBad($text, $tag = null, $code = null){
		if($tag === null){
			$tag = '*';
		}
		$this->dataSend($tag.' BAD'.($code ? ' ['.$code.']' : '').' '.$text);
	}
	
	public function sendPreauth($text, $code = null){
		$this->dataSend('* PREAUTH'.($code ? ' ['.$code.']' : '').' '.$text);
	}
	
	public function sendBye($text, $code = null){
		$this->dataSend('* BYE'.($code ? ' ['.$code.']' : '').' '.$text);
	}
	
	public function shutdown(){
		if(!$this->getStatus('hasShutdown')){
			$this->setStatus('hasShutdown', true);
			
			$this->getSocket()->shutdown();
			$this->getSocket()->close();
		}
	}
	
	public function select($folder = null){
		if($folder === null){
			// Restore the previous selected mailbox. Maybe another client has
			// selected another mailbox so we must jump back to the folder for
			// this client.
			if($this->selectedFolder !== null){
				$this->getServer()->getRootStorage()->selectFolder($this->selectedFolder);
			}
		}
		else{
			// Select a new folder.
			$this->getServer()->getRootStorage()->selectFolder($folder);
			
			$this->log('debug', 'client '.$this->id.' prev select folder: "'.$this->selectedFolder.'"');
			$this->selectedFolder = $folder;
			$this->log('debug', 'client '.$this->id.' new select folder:  "'.$this->selectedFolder.'"');
		}
	}
	
}
