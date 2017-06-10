<?php

namespace TheFox\Test;

use PHPUnit\Framework\TestCase;
use TheFox\Logic\Obj;
use TheFox\Logic\Gate;
use TheFox\Logic\AndGate;
use TheFox\Logic\OrGate;
use TheFox\Logic\NotGate;

class LogicGateTest extends TestCase
{
    public function testObj()
    {
        $gate1 = new Obj('val1');
        $gate2 = new Obj($gate1);
        $gate3 = clone $gate2;

        $this->assertEquals('val1', $gate1->getValue());
        $this->assertTrue(is_object($gate3));
    }

    public function testGate()
    {
        $gate1 = new Obj('val1');
        $gate2 = new Obj('val2');

        $gate3 = new Gate();
        $gate3->setObj1($gate1);
        $gate3->setObj2($gate2);

        $this->assertEquals($gate1, $gate3->getObj1());
        $this->assertEquals($gate2, $gate3->getObj2());
        $this->assertEquals(null, $gate3->getBool());

        $gate4 = clone $gate3;
        $this->assertEquals('val1', $gate4->getObj1()->getValue());
        $this->assertEquals('val2', $gate4->getObj2()->getValue());
    }

    public function testAnd1()
    {
        $gate = new AndGate();
        $this->assertFalse($gate->getBool());

        $gate = new AndGate();
        #$gate->setObj1(new Obj(true));
        $gate->setObj2(new Obj(true));
        $this->assertFalse($gate->getBool());

        $gate = new AndGate();
        $gate->setObj1(new Obj(true));
        #$gate->setObj2(new Obj(true));
        $this->assertFalse($gate->getBool());


        $gate = new AndGate();
        $gate->setObj1(new Obj(false));
        $gate->setObj2(new Obj(false));
        $this->assertFalse($gate->getBool());

        $gate = new AndGate();
        $gate->setObj1(new Obj(false));
        $gate->setObj2(new Obj(true));
        $this->assertFalse($gate->getBool());

        $gate = new AndGate();
        $gate->setObj1(new Obj(true));
        $gate->setObj2(new Obj(false));
        $this->assertFalse($gate->getBool());

        $gate = new AndGate();
        $gate->setObj1(new Obj(true));
        $gate->setObj2(new Obj(true));
        $this->assertTrue($gate->getBool());
    }

    public function testAnd2()
    {
        $gate3 = new AndGate();
        $gate3->setObj1(new Obj(true));
        $gate3->setObj2(new Obj(true));

        $gate2 = new AndGate();
        $gate2->setObj1(new Obj(true));
        $gate2->setObj2($gate3);

        $gate1 = new AndGate();
        $gate1->setObj1(new Obj(true));
        $gate1->setObj2($gate2);

        $this->assertTrue($gate1->getBool());
    }

    public function testOr()
    {
        $gate = new OrGate();
        #$gate->setObj1(new Obj(false));
        $gate->setObj2(new Obj(false));
        $this->assertFalse($gate->getBool());

        $gate = new OrGate();
        $gate->setObj1(new Obj(false));
        #$gate->setObj2(new Obj(false));
        $this->assertFalse($gate->getBool());

        $gate = new OrGate();
        #$gate->setObj1(new Obj(true));
        $gate->setObj2(new Obj(true));
        $this->assertTrue($gate->getBool());

        $gate = new OrGate();
        $gate->setObj1(new Obj(true));
        #$gate->setObj2(new Obj(true));
        $this->assertTrue($gate->getBool());


        $gate = new OrGate();
        $gate->setObj1(new Obj(false));
        $gate->setObj2(new Obj(false));
        $this->assertFalse($gate->getBool());

        $gate = new OrGate();
        $gate->setObj1(new Obj(false));
        $gate->setObj2(new Obj(true));
        $this->assertTrue($gate->getBool());

        $gate = new OrGate();
        $gate->setObj1(new Obj(true));
        $gate->setObj2(new Obj(false));
        $this->assertTrue($gate->getBool());

        $gate = new OrGate();
        $gate->setObj1(new Obj(true));
        $gate->setObj2(new Obj(true));
        $this->assertTrue($gate->getBool());
    }

    public function testNot()
    {
        $gate = new NotGate();
        $this->assertTrue($gate->getBool());

        $gate = new NotGate();
        $gate->setObj(new Obj(true));
        $this->assertFalse($gate->getBool());

        $gate = new NotGate();
        $gate->setObj(new Obj(false));
        $this->assertTrue($gate->getBool());

        $gate = new NotGate(new Obj(true));
        $this->assertFalse($gate->getBool());

        $gate = new NotGate(new Obj(false));
        $this->assertTrue($gate->getBool());
    }

    public function testAll1()
    {
        $gate2 = new OrGate();
        $gate3 = new OrGate();
        $gate1 = new AndGate();
        $gate1->setObj1($gate2);
        $gate1->setObj2($gate3);

        $this->assertFalse($gate1->getBool());

        $gate2->setObj1(new Obj(false));
        $gate2->setObj2(new Obj(false));
        $gate3->setObj1(new Obj(false));
        $gate3->setObj2(new Obj(false));
        $this->assertFalse($gate1->getBool());

        $gate2->setObj1(new Obj(true));
        $gate2->setObj2(new Obj(false));
        $gate3->setObj1(new Obj(false));
        $gate3->setObj2(new Obj(false));
        $this->assertFalse($gate1->getBool());

        $gate2->setObj1(new Obj(false));
        $gate2->setObj2(new Obj(true));
        $gate3->setObj1(new Obj(false));
        $gate3->setObj2(new Obj(false));
        $this->assertFalse($gate1->getBool());

        $gate2->setObj1(new Obj(true));
        $gate2->setObj2(new Obj(false));
        $gate3->setObj1(new Obj(true));
        $gate3->setObj2(new Obj(false));
        $this->assertTrue($gate1->getBool());

        $gate2->setObj1(new Obj(true));
        $gate2->setObj2(new Obj(false));
        $gate3->setObj1(new Obj(false));
        $gate3->setObj2(new Obj(true));
        $this->assertTrue($gate1->getBool());
    }

    public function testAll2()
    {
        $gate2 = new NotGate();
        $gate3 = new NotGate();
        $gate1 = new AndGate();
        $gate1->setObj1($gate2);
        $gate1->setObj2($gate3);

        $this->assertTrue($gate1->getBool());

        $gate2->setObj(new Obj(false));
        $gate3->setObj(new Obj(false));
        $this->assertTrue($gate1->getBool());

        $gate2->setObj(new Obj(true));
        $gate3->setObj(new Obj(false));
        $this->assertFalse($gate1->getBool());

        $gate2->setObj(new Obj(false));
        $gate3->setObj(new Obj(true));
        $this->assertFalse($gate1->getBool());

        $gate2->setObj(new Obj(true));
        $gate3->setObj(new Obj(true));
        $this->assertFalse($gate1->getBool());
    }

    public function testAll3()
    {
        $gate2 = new AndGate();
        $gate1 = new OrGate();
        $gate1->setObj2($gate2);

        $gate1->setObj1(new Obj(1));
        $gate2->setObj1(new Obj(1));
        $gate2->setObj2(new Obj(1));
        $this->assertTrue($gate1->getBool());

        $gate1->setObj1(new Obj(1));
        $gate2->setObj1(new Obj(1));
        $gate2->setObj2(new Obj(0));
        $this->assertTrue($gate1->getBool());

        $gate1->setObj1(new Obj(1));
        $gate2->setObj1(new Obj(0));
        $gate2->setObj2(new Obj(1));
        $this->assertTrue($gate1->getBool());

        $gate1->setObj1(new Obj(1));
        $gate2->setObj1(new Obj(0));
        $gate2->setObj2(new Obj(0));
        $this->assertTrue($gate1->getBool());

        $gate1->setObj1(new Obj(0));
        $gate2->setObj1(new Obj(1));
        $gate2->setObj2(new Obj(1));
        $this->assertTrue($gate1->getBool());

        $gate1->setObj1(new Obj(0));
        $gate2->setObj1(new Obj(1));
        $gate2->setObj2(new Obj(0));
        $this->assertFalse($gate1->getBool());

        $gate1->setObj1(new Obj(0));
        $gate2->setObj1(new Obj(0));
        $gate2->setObj2(new Obj(1));
        $this->assertFalse($gate1->getBool());

        $gate1->setObj1(new Obj(0));
        $gate2->setObj1(new Obj(0));
        $gate2->setObj2(new Obj(0));
        $this->assertFalse($gate1->getBool());
    }

    public function testAll4()
    {
        $gate2 = new AndGate();
        $gate1 = new OrGate();
        $gate1->setObj1($gate2);

        $gate2->setObj1(new Obj(1));
        $gate2->setObj2(new Obj(1));
        $gate1->setObj2(new Obj(1));
        $this->assertTrue($gate1->getBool());

        $gate2->setObj1(new Obj(1));
        $gate2->setObj2(new Obj(1));
        $gate1->setObj2(new Obj(0));
        $this->assertTrue($gate1->getBool());

        $gate2->setObj1(new Obj(1));
        $gate2->setObj2(new Obj(0));
        $gate1->setObj2(new Obj(1));
        $this->assertTrue($gate1->getBool());

        $gate2->setObj1(new Obj(1));
        $gate2->setObj2(new Obj(0));
        $gate1->setObj2(new Obj(0));
        $this->assertFalse($gate1->getBool());

        $gate2->setObj1(new Obj(0));
        $gate2->setObj2(new Obj(1));
        $gate1->setObj2(new Obj(1));
        $this->assertTrue($gate1->getBool());

        $gate2->setObj1(new Obj(0));
        $gate2->setObj2(new Obj(1));
        $gate1->setObj2(new Obj(0));
        $this->assertFalse($gate1->getBool());

        $gate2->setObj1(new Obj(0));
        $gate2->setObj2(new Obj(0));
        $gate1->setObj2(new Obj(1));
        $this->assertTrue($gate1->getBool());

        $gate2->setObj1(new Obj(0));
        $gate2->setObj2(new Obj(0));
        $gate1->setObj2(new Obj(0));
        $this->assertFalse($gate1->getBool());
    }
}
