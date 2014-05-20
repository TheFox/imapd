<?php

use TheFox\Imap\Client;

class ClientTest extends PHPUnit_Framework_TestCase{
	
	public function providerMsgRaw(){
		return array(
			array('TAG1 cmd2 arg1 arg2',
				array('tag' => 'TAG1', 'command' => 'cmd2', 'args' => array('arg1', 'arg2'))),
			array('TAG1 cmd2 arg1  arg2',
				array('tag' => 'TAG1', 'command' => 'cmd2', 'args' => array('arg1', 'arg2'))),
			array('TAG1 cmd2  arg1 arg2',
				array('tag' => 'TAG1', 'command' => 'cmd2', 'args' => array('arg1', 'arg2'))),
			array('TAG1  cmd2 arg1 arg2',
				array('tag' => 'TAG1', 'command' => 'cmd2', 'args' => array('arg1', 'arg2'))),
			array('TAG1  cmd2  arg1 arg2',
				array('tag' => 'TAG1', 'command' => 'cmd2', 'args' => array('arg1', 'arg2'))),
			array('TAG1  cmd2  arg1  arg2',
				array('tag' => 'TAG1', 'command' => 'cmd2', 'args' => array('arg1', 'arg2'))),
			array('TAG1 cmd2 arg1 "arg21 still arg22" aaarrg3',
				array('tag' => 'TAG1', 'command' => 'cmd2',
					'args' => array('arg1', 'arg21 still arg22', 'aaarrg3'))),
			array('TAG1 cmd2 arg1 "arg21 still  arg22" aaarrg3',
				array('tag' => 'TAG1', 'command' => 'cmd2',
					'args' => array('arg1', 'arg21 still  arg22', 'aaarrg3'))),
			array('TAG1 cmd2 arg1 "arg21 still arg22"  aaarrg3',
				array('tag' => 'TAG1', 'command' => 'cmd2',
					'args' => array('arg1', 'arg21 still arg22', 'aaarrg3'))),
			array('TAG1 cmd2 arg1 "arg21 still  arg22"  aaarrg3',
				array('tag' => 'TAG1', 'command' => 'cmd2',
					'args' => array('arg1', 'arg21 still  arg22', 'aaarrg3'))),
			array('TAG1 cmd2 arg1 " arg21 still arg22" aaarrg3',
				array('tag' => 'TAG1', 'command' => 'cmd2',
					'args' => array('arg1', ' arg21 still arg22', 'aaarrg3'))),
			array('TAG1 cmd2 arg1 "arg21 still arg22 " aaarrg3',
				array('tag' => 'TAG1', 'command' => 'cmd2',
					'args' => array('arg1', 'arg21 still arg22 ', 'aaarrg3'))),
			array('TAG1 cmd2 arg1  "arg21 still arg22 " aaarrg3',
				array('tag' => 'TAG1', 'command' => 'cmd2',
					'args' => array('arg1', 'arg21 still arg22 ', 'aaarrg3'))),
			array('TAG1 cmd2 arg1 " arg21 still arg22 " aaarrg3',
				array('tag' => 'TAG1', 'command' => 'cmd2',
					'args' => array('arg1', ' arg21 still arg22 ', 'aaarrg3'))),
		);
	}
	
	/**
     * @dataProvider providerMsgRaw
     */
	public function testMsgGetArgs($msgRaw, $expect){
		$client = new Client();
		
		$this->assertEquals($expect, $client->msgGetArgs($msgRaw));
	}
	
}
