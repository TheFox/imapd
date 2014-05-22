<?php

namespace TheFox\Imap;

use Exception;
use RuntimeException;
use InvalidArgumentException;

use Zend\Mail\Storage;
use Zend\Mail\Headers;

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
		$this->status['hasAuth'] = false;
		$this->status['authStep'] = 0;
		$this->status['authTag'] = '';
		$this->status['authMechanism'] = '';
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
				$this->setStatus('authTag', $tag);
				$this->setStatus('authMechanism', $args[0]);
				
				$this->setStatus('authStep', 1);
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
				$this->sendBad('arguments invalid', $tag);
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
					$this->sendBad('arguments invalid', $tag);
				}
			}
			else{
				$this->sendNo($commandcmp.' failure', $tag);
			}
		}
		elseif($commandcmp == 'create'){
			$args = $this->msgParseString($args, 1);
			#ve($args);
			
			$this->log('debug', 'client '.$this->id.' create: '.$args[0]);
			
			if($this->getStatus('hasAuth')){
				if(isset($args[0]) && $args[0]){
					$this->sendCreate($tag);
				}
				else{
					$this->sendBad('arguments invalid', $tag);
				}
			}
			else{
				$this->sendNo($commandcmp.' failure', $tag);
			}
		}
		elseif($commandcmp == 'list'){
			$args = $this->msgParseString($args, 1);
			#ve($args);
			
			$this->log('debug', 'client '.$this->id.' list: '.$args[0]);
			
			if($this->getStatus('hasAuth')){
				if(isset($args[0]) && $args[0]){
					$this->sendList($tag);
				}
				else{
					$this->sendBad('arguments invalid', $tag);
				}
			}
			else{
				$this->sendNo($commandcmp.' failure', $tag);
			}
		}
		elseif($commandcmp == 'lsub'){
			$args = $this->msgParseString($args, 1);
			#ve($args);
			
			$this->log('debug', 'client '.$this->id.' lsub: '.$args[0]);
			
			if($this->getStatus('hasAuth')){
				if(isset($args[0]) && $args[0]){
					$this->sendLsub($tag);
				}
				else{
					$this->sendBad('arguments invalid', $tag);
				}
			}
			else{
				$this->sendNo($commandcmp.' failure', $tag);
			}
		}
		elseif($commandcmp == 'uid'){
			$args = $this->msgParseString($args, 3);
			#ve($args);
			
			$this->log('debug', 'client '.$this->id.' uid: "'.$args[0].'" "'.$args[1].'"');
			
			if($this->getStatus('hasAuth')){
				if(isset($args[0]) && $args[0] && isset($args[1]) && $args[1]){
					$this->sendUid($tag, $args);
				}
				else{
					$this->sendBad('arguments invalid', $tag);
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
			else{
				$this->log('debug', 'client '.$this->id.' not implemented: "'.$tag.'" "'.$command.'" >"'.$args.'"<');
				$this->sendBad('Not implemented: "'.$tag.'" "'.$command.'"', $tag);
			}
		}
	}
	
	private function dataSend($msg){
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
		$this->sendOk('NOOP completed', $tag);
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
	
	private function sendSelect($tag, $folder){
		try{
			$this->getServer()->getRootStorage()->selectFolder($folder);
		}
		catch(Exception $e){
			$this->sendNo('"'.$folder.'" no such mailbox', $tag);
			return;
		}
		
		$count = $this->getServer()->getRootStorage()->countMessages();
		
		// Search for first unseen msg.
		$firstUnseen = 0;
		for($n = 1; $n <= $count; $n++){
			$message = $this->getServer()->getRootStorage()->getMessage($n);
			#print 'sendSelect msg: '.$n.', '.$message->subject.', '.(int)$message->hasFlag(Storage::FLAG_RECENT).', '.$this->getServer()->getRootStorage()->getUniqueId($n).''."\n";
			if($message->hasFlag(Storage::FLAG_RECENT)){
				$firstUnseen = $n;
				break;
			}
		}
		
		$this->dataSend('* '.$count.' EXISTS');
		$this->dataSend('* '.$this->getServer()->getRootStorage()->countMessages(Storage::FLAG_RECENT).' RECENT');
		$this->sendOk('Message '.$firstUnseen.' is first unseen', null, 'UNSEEN '.$firstUnseen);
		#$this->dataSend('* OK [UIDVALIDITY 3857529045] UIDs valid');
		#$this->dataSend('* OK [UIDNEXT 4392] Predicted next UID');
		$this->dataSend('* FLAGS ('.Storage::FLAG_ANSWERED.' '.Storage::FLAG_FLAGGED.' '.Storage::FLAG_DELETED.' '.Storage::FLAG_SEEN.' '.Storage::FLAG_DRAFT.')');
		$this->dataSend('* OK [PERMANENTFLAGS ('.Storage::FLAG_DELETED.' '.Storage::FLAG_SEEN.' \*)] Limited');
		$this->sendOk('SELECT completed', $tag, 'READ-WRITE');
	}
	
	private function sendCreate($tag){
		$this->sendOk('CREATE completed', $tag);
	}
	
	private function sendList($tag){
		$this->sendOk('LIST completed', $tag);
	}
	
	private function sendLsub($tag){
		#$this->dataSend('* LSUB () "." "#news.test"');
		$this->sendOk('LSUB completed', $tag);
	}
	
	private function sendFetchRaw($tag, $args, $isUid = false){
		#ve($args);
		
		$argSeq = $args[0];
		$argWanted = $args[1];
		
		$msgItems = array();
		if($isUid){
			$msgItems['uid'] = '';
		}
		if(isset($argWanted)){
			$wanted = $this->msgGetParenthesizedlist($argWanted);
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
		
		// Collect messages with sequence-sets.
		$msgSeqNums = array();
		foreach(preg_split('/,/', $argSeq) as $seqItem){
			$seqMin = 0;
			$seqMax = 0;
			
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
			
			$this->log('debug', 'sendUid seq: "'.$seqMin.'" - "'.$seqMax.'"');
			
			if($seqMin == 0){
				$this->sendBad('Invalid minimum sequence number: "'.$seqMin.'"', $tag);
				return;
			}
			
			$count = $this->getServer()->getRootStorage()->countMessages();
			if(!$count){
				$this->sendBad('No messages in selected mailbox', $tag);
				return;
			}
			
			$msgSeqAdd = false;
			$msgSeqIsEnd = false;
			for($msgSeqNum = 1; $msgSeqNum <= $count; $msgSeqNum++){
				$message = $this->getServer()->getRootStorage()->getMessage($msgSeqNum);
				#$uid = $this->getServer()->getRootStorage()->getUniqueId($msgSeqNum);
				#$uid = crc32($this->getServer()->getRootStorage()->getUniqueId($msgSeqNum));
				#$uid = $msgSeqNum;
				#$uid = $msgSeqNum + 10000;
				$uid = $this->getServer()->getRootStorageDbMsgIdBySeqNum($msgSeqNum);
				
				
				/*if(($isUid && ) || (!$isUid && )){
					$msgSeqAdd = true;
				}
				if(($isUid && $seqMax != '*' && $uid == $seqMax) || (!$isUid && $seqMax != '*' && $msgSeqNum >= $)){
					$this->log('debug', 'sendUid msg: '.$msgSeqNum.' break');
					$msgSeqIsEnd = true;
				}*/
				
				if($seqMin == '1' && $seqMax == '*'){
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
					#$this->log('debug', 'sendUid msg:       add');
					$msgSeqNums[] = $msgSeqNum;
				}
				
				if($msgSeqIsEnd){
					break;
				}
			}
		}
		
		// Process collected msgs.
		$msgSeqNums = array_unique($msgSeqNums);
		sort($msgSeqNums);
		foreach($msgSeqNums as $msgSeqNum){
			$message = $this->getServer()->getRootStorage()->getMessage($msgSeqNum);
			$flags = $message->getFlags();
			
			#$uid = $this->getServer()->getRootStorage()->getUniqueId($msgSeqNum);
			#$uid = crc32($this->getServer()->getRootStorage()->getUniqueId($msgSeqNum));
			#$uid = $msgSeqNum;
			#$uid = $msgSeqNum + 10000;
			$uid = $this->getServer()->getRootStorageDbMsgIdBySeqNum($msgSeqNum);
			if(!$uid){
				$this->log('error', 'Can not get ID for seq num '.$msgSeqNum.' from root storage.');
				continue;
			}
			
			#$this->log('debug', 'sendUid msg: '.$msgSeqNum.', '.$message->subject.', '.$uid);
			
			$this->log('debug', 'sendUid msg: '.$msgSeqNum.' '.sprintf('%10s', $uid).' ['.$seqMin.'/'.$seqMax.'] => '. (int)$isUid
					.' '. (int)($uid == $seqMin) .' '. (int)($msgSeqNum >= $seqMin) .' '. (int)($msgSeqNum >= $seqMax) );
			
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
					
					if(!$peek){
						$flags[Storage::FLAG_SEEN] = Storage::FLAG_SEEN;
					}
					
					$msgStr = $message->getHeaders()->toString().Headers::EOL.$message->getContent();
					if(isset($val['header'])){
						$this->log('debug', 'client '.$this->id.' fetch header');
						$section = 'HEADER';
						$msgStr = $message->getHeaders()->toString();
					}
					elseif(isset($val['header.fields'])){
						$this->log('debug', 'client '.$this->id.' fetch header.fields');
						$section = 'HEADER';
						$msgStr = '';
						
						$headerStrs = array();
						foreach($val['header.fields'] as $fieldNum => $field){
							try{
								$header = $message->getHeader($field);
								#$this->log('debug', 'client '.$this->id.' field: "'.$header->getFieldName().'" => "'.$header->getFieldValue().'"');
								#$this->log('debug', 'client '.$this->id.' field: "'.$header->toString().'"');
								$msgStr .= $header->toString().Headers::EOL;
							}
							catch(InvalidArgumentException $e){
							}
						}
					}
					else{
						$this->log('debug', 'client '.$this->id.' fetch all');
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
	
	private function sendFetch($tag, $args){
		$this->sendFetchRaw($tag, $args, false);
		$this->sendOk('FETCH completed', $tag);
	}
	
	private function sendUid($tag, $args){
		$command = array_shift($args);
		$commandcmp = strtolower($command);
		
		if($commandcmp == 'copy'){
			$this->sendBad('Copy not implemented', $tag);
		}
		elseif($commandcmp == 'fetch'){
			$this->sendFetchRaw($tag, $args, true);
			$this->sendOk('UID FETCH completed', $tag);
		}
		elseif($commandcmp == 'store'){
			$this->sendBad('Store not implemented', $tag);
		}
		else{
			$this->sendBad('arguments invalid', $tag);
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
	
}
