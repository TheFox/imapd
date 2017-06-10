<?php

namespace TheFox\Logic;

class Gate
{
    private $obj1;
    private $obj2;

    public function __clone()
    {
        if ($this->obj1 && is_object($this->obj1)) {
            $this->obj1 = clone $this->obj1;
        }
        if ($this->obj2 && is_object($this->obj2)) {
            $this->obj2 = clone $this->obj2;
        }
    }

    public function setObj1($obj1)
    {
        $this->obj1 = $obj1;
    }

    public function getObj1()
    {
        return $this->obj1;
    }

    public function setObj2($obj2)
    {
        $this->obj2 = $obj2;
    }

    public function getObj2()
    {
        return $this->obj2;
    }

    public function bool()
    {
        return null;
    }
}
