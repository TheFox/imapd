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
	
	public function createFolder($path){
		#fwrite(STDOUT, __FUNCTION__.': '.$path."\n");
		
		$path = $this->genFolderPath($path);
		if(!file_exists($path)){
			$filesystem = new Filesystem();
			$filesystem->mkdir($path, 0755, 0000, true);
		}
	}
	
	public function getFolders($baseFolder, $searchFolder, $recursive = false){
		$folders = array();
		
		$path = $this->genFolderPath($baseFolder);
		
		#fwrite(STDOUT, 'getFolders path: '.$path."\n");
		#fwrite(STDOUT, 'getFolders base: '.$baseFolder."\n");
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
		
		foreach($files as $file){
			#fwrite(STDOUT, '  folder: '.$file->getPathname()."\n");
			#\Doctrine\Common\Util\Debug::dump($file);
			$folders[] = $file->getPathname();
		}
		
		return $folders;
	}
	
	public function addMail($mailStr, $folder, $flags, $recent){
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
	
	public function getMsgIdBySeq($seqNum, $folder = null){
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
					$id = $this->getDb()->getMsgIdByPath($file->getPathname());
					#fwrite(STDOUT, 'id: '.$id."\n");
					return $id;
				}
			}
		}
		
		return null;
	}
	
	public function getNextMsgId(){
		if($this->getDb()){
			return $this->getDb()->getNextId();
		}
		
		return null;
	}
	
}
