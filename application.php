#!/usr/bin/env php
<?php

require_once __DIR__.'/bootstrap.php';

use Symfony\Component\Console\Application;

use TheFox\Console\Command\InfoCommand;
use TheFox\Console\Command\ServerCommand;
use TheFox\Imap\Imapd;

$application = new Application(Imapd::NAME, Imapd::VERSION);
$application->add(new InfoCommand());
$application->add(new ServerCommand());
$application->run();
