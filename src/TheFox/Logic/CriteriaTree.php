<?php

namespace TheFox\Logic;

class CriteriaTree
{
    /**
     * @var array
     */
    private $criteria = [];

    /**
     * @var null|Gate
     */
    private $rootGate;

    /**
     * CriteriaTree constructor.
     * @param array $criteria
     */
    public function __construct(array $criteria = [])
    {
        if ($criteria) {
            $this->setCriteria($criteria);
        }
    }

    /**
     * @param array $criteria
     */
    public function setCriteria(array $criteria)
    {
        $this->criteria = $criteria;
    }

    /**
     * @return null|Gate
     */
    public function getRootGate()
    {
        return $this->rootGate;
    }

    /**
     * @param int $level
     * @return Gate|Obj
     */
    public function build($level = 0)
    {
        //$func = __FUNCTION__;
        //$rep = '-';

        /** @var null|Gate $rootGate */
        $rootGate = null;

        /** @var $gate null|Gate */
        $gate = null;

        $obj1 = null;

        $critLen = count($this->criteria);
        $realCriteriaC = 0;
        for ($criteriumId = 0; $criteriumId < $critLen; $criteriumId++) {
            $criterium = $this->criteria[$criteriumId];

            if (is_array($criterium)) {
                $tree = new CriteriaTree($criterium);
                $subobj = $tree->build($level + 1);

                if ($gate) {
                    $gate->setObj2($subobj);
                } else {
                    if ($obj1 === null) {
                        $rootGate = $obj1 = $subobj;
                    }
                }

                $realCriteriaC++;
            } else {
                $criteriumcmp = strtolower($criterium);

                if ($criteriumcmp == 'or') {
                    if ($gate) {
                        $oldGate = $gate;
                        $rootGate = $gate = new OrGate();
                        $gate->setObj1($oldGate);
                    } else {
                        $rootGate = $gate = new OrGate();
                        if ($obj1 !== null) {
                            $gate->setObj1($obj1);
                            $obj1 = null;
                        }
                    }
                } elseif ($criteriumcmp == 'and') {
                    if ($gate) {
                        $oldGate = $gate;
                        $gate = new AndGate();
                        if ($oldGate instanceof NotGate) {
                            $rootGate = $gate;
                            $gate->setObj1($oldGate);
                        } else {
                            $gate->setObj1($oldGate->getObj2());
                            $oldGate->setObj2($gate);
                        }
                    } else {
                        $rootGate = $gate = new AndGate();
                        if ($obj1 !== null) {
                            $gate->setObj1($obj1);
                            $obj1 = null;
                        }
                    }
                } elseif ($criteriumcmp == 'not') {
                    if ($gate) {
                        $newGate = new NotGate();
                        $gate->setObj2($newGate);
                    } else {
                        $rootGate = $gate = new NotGate();
                    }
                } else {
                    if ($gate) {
                        if ($gate instanceof OrGate) {
                            if ($gate->getObj2() && $gate->getObj2() instanceof NotGate) {
                                $gate->getObj2()->setObj(new Obj($criterium));
                            } else {
                                $gate->setObj2(new Obj($criterium));
                            }
                        } elseif ($gate instanceof AndGate) {
                            if ($gate->getObj2() && $gate->getObj2() instanceof NotGate) {
                                $gate->getObj2()->setObj(new Obj($criterium));
                            } else {
                                $gate->setObj2(new Obj($criterium));
                            }
                        } elseif ($gate instanceof NotGate) {
                            $gate->setObj1(new Obj($criterium));
                        }
                    } else {
                        if ($obj1 === null) {
                            $rootGate = $obj1 = new Obj($criterium);
                        }
                    }
                }
            }
        }

        $this->rootGate = $rootGate;
        
        return $rootGate;
    }


    /**
     * @todo unit test
     * @return bool
     */
    public function getBool(): bool
    {
        $gate = $this->getRootGate();
        if ($gate) {
            return $gate->getBool();
        }
        return false;
    }
}
