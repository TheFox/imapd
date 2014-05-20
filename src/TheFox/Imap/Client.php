<?php

namespace TheFox\Imap;

use Exception;
use RuntimeException;

use Zend\Mail\Storage;

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
	
	public function msgGetArgs($msgRaw){
		$args = preg_split('/ /', $msgRaw);

		$max = 0;
		$tag = '';
		while(!$tag && $max <= 100){
			$max++;
			$tag = array_shift($args);
		}
		
		$max = 0;
		$command = '';
		while(!$command && $max <= 100){
			$max++;
			$command = array_shift($args);
		}

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
			if($isStrBegin && !$isStrEnd){
				#fwrite(STDOUT, "    str begin\n");
				$new = true;
				$arg = substr($arg, 1);
			}
			elseif(!$isStrBegin && $isStrEnd){
				#fwrite(STDOUT, "    str end\n");
				$arg = substr($arg, 0, -1);
			}
			elseif($isStrBegin && $isStrEnd){
				#fwrite(STDOUT, "    str begin & end\n");
				$new = true;
				$arg = substr(substr($arg, 1), 0, -1);
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
			
			if($new){
				#fwrite(STDOUT, "    new\n");
				$argsrc++;
				$argsr[$argsrc] = array($arg);
			}
			else{
				#fwrite(STDOUT, "    append\n");
				$argsr[$argsrc][] = $arg;
			}
		}
		
		$args = array_values($args);

		#fwrite(STDOUT, "\n\n");

		foreach($argsr as $n => $arg){
			$argstr = join(' ', $arg);
			#fwrite(STDOUT, "r arg $n '".$argstr."'\n");
			
			if($argstr){
				$argsr[$n] = $argstr;
				
				foreach($arg as $j => $sarg){
					#fwrite(STDOUT, "    s arg $j '".$sarg."'\n");
				}
			}
			else{
				#fwrite(STDOUT, "    unset\n");
				unset($argsr[$n]);
			}
			
		}
		$argsr = array_values($argsr);
		
		return array(
			'tag' => $tag,
			'command' => $command,
			'args' => $argsr,
		);
	}
	
	private function msgHandle($msgRaw){
		#print __CLASS__.'->'.__FUNCTION__.': "'.$msgRaw.'"'."\n";
		
		$args = $this->msgGetArgs($msgRaw);
		
		$tag = $args['tag'];
		$command = $args['command'];
		$commandcmp = strtolower($command);
		$args = $args['args'];
		
		
		
		#ve($args);
		
		#$this->log('debug', 'client '.$this->id.': >'.$tag.'< >'.$command.'<');
		
		if($commandcmp == 'capability'){
			$this->log('debug', 'client '.$this->id.' capability: '.$tag);
			
			$this->sendCapability($tag);
		}
		elseif($commandcmp == 'authenticate'){
			$this->log('debug', 'client '.$this->id.' authenticate: "'.$args[0].'"');
			
			$this->sendAuthenticate($tag, $args[0]);
		}
		elseif($commandcmp == 'lsub'){
			$this->log('debug', 'client '.$this->id.' lsub: '.$args[0]);
			
			$this->sendLsub($tag);
		}
		elseif($commandcmp == 'list'){
			$this->log('debug', 'client '.$this->id.' list: '.$args[0]);
			
			$this->sendList($tag);
		}
		elseif($commandcmp == 'create'){
			$this->log('debug', 'client '.$this->id.' create: '.$args[0]);
			
			$this->sendCreate($tag);
		}
		elseif($commandcmp == 'select'){
			$name = $args[0];
			
			if($name[0] == '"' && substr($name, -1) == '"'){
				$name = substr(substr($name, 1), 0, -1);
			}
			
			$this->log('debug', 'client '.$this->id.' select: "'.$name.'"');
			
			$this->sendSelect($tag, $name);
			
			$this->sendOk('Testmsg', null, 'ALERT');
		}
		elseif($commandcmp == 'uid'){
			$this->log('debug', 'client '.$this->id.' uid: "'.$args[0].'" "'.$args[1].'"');
			
			$this->sendUid($tag);
		}
		else{
			$this->log('debug', 'client '.$this->id.' not implemented: "'.$tag.'" "'.$command.'"');
		}
		
	}
	
	private function dataSend($msg){
		$this->log('debug', 'client '.$this->id.' data send: "'.$msg.'"');
		$this->getSocket()->write($msg.static::MSG_SEPARATOR);
	}
	
	public function sendOk($text, $tag = '*', $code = ''){
		if($tag === null){
			$tag = '*';
		}
		$this->dataSend($tag.' OK'.($code ? ' ['.$code.']' : '').' '.$text);
	}
	
	public function sendNo($text, $tag = '*', $code = ''){
		$this->dataSend($tag.' NO'.($code ? ' ['.$code.']' : '').' '.$text);
	}
	
	public function sendBad($text, $tag = '*', $code = ''){
		$this->dataSend($tag.' BAD'.($code ? ' ['.$code.']' : '').' '.$text);
	}
	
	public function sendHello(){
		$this->sendOk('IMAP4rev1 Service Ready');
	}
	
	private function sendCapability($tag){
		$this->dataSend('* CAPABILITY IMAP4rev1 AUTH=PLAIN');
		$this->sendOk('CAPABILITY completed', $tag);
	}
	
	private function sendAuthenticate($tag, $mechanism){
		$this->dataSend('+');
		#$this->dataSend('+ '.base64_encode('hello'));
		$this->sendOk($mechanism.' authentication successful', $tag);
	}
	
	private function sendLsub($tag){
		#$this->dataSend('* LSUB () "." "#news.test"');
		$this->sendOk('LSUB completed', $tag);
	}
	
	private function sendList($tag){
		$this->sendOk('LIST completed', $tag);
	}
	
	private function sendCreate($tag){
		$this->sendOk('CREATE completed', $tag);
	}
	
	private function sendSelect($tag, $folder){
		
		$storage = $this->getServer()->getRootStorage();
		
		#ve($storage);
		#ve($storage->getCurrentFolder());
		#ve(get_class_methods($storage));
		
		#foreach($storage->getFolders() as $folder){ print "name: ".$folder->getLocalName()."\n";}
		
		try{
			$this->getServer()->getRootStorage()->selectFolder($folder);
			#$storage->selectFolder($folder);
			#$folder = $storage->INBOX;
			#$folder = $storage->test2;
			#ve($folder);
			
		}
		catch(Exception $e){
			print 'sendSelect: '.$e->getMessage()."\n";
		}
		
		$count = $storage->countMessages();
		$firstUnseen = 1;
		for($n = 1; $n <= $count; $n++){
			
			$message = $storage->getMessage($n);
			#ve($message);
			#ve(get_class_methods($message));
			
			
			print 'sendSelect msg: '.$n.', '.$message->subject.', '.(int)$message->hasFlag(Storage::FLAG_RECENT).', '.$storage->getUniqueId($n).''."\n";
			
			if($message->hasFlag(Storage::FLAG_RECENT)){
				$firstUnseen = $n;
				break;
			}
		}
		
		$this->dataSend('* '.$count.' EXISTS');
		$this->dataSend('* '.$storage->countMessages(Storage::FLAG_RECENT).' RECENT');
		$this->dataSend('* OK [UNSEEN '.$firstUnseen.'] Message '.$firstUnseen.' is first unseen');
		#$this->dataSend('* OK [UIDVALIDITY 3857529045] UIDs valid');
		#$this->dataSend('* OK [UIDNEXT 4392] Predicted next UID');
		
		
		$this->dataSend('* FLAGS ('.Storage::FLAG_ANSWERED.' '.Storage::FLAG_FLAGGED.' '.Storage::FLAG_DELETED.' '.Storage::FLAG_SEEN.' '.Storage::FLAG_DRAFT.')');
		#$this->dataSend('* OK [PERMANENTFLAGS ('.Storage::FLAG_DELETED.' '.Storage::FLAG_SEEN.' \*)] Limited');
		$this->sendOk('[READ-WRITE] SELECT', 'completed', $tag);
	}
	
	private function sendUid($tag){
		
	}
	
	public function shutdown(){
		if(!$this->getStatus('hasShutdown')){
			$this->setStatus('hasShutdown', true);
			
			$this->getSocket()->shutdown();
			$this->getSocket()->close();
		}
	}
	
}
