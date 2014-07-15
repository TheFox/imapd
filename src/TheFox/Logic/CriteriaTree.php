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
	
	public function build($level = 0){
		$func = __FUNCTION__;
		$rep = '-';
		#fwrite(STDOUT, str_repeat($rep, 4 * $level).$func.': '.$level.''."\n");
		
		$rootGate = null;
		$gate = null;
		$obj1 = null;
		
		$critLen = count($this->criteria);
		$realCriteriaC = 0;
		for($criteriumId = 0; $criteriumId < $critLen; $criteriumId++){
			$criterium = $this->criteria[$criteriumId];
			
			if(is_array($criterium)){
				#fwrite(STDOUT, str_repeat($rep, 4 * $level)."\t".'criterium: '.$criteriumId.' array o1='.($obj1 === null ? 'n' : 'o').' g='.($gate === null ? 'n' : 'o').''."\n");
				$tree = new CriteriaTree($criterium);
				$subobj = $tree->$func($level + 1);
				if($gate){
					#fwrite(STDOUT, str_repeat($rep, 4 * $level)."\t\t".'array: gate is set'."\n");
					$gate->setObj2($subobj);
				}
				else{
					#fwrite(STDOUT, str_repeat($rep, 4 * $level)."\t\t".'array: gate == null'."\n");
					if($obj1 === null){
						#fwrite(STDOUT, str_repeat($rep, 4 * $level)."\t\t\t".'array: obj1 == null'."\n");
						$obj1 = $subobj;
					}
					else{
						#fwrite(STDOUT, str_repeat($rep, 4 * $level)."\t\t\t".'array: obj1 is set'."\n");
					}
				}
				#fwrite(STDOUT, str_repeat($rep, 4 * $level)."\t".'array: subobj'."\n");
				$realCriteriaC++;
			}
			else{
				$criteriumcmp = strtolower($criterium);
				
				#fwrite(STDOUT, str_repeat($rep, 4 * $level)."\t".'criterium: '.$criteriumId.' /'.$criterium.'/ /'.$criteriumcmp.'/ o1='.($obj1 === null ? 'n' : 'o').' g='.($gate === null ? 'n' : get_class($gate)).''."\n");
				
				if($criteriumcmp == 'or'){
					#fwrite(STDOUT, str_repeat($rep, 4 * $level)."\t\t".'or'."\n");
					if($gate){
						#fwrite(STDOUT, str_repeat($rep, 4 * $level)."\t\t\t".'gate is set'."\n");
						
						$oldGate = $gate;
						$rootGate = $gate = new OrGate();
						$gate->setObj1($oldGate);
					}
					else{
						#fwrite(STDOUT, str_repeat($rep, 4 * $level)."\t\t\t".'gate == null'."\n");
						$rootGate = $gate = new OrGate();
						if($obj1 === null){
							#fwrite(STDOUT, str_repeat($rep, 4 * $level)."\t\t\t\t".'obj1 == null'."\n");
						}
						else{
							#fwrite(STDOUT, str_repeat($rep, 4 * $level)."\t\t\t\t".'obj1 is set'."\n");
							$gate->setObj1($obj1);
							$obj1 = null;
						}
					}
				}
				elseif($criteriumcmp == 'and'){
					#fwrite(STDOUT, str_repeat($rep, 4 * $level)."\t\t".'and'."\n");
					if($gate){
						#fwrite(STDOUT, str_repeat($rep, 4 * $level)."\t\t\t".'gate is set'."\n");
						$oldGate = $gate;
						$gate = new AndGate();
						if($oldGate instanceof NotGate){
							#fwrite(STDOUT, str_repeat($rep, 4 * $level)."\t\t\t".'gate is NotGate'."\n");
							$rootGate = $gate;
							$gate->setObj1($oldGate);
						}
						else{
							$gate->setObj1($oldGate->getObj2());
							$oldGate->setObj2($gate);
						}
					}
					else{
						#fwrite(STDOUT, str_repeat($rep, 4 * $level)."\t\t\t".'gate == null'."\n");
						$rootGate = $gate = new AndGate();
						if($obj1 === null){
							#fwrite(STDOUT, str_repeat($rep, 4 * $level)."\t\t\t\t".'obj1 == null'."\n");
						}
						else{
							#fwrite(STDOUT, str_repeat($rep, 4 * $level)."\t\t\t\t".'obj1 is set'."\n");
							$gate->setObj1($obj1);
							$obj1 = null;
						}
					}
				}
				elseif($criteriumcmp == 'not'){
					#fwrite(STDOUT, str_repeat($rep, 4 * $level)."\t\t".'not'."\n");
					
					if($gate){
						#fwrite(STDOUT, str_repeat($rep, 4 * $level)."\t\t\t".'gate is set'."\n");
						
						$newGate = new NotGate();
						$gate->setObj2($newGate);
					}
					else{
						#fwrite(STDOUT, str_repeat($rep, 4 * $level)."\t\t\t".'gate == null'."\n");
						$rootGate = $gate = new NotGate();
						if($obj1 === null){
							##fwrite(STDOUT, str_repeat($rep, 4 * $level)."\t\t\t\t".'obj1 == null'."\n");
						}
						else{
							##fwrite(STDOUT, str_repeat($rep, 4 * $level)."\t\t\t\t".'obj1 is set'."\n");
						}
					}
				}
				else{
					#fwrite(STDOUT, str_repeat($rep, 4 * $level)."\t\t".'val'."\n");
					if($gate){
						#fwrite(STDOUT, str_repeat($rep, 4 * $level)."\t\t\t".'gate is set'."\n");
						if($gate instanceof OrGate){
							#fwrite(STDOUT, str_repeat($rep, 4 * $level)."\t\t\t\t".'is OrGate'."\n");
							if($gate->getObj2() && $gate->getObj2() instanceof NotGate){
								#fwrite(STDOUT, str_repeat($rep, 4 * $level)."\t\t\t\t".'sub is NotGate'."\n");
								$gate->getObj2()->setObj(new Obj($criterium));
							}
							else{
								#fwrite(STDOUT, str_repeat($rep, 4 * $level)."\t\t\t\t".'sub is normal'."\n");
								$gate->setObj2(new Obj($criterium));
							}
						}
						elseif($gate instanceof AndGate){
							#fwrite(STDOUT, str_repeat($rep, 4 * $level)."\t\t\t\t".'is AndGate'."\n");
							if($gate->getObj2() && $gate->getObj2() instanceof NotGate){
								#fwrite(STDOUT, str_repeat($rep, 4 * $level)."\t\t\t\t".'sub is NotGate'."\n");
								$gate->getObj2()->setObj(new Obj($criterium));
							}
							else{
								#fwrite(STDOUT, str_repeat($rep, 4 * $level)."\t\t\t\t".'sub is normal'."\n");
								$gate->setObj2(new Obj($criterium));
							}
						}
						elseif($gate instanceof NotGate){
							#fwrite(STDOUT, str_repeat($rep, 4 * $level)."\t\t\t\t".'is NotGate'."\n");
							$gate->setObj1(new Obj($criterium));
						}
						else{
							##fwrite(STDOUT, str_repeat($rep, 4 * $level)."\t\t\t\t".'is other'."\n");
							#$gate->setObj2(new Obj($criterium));
						}
					}
					else{
						#fwrite(STDOUT, str_repeat($rep, 4 * $level)."\t\t\t".'gate == null'."\n");
						if($obj1 === null){
							#fwrite(STDOUT, str_repeat($rep, 4 * $level)."\t\t\t\t".'obj1 == null'."\n");
							$rootGate = $obj1 = new Obj($criterium);
							#ve($obj1);
						}
						else{
							#fwrite(STDOUT, str_repeat($rep, 4 * $level)."\t\t\t\t".'obj1 is set'."\n");
						}
					}
				}
			}
		}
		
		#return $gate;
		#return($gate ? $gate : $obj1);
		
		$this->rootGate = $rootGate;
		return $rootGate;
	}
	
	public function bool(){
		return $this->rootGate->bool();
	}
	
}
