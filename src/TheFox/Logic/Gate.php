<?php

namespace TheFox\Logic;

class Gate
{
    /**
     * @var null|Obj|Gate
     */
    private $obj1;

    /**
     * @var null|Obj|Gate
     */
    private $obj2;

    /**
     * @todo unit test
     * @return string
     */
    function __toString(): string
    {
        return 'Gate[' . $this->obj1 . ',' . $this->obj2 . ']';
    }

    public function __clone()
    {
        if ($this->obj1 && is_object($this->obj1)) {
            $this->obj1 = clone $this->obj1;
        }
        if ($this->obj2 && is_object($this->obj2)) {
            $this->obj2 = clone $this->obj2;
        }
    }

    /**
     * @param Obj|Gate $obj1
     */
    public function setObj1($obj1)
    {
        $this->obj1 = $obj1;
    }

    /**
     * @return Obj|Gate
     */
    public function getObj1()
    {
        return $this->obj1;
    }

    /**
     * @param Obj|Gate $obj2
     */
    public function setObj2($obj2)
    {
        $this->obj2 = $obj2;
    }

    /**
     * @return null|Obj|Gate
     */
    public function getObj2()
    {
        return $this->obj2;
    }

    /**
     * @return bool
     */
    public function getBool(): bool
    {
        return false;
    }
}
