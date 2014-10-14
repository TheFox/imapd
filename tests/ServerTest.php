<?php

use Zend\Mail\Storage\Writable\Maildir;
use Zend\Mail\Message;
use Zend\Mail\Storage;
use Zend\Mail\Storage\Message\File;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Filesystem\Filesystem;

use TheFox\Logger\Logger;
use TheFox\Imap\Server;
use TheFox\Imap\MsgDb;
use TheFox\Imap\Event;

class ServerTest extends PHPUnit_Framework_TestCase{
	
	public function testBasic(){
		$server = new Server('', 0);
		$this->assertTrue($server->getLog() === null);
		
		$server = new Server('', 0);
		$server->setLog(new Logger('test_application'));
		$this->assertTrue($server->getLog() !== null);
	}
	
	public function testGetStorageMailbox(){
		$server = new Server('', 0);
		$server->setLog(new Logger('test_application'));
		$server->init();
		$storage = $server->getStorageMailbox();
		
		$this->assertTrue($storage['object'] instanceof Maildir);
		
		#fwrite(STDOUT, 'dir: '.$storage['path']."\n");
		
		$filesystem = new Filesystem();
		$filesystem->remove($storage['path']);
	}
	
	public function testStorageAddMaildir(){
		$maildirPath = './tests/test_mailbox_'.date('Ymd_His').'_'.uniqid('', true);
		
		$server = new Server('', 0);
		$server->setLog(new Logger('test_application'));
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
		$server->setLog(new Logger('test_application'));
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
		$server->setLog(new Logger('test_application'));
		$server->init();
		$storage = $server->storageInit();
		
		if(isset($storage['object'])){
			$this->assertFileExists($storage['path']);
			
			$path = $storage['path'];
			$server->storageRemoveTempAndSave();
			
			$this->assertFalse(file_exists($path));
		}
	}
	
	public function testStorageMailboxGetFolders1(){
		$maildirPath = './tests/test_mailbox_'.date('Ymd_His').'_'.uniqid('', true);
		
		$server = new Server('', 0);
		$server->setLog(new Logger('test_application'));
		$server->init();
		$server->storageAddMaildir($maildirPath);
		
		$server->storageFolderAdd('test_dir1');
		$this->assertFileExists($maildirPath.'/.test_dir1');
		
		$server->storageFolderAdd('test_dir2');
		$this->assertFileExists($maildirPath.'/.test_dir2');
		
		$server->storageFolderAdd('test_dir2.test_dir3');
		$this->assertFileExists($maildirPath.'/.test_dir2.test_dir3');
		
		
		$folders = $server->storageMailboxGetFolders('', 'INBOX');
		#ve($folders);
		$this->assertEquals(4, count($folders));
		
		$folders = $server->storageMailboxGetFolders('', 'test_dir1');
		$this->assertEquals(1, count($folders));
		
		$folders = $server->storageMailboxGetFolders('', 'test_dir2');
		$this->assertEquals(1, count($folders));
	}
	
	public function testStorageMailboxGetFolders2(){
		$maildirPath = './tests/test_mailbox_'.date('Ymd_His').'_'.uniqid('', true);
		
		$server = new Server('', 0);
		$server->setLog(new Logger('test_application'));
		$server->init();
		$server->storageAddMaildir($maildirPath);
		
		$server->storageFolderAdd('Drafts');
		$this->assertFileExists($maildirPath.'/.Drafts');
		
		$server->storageFolderAdd('Trash');
		$this->assertFileExists($maildirPath.'/.Trash');
		
		
		$folders = $server->storageMailboxGetFolders('', '*');
		#ve($folders);
		$this->assertEquals(3, count($folders));
		
		$folders = $server->storageMailboxGetFolders('', 'INBOX');
		#ve($folders);
		$this->assertEquals(3, count($folders));
		
		$folders = $server->storageMailboxGetFolders('INBOX', '*');
		#ve($folders);
		$this->assertEquals(3, count($folders));
		
		$folders = $server->storageMailboxGetFolders('', 'Drafts');
		#ve($folders);
		$this->assertEquals(1, count($folders));
		
		$folders = $server->storageMailboxGetFolders('', 'Trash');
		#ve($folders);
		$this->assertEquals(1, count($folders));
		
		$folders = $server->storageMailboxGetFolders('Trash', '*');
		#ve($folders);
		$this->assertEquals(0, count($folders));
	}
	
	public function testStorageMailboxGetDbNextId(){
		$maildirPath = './tests/test_mailbox_'.date('Ymd_His').'_'.uniqid('', true);
		
		$server = new Server('', 0);
		$server->setLog(new Logger('test_application'));
		$server->init();
		$server->storageAddMaildir($maildirPath);
		
		$message = new Message();
		$message->addFrom('thefox21at@gmail.com');
		$message->addTo('thefox@fox21.at');
		$message->setSubject('my_subject 1');
		$message->setBody('my_body');
		$msgId = $server->mailAdd($message);
		
		$message = new Message();
		$message->addFrom('thefox21at@gmail.com');
		$message->addTo('thefox@fox21.at');
		$message->setSubject('my_subject 2');
		$message->setBody('my_body');
		$msgId = $server->mailAdd($message);
		
		$message = new Message();
		$message->addFrom('thefox21at@gmail.com');
		$message->addTo('thefox@fox21.at');
		$message->setSubject('my_subject 3');
		$message->setBody('my_body');
		$msgId = $server->mailAdd($message);
		
		$message = new Message();
		$message->addFrom('thefox21at@gmail.com');
		$message->addTo('thefox@fox21.at');
		$message->setSubject('my_subject 4');
		$message->setBody('my_body');
		$msgId = $server->mailAdd($message);
		
		$this->assertEquals(100005, $server->storageMailboxGetDbNextId());
	}
	
	public function testStorageMailboxGetDbSeqById(){
		$maildirPath = './tests/test_mailbox_'.date('Ymd_His').'_'.uniqid('', true);
		
		$server = new Server('', 0);
		$server->setLog(new Logger('test_application'));
		$server->init();
		$server->storageAddMaildir($maildirPath);
		
		$message = new Message();
		$message->addFrom('thefox21at@gmail.com');
		$message->addTo('thefox@fox21.at');
		$message->setSubject('my_subject 1');
		$message->setBody('my_body');
		$msgId = $server->mailAdd($message);
		$this->assertEquals(1, $server->storageMailboxGetDbSeqById($msgId));
		
		$message = new Message();
		$message->addFrom('thefox21at@gmail.com');
		$message->addTo('thefox@fox21.at');
		$message->setSubject('my_subject 2');
		$message->setBody('my_body');
		$msgId = $server->mailAdd($message);
		$this->assertEquals(2, $server->storageMailboxGetDbSeqById($msgId));
		
		$message = new Message();
		$message->addFrom('thefox21at@gmail.com');
		$message->addTo('thefox@fox21.at');
		$message->setSubject('my_subject 3');
		$message->setBody('my_body');
		$msgId = $server->mailAdd($message);
		$this->assertEquals(3, $server->storageMailboxGetDbSeqById($msgId));
		
		$message = new Message();
		$message->addFrom('thefox21at@gmail.com');
		$message->addTo('thefox@fox21.at');
		$message->setSubject('my_subject 4');
		$message->setBody('my_body');
		$msgId = $server->mailAdd($message);
		$this->assertEquals(4, $server->storageMailboxGetDbSeqById($msgId));
	}
	
	/*public function testStorageMaildirGetDbMsgIdBySeqNum(){
		$maildirPath = './tests/test_mailbox_'.date('Ymd_His').'_'.uniqid('', true);
		
		$server = new Server('', 0);
		$server->setLog(new Logger('test_application'));
		$server->init();
		$server->storageAddMaildir($maildirPath);
		
		$message = new Message();
		$message->addFrom('thefox21at@gmail.com');
		$message->addTo('thefox@fox21.at');
		$message->setSubject('my_subject 1');
		$message->setBody('my_body');
		$msgId = $server->mailAdd($message);
		$this->assertEquals($msgId, $server->storageMaildirGetDbMsgIdBySeqNum(1));
		
		$message = new Message();
		$message->addFrom('thefox21at@gmail.com');
		$message->addTo('thefox@fox21.at');
		$message->setSubject('my_subject 2');
		$message->setBody('my_body');
		$msgId = $server->mailAdd($message);
		$this->assertEquals($msgId, $server->storageMaildirGetDbMsgIdBySeqNum(2));
		
		$message = new Message();
		$message->addFrom('thefox21at@gmail.com');
		$message->addTo('thefox@fox21.at');
		$message->setSubject('my_subject 3');
		$message->setBody('my_body');
		$msgId = $server->mailAdd($message);
		$this->assertEquals($msgId, $server->storageMaildirGetDbMsgIdBySeqNum(3));
		
		$message = new Message();
		$message->addFrom('thefox21at@gmail.com');
		$message->addTo('thefox@fox21.at');
		$message->setSubject('my_subject 4');
		$message->setBody('my_body');
		$msgId = $server->mailAdd($message);
		$this->assertEquals($msgId, $server->storageMaildirGetDbMsgIdBySeqNum(4));
	}*/
	
	public function testMailAdd(){
		$maildirPath = './tests/test_mailbox_'.date('Ymd_His').'_'.uniqid('', true);
		
		$server = new Server('', 0);
		$server->setLog(new Logger('test_application'));
		$server->init();
		$server->storageAddMaildir($maildirPath);
		
		$message = new Message();
		$message->addFrom('thefox21at@gmail.com');
		$message->addTo('thefox@fox21.at');
		$message->setSubject('my_subject 1');
		$message->setBody('my_body');
		$msgId = $server->mailAdd($message);
		$this->assertEquals(100001, $msgId);
		$this->assertEquals(1, $server->storageMailboxGetDbSeqById($msgId));
		
		$message = new Message();
		$message->addFrom('thefox21at@gmail.com');
		$message->addTo('thefox@fox21.at');
		$message->setSubject('my_subject 2');
		$message->setBody('my_body');
		$msgId = $server->mailAdd($message);
		$this->assertEquals(100002, $msgId);
		$this->assertEquals(2, $server->storageMailboxGetDbSeqById($msgId));
		
		$message = new Message();
		$message->addFrom('thefox21at@gmail.com');
		$message->addTo('thefox@fox21.at');
		$message->setSubject('my_subject 3');
		$message->setBody('my_body');
		$msgId = $server->mailAdd($message);
		$this->assertEquals(100003, $msgId);
		$this->assertEquals(3, $server->storageMailboxGetDbSeqById($msgId));
		
		$message = new Message();
		$message->addFrom('thefox21at@gmail.com');
		$message->addTo('thefox@fox21.at');
		$message->setSubject('my_subject 4');
		$message->setBody('my_body');
		$msgId = $server->mailAdd($message);
		$this->assertEquals(100004, $msgId);
		$this->assertEquals(4, $server->storageMailboxGetDbSeqById($msgId));
		
		$message = new Message();
		$message->addFrom('thefox21at@gmail.com');
		$message->addTo('thefox@fox21.at');
		$message->setSubject('my_subject 5');
		$message->setBody('my_body');
		$msgId = $server->mailAdd($message);
		$this->assertEquals(100005, $msgId);
		$this->assertEquals(5, $server->storageMailboxGetDbSeqById($msgId));
		
		$message = new Message();
		$message->addFrom('thefox21at@gmail.com');
		$message->addTo('thefox@fox21.at');
		$message->setSubject('my_subject 6');
		$message->setBody('my_body');
		$msgId = $server->mailAdd($message);
		$this->assertEquals(100006, $msgId);
		$this->assertEquals(6, $server->storageMailboxGetDbSeqById($msgId));
		
		$message = new Message();
		$message->addFrom('thefox21at@gmail.com');
		$message->addTo('thefox@fox21.at');
		$message->setSubject('my_subject 7');
		$message->setBody('my_body');
		$msgId = $server->mailAdd($message, null, null, false);
		$this->assertEquals(100007, $msgId);
		$this->assertEquals(7, $server->storageMailboxGetDbSeqById($msgId));
		
		$message = new Message();
		$message->addFrom('thefox21at@gmail.com');
		$message->addTo('thefox@fox21.at');
		$message->setSubject('my_subject 8');
		$message->setBody('my_body');
		$msgId = $server->mailAdd($message, null, null, false);
		$this->assertEquals(100008, $msgId);
		$this->assertEquals(8, $server->storageMailboxGetDbSeqById($msgId));
		
		$finder = new Finder();
		$files = $finder->in($maildirPath.'/new')->files();
		$this->assertEquals(6, count($files));
		
		$finder = new Finder();
		$files = $finder->in($maildirPath.'/cur')->files();
		$this->assertEquals(2, count($files));
		
		
		
		$server->storageFolderAdd('test_dir1');
		
		$message = new Message();
		$message->addFrom('thefox21at@gmail.com');
		$message->addTo('thefox@fox21.at');
		$message->setSubject('my_subject 9');
		$message->setBody('my_body');
		$msgId = $server->mailAdd($message, 'test_dir1');
		$this->assertEquals(100009, $msgId);
		$this->assertEquals(1, $server->storageMailboxGetDbSeqById($msgId));
		
		$message = new Message();
		$message->addFrom('thefox21at@gmail.com');
		$message->addTo('thefox@fox21.at');
		$message->setSubject('my_subject 10');
		$message->setBody('my_body');
		$msgId = $server->mailAdd($message, 'test_dir1');
		$this->assertEquals(100010, $msgId);
		$this->assertEquals(2, $server->storageMailboxGetDbSeqById($msgId));
		
		
		$server->shutdown();
	}
	
	public function testMailRemove1(){
		$maildirPath = './tests/test_mailbox_'.date('Ymd_His').'_'.uniqid('', true);
		
		$server = new Server('', 0);
		$server->setLog(new Logger('test_application'));
		$server->init();
		$server->storageAddMaildir($maildirPath);
		
		$message = new Message();
		$message->addFrom('thefox21at@gmail.com');
		$message->addTo('thefox@fox21.at');
		$message->setSubject('my_subject 1');
		$message->setBody('my_body');
		$tmpId = $server->mailAdd($message);
		#fwrite(STDOUT, 'tmpId: '.$tmpId."\n");
		
		$message = new Message();
		$message->addFrom('thefox21at@gmail.com');
		$message->addTo('thefox@fox21.at');
		$message->setSubject('my_subject 2');
		$message->setBody('my_body');
		$tmpId = $server->mailAdd($message);
		#fwrite(STDOUT, 'tmpId: '.$tmpId."\n");
		
		$message = new Message();
		$message->addFrom('thefox21at@gmail.com');
		$message->addTo('thefox@fox21.at');
		$message->setSubject('my_subject 3');
		$message->setBody('my_body');
		$tmpId = $server->mailAdd($message);
		#fwrite(STDOUT, 'tmpId: '.$tmpId."\n");
		
		$message = new Message();
		$message->addFrom('thefox21at@gmail.com');
		$message->addTo('thefox@fox21.at');
		$message->setSubject('my_subject 4');
		$message->setBody('my_body');
		$tmpId = $server->mailAdd($message);
		#fwrite(STDOUT, 'tmpId: '.$tmpId."\n");
		
		$message = new Message();
		$message->addFrom('thefox21at@gmail.com');
		$message->addTo('thefox@fox21.at');
		$message->setSubject('my_subject 5');
		$message->setBody('my_body');
		$tmpId = $server->mailAdd($message);
		#fwrite(STDOUT, 'tmpId: '.$tmpId."\n");
		
		$message = new Message();
		$message->addFrom('thefox21at@gmail.com');
		$message->addTo('thefox@fox21.at');
		$message->setSubject('my_subject 6');
		$message->setBody('my_body');
		$tmpId = $server->mailAdd($message);
		#fwrite(STDOUT, 'tmpId: '.$tmpId."\n");
		
		$finder = new Finder();
		$files = $finder->in($maildirPath.'/new')->files();
		$this->assertEquals(6, count($files));
		
		$server->mailRemove(100002);
		$finder = new Finder();
		$files = $finder->in($maildirPath.'/new')->files();
		$this->assertEquals(5, count($files));
		
		$this->assertEquals(4, $server->storageMailboxGetDbSeqById(100005));
		$this->assertEquals(5, $server->storageMailboxGetDbSeqById(100006));
		
		$server->shutdown();
	}
	
	public function testMailRemove2(){
		$maildirPath = './tests/test_mailbox_'.date('Ymd_His').'_'.uniqid('', true);
		
		$server = new Server('', 0);
		$server->setLog(new Logger('test_application'));
		$server->init();
		$server->storageAddMaildir($maildirPath);
		
		$message = new Message();
		$message->addFrom('thefox21at@gmail.com');
		$message->addTo('thefox@fox21.at');
		$message->setSubject('my_subject 1');
		$message->setBody('my_body');
		$tmpId = $server->mailAdd($message);
		#fwrite(STDOUT, 'tmpId: '.$tmpId."\n");
		
		$message = new Message();
		$message->addFrom('thefox21at@gmail.com');
		$message->addTo('thefox@fox21.at');
		$message->setSubject('my_subject 2');
		$message->setBody('my_body');
		$tmpId = $server->mailAdd($message);
		#fwrite(STDOUT, 'tmpId: '.$tmpId."\n");
		$msgId = $tmpId;
		
		$message = new Message();
		$message->addFrom('thefox21at@gmail.com');
		$message->addTo('thefox@fox21.at');
		$message->setSubject('my_subject 3');
		$message->setBody('my_body');
		$tmpId = $server->mailAdd($message);
		#fwrite(STDOUT, 'tmpId: '.$tmpId."\n");
		
		$message = new Message();
		$message->addFrom('thefox21at@gmail.com');
		$message->addTo('thefox@fox21.at');
		$message->setSubject('my_subject 4');
		$message->setBody('my_body');
		$tmpId = $server->mailAdd($message);
		#fwrite(STDOUT, 'tmpId: '.$tmpId."\n");
		
		$message = new Message();
		$message->addFrom('thefox21at@gmail.com');
		$message->addTo('thefox@fox21.at');
		$message->setSubject('my_subject 5');
		$message->setBody('my_body');
		$tmpId = $server->mailAdd($message);
		#fwrite(STDOUT, 'tmpId: '.$tmpId."\n");
		
		$message = new Message();
		$message->addFrom('thefox21at@gmail.com');
		$message->addTo('thefox@fox21.at');
		$message->setSubject('my_subject 6');
		$message->setBody('my_body');
		$tmpId = $server->mailAdd($message);
		#fwrite(STDOUT, 'tmpId: '.$tmpId."\n");
		
		$finder = new Finder();
		$files = $finder->in($maildirPath.'/new')->files();
		$this->assertEquals(6, count($files));
		
		$server->mailRemoveBySequenceNum(4);
		$finder = new Finder();
		$files = $finder->in($maildirPath.'/new')->files();
		$this->assertEquals(5, count($files));
		
		$this->assertEquals(null, $server->storageMailboxGetDbSeqById(100004));
		$this->assertEquals(4, $server->storageMailboxGetDbSeqById(100005));
		$this->assertEquals(5, $server->storageMailboxGetDbSeqById(100006));
		
		$server->shutdown();
	}
	
	public function testMailCopy1(){
		$maildirPath = './tests/test_mailbox_'.date('Ymd_His').'_'.uniqid('', true);
		
		$server = new Server('', 0);
		$server->setLog(new Logger('test_application'));
		$server->init();
		$server->storageAddMaildir($maildirPath);
		
		$message = new Message();
		$message->addFrom('thefox21at@gmail.com');
		$message->addTo('thefox@fox21.at');
		$message->setSubject('my_subject 1');
		$message->setBody('my_body');
		$tmpId = $server->mailAdd($message);
		#fwrite(STDOUT, 'tmpId: '.$tmpId."\n");
		
		$message = new Message();
		$message->addFrom('thefox21at@gmail.com');
		$message->addTo('thefox@fox21.at');
		$message->setSubject('my_subject 2');
		$message->setBody('my_body');
		$tmpId = $server->mailAdd($message);
		#fwrite(STDOUT, 'tmpId: '.$tmpId."\n");
		
		$message = new Message();
		$message->addFrom('thefox21at@gmail.com');
		$message->addTo('thefox@fox21.at');
		$message->setSubject('my_subject 3');
		$message->setBody('my_body');
		$tmpId = $server->mailAdd($message);
		#fwrite(STDOUT, 'tmpId: '.$tmpId."\n");
		
		$message = new Message();
		$message->addFrom('thefox21at@gmail.com');
		$message->addTo('thefox@fox21.at');
		$message->setSubject('my_subject 4');
		$message->setBody('my_body');
		$tmpId = $server->mailAdd($message);
		#fwrite(STDOUT, 'tmpId: '.$tmpId."\n");
		
		$message = new Message();
		$message->addFrom('thefox21at@gmail.com');
		$message->addTo('thefox@fox21.at');
		$message->setSubject('my_subject 5');
		$message->setBody('my_body');
		$tmpId = $server->mailAdd($message);
		#fwrite(STDOUT, 'tmpId: '.$tmpId."\n");
		
		$message = new Message();
		$message->addFrom('thefox21at@gmail.com');
		$message->addTo('thefox@fox21.at');
		$message->setSubject('my_subject 6');
		$message->setBody('my_body');
		$tmpId = $server->mailAdd($message);
		#fwrite(STDOUT, 'tmpId: '.$tmpId."\n");
		
		$finder = new Finder();
		$files = $finder->in($maildirPath.'/new')->files();
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
		$server->setLog(new Logger('test_application'));
		$server->init();
		$server->storageAddMaildir($maildirPath);
		
		$message = new Message();
		$message->addFrom('thefox21at@gmail.com');
		$message->addTo('thefox@fox21.at');
		$message->setSubject('my_subject 1');
		$message->setBody('my_body');
		$tmpId = $server->mailAdd($message);
		#fwrite(STDOUT, 'tmpId: '.$tmpId."\n");
		
		$message = new Message();
		$message->addFrom('thefox21at@gmail.com');
		$message->addTo('thefox@fox21.at');
		$message->setSubject('my_subject 2');
		$message->setBody('my_body');
		$tmpId = $server->mailAdd($message);
		#fwrite(STDOUT, 'tmpId: '.$tmpId."\n");
		
		$message = new Message();
		$message->addFrom('thefox21at@gmail.com');
		$message->addTo('thefox@fox21.at');
		$message->setSubject('my_subject 3');
		$message->setBody('my_body');
		$tmpId = $server->mailAdd($message);
		#fwrite(STDOUT, 'tmpId: '.$tmpId."\n");
		
		$message = new Message();
		$message->addFrom('thefox21at@gmail.com');
		$message->addTo('thefox@fox21.at');
		$message->setSubject('my_subject 4');
		$message->setBody('my_body');
		$tmpId = $server->mailAdd($message);
		#fwrite(STDOUT, 'tmpId: '.$tmpId."\n");
		
		$message = new Message();
		$message->addFrom('thefox21at@gmail.com');
		$message->addTo('thefox@fox21.at');
		$message->setSubject('my_subject 5');
		$message->setBody('my_body');
		$tmpId = $server->mailAdd($message);
		#fwrite(STDOUT, 'tmpId: '.$tmpId."\n");
		
		$message = new Message();
		$message->addFrom('thefox21at@gmail.com');
		$message->addTo('thefox@fox21.at');
		$message->setSubject('my_subject 6');
		$message->setBody('my_body');
		$tmpId = $server->mailAdd($message);
		#fwrite(STDOUT, 'tmpId: '.$tmpId."\n");
		
		$finder = new Finder();
		$files = $finder->in($maildirPath.'/new')->files();
		$this->assertEquals(6, count($files));
		
		$server->storageFolderAdd('test_dir1');
		
		$server->mailCopyBySequenceNum(2, 'test_dir1');
		$server->mailCopyBySequenceNum(4, 'test_dir1');
		
		$this->assertEquals(1, $server->storageMailboxGetDbSeqById(100007));
		$this->assertEquals(2, $server->storageMailboxGetDbSeqById(100008));
		
		$server->shutdown();
	}
	
	public function testMailGet(){
		$maildirPath = './tests/test_mailbox_'.date('Ymd_His').'_'.uniqid('', true);
		
		$server = new Server('', 0);
		$server->setLog(new Logger('test_application'));
		$server->init();
		$server->storageAddMaildir($maildirPath);
		
		$message = new Message();
		$message->addFrom('thefox21at@gmail.com');
		$message->addTo('thefox@fox21.at');
		$message->setSubject('my_subject 1');
		$message->setBody('my_body');
		$msgId = $server->mailAdd($message);
		
		$this->assertEquals(100001, $msgId);
		
		$message = $server->mailGet($msgId);
		#ve($message);
		$this->assertTrue($message instanceof File);
		$this->assertEquals('my_subject 1', $message->subject);
		$this->assertEquals('my_body', $message->getContent());
	}
	
	public function functionForTestEvent(){
		#fwrite(STDOUT, "forTestEvent\n");
		return 18;
	}
	
	public function testEvent(){
		$maildirPath = './tests/test_mailbox_'.date('Ymd_His').'_'.uniqid('', true);
		
		$server = new Server('', 0);
		$server->setLog(new Logger('test_application'));
		$server->init();
		$server->storageAddMaildir($maildirPath);
		
		$testData = 21;
		$event1 = new Event(Event::TRIGGER_MAIL_ADD_PRE, null, function($event) use(&$testData) {
			#fwrite(STDOUT, 'my function: '.$event->getTrigger().', '.$testData."\n");
			$this->assertEquals(21, $testData);
			
			$testData = 24;
			
			return 42;
		});
		$server->eventAdd($event1);
		
		$event2 = new Event(Event::TRIGGER_MAIL_ADD_PRE, $this, 'functionForTestEvent');
		$server->eventAdd($event2);
		
		$event3 = new Event(Event::TRIGGER_MAIL_ADD, null, function($event, $mail){
			$this->assertTrue(is_object($mail));
			$this->assertEquals('my_subject 1', $mail->getSubject());
		});
		$server->eventAdd($event3);
		
		$event4 = new Event(Event::TRIGGER_MAIL_ADD_POST, null, function($event, $msgId){
			$this->assertEquals(100001, $msgId);
		});
		$server->eventAdd($event4);
		
		
		$message = new Message();
		$message->addFrom('thefox21at@gmail.com');
		$message->addTo('thefox@fox21.at');
		$message->setSubject('my_subject 1');
		$message->setBody('my_body');
		$server->mailAdd($message);
		
		$this->assertEquals(24, $testData);
		$this->assertEquals(42, $event1->getReturnValue());
		$this->assertEquals(18, $event2->getReturnValue());
		$this->assertEquals(null, $event3->getReturnValue());
		$this->assertEquals(null, $event4->getReturnValue());
	}
	
}
