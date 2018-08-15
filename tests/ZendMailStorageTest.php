<?php

namespace TheFox\Test;

use PHPUnit\Framework\TestCase;
use Zend\Mail\Storage;

class ZendMailStorageTest extends TestCase
{
    /**
     * @return array
     */
    public function providerFlags(): array
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
     * @param string $flag
     * @param string $expect
     */
    public function testFlags(string $flag, string $expect)
    {
        $this->assertEquals($expect, $flag);
    }
}
