<?php

/**
 * Message Database
 */

namespace TheFox\Imap;

use Zend\Mail\Storage;
use Symfony\Component\Yaml\Yaml;

use TheFox\Storage\YamlStorage;

class MsgDb extends YamlStorage{
	
	private $msgsByPath = array();
	
	public function __construct($filePath = null){
		parent::__construct($filePath);
		
		$this->data['msgsId'] = 100000;
		$this->data['msgs'] = array();
		$this->data['timeCreated'] = time();
	}
	
	/*public function save(){
		$rv = parent::save();
		if($rv){
			$path = $this->getFilePath();
			$path = str_replace('.yml', '_2.yml', $path);
			$data = $this->data;
			foreach($data['msgs'] as $msgId => $msg){
				unset($data['msgs'][$msgId]['path']);
			}
			$rv = file_put_contents($path, Yaml::dump($data));
		}
		
		return $rv;
	}*/
	
	public function load(){
		if(parent::load()){
			
			if(array_key_exists('msgs', $this->data) && $this->data['msgs']){
				foreach($this->data['msgs'] as $msgId => $msgAr){
					$this->msgsByPath[$msgAr['path']] = $msgAr;
				}
			}
			
			return true;
		}
		
		return false;
	}
	
	public function addMsg($path = '', $flags = null, $recent = true){
		if($flags === null){
			$flags = array(Storage::FLAG_SEEN);
		}
		
		$this->data['msgsId']++;
		$msg = array(
			'id' => $this->data['msgsId'],
			'path' => $path,
			'flags' => $flags,
			'recent' => $recent,
		);
		
		$this->data['msgs'][$this->data['msgsId']] = $msg;
		$this->msgsByPath[$path] = $msg;
		$this->setDataChanged(true);
		
		return $this->data['msgsId'];
	}
	
	public function removeMsg($msgId){
		$msg = $this->data['msgs'][$msgId];
		unset($this->data['msgs'][$msgId]);
		unset($this->msgsByPath[$msg['path']]);
		
		$this->setDataChanged(true);
		
		return $msg;
	}
	
	public function getMsgIdByPath($path){
		if(isset($this->msgsByPath[$path])){
			return $this->msgsByPath[$path]['id'];
		}
		
		return null;
	}
	
	public function getMsgById($msgId){
		if(isset($this->data['msgs'][$msgId])){
			return $this->data['msgs'][$msgId];
		}
		
		return null;
	}
	
	public function getMsgIdsByFlags($flags){
		$rv = array();
		foreach($this->data['msgs'] as $msgId => $msg){
			foreach($flags as $flag){
				if(in_array($flag, $msg['flags'])
					|| $flag == Storage::FLAG_RECENT && $msg['recent']){
					$rv[] = $msg['id'];
					break;
				}
			}
		}
		return $rv;
	}
	
	public function getFlagsById($msgId){
		if(isset($this->data['msgs'][$msgId])){
			$msg = $this->data['msgs'][$msgId];
			$flags = $msg['flags'];
			if($msg['recent']){
				$flags[] = Storage::FLAG_RECENT;
			}
			return $flags;
		}
		
		return array();
	}
	
	public function setFlagsById($msgId, $flags){
		$flags = array_unique($flags);
		if(($key = array_search(Storage::FLAG_RECENT, $flags)) !== false){
			unset($flags[$key]);
		}
		$flags = array_values($flags);
		
		if(isset($this->data['msgs'][$msgId])){
			$this->data['msgs'][$msgId]['flags'] = $flags;
			$this->data['msgs'][$msgId]['recent'] = false;
			$this->msgsByPath[$this->data['msgs'][$msgId]['path']] = $this->data['msgs'][$msgId];
			$this->setDataChanged(true);
		}
	}
	
	public function setPathById($msgId, $path){
		if(isset($this->data['msgs'][$msgId])){
			unset($this->msgsByPath[$this->data['msgs'][$msgId]['path']]);
			$this->data['msgs'][$msgId]['path'] = $path;
			$this->msgsByPath[$this->data['msgs'][$msgId]['path']] = $this->data['msgs'][$msgId];
			$this->setDataChanged(true);
		}
	}
	
	public function getNextId(){
		return $this->data['msgsId'] + 1;
	}
	
}
