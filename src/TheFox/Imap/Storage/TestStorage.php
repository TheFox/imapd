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
	
	public function createFolder($folder){
		
	}
	
	public function getFolders($baseFolder, $searchFolder, $recursive = false){
		$folders = array();
		return $folders;
	}
	
	public function getFolder($folder){
		return array();
	}
	
	public function folderExists($folder){
		return false;
	}
	
	public function getMailsCountByFolder($folder){
		return 0;
	}
	
	public function addMail($mailStr, $folder, $flags, $recent){
		return null;
	}
	
	public function removeMail($msgId){
		
	}
	
	public function copyMailById($msgId, $folder){
		
	}
	
	public function copyMailBySequenceNum($seqNum, $folder, $dstFolder){
		
	}
	
	public function getPlainMailById($msgId){
		return '';
	}
	
	public function getMsgSeqById($msgId){
		return null;
	}
	
	public function getMsgIdBySeq($seqNum, $folder){
		return null;
	}
	
	public function getMsgsByFlags($flags){
		return array();
	}
	
	public function getFlagsById($msgId){
		return array();
	}
	
	public function setFlagsById($msgId, $flags){
		
	}
	
	public function getFlagsBySeq($seqNum, $folder){
		return array();
	}
	
	public function setFlagsBySeq($seqNum, $folder, $flags){
		
	}
	
	public function getNextMsgId(){
		return null;
	}
	
}
