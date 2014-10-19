<?php

namespace TheFox\Imap\Storage;

use Symfony\Component\Finder\Finder;
use Symfony\Component\Filesystem\Filesystem;

class DirectoryStorage extends AbstractStorage{
	
	public function __construct(){
		#fwrite(STDOUT, 'new DirectoryStorage'."\n");
	}
	
	public function getDirectorySeperator(){
		return DIRECTORY_SEPARATOR;
	}
	
	public function setPath($path){
		#fwrite(STDOUT, 'DirectoryStorage->setPath: '.$path."\n");
		
		parent::setPath($path);
		
		if(!file_exists($this->getPath())){
			$filesystem = new Filesystem();
			$filesystem->mkdir($this->getPath(), 0755, 0000, true);
		}
	}
	
	public function createFolder($folder){
		#fwrite(STDOUT, __FUNCTION__.': '.$folder."\n");
		
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
		
		fwrite(STDOUT, 'getFolders path: '.$path."\n");
		fwrite(STDOUT, 'getFolders base: '.$baseFolder."\n");
		#fwrite(STDOUT, 'getFolders search: '.$searchFolder."\n");
		#fwrite(STDOUT, 'getFolders rec: '.(int)$recursive."\n");
		
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
			#$folderPath = $file->getFilename();
			#fwrite(STDOUT, '  folder: '.$folderPath."\n");
			$folders[] = $folderPath;
		}
		
		return $folders;
	}
	
	public function getFolder($folder){
		$path = $this->genFolderPath($folder);
		#\Doctrine\Common\Util\Debug::dump($path);
		
		$finder = new Finder();
		$files = $finder->in($path)->files()->depth(0)->name('*.eml');
		#\Doctrine\Common\Util\Debug::dump($files);
		
		$msgs = array();
		foreach($files as $fileId => $file){
			\Doctrine\Common\Util\Debug::dump($file);
			#break;
			#$msgs[] = $this->getMsgIdByPath($file->);
		}
		return $msgs;
	}
	
	public function folderExists($folder){
		$path = $this->genFolderPath($folder);
		#\Doctrine\Common\Util\Debug::dump($path);
		#fwrite(STDOUT, ' -> path: '.$path."\n");
		return file_exists($path) && is_dir($path);
	}
	
	public function getMailsCountByFolder($folder){
		$path = $this->genFolderPath($folder);
		#fwrite(STDOUT, 'path: '.$path."\n");
		$finder = new Finder();
		$files = $finder->in($path)->files()->depth(0)->name('*.eml');
		#fwrite(STDOUT, 'count: '.(int)count($files)."\n");
		return count($files);
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
			fwrite(STDOUT, 'path: '.$msg['path']."\n");
			
			$filesystem = new Filesystem();
			$filesystem->remove($msg['path']);
		}
	}
	
	public function copyMail($msgId, $folder){
		if($this->getDb()){
			$msg = $this->getDb()->getMsgById($msgId);
			if(file_exists($msg['path'])){
				#\Doctrine\Common\Util\Debug::dump($msg);
				$pathinfo = pathinfo($msg['path']);
				#\Doctrine\Common\Util\Debug::dump($pathinfo);
				
				
				$dstFolder = $this->genFolderPath($folder);
				$dstFile = $dstFolder.'/'.$pathinfo['basename'];
				
				#fwrite(STDOUT, 'path: '.$dstFolder."\n");
				#fwrite(STDOUT, 'dstFile: '.$dstFile."\n");
				
				#$filesystem = new Filesystem();
				#$filesystem->copy($msg['path'], $dstFile);
				
				$mailStr = file_get_contents($msg['path']);
				$this->addMail($mailStr, $folder);
			}
		}
	}
	
	public function copyMailBySequenceNum($seqNum, $folder){
		$msgId = $this->getMsgIdBySeq($seqNum);
		if($msgId){
			$this->copyMail($msgId, $folder);
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
				#\Doctrine\Common\Util\Debug::dump($pathinfo);
				if(isset($pathinfo['dirname']) && isset($pathinfo['basename'])){
					$seq = 0;
					$finder = new Finder();
					$files = $finder->in($pathinfo['dirname'])->files()->depth(0)->name('*.eml');
					foreach($files as $file){
						#\Doctrine\Common\Util\Debug::dump($file);
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
		#fwrite(STDOUT, 'getMsgIdBySeq'."\n");
		
		if($this->getDb()){
			$path = $this->genFolderPath($folder);
			
			$seq = 0;
			$finder = new Finder();
			$files = $finder->in($path)->files()->depth(0)->name('*.eml');
			foreach($files as $file){
				$seq++;
				#fwrite(STDOUT, 'getMsgIdBySeq: '.$seq.', '.$file->getPathname()."\n");
				if($seq >= $seqNum){
					$msgId = $this->getDb()->getMsgIdByPath($file->getPathname());
					#fwrite(STDOUT, 'msgId: '.$msgId."\n");
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
	
	public function getNextMsgId(){
		if($this->getDb()){
			return $this->getDb()->getNextId();
		}
		
		return null;
	}
	
}
