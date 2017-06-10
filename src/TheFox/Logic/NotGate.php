<?php

namespace TheFox\Logic;

class NotGate extends Gate
{
    /**
     * NotGate constructor.
     * @param Obj|null $obj
     */
    public function __construct(Obj $obj = null)
    {
        if ($obj) {
            $this->setObj($obj);
        }
    }

    /**
     * @param Obj $obj
     */
    public function setObj(Obj $obj)
    {
        $this->setObj1($obj);
    }

    /**
     * @return bool
     */
    public function getBool(): bool
    {
        $bool = false;
        if ($this->getObj1()) {
            $bool = $this->getObj1()->getBool();
        }
        return !$bool;
    }
}
