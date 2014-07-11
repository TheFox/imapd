<?php

use Zend\Mail\Storage\Writable\Maildir;
use Zend\Mail\Message;
use Zend\Mail\Storage;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Filesystem\Filesystem;

use TheFox\Imap\Server;
use TheFox\Imap\MsgDb;

class ServerTest extends PHPUnit_Framework_TestCase{
	
	public function testBasic(){
		$server = new Server('', 0);
		$this->assertTrue(is_null($server->getLog()));
	}
	
	public function testGetStorageMailbox(){
		$server = new Server('', 0);
		$server->init();
		$storage = $server->getStorageMailbox();
		
		$this->assertTrue($storage['object'] instanceof Maildir);
		
		fwrite(STDOUT, 'dir: '.$storage['path']."\n");
		
		$filesystem = new Filesystem();
		$filesystem->remove($storage['path']);
	}
	
	public function testStorageAddMaildir(){
		$maildirPath = './tests/test_mailbox_'.date('Ymd_His').'_'.uniqid('', true);
		
		$server = new Server('', 0);
		$server->init();
		
		$storage = $server->storageAddMaildir($maildirPath);
		$this->assertTrue(is_array($storage));
		
		$storage = $server->getStorageMailbox();
		$this->assertTrue(is_array($storage));
		
		$this->assertTrue(isset($storage['object']));
		$this->assertTrue(isset($storage['path']));
		$this->assertTrue(isset($storage['type']));
		$this->assertTrue(isset($storage['db']));
		
		$this->assertTrue($storage['object'] instanceof Maildir);
		$this->assertTrue(is_string($storage['path']));
		$this->assertTrue(is_string($storage['type']));
		$this->assertTrue($storage['db'] instanceof MsgDb);
		
		$this->assertFileExists($maildirPath);
		$this->assertFileExists($maildirPath.'/cur');
		$this->assertFileExists($maildirPath.'/new');
		$this->assertFileExists($maildirPath.'/tmp');
	}
	
	public function testStorageFolderAdd(){
		$maildirPath = './tests/test_mailbox_'.date('Ymd_His').'_'.uniqid('', true);
		
		$server = new Server('', 0);
		$server->init();
		$server->storageAddMaildir($maildirPath);
		
		$server->storageFolderAdd('test_dir1');
		$this->assertFileExists($maildirPath.'/.test_dir1');
		
		$server->storageFolderAdd('test_dir2');
		$this->assertFileExists($maildirPath.'/.test_dir2');
		
		$server->storageFolderAdd('test_dir2.test_dir3');
		$this->assertFileExists($maildirPath.'/.test_dir2.test_dir3');
	}
	
	public function testStorageRemoveTempAndSave(){
		$server = new Server('', 0);
		$server->init();
		$storage = $server->storageInit();
		
		if(isset($storage['object'])){
			$this->assertFileExists($storage['path']);
			
			$path = $storage['path'];
			$server->storageRemoveTempAndSave();
			
			$this->assertFalse(file_exists($path));
		}
	}
	
	public function testStorageMailboxGetFolders(){
		$maildirPath = './tests/test_mailbox_'.date('Ymd_His').'_'.uniqid('', true);
		
		$server = new Server('', 0);
		$server->init();
		$server->storageAddMaildir($maildirPath);
		
		$server->storageFolderAdd('test_dir1');
		$this->assertFileExists($maildirPath.'/.test_dir1');
		
		$server->storageFolderAdd('test_dir2');
		$this->assertFileExists($maildirPath.'/.test_dir2');
		
		$server->storageFolderAdd('test_dir2.test_dir3');
		$this->assertFileExists($maildirPath.'/.test_dir2.test_dir3');
		
		
		$folders = $server->storageMailboxGetFolders('INBOX');
		$this->assertEquals(3, count($folders));
		
		$folders = $server->storageMailboxGetFolders('test_dir1');
		$this->assertEquals(0, count($folders));
		
		$folders = $server->storageMailboxGetFolders('test_dir2');
		$this->assertEquals(1, count($folders));
	}
	
	public function testStorageMailboxGetDbNextId(){
		$this->assertTrue(true);
	}
	
	public function testStorageMailboxGetDbSeqById(){
		$this->assertTrue(true);
	}
	
	public function testStorageMaildirGetDbMsgIdBySeqNum(){
		$this->assertTrue(true);
	}
	
	public function testMailAdd(){
		$maildirPath = './tests/test_mailbox_'.date('Ymd_His').'_'.uniqid('', true);
		
		$server = new Server('', 0);
		$server->init();
		$server->storageAddMaildir($maildirPath);
		
		$message = new Message();
		$message->addFrom('thefox21at@gmail.com');
		$message->addTo('thefox@fox21.at');
		$message->setSubject('my_subject 1');
		$message->setBody('my_body');
		$msgId = $server->mailAdd($message->toString());
		$this->assertEquals(100001, $msgId);
		$this->assertEquals(1, $server->storageMailboxGetDbSeqById($msgId));
		
		$message = new Message();
		$message->addFrom('thefox21at@gmail.com');
		$message->addTo('thefox@fox21.at');
		$message->setSubject('my_subject 2');
		$message->setBody('my_body');
		$msgId = $server->mailAdd($message->toString());
		$this->assertEquals(100002, $msgId);
		$this->assertEquals(2, $server->storageMailboxGetDbSeqById($msgId));
		
		$message = new Message();
		$message->addFrom('thefox21at@gmail.com');
		$message->addTo('thefox@fox21.at');
		$message->setSubject('my_subject 3');
		$message->setBody('my_body');
		$msgId = $server->mailAdd($message->toString());
		$this->assertEquals(100003, $msgId);
		$this->assertEquals(3, $server->storageMailboxGetDbSeqById($msgId));
		
		$message = new Message();
		$message->addFrom('thefox21at@gmail.com');
		$message->addTo('thefox@fox21.at');
		$message->setSubject('my_subject 4');
		$message->setBody('my_body');
		$msgId = $server->mailAdd($message->toString());
		$this->assertEquals(100004, $msgId);
		$this->assertEquals(4, $server->storageMailboxGetDbSeqById($msgId));
		
		$message = new Message();
		$message->addFrom('thefox21at@gmail.com');
		$message->addTo('thefox@fox21.at');
		$message->setSubject('my_subject 5');
		$message->setBody('my_body');
		$msgId = $server->mailAdd($message->toString());
		$this->assertEquals(100005, $msgId);
		$this->assertEquals(5, $server->storageMailboxGetDbSeqById($msgId));
		
		$message = new Message();
		$message->addFrom('thefox21at@gmail.com');
		$message->addTo('thefox@fox21.at');
		$message->setSubject('my_subject 6');
		$message->setBody('my_body');
		$msgId = $server->mailAdd($message->toString());
		$this->assertEquals(100006, $msgId);
		$this->assertEquals(6, $server->storageMailboxGetDbSeqById($msgId));
		
		$message = new Message();
		$message->addFrom('thefox21at@gmail.com');
		$message->addTo('thefox@fox21.at');
		$message->setSubject('my_subject 7');
		$message->setBody('my_body');
		$msgId = $server->mailAdd($message->toString(), null, null, false);
		$this->assertEquals(100007, $msgId);
		$this->assertEquals(7, $server->storageMailboxGetDbSeqById($msgId));
		
		$message = new Message();
		$message->addFrom('thefox21at@gmail.com');
		$message->addTo('thefox@fox21.at');
		$message->setSubject('my_subject 8');
		$message->setBody('my_body');
		$msgId = $server->mailAdd($message->toString(), null, null, false);
		$this->assertEquals(100008, $msgId);
		$this->assertEquals(8, $server->storageMailboxGetDbSeqById($msgId));
		
		$finder = new Finder();
		$files = $finder->files()->in($maildirPath.'/new');
		$this->assertEquals(6, count($files));
		
		$finder = new Finder();
		$files = $finder->files()->in($maildirPath.'/cur');
		$this->assertEquals(2, count($files));
		
		
		
		$server->storageFolderAdd('test_dir1');
		
		$message = new Message();
		$message->addFrom('thefox21at@gmail.com');
		$message->addTo('thefox@fox21.at');
		$message->setSubject('my_subject 9');
		$message->setBody('my_body');
		$msgId = $server->mailAdd($message->toString(), 'test_dir1');
		$this->assertEquals(100009, $msgId);
		$this->assertEquals(1, $server->storageMailboxGetDbSeqById($msgId));
		
		$message = new Message();
		$message->addFrom('thefox21at@gmail.com');
		$message->addTo('thefox@fox21.at');
		$message->setSubject('my_subject 10');
		$message->setBody('my_body');
		$msgId = $server->mailAdd($message->toString(), 'test_dir1');
		$this->assertEquals(100010, $msgId);
		$this->assertEquals(2, $server->storageMailboxGetDbSeqById($msgId));
		
		
		$server->shutdown();
	}
	
	public function testMailRemove1(){
		$maildirPath = './tests/test_mailbox_'.date('Ymd_His').'_'.uniqid('', true);
		
		$server = new Server('', 0);
		$server->init();
		$server->storageAddMaildir($maildirPath);
		
		$message = new Message();
		$message->addFrom('thefox21at@gmail.com');
		$message->addTo('thefox@fox21.at');
		$message->setSubject('my_subject 1');
		$message->setBody('my_body');
		$tmpId = $server->mailAdd($message->toString());
		#fwrite(STDOUT, 'tmpId: '.$tmpId."\n");
		
		$message = new Message();
		$message->addFrom('thefox21at@gmail.com');
		$message->addTo('thefox@fox21.at');
		$message->setSubject('my_subject 2');
		$message->setBody('my_body');
		$tmpId = $server->mailAdd($message->toString());
		#fwrite(STDOUT, 'tmpId: '.$tmpId."\n");
		
		$message = new Message();
		$message->addFrom('thefox21at@gmail.com');
		$message->addTo('thefox@fox21.at');
		$message->setSubject('my_subject 3');
		$message->setBody('my_body');
		$tmpId = $server->mailAdd($message->toString());
		#fwrite(STDOUT, 'tmpId: '.$tmpId."\n");
		
		$message = new Message();
		$message->addFrom('thefox21at@gmail.com');
		$message->addTo('thefox@fox21.at');
		$message->setSubject('my_subject 4');
		$message->setBody('my_body');
		$tmpId = $server->mailAdd($message->toString());
		#fwrite(STDOUT, 'tmpId: '.$tmpId."\n");
		
		$message = new Message();
		$message->addFrom('thefox21at@gmail.com');
		$message->addTo('thefox@fox21.at');
		$message->setSubject('my_subject 5');
		$message->setBody('my_body');
		$tmpId = $server->mailAdd($message->toString());
		#fwrite(STDOUT, 'tmpId: '.$tmpId."\n");
		
		$message = new Message();
		$message->addFrom('thefox21at@gmail.com');
		$message->addTo('thefox@fox21.at');
		$message->setSubject('my_subject 6');
		$message->setBody('my_body');
		$tmpId = $server->mailAdd($message->toString());
		#fwrite(STDOUT, 'tmpId: '.$tmpId."\n");
		
		$finder = new Finder();
		$files = $finder->files()->in($maildirPath.'/new');
		$this->assertEquals(6, count($files));
		
		$server->mailRemove(100002);
		$finder = new Finder();
		$files = $finder->files()->in($maildirPath.'/new');
		$this->assertEquals(5, count($files));
		
		$this->assertEquals(4, $server->storageMailboxGetDbSeqById(100005));
		$this->assertEquals(5, $server->storageMailboxGetDbSeqById(100006));
		
		$server->shutdown();
	}
	
	public function testMailRemove2(){
		$maildirPath = './tests/test_mailbox_'.date('Ymd_His').'_'.uniqid('', true);
		
		$server = new Server('', 0);
		$server->init();
		$server->storageAddMaildir($maildirPath);
		
		$message = new Message();
		$message->addFrom('thefox21at@gmail.com');
		$message->addTo('thefox@fox21.at');
		$message->setSubject('my_subject 1');
		$message->setBody('my_body');
		$tmpId = $server->mailAdd($message->toString());
		#fwrite(STDOUT, 'tmpId: '.$tmpId."\n");
		
		$message = new Message();
		$message->addFrom('thefox21at@gmail.com');
		$message->addTo('thefox@fox21.at');
		$message->setSubject('my_subject 2');
		$message->setBody('my_body');
		$tmpId = $server->mailAdd($message->toString());
		#fwrite(STDOUT, 'tmpId: '.$tmpId."\n");
		$msgId = $tmpId;
		
		$message = new Message();
		$message->addFrom('thefox21at@gmail.com');
		$message->addTo('thefox@fox21.at');
		$message->setSubject('my_subject 3');
		$message->setBody('my_body');
		$tmpId = $server->mailAdd($message->toString());
		#fwrite(STDOUT, 'tmpId: '.$tmpId."\n");
		
		$message = new Message();
		$message->addFrom('thefox21at@gmail.com');
		$message->addTo('thefox@fox21.at');
		$message->setSubject('my_subject 4');
		$message->setBody('my_body');
		$tmpId = $server->mailAdd($message->toString());
		#fwrite(STDOUT, 'tmpId: '.$tmpId."\n");
		
		$message = new Message();
		$message->addFrom('thefox21at@gmail.com');
		$message->addTo('thefox@fox21.at');
		$message->setSubject('my_subject 5');
		$message->setBody('my_body');
		$tmpId = $server->mailAdd($message->toString());
		#fwrite(STDOUT, 'tmpId: '.$tmpId."\n");
		
		$message = new Message();
		$message->addFrom('thefox21at@gmail.com');
		$message->addTo('thefox@fox21.at');
		$message->setSubject('my_subject 6');
		$message->setBody('my_body');
		$tmpId = $server->mailAdd($message->toString());
		#fwrite(STDOUT, 'tmpId: '.$tmpId."\n");
		
		$finder = new Finder();
		$files = $finder->files()->in($maildirPath.'/new');
		$this->assertEquals(6, count($files));
		
		$server->mailRemoveBySequenceNum(4);
		$finder = new Finder();
		$files = $finder->files()->in($maildirPath.'/new');
		$this->assertEquals(5, count($files));
		
		$this->assertEquals(4, $server->storageMailboxGetDbSeqById(100004));
		$this->assertEquals(5, $server->storageMailboxGetDbSeqById(100006));
		
		$server->shutdown();
	}
	
	public function testMailCopy1(){
		$maildirPath = './tests/test_mailbox_'.date('Ymd_His').'_'.uniqid('', true);
		
		$server = new Server('', 0);
		$server->init();
		$server->storageAddMaildir($maildirPath);
		
		$message = new Message();
		$message->addFrom('thefox21at@gmail.com');
		$message->addTo('thefox@fox21.at');
		$message->setSubject('my_subject 1');
		$message->setBody('my_body');
		$tmpId = $server->mailAdd($message->toString());
		#fwrite(STDOUT, 'tmpId: '.$tmpId."\n");
		
		$message = new Message();
		$message->addFrom('thefox21at@gmail.com');
		$message->addTo('thefox@fox21.at');
		$message->setSubject('my_subject 2');
		$message->setBody('my_body');
		$tmpId = $server->mailAdd($message->toString());
		#fwrite(STDOUT, 'tmpId: '.$tmpId."\n");
		
		$message = new Message();
		$message->addFrom('thefox21at@gmail.com');
		$message->addTo('thefox@fox21.at');
		$message->setSubject('my_subject 3');
		$message->setBody('my_body');
		$tmpId = $server->mailAdd($message->toString());
		#fwrite(STDOUT, 'tmpId: '.$tmpId."\n");
		
		$message = new Message();
		$message->addFrom('thefox21at@gmail.com');
		$message->addTo('thefox@fox21.at');
		$message->setSubject('my_subject 4');
		$message->setBody('my_body');
		$tmpId = $server->mailAdd($message->toString());
		#fwrite(STDOUT, 'tmpId: '.$tmpId."\n");
		
		$message = new Message();
		$message->addFrom('thefox21at@gmail.com');
		$message->addTo('thefox@fox21.at');
		$message->setSubject('my_subject 5');
		$message->setBody('my_body');
		$tmpId = $server->mailAdd($message->toString());
		#fwrite(STDOUT, 'tmpId: '.$tmpId."\n");
		
		$message = new Message();
		$message->addFrom('thefox21at@gmail.com');
		$message->addTo('thefox@fox21.at');
		$message->setSubject('my_subject 6');
		$message->setBody('my_body');
		$tmpId = $server->mailAdd($message->toString());
		#fwrite(STDOUT, 'tmpId: '.$tmpId."\n");
		
		$finder = new Finder();
		$files = $finder->files()->in($maildirPath.'/new');
		$this->assertEquals(6, count($files));
		
		$server->storageFolderAdd('test_dir1');
		
		$server->mailCopy(100002, 'test_dir1');
		$server->mailCopy(100004, 'test_dir1');
		
		$this->assertEquals(1, $server->storageMailboxGetDbSeqById(100007));
		$this->assertEquals(2, $server->storageMailboxGetDbSeqById(100008));
		
		$server->shutdown();
	}
	
	public function testMailCopy2(){
		$maildirPath = './tests/test_mailbox_'.date('Ymd_His').'_'.uniqid('', true);
		
		$server = new Server('', 0);
		$server->init();
		$server->storageAddMaildir($maildirPath);
		
		$message = new Message();
		$message->addFrom('thefox21at@gmail.com');
		$message->addTo('thefox@fox21.at');
		$message->setSubject('my_subject 1');
		$message->setBody('my_body');
		$tmpId = $server->mailAdd($message->toString());
		#fwrite(STDOUT, 'tmpId: '.$tmpId."\n");
		
		$message = new Message();
		$message->addFrom('thefox21at@gmail.com');
		$message->addTo('thefox@fox21.at');
		$message->setSubject('my_subject 2');
		$message->setBody('my_body');
		$tmpId = $server->mailAdd($message->toString());
		#fwrite(STDOUT, 'tmpId: '.$tmpId."\n");
		
		$message = new Message();
		$message->addFrom('thefox21at@gmail.com');
		$message->addTo('thefox@fox21.at');
		$message->setSubject('my_subject 3');
		$message->setBody('my_body');
		$tmpId = $server->mailAdd($message->toString());
		#fwrite(STDOUT, 'tmpId: '.$tmpId."\n");
		
		$message = new Message();
		$message->addFrom('thefox21at@gmail.com');
		$message->addTo('thefox@fox21.at');
		$message->setSubject('my_subject 4');
		$message->setBody('my_body');
		$tmpId = $server->mailAdd($message->toString());
		#fwrite(STDOUT, 'tmpId: '.$tmpId."\n");
		
		$message = new Message();
		$message->addFrom('thefox21at@gmail.com');
		$message->addTo('thefox@fox21.at');
		$message->setSubject('my_subject 5');
		$message->setBody('my_body');
		$tmpId = $server->mailAdd($message->toString());
		#fwrite(STDOUT, 'tmpId: '.$tmpId."\n");
		
		$message = new Message();
		$message->addFrom('thefox21at@gmail.com');
		$message->addTo('thefox@fox21.at');
		$message->setSubject('my_subject 6');
		$message->setBody('my_body');
		$tmpId = $server->mailAdd($message->toString());
		#fwrite(STDOUT, 'tmpId: '.$tmpId."\n");
		
		$finder = new Finder();
		$files = $finder->files()->in($maildirPath.'/new');
		$this->assertEquals(6, count($files));
		
		$server->storageFolderAdd('test_dir1');
		
		$server->mailCopyBySequenceNum(2, 'test_dir1');
		$server->mailCopyBySequenceNum(4, 'test_dir1');
		
		$this->assertEquals(1, $server->storageMailboxGetDbSeqById(100007));
		$this->assertEquals(2, $server->storageMailboxGetDbSeqById(100008));
		
		$server->shutdown();
	}
	
}
