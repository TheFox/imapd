<?php

use Symfony\Component\Finder\Finder;
use Symfony\Component\Filesystem\Filesystem;

use TheFox\Imap\Storage\DirectoryStorage;

class AbstractStorageTest extends PHPUnit_Framework_TestCase{
	
	public function testBasic(){
		$storage = new DirectoryStorage();
		
		$this->assertTrue(is_object($storage));
	}
	
	public function testSetPath(){
		$path1 = './test_data/test_mailbox_'.date('Ymd_His').'_'.uniqid('', true);
		$path2 = './test_data/test_mailbox_'.date('Ymd_His').'_'.uniqid('', true);
		
		$storage = new DirectoryStorage();
		
		$storage->setPath($path1);
		$this->assertEquals($path1, $storage->getPath());
		
		$storage->setPath($path2);
		$this->assertEquals($path2, $storage->getPath());
	}
	
	public function testSetDbPath(){
		$path1 = './test_data/test_mailbox_'.date('Ymd_His').'_'.uniqid('', true);
		$path2 = './test_data/test_mailbox_'.date('Ymd_His').'_'.uniqid('', true);
		
		$storage = new DirectoryStorage();
		
		$storage->setDbPath($path1);
		$this->assertEquals($path1, $storage->getDbPath());
		
		$storage->setDbPath($path2);
		$this->assertEquals($path2, $storage->getDbPath());
	}
	
	public function testSetType(){
		$storage = new DirectoryStorage();
		
		$storage->setType('test1');
		$this->assertEquals('test1', $storage->getType());
		
		$storage->setType('test2');
		$this->assertEquals('test2', $storage->getType());
	}
	
}
