<?php

use TheFox\Imap\Client;

class ClientTest extends PHPUnit_Framework_TestCase{
	
	public function providerMsgRaw(){
		$rv = array();
		$expect = array('tag' => 'TAG1', 'command' => 'cmd2', 'args' => array());
		
		$expect['args'] = array('arg1');
		$rv[] = array('TAG1 cmd2 "arg1"', $expect);
		
		$expect['args'] = array('arg1', 'arg2');
		$rv[] = array('TAG1 cmd2 "arg1" "arg2"', $expect);
		$rv[] = array('TAG1 cmd2 "arg1"  "arg2"', $expect);
		
		$expect['args'] = array('arg1', '');
		$rv[] = array('TAG1 cmd2 "arg1" ""', $expect);
		
		$expect['args'] = array('arg1', '', 'arg3');
		$rv[] = array('TAG1 cmd2 "arg1" "" arg3', $expect);
		
		$expect['args'] = array('arg1', 'arg2');
		$rv[] = array('TAG1 cmd2 arg1 arg2', $expect);
		$rv[] = array('TAG1 cmd2 arg1  arg2', $expect);
		$rv[] = array('TAG1 cmd2  arg1 arg2', $expect);
		$rv[] = array('TAG1  cmd2 arg1 arg2', $expect);
		
		$expect['args'] = array('arg1', 'arg21 still arg22', 'aaarrg3');
		$rv[] =   array('TAG1 cmd2 arg1 "arg21 still arg22" aaarrg3', $expect);
		$rv[] =   array('TAG1 cmd2 arg1 "arg21 still arg22"  aaarrg3', $expect);
		$rv[] =   array('TAG1 cmd2 arg1 "arg21 still arg22"  aaarrg3', $expect);
		$rv[] =   array('TAG1 cmd2 arg1 "arg21 still arg22"   aaarrg3', $expect);
		
		$expect['args'] = array('arg1', 'arg21 still arg22 ', 'aaarrg3');
		$rv[] =   array('TAG1 cmd2 arg1 "arg21 still arg22 " aaarrg3', $expect);
		$rv[] =  array('TAG1 cmd2 arg1  "arg21 still arg22 " aaarrg3', $expect);
		
		$expect['args'] = array('arg1', 'arg21 still  arg22', 'aaarrg3');
		$rv[] =   array('TAG1 cmd2 arg1 "arg21 still  arg22" aaarrg3', $expect);
		$rv[] =   array('TAG1 cmd2 arg1 "arg21 still  arg22"  aaarrg3', $expect);
		$rv[] =   array('TAG1 cmd2 arg1 "arg21 still  arg22"   aaarrg3', $expect);
		
		$expect['args'] = array('arg1', ' arg21 still arg22', 'aaarrg3');
		$rv[] =   array('TAG1 cmd2 arg1 " arg21 still arg22" aaarrg3', $expect);
		
		$expect['args'] = array('arg1', ' arg21 still arg22 ', 'aaarrg3');
		$rv[] =   array('TAG1 cmd2 arg1 " arg21 still arg22 " aaarrg3', $expect);
		
		return $rv;
	}
	
	/**
     * @dataProvider providerMsgRaw
     */
	public function testMsgGetArgs($msgRaw, $expect){
		$client = new Client();
		$this->assertEquals($expect, $client->msgGetArgs($msgRaw));
	}
	
}
