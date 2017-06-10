<?php

namespace TheFox\Test;

use PHPUnit_Framework_TestCase;
use Zend\Mail\Storage;

class ZendMailStorageTest extends PHPUnit_Framework_TestCase
{
    public function providerFlags()
    {
        $rv = [];
        $rv[] = [Storage::FLAG_SEEN, '\Seen'];
        $rv[] = [Storage::FLAG_ANSWERED, '\Answered'];
        $rv[] = [Storage::FLAG_FLAGGED, '\Flagged'];
        $rv[] = [Storage::FLAG_DELETED, '\Deleted'];
        $rv[] = [Storage::FLAG_DRAFT, '\Draft'];
        $rv[] = [Storage::FLAG_RECENT, '\Recent'];
        return $rv;
    }

    /**
     * @dataProvider providerFlags
     */
    public function testFlags($flag, $expect)
    {
        $this->assertEquals($expect, $flag);
    }
}
