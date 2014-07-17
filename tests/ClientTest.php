<?php

use Zend\Mail\Message;
use Zend\Mail\Storage;
use Zend\Mail\Headers;
use Zend\Mail\Header\Date;
use Symfony\Component\Finder\Finder;

use TheFox\Imap\Server;
use TheFox\Imap\Client;

class ClientTest extends PHPUnit_Framework_TestCase{
	
	public function providerMsgGetArgs(){
		$rv = array();
		$expect = array('tag' => 'TAG1', 'command' => 'cmd2', 'args' => array());
		
		$expect['args'] = array('arg1');
		$rv[] = array('TAG1 cmd2 "arg1"', $expect);
		$rv[] = array('TAG1 cmd2 "arg1" ', $expect);
		
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
     * @dataProvider providerMsgGetArgs
     */
	public function testMsgGetArgs($msgRaw, $expect){
		$client = new Client();
		$this->assertEquals($expect, $client->msgGetArgs($msgRaw));
	}
	
	public function providerMsgRawParenthesizedlist(){
		$rv = array(
			array('', array()),
			array('aaa', array('aaa')),
			array('()', array()),
			array('(a)', array('a')),
			array('("a")', array('a')),
			array('(a b)', array('a', 'b')),
			array('(a "b")', array('a', 'b')),
			array('(a "bc def")', array('a', 'bc def')),
			array('(aa bbb "cccc")', array('aa', 'bbb', 'cccc')),
			array('(aa bbb "cccc dd")', array('aa', 'bbb', 'cccc dd')),
			array('((aa bbb "cccc") dd)', array(array('aa', 'bbb', 'cccc'), 'dd')),
			array('(aa bbb cccc) dd', array(array('aa', 'bbb', 'cccc'), 'dd')),
			array('aa (bbb cccc)', array('aa', array('bbb', 'cccc'))),
			array('(aa bbb "cccc") dd', array(array('aa', 'bbb', 'cccc'), 'dd')),
			array('aa (bbb "cccc") dd', array('aa', array('bbb', 'cccc'), 'dd')),
			array('(a bb ccc)', array('a', 'bb', 'ccc')),
			array('(a (bb) ccc)', array('a', array('bb'), 'ccc')),
			array('(a (bb ccc) dddd)', array('a', array('bb', 'ccc'), 'dddd')),
			array('(a (bb ccc dddd) eeeee)', array('a', array('bb', 'ccc', 'dddd'), 'eeeee')),
			array('(a ((bb ccc) dddd) eeeee)', array('a', array(array('bb', 'ccc'), 'dddd'), 'eeeee')),
			array('(a bb "ccc") dddd', array(array('a', 'bb', 'ccc'), 'dddd')),
			array('("ccc" a bb) dddd', array(array('ccc', 'a', 'bb'), 'dddd')),
			array('BEFORE X', array('BEFORE', 'X')),
			array('BEFORE 1990', array('BEFORE', '1990')),
		);
		
		$raw = '(UID RFC822.SIZE FLAGS BODY.PEEK[HEADER.FIELDS (From To Cc Bcc Subject Date ';
		$raw .= 'Message-ID Priority X-Priority References Newsgroups In-Reply-To Content-Type Reply-To)])';
		
		$expect = array(
			'UID', 'RFC822.SIZE', 'FLAGS', 'BODY.PEEK',
			array(
				'HEADER.FIELDS',
				array('From', 'To', 'Cc', 'Bcc', 'Subject', 'Date', 'Message-ID', 'Priority', 'X-Priority',
				'References', 'Newsgroups', 'In-Reply-To', 'Content-Type', 'Reply-To'),
			),
		);
		$rv[] = array($raw, $expect);
		
		return $rv;
	}
	
	/**
     * @dataProvider providerMsgRawParenthesizedlist
     */
	public function testMsgGetParenthesizedlist($msgRaw, $expect){
		$client = new Client();
		$this->assertEquals($expect, $client->msgGetParenthesizedlist($msgRaw));
	}
	
	public function testCreateSequenceSet(){
		$maildirPath = './tests/test_mailbox_'.date('Ymd_His').'_'.uniqid('', true);
		
		$server = new Server('', 0);
		$server->init();
		$server->storageAddMaildir($maildirPath);
		
		$client = new Client();
		$client->setServer($server);
		$client->setId(1);
		$client->setStatus('hasAuth', true);
		$server->storageFolderAdd('test_dir1');
		$client->msgHandle('6 select test_dir1');
		
		$message = new Message();
		$message->addFrom('thefox21at@gmail.com');
		$message->addTo('thefox@fox21.at');
		$message->setSubject('my_subject 1');
		$message->setBody('my_body');
		$server->mailAdd($message->toString());
		
		$message = new Message();
		$message->addFrom('thefox21at@gmail.com');
		$message->addTo('thefox@fox21.at');
		$message->setSubject('my_subject 2');
		$message->setBody('my_body');
		$server->mailAdd($message->toString());
		
		$message = new Message();
		$message->addFrom('thefox21at@gmail.com');
		$message->addTo('thefox@fox21.at');
		$message->setSubject('my_subject 3');
		$message->setBody('my_body');
		$server->mailAdd($message->toString());
		
		$message = new Message();
		$message->addFrom('thefox21at@gmail.com');
		$message->addTo('thefox@fox21.at');
		$message->setSubject('my_subject 4');
		$message->setBody('my_body');
		$server->mailAdd($message->toString());
		
		$message = new Message();
		$message->addFrom('thefox21at@gmail.com');
		$message->addTo('thefox@fox21.at');
		$message->setSubject('my_subject 5');
		$message->setBody('my_body');
		$server->mailAdd($message->toString());
		
		$message = new Message();
		$message->addFrom('thefox21at@gmail.com');
		$message->addTo('thefox@fox21.at');
		$message->setSubject('my_subject 6');
		$message->setBody('my_body');
		$server->mailAdd($message->toString());
		
		
		$seq = $client->createSequenceSet('1');
		$this->assertEquals(array(1), $seq);
		
		$seq = $client->createSequenceSet('3');
		$this->assertEquals(array(3), $seq);
		
		$seq = $client->createSequenceSet('3,5');
		$this->assertEquals(array(3, 5), $seq);
		
		$seq = $client->createSequenceSet('3,5,6,4');
		$this->assertEquals(array(3, 4, 5, 6), $seq);
		
		$seq = $client->createSequenceSet('3, 5');
		$this->assertEquals(array(3, 5), $seq);
		
		$seq = $client->createSequenceSet('3:3');
		$this->assertEquals(array(3), $seq);
		
		$seq = $client->createSequenceSet('3:4');
		$this->assertEquals(array(3, 4), $seq);
		
		$seq = $client->createSequenceSet('3:5');
		$this->assertEquals(array(3, 4, 5), $seq);
		
		$seq = $client->createSequenceSet('3:5,2');
		$this->assertEquals(array(2, 3, 4, 5), $seq);
		
		$seq = $client->createSequenceSet('*');
		$this->assertEquals(array(1, 2, 3, 4, 5, 6), $seq);
		
		$seq = $client->createSequenceSet('3:*');
		$this->assertEquals(array(3, 4, 5, 6), $seq);
		
		$seq = $client->createSequenceSet('3:*,2');
		$this->assertEquals(array(2, 3, 4, 5, 6), $seq);
		
		
		$seq = $client->createSequenceSet('100001', true);
		$this->assertEquals(array(1), $seq);
		
		$seq = $client->createSequenceSet('100002', true);
		$this->assertEquals(array(2), $seq);
		
		$seq = $client->createSequenceSet('100002,100004', true);
		$this->assertEquals(array(2, 4), $seq);
		
		$seq = $client->createSequenceSet('100002, 100004', true);
		$this->assertEquals(array(2, 4), $seq);
		
		$seq = $client->createSequenceSet('100002,100005,100004,100003', true);
		$this->assertEquals(array(2, 3, 4, 5), $seq);
		
		$seq = $client->createSequenceSet('100002:100002', true);
		$this->assertEquals(array(2), $seq);
		
		$seq = $client->createSequenceSet('100002:100003', true);
		$this->assertEquals(array(2, 3), $seq);
		
		$seq = $client->createSequenceSet('100002:100004', true);
		$this->assertEquals(array(2, 3, 4), $seq);
		
		$seq = $client->createSequenceSet('100002:100004,100005', true);
		$this->assertEquals(array(2, 3, 4, 5), $seq);
		
		$seq = $client->createSequenceSet('*', true);
		$this->assertEquals(array(1, 2, 3, 4, 5, 6), $seq);
		
		$seq = $client->createSequenceSet('100002:*', true);
		$this->assertEquals(array(2, 3, 4, 5, 6), $seq);
		
		$seq = $client->createSequenceSet('100002:*,100001', true);
		$this->assertEquals(array(1, 2, 3, 4, 5, 6), $seq);
		
		$seq = $client->createSequenceSet('100007', true);
		$this->assertEquals(array(), $seq);
		
		$seq = $client->createSequenceSet('999999:*', true);
		$this->assertEquals(array(6), $seq);
	}
	
	public function providerMsgHandle(){
		$rv = array(
			array('NO_COMMAND', 'NO_COMMAND BAD Not implemented: "NO_COMMAND" ""'),
			array('1 NO_COMMAND', '1 BAD Not implemented: "1" "NO_COMMAND"'),
		);
		
		foreach($rv as $cn => $case){
			$rv[$cn][1] .= Client::MSG_SEPARATOR;
		}
		
		return $rv;
	}
	
	/**
     * @dataProvider providerMsgHandle
     */
	public function testMsgHandleBasic($msgRaw, $expect){
		$server = new Server('', 0);
		$server->init();
		
		$client = new Client();
		$client->setServer($server);
		$client->setId(1);
		
		$msg = $client->msgHandle($msgRaw);
		
		$this->assertEquals($expect, $msg);
		
		$server->shutdown();
	}
	
	public function testMsgHandleCapability(){
		$server = new Server('', 0);
		$server->init();
		
		$client = new Client();
		$client->setServer($server);
		$client->setId(1);
		
		$msg = $client->msgHandle('1 capability');
		$this->assertEquals('* CAPABILITY IMAP4rev1 AUTH=PLAIN'.Client::MSG_SEPARATOR.'1 OK CAPABILITY completed'.Client::MSG_SEPARATOR, $msg);
	}
	
	public function testMsgHandleNoop(){
		$server = new Server('', 0);
		$server->init();
		
		$client = new Client();
		$client->setServer($server);
		$client->setId(1);
		
		$msg = $client->msgHandle('2 NOOP');
		$this->assertEquals('2 OK NOOP completed client 1, ""'.Client::MSG_SEPARATOR, $msg);
		
		$server->shutdown();
	}
	
	public function testMsgHandleLogout(){
		$server = new Server('', 0);
		$server->init();
		
		$client = new Client();
		$client->setServer($server);
		$client->setId(1);
		
		$msg = $client->msgHandle('3 LOGOUT');
		$this->assertEquals('* BYE IMAP4rev1 Server logging out'.Client::MSG_SEPARATOR.'3 OK LOGOUT completed'.Client::MSG_SEPARATOR, $msg);
	}
	
	public function testMsgHandleAuthenticate(){
		$server = new Server('', 0);
		$server->init();
		
		$client = new Client();
		$client->setServer($server);
		$client->setId(1);
		
		$msg = $client->msgHandle('4 authenticate plain');
		$this->assertEquals('+'.Client::MSG_SEPARATOR, $msg);
		
		$msg = $client->msgHandle('AHRoZWZveAB0ZXN0');
		$this->assertEquals('4 OK plain authentication successful'.Client::MSG_SEPARATOR, $msg);
	}
	
	public function testMsgHandleLogin(){
		$server = new Server('', 0);
		$server->init();
		
		$client = new Client();
		$client->setServer($server);
		$client->setId(1);
		
		$msg = $client->msgHandle('3 LOGIN');
		$this->assertEquals('3 BAD Arguments invalid.'.Client::MSG_SEPARATOR, $msg);
		
		$msg = $client->msgHandle('3 LOGIN user');
		$this->assertEquals('3 BAD Arguments invalid.'.Client::MSG_SEPARATOR, $msg);
		
		$msg = $client->msgHandle('3 LOGIN user password');
		$this->assertEquals('3 OK LOGIN completed'.Client::MSG_SEPARATOR, $msg);
	}
	
	public function testMsgHandleSelect(){
		$server = new Server('', 0);
		$server->init();
		$server->storageAddMaildir('./tests/test_mailbox_'.date('Ymd_His').'_'.uniqid('', true));
		
		$client = new Client();
		$client->setServer($server);
		$client->setId(1);
		
		$msg = $client->msgHandle('6 select test_dir');
		$this->assertEquals('6 NO select failure'.Client::MSG_SEPARATOR, $msg);
		
		$client->setStatus('hasAuth', true);
		$msg = $client->msgHandle('6 select');
		$this->assertEquals('6 BAD Arguments invalid.'.Client::MSG_SEPARATOR, $msg);
		
		$msg = $client->msgHandle('6 select test_dir');
		$this->assertEquals('6 NO "test_dir" no such mailbox'.Client::MSG_SEPARATOR, $msg);
		
		$server->storageFolderAdd('test_dir');
		$msg = $client->msgHandle('6 select test_dir');
		$this->assertEquals('6 OK [READ-WRITE] SELECT completed'.Client::MSG_SEPARATOR, $msg);
	}
	
	public function testMsgHandleCreate(){
		$server = new Server('', 0);
		$server->init();
		$server->storageAddMaildir('./tests/test_mailbox_'.date('Ymd_His').'_'.uniqid('', true));
		
		$client = new Client();
		$client->setServer($server);
		$client->setId(1);
		
		$msg = $client->msgHandle('7 create');
		$this->assertEquals('7 NO create failure'.Client::MSG_SEPARATOR, $msg);
		
		$msg = $client->msgHandle('7 create test_dir');
		$this->assertEquals('7 NO create failure'.Client::MSG_SEPARATOR, $msg);
		
		$client->setStatus('hasAuth', true);
		$msg = $client->msgHandle('7 create test_dir');
		$this->assertEquals('7 OK CREATE completed'.Client::MSG_SEPARATOR, $msg);
		
		$msg = $client->msgHandle('7 create test_dir');
		$this->assertEquals('7 NO CREATE failure: folder already exists'.Client::MSG_SEPARATOR, $msg);
		
		$msg = $client->msgHandle('7 create');
		$this->assertEquals('7 BAD Arguments invalid.'.Client::MSG_SEPARATOR, $msg);
		
		$msg = $client->msgHandle('7 create test_dir/test_subdir1');
		$this->assertEquals('7 NO CREATE failure: invalid name - no directory separator allowed in folder name'.Client::MSG_SEPARATOR, $msg);
		
		$msg = $client->msgHandle('7 create test_dir.test_subdir2');
		$this->assertEquals('7 OK CREATE completed'.Client::MSG_SEPARATOR, $msg);
	}
	
	public function testMsgHandleSubscribe(){
		$server = new Server('', 0);
		$server->init();
		$server->storageAddMaildir('./tests/test_mailbox_'.date('Ymd_His').'_'.uniqid('', true));
		
		$client = new Client();
		$client->setServer($server);
		$client->setId(1);
		
		$msg = $client->msgHandle('8 subscribe');
		$this->assertEquals('8 NO subscribe failure'.Client::MSG_SEPARATOR, $msg);
		
		$msg = $client->msgHandle('8 subscribe test_dir');
		$this->assertEquals('8 NO subscribe failure'.Client::MSG_SEPARATOR, $msg);
		
		$client->setStatus('hasAuth', true);
		
		$msg = $client->msgHandle('8 subscribe');
		$this->assertEquals('8 BAD Arguments invalid.'.Client::MSG_SEPARATOR, $msg);
		
		$msg = $client->msgHandle('8 subscribe test_dir');
		$this->assertEquals('8 NO SUBSCRIBE failure: no subfolder named test_dir'.Client::MSG_SEPARATOR, $msg);
		
		$server->storageFolderAdd('test_dir');
		$msg = $client->msgHandle('8 subscribe test_dir');
		$this->assertEquals('8 OK SUBSCRIBE completed'.Client::MSG_SEPARATOR, $msg);
	}
	
	public function testMsgHandleUnsubscribe(){
		$server = new Server('', 0);
		$server->init();
		$server->storageAddMaildir('./tests/test_mailbox_'.date('Ymd_His').'_'.uniqid('', true));
		
		$client = new Client();
		$client->setServer($server);
		$client->setId(1);
		
		$msg = $client->msgHandle('9 unsubscribe test_dir');
		$this->assertEquals('9 NO unsubscribe failure'.Client::MSG_SEPARATOR, $msg);
		
		$client->setStatus('hasAuth', true);
		
		$msg = $client->msgHandle('9 unsubscribe');
		$this->assertEquals('9 BAD Arguments invalid.'.Client::MSG_SEPARATOR, $msg);
		
		$msg = $client->msgHandle('9 unsubscribe test_dir');
		$this->assertEquals('9 NO UNSUBSCRIBE failure: no subfolder named test_dir'.Client::MSG_SEPARATOR, $msg);
		
		$server->storageFolderAdd('test_dir');
		$msg = $client->msgHandle('9 unsubscribe test_dir');
		$this->assertEquals('9 OK UNSUBSCRIBE completed'.Client::MSG_SEPARATOR, $msg);
	}
	
	public function testMsgHandleList(){
		$server = new Server('', 0);
		$server->init();
		$server->storageAddMaildir('./tests/test_mailbox_'.date('Ymd_His').'_'.uniqid('', true));
		
		$client = new Client();
		$client->setServer($server);
		$client->setId(1);
		
		#$msg = $client->msgHandle('10 LIST');
		#$this->assertEquals('10 NO list failure'.Client::MSG_SEPARATOR, $msg);
		
		$client->setStatus('hasAuth', true);
		
		$msg = $client->msgHandle('10 LIST');
		$this->assertEquals('10 BAD Arguments invalid.'.Client::MSG_SEPARATOR, $msg);
		
		$msg = $client->msgHandle('10 LIST test_dir1');
		$this->assertEquals('10 BAD Arguments invalid.'.Client::MSG_SEPARATOR, $msg);
		
		$msg = $client->msgHandle('10 LIST test_dir1.*');
		$this->assertEquals('10 BAD Arguments invalid.'.Client::MSG_SEPARATOR, $msg);
		
		$msg = $client->msgHandle('10 LIST "" test_dir1');
		$this->assertEquals('10 OK LIST completed'.Client::MSG_SEPARATOR, $msg);
		
		$msg = $client->msgHandle('10 LIST "" test_dir1.*');
		$this->assertEquals('10 OK LIST completed'.Client::MSG_SEPARATOR, $msg);
		
		$msg = $client->msgHandle('10 LIST "" INBOX');
		$this->assertEquals('* LIST () "." "INBOX"'.Client::MSG_SEPARATOR.'10 OK LIST completed'.Client::MSG_SEPARATOR, $msg);
		
		$server->storageFolderAdd('test_dir1');
		
		$msg = $client->msgHandle('10 LIST "" test_dir1');
		$this->assertEquals('* LIST () "." "test_dir1"'.Client::MSG_SEPARATOR.'10 OK LIST completed'.Client::MSG_SEPARATOR, $msg);
		
		$msg = $client->msgHandle('10 LIST "" test_dir1.*');
		$this->assertEquals('10 OK LIST completed'.Client::MSG_SEPARATOR, $msg);
		
		$server->storageFolderAdd('test_dir1.test_subdir2');
		
		#$msg = $client->msgHandle('10 LIST "" test_dir1.*');
		#$this->assertEquals('* LIST () "." "test_dir1.test_subdir2"'.Client::MSG_SEPARATOR.'10 OK LIST completed'.Client::MSG_SEPARATOR, $msg);
		
		$msg = $client->msgHandle('10 LIST "test_dir1" test_sub*');
		$this->assertEquals('* LIST () "." "test_dir1.test_subdir2"'.Client::MSG_SEPARATOR.'10 OK LIST completed'.Client::MSG_SEPARATOR, $msg);
	}
	
	public function testMsgHandleLsub(){
		$server = new Server('', 0);
		$server->init();
		$server->storageAddMaildir('./tests/test_mailbox_'.date('Ymd_His').'_'.uniqid('', true));
		
		$client = new Client();
		$client->setServer($server);
		$client->setId(1);
		
		$msg = $client->msgHandle('11 lsub');
		$this->assertEquals('11 NO lsub failure'.Client::MSG_SEPARATOR, $msg);
		
		$client->setStatus('hasAuth', true);
		
		$msg = $client->msgHandle('11 lsub');
		$this->assertEquals('11 BAD Arguments invalid.'.Client::MSG_SEPARATOR, $msg);
		
		$msg = $client->msgHandle('11 lsub test_dir');
		$this->assertEquals('11 OK LSUB completed'.Client::MSG_SEPARATOR, $msg);
		
		$server->storageFolderAdd('test_dir1');
		
		$client->msgHandle('8 subscribe test_dir1');
		$msg = $client->msgHandle('11 lsub test_dir1');
		$this->assertEquals('* LSUB () "." "test_dir1"'.Client::MSG_SEPARATOR.'11 OK LSUB completed'.Client::MSG_SEPARATOR, $msg);
		
		$server->storageFolderAdd('test_dir2');
		$client->msgHandle('8 subscribe test_dir2');
		$msg = $client->msgHandle('11 lsub test_dir2');
		$this->assertEquals('* LSUB () "." "test_dir1"'.Client::MSG_SEPARATOR.'* LSUB () "." "test_dir2"'.Client::MSG_SEPARATOR.'11 OK LSUB completed'.Client::MSG_SEPARATOR, $msg);
	}
	
	/*public function testMsgHandleAppend(){
		$this->markTestIncomplete('This test has not been implemented yet.');
	}*/
	
	public function testMsgHandleCheck(){
		$server = new Server('', 0);
		$server->init();
		$server->storageAddMaildir('./tests/test_mailbox_'.date('Ymd_His').'_'.uniqid('', true));
		
		$client = new Client();
		$client->setServer($server);
		$client->setId(1);
		
		$msg = $client->msgHandle('12 check');
		$this->assertEquals('12 NO check failure'.Client::MSG_SEPARATOR, $msg);
		
		$client->setStatus('hasAuth', true);
		
		$msg = $client->msgHandle('12 check');
		$this->assertEquals('12 NO No mailbox selected.'.Client::MSG_SEPARATOR, $msg);
		
		$server->storageFolderAdd('test_dir');
		$client->msgHandle('6 select test_dir');
		
		$msg = $client->msgHandle('12 check');
		$this->assertEquals('12 OK CHECK completed'.Client::MSG_SEPARATOR, $msg);
	}
	
	public function testMsgHandleClose(){
		$server = new Server('', 0);
		$server->init();
		$server->storageAddMaildir('./tests/test_mailbox_'.date('Ymd_His').'_'.uniqid('', true));
		
		$client = new Client();
		$client->setServer($server);
		$client->setId(1);
		
		$msg = $client->msgHandle('13 close');
		$this->assertEquals('13 NO close failure'.Client::MSG_SEPARATOR, $msg);
		
		$client->setStatus('hasAuth', true);
		
		$msg = $client->msgHandle('13 close');
		$this->assertEquals('13 NO No mailbox selected.'.Client::MSG_SEPARATOR, $msg);
		
		$server->storageFolderAdd('test_dir');
		$client->msgHandle('6 select test_dir');
		
		$msg = $client->msgHandle('13 close');
		$this->assertEquals('13 OK CLOSE completed'.Client::MSG_SEPARATOR, $msg);
	}
	
	public function testMsgHandleExpunge1(){
		$server = new Server('', 0);
		$server->init();
		$server->storageAddMaildir('./tests/test_mailbox_'.date('Ymd_His').'_'.uniqid('', true));
		
		$client = new Client();
		$client->setServer($server);
		$client->setId(1);
		
		$msg = $client->msgHandle('14 expunge');
		$this->assertEquals('14 NO expunge failure'.Client::MSG_SEPARATOR, $msg);
		
		$client->setStatus('hasAuth', true);
		
		$msg = $client->msgHandle('14 expunge');
		$this->assertEquals('14 NO No mailbox selected.'.Client::MSG_SEPARATOR, $msg);
		
		$server->storageFolderAdd('test_dir');
		$client->msgHandle('6 select test_dir');
		
		$msg = $client->msgHandle('14 expunge');
		$this->assertEquals('14 OK EXPUNGE completed'.Client::MSG_SEPARATOR, $msg);
		
		
		$message = new Message();
		$message->addFrom('thefox21at@gmail.com');
		$message->addTo('thefox@fox21.at');
		$message->setSubject('my_subject 1');
		$message->setBody('my_body');
		$server->mailAdd($message->toString(), null, array(Storage::FLAG_DELETED));
		
		$message = new Message();
		$message->addFrom('thefox21at@gmail.com');
		$message->addTo('thefox@fox21.at');
		$message->setSubject('my_subject 2');
		$message->setBody('my_body');
		$server->mailAdd($message->toString());
		
		$message = new Message();
		$message->addFrom('thefox21at@gmail.com');
		$message->addTo('thefox@fox21.at');
		$message->setSubject('my_subject 3');
		$message->setBody('my_body');
		$server->mailAdd($message->toString(), null, array(Storage::FLAG_DELETED));
		
		$message = new Message();
		$message->addFrom('thefox21at@gmail.com');
		$message->addTo('thefox@fox21.at');
		$message->setSubject('my_subject 4');
		$message->setBody('my_body');
		$server->mailAdd($message->toString());
		
		$message = new Message();
		$message->addFrom('thefox21at@gmail.com');
		$message->addTo('thefox@fox21.at');
		$message->setSubject('my_subject 5');
		$message->setBody('my_body');
		$server->mailAdd($message->toString());
		
		$message = new Message();
		$message->addFrom('thefox21at@gmail.com');
		$message->addTo('thefox@fox21.at');
		$message->setSubject('my_subject 6');
		$message->setBody('my_body');
		$server->mailAdd($message->toString(), null, array(Storage::FLAG_DELETED));
		
		$message = new Message();
		$message->addFrom('thefox21at@gmail.com');
		$message->addTo('thefox@fox21.at');
		$message->setSubject('my_subject 7');
		$message->setBody('my_body');
		$server->mailAdd($message->toString(), null, array(Storage::FLAG_DELETED));
		
		$message = new Message();
		$message->addFrom('thefox21at@gmail.com');
		$message->addTo('thefox@fox21.at');
		$message->setSubject('my_subject 8');
		$message->setBody('my_body');
		$server->mailAdd($message->toString());
		
		
		$msg = $client->msgHandle('14 expunge');
		$this->assertEquals('* 1 EXPUNGE'.Client::MSG_SEPARATOR.'* 2 EXPUNGE'.Client::MSG_SEPARATOR.'* 4 EXPUNGE'.Client::MSG_SEPARATOR.'* 4 EXPUNGE'.Client::MSG_SEPARATOR.'14 OK EXPUNGE completed'.Client::MSG_SEPARATOR, $msg);
	}
	
	public function testMsgHandleExpunge2(){
		$server = new Server('', 0);
		$server->init();
		$server->storageAddMaildir('./tests/test_mailbox_'.date('Ymd_His').'_'.uniqid('', true));
		
		$client = new Client();
		$client->setServer($server);
		$client->setId(1);
		
		$msg = $client->msgHandle('14 expunge');
		$this->assertEquals('14 NO expunge failure'.Client::MSG_SEPARATOR, $msg);
		
		$client->setStatus('hasAuth', true);
		
		$msg = $client->msgHandle('14 expunge');
		$this->assertEquals('14 NO No mailbox selected.'.Client::MSG_SEPARATOR, $msg);
		
		$server->storageFolderAdd('test_dir');
		$client->msgHandle('6 select test_dir');
		
		$msg = $client->msgHandle('14 expunge');
		$this->assertEquals('14 OK EXPUNGE completed'.Client::MSG_SEPARATOR, $msg);
		
		
		$message = new Message();
		$message->addFrom('thefox21at@gmail.com');
		$message->addTo('thefox@fox21.at');
		$message->setSubject('my_subject 1');
		$message->setBody('my_body');
		$server->mailAdd($message->toString());
		
		$message = new Message();
		$message->addFrom('thefox21at@gmail.com');
		$message->addTo('thefox@fox21.at');
		$message->setSubject('my_subject 2');
		$message->setBody('my_body');
		$server->mailAdd($message->toString(), null, array(Storage::FLAG_DELETED));
		
		$message = new Message();
		$message->addFrom('thefox21at@gmail.com');
		$message->addTo('thefox@fox21.at');
		$message->setSubject('my_subject 3');
		$message->setBody('my_body');
		$server->mailAdd($message->toString(), null, array(Storage::FLAG_DELETED));
		
		$msg = $client->msgHandle('14 expunge');
		$this->assertEquals('* 2 EXPUNGE'.Client::MSG_SEPARATOR.'* 2 EXPUNGE'.Client::MSG_SEPARATOR.'14 OK EXPUNGE completed'.Client::MSG_SEPARATOR, $msg);
	}
	
	public function providerParseSearchKeys(){
		$rv = array();
		
		$rv[] = array(array(), array());
		$rv[] = array(array(''), array(''));
		$rv[] = array(array('1'), array('1'));
		$rv[] = array(array('ALL'), array('ALL'));
		$rv[] = array(array('ANSWERED'), array('ANSWERED'));
		$rv[] = array(array('BCC', 'thefox'), array('BCC thefox'));
		$rv[] = array(array('BEFORE', '1987-02-21'), array('BEFORE 1987-02-21'));
		$rv[] = array(array('BODY', 'fox'), array('BODY fox'));
		$rv[] = array(array('CC', 'fox'), array('CC fox'));
		$rv[] = array(array('DELETED'), array('DELETED'));
		$rv[] = array(array('DRAFT'), array('DRAFT'));
		$rv[] = array(array('FLAGGED'), array('FLAGGED'));
		$rv[] = array(array('FROM', 'fox'), array('FROM fox'));
		$rv[] = array(array('HEADER', 'FieldName1', 'fox'), array('HEADER FieldName1 fox'));
		$rv[] = array(array('KEYWORD', 'flag21'), array('KEYWORD flag21'));
		$rv[] = array(array('LARGER', 21), array('LARGER 21'));
		$rv[] = array(array('LARGER', '24'), array('LARGER 24'));
		$rv[] = array(array('NEW'), array('NEW'));
		$rv[] = array(array('NOT', 'BCC', 'fox'), array('NOT', 'BCC fox'));
		$rv[] = array(array('OLD'), array('OLD'));
		$rv[] = array(array('ON', '1987-02-21'), array('ON 1987-02-21'));
		$rv[] = array(array('OR', 'BCC', 'thefox', 'BCC', '21'), array(array('BCC thefox', 'OR', 'BCC 21')));
		$rv[] = array(array('RECENT'), array('RECENT'));
		$rv[] = array(array('SEEN'), array('SEEN'));
		$rv[] = array(array('SENTBEFORE', '1987-02-21'), array('SENTBEFORE 1987-02-21'));
		$rv[] = array(array('SENTON', '1987-02-21'), array('SENTON 1987-02-21'));
		$rv[] = array(array('SENTSINCE', '1987-02-21'), array('SENTSINCE 1987-02-21'));
		$rv[] = array(array('SMALLER', 21), array('SMALLER 21'));
		$rv[] = array(array('SMALLER', '24'), array('SMALLER 24'));
		$rv[] = array(array('SUBJECT', 'hello'), array('SUBJECT hello'));
		$rv[] = array(array('SUBJECT', '"hello world"'), array('SUBJECT "hello world"'));
		$rv[] = array(array('TEXT', 'fox'), array('TEXT fox'));
		$rv[] = array(array('TO', 'fox'), array('TO fox'));
		$rv[] = array(array('UID', '100001'), array('UID 100001'));
		$rv[] = array(array('UNANSWERED'), array('UNANSWERED'));
		$rv[] = array(array('UNDELETED'), array('UNDELETED'));
		$rv[] = array(array('UNDRAFT'), array('UNDRAFT'));
		$rv[] = array(array('UNFLAGGED'), array('UNFLAGGED'));
		$rv[] = array(array('UNKEYWORD', 'flag21'), array('UNKEYWORD flag21'));
		$rv[] = array(array('UNSEEN'), array('UNSEEN'));
		
		
		$rv[] = array(array('1', '2'), array('1', 'AND', '2'));
		$rv[] = array(array('BCC', 'thefox', 'BCC', '21'), array('BCC thefox', 'AND', 'BCC 21'));
		$rv[] = array(array('BCC', 'thefox', 'AND', 'BCC', '21'), array('BCC thefox', 'AND', 'BCC 21'));
		
		$rv[] = array(array('BCC', 'at', 'OR', 'BCC', 'thefox', 'BCC', '21'), array('BCC at', 'AND', array('BCC thefox', 'OR', 'BCC 21')));
		$rv[] = array(array('OR', 'BCC', 'thefox', 'BCC', '21', 'AND', 'BCC', 'at'), array(array('BCC thefox', 'OR', 'BCC 21'), 'AND', 'BCC at'));
		$rv[] = array(array('OR', 'SEEN', 'UNFLAGGED', 'AND', 'BCC', 'at'), array(array('SEEN', 'OR', 'UNFLAGGED'), 'AND', 'BCC at'));
		$rv[] = array(array('BCC', 'thefox', 'NOT', 'BCC', '21'), array('BCC thefox', 'AND', 'NOT', 'BCC 21'));
		
		#return $rv;
		
		$rv[] = array(array(
				'ALL',
				'ANSWERED',
				'BCC', 'thefox',
				'BEFORE', '1987-02-21',
				'BODY', 'fox',
				'CC', 'fox',
				'DELETED',
				'DRAFT',
				'FLAGGED',
				'FROM', 'fox',
				'HEADER', 'FieldName1', 'fox',
				'KEYWORD', 'flag21',
				'LARGER', 21,
				'LARGER', '24',
				'NEW',
				'NOT', 'BCC', 'fox',
				'OLD',
				'ON', '1987-02-21',
				'OR', 'BCC', 'thefox', 'BCC', '21',
				'RECENT',
				'SEEN',
				'SENTBEFORE', '1987-02-21',
				'SENTON', '1987-02-21',
				'SENTSINCE', '1987-02-21',
				'SMALLER', 21,
				'SMALLER', '24',
				'SUBJECT', 'hello',
				'SUBJECT', '"hello world"',
				'TEXT', 'fox',
				'TO', 'fox',
				'UID', '100001',
				'UNANSWERED',
				'UNDELETED',
				'UNDRAFT',
				'UNFLAGGED',
				'UNKEYWORD', 'flag21',
				'UNSEEN'
			), array(
				'ALL',
				'AND', 'ANSWERED',
				'AND', 'BCC thefox',
				'AND', 'BEFORE 1987-02-21',
				'AND', 'BODY fox',
				'AND', 'CC fox',
				'AND', 'DELETED',
				'AND', 'DRAFT',
				'AND', 'FLAGGED',
				'AND', 'FROM fox',
				'AND', 'HEADER FieldName1 fox',
				'AND', 'KEYWORD flag21',
				'AND', 'LARGER 21',
				'AND', 'LARGER 24',
				'AND', 'NEW',
				'AND', 'NOT', 'BCC fox',
				'AND', 'OLD',
				'AND', 'ON 1987-02-21',
				'AND', array('BCC thefox', 'OR', 'BCC 21'),
				'AND', 'RECENT',
				'AND', 'SEEN',
				'AND', 'SENTBEFORE 1987-02-21',
				'AND', 'SENTON 1987-02-21',
				'AND', 'SENTSINCE 1987-02-21',
				'AND', 'SMALLER 21',
				'AND', 'SMALLER 24',
				'AND', 'SUBJECT hello',
				'AND', 'SUBJECT "hello world"',
				'AND', 'TEXT fox',
				'AND', 'TO fox',
				'AND', 'UID 100001',
				'AND', 'UNANSWERED',
				'AND', 'UNDELETED',
				'AND', 'UNDRAFT',
				'AND', 'UNFLAGGED',
				'AND', 'UNKEYWORD flag21',
				'AND', 'UNSEEN'
			)
		);
		
		$rv[] = array(array(array('1', '2'), '3'), array(array('1', 'AND', '2'), 'AND', '3'));
		$rv[] = array(array('4', array('1', '2'), '3'), array('4', 'AND', array('1', 'AND', '2'), 'AND', '3'));
		$rv[] = array(array(array('1', '2'), 'AND', '3'), array(array('1', 'AND', '2'), 'AND', '3'));
		
		
		$rv[] = array(array('OR', '1', '2'), array(array('1', 'OR', '2')));
		$rv[] = array(array('OR', 'OR', '1', '2', '3'), array(array(array('1', 'OR', '2'), 'OR', '3')));
		
		$rv[] = array(array('OR', 'OR', 'BCC', 'thefox', 'TO', 'thefox', 'CC', 'thefox'), array(array(array('BCC thefox', 'OR', 'TO thefox'), 'OR', 'CC thefox')));
		
		$rv[] = array(array('OR', array('1', '2'), '3'), array(array(array('1', 'AND', '2'), 'OR', '3')));
		
		$rv[] = array(array('123', 'OR', array('1', '2'), '3'), array('123', 'AND', array(array('1', 'AND', '2'), 'OR', '3')));
		
		return $rv;
	}
	
	/**
     * @dataProvider providerParseSearchKeys
     */
	public function testParseSearchKeys($testData, $expect){
		$client = new Client();
		$client->setId(1);
		
		$posOffset = 0;
		$rv = $client->parseSearchKeys($testData, $posOffset);
		
		#fwrite(STDOUT, 'list: '.$posOffset."\n"); ve($rv);
		
		$this->assertEquals($expect, $rv);
	}
	
	public function testMsgHandleUidSearch(){
		$server = new Server('', 0);
		$server->init();
		$server->storageAddMaildir('./tests/test_mailbox_'.date('Ymd_His').'_'.uniqid('', true));
		
		$client = new Client();
		$client->setServer($server);
		$client->setId(1);
		
		$msg = $client->msgHandle('17 uid search');
		$this->assertEquals('17 NO uid failure'.Client::MSG_SEPARATOR, $msg);
		
		$client->setStatus('hasAuth', true);
		
		$msg = $client->msgHandle('17 uid search');
		$this->assertEquals('17 NO No mailbox selected.'.Client::MSG_SEPARATOR, $msg);
		
		$client->msgHandle('6 select INBOX');
		
		
		$message = new Message();
		$message->addFrom('thefox21at@gmail.com');
		$message->addTo('thefox@fox21.at');
		$message->setSubject('my_subject 1');
		$message->setBody('my_body');
		$server->mailAdd($message->toString(), null, null, false);
		
		$message = new Message();
		$message->addFrom('thefox21at@gmail.com');
		$message->addTo('thefox@fox21.at');
		$message->setSubject('my_subject 2');
		$message->setBody('my_body');
		$server->mailAdd($message->toString(), null, array(Storage::FLAG_ANSWERED), false);
		
		$message = new Message();
		$message->addFrom('thefox21at@gmail.com');
		$message->addTo('thefox@fox21.at');
		$message->addBcc('steve@apple.com');
		$message->setSubject('my_subject 3');
		$message->setBody('my_body');
		$server->mailAdd($message->toString(), null, null, false);
		
		$headers = new Headers();
		$headers->addHeader(Date::fromString('Date: ' . date('r', mktime(0, 0, 0, 2, 21, 1987))));
		
		$message = new Message();
		$message->setHeaders($headers);
		$message->addFrom('thefox21at@gmail.com');
		$message->addTo('thefox@fox21.at');
		$message->setSubject('my_subject 4');
		$message->setBody('my_body');
		$server->mailAdd($message->toString(), null, null, false);
		
		$message = new Message();
		$message->setHeaders($headers);
		$message->addFrom('thefox21at@gmail.com');
		$message->addTo('thefox@fox21.at');
		$message->setSubject('my_subject 5');
		$message->setBody('my_body');
		$server->mailAdd($message->toString(), null, null, false);
		
		$headers = new Headers();
		$headers->addHeader(Date::fromString('Date: ' . date('r', mktime(0, 0, 0, 11, 20, 1986))));
		
		$message = new Message();
		$message->setHeaders($headers);
		$message->addFrom('thefox21at@gmail.com');
		$message->addTo('thefox@fox21.at');
		$message->setSubject('my_subject 6');
		$message->setBody('hello world');
		$server->mailAdd($message->toString(), null, null, false);
		
		$message = new Message();
		$message->addFrom('thefox21at@gmail.com');
		$message->addTo('thefox@fox21.at');
		$message->setSubject('my_subject 7');
		$message->setBody('my_body');
		$server->mailAdd($message->toString(), null, array(Storage::FLAG_DELETED), false);
		
		$message = new Message();
		$message->addFrom('thefox21at@gmail.com');
		$message->addTo('thefox@fox21.at');
		$message->setSubject('my_subject 8');
		$message->setBody('my_body');
		$server->mailAdd($message->toString(), null, array(Storage::FLAG_DRAFT), false);
		
		$message = new Message();
		$message->addFrom('thefox21at@gmail.com');
		$message->addTo('thefox@fox21.at');
		$message->setSubject('my_subject 9');
		$message->setBody('my_body');
		$server->mailAdd($message->toString(), null, array(Storage::FLAG_FLAGGED), false);
		
		$message = new Message();
		$message->addFrom('test@fox21.at');
		$message->addTo('thefox@fox21.at');
		$message->setSubject('my_subject 10');
		$message->setBody('my_body');
		$server->mailAdd($message->toString(), null, null, false);
		
		$message = new Message();
		$message->addFrom('thefox21at@gmail.com');
		$message->addTo('thefox@fox21.at');
		$message->setSubject('my_subject 11');
		$message->setBody('my super fancy long body for testing the size');
		$server->mailAdd($message->toString(), null, null, false);
		
		$message = new Message();
		$message->addFrom('thefox21at@gmail.com');
		$message->addTo('thefox@fox21.at');
		$message->setSubject('my_subject 12');
		$message->setBody('my_body');
		$server->mailAdd($message->toString(), null, array(), true);
		
		$message = new Message();
		$message->addFrom('thefox21at@gmail.com');
		$message->addTo('thefox@fox21.at');
		$message->setSubject('my_subject 13');
		$message->setBody('my_body');
		$server->mailAdd($message->toString(), null, array(), false);
		
		$message = new Message();
		$message->addFrom('thefox21at@gmail.com');
		$message->addTo('thefox@fox21.at');
		#$message->setSubject('my_subject 14');
		$message->setBody('my_body');
		$server->mailAdd($message->toString(), null, array(), false);
		
		$message = new Message();
		$message->addFrom('thefox21at@gmail.com');
		$message->addTo('thefox@fox21.at');
		$message->setSubject('my_subject 15');
		$message->setBody('this is a test body');
		$server->mailAdd($message->toString(), null, array(), false);
		
		$message = new Message();
		$message->addFrom('thefox21at@gmail.com');
		$message->addTo('thefox@fox21.at');
		$message->setSubject('my_subject 16');
		#$message->setBody('my_body');
		$server->mailAdd($message->toString(), null, array(), false);
		
		$message = new Message();
		$message->addFrom('thefox21at@gmail.com');
		$message->addTo('steve@apple.com');
		$message->setSubject('my_subject 17');
		$message->setBody('my_body');
		$server->mailAdd($message->toString(), null, array(), false);
		
		$message = new Message();
		$message->addFrom('thefox21at@gmail.com');
		$message->addTo('thefox@fox21.at');
		$message->setSubject('my_subject 18');
		$message->setBody('my_body');
		$server->mailAdd($message->toString(), null, array(), false);
		
		$message = new Message();
		$message->addFrom('thefox21at@gmail.com');
		$message->addTo('thefox@fox21.at');
		$message->setSubject('my_subject 19');
		$message->setBody('my_body');
		$server->mailAdd($message->toString(), null, array(Storage::FLAG_ANSWERED), false);
		
		$message = new Message();
		$message->addFrom('thefox21at@gmail.com');
		$message->addTo('thefox@fox21.at');
		$message->setSubject('my_subject 20');
		$message->setBody('my_body');
		$server->mailAdd($message->toString(), null, array(Storage::FLAG_DELETED), false);
		
		$message = new Message();
		$message->addFrom('thefox21at@gmail.com');
		$message->addTo('thefox@fox21.at');
		$message->setSubject('my_subject 21');
		$message->setBody('my_body');
		$server->mailAdd($message->toString(), null, array(Storage::FLAG_DRAFT), false);
		
		$message = new Message();
		$message->addFrom('thefox21at@gmail.com');
		$message->addTo('thefox@fox21.at');
		$message->setSubject('my_subject 22');
		$message->setBody('my_body');
		$server->mailAdd($message->toString(), null, array(Storage::FLAG_FLAGGED), false);
		
		$message = new Message();
		$message->addFrom('thefox21at@gmail.com');
		$message->addTo('thefox@fox21.at');
		$message->setSubject('my_subject 23');
		$message->setBody('my_body');
		$server->mailAdd($message->toString(), null, array(Storage::FLAG_SEEN), false);
		
		
		
		
		$msg = $client->msgHandle('17 uid SEARCH ALL');
		$this->assertEquals('* SEARCH 100001 100002 100003 100004 100005 100006 100007 100008 100009 100010 100011 100012 100013 100014 100015 100016 100017 100018 100019 100020 100021 100022 100023'.Client::MSG_SEPARATOR.'17 OK UID SEARCH completed'.Client::MSG_SEPARATOR, $msg);
		
		$msg = $client->msgHandle('17 uid SEARCH ANSWERED');
		$this->assertEquals('* SEARCH 100002 100019'.Client::MSG_SEPARATOR.'17 OK UID SEARCH completed'.Client::MSG_SEPARATOR, $msg);
		
		$msg = $client->msgHandle('17 uid SEARCH BCC apple');
		$this->assertEquals('* SEARCH 100003'.Client::MSG_SEPARATOR.'17 OK UID SEARCH completed'.Client::MSG_SEPARATOR, $msg);
		
		$msg = $client->msgHandle('17 uid SEARCH BEFORE 1990');
		#$this->assertEquals('* SEARCH 100004 100005'.Client::MSG_SEPARATOR.'17 OK UID SEARCH completed'.Client::MSG_SEPARATOR, $msg);
		
		$msg = $client->msgHandle('17 uid SEARCH BODY world');
		$this->assertEquals('* SEARCH 100006'.Client::MSG_SEPARATOR.'17 OK UID SEARCH completed'.Client::MSG_SEPARATOR, $msg);
		
		$msg = $client->msgHandle('17 uid SEARCH DELETED');
		$this->assertEquals('* SEARCH 100007 100020'.Client::MSG_SEPARATOR.'17 OK UID SEARCH completed'.Client::MSG_SEPARATOR, $msg);
		
		$msg = $client->msgHandle('17 uid SEARCH DRAFT');
		$this->assertEquals('* SEARCH 100008 100021'.Client::MSG_SEPARATOR.'17 OK UID SEARCH completed'.Client::MSG_SEPARATOR, $msg);
		
		$msg = $client->msgHandle('17 uid SEARCH FLAGGED');
		$this->assertEquals('* SEARCH 100009 100022'.Client::MSG_SEPARATOR.'17 OK UID SEARCH completed'.Client::MSG_SEPARATOR, $msg);
		
		$msg = $client->msgHandle('17 uid SEARCH FROM test@');
		$this->assertEquals('* SEARCH 100010'.Client::MSG_SEPARATOR.'17 OK UID SEARCH completed'.Client::MSG_SEPARATOR, $msg);
		
		$msg = $client->msgHandle('17 uid SEARCH HEADER Date 1987');
		$this->assertEquals('* SEARCH 100004 100005'.Client::MSG_SEPARATOR.'17 OK UID SEARCH completed'.Client::MSG_SEPARATOR, $msg);
		
		$msg = $client->msgHandle('17 uid SEARCH LARGER 40');
		$this->assertEquals('* SEARCH 100011'.Client::MSG_SEPARATOR.'17 OK UID SEARCH completed'.Client::MSG_SEPARATOR, $msg);
		
		$msg = $client->msgHandle('17 uid SEARCH NEW');
		$this->assertEquals('* SEARCH 100012'.Client::MSG_SEPARATOR.'17 OK UID SEARCH completed'.Client::MSG_SEPARATOR, $msg);
		
		$msg = $client->msgHandle('17 uid SEARCH OLD');
		$this->assertEquals('* SEARCH 100001 100002 100003 100004 100005 100006 100007 100008 100009 100010 100011 100013 100014 100015 100016 100017 100018 100019 100020 100021 100022 100023'.Client::MSG_SEPARATOR.'17 OK UID SEARCH completed'.Client::MSG_SEPARATOR, $msg);
		
		#$msg = $client->msgHandle('17 uid SEARCH ON 1987-02-21');
		#$this->assertEquals('* SEARCH 100004 100005'.Client::MSG_SEPARATOR.'17 OK UID SEARCH completed'.Client::MSG_SEPARATOR, $msg);
		
		$msg = $client->msgHandle('17 uid SEARCH OR 5 6');
		$this->assertEquals('* SEARCH 100005 100006'.Client::MSG_SEPARATOR.'17 OK UID SEARCH completed'.Client::MSG_SEPARATOR, $msg);
		
		$msg = $client->msgHandle('17 uid SEARCH OR OR 5 6 7');
		$this->assertEquals('* SEARCH 100005 100006 100007'.Client::MSG_SEPARATOR.'17 OK UID SEARCH completed'.Client::MSG_SEPARATOR, $msg);
		
		$msg = $client->msgHandle('17 uid SEARCH RECENT');
		$this->assertEquals('* SEARCH 100012'.Client::MSG_SEPARATOR.'17 OK UID SEARCH completed'.Client::MSG_SEPARATOR, $msg);
		
		$msg = $client->msgHandle('17 uid SEARCH SEEN');
		$this->assertEquals('* SEARCH 100001 100003 100004 100005 100006 100010 100011 100023'.Client::MSG_SEPARATOR.'17 OK UID SEARCH completed'.Client::MSG_SEPARATOR, $msg);
		
		$msg = $client->msgHandle('17 uid SEARCH SENTBEFORE 1990-01-01');
		$this->assertEquals('* SEARCH 100004 100005 100006'.Client::MSG_SEPARATOR.'17 OK UID SEARCH completed'.Client::MSG_SEPARATOR, $msg);
		
		$msg = $client->msgHandle('17 uid SEARCH SENTON 1987-02-21');
		$this->assertEquals('* SEARCH 100004 100005'.Client::MSG_SEPARATOR.'17 OK UID SEARCH completed'.Client::MSG_SEPARATOR, $msg);
		
		$msg = $client->msgHandle('17 uid SEARCH SENTSINCE 1987-02-21');
		$this->assertEquals('* SEARCH 100001 100002 100003 100004 100005 100007 100008 100009 100010 100011 100012 100013 100014 100015 100016 100017 100018 100019 100020 100021 100022 100023'.Client::MSG_SEPARATOR.'17 OK UID SEARCH completed'.Client::MSG_SEPARATOR, $msg);
		
		$msg = $client->msgHandle('17 uid SEARCH SMALLER 30');
		$this->assertEquals('* SEARCH 100001 100002 100003 100004 100005 100006 100007 100008 100009 100010 100012 100013 100014 100015 100016 100017 100018 100019 100020 100021 100022 100023'.Client::MSG_SEPARATOR.'17 OK UID SEARCH completed'.Client::MSG_SEPARATOR, $msg);
		
		$msg = $client->msgHandle('17 uid SEARCH SUBJECT "t 13"');
		$this->assertEquals('* SEARCH 100013'.Client::MSG_SEPARATOR.'17 OK UID SEARCH completed'.Client::MSG_SEPARATOR, $msg);
		
		$msg = $client->msgHandle('17 uid SEARCH TEXT test');
		$this->assertEquals('* SEARCH 100011 100015'.Client::MSG_SEPARATOR.'17 OK UID SEARCH completed'.Client::MSG_SEPARATOR, $msg);
		
		$msg = $client->msgHandle('17 uid SEARCH TO steve');
		$this->assertEquals('* SEARCH 100017'.Client::MSG_SEPARATOR.'17 OK UID SEARCH completed'.Client::MSG_SEPARATOR, $msg);
		
		$msg = $client->msgHandle('17 uid SEARCH UID 100018');
		$this->assertEquals('* SEARCH 100018'.Client::MSG_SEPARATOR.'17 OK UID SEARCH completed'.Client::MSG_SEPARATOR, $msg);
		
		$msg = $client->msgHandle('17 uid SEARCH UNANSWERED');
		$this->assertEquals('* SEARCH 100001 100003 100004 100005 100006 100007 100008 100009 100010 100011 100012 100013 100014 100015 100016 100017 100018 100020 100021 100022 100023'.Client::MSG_SEPARATOR.'17 OK UID SEARCH completed'.Client::MSG_SEPARATOR, $msg);
		
		$msg = $client->msgHandle('17 uid SEARCH UNDELETED');
		$this->assertEquals('* SEARCH 100001 100002 100003 100004 100005 100006 100008 100009 100010 100011 100012 100013 100014 100015 100016 100017 100018 100019 100021 100022 100023'.Client::MSG_SEPARATOR.'17 OK UID SEARCH completed'.Client::MSG_SEPARATOR, $msg);
		
		$msg = $client->msgHandle('17 uid SEARCH UNDRAFT');
		$this->assertEquals('* SEARCH 100001 100002 100003 100004 100005 100006 100007 100009 100010 100011 100012 100013 100014 100015 100016 100017 100018 100019 100020 100022 100023'.Client::MSG_SEPARATOR.'17 OK UID SEARCH completed'.Client::MSG_SEPARATOR, $msg);
		
		$msg = $client->msgHandle('17 uid SEARCH UNFLAGGED');
		$this->assertEquals('* SEARCH 100001 100002 100003 100004 100005 100006 100007 100008 100010 100011 100012 100013 100014 100015 100016 100017 100018 100019 100020 100021 100023'.Client::MSG_SEPARATOR.'17 OK UID SEARCH completed'.Client::MSG_SEPARATOR, $msg);
		
		#$msg = $client->msgHandle('17 uid SEARCH UNKEYWORD');
		#$this->assertEquals('* SEARCH 123'.Client::MSG_SEPARATOR.'17 OK UID SEARCH completed'.Client::MSG_SEPARATOR, $msg);
		
		$msg = $client->msgHandle('17 uid SEARCH UNSEEN');
		$this->assertEquals('* SEARCH 100002 100007 100008 100009 100012 100013 100014 100015 100016 100017 100018 100019 100020 100021 100022'.Client::MSG_SEPARATOR.'17 OK UID SEARCH completed'.Client::MSG_SEPARATOR, $msg);
		
		
		
		
		$msg = $client->msgHandle('17 uid SEARCH BEFORE 1985');
		$this->assertEquals('17 OK UID SEARCH completed'.Client::MSG_SEPARATOR, $msg);
		
		$msg = $client->msgHandle('17 uid SEARCH BODY xyz');
		$this->assertEquals('17 OK UID SEARCH completed'.Client::MSG_SEPARATOR, $msg);
		
		
		
		#$msg = $client->msgHandle('17 uid SEARCH OR (UNDELETED FROM "thefox") ANSWERED AND FROM "21"');
		
		#$msg = $client->msgHandle('17 uid SEARCH UNDELETED HEADER From @fox21.at HEADER Date 2014');
		
		#$msg = $client->msgHandle('17 uid SEARCH NOT 5');
		#$msg = $client->msgHandle('17 uid SEARCH NOT UID 100005');
		#$this->assertEquals('x17 OK UID SEARCH completed'.Client::MSG_SEPARATOR, $msg);
		
		#$msg = $client->msgHandle('17 uid SEARCH NOT UID 100021');
		
	}
	
	public function testMsgHandleUidFetch1(){
		$server = new Server('', 0);
		$server->init();
		$server->storageAddMaildir('./tests/test_mailbox_'.date('Ymd_His').'_'.uniqid('', true));
		
		$client = new Client();
		$client->setServer($server);
		$client->setId(1);
		
		$msg = $client->msgHandle('15 UID fetch');
		$this->assertEquals('15 NO uid failure'.Client::MSG_SEPARATOR, $msg);
		
		$client->setStatus('hasAuth', true);
		
		$msg = $client->msgHandle('15 UID fetch');
		$this->assertEquals('15 NO No mailbox selected.'.Client::MSG_SEPARATOR, $msg);
		
		$client->msgHandle('6 select INBOX');
		
		$msg = $client->msgHandle('15 UID fetch');
		$this->assertEquals('15 BAD Arguments invalid.'.Client::MSG_SEPARATOR, $msg);
		
		$message = new Message();
		$message->addFrom('thefox21at@gmail.com');
		$message->addTo('thefox@fox21.at');
		$message->setSubject('my_subject 1');
		$message->setBody('my_body');
		$server->mailAdd($message->toString());
		
		$message = new Message();
		$message->addFrom('thefox21at@gmail.com');
		$message->addTo('thefox@fox21.at');
		$message->setSubject('my_subject 2');
		$message->setBody('my_body');
		$server->mailAdd($message->toString());
		
		$message = new Message();
		$message->addFrom('thefox21at@gmail.com');
		$message->addTo('thefox@fox21.at');
		$message->setSubject('my_subject 3');
		$message->setBody('my_body');
		$server->mailAdd($message->toString());
		
		$message = new Message();
		$message->addFrom('thefox21at@gmail.com');
		$message->addTo('thefox@fox21.at');
		$message->setSubject('my_subject 4');
		$message->setBody('my_body');
		$server->mailAdd($message->toString());
		
		
		$msg = $client->msgHandle('15 UID fetch 1:* (ALL)');
		$this->assertEquals(
			'* 1 FETCH (UID 100001)'.Client::MSG_SEPARATOR
			.'* 2 FETCH (UID 100002)'.Client::MSG_SEPARATOR
			.'* 3 FETCH (UID 100003)'.Client::MSG_SEPARATOR
			.'* 4 FETCH (UID 100004)'.Client::MSG_SEPARATOR
			.'15 OK UID FETCH completed'.Client::MSG_SEPARATOR
			, $msg);
		
		$msg = $client->msgHandle('15 UID fetch 1:* (FAST)');
		$this->assertEquals(
			'* 1 FETCH (UID 100001)'.Client::MSG_SEPARATOR
			.'* 2 FETCH (UID 100002)'.Client::MSG_SEPARATOR
			.'* 3 FETCH (UID 100003)'.Client::MSG_SEPARATOR
			.'* 4 FETCH (UID 100004)'.Client::MSG_SEPARATOR
			.'15 OK UID FETCH completed'.Client::MSG_SEPARATOR
			, $msg);
		
		$msg = $client->msgHandle('15 UID fetch 1:* (FULL)');
		$this->assertEquals(
			'* 1 FETCH (UID 100001)'.Client::MSG_SEPARATOR
			.'* 2 FETCH (UID 100002)'.Client::MSG_SEPARATOR
			.'* 3 FETCH (UID 100003)'.Client::MSG_SEPARATOR
			.'* 4 FETCH (UID 100004)'.Client::MSG_SEPARATOR
			.'15 OK UID FETCH completed'.Client::MSG_SEPARATOR
			, $msg);
	}
	
	public function testMsgHandleUidFetch2(){
		$server = new Server('', 0);
		$server->init();
		$server->storageAddMaildir('./tests/test_mailbox_'.date('Ymd_His').'_'.uniqid('', true));
		
		$client = new Client();
		$client->setServer($server);
		$client->setId(1);
		
		$client->setStatus('hasAuth', true);
		$client->msgHandle('6 select INBOX');
		
		$message = new Message();
		$message->addFrom('thefox21at@gmail.com');
		$message->addTo('thefox@fox21.at');
		$message->setSubject('my_subject 1');
		$message->setBody('my_body');
		$server->mailAdd($message->toString());
		
		$message = new Message();
		$message->addFrom('thefox21at@gmail.com');
		$message->addTo('thefox@fox21.at');
		$message->setSubject('my_subject 2');
		$message->setBody('my_body');
		$server->mailAdd($message->toString());
		
		$message = new Message();
		$message->addFrom('thefox21at@gmail.com');
		$message->addTo('thefox@fox21.at');
		$message->setSubject('my_subject 3');
		$message->setBody('my_body');
		$server->mailAdd($message->toString(), null, null, false);
		
		$message = new Message();
		$message->addFrom('thefox21at@gmail.com');
		$message->addTo('thefox@fox21.at');
		$message->setSubject('my_subject 4');
		$message->setBody('my_body');
		$server->mailAdd($message->toString());
		
		
		$msg = $client->msgHandle('15 UID fetch 1:* (FLAGS)');
		$this->assertEquals(
			'* 1 FETCH (UID 100003 FLAGS (\Seen))'.Client::MSG_SEPARATOR
			.'* 2 FETCH (UID 100001 FLAGS (\Recent))'.Client::MSG_SEPARATOR
			.'* 3 FETCH (UID 100002 FLAGS (\Recent))'.Client::MSG_SEPARATOR
			.'* 4 FETCH (UID 100004 FLAGS (\Recent))'.Client::MSG_SEPARATOR
			.'15 OK UID FETCH completed'.Client::MSG_SEPARATOR
			, $msg);
	}
	
	public function testMsgHandleUidFetch3(){
		$server = new Server('', 0);
		$server->init();
		$server->storageAddMaildir('./tests/test_mailbox_'.date('Ymd_His').'_'.uniqid('', true));
		
		$client = new Client();
		$client->setServer($server);
		$client->setId(1);
		
		$client->setStatus('hasAuth', true);
		$client->msgHandle('6 select INBOX');
		
		$message = new Message();
		$message->addFrom('thefox21at@gmail.com');
		$message->addTo('thefox@fox21.at');
		$message->setSubject('my_subject 1');
		$message->setBody('my_body');
		$server->mailAdd($message->toString());
		
		$message = new Message();
		$message->addFrom('thefox21at@gmail.com');
		$message->addTo('thefox@fox21.at');
		$message->setSubject('my_subject 2');
		$message->setBody('my_body');
		$server->mailAdd($message->toString());
		
		$message = new Message();
		$message->addFrom('thefox21at@gmail.com');
		$message->addTo('thefox@fox21.at');
		$message->setSubject('my_subject 3');
		$message->setBody('my_body');
		$server->mailAdd($message->toString());
		
		$message = new Message();
		$message->addFrom('thefox21at@gmail.com');
		$message->addTo('thefox@fox21.at');
		$message->setSubject('my_subject 4');
		$message->setBody('my_body');
		$server->mailAdd($message->toString());
		
		
		$msg = $client->msgHandle('15 UID fetch 100002:100004 (FLAGS)');
		$this->assertEquals(
			'* 2 FETCH (UID 100002 FLAGS (\Recent))'.Client::MSG_SEPARATOR
			.'* 3 FETCH (UID 100003 FLAGS (\Recent))'.Client::MSG_SEPARATOR
			.'* 4 FETCH (UID 100004 FLAGS (\Recent))'.Client::MSG_SEPARATOR
			.'15 OK UID FETCH completed'.Client::MSG_SEPARATOR
			, $msg);
	}
	
	public function testMsgHandleUidStore(){
		$server = new Server('', 0);
		$server->init();
		$server->storageAddMaildir('./tests/test_mailbox_'.date('Ymd_His').'_'.uniqid('', true));
		
		$client = new Client();
		$client->setServer($server);
		$client->setId(1);
		
		$msg = $client->msgHandle('18 UID store');
		$this->assertEquals('18 NO uid failure'.Client::MSG_SEPARATOR, $msg);
		
		$msg = $client->msgHandle('18 uid store 100001 +FLAGS (\Deleted \Seen)');
		$this->assertEquals('18 NO uid failure'.Client::MSG_SEPARATOR, $msg);
		
		$client->setStatus('hasAuth', true);
		$client->msgHandle('6 select INBOX');
		
		$msg = $client->msgHandle('18 uid store 100001 +FLAGS (\Deleted \Seen)');
		$this->assertEquals('18 OK UID STORE completed'.Client::MSG_SEPARATOR, $msg);
		
		
		$message = new Message();
		$message->addFrom('thefox21at@gmail.com');
		$message->addTo('thefox@fox21.at');
		$message->setSubject('my_subject 1');
		$message->setBody('my_body');
		$server->mailAdd($message->toString());
		
		$message = new Message();
		$message->addFrom('thefox21at@gmail.com');
		$message->addTo('thefox@fox21.at');
		$message->setSubject('my_subject 2');
		$message->setBody('my_body');
		$server->mailAdd($message->toString());
		
		$message = new Message();
		$message->addFrom('thefox21at@gmail.com');
		$message->addTo('thefox@fox21.at');
		$message->setSubject('my_subject 3');
		$message->setBody('my_body');
		$server->mailAdd($message->toString());
		
		$message = new Message();
		$message->addFrom('thefox21at@gmail.com');
		$message->addTo('thefox@fox21.at');
		$message->setSubject('my_subject 4');
		$message->setBody('my_body');
		$server->mailAdd($message->toString());
		
		
		
		
		#$msg = $client->msgHandle('18 uid store 100001 +FLAGS (\Deleted \Seen)');
		#$this->assertEquals('18 OK UID STORE completed'.Client::MSG_SEPARATOR, $msg);
		
	}
	
	public function testMsgHandleCopy(){
		$maildirPath = './tests/test_mailbox_'.date('Ymd_His').'_'.uniqid('', true);
		
		$server = new Server('', 0);
		$server->init();
		$server->storageAddMaildir($maildirPath);
		
		$client = new Client();
		$client->setServer($server);
		$client->setId(1);
		
		$msg = $client->msgHandle('16 copy');
		$this->assertEquals('16 NO copy failure'.Client::MSG_SEPARATOR, $msg);
		
		$client->setStatus('hasAuth', true);
		
		$msg = $client->msgHandle('16 copy');
		$this->assertEquals('16 BAD Arguments invalid.'.Client::MSG_SEPARATOR, $msg);
		
		$server->storageFolderAdd('test_dir1');
		$server->storageFolderAdd('test_dir2');
		$client->msgHandle('6 select test_dir1');
		
		$msg = $client->msgHandle('16 copy');
		$this->assertEquals('16 BAD Arguments invalid.'.Client::MSG_SEPARATOR, $msg);
		
		$msg = $client->msgHandle('16 copy 1');
		$this->assertEquals('16 BAD Arguments invalid.'.Client::MSG_SEPARATOR, $msg);
		
		$msg = $client->msgHandle('16 copy 1 test_dir3');
		$this->assertEquals('16 BAD No messages in selected mailbox.'.Client::MSG_SEPARATOR, $msg);
		
		$msg = $client->msgHandle('16 UID copy 1 test_dir2');
		$this->assertEquals('16 BAD No messages in selected mailbox.'.Client::MSG_SEPARATOR, $msg);
		
		
		$message = new Message();
		$message->addFrom('thefox21at@gmail.com');
		$message->addTo('thefox@fox21.at');
		$message->setSubject('my_subject 1');
		$message->setBody('my_body');
		$server->mailAdd($message->toString());
		
		$message = new Message();
		$message->addFrom('thefox21at@gmail.com');
		$message->addTo('thefox@fox21.at');
		$message->setSubject('my_subject 2');
		$message->setBody('my_body');
		$server->mailAdd($message->toString());
		
		$message = new Message();
		$message->addFrom('thefox21at@gmail.com');
		$message->addTo('thefox@fox21.at');
		$message->setSubject('my_subject 3');
		$message->setBody('my_body');
		$server->mailAdd($message->toString());
		
		$message = new Message();
		$message->addFrom('thefox21at@gmail.com');
		$message->addTo('thefox@fox21.at');
		$message->setSubject('my_subject 4');
		$message->setBody('my_body');
		$server->mailAdd($message->toString());
		
		$finder = new Finder();
		$files = $finder->files()->in($maildirPath.'/.test_dir1/new');
		$this->assertEquals(4, count($files));
		
		
		$msg = $client->msgHandle('15 copy 2 test_dir2');
		$this->assertEquals('15 OK COPY completed'.Client::MSG_SEPARATOR, $msg);
		$finder = new Finder();
		$files = $finder->files()->in($maildirPath.'/.test_dir2/cur');
		$this->assertEquals(1, count($files));
		
		$msg = $client->msgHandle('15 copy 3:4 test_dir2');
		$this->assertEquals('15 OK COPY completed'.Client::MSG_SEPARATOR, $msg);
		$finder = new Finder();
		$files = $finder->files()->in($maildirPath.'/.test_dir2/cur');
		$this->assertEquals(3, count($files));
		
		$server->shutdown();
	}
	
	public function testMsgHandleUidCopy(){
		$maildirPath = './tests/test_mailbox_'.date('Ymd_His').'_'.uniqid('', true);
		
		$server = new Server('', 0);
		$server->init();
		$server->storageAddMaildir($maildirPath);
		
		$client = new Client();
		$client->setServer($server);
		$client->setId(1);
		
		$msg = $client->msgHandle('15 UID copy');
		$this->assertEquals('15 NO uid failure'.Client::MSG_SEPARATOR, $msg);
		
		$client->setStatus('hasAuth', true);
		
		$msg = $client->msgHandle('15 UID copy');
		$this->assertEquals('15 NO No mailbox selected.'.Client::MSG_SEPARATOR, $msg);
		
		$server->storageFolderAdd('test_dir1');
		$server->storageFolderAdd('test_dir2');
		$client->msgHandle('6 select test_dir1');
		
		$msg = $client->msgHandle('15 UID copy');
		$this->assertEquals('15 BAD Arguments invalid.'.Client::MSG_SEPARATOR, $msg);
		
		$msg = $client->msgHandle('15 UID copy 100001');
		$this->assertEquals('15 BAD Arguments invalid.'.Client::MSG_SEPARATOR, $msg);
		
		$msg = $client->msgHandle('15 UID copy 100001 test_dir3');
		$this->assertEquals('15 BAD No messages in selected mailbox.'.Client::MSG_SEPARATOR, $msg);
		
		$msg = $client->msgHandle('15 UID copy 100001 test_dir2');
		$this->assertEquals('15 BAD No messages in selected mailbox.'.Client::MSG_SEPARATOR, $msg);
		
		
		$message = new Message();
		$message->addFrom('thefox21at@gmail.com');
		$message->addTo('thefox@fox21.at');
		$message->setSubject('my_subject 1');
		$message->setBody('my_body');
		$server->mailAdd($message->toString());
		
		$message = new Message();
		$message->addFrom('thefox21at@gmail.com');
		$message->addTo('thefox@fox21.at');
		$message->setSubject('my_subject 2');
		$message->setBody('my_body');
		$server->mailAdd($message->toString());
		
		$message = new Message();
		$message->addFrom('thefox21at@gmail.com');
		$message->addTo('thefox@fox21.at');
		$message->setSubject('my_subject 3');
		$message->setBody('my_body');
		$server->mailAdd($message->toString());
		
		$message = new Message();
		$message->addFrom('thefox21at@gmail.com');
		$message->addTo('thefox@fox21.at');
		$message->setSubject('my_subject 4');
		$message->setBody('my_body');
		$server->mailAdd($message->toString());
		
		$finder = new Finder();
		$files = $finder->files()->in($maildirPath.'/.test_dir1/new');
		$this->assertEquals(4, count($files));
		
		
		$msg = $client->msgHandle('15 UID copy 100002 test_dir2');
		$this->assertEquals('15 OK COPY completed'.Client::MSG_SEPARATOR, $msg);
		$finder = new Finder();
		$files = $finder->files()->in($maildirPath.'/.test_dir2/cur');
		$this->assertEquals(1, count($files));
		
		$msg = $client->msgHandle('15 UID copy 100003:100004 test_dir2');
		$this->assertEquals('15 OK COPY completed'.Client::MSG_SEPARATOR, $msg);
		$finder = new Finder();
		$files = $finder->files()->in($maildirPath.'/.test_dir2/cur');
		$this->assertEquals(3, count($files));
		
		$msg = $client->msgHandle('15 UID copy 1 test_dir2');
		$this->assertEquals('15 OK COPY completed'.Client::MSG_SEPARATOR, $msg);
		
		$msg = $client->msgHandle('15 UID copy 100001 test_dir3');
		$this->assertEquals('15 NO [TRYCREATE] Can not get folder: no subfolder named test_dir3'.Client::MSG_SEPARATOR, $msg);
		
		$server->shutdown();
	}
	
	public function testSendOk(){
		$client = new Client();
		$client->setId(1);
		
		$this->assertEquals('* OK text1'.Client::MSG_SEPARATOR, $client->sendOk('text1'));
		$this->assertEquals('tag1 OK text1'.Client::MSG_SEPARATOR, $client->sendOk('text1', 'tag1'));
		$this->assertEquals('tag1 OK [code1] text1'.Client::MSG_SEPARATOR, $client->sendOk('text1', 'tag1', 'code1'));
		$this->assertEquals('* OK [code1] text1'.Client::MSG_SEPARATOR, $client->sendOk('text1', null, 'code1'));
	}
	
	public function testSendNo(){
		$client = new Client();
		$client->setId(1);
		
		$this->assertEquals('* NO text1'.Client::MSG_SEPARATOR, $client->sendNo('text1'));
		$this->assertEquals('tag1 NO text1'.Client::MSG_SEPARATOR, $client->sendNo('text1', 'tag1'));
		$this->assertEquals('tag1 NO [code1] text1'.Client::MSG_SEPARATOR, $client->sendNo('text1', 'tag1', 'code1'));
		$this->assertEquals('* NO [code1] text1'.Client::MSG_SEPARATOR, $client->sendNo('text1', null, 'code1'));
	}
	
	public function testSendBad(){
		$client = new Client();
		$client->setId(1);
		
		$this->assertEquals('* BAD text1'.Client::MSG_SEPARATOR, $client->sendBad('text1'));
		$this->assertEquals('tag1 BAD text1'.Client::MSG_SEPARATOR, $client->sendBad('text1', 'tag1'));
		$this->assertEquals('tag1 BAD [code1] text1'.Client::MSG_SEPARATOR, $client->sendBad('text1', 'tag1', 'code1'));
		$this->assertEquals('* BAD [code1] text1'.Client::MSG_SEPARATOR, $client->sendBad('text1', null, 'code1'));
	}
	
	public function testSendPreauth(){
		$client = new Client();
		$client->setId(1);
		
		$this->assertEquals('* PREAUTH text1'.Client::MSG_SEPARATOR, $client->sendPreauth('text1'));
		$this->assertEquals('* PREAUTH [code1] text1'.Client::MSG_SEPARATOR, $client->sendPreauth('text1', 'code1'));
	}
	
	public function testSendBye(){
		$client = new Client();
		$client->setId(1);
		
		$this->assertEquals('* BYE text1'.Client::MSG_SEPARATOR, $client->sendBye('text1'));
		$this->assertEquals('* BYE [code1] text1'.Client::MSG_SEPARATOR, $client->sendBye('text1', 'code1'));
	}
	
	public function testSelect(){
		$server = new Server('', 0);
		$server->init();
		$server->storageAddMaildir('./tests/test_mailbox_'.date('Ymd_His').'_'.uniqid('', true));
		$server->storageFolderAdd('test_dir1');
		$server->storageFolderAdd('test_dir2');
		
		$client1 = new Client();
		$client1->setServer($server);
		$client1->setId(1);
		$client2 = new Client();
		$client2->setServer($server);
		$client2->setId(2);
		
		$storage = $server->getStorageMailbox();
		
		$curr = $storage['object']->getCurrentFolder();
		$this->assertEquals('INBOX', $curr);
		
		$client1->select('test_dir1');
		$curr = $storage['object']->getCurrentFolder();
		$this->assertEquals('test_dir1', $curr);
		
		$client1->select('test_dir2');
		$curr = $storage['object']->getCurrentFolder();
		$this->assertEquals('test_dir2', $curr);
		
		$client2->select('test_dir1');
		$curr = $storage['object']->getCurrentFolder();
		$this->assertEquals('test_dir1', $curr);
		
		$client1->select();
		$curr = $storage['object']->getCurrentFolder();
		$this->assertEquals('test_dir2', $curr);
		
		$client2->select();
		$curr = $storage['object']->getCurrentFolder();
		$this->assertEquals('test_dir1', $curr);
	}
	
}
