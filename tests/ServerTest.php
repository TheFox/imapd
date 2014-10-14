<?php

#use Zend\Mail\Storage\Writable\Maildir;
use Zend\Mail\Message;
#use Zend\Mail\Storage;
#use Zend\Mail\Storage\Message\File;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Filesystem\Filesystem;

use TheFox\Logger\Logger;
use TheFox\Logger\StreamHandler;
use TheFox\Imap\Server;
use TheFox\Imap\MsgDb;
use TheFox\Imap\Event;
use TheFox\Imap\Storage\DirectoryStorage;
use TheFox\Imap\Storage\TestStorage;

class ServerTest extends PHPUnit_Framework_TestCase{
	
	public function testBasic(){
		$server = new Server('', 0);
		$this->assertTrue($server->getLog() === null);
		
		$server = new Server('', 0);
		$server->setLog(new Logger('test_application'));
		$this->assertTrue($server->getLog() !== null);
	}
	
	public function testGetDefaultStorage(){
		$server = new Server('', 0);
		$server->setLog(new Logger('test_application'));
		$server->init();
		$storage = $server->getDefaultStorage();
		
		$this->assertTrue($storage instanceof DirectoryStorage);
		
		fwrite(STDOUT, 'dir: '.$storage->getPath()."\n");
		
		$filesystem = new Filesystem();
		$filesystem->remove($storage->getPath());
	}
	
	public function testAddStorage(){
		$path1 = './tests/test_mailbox_'.date('Ymd_His').'_'.uniqid('', true);
		$path2 = './tests/test_mailbox_'.date('Ymd_His').'_'.uniqid('', true);
		
		$server = new Server('', 0);
		$server->setLog(new Logger('test_application'));
		$server->init();
		
		$storage1 = new DirectoryStorage();
		$storage1->setPath($path1);
		$server->addStorage($storage1);
		
		$storage2 = new DirectoryStorage();
		$storage2->setPath($path2);
		$server->addStorage($storage2);
		
		$this->assertFileExists($path1);
		$this->assertFileExists($path2);
		
		$this->assertEquals($storage1, $server->getDefaultStorage());
		
		$filesystem = new Filesystem();
		$filesystem->remove($storage1->getPath());
		$filesystem->remove($storage2->getPath());
	}
	
	public function testAddFolder(){
		$path1 = './tests/test_mailbox_'.date('Ymd_His').'_'.uniqid('', true);
		#$path2 = './tests/test_mailbox_'.date('Ymd_His').'_'.uniqid('', true);
		
		$server = new Server('', 0);
		$server->setLog(new Logger('test_application'));
		$server->init();
		
		$storage1 = new DirectoryStorage();
		$storage1->setPath($path1);
		$server->addStorage($storage1);
		
		#$storage2 = new TestStorage();
		#$storage2->setPath($path2);
		#$server->addStorage($storage2);
		
		$server->addFolder('test_dir1');
		$this->assertFileExists($path1.'/test_dir1');
		#$this->assertFileExists($path2.'/test_dir1');
		
		$server->addFolder('test_dir2');
		$this->assertFileExists($path1.'/test_dir2');
		#$this->assertFileExists($path2.'/test_dir2');
		
		$server->addFolder('test_dir2.test_dir3');
		$this->assertFileExists($path1.'/test_dir2/test_dir3');
		#$this->assertFileExists($path2.'/test_dir2_test_dir3');
		
		$server->addFolder('test_dir4.test_dir5');
		$this->assertFileExists($path1.'/test_dir4/test_dir5');
		#$this->assertFileExists($path2.'/test_dir4_test_dir5');
	}
	
	public function testGetFolders1(){
		$path1 = './tests/test_mailbox_'.date('Ymd_His').'_'.uniqid('', true);
		$path2 = './tests/test_mailbox_'.date('Ymd_His').'_'.uniqid('', true);
		
		$log = new Logger('test_application');
		$log->pushHandler(new StreamHandler('php://stdout', Logger::DEBUG));
		
		$server = new Server('', 0);
		$server->setLog($log);
		$server->init();
		
		$storage1 = new DirectoryStorage();
		$storage1->setPath($path1);
		$server->addStorage($storage1);
		
		$storage2 = new TestStorage();
		$storage2->setPath($path2);
		$storage2->setType('temp');
		$server->addStorage($storage2);
		
		$server->addFolder('test_dir1');
		$server->addFolder('test_dir2');
		$server->addFolder('test_dir2.test_dir3');
		
		
		$folders = $server->getFolders('', 'INBOX');
		#\Doctrine\Common\Util\Debug::dump($folders);
		$this->assertEquals(3, count($folders));
		$this->assertEquals($path1.'/test_dir1', $folders[0]);
		$this->assertEquals($path1.'/test_dir2', $folders[1]);
		$this->assertEquals($path1.'/test_dir2/test_dir3', $folders[2]);
		
		$folders = $server->getFolders('', 'test_dir1');
		#\Doctrine\Common\Util\Debug::dump($folders);
		$this->assertEquals(1, count($folders));
		$this->assertEquals($path1.'/test_dir1', $folders[0]);
		
		$folders = $server->getFolders('', 'test_dir2');
		#\Doctrine\Common\Util\Debug::dump($folders);
		$this->assertEquals(1, count($folders));
		$this->assertEquals($path1.'/test_dir2', $folders[0]);
	}
	
	public function testGetFolders2(){
		$path1 = './tests/test_mailbox_'.date('Ymd_His').'_'.uniqid('', true);
		$path2 = './tests/test_mailbox_'.date('Ymd_His').'_'.uniqid('', true);
		
		$log = new Logger('test_application');
		$log->pushHandler(new StreamHandler('php://stdout', Logger::DEBUG));
		
		$server = new Server('', 0);
		$server->setLog($log);
		$server->init();
		
		$storage1 = new DirectoryStorage();
		$storage1->setPath($path1);
		$server->addStorage($storage1);
		
		$storage2 = new TestStorage();
		$storage2->setPath($path2);
		$storage2->setType('temp');
		$server->addStorage($storage2);
		
		$server->addFolder('Drafts');
		$server->addFolder('Trash');
		
		
		$folders = $server->getFolders('', '*');
		#\Doctrine\Common\Util\Debug::dump($folders);
		$this->assertEquals(2, count($folders));
		$this->assertEquals($path1.'/Drafts', $folders[0]);
		$this->assertEquals($path1.'/Trash', $folders[1]);
		
		$folders = $server->getFolders('', 'INBOX');
		#\Doctrine\Common\Util\Debug::dump($folders);
		$this->assertEquals(2, count($folders));
		$this->assertEquals($path1.'/Drafts', $folders[0]);
		$this->assertEquals($path1.'/Trash', $folders[1]);
		
		$folders = $server->getFolders('INBOX', '*');
		#\Doctrine\Common\Util\Debug::dump($folders);
		$this->assertEquals(2, count($folders));
		$this->assertEquals($path1.'/Drafts', $folders[0]);
		$this->assertEquals($path1.'/Trash', $folders[1]);
		
		$folders = $server->getFolders('', 'Drafts');
		#\Doctrine\Common\Util\Debug::dump($folders);
		$this->assertEquals(1, count($folders));
		$this->assertEquals($path1.'/Drafts', $folders[0]);
		
		$folders = $server->getFolders('', 'Trash');
		#\Doctrine\Common\Util\Debug::dump($folders);
		$this->assertEquals(1, count($folders));
		$this->assertEquals($path1.'/Trash', $folders[0]);
		
		$folders = $server->getFolders('Trash', '*');
		#\Doctrine\Common\Util\Debug::dump($folders);
		$this->assertEquals(0, count($folders));
	}
	
	public function testGetNextMsgId(){
		$path1 = './tests/test_mailbox_'.date('Ymd_His').'_'.uniqid('', true);
		
		$log = new Logger('test_application');
		$log->pushHandler(new StreamHandler('php://stdout', Logger::DEBUG));
		
		$server = new Server('', 0);
		$server->setLog($log);
		$server->init();
		
		$storage1 = new DirectoryStorage();
		$storage1->setPath($path1);
		$server->addStorage($storage1);
		
		
		$message = new Message();
		$message->addFrom('thefox21at@gmail.com');
		$message->addTo('thefox@fox21.at');
		$message->setSubject('my_subject 1');
		$message->setBody('my_body');
		$msgId = $server->addMail($message);
		
		$message = new Message();
		$message->addFrom('thefox21at@gmail.com');
		$message->addTo('thefox@fox21.at');
		$message->setSubject('my_subject 2');
		$message->setBody('my_body');
		$msgId = $server->addMail($message);
		
		$message = new Message();
		$message->addFrom('thefox21at@gmail.com');
		$message->addTo('thefox@fox21.at');
		$message->setSubject('my_subject 3');
		$message->setBody('my_body');
		$msgId = $server->addMail($message);
		
		$message = new Message();
		$message->addFrom('thefox21at@gmail.com');
		$message->addTo('thefox@fox21.at');
		$message->setSubject('my_subject 4');
		$message->setBody('my_body');
		$msgId = $server->addMail($message);
		
		$this->assertEquals(100005, $server->getNextMsgId());
	}
	
	public function testGetMsgSeqById(){
		$path1 = './tests/test_mailbox_'.date('Ymd_His').'_'.uniqid('', true);
		
		$log = new Logger('test_application');
		$log->pushHandler(new StreamHandler('php://stdout', Logger::DEBUG));
		
		$server = new Server('', 0);
		$server->setLog($log);
		$server->init();
		
		$storage1 = new DirectoryStorage();
		$storage1->setPath($path1);
		$server->addStorage($storage1);
		
		
		$message = new Message();
		$message->addFrom('thefox21at@gmail.com');
		$message->addTo('thefox@fox21.at');
		$message->setSubject('my_subject 1');
		$message->setBody('my_body');
		$msgId = $server->addMail($message);
		$this->assertEquals(1, $server->getMsgSeqById($msgId));
		
		$message = new Message();
		$message->addFrom('thefox21at@gmail.com');
		$message->addTo('thefox@fox21.at');
		$message->setSubject('my_subject 2');
		$message->setBody('my_body');
		$msgId = $server->addMail($message);
		$this->assertEquals(2, $server->getMsgSeqById($msgId));
		
		$message = new Message();
		$message->addFrom('thefox21at@gmail.com');
		$message->addTo('thefox@fox21.at');
		$message->setSubject('my_subject 3');
		$message->setBody('my_body');
		$msgId = $server->addMail($message);
		$this->assertEquals(3, $server->getMsgSeqById($msgId));
		
		$message = new Message();
		$message->addFrom('thefox21at@gmail.com');
		$message->addTo('thefox@fox21.at');
		$message->setSubject('my_subject 4');
		$message->setBody('my_body');
		$msgId = $server->addMail($message);
		$this->assertEquals(4, $server->getMsgSeqById($msgId));
	}
	
	public function testGetMsgIdBySeq(){
		$path1 = './tests/test_mailbox_'.date('Ymd_His').'_'.uniqid('', true);
		
		$log = new Logger('test_application');
		$log->pushHandler(new StreamHandler('php://stdout', Logger::DEBUG));
		
		$server = new Server('', 0);
		$server->setLog($log);
		$server->init();
		
		$storage1 = new DirectoryStorage();
		$storage1->setPath($path1);
		$server->addStorage($storage1);
		
		
		$message = new Message();
		$message->addFrom('thefox21at@gmail.com');
		$message->addTo('thefox@fox21.at');
		$message->setSubject('my_subject 1');
		$message->setBody('my_body');
		$msgId = $server->addMail($message);
		$this->assertEquals($msgId, $server->getMsgIdBySeq(1));
		
		$message = new Message();
		$message->addFrom('thefox21at@gmail.com');
		$message->addTo('thefox@fox21.at');
		$message->setSubject('my_subject 2');
		$message->setBody('my_body');
		$msgId = $server->addMail($message);
		$this->assertEquals($msgId, $server->getMsgIdBySeq(2));
		
		$message = new Message();
		$message->addFrom('thefox21at@gmail.com');
		$message->addTo('thefox@fox21.at');
		$message->setSubject('my_subject 3');
		$message->setBody('my_body');
		$msgId = $server->addMail($message);
		$this->assertEquals($msgId, $server->getMsgIdBySeq(3));
		
		$message = new Message();
		$message->addFrom('thefox21at@gmail.com');
		$message->addTo('thefox@fox21.at');
		$message->setSubject('my_subject 4');
		$message->setBody('my_body');
		$msgId = $server->addMail($message);
		$this->assertEquals($msgId, $server->getMsgIdBySeq(4));
		
		
		$server->addFolder('test_dir1');
		
		$message = new Message();
		$message->addFrom('thefox21at@gmail.com');
		$message->addTo('thefox@fox21.at');
		$message->setSubject('my_subject 9');
		$message->setBody('my_body');
		$msgId = $server->addMail($message, 'test_dir1');
		#fwrite(STDOUT, 'test_dir1: 1, '.$msgId."\n");
		$this->assertEquals($msgId, $server->getMsgIdBySeq(1, 'test_dir1'));
		
		$message = new Message();
		$message->addFrom('thefox21at@gmail.com');
		$message->addTo('thefox@fox21.at');
		$message->setSubject('my_subject 10');
		$message->setBody('my_body');
		$msgId = $server->addMail($message, 'test_dir1');
		#fwrite(STDOUT, 'test_dir1: 2, '.$msgId."\n");
		$this->assertEquals($msgId, $server->getMsgIdBySeq(2, 'test_dir1'));
		
		
		$server->shutdown();
	}
	
	public function testAddMail(){
		$path1 = './tests/test_mailbox_'.date('Ymd_His').'_'.uniqid('', true);
		
		$log = new Logger('test_application');
		$log->pushHandler(new StreamHandler('php://stdout', Logger::DEBUG));
		
		$server = new Server('', 0);
		$server->setLog($log);
		$server->init();
		
		$storage1 = new DirectoryStorage();
		$storage1->setPath($path1);
		$server->addStorage($storage1);
		
		
		$message = new Message();
		$message->addFrom('thefox21at@gmail.com');
		$message->addTo('thefox@fox21.at');
		$message->setSubject('my_subject 1');
		$message->setBody('my_body');
		$msgId = $server->addMail($message);
		$this->assertEquals(100001, $msgId);
		$this->assertEquals(1, $server->getMsgSeqById($msgId));
		
		
		$message = new Message();
		$message->addFrom('thefox21at@gmail.com');
		$message->addTo('thefox@fox21.at');
		$message->setSubject('my_subject 2');
		$message->setBody('my_body');
		$msgId = $server->addMail($message);
		$this->assertEquals(100002, $msgId);
		$this->assertEquals(2, $server->getMsgSeqById($msgId));
		
		$message = new Message();
		$message->addFrom('thefox21at@gmail.com');
		$message->addTo('thefox@fox21.at');
		$message->setSubject('my_subject 3');
		$message->setBody('my_body');
		$msgId = $server->addMail($message);
		$this->assertEquals(100003, $msgId);
		$this->assertEquals(3, $server->getMsgSeqById($msgId));
		
		$message = new Message();
		$message->addFrom('thefox21at@gmail.com');
		$message->addTo('thefox@fox21.at');
		$message->setSubject('my_subject 4');
		$message->setBody('my_body');
		$msgId = $server->addMail($message);
		$this->assertEquals(100004, $msgId);
		$this->assertEquals(4, $server->getMsgSeqById($msgId));
		
		$message = new Message();
		$message->addFrom('thefox21at@gmail.com');
		$message->addTo('thefox@fox21.at');
		$message->setSubject('my_subject 5');
		$message->setBody('my_body');
		$msgId = $server->addMail($message);
		$this->assertEquals(100005, $msgId);
		$this->assertEquals(5, $server->getMsgSeqById($msgId));
		
		$message = new Message();
		$message->addFrom('thefox21at@gmail.com');
		$message->addTo('thefox@fox21.at');
		$message->setSubject('my_subject 6');
		$message->setBody('my_body');
		$msgId = $server->addMail($message);
		$this->assertEquals(100006, $msgId);
		$this->assertEquals(6, $server->getMsgSeqById($msgId));
		
		$message = new Message();
		$message->addFrom('thefox21at@gmail.com');
		$message->addTo('thefox@fox21.at');
		$message->setSubject('my_subject 7');
		$message->setBody('my_body');
		$msgId = $server->addMail($message, null, null, false);
		$this->assertEquals(100007, $msgId);
		$this->assertEquals(7, $server->getMsgSeqById($msgId));
		
		$message = new Message();
		$message->addFrom('thefox21at@gmail.com');
		$message->addTo('thefox@fox21.at');
		$message->setSubject('my_subject 8');
		$message->setBody('my_body');
		$msgId = $server->addMail($message, null, null, false);
		$this->assertEquals(100008, $msgId);
		$this->assertEquals(8, $server->getMsgSeqById($msgId));
		
		$finder = new Finder();
		$files = $finder->in($path1)->files();
		$this->assertEquals(8, count($files));
		
		
		
		$server->addFolder('test_dir1');
		
		$message = new Message();
		$message->addFrom('thefox21at@gmail.com');
		$message->addTo('thefox@fox21.at');
		$message->setSubject('my_subject 9');
		$message->setBody('my_body');
		$msgId = $server->addMail($message, 'test_dir1');
		$this->assertEquals(100009, $msgId);
		$this->assertEquals(1, $server->getMsgSeqById($msgId));
		
		$message = new Message();
		$message->addFrom('thefox21at@gmail.com');
		$message->addTo('thefox@fox21.at');
		$message->setSubject('my_subject 10');
		$message->setBody('my_body');
		$msgId = $server->addMail($message, 'test_dir1');
		$this->assertEquals(100010, $msgId);
		$this->assertEquals(2, $server->getMsgSeqById($msgId));
		
		
		$server->shutdown();
	}
	
	public function testRemoveMail1(){
		$path1 = './tests/test_mailbox_'.date('Ymd_His').'_'.uniqid('', true);
		
		$log = new Logger('test_application');
		$log->pushHandler(new StreamHandler('php://stdout', Logger::DEBUG));
		
		$server = new Server('', 0);
		$server->setLog($log);
		$server->init();
		
		$storage1 = new DirectoryStorage();
		$storage1->setPath($path1);
		$server->addStorage($storage1);
		
		
		$message = new Message();
		$message->addFrom('thefox21at@gmail.com');
		$message->addTo('thefox@fox21.at');
		$message->setSubject('my_subject 1');
		$message->setBody('my_body');
		$msgId = $server->addMail($message);
		fwrite(STDOUT, 'msgId: '.$msgId."\n");
		
		$message = new Message();
		$message->addFrom('thefox21at@gmail.com');
		$message->addTo('thefox@fox21.at');
		$message->setSubject('my_subject 2');
		$message->setBody('my_body');
		$msgId = $server->addMail($message);
		fwrite(STDOUT, 'msgId: '.$msgId."\n");
		
		$message = new Message();
		$message->addFrom('thefox21at@gmail.com');
		$message->addTo('thefox@fox21.at');
		$message->setSubject('my_subject 3');
		$message->setBody('my_body');
		$msgId = $server->addMail($message);
		fwrite(STDOUT, 'msgId: '.$msgId."\n");
		
		$message = new Message();
		$message->addFrom('thefox21at@gmail.com');
		$message->addTo('thefox@fox21.at');
		$message->setSubject('my_subject 4');
		$message->setBody('my_body');
		$msgId = $server->addMail($message);
		fwrite(STDOUT, 'msgId: '.$msgId."\n");
		
		$message = new Message();
		$message->addFrom('thefox21at@gmail.com');
		$message->addTo('thefox@fox21.at');
		$message->setSubject('my_subject 5');
		$message->setBody('my_body');
		$msgId = $server->addMail($message);
		fwrite(STDOUT, 'msgId: '.$msgId."\n");
		
		$message = new Message();
		$message->addFrom('thefox21at@gmail.com');
		$message->addTo('thefox@fox21.at');
		$message->setSubject('my_subject 6');
		$message->setBody('my_body');
		$msgId = $server->addMail($message);
		fwrite(STDOUT, 'msgId: '.$msgId."\n");
		
		
		$finder = new Finder();
		$files = $finder->in($path1)->files()->depth(0)->name('*.eml');
		$this->assertEquals(6, count($files));
		
		$server->removeMail(100002);
		
		$finder = new Finder();
		$files = $finder->in($path1)->files()->depth(0)->name('*.eml');
		$this->assertEquals(5, count($files));
		
		$this->assertEquals(4, $server->getMsgSeqById(100005));
		$this->assertEquals(5, $server->getMsgSeqById(100006));
		
		$server->shutdown();
	}
	
	public function testRemoveMail2(){
		$path1 = './tests/test_mailbox_'.date('Ymd_His').'_'.uniqid('', true);
		
		$log = new Logger('test_application');
		$log->pushHandler(new StreamHandler('php://stdout', Logger::DEBUG));
		
		$server = new Server('', 0);
		$server->setLog($log);
		$server->init();
		
		$storage1 = new DirectoryStorage();
		$storage1->setPath($path1);
		$server->addStorage($storage1);
		
		
		$message = new Message();
		$message->addFrom('thefox21at@gmail.com');
		$message->addTo('thefox@fox21.at');
		$message->setSubject('my_subject 1');
		$message->setBody('my_body');
		$tmpId = $server->addMail($message);
		fwrite(STDOUT, 'tmpId: '.$tmpId."\n");
		
		$message = new Message();
		$message->addFrom('thefox21at@gmail.com');
		$message->addTo('thefox@fox21.at');
		$message->setSubject('my_subject 2');
		$message->setBody('my_body');
		$tmpId = $server->addMail($message);
		fwrite(STDOUT, 'tmpId: '.$tmpId."\n");
		$msgId = $tmpId;
		
		$message = new Message();
		$message->addFrom('thefox21at@gmail.com');
		$message->addTo('thefox@fox21.at');
		$message->setSubject('my_subject 3');
		$message->setBody('my_body');
		$tmpId = $server->addMail($message);
		fwrite(STDOUT, 'tmpId: '.$tmpId."\n");
		
		$message = new Message();
		$message->addFrom('thefox21at@gmail.com');
		$message->addTo('thefox@fox21.at');
		$message->setSubject('my_subject 4');
		$message->setBody('my_body');
		$tmpId = $server->addMail($message);
		fwrite(STDOUT, 'tmpId: '.$tmpId."\n");
		
		$message = new Message();
		$message->addFrom('thefox21at@gmail.com');
		$message->addTo('thefox@fox21.at');
		$message->setSubject('my_subject 5');
		$message->setBody('my_body');
		$tmpId = $server->addMail($message);
		fwrite(STDOUT, 'tmpId: '.$tmpId."\n");
		
		$message = new Message();
		$message->addFrom('thefox21at@gmail.com');
		$message->addTo('thefox@fox21.at');
		$message->setSubject('my_subject 6');
		$message->setBody('my_body');
		$tmpId = $server->addMail($message);
		fwrite(STDOUT, 'tmpId: '.$tmpId."\n");
		
		
		$finder = new Finder();
		$files = $finder->in($path1)->files()->depth(0)->name('*.eml');
		$this->assertEquals(6, count($files));
		
		$server->removeMailBySequenceNum(4);
		
		$finder = new Finder();
		$files = $finder->in($path1)->files()->depth(0)->name('*.eml');
		$this->assertEquals(5, count($files));
		
		$this->assertEquals(null, $server->getMsgSeqById(100004));
		$this->assertEquals(4, $server->getMsgSeqById(100005));
		$this->assertEquals(5, $server->getMsgSeqById(100006));
		
		$server->shutdown();
	}
	
	public function testMailCopy1(){
		$path1 = './tests/test_mailbox_'.date('Ymd_His').'_'.uniqid('', true);
		
		$log = new Logger('test_application');
		$log->pushHandler(new StreamHandler('php://stdout', Logger::DEBUG));
		
		$server = new Server('', 0);
		$server->setLog($log);
		$server->init();
		
		$storage1 = new DirectoryStorage();
		$storage1->setPath($path1);
		$server->addStorage($storage1);
		
		
		$message = new Message();
		$message->addFrom('thefox21at@gmail.com');
		$message->addTo('thefox@fox21.at');
		$message->setSubject('my_subject 1');
		$message->setBody('my_body');
		$tmpId = $server->addMail($message);
		fwrite(STDOUT, 'tmpId: '.$tmpId."\n");
		
		$message = new Message();
		$message->addFrom('thefox21at@gmail.com');
		$message->addTo('thefox@fox21.at');
		$message->setSubject('my_subject 2');
		$message->setBody('my_body');
		$tmpId = $server->addMail($message);
		fwrite(STDOUT, 'tmpId: '.$tmpId."\n");
		
		$message = new Message();
		$message->addFrom('thefox21at@gmail.com');
		$message->addTo('thefox@fox21.at');
		$message->setSubject('my_subject 3');
		$message->setBody('my_body');
		$tmpId = $server->addMail($message);
		fwrite(STDOUT, 'tmpId: '.$tmpId."\n");
		
		$message = new Message();
		$message->addFrom('thefox21at@gmail.com');
		$message->addTo('thefox@fox21.at');
		$message->setSubject('my_subject 4');
		$message->setBody('my_body');
		$tmpId = $server->addMail($message);
		fwrite(STDOUT, 'tmpId: '.$tmpId."\n");
		
		$message = new Message();
		$message->addFrom('thefox21at@gmail.com');
		$message->addTo('thefox@fox21.at');
		$message->setSubject('my_subject 5');
		$message->setBody('my_body');
		$tmpId = $server->addMail($message);
		fwrite(STDOUT, 'tmpId: '.$tmpId."\n");
		
		$message = new Message();
		$message->addFrom('thefox21at@gmail.com');
		$message->addTo('thefox@fox21.at');
		$message->setSubject('my_subject 6');
		$message->setBody('my_body');
		$tmpId = $server->addMail($message);
		fwrite(STDOUT, 'tmpId: '.$tmpId."\n");
		
		
		$finder = new Finder();
		$files = $finder->in($path1)->files()->depth(0)->name('*.eml');
		$this->assertEquals(6, count($files));
		
		
		$server->addFolder('test_dir1');
		
		$server->mailCopy(100002, 'test_dir1');
		$server->mailCopy(100004, 'test_dir1');
		
		$this->assertEquals(1, $server->getMsgSeqById(100007));
		$this->assertEquals(2, $server->getMsgSeqById(100008));
		
		$server->shutdown();
	}
	
	public function testShutdownStorages(){
		$path1 = './tests/test_mailbox_'.date('Ymd_His').'_'.uniqid('', true);
		$path2 = './tests/test_mailbox_'.date('Ymd_His').'_'.uniqid('', true);
		
		$server = new Server('', 0);
		$server->setLog(new Logger('test_application'));
		$server->init();
		
		$storage1 = new DirectoryStorage();
		$storage1->setPath($path1);
		$server->addStorage($storage1);
		
		$storage2 = new TestStorage();
		$storage2->setPath($path2);
		$storage2->setType('temp');
		$server->addStorage($storage2);
		
		$server->addFolder('test_dir1');
		$server->shutdownStorages();
		
		$this->assertTrue(file_exists($path1));
		$this->assertFalse(file_exists($path2));
	}
	
	
	/*
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
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
		$tmpId = $server->addMail($message);
		#fwrite(STDOUT, 'tmpId: '.$tmpId."\n");
		
		$message = new Message();
		$message->addFrom('thefox21at@gmail.com');
		$message->addTo('thefox@fox21.at');
		$message->setSubject('my_subject 2');
		$message->setBody('my_body');
		$tmpId = $server->addMail($message);
		#fwrite(STDOUT, 'tmpId: '.$tmpId."\n");
		
		$message = new Message();
		$message->addFrom('thefox21at@gmail.com');
		$message->addTo('thefox@fox21.at');
		$message->setSubject('my_subject 3');
		$message->setBody('my_body');
		$tmpId = $server->addMail($message);
		#fwrite(STDOUT, 'tmpId: '.$tmpId."\n");
		
		$message = new Message();
		$message->addFrom('thefox21at@gmail.com');
		$message->addTo('thefox@fox21.at');
		$message->setSubject('my_subject 4');
		$message->setBody('my_body');
		$tmpId = $server->addMail($message);
		#fwrite(STDOUT, 'tmpId: '.$tmpId."\n");
		
		$message = new Message();
		$message->addFrom('thefox21at@gmail.com');
		$message->addTo('thefox@fox21.at');
		$message->setSubject('my_subject 5');
		$message->setBody('my_body');
		$tmpId = $server->addMail($message);
		#fwrite(STDOUT, 'tmpId: '.$tmpId."\n");
		
		$message = new Message();
		$message->addFrom('thefox21at@gmail.com');
		$message->addTo('thefox@fox21.at');
		$message->setSubject('my_subject 6');
		$message->setBody('my_body');
		$tmpId = $server->addMail($message);
		#fwrite(STDOUT, 'tmpId: '.$tmpId."\n");
		
		$finder = new Finder();
		$files = $finder->in($maildirPath.'/new')->files();
		$this->assertEquals(6, count($files));
		
		$server->addFolder('test_dir1');
		
		$server->mailCopyBySequenceNum(2, 'test_dir1');
		$server->mailCopyBySequenceNum(4, 'test_dir1');
		
		$this->assertEquals(1, $server->getMsgSeqById(100007));
		$this->assertEquals(2, $server->getMsgSeqById(100008));
		
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
		$msgId = $server->addMail($message);
		
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
		$phpunit = $this;
		$event1 = new Event(Event::TRIGGER_MAIL_ADD_PRE, null, function($event) use($phpunit, &$testData) {
			#fwrite(STDOUT, 'my function: '.$event->getTrigger().', '.$testData."\n");
			$phpunit->assertEquals(21, $testData);
			
			$testData = 24;
			
			return 42;
		});
		$server->eventAdd($event1);
		
		$event2 = new Event(Event::TRIGGER_MAIL_ADD_PRE, $this, 'functionForTestEvent');
		$server->eventAdd($event2);
		
		$event3 = new Event(Event::TRIGGER_MAIL_ADD, null, function($event, $mail) use($phpunit) {
			$phpunit->assertTrue(is_object($mail));
			$phpunit->assertEquals('my_subject 1', $mail->getSubject());
		});
		$server->eventAdd($event3);
		
		$event4 = new Event(Event::TRIGGER_MAIL_ADD_POST, null, function($event, $msgId) use($phpunit) {
			$phpunit->assertEquals(100001, $msgId);
		});
		$server->eventAdd($event4);
		
		
		$message = new Message();
		$message->addFrom('thefox21at@gmail.com');
		$message->addTo('thefox@fox21.at');
		$message->setSubject('my_subject 1');
		$message->setBody('my_body');
		$server->addMail($message);
		
		$this->assertEquals(24, $testData);
		$this->assertEquals(42, $event1->getReturnValue());
		$this->assertEquals(18, $event2->getReturnValue());
		$this->assertEquals(null, $event3->getReturnValue());
		$this->assertEquals(null, $event4->getReturnValue());
	}
	*/
}
