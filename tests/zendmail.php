<?php

require_once __DIR__.'/bootstrap.php';

use Zend\Mail\Storage\Writable\Maildir;
use Zend\Mail\Message;
use Zend\Mail\Storage;

$settings = array('dirname' => 'test_maildir');
#Maildir::initMaildir($settings['dirname']);

$mail = new Maildir($settings);
#\Doctrine\Common\Util\Debug::dump($mail);

$message = new Message();
$message->addFrom('dev1@fox21.at');
$message->addTo('dev2@fox21.at');
$message->setSubject('my_subject '.time());
$message->setBody('my_body');

#$mail->appendMessage($message->toString(), null, null, false);
#$mail->appendMessage($message->toString(), null, null, true);
#$mail->appendMessage($message->toString(), null, array(), false);
#$mail->appendMessage($message->toString(), null, array(), true);
$mail->appendMessage($message->toString(), null, array(Storage::FLAG_DRAFT), false);
#$mail->appendMessage($message->toString(), null, array(Storage::FLAG_DRAFT), true);
