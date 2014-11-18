<?php

use TheFox\Logic\CriteriaTree;
use TheFox\Logic\Obj;
use TheFox\Logic\Gate;
use TheFox\Logic\AndGate;
use TheFox\Logic\OrGate;
use TheFox\Logic\NotGate;

class CriteriaTreeTest extends PHPUnit_Framework_TestCase{
	
	public function providerCriteriaBool(){
		$rv = array();
		
		$rv[] = array(array('0'), 0);
		$rv[] = array(array('1'), 1);
		
		$rv[] = array(array('0', 'OR', '0'), 0 || 0);
		$rv[] = array(array('0', 'OR', '1'), 0 || 1);
		$rv[] = array(array('1', 'OR', '0'), 1 || 0);
		$rv[] = array(array('1', 'OR', '1'), 1 || 1);
		
		$rv[] = array(array('0', 'AND', '0'), 0 && 0);
		$rv[] = array(array('0', 'AND', '1'), 0 && 1);
		$rv[] = array(array('1', 'AND', '0'), 1 && 0);
		$rv[] = array(array('1', 'AND', '1'), 1 && 1);
		
		
		
		$rv[] = array(array('0', 'OR', '0', 'OR', '0'), 0 || 0 || 0);
		$rv[] = array(array('0', 'OR', '0', 'OR', '1'), 0 || 0 || 1);
		$rv[] = array(array('0', 'OR', '1', 'OR', '0'), 0 || 1 || 0);
		$rv[] = array(array('0', 'OR', '1', 'OR', '1'), 0 || 1 || 1);
		$rv[] = array(array('1', 'OR', '0', 'OR', '0'), 1 || 0 || 0);
		$rv[] = array(array('1', 'OR', '0', 'OR', '1'), 1 || 0 || 1);
		$rv[] = array(array('1', 'OR', '1', 'OR', '0'), 1 || 1 || 0);
		$rv[] = array(array('1', 'OR', '1', 'OR', '1'), 1 || 1 || 1);
		
		$rv[] = array(array('0', 'OR', array('0', 'OR', '0')), 0 || (0 || 0));
		$rv[] = array(array('0', 'OR', array('0', 'OR', '1')), 0 || (0 || 1));
		$rv[] = array(array('0', 'OR', array('1', 'OR', '0')), 0 || (1 || 0));
		$rv[] = array(array('0', 'OR', array('1', 'OR', '1')), 0 || (1 || 1));
		$rv[] = array(array('1', 'OR', array('0', 'OR', '0')), 1 || (0 || 0));
		$rv[] = array(array('1', 'OR', array('0', 'OR', '1')), 1 || (0 || 1));
		$rv[] = array(array('1', 'OR', array('1', 'OR', '0')), 1 || (1 || 0));
		$rv[] = array(array('1', 'OR', array('1', 'OR', '1')), 1 || (1 || 1));
		
		$rv[] = array(array(array('0', 'OR', '0'), 'OR', '0'), (0 || 0) || 0);
		$rv[] = array(array(array('0', 'OR', '0'), 'OR', '1'), (0 || 0) || 1);
		$rv[] = array(array(array('0', 'OR', '1'), 'OR', '0'), (0 || 1) || 0);
		$rv[] = array(array(array('0', 'OR', '1'), 'OR', '1'), (0 || 1) || 1);
		$rv[] = array(array(array('1', 'OR', '0'), 'OR', '0'), (1 || 0) || 0);
		$rv[] = array(array(array('1', 'OR', '0'), 'OR', '1'), (1 || 0) || 1);
		$rv[] = array(array(array('1', 'OR', '1'), 'OR', '0'), (1 || 1) || 0);
		$rv[] = array(array(array('1', 'OR', '1'), 'OR', '1'), (1 || 1) || 1);
		
		$rv[] = array(array('0', 'AND', '0', 'AND', '0'), 0 && 0 && 0);
		$rv[] = array(array('0', 'AND', '0', 'AND', '1'), 0 && 0 && 1);
		$rv[] = array(array('0', 'AND', '1', 'AND', '0'), 0 && 1 && 0);
		$rv[] = array(array('0', 'AND', '1', 'AND', '1'), 0 && 1 && 1);
		$rv[] = array(array('1', 'AND', '0', 'AND', '0'), 1 && 0 && 0);
		$rv[] = array(array('1', 'AND', '0', 'AND', '1'), 1 && 0 && 1);
		$rv[] = array(array('1', 'AND', '1', 'AND', '0'), 1 && 1 && 0);
		$rv[] = array(array('1', 'AND', '1', 'AND', '1'), 1 && 1 && 1);
		
		$rv[] = array(array('0', 'AND', array('0', 'AND', '0')), 0 && (0 && 0));
		$rv[] = array(array('0', 'AND', array('0', 'AND', '1')), 0 && (0 && 1));
		$rv[] = array(array('0', 'AND', array('1', 'AND', '0')), 0 && (1 && 0));
		$rv[] = array(array('0', 'AND', array('1', 'AND', '1')), 0 && (1 && 1));
		$rv[] = array(array('1', 'AND', array('0', 'AND', '0')), 1 && (0 && 0));
		$rv[] = array(array('1', 'AND', array('0', 'AND', '1')), 1 && (0 && 1));
		$rv[] = array(array('1', 'AND', array('1', 'AND', '0')), 1 && (1 && 0));
		$rv[] = array(array('1', 'AND', array('1', 'AND', '1')), 1 && (1 && 1));
		
		$rv[] = array(array(array('0', 'AND', '0'), 'AND', '0'), (0 && 0) && 0);
		$rv[] = array(array(array('0', 'AND', '0'), 'AND', '1'), (0 && 0) && 1);
		$rv[] = array(array(array('0', 'AND', '1'), 'AND', '0'), (0 && 1) && 0);
		$rv[] = array(array(array('0', 'AND', '1'), 'AND', '1'), (0 && 1) && 1);
		$rv[] = array(array(array('1', 'AND', '0'), 'AND', '0'), (1 && 0) && 0);
		$rv[] = array(array(array('1', 'AND', '0'), 'AND', '1'), (1 && 0) && 1);
		$rv[] = array(array(array('1', 'AND', '1'), 'AND', '0'), (1 && 1) && 0);
		$rv[] = array(array(array('1', 'AND', '1'), 'AND', '1'), (1 && 1) && 1);
		
		
		
		$rv[] = array(array('0', 'AND', '0', 'AND', '0'), 0 && 0 && 0);
		$rv[] = array(array('0', 'AND', '0', 'OR', '0'), 0 && 0 || 0);
		$rv[] = array(array('0', 'OR', '0', 'AND', '0'), 0 || 0 && 0);
		$rv[] = array(array('0', 'OR', '0', 'OR', '0'), 0 || 0 || 0);
		
		$rv[] = array(array('0', 'AND', '0', 'AND', '1'), 0 && 0 && 1);
		$rv[] = array(array('0', 'AND', '0', 'OR', '1'), 0 && 0 || 1);
		$rv[] = array(array('0', 'OR', '0', 'AND', '1'), 0 || 0 && 1);
		$rv[] = array(array('0', 'OR', '0', 'OR', '1'), 0 || 0 || 1);
		
		$rv[] = array(array('0', 'AND', '1', 'AND', '0'), 0 && 1 && 0);
		$rv[] = array(array('0', 'AND', '1', 'OR', '0'), 0 && 1 || 0);
		$rv[] = array(array('0', 'OR', '1', 'AND', '0'), 0 || 1 && 0);
		$rv[] = array(array('0', 'OR', '1', 'OR', '0'), 0 || 1 || 0);
		
		$rv[] = array(array('0', 'AND', '1', 'AND', '1'), 0 && 1 && 1);
		$rv[] = array(array('0', 'AND', '1', 'OR', '1'), 0 && 1 || 1);
		$rv[] = array(array('0', 'OR', '1', 'AND', '1'), 0 || 1 && 1);
		$rv[] = array(array('0', 'OR', '1', 'OR', '1'), 0 || 1 || 1);
		
		$rv[] = array(array('1', 'AND', '0', 'AND', '0'), 1 && 0 && 0);
		$rv[] = array(array('1', 'AND', '0', 'OR', '0'), 1 && 0 || 0);
		$rv[] = array(array('1', 'OR', '0', 'AND', '0'), 1 || 0 && 0);
		$rv[] = array(array('1', 'OR', '0', 'OR', '0'), 1 || 0 || 0);
		
		$rv[] = array(array('1', 'AND', '0', 'AND', '1'), 1 && 0 && 1);
		$rv[] = array(array('1', 'AND', '0', 'OR', '1'), 1 && 0 || 1);
		$rv[] = array(array('1', 'OR', '0', 'AND', '1'), 1 || 0 && 1);
		$rv[] = array(array('1', 'OR', '0', 'OR', '1'), 1 || 0 || 1);
		
		$rv[] = array(array('1', 'AND', '1', 'AND', '0'), 1 && 1 && 0);
		$rv[] = array(array('1', 'AND', '1', 'OR', '0'), 1 && 1 || 0);
		$rv[] = array(array('1', 'OR', '1', 'AND', '0'), 1 || 1 && 0);
		$rv[] = array(array('1', 'OR', '1', 'OR', '0'), 1 || 1 || 0);
		
		$rv[] = array(array('1', 'AND', '1', 'AND', '1'), 1 && 1 && 1);
		$rv[] = array(array('1', 'AND', '1', 'OR', '1'), 1 && 1 || 1);
		$rv[] = array(array('1', 'OR', '1', 'AND', '1'), 1 || 1 && 1);
		$rv[] = array(array('1', 'OR', '1', 'OR', '1'), 1 || 1 || 1);
		
		///
		$rv[] = array(array('0', 'AND', array('0', 'AND', '0')), 0 && (0 && 0));
		$rv[] = array(array('0', 'AND', array('0', 'OR', '0')), 0 && (0 || 0));
		$rv[] = array(array('0', 'OR', array('0', 'AND', '0')), 0 || (0 && 0));
		$rv[] = array(array('0', 'OR', array('0', 'OR', '0')), 0 || (0 || 0));
		
		$rv[] = array(array('0', 'AND', array('0', 'AND', '1')), 0 && (0 && 1));
		$rv[] = array(array('0', 'AND', array('0', 'OR', '1')), 0 && (0 || 1));
		$rv[] = array(array('0', 'OR', array('0', 'AND', '1')), 0 || (0 && 1));
		$rv[] = array(array('0', 'OR', array('0', 'OR', '1')), 0 || (0 || 1));
		
		$rv[] = array(array('0', 'AND', array('1', 'AND', '0')), 0 && (1 && 0));
		$rv[] = array(array('0', 'AND', array('1', 'OR', '0')), 0 && (1 || 0));
		$rv[] = array(array('0', 'OR', array('1', 'AND', '0')), 0 || (1 && 0));
		$rv[] = array(array('0', 'OR', array('1', 'OR', '0')), 0 || (1 || 0));
		
		$rv[] = array(array('0', 'AND', array('1', 'AND', '1')), 0 && (1 && 1));
		$rv[] = array(array('0', 'AND', array('1', 'OR', '1')), 0 && (1 || 1));
		$rv[] = array(array('0', 'OR', array('1', 'AND', '1')), 0 || (1 && 1));
		$rv[] = array(array('0', 'OR', array('1', 'OR', '1')), 0 || (1 || 1));
		
		$rv[] = array(array('1', 'AND', array('0', 'AND', '0')), 1 && (0 && 0));
		$rv[] = array(array('1', 'AND', array('0', 'OR', '0')), 1 && (0 || 0));
		$rv[] = array(array('1', 'OR', array('0', 'AND', '0')), 1 || (0 && 0));
		$rv[] = array(array('1', 'OR', array('0', 'OR', '0')), 1 || (0 || 0));
		
		$rv[] = array(array('1', 'AND', array('0', 'AND', '1')), 1 && (0 && 1));
		$rv[] = array(array('1', 'AND', array('0', 'OR', '1')), 1 && (0 || 1));
		$rv[] = array(array('1', 'OR', array('0', 'AND', '1')), 1 || (0 && 1));
		$rv[] = array(array('1', 'OR', array('0', 'OR', '1')), 1 || (0 || 1));
		
		$rv[] = array(array('1', 'AND', array('1', 'AND', '0')), 1 && (1 && 0));
		$rv[] = array(array('1', 'AND', array('1', 'OR', '0')), 1 && (1 || 0));
		$rv[] = array(array('1', 'OR', array('1', 'AND', '0')), 1 || (1 && 0));
		$rv[] = array(array('1', 'OR', array('1', 'OR', '0')), 1 || (1 || 0));
		
		$rv[] = array(array('1', 'AND', array('1', 'AND', '1')), 1 && (1 && 1));
		$rv[] = array(array('1', 'AND', array('1', 'OR', '1')), 1 && (1 || 1));
		$rv[] = array(array('1', 'OR', array('1', 'AND', '1')), 1 || (1 && 1));
		$rv[] = array(array('1', 'OR', array('1', 'OR', '1')), 1 || (1 || 1));
		
		///
		$rv[] = array(array(array('0', 'AND', '0'), 'AND', '0'), (0 && 0) && 0);
		$rv[] = array(array(array('0', 'AND', '0'), 'OR', '0'), (0 && 0) || 0);
		$rv[] = array(array(array('0', 'OR', '0'), 'AND', '0'), (0 || 0) && 0);
		$rv[] = array(array(array('0', 'OR', '0'), 'OR', '0'), (0 || 0) || 0);
		
		$rv[] = array(array(array('0', 'AND', '0'), 'AND', '1'), (0 && 0) && 1);
		$rv[] = array(array(array('0', 'AND', '0'), 'OR', '1'), (0 && 0) || 1);
		$rv[] = array(array(array('0', 'OR', '0'), 'AND', '1'), (0 || 0) && 1);
		$rv[] = array(array(array('0', 'OR', '0'), 'OR', '1'), (0 || 0) || 1);
		
		$rv[] = array(array(array('0', 'AND', '1'), 'AND', '0'), (0 && 1) && 0);
		$rv[] = array(array(array('0', 'AND', '1'), 'OR', '0'), (0 && 1) || 0);
		$rv[] = array(array(array('0', 'OR', '1'), 'AND', '0'), (0 || 1) && 0);
		$rv[] = array(array(array('0', 'OR', '1'), 'OR', '0'), (0 || 1) || 0);
		
		$rv[] = array(array(array('0', 'AND', '1'), 'AND', '1'), (0 && 1) && 1);
		$rv[] = array(array(array('0', 'AND', '1'), 'OR', '1'), (0 && 1) || 1);
		$rv[] = array(array(array('0', 'OR', '1'), 'AND', '1'), (0 || 1) && 1);
		$rv[] = array(array(array('0', 'OR', '1'), 'OR', '1'), (0 || 1) || 1);
		
		$rv[] = array(array(array('1', 'AND', '0'), 'AND', '0'), (1 && 0) && 0);
		$rv[] = array(array(array('1', 'AND', '0'), 'OR', '0'), (1 && 0) || 0);
		$rv[] = array(array(array('1', 'OR', '0'), 'AND', '0'), (1 || 0) && 0);
		$rv[] = array(array(array('1', 'OR', '0'), 'OR', '0'), (1 || 0) || 0);
		
		$rv[] = array(array(array('1', 'AND', '0'), 'AND', '1'), (1 && 0) && 1);
		$rv[] = array(array(array('1', 'AND', '0'), 'OR', '1'), (1 && 0) || 1);
		$rv[] = array(array(array('1', 'OR', '0'), 'AND', '1'), (1 || 0) && 1);
		$rv[] = array(array(array('1', 'OR', '0'), 'OR', '1'), (1 || 0) || 1);
		
		$rv[] = array(array(array('1', 'AND', '1'), 'AND', '0'), (1 && 1) && 0);
		$rv[] = array(array(array('1', 'AND', '1'), 'OR', '0'), (1 && 1) || 0);
		$rv[] = array(array(array('1', 'OR', '1'), 'AND', '0'), (1 || 1) && 0);
		$rv[] = array(array(array('1', 'OR', '1'), 'OR', '0'), (1 || 1) || 0);
		
		$rv[] = array(array(array('1', 'AND', '1'), 'AND', '1'), (1 && 1) && 1);
		$rv[] = array(array(array('1', 'AND', '1'), 'OR', '1'), (1 && 1) || 1);
		$rv[] = array(array(array('1', 'OR', '1'), 'AND', '1'), (1 || 1) && 1);
		$rv[] = array(array(array('1', 'OR', '1'), 'OR', '1'), (1 || 1) || 1);
		
		
		
		$rv[] = array(array('NOT', '0'), !0);
		$rv[] = array(array('NOT', '1'), !1);
		
		$rv[] = array(array('1', 'AND', 'NOT', '1'), 1 && !1);
		$rv[] = array(array('1', 'AND', 'NOT', '0'), 1 && !0);
		$rv[] = array(array('0', 'AND', 'NOT', '1'), 0 && !1);
		$rv[] = array(array('0', 'AND', 'NOT', '0'), 0 && !0);
		
		$rv[] = array(array('1', 'AND', array('NOT', '1')), 1 && (!1));
		$rv[] = array(array('1', 'AND', array('NOT', '0')), 1 && (!0));
		$rv[] = array(array('0', 'AND', array('NOT', '1')), 0 && (!1));
		$rv[] = array(array('0', 'AND', array('NOT', '0')), 0 && (!0));
		
		$rv[] = array(array('NOT', '1', 'AND', '1'), !1 && 1);
		$rv[] = array(array('NOT', '1', 'AND', '0'), !1 && 0);
		$rv[] = array(array('NOT', '0', 'AND', '1'), !0 && 1);
		$rv[] = array(array('NOT', '0', 'AND', '0'), !0 && 0);
		
		$rv[] = array(array(array('NOT', '1'), 'AND', '1'), (!1) && 1);
		$rv[] = array(array(array('NOT', '1'), 'AND', '0'), (!1) && 0);
		$rv[] = array(array(array('NOT', '0'), 'AND', '1'), (!0) && 1);
		$rv[] = array(array(array('NOT', '0'), 'AND', '0'), (!0) && 0);
		
		$rv[] = array(array('NOT', '1', 'AND', 'NOT', '1'), !1 && !1);
		$rv[] = array(array('NOT', '1', 'AND', 'NOT', '0'), !1 && !0);
		$rv[] = array(array('NOT', '0', 'AND', 'NOT', '1'), !0 && !1);
		$rv[] = array(array('NOT', '0', 'AND', 'NOT', '0'), !0 && !0);
		
		$rv[] = array(array('NOT', '1', 'AND', array('NOT', '1')), !1 && (!1));
		$rv[] = array(array('NOT', '1', 'AND', array('NOT', '0')), !1 && (!0));
		$rv[] = array(array('NOT', '0', 'AND', array('NOT', '1')), !0 && (!1));
		$rv[] = array(array('NOT', '0', 'AND', array('NOT', '0')), !0 && (!0));
		
		$rv[] = array(array(array('NOT', '1'), 'AND', 'NOT', '1'), (!1) && !1);
		$rv[] = array(array(array('NOT', '1'), 'AND', 'NOT', '0'), (!1) && !0);
		$rv[] = array(array(array('NOT', '0'), 'AND', 'NOT', '1'), (!0) && !1);
		$rv[] = array(array(array('NOT', '0'), 'AND', 'NOT', '0'), (!0) && !0);
		
		$rv[] = array(array(array('NOT', '1'), 'AND', array('NOT', '1')), (!1) && (!1));
		$rv[] = array(array(array('NOT', '1'), 'AND', array('NOT', '0')), (!1) && (!0));
		$rv[] = array(array(array('NOT', '0'), 'AND', array('NOT', '1')), (!0) && (!1));
		$rv[] = array(array(array('NOT', '0'), 'AND', array('NOT', '0')), (!0) && (!0));
		
		
		$rv[] = array(array('1', 'OR', 'NOT', '1'), 1 || !1);
		$rv[] = array(array('1', 'OR', 'NOT', '0'), 1 || !0);
		$rv[] = array(array('0', 'OR', 'NOT', '1'), 0 || !1);
		$rv[] = array(array('0', 'OR', 'NOT', '0'), 0 || !0);
		
		$rv[] = array(array('1', 'OR', array('NOT', '1')), 1 || (!1));
		$rv[] = array(array('1', 'OR', array('NOT', '0')), 1 || (!0));
		$rv[] = array(array('0', 'OR', array('NOT', '1')), 0 || (!1));
		$rv[] = array(array('0', 'OR', array('NOT', '0')), 0 || (!0));
		
		$rv[] = array(array('NOT', '1', 'OR', '1'), !1 || 1);
		$rv[] = array(array('NOT', '1', 'OR', '0'), !1 || 0);
		$rv[] = array(array('NOT', '0', 'OR', '1'), !0 || 1);
		$rv[] = array(array('NOT', '0', 'OR', '0'), !0 || 0);
		
		$rv[] = array(array(array('NOT', '1'), 'OR', '1'), (!1) || 1);
		$rv[] = array(array(array('NOT', '1'), 'OR', '0'), (!1) || 0);
		$rv[] = array(array(array('NOT', '0'), 'OR', '1'), (!0) || 1);
		$rv[] = array(array(array('NOT', '0'), 'OR', '0'), (!0) || 0);
		
		
		$rv[] = array(array('NOT', '1', 'OR', 'NOT', '1'), !1 || !1);
		$rv[] = array(array('NOT', '1', 'OR', 'NOT', '0'), !1 || !0);
		$rv[] = array(array('NOT', '0', 'OR', 'NOT', '1'), !0 || !1);
		$rv[] = array(array('NOT', '0', 'OR', 'NOT', '0'), !0 || !0);
		
		$rv[] = array(array('NOT', '1', 'OR', array('NOT', '1')), !1 || (!1));
		$rv[] = array(array('NOT', '1', 'OR', array('NOT', '0')), !1 || (!0));
		$rv[] = array(array('NOT', '0', 'OR', array('NOT', '1')), !0 || (!1));
		$rv[] = array(array('NOT', '0', 'OR', array('NOT', '0')), !0 || (!0));
		
		$rv[] = array(array(array('NOT', '1'), 'OR', 'NOT', '1'), (!1) || !1);
		$rv[] = array(array(array('NOT', '1'), 'OR', 'NOT', '0'), (!1) || !0);
		$rv[] = array(array(array('NOT', '0'), 'OR', 'NOT', '1'), (!0) || !1);
		$rv[] = array(array(array('NOT', '0'), 'OR', 'NOT', '0'), (!0) || !0);
		
		$rv[] = array(array(array('NOT', '1'), 'OR', array('NOT', '1')), (!1) || (!1));
		$rv[] = array(array(array('NOT', '1'), 'OR', array('NOT', '0')), (!1) || (!0));
		$rv[] = array(array(array('NOT', '0'), 'OR', array('NOT', '1')), (!0) || (!1));
		$rv[] = array(array(array('NOT', '0'), 'OR', array('NOT', '0')), (!0) || (!0));
		
		
		return $rv;
	}
	
	/**
     * @dataProvider providerCriteriaBool
     */
	public function testCriteriaBool($testData, $expect){
		$tree = new CriteriaTree($testData);
		$gate = $tree->build();
		
		#fwrite(STDOUT, 'gate'."\n"); ve($gate);
		
		$this->assertEquals($expect, $gate->bool());
		$this->assertEquals($expect, $tree->bool());
	}
	
	public function providerCriteriaTree(){
		$rv = array();
		
		$gate1 = new OrGate();
		$gate1->setObj1(new Obj('val1'));
		$gate1->setObj2(new Obj('val2'));
		$rv[] = array(array('val1', 'OR', 'val2'), $gate1);
		$rv[] = array(array(array('val1', 'OR', 'val2')), $gate1);
		
		$gate1 = new AndGate();
		$gate1->setObj1(new Obj('val1'));
		$gate1->setObj2(new Obj('val2'));
		$rv[] = array(array('val1', 'AND', 'val2'), $gate1);
		
		$gate2 = new AndGate();
		$gate2->setObj1(new Obj('val2'));
		$gate2->setObj2(new Obj('val3'));
		$gate1 = new OrGate();
		$gate1->setObj1(new Obj('val1'));
		$gate1->setObj2($gate2);
		$rv[] = array(array('val1', 'OR', 'val2', 'AND', 'val3'), $gate1);
		
		$gate2 = new AndGate();
		$gate2->setObj1(new Obj('val1'));
		$gate2->setObj2(new Obj('val2'));
		$gate1 = new OrGate();
		$gate1->setObj1($gate2);
		$gate1->setObj2(new Obj('val3'));
		$rv[] = array(array('val1', 'AND', 'val2', 'OR', 'val3'), $gate1);
		
		$gate2 = new OrGate();
		$gate2->setObj1(new Obj('val2'));
		$gate2->setObj2(new Obj('val3'));
		$gate1 = new AndGate();
		$gate1->setObj1(new Obj('val1'));
		$gate1->setObj2($gate2);
		$rv[] = array(array('val1', 'AND', array('val2', 'OR', 'val3')), $gate1);
		
		$gate2 = new AndGate();
		$gate2->setObj1(new Obj('UNDELETED'));
		$gate2->setObj2(new Obj('FROM thefox'));
		$gate4 = new NotGate(new Obj('FROM 21'));
		$gate3 = new AndGate();
		$gate3->setObj1(new Obj('ANSWERED'));
		$gate3->setObj2($gate4);
		$gate1 = new OrGate();
		$gate1->setObj1($gate2);
		$gate1->setObj2($gate3);
		$rv[] = array(array(array('UNDELETED', 'AND', 'FROM thefox'), 'OR', 'ANSWERED', 'AND', 'NOT', 'FROM 21'), $gate1);
		
		$gate1 = new OrGate();
		$gate1->setObj1(new Obj('val1'));
		$gate1->setObj2(new Obj('val2'));
		$gate2 = clone $gate1;
		$gate1->setObj1(new Obj('val3'));
		$gate1->setObj2(new Obj('val4'));
		$rv[] = array(array('val1', 'OR', 'val2'), $gate2);
		
		return $rv;
	}
	
	/**
     * @dataProvider providerCriteriaTree
     */
	public function testCriteriaTree($testData, $expect){
		$tree = new CriteriaTree($testData);
		$gate = $tree->build();
		
		#fwrite(STDOUT, 'gate'."\n"); ve($gate);
		
		$this->assertEquals($expect, $gate);
	}
	
}
