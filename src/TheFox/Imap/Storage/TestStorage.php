<?php

namespace TheFox\Imap\Storage;

use Symfony\Component\Filesystem\Filesystem;

class TestStorage extends AbstractStorage{
	
	public function getDirectorySeperator(){
		return '_';
	}
	
	public function setPath($path){
		parent::setPath($path);
		
		if(!file_exists($this->getPath())){
			$filesystem = new Filesystem();
			$filesystem->mkdir($this->getPath(), 0755, 0000, true);
		}
	}
	
	public function createFolder($path){
		
	}
	
	public function getFolders($baseFolder, $searchFolder, $recursive = false){
		$folders = array();
		return $folders;
	}
	
	public function addMail($mailStr, $folder, $flags, $recent){
		return null;
	}
	
	public function removeMail($msgId){
		
	}
	
	public function getMsgSeqById($msgId){
		return null;
	}
	
	public function getMsgIdBySeq($seqNum, $folder = null){
		return null;
	}
	
	public function getNextMsgId(){
		return null;
	}
	
}
