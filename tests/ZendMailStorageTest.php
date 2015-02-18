<?php

use Zend\Mail\Storage;

class ZendMailStorageTest extends PHPUnit_Framework_TestCase{
	
	public function providerFlags(){
		$rv = array();
		$rv[] = array(Storage::FLAG_SEEN, '\Seen');
		$rv[] = array(Storage::FLAG_ANSWERED, '\Answered');
		$rv[] = array(Storage::FLAG_FLAGGED, '\Flagged');
		$rv[] = array(Storage::FLAG_DELETED, '\Deleted');
		$rv[] = array(Storage::FLAG_DRAFT, '\Draft');
		$rv[] = array(Storage::FLAG_RECENT, '\Recent');
		return $rv;
	}
	
	/**
	 * @dataProvider providerFlags
	 */
	public function testFlags($flag, $expect){
		$this->assertEquals($expect, $flag);
	}
	
}
