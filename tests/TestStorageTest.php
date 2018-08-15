<?php

namespace TheFox\Test;

use PHPUnit\Framework\TestCase;
use TheFox\Imap\Storage\TestStorage;

class TestStorageTest extends TestCase
{
    public function testGetDirectorySeperator()
    {
        $storage = new TestStorage();

        $this->assertEquals('_', $storage->getDirectorySeperator());
    }
}
