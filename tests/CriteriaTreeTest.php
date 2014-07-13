<?php

use TheFox\Logic\CriteriaTree;
use TheFox\Logic\Obj;
use TheFox\Logic\Gate;
use TheFox\Logic\AndGate;
use TheFox\Logic\OrGate;
use TheFox\Logic\NotGate;

class CriteriaTreeTest extends PHPUnit_Framework_TestCase{
	
	public function providerCriteriaTree(){
		$rv = array();
		
		$gate1 = new OrGate();
		$gate1->setObj1(new Obj('val1'));
		#$gate1->setObj2();
		$rv[] = array(array('val1', 'OR'), $gate1);
		
		$gate1 = new OrGate();
		#$gate1->setObj1();
		$gate1->setObj2(new Obj('val2'));
		$rv[] = array(array('OR', 'val2'), $gate1);
		
		$gate1 = new OrGate();
		$gate1->setObj1(new Obj('val1'));
		$gate1->setObj2(new Obj('val2'));
		$rv[] = array(array('val1', 'OR', 'val2'), $gate1);
		
		$gate1 = new AndGate();
		$gate1->setObj1(new Obj('val1'));
		#$gate1->setObj2();
		$rv[] = array(array('val1', 'AND'), $gate1);
		
		$gate1 = new AndGate();
		#$gate1->setObj1();
		$gate1->setObj2(new Obj('val2'));
		$rv[] = array(array('AND', 'val2'), $gate1);
		
		$gate1 = new AndGate();
		$gate1->setObj1(new Obj('val1'));
		$gate1->setObj2(new Obj('val2'));
		$rv[] = array(array('val1', 'AND', 'val2'), $gate1);
		
		$gate1 = new AndGate();
		$gate2 = new AndGate();
		$gate2->setObj1(new Obj('val1'));
		$gate2->setObj2(new Obj('val2'));
		$gate1->setObj1($gate2);
		$gate1->setObj2(new Obj('val3'));
		$rv[] = array(array('val1', 'AND', 'val2', 'AND', 'val3'), $gate1);
		
		$gate1 = new OrGate();
		$gate1->setObj1(new Obj('val1'));
		$gate2 = new AndGate();
		$gate2->setObj1(new Obj('val2'));
		$gate2->setObj2(new Obj('val3'));
		$gate1->setObj2($gate2);
		$rv[] = array(array('val1', 'OR', 'val2', 'AND', 'val3'), $gate1);
		
		$gate1 = new OrGate();
		$gate1->setObj2(new Obj('val3'));
		$gate2 = new AndGate();
		$gate2->setObj1(new Obj('val1'));
		$gate2->setObj2(new Obj('val2'));
		$gate1->setObj1($gate2);
		$rv[] = array(array('val1', 'AND', 'val2', 'OR', 'val3'), $gate1);
		
		
		$gate1 = new AndGate();
		$gate1->setObj1(new Obj('val1'));
		$gate2 = new OrGate();
		$gate2->setObj1(new Obj('val2'));
		$gate2->setObj2(new Obj('val3'));
		$gate1->setObj2($gate2);
		#$rv[] = array(array('val1', 'AND', array('val2', 'OR', 'val3')), $gate1);
		
		$gate1 = new AndGate();
		$gate1->setObj1(new Obj('val1'));
		$gate2 = new AndGate();
		$gate2->setObj1(new Obj('val2'));
		$gate2->setObj2(new Obj('val3'));
		$gate1->setObj2($gate2);
		$rv[] = array(array('val1', 'AND', array('val2', 'AND', 'val3')), $gate1);
		
		
		#$rv[] = array(array('val1', 'OR', array('val2', 'OR', 'val3')), $gate1);
		#$rv[] = array(array('val1', 'OR', array('val2', 'AND', 'val3')), $gate1);
		
		#$rv[] = array(array(array('val2', 'OR', 'val3'), 'OR', 'val1'), $gate1);
		#$rv[] = array(array(array('val2', 'OR', 'val3'), 'AND', 'val1'), $gate1);
		
		#$rv[] = array(array(array('val2', 'AND', 'val3'), 'AND', 'val1'), $gate1);
		#$rv[] = array(array(array('val2', 'AND', 'val3'), 'OR', 'val1'), $gate1);
		
		
		#$rv[] = array(array(array('val1', 'AND', 'val2'), 'AND', array('val3', 'AND', 'val4')), $gate1);
		#$rv[] = array(array(array('val1', 'AND', 'val2'), 'AND', array('val3', 'OR', 'val4')), $gate1);
		#$rv[] = array(array(array('val1', 'AND', 'val2'), 'OR', array('val3', 'AND', 'val4')), $gate1);
		#$rv[] = array(array(array('val1', 'AND', 'val2'), 'OR', array('val3', 'OR', 'val4')), $gate1);
		#$rv[] = array(array(array('val1', 'OR', 'val2'), 'AND', array('val3', 'AND', 'val4')), $gate1);
		#$rv[] = array(array(array('val1', 'OR', 'val2'), 'AND', array('val3', 'OR', 'val4')), $gate1);
		#$rv[] = array(array(array('val1', 'OR', 'val2'), 'OR', array('val3', 'AND', 'val4')), $gate1);
		#$rv[] = array(array(array('val1', 'OR', 'val2'), 'OR', array('val3', 'OR', 'val4')), $gate1);
		
		
		#$rv[] = array(array(array('UNDELETED', 'FROM', 'thefox'), 'OR', 'ANSWERED', 'AND', 'NOT', 'FROM', '21'), $gate1);
		
		return $rv;
	}
	
	/**
     * @dataProvider providerCriteriaTree
     */
	public function testCriteriaTree($testData, $expect){
		$tree = new CriteriaTree($testData);
		$obj = $tree->build();
		
		fwrite(STDOUT, 'obj'."\n");
		ve($obj);
		
		$this->assertEquals($expect, $obj);
	}
	
}
