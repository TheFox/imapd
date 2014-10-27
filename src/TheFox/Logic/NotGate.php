<?php

namespace TheFox\Logic;

class NotGate extends Gate{
	
	public function __construct($obj = null){
		if($obj){
			$this->setObj($obj);
		}
	}
	
	public function setObj($obj){
		$this->setObj1($obj);
	}
	
	public function bool(){
		$bool = false;
		if($this->getObj1()){
			$bool = $this->getObj1()->bool();
		}
		return (!$bool);
	}
	
}
