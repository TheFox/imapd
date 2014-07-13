<?php

namespace TheFox\Logic;

class CriteriaTree{
	
	private $criteria = array();
	
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
		fwrite(STDOUT, str_repeat($rep, 4 * $level).$func.': '.$level.''."\n");
		
		#if($level >= 1) usleep(100000); # TODO
		
		$gate = null;
		$obj1 = null;
		
		$critLen = count($this->criteria);
		
		for($criteriumId = 0; $criteriumId < $critLen; $criteriumId++){
			$criterium = $this->criteria[$criteriumId];
			
			if(is_array($criterium)){
				fwrite(STDOUT, str_repeat($rep, 4 * $level)."\t".'criterium: '.$criteriumId.' array o1='.($obj1 === null ? 'n' : 'o').' g='.($gate === null ? 'n' : 'o').''."\n");
				
				$tree = new CriteriaTree($criterium);
				$subobj = $tree->$func($level + 1);
				
				if($gate){
					fwrite(STDOUT, str_repeat($rep, 4 * $level)."\t\t".'array: gate is set'."\n");
					
					$gate->setObj2($subobj);
				}
				else{
					fwrite(STDOUT, str_repeat($rep, 4 * $level)."\t\t".'array: gate == null'."\n");
					if($obj1 === null){
						fwrite(STDOUT, str_repeat($rep, 4 * $level)."\t\t\t".'array: obj1 == null'."\n");
						$obj1 = $subobj;
					}
					else{
						fwrite(STDOUT, str_repeat($rep, 4 * $level)."\t\t\t".'array: obj1 is set'."\n");
					}
				}
				
				fwrite(STDOUT, str_repeat($rep, 4 * $level)."\t".'array: subobj'."\n");
				#ve($subobj);
			}
			else{
				$criteriumcmp = strtolower($criterium);
				
				fwrite(STDOUT, str_repeat($rep, 4 * $level)."\t".'criterium: '.$criteriumId.' /'.$criterium.'/ /'.$criteriumcmp.'/ o1='.($obj1 === null ? 'null' : 'o').' g='.($gate === null ? 'n' : 'o').''."\n");
				
				if($criteriumcmp == 'or'){
					fwrite(STDOUT, str_repeat($rep, 4 * $level)."\t\t".'or'."\n");
					if($gate){
						fwrite(STDOUT, str_repeat($rep, 4 * $level)."\t\t\t".'gate is set'."\n");
						$obj1 = $gate;
						$gate = new OrGate();
						$gate->setObj1($obj1);
						$obj1 = null;
					}
					else{
						fwrite(STDOUT, str_repeat($rep, 4 * $level)."\t\t\t".'gate == null'."\n");
						$gate = new OrGate();
						if($obj1 === null){
							fwrite(STDOUT, str_repeat($rep, 4 * $level)."\t\t\t\t".'obj1 == null'."\n");
						}
						else{
							fwrite(STDOUT, str_repeat($rep, 4 * $level)."\t\t\t\t".'obj1 is set'."\n");
							$gate->setObj1($obj1);
							$obj1 = null;
						}
					}
				}
				elseif($criteriumcmp == 'and'){
					fwrite(STDOUT, str_repeat($rep, 4 * $level)."\t\t".'and'."\n");
					if($gate){
						fwrite(STDOUT, str_repeat($rep, 4 * $level)."\t\t\t".'gate is set'."\n");
						$obj1 = $gate;
						$gate = new AndGate();
						$gate->setObj1($obj1);
						$obj1 = null;
					}
					else{
						fwrite(STDOUT, str_repeat($rep, 4 * $level)."\t\t\t".'gate == null'."\n");
						$gate = new AndGate();
						if($obj1 === null){
							fwrite(STDOUT, str_repeat($rep, 4 * $level)."\t\t\t\t".'obj1 == null'."\n");
						}
						else{
							fwrite(STDOUT, str_repeat($rep, 4 * $level)."\t\t\t\t".'obj1 is set'."\n");
							$gate->setObj1($obj1);
							$obj1 = null;
						}
					}
				}
				elseif($criteriumcmp == 'not'){
					fwrite(STDOUT, str_repeat($rep, 4 * $level)."\t\t".'not'."\n");
				}
				else{
					fwrite(STDOUT, str_repeat($rep, 4 * $level)."\t\t".'val'."\n");
					if($gate){
						fwrite(STDOUT, str_repeat($rep, 4 * $level)."\t\t\t".'gate is set'."\n");
						
						if($gate instanceof OrGate){
							fwrite(STDOUT, str_repeat($rep, 4 * $level)."\t\t\t\t".'is OrGate'."\n");
							$rest = array_slice($this->criteria, $criteriumId);
							#ve($rest);
							$tree = new CriteriaTree($rest);
							$obj2 = $tree->$func($level + 1);
							$gate->setObj2($obj2);
							$obj1 = null;
							break;
						}
						else{
							fwrite(STDOUT, str_repeat($rep, 4 * $level)."\t\t\t\t".'is other: '.get_class($gate)."\n");
							$gate->setObj2(new Obj($criterium));
						}
					}
					else{
						fwrite(STDOUT, str_repeat($rep, 4 * $level)."\t\t\t".'gate == null'."\n");
						if($obj1 === null){
							fwrite(STDOUT, str_repeat($rep, 4 * $level)."\t\t\t\t".'obj1 == null'."\n");
							$obj1 = new Obj($criterium);
						}
						else{
							fwrite(STDOUT, str_repeat($rep, 4 * $level)."\t\t\t\t".'obj1 is set'."\n");
						}
					}
				}
			}
		}
		
		#return $gate;
		return($gate ? $gate : $obj1);
	}
	
}
