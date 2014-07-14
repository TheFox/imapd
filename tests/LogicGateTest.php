<?php

use TheFox\Logic\Obj;
use TheFox\Logic\AndGate;
use TheFox\Logic\OrGate;
use TheFox\Logic\NotGate;

class LogicGateTest extends PHPUnit_Framework_TestCase{
	
	public function testAnd1(){
		$gate = new AndGate();
		$this->assertFalse($gate->bool());
		
		$gate = new AndGate();
		#$gate->setObj1(new Obj(true));
		$gate->setObj2(new Obj(true));
		$this->assertFalse($gate->bool());
		
		$gate = new AndGate();
		$gate->setObj1(new Obj(true));
		#$gate->setObj2(new Obj(true));
		$this->assertFalse($gate->bool());
		
		
		$gate = new AndGate();
		$gate->setObj1(new Obj(false));
		$gate->setObj2(new Obj(false));
		$this->assertFalse($gate->bool());
		
		$gate = new AndGate();
		$gate->setObj1(new Obj(false));
		$gate->setObj2(new Obj(true));
		$this->assertFalse($gate->bool());
		
		$gate = new AndGate();
		$gate->setObj1(new Obj(true));
		$gate->setObj2(new Obj(false));
		$this->assertFalse($gate->bool());
		
		$gate = new AndGate();
		$gate->setObj1(new Obj(true));
		$gate->setObj2(new Obj(true));
		$this->assertTrue($gate->bool());
	}
	
	public function testAnd2(){
		$gate3 = new AndGate();
		$gate3->setObj1(new Obj(true));
		$gate3->setObj2(new Obj(true));
		
		$gate2 = new AndGate();
		$gate2->setObj1(new Obj(true));
		$gate2->setObj2($gate3);
		
		$gate1 = new AndGate();
		$gate1->setObj1(new Obj(true));
		$gate1->setObj2($gate2);
		
		$this->assertTrue($gate1->bool());
	}
	
	public function testOr(){
		$gate = new OrGate();
		#$gate->setObj1(new Obj(false));
		$gate->setObj2(new Obj(false));
		$this->assertFalse($gate->bool());
		
		$gate = new OrGate();
		$gate->setObj1(new Obj(false));
		#$gate->setObj2(new Obj(false));
		$this->assertFalse($gate->bool());
		
		$gate = new OrGate();
		#$gate->setObj1(new Obj(true));
		$gate->setObj2(new Obj(true));
		$this->assertTrue($gate->bool());
		
		$gate = new OrGate();
		$gate->setObj1(new Obj(true));
		#$gate->setObj2(new Obj(true));
		$this->assertTrue($gate->bool());
		
		
		$gate = new OrGate();
		$gate->setObj1(new Obj(false));
		$gate->setObj2(new Obj(false));
		$this->assertFalse($gate->bool());
		
		$gate = new OrGate();
		$gate->setObj1(new Obj(false));
		$gate->setObj2(new Obj(true));
		$this->assertTrue($gate->bool());
		
		$gate = new OrGate();
		$gate->setObj1(new Obj(true));
		$gate->setObj2(new Obj(false));
		$this->assertTrue($gate->bool());
		
		$gate = new OrGate();
		$gate->setObj1(new Obj(true));
		$gate->setObj2(new Obj(true));
		$this->assertTrue($gate->bool());
	}
	
	public function testNot(){
		$gate = new NotGate();
		$this->assertTrue($gate->bool());
		
		$gate = new NotGate();
		$gate->setObj(new Obj(true));
		$this->assertFalse($gate->bool());
		
		$gate = new NotGate();
		$gate->setObj(new Obj(false));
		$this->assertTrue($gate->bool());
		
		$gate = new NotGate(new Obj(true));
		$this->assertFalse($gate->bool());
		
		$gate = new NotGate(new Obj(false));
		$this->assertTrue($gate->bool());
	}
	
	public function testAll1(){
		$gate2 = new OrGate();
		$gate3 = new OrGate();
		$gate1 = new AndGate();
		$gate1->setObj1($gate2);
		$gate1->setObj2($gate3);
		
		$this->assertFalse($gate1->bool());
		
		$gate2->setObj1(new Obj(false));
		$gate2->setObj2(new Obj(false));
		$gate3->setObj1(new Obj(false));
		$gate3->setObj2(new Obj(false));
		$this->assertFalse($gate1->bool());
		
		$gate2->setObj1(new Obj(true));
		$gate2->setObj2(new Obj(false));
		$gate3->setObj1(new Obj(false));
		$gate3->setObj2(new Obj(false));
		$this->assertFalse($gate1->bool());
		
		$gate2->setObj1(new Obj(false));
		$gate2->setObj2(new Obj(true));
		$gate3->setObj1(new Obj(false));
		$gate3->setObj2(new Obj(false));
		$this->assertFalse($gate1->bool());
		
		$gate2->setObj1(new Obj(true));
		$gate2->setObj2(new Obj(false));
		$gate3->setObj1(new Obj(true));
		$gate3->setObj2(new Obj(false));
		$this->assertTrue($gate1->bool());
		
		$gate2->setObj1(new Obj(true));
		$gate2->setObj2(new Obj(false));
		$gate3->setObj1(new Obj(false));
		$gate3->setObj2(new Obj(true));
		$this->assertTrue($gate1->bool());
	}
	
	public function testAll2(){
		$gate2 = new NotGate();
		$gate3 = new NotGate();
		$gate1 = new AndGate();
		$gate1->setObj1($gate2);
		$gate1->setObj2($gate3);
		
		$this->assertTrue($gate1->bool());
		
		$gate2->setObj(new Obj(false));
		$gate3->setObj(new Obj(false));
		$this->assertTrue($gate1->bool());
		
		$gate2->setObj(new Obj(true));
		$gate3->setObj(new Obj(false));
		$this->assertFalse($gate1->bool());
		
		$gate2->setObj(new Obj(false));
		$gate3->setObj(new Obj(true));
		$this->assertFalse($gate1->bool());
		
		$gate2->setObj(new Obj(true));
		$gate3->setObj(new Obj(true));
		$this->assertFalse($gate1->bool());
	}
	
	public function testAll3(){
		$gate2 = new AndGate();
		$gate1 = new OrGate();
		$gate1->setObj2($gate2);
		
		$gate1->setObj1(new Obj(1));
		$gate2->setObj1(new Obj(1));
		$gate2->setObj2(new Obj(1));
		$this->assertTrue($gate1->bool());
		
		$gate1->setObj1(new Obj(1));
		$gate2->setObj1(new Obj(1));
		$gate2->setObj2(new Obj(0));
		$this->assertTrue($gate1->bool());
		
		$gate1->setObj1(new Obj(1));
		$gate2->setObj1(new Obj(0));
		$gate2->setObj2(new Obj(1));
		$this->assertTrue($gate1->bool());
		
		$gate1->setObj1(new Obj(1));
		$gate2->setObj1(new Obj(0));
		$gate2->setObj2(new Obj(0));
		$this->assertTrue($gate1->bool());
		
		$gate1->setObj1(new Obj(0));
		$gate2->setObj1(new Obj(1));
		$gate2->setObj2(new Obj(1));
		$this->assertTrue($gate1->bool());
		
		$gate1->setObj1(new Obj(0));
		$gate2->setObj1(new Obj(1));
		$gate2->setObj2(new Obj(0));
		$this->assertFalse($gate1->bool());
		
		$gate1->setObj1(new Obj(0));
		$gate2->setObj1(new Obj(0));
		$gate2->setObj2(new Obj(1));
		$this->assertFalse($gate1->bool());
		
		$gate1->setObj1(new Obj(0));
		$gate2->setObj1(new Obj(0));
		$gate2->setObj2(new Obj(0));
		$this->assertFalse($gate1->bool());
	}
	
	public function testAll4(){
		$gate2 = new AndGate();
		$gate1 = new OrGate();
		$gate1->setObj1($gate2);
		
		$gate2->setObj1(new Obj(1));
		$gate2->setObj2(new Obj(1));
		$gate1->setObj2(new Obj(1));
		$this->assertTrue($gate1->bool());
		
		$gate2->setObj1(new Obj(1));
		$gate2->setObj2(new Obj(1));
		$gate1->setObj2(new Obj(0));
		$this->assertTrue($gate1->bool());
		
		$gate2->setObj1(new Obj(1));
		$gate2->setObj2(new Obj(0));
		$gate1->setObj2(new Obj(1));
		$this->assertTrue($gate1->bool());
		
		$gate2->setObj1(new Obj(1));
		$gate2->setObj2(new Obj(0));
		$gate1->setObj2(new Obj(0));
		$this->assertFalse($gate1->bool());
		
		$gate2->setObj1(new Obj(0));
		$gate2->setObj2(new Obj(1));
		$gate1->setObj2(new Obj(1));
		$this->assertTrue($gate1->bool());
		
		$gate2->setObj1(new Obj(0));
		$gate2->setObj2(new Obj(1));
		$gate1->setObj2(new Obj(0));
		$this->assertFalse($gate1->bool());
		
		$gate2->setObj1(new Obj(0));
		$gate2->setObj2(new Obj(0));
		$gate1->setObj2(new Obj(1));
		$this->assertTrue($gate1->bool());
		
		$gate2->setObj1(new Obj(0));
		$gate2->setObj2(new Obj(0));
		$gate1->setObj2(new Obj(0));
		$this->assertFalse($gate1->bool());
	}
	
}
