<?php

namespace TheFox\Test;

use PHPUnit_Framework_TestCase;
use TheFox\Imap\Storage\DirectoryStorage;

class DirectoryStorageTest extends PHPUnit_Framework_TestCase
{
    public function testGetDirectorySeperator()
    {
        $storage = new DirectoryStorage();

        $this->assertEquals(DIRECTORY_SEPARATOR, $storage->getDirectorySeperator());
    }

    public function testSetPath()
    {
        $path = './tmp/test_data/test_mailbox_' . date('Ymd_His') . '_' . uniqid('', true);

        $storage = new DirectoryStorage();
        $storage->setPath($path);

        $this->assertTrue(file_exists($path));
    }
}
