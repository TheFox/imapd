#!/usr/bin/env php
<?php

require_once __DIR__.'/bootstrap.php';

use Symfony\Component\Console\Application;

use TheFox\Console\Command\ServerCommand;

$application = new Application('IMAPd', '0.1.2');
$application->add(new ServerCommand());
$application->run();
