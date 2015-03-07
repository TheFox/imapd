<?php

namespace TheFox\Test;

use PHPUnit_Framework_TestCase;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Filesystem\Filesystem;

use TheFox\Imap\Storage\TestStorage;

class TestStorageTest extends PHPUnit_Framework_TestCase{
	
	public function testGetDirectorySeperator(){
		$storage = new TestStorage();
		
		$this->assertEquals('_', $storage->getDirectorySeperator());
	}
	
}
