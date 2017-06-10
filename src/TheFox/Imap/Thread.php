<?php

/**
 * Pseudo Thread
 */

namespace TheFox\Imap;

class Thread
{
    private $exit = 0;

    public function setExit($exit = 1)
    {
        $this->exit = $exit;
    }

    public function getExit()
    {
        return (int)$this->exit;
    }
}
