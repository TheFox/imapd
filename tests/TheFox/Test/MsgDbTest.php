<?php

namespace TheFox\Test;

use PHPUnit_Framework_TestCase;
use Zend\Mail\Storage;
use Symfony\Component\Finder\Finder;

use TheFox\Imap\MsgDb;

class MsgDbTest extends PHPUnit_Framework_TestCase{
	
	public function testBasic(){
		$db = new MsgDb();
		$this->assertTrue(is_object($db));
	}
	
	public function testSaveLoad(){
		$db1 = new MsgDb('test_data/msgdb1.yml');
		
		$db1->addMsg('test_data/email1.eml', array(Storage::FLAG_SEEN), true);
		$db1->addMsg('test_data/email2.eml', array(Storage::FLAG_SEEN), true);
		$db1->save();
		
		$finder = new Finder();
		$files = $finder->in('test_data')->name('msgdb1.yml');
		$this->assertEquals(1, count($files));
		
		$db2 = new MsgDb('test_data/msgdb1.yml');
		$this->assertTrue($db2->load());
		
		$msg = $db2->getMsgById(100002);
		$this->assertEquals(100002, $msg['id']);
		$this->assertEquals('test_data/email2.eml', $msg['path']);
		$this->assertEquals(array(Storage::FLAG_SEEN), $msg['flags']);
		$this->assertEquals(true, $msg['recent']);
		
		$db3 = new MsgDb('test_data/msgdb2.yml');
		$this->assertFalse($db3->load());
		
		#\Doctrine\Common\Util\Debug::dump($db3);
	}
	
	public function testAddMsg1(){
		$db = new MsgDb();
		
		$msgId = $db->addMsg('test_data/email1.eml', array(Storage::FLAG_SEEN), true);
		$msgId = $db->addMsg('test_data/email2.eml');
		#\Doctrine\Common\Util\Debug::dump($msgId);
		$this->assertEquals(100002, $msgId);
	}
	
	public function testAddMsg3(){
		$db = new MsgDb('test_data/msgdb3.yml');
		
		$db->addMsg('test_data/email1.eml', array(Storage::FLAG_SEEN), true);
		$db->save();
		
		$finder = new Finder();
		$files = $finder->in('test_data')->name('msgdb3.yml');
		$this->assertEquals(1, count($files));
	}
	
	public function testRemoveMsg(){
		$db = new MsgDb();
		
		$msgId = $db->addMsg('test_data/email1.eml', array(Storage::FLAG_SEEN, Storage::FLAG_ANSWERED), true);
		$msg = $db->removeMsg($msgId);
		#\Doctrine\Common\Util\Debug::dump($msg);
		
		$this->assertEquals(100001, $msg['id']);
		$this->assertEquals('test_data/email1.eml', $msg['path']);
		$this->assertEquals(array(Storage::FLAG_SEEN, Storage::FLAG_ANSWERED), $msg['flags']);
		$this->assertEquals(true, $msg['recent']);
	}
	
	public function testGetMsgIdByPath1(){
		$db = new MsgDb();
		
		$msgId = $db->addMsg('test_data/email1.eml', array(Storage::FLAG_SEEN, Storage::FLAG_ANSWERED), true);
		$msgId = $db->addMsg('test_data/email2.eml', array(Storage::FLAG_SEEN), false);
		$msgId = $db->addMsg('test_data/email3.eml', array(Storage::FLAG_SEEN), false);
		
		$msgId = $db->getMsgIdByPath('test_data/email2.eml');
		$this->assertEquals(100002, $msgId);
	}
	
	public function testGetMsgIdByPath2(){
		$db = new MsgDb();
		
		$msgId = $db->addMsg('test_data/email1.eml', array(Storage::FLAG_SEEN, Storage::FLAG_ANSWERED), true);
		$msgId = $db->addMsg('test_data/email2.eml', array(Storage::FLAG_SEEN), false);
		$msgId = $db->addMsg('test_data/email3.eml', array(Storage::FLAG_SEEN), false);
		
		$msgId = $db->getMsgIdByPath('test_data/emailX.eml');
		$this->assertEquals(null, $msgId);
	}
	
	public function testGetMsgById1(){
		$db = new MsgDb();
		
		$db->addMsg('test_data/email1.eml', array(Storage::FLAG_SEEN, Storage::FLAG_ANSWERED), true);
		$msgId = $db->addMsg('test_data/email2.eml', array(Storage::FLAG_SEEN), false);
		$db->addMsg('test_data/email3.eml', array(Storage::FLAG_SEEN), false);
		
		$msg = $db->getMsgById($msgId);
		#\Doctrine\Common\Util\Debug::dump($msg);
		
		$this->assertEquals(100002, $msg['id']);
		$this->assertEquals('test_data/email2.eml', $msg['path']);
		$this->assertEquals(array(Storage::FLAG_SEEN), $msg['flags']);
		$this->assertEquals(false, $msg['recent']);
	}
	
	public function testGetMsgById2(){
		$db = new MsgDb();
		
		$db->addMsg('test_data/email1.eml', array(Storage::FLAG_SEEN, Storage::FLAG_ANSWERED), true);
		$db->addMsg('test_data/email2.eml', array(Storage::FLAG_SEEN), false);
		$db->addMsg('test_data/email3.eml', array(Storage::FLAG_SEEN), false);
		
		$msg = $db->getMsgById(900);
		#\Doctrine\Common\Util\Debug::dump($msg);
		$this->assertEquals(null, $msg);
	}
	
	public function testGetMsgIdsByFlags(){
		$db = new MsgDb();
		
		$db->addMsg('test_data/email1.eml', array(Storage::FLAG_ANSWERED), true);
		$db->addMsg('test_data/email2.eml', array(Storage::FLAG_SEEN), false);
		$db->addMsg('test_data/email3.eml', array(Storage::FLAG_SEEN), false);
		
		$msgs = $db->getMsgIdsByFlags(array(Storage::FLAG_SEEN));
		#\Doctrine\Common\Util\Debug::dump($msgs);
		$this->assertEquals(100002, $msgs[0]);
		$this->assertEquals(100003, $msgs[1]);
	}
	
	public function testGetFlagsById(){
		$db = new MsgDb();
		
		$db->addMsg('test_data/email1.eml', array(Storage::FLAG_ANSWERED), true);
		$db->addMsg('test_data/email2.eml', array(Storage::FLAG_SEEN, Storage::FLAG_ANSWERED), true);
		$db->addMsg('test_data/email3.eml', array(Storage::FLAG_SEEN), false);
		
		$flags = $db->getFlagsById(100002);
		$this->assertEquals(Storage::FLAG_SEEN, $flags[0]);
		$this->assertEquals(Storage::FLAG_ANSWERED, $flags[1]);
		$this->assertEquals(Storage::FLAG_RECENT, $flags[2]);
		
		$flags = $db->getFlagsById(900);
		$this->assertEquals(array(), $flags);
	}
	
	public function testSetFlagsById(){
		$db = new MsgDb();
		
		$db->addMsg('test_data/email1.eml', array(Storage::FLAG_ANSWERED), true);
		$db->addMsg('test_data/email2.eml', array(Storage::FLAG_SEEN, Storage::FLAG_ANSWERED), false);
		$db->addMsg('test_data/email3.eml', array(Storage::FLAG_SEEN), false);
		
		$db->setFlagsById(100002, array(Storage::FLAG_FLAGGED, Storage::FLAG_RECENT));
		$flags = $db->getFlagsById(100002);
		#\Doctrine\Common\Util\Debug::dump($flags);
		$this->assertEquals(Storage::FLAG_FLAGGED, $flags[0]);
	}
	
	public function testSetPathById(){
		$db = new MsgDb();
		
		$db->addMsg('', array(Storage::FLAG_ANSWERED), true);
		$db->addMsg('', array(Storage::FLAG_SEEN, Storage::FLAG_ANSWERED), false);
		$db->addMsg('', array(Storage::FLAG_SEEN), false);
		
		$db->setPathById(100002, 'test_data/email2.eml');
		
		$msg = $db->getMsgById(100002);
		#\Doctrine\Common\Util\Debug::dump($msg);
		$this->assertEquals('test_data/email2.eml', $msg['path']);
	}
	
	public function testGetNextId(){
		$db = new MsgDb();
		$this->assertEquals(100001, $db->getNextId());
		
		$db->addMsg('test_data/email1.eml', array(Storage::FLAG_ANSWERED), true);
		$db->addMsg('test_data/email2.eml', array(Storage::FLAG_SEEN, Storage::FLAG_ANSWERED), false);
		$db->addMsg('test_data/email3.eml', array(Storage::FLAG_SEEN), false);
		
		$this->assertEquals(100004, $db->getNextId());
	}
	
}
