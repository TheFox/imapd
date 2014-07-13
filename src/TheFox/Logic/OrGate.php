<?php

namespace TheFox\Logic;

class OrGate extends Gate{
	
	public function bool(){
		$bool1 = false;
		if($this->getObj1()){
			$bool1 = $this->getObj1()->bool();
		}
		$bool2 = false;
		if($this->getObj2()){
			$bool2 = $this->getObj2()->bool();
		}
		return($bool1 || $bool2);
	}
	
}
