<?php

namespace TheFox\Test;

use PHPUnit_Framework_TestCase;
use Symfony\Component\Finder\Finder;
use TheFox\Storage\YamlStorage;

class YamlStorageTest extends PHPUnit_Framework_TestCase
{
    public function testBasic()
    {
        $storage = new YamlStorage();

        $this->assertTrue(is_object($storage));
    }

    public function testSave()
    {
        $storage = new YamlStorage('test_data/test1.yml');
        $storage->data['test'] = ['test1' => 123, 'test2' => 'test3'];

        $this->assertFalse($storage->getDataChanged());

        $storage->setDataChanged();
        $this->assertTrue($storage->getDataChanged());

        $storage->save();

        $finder = new Finder();
        $files = $finder->in('test_data')->name('test1.yml');
        $this->assertEquals(1, count($files));
    }

    public function testLoad1()
    {
        $storage = new YamlStorage('test_data/test1.yml');
        $storage->setDataChanged();
        $storage->save();

        $storage = new YamlStorage('test_data/test1.yml');
        $storage->load();

        $this->assertTrue($storage->isLoaded());
    }

    public function testLoad2()
    {
        $storage = new YamlStorage('test_data/test2.yml');
        $storage->load();

        $this->assertFalse($storage->isLoaded());
    }

    public function testIsLoaded()
    {
        $storage = new YamlStorage();
        $this->assertFalse($storage->isLoaded());

        $storage->isLoaded(true);
        $this->assertTrue($storage->isLoaded());

        $storage->isLoaded(false);
        $this->assertFalse($storage->isLoaded());
    }

    public function testSetDatadirBasePath()
    {
        $storage = new YamlStorage();
        $this->assertEquals(null, $storage->getDatadirBasePath());

        $storage->setDatadirBasePath('test_data');

        $this->assertEquals('test_data', $storage->getDatadirBasePath());
    }
}
