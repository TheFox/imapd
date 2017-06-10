<?php

namespace TheFox\Logic;

class AndGate extends Gate
{
    /**
     * @return bool
     */
    public function getBool(): bool
    {
        if ($this->getObj1() && $this->getObj1()->getBool() && $this->getObj2() && $this->getObj2()->getBool()) {
            return true;
        }
        return false;
    }
}
