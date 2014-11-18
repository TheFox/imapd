<?php

namespace TheFox\Logic;

class Obj{
	
	private $value = null;
	
	public function __construct($value = null){
		$this->setValue($value);
	}
	
	public function __clone(){
		if($this->value && is_object($this->value)){
			$this->value = clone $this->value;
		}
	}
	
	public function setValue($value){
		$this->value = $value;
	}
	
	public function getValue(){
		return $this->value;
	}
	
	public function bool(){
		return ((bool)$this->value);
	}
	
}
