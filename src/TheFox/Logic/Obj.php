<?php

namespace TheFox\Logic;

class Obj
{
    /**
     * @var mixed
     */
    private $value;

    /**
     * Obj constructor.
     * @param mixed $value
     */
    public function __construct($value = null)
    {
        $this->setValue($value);
    }

    /**
     * @todo unit test
     * @return string
     */
    function __toString(): string
    {
        return 'Obj[' . $this->value . ']';
    }

    public function __clone()
    {
        if ($this->value && is_object($this->value)) {
            $this->value = clone $this->value;
        }
    }

    /**
     * @param mixed $value
     */
    public function setValue($value)
    {
        $this->value = $value;
    }

    /**
     * @return mixed
     */
    public function getValue()
    {
        return $this->value;
    }

    /**
     * @return bool
     */
    public function getBool(): bool
    {
        return $this->value;
    }
}
