<?php

namespace TheFox\Imap;

use TheFox\Storage\YamlStorage;

class MsgDb extends YamlStorage{
	
	#private $msgIdByUid = array();
	#private $msgUidById = array();
	private $msgsByPath = array();
	
	public function __construct($filePath = null){
		#print __CLASS__.'->'.__FUNCTION__.''."\n";
		parent::__construct($filePath);
		
		$this->data['msgsId'] = 100000;
		$this->data['msgs'] = array();
		$this->data['timeCreated'] = time();
	}
	
	public function load(){
		#print __CLASS__.'->'.__FUNCTION__.''."\n";
		
		if(parent::load()){
			
			if(array_key_exists('msgs', $this->data) && $this->data['msgs']){
				foreach($this->data['msgs'] as $msgId => $msgAr){
					#$this->msgIdByUid[$msgAr['uid']] = $msgAr['id'];
					#$this->msgUidById[$msgAr['id']] = $msgAr['uid'];
					$this->msgsByPath[$msgAr['path']] = $msgAr;
					
					#print __CLASS__.'->'.__FUNCTION__.': '.$msgAr['id'].' -> '.$msgAr['uid']."\n";
				}
			}
			
			return true;
		}
		
		return false;
	}
	
	public function addMsg($path){
		$this->data['msgsId']++;
		$this->data['msgs'][$this->data['msgsId']] = array(
			'id' => $this->data['msgsId'],
			'path' => $path,
			'flags' => '',
		);
		$this->setDataChanged(true);
		
		return $this->data['msgsId'];
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
	
	/*
	public function msgAdd($uid, $seq = 0, $folder = null){
		#fwrite(STDOUT, "msgAdd: ".$uid."\n");
		
		if($uid){
			// Fix the shit from https://github.com/zendframework/zf2/issues/6317.
			// I think the coder of Zend\Mail is a noob. This line is for you.
			$pos = strpos($uid, ',');
			if($pos !== false){
				$uid = substr($uid, 0, $pos);
			}
		}
		
		$this->data['msgsId']++;
		$this->data['msgs'][$this->data['msgsId']] = array(
			'id' => $this->data['msgsId'],
			'uid' => $uid,
			'seq' => $seq,
			'folder' => $folder,
		);
		$this->msgIdByUid[$uid] = $this->data['msgsId'];
		$this->msgUidById[$this->data['msgsId']] = $uid;
		
		$this->setDataChanged(true);
		
		return $this->data['msgsId'];
	}
	
	public function msgRemove($id){
		#fwrite(STDOUT, __CLASS__.'->'.__FUNCTION__.': /'.$id.'/'."\n");
		
		$oldMsg = $this->data['msgs'][$id];
		
		#ve($oldMsg);
		
		#fwrite(STDOUT, __CLASS__.'->'.__FUNCTION__.' unset uid: '.$oldMsg['uid']."\n");
		unset($this->msgIdByUid[$oldMsg['uid']]);
		
		#fwrite(STDOUT, __CLASS__.'->'.__FUNCTION__.' unset id: '.$oldMsg['id']."\n");
		unset($this->msgUidById[$oldMsg['id']]);
		
		#fwrite(STDOUT, __CLASS__.'->'.__FUNCTION__.' unset: '.$id."\n");
		unset($this->data['msgs'][$id]);
		
		$newSeq = 1;
		foreach($this->data['msgs'] as $msgId => $msg){
			if($msg['folder'] == $oldMsg['folder']){
				$this->data['msgs'][$msgId]['seq'] = $newSeq;
				$newSeq++;
			}
		}
		
		$this->setDataChanged(true);
	}
	
	public function getMsgUidById($id){
		if(isset($this->msgUidById[$id])){
			return $this->msgUidById[$id];
		}
		return null;
	}
	
	public function getMsgIdByUid($uid){
		if(isset($this->msgIdByUid[$uid])){
			#fwrite(STDOUT, __CLASS__.'->'.__FUNCTION__.': '.$uid.' is set'."\n");
			return $this->msgIdByUid[$uid];
		}
		
		// This is shitty. Because ISSUE 6317 (https://github.com/zendframework/zf2/issues/6317).
		foreach($this->msgIdByUid as $suid => $smsgId){
			#fwrite(STDOUT, __CLASS__.'->'.__FUNCTION__.': '.$suid.' => '.$smsgId.' "'.substr($uid, 0, strlen($suid)).'"'."\n");
			if(substr($uid, 0, strlen($suid)) == $suid){
				return $smsgId;
			}
		}
		
		#fwrite(STDOUT, __CLASS__.'->'.__FUNCTION__.': '.$uid.' not found'."\n");
		return null;
	}
	
	public function getSeqById($id){
		if(isset($this->data['msgs'][$id])){
			return $this->data['msgs'][$id]['seq'];
		}
		return null;
	}
	
	public function getNextId(){
		return $this->data['msgsId'] + 1;
	}
	*/
}
