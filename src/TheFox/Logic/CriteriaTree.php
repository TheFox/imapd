<?php

namespace TheFox\Logic;

class CriteriaTree{
	
	private $criteria = array();
	private $rootGate = null;
	
	public function __construct($criteria = null){
		if($criteria){
			$this->setCriteria($criteria);
		}
	}
	
	public function setCriteria($criteria){
		$this->criteria = $criteria;
	}
	
	public function getRootGate(){
		return $this->rootGate;
	}
	
	public function build($level = 0){
		$func = __FUNCTION__;
		$rep = '-';
		
		$rootGate = null;
		$gate = null;
		$obj1 = null;
		
		$critLen = count($this->criteria);
		$realCriteriaC = 0;
		for($criteriumId = 0; $criteriumId < $critLen; $criteriumId++){
			$criterium = $this->criteria[$criteriumId];
			
			if(is_array($criterium)){
				$tree = new CriteriaTree($criterium);
				$subobj = $tree->$func($level + 1);
				
				if($gate){
					$gate->setObj2($subobj);
				}
				else{
					if($obj1 === null){
						#$obj1 = $subobj;
						$rootGate = $obj1 = $subobj;
					}
				}
				
				$realCriteriaC++;
			}
			else{
				$criteriumcmp = strtolower($criterium);
				
				if($criteriumcmp == 'or'){
					if($gate){
						$oldGate = $gate;
						$rootGate = $gate = new OrGate();
						$gate->setObj1($oldGate);
					}
					else{
						$rootGate = $gate = new OrGate();
						if($obj1 !== null){
							$gate->setObj1($obj1);
							$obj1 = null;
						}
					}
				}
				elseif($criteriumcmp == 'and'){
					if($gate){
						$oldGate = $gate;
						$gate = new AndGate();
						if($oldGate instanceof NotGate){
							$rootGate = $gate;
							$gate->setObj1($oldGate);
						}
						else{
							$gate->setObj1($oldGate->getObj2());
							$oldGate->setObj2($gate);
						}
					}
					else{
						$rootGate = $gate = new AndGate();
						if($obj1 !== null){
							$gate->setObj1($obj1);
							$obj1 = null;
						}
					}
				}
				elseif($criteriumcmp == 'not'){
					if($gate){
						$newGate = new NotGate();
						$gate->setObj2($newGate);
					}
					else{
						$rootGate = $gate = new NotGate();
					}
				}
				else{
					if($gate){
						if($gate instanceof OrGate){
							if($gate->getObj2() && $gate->getObj2() instanceof NotGate){
								$gate->getObj2()->setObj(new Obj($criterium));
							}
							else{
								$gate->setObj2(new Obj($criterium));
							}
						}
						elseif($gate instanceof AndGate){
							if($gate->getObj2() && $gate->getObj2() instanceof NotGate){
								$gate->getObj2()->setObj(new Obj($criterium));
							}
							else{
								$gate->setObj2(new Obj($criterium));
							}
						}
						elseif($gate instanceof NotGate){
							$gate->setObj1(new Obj($criterium));
						}
					}
					else{
						if($obj1 === null){
							$rootGate = $obj1 = new Obj($criterium);
						}
					}
				}
			}
		}
		
		$this->rootGate = $rootGate;
		return $rootGate;
	}
	
	public function bool(){
		return $this->getRootGate()->bool();
	}
	
}
