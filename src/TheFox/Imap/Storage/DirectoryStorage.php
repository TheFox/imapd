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
			$filesystem->mkdir($this->getPath(), 0755);
		}
	}
	
	public function createFolder($folder){
		$rv = false;
		if(!$this->folderExists($folder)){
			$path = $this->genFolderPath($folder);
			if(!file_exists($path)){
				$filesystem = new Filesystem();
				$filesystem->mkdir($path, 0755);
				$rv = file_exists($path);
			}
		}
		return $rv;
	}
	
	public function getFolders($baseFolder, $searchFolder, $recursive = false){
		$path = $this->genFolderPath($baseFolder);
		
		$finder = new Finder();
		$files = array();
		if($recursive){
			$files = $finder->in($path)->directories()->name($searchFolder)->sortByName();
		}
		else{
			$files = $finder->in($path)->directories()->depth(0)->name($searchFolder)->sortByName();
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
		$files = $finder->in($path)->files()->depth(0)->name('*.eml')->sortByName();
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
		
		$path = $this->genFolderPath($folder);
		$fileName = 'mail_'.sprintf('%.32f', microtime(true)).'_'.mt_rand(100000, 999999).'.eml';
		$filePath = $path.'/'.$fileName;
		
		if($this->getDb()){
			$msgId = $this->getDb()->addMsg($filePath, $flags, $recent);
			
			$fileName = 'mail_'.sprintf('%032d', $msgId).'.eml';
			$filePath = $path.'/'.$fileName;
			
			$this->getDb()->setPathById($msgId, $filePath);
			#fwrite(STDOUT, 'storage addMail msgId: '.$msgId.PHP_EOL);
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
		$rv = '';
		if($this->getDb()){
			$msg = $this->getDb()->getMsgById($msgId);
			if(file_exists($msg['path'])){
				$mailStr = file_get_contents($msg['path']);
				$rv = $mailStr;
			}
		}
		
		return $rv;
	}
	
	public function getMsgSeqById($msgId){
		#fwrite(STDOUT, ' -> getMsgIdBySeq: /'.$msgId.'/'.PHP_EOL);
		$rv = null;
		if($this->getDb()){
			#fwrite(STDOUT, ' -> db ok'.PHP_EOL);
			$msg = $this->getDb()->getMsgById($msgId);
			if($msg){
				#fwrite(STDOUT, ' -> msg ok'.PHP_EOL);
				
				$pathinfo = pathinfo($msg['path']);
				#\Doctrine\Common\Util\Debug::dump($pathinfo);
				if(isset($pathinfo['dirname']) && isset($pathinfo['basename'])){
					#fwrite(STDOUT, ' -> name ok'.PHP_EOL);
					
					$seq = 0;
					$finder = new Finder();
					$files = $finder->in($pathinfo['dirname'])->files()->depth(0)->name('*.eml')->sortByName();
					foreach($files as $file){
						$seq++;
						
						#fwrite(STDOUT, ' -> seq: /'.$seq.'/ /'.$file->getBasename().'/'.PHP_EOL);
						if($file->getFilename() == $pathinfo['basename']){
							break;
						}
					}
					
					$rv = $seq;
				}
			}
		}
		
		return $rv;
	}
	
	public function getMsgIdBySeq($seqNum, $folder){
		$rv = null;
		if($this->getDb()){
			$path = $this->genFolderPath($folder);
			
			$seq = 0;
			$finder = new Finder();
			$files = $finder->in($path)->files()->depth(0)->name('*.eml')->sortByName();
			foreach($files as $file){
				$seq++;
				#fwrite(STDOUT, 'getMsgIdBySeq: '.$seq.' '.$seqNum.' '.$file->getBasename().PHP_EOL);
				
				if($seq >= $seqNum){
					$msgId = $this->getDb()->getMsgIdByPath($file->getPathname());
					#fwrite(STDOUT, 'getMsgIdBySeq id: /'.$msgId.'/'.PHP_EOL);
					$rv = $msgId;
					break;
				}
			}
		}
		return $rv;
	}
	
	public function getMsgsByFlags($flags){
		$rv = array();
		
		if($this->getDb()){
			$rv = $this->getDb()->getMsgIdsByFlags($flags);
		}
		
		return $rv;
	}
	
	public function getFlagsById($msgId){
		$rv = array();
		
		if($this->getDb()){
			$rv = $this->getDb()->getFlagsById($msgId);
		}
		
		return $rv;
	}
	
	public function setFlagsById($msgId, $flags){
		if($this->getDb()){
			$this->getDb()->setFlagsById($msgId, $flags);
		}
	}
	
	public function getFlagsBySeq($seqNum, $folder){
		$rv = array();
		
		if($this->getDb()){
			$path = $this->genFolderPath($folder);
			
			$seq = 0;
			$finder = new Finder();
			$files = $finder->in($path)->files()->depth(0)->name('*.eml')->sortByName();
			foreach($files as $file){
				$seq++;
				if($seq >= $seqNum){
					$msgId = $this->getDb()->getMsgIdByPath($file->getPathname());
					$rv = $this->getFlagsById($msgId);
					break;
				}
			}
		}
		
		return $rv;
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
		$rv = null;
		if($this->getDb()){
			$rv = $this->getDb()->getNextId();
		}
		return $rv;
	}
	
}
