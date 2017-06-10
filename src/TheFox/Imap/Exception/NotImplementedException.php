<?php

namespace TheFox\Imap\Exception;

use Exception;

class NotImplementedException extends Exception
{
    /**
     * @var string
     */
    protected $message = 'Not implemented yet.';
}
