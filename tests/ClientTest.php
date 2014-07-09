<?php

use Zend\Mail\Message;
use Zend\Mail\Storage;
use Symfony\Component\Finder\Finder;

use TheFox\Imap\Server;
use TheFox\Imap\Client;

class ClientTest extends PHPUnit_Framework_TestCase{
	
	public function providerMsgParseString(){
		$rv = array();
		
		$expect = array('arg1', 'arg2', 'arg3 arg4');
		$rv[] = array('arg1 arg2 arg3 arg4', $expect, 3);
		$rv[] = array('arg1  arg2 arg3 arg4', $expect, 3);
		$rv[] = array('arg1 arg2  arg3 arg4', $expect, 3);
		$rv[] = array('arg1  arg2  arg3 arg4', $expect, 3);
		
		$expect = array('arg1', 'arg2', 'arg3  arg4');
		$rv[] = array('arg1 arg2 arg3  arg4', $expect, 3);
		$rv[] = array('arg1  arg2  arg3  arg4', $expect, 3);
		
		$expect = array('arg1', 'arg2', '0');
		$rv[] = array('arg1 arg2 0', $expect, 3);
		
		$expect = array('arg1', 'arg2', 0);
		$rv[] = array('arg1 arg2 0', $expect, 3);
		
		$expect = array('arg1', 'arg2', '000');
		$rv[] = array('arg1 arg2 000', $expect, 3);
		
		$expect = array('arg1', 'arg2', '123');
		$rv[] = array('arg1 arg2 123', $expect, 3);
		
		$expect = array('arg1', 'arg2', '0123');
		$rv[] = array('arg1 arg2 0123', $expect, 3);
		
		return $rv;
	}
	
	/**
     * @dataProvider providerMsgParseString
     */
	public function testMsgParseString($msgRaw, $expect, $argsMax){
		$client = new Client();
		$this->assertEquals($expect, $client->msgParseString($msgRaw, $argsMax));
	}
	
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
			array('(a b)', array('a', 'b')),
			array('(a bb ccc)', array('a', 'bb', 'ccc')),
			array('(a (bb) ccc)', array('a', array('bb'), 'ccc')),
			array('(a (bb ccc) dddd)', array('a', array('bb', 'ccc'), 'dddd')),
			array('(a (bb ccc dddd) eeeee)', array('a', array('bb', 'ccc', 'dddd'), 'eeeee')),
			array('(a ((bb ccc) dddd) eeeee)', array('a', array(array('bb', 'ccc'), 'dddd'), 'eeeee')),
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
	}
	
	public function providerMsgHandle(){
		$rv = array(
			array('NO_COMMAND', 'NO_COMMAND BAD Not implemented: "NO_COMMAND" ""'),
			array('1 NO_COMMAND', '1 BAD Not implemented: "1" "NO_COMMAND"'),
			array('1 capability', '* CAPABILITY IMAP4rev1 AUTH=PLAIN'.Client::MSG_SEPARATOR.'1 OK CAPABILITY completed'),
			array('2 noop', '2 OK NOOP completed client 1, ""'),
			array('3 logout', '* BYE IMAP4rev1 Server logging out'),
			array('4 authenticate X', '4 NO X Unsupported authentication mechanism'),
			array('5 login thefox password', '5 OK LOGIN completed'),
			array('5 login thefox', '5 BAD Arguments invalid.'),
			#array('', ''),
			#array('', ''),
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
		$client = new Client();
		$client->setServer($server);
		$client->setId(1);
		
		$msg = $client->msgHandle($msgRaw);
		
		$this->assertEquals($expect, $msg);
		
		#$this->assertStringStartsWith($expect, $msg);
		#fwrite(STDOUT, "msg: '$msg'\n");
		
		#$this->markTestIncomplete('This test has not been implemented yet.');
	}
	
	public function testMsgHandleAuthenticate(){
		$server = new Server('', 0);
		$client = new Client();
		$client->setServer($server);
		$client->setId(1);
		
		$msg = $client->msgHandle('4 authenticate plain');
		$this->assertEquals('+'.Client::MSG_SEPARATOR, $msg);
		
		$msg = $client->msgHandle('AHRoZWZveAB0ZXN0');
		$this->assertEquals('4 OK plain authentication successful'.Client::MSG_SEPARATOR, $msg);
		
		#$this->assertStringStartsWith($expect, $msg);
		#fwrite(STDOUT, "msg: '$msg'\n");
		
		#$this->markTestIncomplete('This test has not been implemented yet.');
	}
	
	public function testMsgHandleSelect(){
		$server = new Server('', 0);
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
		$server->storageAddMaildir('./tests/test_mailbox_'.date('Ymd_His').'_'.uniqid('', true));
		
		$client = new Client();
		$client->setServer($server);
		$client->setId(1);
		
		$msg = $client->msgHandle('10 list');
		$this->assertEquals('10 NO list failure'.Client::MSG_SEPARATOR, $msg);
		
		$client->setStatus('hasAuth', true);
		
		$msg = $client->msgHandle('10 list');
		$this->assertEquals('10 BAD Arguments invalid.'.Client::MSG_SEPARATOR, $msg);
		
		$msg = $client->msgHandle('10 list test_dir');
		$this->assertEquals('10 NO LIST failure: no subfolder named test_dir'.Client::MSG_SEPARATOR, $msg);
		
		$msg = $client->msgHandle('10 list test_dir.*');
		$this->assertEquals('10 NO LIST failure: no subfolder named test_dir'.Client::MSG_SEPARATOR, $msg);
		
		
		$server->storageFolderAdd('test_dir');
		
		$msg = $client->msgHandle('10 list test_dir');
		$this->assertEquals('10 OK LIST completed'.Client::MSG_SEPARATOR, $msg);
		
		$msg = $client->msgHandle('10 list test_dir.*');
		$this->assertEquals('10 OK LIST completed'.Client::MSG_SEPARATOR, $msg);
		
		$server->storageFolderAdd('test_dir.test_subdir1');
		$msg = $client->msgHandle('10 list test_dir.*');
		$this->assertEquals('* LIST () "." "test_dir.test_subdir1"'.Client::MSG_SEPARATOR.'10 OK LIST completed'.Client::MSG_SEPARATOR, $msg);
	}
	
	public function testMsgHandleLsub(){
		$server = new Server('', 0);
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
		
		$server->storageFolderAdd('test_dir');
		$client->msgHandle('8 subscribe test_dir');
		$msg = $client->msgHandle('11 lsub test_dir');
		$this->assertEquals('* LSUB () "." "test_dir"'.Client::MSG_SEPARATOR.'11 OK LSUB completed'.Client::MSG_SEPARATOR, $msg);
	}
	
	/*public function testMsgHandleAppend(){
		$this->markTestIncomplete('This test has not been implemented yet.');
	}*/
	
	public function testMsgHandleCheck(){
		$server = new Server('', 0);
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
		$server->storageAddMaildir('./tests/test_mailbox_'.date('Ymd_His').'_'.uniqid('', true));
		$server->init();
		
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
		$server->mailAdd($message->toString(), null, array(Storage::FLAG_DELETED => Storage::FLAG_DELETED));
		
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
		$server->mailAdd($message->toString(), null, array(Storage::FLAG_DELETED => Storage::FLAG_DELETED));
		
		$msg = $client->msgHandle('14 expunge');
		$this->assertEquals('* 1 EXPUNGE'.Client::MSG_SEPARATOR.'* 2 EXPUNGE'.Client::MSG_SEPARATOR.'14 OK EXPUNGE completed'.Client::MSG_SEPARATOR, $msg);
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
		$server->mailAdd($message->toString(), null, array(Storage::FLAG_DELETED => Storage::FLAG_DELETED));
		
		$message = new Message();
		$message->addFrom('thefox21at@gmail.com');
		$message->addTo('thefox@fox21.at');
		$message->setSubject('my_subject 3');
		$message->setBody('my_body');
		$server->mailAdd($message->toString(), null, array(Storage::FLAG_DELETED => Storage::FLAG_DELETED));
		
		$msg = $client->msgHandle('14 expunge');
		$this->assertEquals('* 2 EXPUNGE'.Client::MSG_SEPARATOR.'* 2 EXPUNGE'.Client::MSG_SEPARATOR.'14 OK EXPUNGE completed'.Client::MSG_SEPARATOR, $msg);
	}
	
	/*public function testMsgHandleSearch(){
		$server = new Server('', 0);
		$server->storageAddMaildir('./tests/test_mailbox_'.date('Ymd_His').'_'.uniqid('', true));
		
		$client = new Client();
		$client->setServer($server);
		$client->setId(1);
	}*/
	
	/*public function testMsgHandleStore(){
		$server = new Server('', 0);
		$server->storageAddMaildir('./tests/test_mailbox_'.date('Ymd_His').'_'.uniqid('', true));
		
		$client = new Client();
		$client->setServer($server);
		$client->setId(1);
	}*/
	
	/*public function testMsgHandleCopy(){
		$server = new Server('', 0);
		$server->storageAddMaildir('./tests/test_mailbox_'.date('Ymd_His').'_'.uniqid('', true));
		
		$client = new Client();
		$client->setServer($server);
		$client->setId(1);
	}*/
	
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
		
		$msg = $client->msgHandle('15 UID copy 1');
		$this->assertEquals('15 BAD Arguments invalid.'.Client::MSG_SEPARATOR, $msg);
		
		#$msg = $client->msgHandle('15 UID copy 1 test_dir3');
		#$this->assertEquals('x', $msg);
		
		$msg = $client->msgHandle('15 UID copy 1 test_dir2');
		$this->assertEquals('15 OK COPY completed'.Client::MSG_SEPARATOR, $msg);
		
		
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
		
		#$client->msgHandle('6 select test_dir2');
		#$client->msgHandle('6 select test_dir1');
		
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
		
		$server->shutdown();
	}
	
}
