<?php

namespace TheFox\Logic;

class OrGate extends Gate
{
    public function bool()
    {
        if ($this->getObj1() && $this->getObj1()->bool() || $this->getObj2() && $this->getObj2()->bool()) {
            return true;
        }
        return false;
    }
}
