<?php

namespace TheFox\Logic;

class NotGate extends Gate{
	
	public function setObj($obj){
		$this->setObj1($obj);
	}
	
	public function bool(){
		$bool = false;
		if($this->getObj1()){
			$bool = $this->getObj1()->bool();
		}
		#fwrite(STDOUT, 'bool: '.($bool ? 'YES' : 'no')."\n");
		return(!$bool);
	}
	
}
