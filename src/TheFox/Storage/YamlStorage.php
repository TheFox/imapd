<?php

namespace TheFox\Storage;

use Symfony\Component\Yaml\Yaml;

class YamlStorage{
	
	private $datadirBasePath = null;
	private $filePath = null;
	public $data = array();
	public $dataChanged = false;
	private $isLoaded = false;
	
	public function __construct($filePath = null){
		#print __CLASS__.'->'.__FUNCTION__.''."\n";
		if($filePath !== null){
			$this->setFilePath($filePath);
		}
	}
	
	public function save(){
		#fwrite(STDOUT, __CLASS__.'->'.__FUNCTION__.': '.(int)$this->dataChanged.', '.$this->getFilePath()."\n");
		$rv = false;
		
		if($this->dataChanged){
			if($this->getFilePath()){
				$rv = file_put_contents($this->getFilePath(), Yaml::dump($this->data));
			}
			if($rv){
				$this->setDataChanged(false);
			}
		}
		
		return $rv;
	}
	
	public function load(){
		#print __CLASS__.'->'.__FUNCTION__.''."\n";
		
		if($this->getFilePath()){
			if(file_exists($this->getFilePath())){
				$this->data = Yaml::parse($this->getFilePath());
				return $this->isLoaded(true);
			}
		}
		
		return false;
	}
	
	public function isLoaded($isLoaded = null){
		if($isLoaded !== null){
			$this->isLoaded = $isLoaded;
		}
		
		return $this->isLoaded;
	}
	
	public function setFilePath($filePath){
		$this->filePath = $filePath;
	}
	
	public function getFilePath(){
		return $this->filePath;
	}
	
	public function setDatadirBasePath($datadirBasePath){
		$this->datadirBasePath = $datadirBasePath;
	}
	
	public function getDatadirBasePath(){
		if($this->datadirBasePath){
			return $this->datadirBasePath;
		}
		
		return null;
	}
	
	public function setDataChanged($changed = true){
		$this->dataChanged = $changed;
		#fwrite(STDOUT, __CLASS__.'->'.__FUNCTION__.': '.(int)$this->dataChanged."\n");
	}
	
	public function getDataChanged(){
		return $this->dataChanged;
	}
	
}
