<?php

namespace TheFox\Imap\Storage;

use Symfony\Component\Finder\Finder;
use Symfony\Component\Filesystem\Filesystem;

#use TheFox\Storage\YamlStorage\MsgDb;

abstract class AbstractStorage{
	
	#private $hasSetup = false;
	private $path;
	private $dbPath;
	private $db;
	private $type = 'normal';
	
	public function __construct(){
		
	}
	
	abstract protected function getDirectorySeperator();
	
	public function setPath($path){
		#fwrite(STDOUT, 'AbstractStorage->setPath: '.$path."\n");
		
		$this->path = $path;
	}
	
	public function getPath(){
		return $this->path;
	}
	
	public function setDbPath($dbPath){
		$this->dbPath = $dbPath;
		
		#$this->db = new MsgDb($this->dbPath);
		#$this->db->load();
	}
	
	public function getDbPath(){
		return $this->dbPath;
	}
	
	public function setDb($db){
		$this->db = $db;
	}
	
	public function getDb(){
		return $this->db;
	}
	
	public function setType($type){
		$this->type = $type;
	}
	
	public function getType(){
		return $this->type;
	}
	
	public function genFolderPath($path){
		#fwrite(STDOUT, __FUNCTION__.' A: '.$path."\n");
		
		if($path){
			$seperator = $this->getDirectorySeperator();
			$path = str_replace('.', $seperator, $path);
			$path = $this->path.DIRECTORY_SEPARATOR.$path;
			$path = str_replace('//', '/', $path);
			
			if(substr($path, -1) == '/'){
				$path = substr($path, 0, -1);
			}
		}
		else{
			$path = $this->path;
		}
		
		
		#fwrite(STDOUT, __FUNCTION__.' B: '.$path."\n");
		
		return $path;
	}
	
	abstract protected function createFolder($path);
	
	abstract protected function getFolders($baseFolder, $searchFolder, $recursive = false);
	
	abstract protected function addMail($mailStr, $folder, $flags, $recent);
	
	abstract protected function removeMail($msgId);
	
	abstract protected function copyMail($msgId, $folder);
	
	abstract protected function getPlainMailById($msgId);
	
	abstract protected function getMsgSeqById($msgId);
	
	abstract protected function getMsgIdBySeq($seqNum, $folder = null);
	
	abstract protected function getNextMsgId();
	
	public function save(){
		if($this->db){
			$this->db->save();
		}
	}
	
}
