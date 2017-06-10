<?php

namespace TheFox\Logger;

class StreamHandler
{
    /**
     * @var string
     */
    private $path;
    
    // @todo type?
    private $level;

    public function __construct(string $path, $level)
    {
        $this->setPath($path);
        $this->setLevel($level);
    }

    /**
     * @param string $path
     */
    public function setPath(string $path)
    {
        $this->path = $path;
    }

    /**
     * @return string
     */
    public function getPath()
    {
        return $this->path;
    }

    public function getLevel()
    {
        return $this->level;
    }

    public function setLevel($level)
    {
        $this->level = $level;
    }
}
