<?php

namespace TheFox\Logic;

class Obj{
	
	private $value = null;
	
	public function __construct($value = null){
		$this->value = $value;
	}
	
	public function __clone(){
		#fwrite(STDOUT, 'Obj __clone: '.$this->value."\n");
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
		return((bool)$this->value);
	}
	
}
