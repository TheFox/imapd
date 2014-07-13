<?php

namespace TheFox\Logic;

class Obj{
	
	private $value = null;
	
	public function __construct($value = null){
		$this->value = $value;
	}
	
	public function bool(){
		return((bool)$this->value);
	}
	
}
