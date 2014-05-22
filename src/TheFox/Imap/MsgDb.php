<?php

namespace TheFox\Imap;

use TheFox\Storage\YamlStorage;

class MsgDb extends YamlStorage{
	
	private $msgIdByUid = array();
	private $msgUidById = array();
	
	public function __construct($filePath = null){
		#print __CLASS__.'->'.__FUNCTION__.''."\n";
		parent::__construct($filePath);
		
		$this->data['msgsId'] = 100000;
		$this->data['msgs'] = array();
		$this->data['timeCreated'] = time();
	}
	
	/*public function save(){
		#print __CLASS__.'->'.__FUNCTION__.''."\n";
		
		$this->data['msgs'] = array();
		foreach($this->msgs as $msgId => $msg){
			#print __CLASS__.'->'.__FUNCTION__.': '.$msgId."\n";
			
			$this->data['msgs'][$msgId] = array(
				'id' => $msg['id'],
				'uid' => $msg['uid'],
			);
			$msg->save();
		}
		
		$rv = parent::save();
		unset($this->data['msgs']);
		
		return $rv;
	}
	*/
	
	public function load(){
		#print __CLASS__.'->'.__FUNCTION__.''."\n";
		
		if(parent::load()){
			
			if(array_key_exists('msgs', $this->data) && $this->data['msgs']){
				foreach($this->data['msgs'] as $msgId => $msgAr){
					$this->msgIdByUid[$msgAr['uid']] = $msgAr['id'];
					$this->msgUidById[$msgAr['id']] = $msgAr['uid'];
				}
			}
			
			return true;
		}
		
		return false;
	}
	
	public function msgAdd($uid){
		#print __CLASS__.'->'.__FUNCTION__.': '.$uid."\n";
		
		$this->data['msgsId']++;
		$this->data['msgs'][$this->data['msgsId']] = array(
			'id' => $this->data['msgsId'],
			'uid' => $uid,
		);
		
		$this->setDataChanged(true);
	}
	
	public function getMsgUidById($id){
		return $this->msgIdByUid[$id];
	}
	
	public function getMsgIdByUid($uid){
		return $this->msgUidById[$uid];
	}
	
}
