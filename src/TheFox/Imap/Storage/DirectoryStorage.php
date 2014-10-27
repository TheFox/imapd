<?php

namespace TheFox\Imap\Storage;

use Symfony\Component\Finder\Finder;
use Symfony\Component\Filesystem\Filesystem;

class DirectoryStorage extends AbstractStorage{
	
	public function getDirectorySeperator(){
		return DIRECTORY_SEPARATOR;
	}
	
	public function setPath($path){
		parent::setPath($path);
		
		if(!file_exists($this->getPath())){
			$filesystem = new Filesystem();
			$filesystem->mkdir($this->getPath(), 0755, 0000, true);
		}
	}
	
	public function createFolder($folder){
		if(!$this->folderExists($folder)){
			$path = $this->genFolderPath($folder);
			if(!file_exists($path)){
				$filesystem = new Filesystem();
				$filesystem->mkdir($path, 0755, 0000, true);
				return file_exists($path);
			}
		}
		else{
			return false;
		}
	}
	
	public function getFolders($baseFolder, $searchFolder, $recursive = false){
		$path = $this->genFolderPath($baseFolder);
		
		$finder = new Finder();
		$files = array();
		if($recursive){
			$files = $finder->in($path)->directories()->name($searchFolder);
		}
		else{
			$files = $finder->in($path)->directories()->depth(0)->name($searchFolder);
		}
		
		$folders = array();
		foreach($files as $file){
			$folderPath = $file->getPathname();
			$folderPath = substr($folderPath, $this->getPathLen());
			if($folderPath[0] == '/'){
				$folderPath = substr($folderPath, 1);
			}
			$folders[] = $folderPath;
		}
		
		return $folders;
	}
	
	public function folderExists($folder){
		$path = $this->genFolderPath($folder);
		return file_exists($path) && is_dir($path);
	}
	
	public function getMailsCountByFolder($folder, $flags = null){
		$path = $this->genFolderPath($folder);
		$finder = new Finder();
		$files = $finder->in($path)->files()->depth(0)->name('*.eml');
		$rv = 0;
		if($flags === null){
			$rv = count($files);
		}
		else{
			if($this->getDb()){
				foreach($files as $fileId => $file){
					$msgId = $this->getDb()->getMsgIdByPath($file->getPathname());
					if($msgId){
						$msgFlags = $this->getDb()->getFlagsById($msgId);
						foreach($flags as $flag){
							if(in_array($flag, $msgFlags)){
								$rv++;
								break;
							}
						}
					}
				}
			}
		}
		return $rv;
	}
	
	public function addMail($mailStr, $folder, $flags = array(), $recent = true){
		$msgId = null;
		
		$fileName = 'mail_'.sprintf('%.32f', microtime(true)).'_'.mt_rand(100000, 999999).'.eml';
		$filePath = $this->genFolderPath($folder).'/'.$fileName;
		
		if($this->getDb()){
			$msgId = $this->getDb()->addMsg($filePath, $flags, $recent);
		}
		
		file_put_contents($filePath, $mailStr);
		
		return $msgId;
	}
	
	public function removeMail($msgId){
		if($this->getDb()){
			$msg = $this->getDb()->removeMsg($msgId);
			$filesystem = new Filesystem();
			$filesystem->remove($msg['path']);
		}
	}
	
	public function copyMailById($msgId, $folder){
		if($this->getDb()){
			$msg = $this->getDb()->getMsgById($msgId);
			if(file_exists($msg['path'])){
				$pathinfo = pathinfo($msg['path']);
				$dstFolder = $this->genFolderPath($folder);
				$dstFile = $dstFolder.'/'.$pathinfo['basename'];
				$mailStr = file_get_contents($msg['path']);
				$this->addMail($mailStr, $folder);
			}
		}
	}
	
	public function copyMailBySequenceNum($seqNum, $folder, $dstFolder){
		$msgId = $this->getMsgIdBySeq($seqNum, $folder);
		if($msgId){
			$this->copyMailById($msgId, $dstFolder);
		}
	}
	
	public function getPlainMailById($msgId){
		if($this->getDb()){
			$msg = $this->getDb()->getMsgById($msgId);
			if(file_exists($msg['path'])){
				$mailStr = file_get_contents($msg['path']);
				return $mailStr;
			}
		}
		
		return '';
	}
	
	public function getMsgSeqById($msgId){
		if($this->getDb()){
			$msg = $this->getDb()->getMsgById($msgId);
			if($msg){
				$pathinfo = pathinfo($msg['path']);
				if(isset($pathinfo['dirname']) && isset($pathinfo['basename'])){
					$seq = 0;
					$finder = new Finder();
					$files = $finder->in($pathinfo['dirname'])->files()->depth(0)->name('*.eml');
					foreach($files as $file){
						$seq++;
						if($file->getFilename() == $pathinfo['basename']){
							break;
						}
					}
					
					return $seq;
				}
			}
		}
		
		return null;
	}
	
	public function getMsgIdBySeq($seqNum, $folder){
		if($this->getDb()){
			$path = $this->genFolderPath($folder);
			
			$seq = 0;
			$finder = new Finder();
			$files = $finder->in($path)->files()->depth(0)->name('*.eml');
			foreach($files as $file){
				$seq++;
				if($seq >= $seqNum){
					$msgId = $this->getDb()->getMsgIdByPath($file->getPathname());
					return $msgId;
				}
			}
		}
		
		return null;
	}
	
	public function getMsgsByFlags($flags){
		if($this->getDb()){
			return $this->getDb()->getMsgIdsByFlags($flags);
		}
		
		return array();
	}
	
	public function getFlagsById($msgId){
		if($this->getDb()){
			return $this->getDb()->getFlagsById($msgId);
		}
		
		return array();
	}
	
	public function setFlagsById($msgId, $flags){
		if($this->getDb()){
			$this->getDb()->setFlagsById($msgId, $flags);
		}
	}
	
	public function getFlagsBySeq($seqNum, $folder){
		if($this->getDb()){
			$path = $this->genFolderPath($folder);
			
			$seq = 0;
			$finder = new Finder();
			$files = $finder->in($path)->files()->depth(0)->name('*.eml');
			foreach($files as $file){
				$seq++;
				if($seq >= $seqNum){
					$msgId = $this->getDb()->getMsgIdByPath($file->getPathname());
					return $this->getFlagsById($msgId);
				}
			}
		}
		
		return array();
	}
	
	public function setFlagsBySeq($seqNum, $folder, $flags){
		if($this->getDb()){
			$msgId = $this->getMsgIdBySeq($seqNum, $folder);
			if($msgId){
				$this->getDb()->setFlagsById($msgId, $flags);
			}
		}
	}
	
	public function getNextMsgId(){
		if($this->getDb()){
			return $this->getDb()->getNextId();
		}
		
		return null;
	}
	
}
